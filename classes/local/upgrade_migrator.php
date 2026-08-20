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
 * inherited token is retired when that task reaches its network call rather than when the call
 * succeeds, and stable pending values make every task retry idempotent.
 */
final class upgrade_migrator {
    /** External service shortname. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /**
     * Longest an inherited token stays usable when the migration task never runs at all.
     *
     * This is a backstop, not the primary defence: run() deletes the token itself as soon as it
     * reaches the network call, so the only sites that reach this deadline are ones where the
     * ad-hoc task was lost or cron is not running. Matched to the hourly cadence of the
     * scheduled task that recovers a stalled migration, so a site that can recover still does.
     */
    public const LEGACY_TOKEN_GRACE = HOURSECS;

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
        // Each of these exits also clears the blocked flag: there is nothing left to retry,
        // so a stale flag would keep warning the administrator about work that cannot run.
        if (self::get_api_key() === null) {
            self::clear_blocked();
            return;
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        if (!$service) {
            self::clear_blocked();
            return;
        }
        $tokens = $DB->get_records('external_tokens', [
            'externalserviceid' => (int)$service->id,
            'tokentype' => 0,
        ]);
        if (!$tokens) {
            self::clear_blocked();
            return;
        }
        $completedat = (int)get_config('local_corolair', 'legacycredentialmigrationcompletedat');
        if ($completedat > 0) {
            $activeid = (int)get_config('local_corolair', 'webservicetokenid');
            if ($activeid > 0 && isset($tokens[$activeid]) && (int)$tokens[$activeid]->validuntil > time()) {
                self::clear_blocked();
                return;
            }
        }
        $adminid = self::resolve_admin_id((int)$service->id);
        if ($adminid <= 0) {
            // Called from db/upgrade.php, throwing here would abort the upgrade for the
            // whole site. That does not protect the inherited credentials -- they stay
            // exactly as exposed either way -- so record the state, let the upgrade
            // finish, and let retry_if_blocked() pick it up once an admin exists.
            set_config('legacycredentialmigrationblocked', 1, 'local_corolair');
            return;
        }
        self::clear_blocked();

        self::grandfather_consent($adminid);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        // From here the inherited credentials are committed to being replaced, so the
        // inherited token gets a deadline it keeps even if the replacement never confirms.
        self::bound_legacy_token_lifetime();

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
     * Retry a migration that could not be queued during the upgrade.
     *
     * Called hourly from the scheduled token task. The blocked flag is set when
     * migrate_if_required() ran while the site had no administrator able to own the
     * integration; once one exists the migration queues itself and the flag clears.
     *
     * @return void
     */
    public static function retry_if_blocked(): void {
        if (!(bool)get_config('local_corolair', 'legacycredentialmigrationblocked')) {
            return;
        }
        self::migrate_if_required();
    }

    /**
     * Recover a migration that is pending but has nothing left to run it.
     *
     * The pending flag gates a great deal: webservice_token_manager::maintain() stands down on
     * it, and the registration task defers to it. Nothing re-queues it, though. retry_if_blocked()
     * only ever looks at the blocked flag, which covers the migration never being queued at all
     * and not the queued task later disappearing -- a purged queue, a restored database, an
     * administrator deleting a repeatedly failing task. Left alone, that combination stops the
     * token lifecycle indefinitely while the inherited credential stays live.
     *
     * Called hourly from the scheduled token task, ahead of maintenance.
     *
     * @return void
     */
    public static function requeue_if_stalled(): void {
        if (!(bool)get_config('local_corolair', 'legacycredentialmigrationpending')) {
            return;
        }
        // The completion timestamp is recorded in run() several statements before it clears the
        // pending flag, so a process dying in between leaves the flag set on a site that has already
        // migrated. That state is self-sustaining: migrate_if_required() reads it as "nothing to
        // migrate" and returns without queueing anything, so the flag would never be cleared and
        // maintenance would never resume. Settle it here, using the same predicate
        // migrate_if_required() uses to reach that conclusion.
        if (self::migration_already_complete()) {
            unset_config('legacycredentialmigrationpending', 'local_corolair');
            return;
        }
        if (\core\task\manager::get_adhoc_tasks('\local_corolair\task\migrate_legacy_credentials_task')) {
            // Still queued, or running right now -- a running task keeps its task_adhoc record.
            // Core retries it on its own backoff; queueing a second one would only duplicate work.
            return;
        }
        self::migrate_if_required();
    }

    /**
     * Whether a completed migration left a usable active token behind.
     *
     * @return bool
     */
    private static function migration_already_complete(): bool {
        global $DB;

        if ((int)get_config('local_corolair', 'legacycredentialmigrationcompletedat') <= 0) {
            return false;
        }
        $activeid = (int)get_config('local_corolair', 'webservicetokenid');
        if ($activeid <= 0) {
            return false;
        }
        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => self::SERVICE_SHORTNAME]);
        if ($serviceid <= 0) {
            return false;
        }
        return $DB->record_exists_select(
            'external_tokens',
            'id = :id AND externalserviceid = :serviceid AND tokentype = 0 AND validuntil > :now',
            ['id' => $activeid, 'serviceid' => $serviceid, 'now' => time()]
        );
    }

    /**
     * Clear the "migration could not be queued" flag.
     *
     * @return void
     */
    private static function clear_blocked(): void {
        unset_config('legacycredentialmigrationblocked', 'local_corolair');
    }

    /**
     * Give any inherited token a deadline it keeps regardless of the remote replacement.
     *
     * run() deletes the inherited token before it calls Raison, so an unreachable backend or an
     * API key the backend rejects no longer extends the exposure. What remains is the case run()
     * never reaches at all: a lost ad-hoc task, a purged queue, a site whose cron does not run.
     * On those sites nothing else expires a pre-1.9 credential, and it is worth bounding: it was
     * minted as md5(uniqid(rand(), true)) rather than from a CSPRNG, it never expires, and older
     * releases put it in a troubleshoot URL query string, so it may already sit in browser
     * history and proxy logs. What it opens is the whole corolair_rest service, owned by an
     * administrator -- and the restrictedusers flip does not close it, because the upgrade
     * authorises every current token owner so the live integration survives that flip.
     *
     * Bounding it costs nothing when the migration behaves: the first cron cycle runs the task,
     * and delete_legacy_tokens() removes the token outright well inside the deadline. Nor can the
     * deadline strand the migration, because the migration never uses this token -- it mints its
     * own in get_or_create_migration_token() and authenticates with the API key -- so the swap
     * still completes whenever Raison returns. The grace period is purely how long the
     * integration keeps working on a site whose task never runs.
     *
     * Matching "no expiry" and nothing else is the entire rule, and widening it would be a bug.
     * Every pre-1.9 release stamped validuntil = 0, so the test selects exactly the inherited
     * credential; it is self-idempotent, since a stamped row no longer matches and a later call
     * cannot push the deadline back out; and it cannot touch the fifteen-day candidate this class
     * has already minted, which a "further away than the deadline" test would silently cut down
     * to the grace period on any re-entry.
     *
     * @return void
     */
    public static function bound_legacy_token_lifetime(): void {
        global $DB;

        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => self::SERVICE_SHORTNAME]);
        if ($serviceid <= 0) {
            return;
        }
        $DB->set_field_select(
            'external_tokens',
            'validuntil',
            time() + self::LEGACY_TOKEN_GRACE,
            'externalserviceid = :serviceid AND (validuntil = 0 OR validuntil IS NULL)',
            ['serviceid' => $serviceid]
        );
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

        // The service is restricted to authorised users, and this owner is resolved
        // independently of the ordinary token owner -- resolve_admin_id() can fall back to
        // get_admin(), who may own no token at all and therefore was never authorised by the
        // upgrade step. Without this the migration would mint a token that is dead the moment
        // it is used, and the migration exists precisely to retire a credential that must not
        // stay live.
        service_account_provisioner::ensure_authorised($serviceid, $adminid);

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

        // Retire the inherited credential before the network call rather than after it. Deleting
        // it afterwards made the exposure window a function of Raison's availability -- a backend
        // that is down, an API key it rejects, or a task that keeps failing each kept a
        // presumed-compromised token live for as long as that lasted. Nothing in the exchange
        // needs it: the request authenticates with the API key, and Raison verifies the
        // replacement by calling back with $newtoken, which is minted and authorised above.
        //
        // Everything that can fail locally has already failed by this point, so a site that can
        // never migrate does not lose its token here. What this does cost is that Raison still
        // holds the old token until the swap confirms, so calls in between fail: one HTTP round
        // trip on the normal path, and on the failure path an outage an administrator can see
        // and act on, in place of a live credential nobody can see.
        self::delete_legacy_tokens($serviceid, (int)$newtoken->id);

        self::migrate_remotely(
            $newtoken,
            $migrationid,
            $replacementsecret,
            $legacyapikey,
            $replacementapikey
        );

        set_config('apikey', $replacementapikey, 'local_corolair');
        webservice_token_manager::record_initial_token($newtoken);
        self::assert_migration_complete($serviceid, $newtoken);
        set_config('setupcompleted', 1, 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');
        unset_config('legacymigrationtokenid', 'local_corolair');
        unset_config('legacycredentialmigrationid', 'local_corolair');
        unset_config('legacycredentialmigrationattempted', 'local_corolair');
        unset_config('legacyreplacementapikeysecret', 'local_corolair');
        unset_config('legacycredentialmigrationpending', 'local_corolair');
    }

    /**
     * Resolve the integration owner: the legacy token owner when usable, else the primary admin.
     *
     * This is deliberately still an administrator, and is not the service account. Retiring
     * an inherited credential also grandfathers consent for a site that predates the consent
     * record, and consent is a human act -- see grandfather_consent(). Ownership of the
     * resulting token converges to the service account on the next maintenance run, which is
     * the path that exists for exactly that purpose.
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
        // Always mint a normally expiring token, even when the site has disabled rotation.
        // The migration endpoint requires a bounded expiration, and its retry path compares
        // the supplied expiration against the stored one to recognise a replay -- a
        // comparison that cannot succeed with no expiration on either side. Retiring an
        // exposed legacy credential must not depend on any of that. The next maintenance
        // run converges this token to the configured lifetime.
        $token = webservice_token_manager::create_token(
            $adminid,
            $serviceid,
            webservice_token_manager::TOKEN_LIFETIME
        );
        set_config('legacymigrationtokenid', (int)$token->id, 'local_corolair');
        return $token;
    }

    /**
     * Return a stable RFC 4122 version-4 identifier for retries.
     *
     * @return string Migration identifier.
     */
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

    /**
     * Return the stable plugin-generated replacement API-key secret.
     *
     * @return string Replacement secret.
     */
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

    /**
     * Send one authenticated migration attempt.
     *
     * @param \curl $curl Moodle curl client.
     * @param string $postdata Encoded request body.
     * @param string $apikey API key used to authenticate the request.
     * @return array Response body, HTTP status, and curl error number.
     */
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

    /**
     * Extract a bounded non-sensitive error description from a backend response.
     *
     * @param mixed $response Backend response body.
     * @return string Safe error description.
     */
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

    /**
     * Confirm the local post-migration invariants before allowing the upgrade to complete.
     *
     * @param int $serviceid External service ID.
     * @param \stdClass $token Activated token record.
     * @return void
     */
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
        return api_key::get();
    }
}
