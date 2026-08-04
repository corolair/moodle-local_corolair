<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * One-time migration of legacy Corolair credentials during upgrade.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Invalidates credentials inherited from pre-1.9.0 installs and reissues fresh ones.
 *
 * Older versions issued a non-expiring web-service token and delivered the API key to the
 * browser. Upgrading to the token lifecycle introduced in 1.9.0 does not, by itself, retire
 * those exposed credentials. This migrator rotates them: it grandfathers the existing
 * consent, mints a fresh CSPRNG token, re-registers with Raison (which reissues the API key
 * and invalidates the previous one server-side), and then deletes the legacy token.
 *
 * The network step is deferred to an ad-hoc task because Raison verifies the candidate token by
 * calling back into Moodle. Web services may be unavailable while an upgrade is running. The
 * inherited credentials remain active until the task verifiably replaces them, and stable pending
 * values make every task retry idempotent.
 */
final class upgrade_migrator {
    /** External service shortname. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /** Authenticated endpoint that atomically replaces both inherited credentials. */
    private const MIGRATION_ENDPOINT =
        'https://services.corolair.dev/moodle-integration/v2/plugin/organization/legacy-credentials/migrate';

    /**
     * Detect a connected legacy installation and schedule its credential migration.
     *
     * This method intentionally performs local work only so it is safe to invoke from
     * db/upgrade.php while Moodle web services may be unavailable.
     *
     * @return void
     */
    public static function migrate_if_required(): void {
        global $DB;

        // Only connected installs (a live API key + an existing service token) carry exposed
        // credentials worth rotating. Unconfigured installs have nothing to migrate.
        if (self::get_api_key() === null) {
            return;
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        if (!$service) {
            return;
        }
        $tokens = $DB->get_records('external_tokens', [
            'externalserviceid' => (int)$service->id,
            'tokentype' => 0,
        ]);
        if (!$tokens) {
            return;
        }
        $activeid = (int)get_config('local_corolair', 'webservicetokenid');
        if ($activeid > 0 && isset($tokens[$activeid]) && (int)$tokens[$activeid]->validuntil > time()) {
            return;
        }
        $adminid = self::resolve_admin_id((int)$service->id);
        if ($adminid <= 0) {
            throw new \moodle_exception('legacycredentialmigrationadminmissing', 'local_corolair');
        }

        self::grandfather_consent($adminid);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $task = new \local_corolair\task\migrate_legacy_credentials_task();
        $task->set_custom_data((object)['adminid' => $adminid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Backward-compatible name used by intermediate plugin versions.
     *
     * @return void
     */
    public static function schedule_if_required(): void {
        self::migrate_if_required();
    }

    /**
     * Perform an idempotent credential migration from the post-upgrade ad-hoc task.
     *
     * @param int $adminid Integration owner whose capabilities the token uses.
     * @return void
     */
    public static function run(int $adminid): void {
        global $DB;

        if (!(bool)get_config('local_corolair', 'legacycredentialmigrationpending')) {
            return;
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], '*', MUST_EXIST);
        $serviceid = (int)$service->id;

        // Reuse a token minted by a previous attempt to avoid orphaning tokens across retries.
        $newtoken = self::get_or_create_migration_token($adminid, $serviceid);

        $legacyapikey = self::get_api_key();
        if ($legacyapikey === null) {
            throw new \moodle_exception('noapikey', 'local_corolair');
        }
        $migrationid = self::get_or_create_migration_id();
        $replacementsecret = self::get_or_create_replacement_api_secret();
        $apikeyparts = explode('.', $legacyapikey, 2);
        if (count($apikeyparts) !== 2 || $apikeyparts[0] === '') {
            throw new \moodle_exception('legacycredentialmigrationfailed', 'local_corolair');
        }
        $replacementapikey = $apikeyparts[0] . '.' . $replacementsecret;

        self::migrate_remotely(
            $newtoken,
            $migrationid,
            $replacementsecret,
            $legacyapikey,
            $replacementapikey
        );

        set_config('apikey', $replacementapikey, 'local_corolair');
        webservice_token_manager::record_initial_token($newtoken);
        self::delete_legacy_tokens($serviceid, (int)$newtoken->id);
        self::assert_migration_complete($serviceid, $newtoken);
        set_config('setupcompleted', 1, 'local_corolair');
        unset_config('legacymigrationtokenid', 'local_corolair');
        unset_config('legacycredentialmigrationid', 'local_corolair');
        unset_config('legacycredentialmigrationattempted', 'local_corolair');
        unset_config('legacyreplacementapikeysecret', 'local_corolair');
        unset_config('legacycredentialmigrationpending', 'local_corolair');
    }

    /**
     * Resolve the integration owner: the legacy token owner when usable, else the primary admin.
     *
     * @param int $serviceid External service ID.
     * @return int User ID, or 0 when none is usable.
     */
    private static function resolve_admin_id(int $serviceid): int {
        global $DB;

        $configured = (int)get_config('local_corolair', 'setupconsentedby');
        if ($configured > 0 && self::is_usable_admin($configured)) {
            return $configured;
        }
        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'tokentype' => 0],
            'timecreated DESC',
            'id, userid',
            0,
            1
        );
        $token = reset($tokens);
        if ($token && self::is_usable_admin((int)$token->userid)) {
            return (int)$token->userid;
        }
        $admin = get_admin();
        return $admin ? (int)$admin->id : 0;
    }

    /**
     * Whether a user exists, is not deleted, and can administer the site.
     *
     * @param int $userid User ID.
     * @return bool
     */
    private static function is_usable_admin(int $userid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
            return false;
        }
        return has_capability('moodle/site:config', \context_system::instance(), $userid);
    }

    /**
     * Grandfather the existing integration as consented so the token lifecycle can operate.
     *
     * @param int $adminid Integration owner.
     * @return void
     */
    private static function grandfather_consent(int $adminid): void {
        if ((int)get_config('local_corolair', 'setupconsentedby') <= 0) {
            set_config('setupconsented', 1, 'local_corolair');
            set_config('setupconsentrequired', 0, 'local_corolair');
            set_config('setupconsentedby', $adminid, 'local_corolair');
            set_config('setupconsentedat', time(), 'local_corolair');
        }
        if ((int)get_config('local_corolair', 'setupdisclosureacknowledgedby') <= 0) {
            set_config('setupdisclosureversion', integration_disclosure::VERSION, 'local_corolair');
            set_config('setupdisclosureacknowledgedby', $adminid, 'local_corolair');
            set_config('setupdisclosureacknowledgedat', time(), 'local_corolair');
        }
    }

    /**
     * Reuse the pending migration token or mint a new CSPRNG token once.
     *
     * @param int $adminid Token owner.
     * @param int $serviceid External service ID.
     * @return \stdClass Token record.
     */
    private static function get_or_create_migration_token(int $adminid, int $serviceid): \stdClass {
        global $DB;

        $candidateid = (int)get_config('local_corolair', 'legacymigrationtokenid');
        if ($candidateid > 0) {
            $candidate = $DB->get_record('external_tokens', [
                'id' => $candidateid,
                'externalserviceid' => $serviceid,
                'userid' => $adminid,
                'tokentype' => 0,
            ]);
            if ($candidate && (int)$candidate->validuntil > time()) {
                return $candidate;
            }
            if ($candidate) {
                $DB->delete_records('external_tokens', ['id' => (int)$candidate->id]);
            }
        }
        $token = webservice_token_manager::create_token($adminid, $serviceid);
        set_config('legacymigrationtokenid', (int)$token->id, 'local_corolair');
        return $token;
    }

    /** Return a stable RFC 4122 version-4 identifier for retries. */
    private static function get_or_create_migration_id(): string {
        $migrationid = (string)get_config('local_corolair', 'legacycredentialmigrationid');
        if ($migrationid !== '') {
            return $migrationid;
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $migrationid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
        set_config('legacycredentialmigrationid', $migrationid, 'local_corolair');
        return $migrationid;
    }

    /** Return the stable plugin-generated replacement API-key secret. */
    private static function get_or_create_replacement_api_secret(): string {
        $secret = (string)get_config('local_corolair', 'legacyreplacementapikeysecret');
        if ($secret !== '') {
            return $secret;
        }
        $secret = bin2hex(random_bytes(32));
        set_config('legacyreplacementapikeysecret', $secret, 'local_corolair');
        return $secret;
    }

    /**
     * Atomically replace the inherited credentials in Raison.
     *
     * @param \stdClass $token Fresh token record.
     * @param string $migrationid Stable idempotency identifier.
     * @param string $replacementsecret Replacement API-key secret.
     * @param string $legacyapikey Inherited API key.
     * @param string $replacementapikey Complete replacement API key.
     * @return void
     * @throws \moodle_exception On any transport or contract failure.
     */
    private static function migrate_remotely(
        \stdClass $token,
        string $migrationid,
        string $replacementsecret,
        string $legacyapikey,
        string $replacementapikey
    ): void {
        $curl = new \curl();
        $postdata = json_encode([
            'migrationId' => $migrationid,
            'replacementApiKeySecret' => $replacementsecret,
            'webserviceToken' => $token->token,
            'expiresAt' => webservice_token_manager::expiration_iso8601($token),
        ]);
        if ($postdata === false) {
            throw new \moodle_exception('unexpectederror', 'local_corolair');
        }

        $attempted = (bool)get_config('local_corolair', 'legacycredentialmigrationattempted');
        $credentials = $attempted
            ? [$replacementapikey, $legacyapikey]
            : [$legacyapikey];
        set_config('legacycredentialmigrationattempted', 1, 'local_corolair');

        $jsonresponse = null;
        $laststatus = 0;
        $lasterror = '';
        foreach ($credentials as $apikey) {
            [$response, $httpstatus, $errno] = self::send_migration_request($curl, $postdata, $apikey);
            $laststatus = $httpstatus;
            $lasterror = self::safe_backend_error($response);
            if ($httpstatus === 401) {
                continue;
            }
            if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
                throw new \moodle_exception(
                    'legacycredentialmigrationfailed',
                    'local_corolair',
                    '',
                    null,
                    'HTTP ' . $httpstatus . '; curl ' . $errno . '; ' . $lasterror
                );
            }
            $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            break;
        }
        if (
            !is_array($jsonresponse) ||
            ($jsonresponse['status'] ?? null) !== 'activated' ||
            ($jsonresponse['migrationId'] ?? null) !== $migrationid
        ) {
            throw new \moodle_exception(
                'legacycredentialmigrationfailed',
                'local_corolair',
                '',
                null,
                'HTTP ' . $laststatus . '; ' . $lasterror
            );
        }
    }

    /** Send one authenticated migration attempt. */
    private static function send_migration_request(\curl $curl, string $postdata, string $apikey): array {
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            "CURLOPT_CONNECTTIMEOUT" => 15,
            "CURLOPT_TIMEOUT" => 60,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $postdata, $options) {
                return $curl->post(self::MIGRATION_ENDPOINT, $postdata, $options);
            },
            audited_request::OP_WEBSERVICE_TOKEN_ROTATION,
            \context_system::instance()
        );
        $errno = $curl->get_errno();
        $info = $curl->get_info();
        return [$response, (int)($info['http_code'] ?? 0), $errno];
    }

    /** Extract a bounded non-sensitive error description from a backend response. */
    private static function safe_backend_error($response): string {
        if (!is_string($response) || $response === '') {
            return 'empty_response';
        }
        try {
            $payload = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return 'invalid_json_response';
        }
        $message = $payload['message'] ?? $payload['error'] ?? 'unknown_backend_error';
        if (is_array($message)) {
            $message = implode('; ', array_map('strval', $message));
        }
        if (!is_string($message)) {
            return 'unknown_backend_error';
        }
        return substr(clean_param($message, PARAM_TEXT), 0, 240);
    }

    /**
     * Delete every service token except the freshly registered one.
     *
     * @param int $serviceid External service ID.
     * @param int $keeptokenid Token ID to preserve.
     * @return void
     */
    private static function delete_legacy_tokens(int $serviceid, int $keeptokenid): void {
        global $DB;

        $tokens = $DB->get_records('external_tokens', ['externalserviceid' => $serviceid], '', 'id');
        foreach ($tokens as $token) {
            if ((int)$token->id !== $keeptokenid) {
                $DB->delete_records('external_tokens', ['id' => (int)$token->id]);
            }
        }
    }

    /** Confirm the local post-migration invariants before allowing the upgrade to complete. */
    private static function assert_migration_complete(int $serviceid, \stdClass $token): void {
        global $DB;

        $tokens = $DB->get_records('external_tokens', [
            'externalserviceid' => $serviceid,
            'tokentype' => 0,
        ]);
        if (
            count($tokens) !== 1 ||
            !isset($tokens[(int)$token->id]) ||
            (int)$tokens[(int)$token->id]->validuntil <= time()
        ) {
            throw new \moodle_exception('legacycredentialmigrationfailed', 'local_corolair');
        }
    }

    /**
     * Return the configured API key, excluding translated placeholder values.
     *
     * @return string|null
     */
    private static function get_api_key(): ?string {
        $apikey = (string)get_config('local_corolair', 'apikey');
        if (
            $apikey === '' ||
            strpos($apikey, 'No Corolair Api Key') === 0 ||
            strpos($apikey, 'Aucune Clé API Corolair') === 0 ||
            strpos($apikey, 'No hay clave API de Corolair') === 0 ||
            strpos($apikey, 'No Raison Api Key') === 0 ||
            strpos($apikey, 'Aucune Clé API Raison') === 0 ||
            strpos($apikey, 'No hay clave API de Raison') === 0
        ) {
            return null;
        }
        return $apikey;
    }
}
