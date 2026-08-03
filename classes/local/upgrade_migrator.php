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
 * The network step is intentionally deferred to an adhoc task ({@see \local_corolair\task\migrate_legacy_credentials_task})
 * that runs after the upgrade completes, because registration requires Raison to call back
 * into a live site — which is not guaranteed while the upgrade (and possible maintenance
 * mode) is in progress. The legacy token is retained until the new one is confirmed, so a
 * working install keeps working; it is deleted only once re-registration succeeds.
 */
final class upgrade_migrator {
    /** External service shortname. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /** Registration endpoint (reissues and rotates the API key). */
    private const REGISTER_ENDPOINT =
        'https://services.corolair.dev/moodle-integration/plugin/organization/register';

    /**
     * Detect a connected legacy install during upgrade and schedule the credential rotation.
     *
     * Runs only synchronous, local work; the network re-registration is queued for after the
     * upgrade. Safe to call unconditionally from db/upgrade.php.
     *
     * @return void
     */
    public static function schedule_if_required(): void {
        global $DB;

        // Already on the new token lifecycle: nothing inherited to rotate.
        if ((int)get_config('local_corolair', 'webservicetokenid') > 0) {
            return;
        }
        // Only connected installs (a live API key + an existing service token) carry exposed
        // credentials worth rotating. Unconfigured installs have nothing to migrate.
        if (self::get_api_key() === null) {
            return;
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        if (!$service) {
            return;
        }
        if (!$DB->record_exists('external_tokens', ['externalserviceid' => (int)$service->id, 'tokentype' => 0])) {
            return;
        }
        $adminid = self::resolve_admin_id((int)$service->id);
        if ($adminid <= 0) {
            // No safe owner to act as; leave credentials untouched. A later interactive setup
            // will establish the lifecycle without this automatic path.
            return;
        }

        self::grandfather_consent($adminid);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $task = new \local_corolair\task\migrate_legacy_credentials_task();
        $task->set_custom_data((object)['adminid' => $adminid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Perform the deferred credential rotation. Throws on remote failure so the adhoc task retries.
     *
     * @param int $adminid Integration owner whose capabilities the token uses.
     * @return void
     */
    public static function run(int $adminid): void {
        global $DB;

        if (!(bool)get_config('local_corolair', 'legacycredentialmigrationpending')) {
            return;
        }
        if ((int)get_config('local_corolair', 'webservicetokenid') > 0) {
            // A concurrent/previous run already established the lifecycle.
            unset_config('legacycredentialmigrationpending', 'local_corolair');
            return;
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], '*', MUST_EXIST);
        $serviceid = (int)$service->id;

        // Reuse a token minted by a previous attempt to avoid orphaning tokens across retries.
        $newtoken = self::get_or_create_migration_token($adminid, $serviceid);

        // Re-register with the fresh token. This reissues the API key and, server-side, the
        // previous (browser-exposed) key stops validating.
        $newapikey = self::register($adminid, $newtoken);

        set_config('apikey', $newapikey, 'local_corolair');
        webservice_token_manager::record_initial_token($newtoken);
        self::delete_legacy_tokens($serviceid, (int)$newtoken->id);
        set_config('setupcompleted', 1, 'local_corolair');
        unset_config('legacymigrationtokenid', 'local_corolair');
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
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
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

    /**
     * Register the fresh token with Raison and return the reissued API key.
     *
     * @param int $adminid Integration owner.
     * @param \stdClass $token Fresh token record.
     * @return string Reissued API key.
     * @throws \moodle_exception On any transport or contract failure (task will retry).
     */
    private static function register(int $adminid, \stdClass $token): string {
        global $DB, $CFG, $SITE;

        $admin = $DB->get_record('user', ['id' => $adminid, 'deleted' => 0], '*', MUST_EXIST);
        $curl = new \curl();
        $postdata = json_encode([
            'url' => $CFG->wwwroot,
            'webserviceToken' => $token->token,
            'expiresAt' => webservice_token_manager::expiration_iso8601($token),
            'email' => $admin->email,
            'firstname' => $admin->firstname,
            'lastname' => $admin->lastname,
            'siteName' => $SITE->fullname,
        ]);
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            "CURLOPT_CONNECTTIMEOUT" => 15,
            "CURLOPT_TIMEOUT" => 60,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $postdata, $options) {
                return $curl->post(self::REGISTER_ENDPOINT, $postdata, $options);
            },
            audited_request::OP_ORGANIZATION_REGISTER,
            \context_system::instance(),
            $adminid
        );
        $errno = $curl->get_errno();
        $info = $curl->get_info();
        $httpstatus = (int)($info['http_code'] ?? 0);
        if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($jsonresponse) ||
            !isset($jsonresponse['apiKey']) ||
            !is_string($jsonresponse['apiKey']) ||
            $jsonresponse['apiKey'] === ''
        ) {
            throw new \moodle_exception('apikeymissing', 'local_corolair');
        }
        return $jsonresponse['apiKey'];
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
