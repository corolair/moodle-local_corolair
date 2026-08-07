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
 * Moodle web-service token lifecycle management for Corolair.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Creates, rotates, retries, warns about, and retires Corolair tokens.
 */
final class webservice_token_manager {
    /** Token lifetime: fifteen days. */
    public const TOKEN_LIFETIME = 15 * DAYSECS;

    /** Lifetime stamped on a token when an administrator has disabled rotation: effectively never. */
    public const NON_EXPIRING_LIFETIME = 100 * YEARSECS;

    /** Remaining lifetime above which a token was minted as non-expiring. */
    public const NON_EXPIRING_THRESHOLD = 10 * YEARSECS;

    /** Start rotation seven days before expiration. */
    public const ROTATE_BEFORE_EXPIRY = 7 * DAYSECS;

    /** Warn administrators five days before expiration if rotation is unresolved. */
    public const WARN_BEFORE_EXPIRY = 5 * DAYSECS;

    /** Warn about an unresolved lifetime change after this many failed attempts. */
    public const LIFETIME_CHANGE_WARN_AFTER = 48;

    /** Do not send the same warning more than once per day. */
    public const WARNING_INTERVAL = DAYSECS;

    /** Re-verify a non-rotating token remotely at most once a week. */
    public const VERIFY_INTERVAL = 7 * DAYSECS;

    /** External service shortname. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /** Corolair rotation endpoint. */
    private const ROTATION_ENDPOINT =
        'https://services.corolair.dev/moodle-integration/v2/plugin/organization/webservice-token/rotate';

    /** Corolair read-only token verification endpoint. */
    private const VERIFICATION_ENDPOINT =
        'https://services.corolair.dev/moodle-integration/v2/plugin/organization/webservice-token/verify';

    /**
     * Create a token that expires after the configured lifetime.
     *
     * @param int $userid Token owner.
     * @param int $serviceid External service ID.
     * @param int|null $lifetime Explicit lifetime, overriding the configured policy.
     * @return \stdClass Inserted token record.
     */
    public static function create_token(int $userid, int $serviceid, ?int $lifetime = null): \stdClass {
        global $DB;

        $now = time();
        $token = (object)[
            'token' => bin2hex(random_bytes(32)),
            'userid' => $userid,
            'tokentype' => 0,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $userid,
            'timecreated' => $now,
            'validuntil' => $now + ($lifetime ?? self::token_lifetime()),
            'externalserviceid' => $serviceid,
            'privatetoken' => random_string(64),
            'name' => get_string('tokenname', 'local_corolair'),
        ];
        $token->id = $DB->insert_record('external_tokens', $token);
        if (!$token->id) {
            throw new \moodle_exception('tokencreationerror', 'local_corolair');
        }
        return $token;
    }

    /**
     * Whether an administrator has opted out of token rotation.
     *
     * @return bool
     */
    public static function rotation_disabled(): bool {
        return (bool)get_config('local_corolair', 'disabletokenrotation');
    }

    /**
     * Whether the rotation policy is pinned in config.php rather than configurable.
     *
     * get_config() applies $CFG->forced_plugin_settings as an override on top of the stored
     * value, but set_config() does not consult it: the row is written and the next read still
     * returns the forced value, with no error. Anything offering the administrator a choice
     * has to check this first, or it will report success for a change that cannot happen.
     *
     * array_key_exists() rather than isset() so an explicitly forced null still counts, which
     * is how core decides the same question in admin_setting::is_readonly().
     *
     * @return bool
     */
    public static function rotation_setting_is_forced(): bool {
        global $CFG;

        $forced = $CFG->forced_plugin_settings ?? [];
        return is_array($forced)
            && array_key_exists('local_corolair', $forced)
            && is_array($forced['local_corolair'])
            && array_key_exists('disabletokenrotation', $forced['local_corolair']);
    }

    /**
     * Return the lifetime new tokens should be minted with under the current policy.
     *
     * @return int Seconds.
     */
    public static function token_lifetime(): int {
        return self::rotation_disabled() ? self::NON_EXPIRING_LIFETIME : self::TOKEN_LIFETIME;
    }

    /**
     * Whether a token was minted as non-expiring.
     *
     * This is deliberately a threshold rather than an equality: create_token() stamps
     * "now + NON_EXPIRING_LIFETIME" at mint time, so no two non-expiring tokens share a
     * value. Fifteen days is far below ten years, which is far below a hundred, so the
     * classification has a ninety-year margin in both directions.
     *
     * The !empty() guard matters. A pre-1.9 token records no expiration at all, and that
     * is not "never expires" -- it is "unknown", the exposed legacy credential the 1.9
     * lifecycle exists to retire. It must keep rotating immediately. See rotation_due().
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function is_non_expiring(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        return !empty($token->validuntil)
            && (int)$token->validuntil - $now > self::NON_EXPIRING_THRESHOLD;
    }

    /**
     * Whether a token's recorded lifetime still matches the configured desired lifetime.
     *
     * This is the whole convergence rule, and both directions of the rotation setting fall
     * out of it. It reads the desired state from configuration on every call rather than
     * from anything queued, so a value set by CLI set_config(), by $CFG->forced_plugin_settings
     * (which never fires an updated callback at all), or by a settings save whose ad-hoc task
     * was lost, all reach the same end state on the next scheduled run.
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function lifetime_matches_configuration(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        if (empty($token->validuntil) || (int)$token->validuntil <= $now) {
            return false;
        }
        if ((int)$token->validuntil > $now + self::token_lifetime()) {
            return false;
        }
        return self::is_non_expiring($token, $now) === self::rotation_disabled();
    }

    /**
     * Determine whether a token needs rotation.
     *
     * A token whose lifetime no longer matches the configured policy is rotated regardless
     * of how far away its expiration is. Without that clause, re-enabling rotation would
     * never take effect: a non-expiring token is a century from expiry, so the ordinary
     * "within seven days of expiring" test stays false forever.
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function rotation_due(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        if (!self::lifetime_matches_configuration($token, $now)) {
            return true;
        }
        return (int)$token->validuntil - $now <= self::ROTATE_BEFORE_EXPIRY;
    }

    /**
     * Determine whether an unresolved rotation requires an administrator warning.
     *
     * An imminent expiration is the usual trigger. A lifetime change that cannot be applied
     * is the other one: re-enabling rotation on a site that cannot reach Corolair leaves a
     * non-expiring token in place indefinitely, and the expiration test alone would never
     * fire on it. That case is only worth paging about once retrying has clearly stopped
     * helping, hence the failure count.
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function warning_due(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        if (!empty($token->validuntil) && (int)$token->validuntil - $now <= self::WARN_BEFORE_EXPIRY) {
            return true;
        }
        return !self::lifetime_matches_configuration($token, $now)
            && (int)get_config('local_corolair', 'webservicetokenfailurecount') >= self::LIFETIME_CHANGE_WARN_AFTER;
    }

    /**
     * Record an administrator changing the rotation policy.
     *
     * @return void
     */
    public static function record_rotation_policy_change(): void {
        self::trigger_event(
            self::rotation_disabled() ? 'rotation_disabled' : 'rotation_enabled',
            null,
            null
        );
    }

    /**
     * Maintain the active token from the scheduled task.
     *
     * @return void
     */
    public static function maintain(): void {
        global $DB;

        if (!(bool)get_config('local_corolair', 'setupcompleted')) {
            return;
        }
        if ((bool)get_config('local_corolair', 'legacycredentialmigrationpending')) {
            // The migration mints its own token and atomically replaces the API key, then
            // deletes every other token for the service. Rotating underneath it leaves
            // whichever finishes second holding a credential the other invalidated. The
            // same reasoning makes setup_corolair_connection_task defer at its lines 89-92.
            // Convergence resumes on the next run once upgrade_migrator::run() clears this.
            return;
        }
        $apikey = self::get_api_key();
        if ($apikey === null) {
            return;
        }

        // The scheduled task holds core's scheduled-task lock and the administrator-triggered
        // retry holds the ad-hoc runner's lock. Those are different locks, so the two can run
        // at once -- and a settings save queues the ad-hoc task at exactly the moment the
        // scheduled one may be mid-run. Two runners would each mint a candidate with its own
        // rotation ID; Corolair rejects the loser, but the loser has already overwritten the
        // winner's recorded candidate, leaving Moodle and Corolair on different tokens.
        $lock = \core\lock\lock_config::get_lock_factory('local_corolair_token')->get_lock('rotation', 0);
        if (!$lock) {
            // The other runner is doing this same work. Convergence retries hourly regardless.
            return;
        }
        try {
            self::maintain_locked($DB, $apikey);
        } finally {
            $lock->release();
        }
    }

    /**
     * Run token maintenance while holding the rotation lock.
     *
     * @param \moodle_database $db Database driver.
     * @param string $apikey Corolair API key.
     * @return void
     */
    private static function maintain_locked(\moodle_database $db, string $apikey): void {
        self::cleanup_previous_token();

        $service = $db->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], '*', MUST_EXIST);
        $adminid = (int)get_config('local_corolair', 'setupconsentedby');
        if ($adminid <= 0) {
            throw new \moodle_exception('setupconsentmissing', 'local_corolair');
        }

        $current = self::get_current_token((int)$service->id, $adminid);
        if (!$current) {
            self::set_rotation_failure('current_token_missing');
            self::send_warning($adminid, null, 'current_token_missing');
            throw new \moodle_exception('tokenmissing', 'local_corolair');
        }
        if (!self::rotation_due($current)) {
            // Nothing to rotate. Rotation is also the only thing that proves the integration
            // still works, so a site that has opted out needs that assurance from elsewhere.
            self::monitor_static_token($current, $adminid, $apikey);
            return;
        }

        set_config('webservicetokenrotationstatus', 'ROTATION_DUE', 'local_corolair');
        try {
            $candidate = self::get_or_create_candidate((int)$service->id, $adminid);
            $rotationid = (string)get_config('local_corolair', 'webservicetokenrotationid');
            self::send_candidate($candidate, $rotationid, $apikey);
            self::activate_candidate($current, $candidate, $rotationid);
        } catch (\Throwable $exception) {
            self::set_rotation_failure(self::safe_error_code($exception));
            if (!empty($current->validuntil) && (int)$current->validuntil <= time()) {
                set_config('webservicetokenrotationstatus', 'EXPIRED', 'local_corolair');
            }
            if (self::warning_due($current)) {
                self::send_warning($adminid, $current, self::safe_error_code($exception));
            }
            throw $exception;
        }
    }

    /**
     * Record an initially registered token as active.
     *
     * @param \stdClass $token Token record.
     * @return void
     */
    public static function record_initial_token(\stdClass $token): void {
        set_config('webservicetokenid', (int)$token->id, 'local_corolair');
        set_config('webservicetokenexpiresat', (int)$token->validuntil, 'local_corolair');
        set_config('webservicetokenrotationstatus', 'ACTIVE', 'local_corolair');
        self::clear_pending_state();
        self::trigger_event('initial_token_activated', $token, null);
        if (self::is_non_expiring($token)) {
            self::trigger_event('nonexpiring_token_activated', $token, null);
        }
    }

    /**
     * Return ISO-8601 expiration metadata for Corolair, or null for a non-expiring token.
     *
     * The local and the transmitted representation of "never expires" are deliberately
     * different. Locally it is a far-future validuntil, so every "validuntil > time()"
     * invariant in the plugin keeps working and validuntil = 0 stays unambiguously the
     * legacy marker. On the wire it is an absent expiry, because Corolair caps any supplied
     * expiration at fifteen days and models "no expiration" as a null column.
     *
     * This keys off the token being sent rather than off the current setting: if the policy
     * changed after the token was minted, the payload must still describe the actual token.
     *
     * @param \stdClass $token Token record.
     * @return string|null
     */
    public static function expiration_iso8601(\stdClass $token): ?string {
        if (self::is_non_expiring($token)) {
            return null;
        }
        return gmdate('c', (int)$token->validuntil);
    }

    /**
     * Find the active token, including upgrades from versions without token metadata.
     *
     * @param int $serviceid External service ID.
     * @param int $userid Token owner.
     * @return \stdClass|false
     */
    private static function get_current_token(int $serviceid, int $userid) {
        global $DB;

        $configuredid = (int)get_config('local_corolair', 'webservicetokenid');
        if ($configuredid > 0) {
            $configured = $DB->get_record('external_tokens', [
                'id' => $configuredid,
                'externalserviceid' => $serviceid,
                'userid' => $userid,
                'tokentype' => 0,
            ]);
            if ($configured) {
                return $configured;
            }
        }

        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'userid' => $userid, 'tokentype' => 0],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $token = reset($tokens);
        if ($token) {
            set_config('webservicetokenid', (int)$token->id, 'local_corolair');
            set_config('webservicetokenexpiresat', (int)$token->validuntil, 'local_corolair');
        }
        return $token;
    }

    /**
     * Reuse the pending candidate or create it once.
     *
     * @param int $serviceid External service ID.
     * @param int $userid Token owner.
     * @return \stdClass
     */
    private static function get_or_create_candidate(int $serviceid, int $userid): \stdClass {
        global $DB;

        $candidateid = (int)get_config('local_corolair', 'webservicetokencandidateid');
        if ($candidateid > 0) {
            $candidate = $DB->get_record('external_tokens', [
                'id' => $candidateid,
                'externalserviceid' => $serviceid,
                'userid' => $userid,
                'tokentype' => 0,
            ]);
            if ($candidate && (int)$candidate->validuntil > time()) {
                // An in-flight candidate is always finished on its original rotation ID, even
                // when the desired lifetime changed underneath it. Corolair may already have
                // activated it without the response reaching us, and its rotate endpoint
                // refuses a new rotation ID while one is pending -- nothing clears that state
                // except a rotation matching it, so minting a fresh ID here would deadlock
                // every later attempt. Deleting the candidate would also strand Corolair on a
                // token that no longer exists in Moodle. rotation_due() fires again on the
                // next run and converges the now-active token, so finishing the wrong-shaped
                // candidate costs one extra rotation, not correctness.
                return $candidate;
            }
            if ($candidate) {
                $DB->delete_records('external_tokens', ['id' => (int)$candidate->id]);
            }
        }

        $candidate = self::create_token($userid, $serviceid);
        $rotationid = self::generate_uuid();
        set_config('webservicetokencandidateid', (int)$candidate->id, 'local_corolair');
        set_config('webservicetokenrotationid', $rotationid, 'local_corolair');
        set_config('webservicetokenrotationstatus', 'ROTATION_PENDING', 'local_corolair');
        set_config('webservicetokenfailurecount', 0, 'local_corolair');
        self::trigger_event('rotation_started', $candidate, $rotationid);
        return $candidate;
    }

    /**
     * Send and strictly validate a candidate activation request.
     *
     * @param \stdClass $candidate Candidate token.
     * @param string $rotationid Idempotency identifier.
     * @param string $apikey Corolair API key.
     * @return void
     */
    private static function send_candidate(\stdClass $candidate, string $rotationid, string $apikey): void {
        $curl = new \curl();
        $expiresat = self::expiration_iso8601($candidate);
        $payload = json_encode([
            'rotationId' => $rotationid,
            'webserviceToken' => $candidate->token,
            'expiresAt' => $expiresat,
        ], JSON_THROW_ON_ERROR);
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
        ];
        set_config('webservicetokenlastattemptat', time(), 'local_corolair');
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $payload, $options) {
                return $curl->post(self::ROTATION_ENDPOINT, $payload, $options);
            },
            audited_request::OP_WEBSERVICE_TOKEN_ROTATION,
            \context_system::instance(),
            (int)get_config('local_corolair', 'setupconsentedby')
        );
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        if ($response === false || $curl->get_errno() !== 0 || $status < 200 || $status >= 300) {
            throw new \moodle_exception('tokenrotationrequestfailed', 'local_corolair');
        }
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        // The echoed expiration is checked symmetrically rather than merely leniently. An
        // expiring rotation still requires a string, so a backend that silently dropped the
        // expiration cannot be mistaken for success -- that is the guarantee this check has
        // always provided. A non-expiring rotation requires the converse, which is what
        // proves Corolair stored no expiration rather than coercing the value to something.
        // Accepting null covers both an absent key and an explicit null.
        //
        // The value itself is never compared to what was sent: Corolair emits
        // Date.toISOString() ("...Z", milliseconds) while the plugin sends gmdate('c')
        // ("+00:00", no milliseconds). Same instant, different strings.
        $echoedexpiry = $result['expiresAt'] ?? null;
        if (
            !is_array($result) ||
            ($result['status'] ?? null) !== 'activated' ||
            ($result['rotationId'] ?? null) !== $rotationid ||
            !is_string($result['verifiedAt'] ?? null) ||
            ($expiresat === null ? $echoedexpiry !== null : !is_string($echoedexpiry))
        ) {
            throw new \moodle_exception('tokenrotationresponseinvalid', 'local_corolair');
        }
    }

    /**
     * Promote a verified candidate and retain the old token only for its overlap.
     *
     * @param \stdClass $current Previous active token.
     * @param \stdClass $candidate Verified candidate.
     * @param string $rotationid Rotation ID.
     * @return void
     */
    private static function activate_candidate(\stdClass $current, \stdClass $candidate, string $rotationid): void {
        $revokeby = min(
            empty($current->validuntil) ? time() + self::ROTATE_BEFORE_EXPIRY : (int)$current->validuntil,
            time() + self::ROTATE_BEFORE_EXPIRY
        );
        set_config('previouswebservicetokenid', (int)$current->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', $revokeby, 'local_corolair');
        set_config('webservicetokenid', (int)$candidate->id, 'local_corolair');
        set_config('webservicetokenexpiresat', (int)$candidate->validuntil, 'local_corolair');
        set_config('webservicetokenrotationstatus', 'ACTIVE', 'local_corolair');
        set_config('webservicetokenrotatedat', time(), 'local_corolair');
        unset_config('webservicetokenlasterror', 'local_corolair');
        unset_config('webservicetokenadminnotifiedat', 'local_corolair');
        self::clear_pending_state();
        self::trigger_event('rotation_succeeded', $candidate, $rotationid);
        if (self::is_non_expiring($candidate)) {
            self::trigger_event('nonexpiring_token_activated', $candidate, $rotationid);
        }
    }

    /**
     * Check an integration that is no longer being rotated.
     *
     * Rotation does two jobs, and this setting is only meant to switch off one of them. The
     * other is monitoring: the rotate endpoint is the only Corolair call that re-verifies,
     * by calling back into Moodle, that the token still authenticates, that the site URL
     * still matches, and that every function the integration needs is still granted. A site
     * that stops rotating stops being checked, and there is no scheduled job on the Corolair
     * side to notice. These two checks replace it.
     *
     * @param \stdClass $token Active token record.
     * @param int $adminid Integration owner.
     * @param string $apikey Corolair API key.
     * @return void
     */
    private static function monitor_static_token(\stdClass $token, int $adminid, string $apikey): void {
        if (!self::rotation_disabled()) {
            return;
        }
        $drift = self::local_drift($token, $adminid);
        if ($drift !== null) {
            self::send_warning($adminid, $token, $drift);
            return;
        }
        if ((int)get_config('local_corolair', 'webservicetokenverifiedat') > time() - self::VERIFY_INTERVAL) {
            return;
        }
        self::verify_remotely($adminid, $apikey);
    }

    /**
     * Detect integration drift that Moodle can see without asking Corolair.
     *
     * @param \stdClass $token Active token record.
     * @param int $adminid Integration owner.
     * @return string|null Safe error code, or null when nothing has drifted.
     */
    private static function local_drift(\stdClass $token, int $adminid): ?string {
        global $DB;

        $granted = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = :serviceid',
            ['serviceid' => (int)$token->externalserviceid]
        );
        if (array_diff(integration_disclosure::get_function_names(), $granted)) {
            return 'function_allowlist_drift';
        }
        if (!$DB->record_exists('user', ['id' => $adminid, 'deleted' => 0, 'suspended' => 0])) {
            return 'token_owner_unusable';
        }
        if (!has_capability('moodle/site:config', \context_system::instance(), $adminid)) {
            return 'token_owner_unusable';
        }
        return null;
    }

    /**
     * Ask Corolair to re-verify the active token without rotating it.
     *
     * @param int $adminid Integration owner.
     * @param string $apikey Corolair API key.
     * @return void
     */
    private static function verify_remotely(int $adminid, string $apikey): void {
        $curl = new \curl();
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: 2',
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $options) {
                return $curl->post(self::VERIFICATION_ENDPOINT, '{}', $options);
            },
            audited_request::OP_WEBSERVICE_TOKEN_VERIFY,
            \context_system::instance(),
            $adminid
        );
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        $verified = false;
        if ($response !== false && $curl->get_errno() === 0 && $status >= 200 && $status < 300) {
            try {
                $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                $verified = is_array($result) && ($result['status'] ?? null) === 'verified';
            } catch (\JsonException $exception) {
                $verified = false;
            }
        }
        if (!$verified) {
            // Verification is a monitor, not a gate. A failure must not throw: the token is
            // still perfectly usable, and turning a Corolair outage into a failing scheduled
            // task would bury the signal in cron noise rather than surface it.
            self::trigger_event('verification_failed', null, null);
            self::send_warning($adminid, null, 'token_verification_failed');
            return;
        }
        set_config('webservicetokenverifiedat', time(), 'local_corolair');
        self::trigger_event('verification_succeeded', null, null);
    }

    /**
     * Delete the prior token after the overlap.
     *
     * @return void
     */
    private static function cleanup_previous_token(): void {
        global $DB;

        $tokenid = (int)get_config('local_corolair', 'previouswebservicetokenid');
        $revokeby = (int)get_config('local_corolair', 'previouswebservicetokenrevokeby');
        if ($tokenid <= 0 || $revokeby <= 0 || $revokeby > time()) {
            return;
        }
        $token = $DB->get_record('external_tokens', ['id' => $tokenid]);
        if ($token) {
            $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], 'id', MUST_EXIST);
            $adminid = (int)get_config('local_corolair', 'setupconsentedby');
            if ((int)$token->externalserviceid === (int)$service->id && (int)$token->userid === $adminid) {
                $DB->delete_records('external_tokens', ['id' => $tokenid]);
                self::trigger_event('old_token_revoked', $token, null);
            }
        }
        unset_config('previouswebservicetokenid', 'local_corolair');
        unset_config('previouswebservicetokenrevokeby', 'local_corolair');
    }

    /**
     * Persist safe failure state.
     *
     * @param string $errorcode Safe error code.
     * @return void
     */
    private static function set_rotation_failure(string $errorcode): void {
        $failures = (int)get_config('local_corolair', 'webservicetokenfailurecount') + 1;
        set_config('webservicetokenrotationstatus', 'ROTATION_FAILED', 'local_corolair');
        set_config('webservicetokenfailurecount', $failures, 'local_corolair');
        set_config('webservicetokenlasterror', $errorcode, 'local_corolair');
        self::trigger_event('rotation_failed', null, (string)get_config('local_corolair', 'webservicetokenrotationid'));
    }

    /**
     * Send a rate-limited warning to the consenting administrator.
     *
     * @param int $adminid Administrator ID.
     * @param \stdClass|null $token Current token.
     * @param string $errorcode Safe failure code.
     * @return void
     */
    private static function send_warning(int $adminid, ?\stdClass $token, string $errorcode): void {
        global $DB;

        $lastnotified = (int)get_config('local_corolair', 'webservicetokenadminnotifiedat');
        if ($lastnotified > time() - self::WARNING_INTERVAL) {
            return;
        }
        $admin = $DB->get_record('user', ['id' => $adminid, 'deleted' => 0], '*', MUST_EXIST);
        $expiry = $token && !empty($token->validuntil)
            ? userdate((int)$token->validuntil)
            : get_string('tokenexpiryunknown', 'local_corolair');
        $details = (object)['expiry' => $expiry, 'error' => $errorcode];
        // A non-expiring token has no meaningful expiration to quote -- printing a date a
        // century out reads as a bug, and "your token expires on" is simply untrue. What is
        // actually wrong in that case is that a policy change has not been applied.
        $bodystring = $token && self::is_non_expiring($token)
            ? 'tokenexpirywarningbodylifetime'
            : 'tokenexpirywarningbody';
        $message = new \core\message\message();
        $message->component = 'local_corolair';
        $message->name = 'tokenexpirywarning';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $admin;
        $message->subject = get_string('tokenexpirywarningsubject', 'local_corolair');
        $message->fullmessage = get_string($bodystring, 'local_corolair', $details);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->fullmessage;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/admin/settings.php', ['section' => 'local_corolair']))->out(false);
        $message->contexturlname = get_string('setupstatus', 'local_corolair');
        message_send($message);
        set_config('webservicetokenadminnotifiedat', time(), 'local_corolair');
        self::trigger_event('warning_sent', $token, null);
    }

    /**
     * Clear candidate-only state after successful activation.
     *
     * @return void
     */
    private static function clear_pending_state(): void {
        unset_config('webservicetokencandidateid', 'local_corolair');
        unset_config('webservicetokenrotationid', 'local_corolair');
        set_config('webservicetokenfailurecount', 0, 'local_corolair');
    }

    /**
     * Return the configured API key, excluding translated placeholder values.
     *
     * @return string|null
     */
    private static function get_api_key(): ?string {
        return api_key::get();
    }

    /**
     * Convert an exception to a non-sensitive operational code.
     *
     * @param \Throwable $exception Failure.
     * @return string
     */
    private static function safe_error_code(\Throwable $exception): string {
        if ($exception instanceof \moodle_exception && !empty($exception->errorcode)) {
            return clean_param($exception->errorcode, PARAM_ALPHANUMEXT);
        }
        if ($exception instanceof \JsonException) {
            return 'invalid_json';
        }
        return 'unexpected_failure';
    }

    /**
     * Generate an RFC 4122 version 4 UUID without an additional dependency.
     *
     * @return string
     */
    private static function generate_uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Emit lifecycle evidence without recording token values.
     *
     * @param string $action Lifecycle action.
     * @param \stdClass|null $token Token record when applicable.
     * @param string|null $rotationid Rotation ID when applicable.
     * @return void
     */
    private static function trigger_event(string $action, ?\stdClass $token, ?string $rotationid): void {
        \local_corolair\event\webservice_token_lifecycle::create([
            'context' => \context_system::instance(),
            'other' => [
                'action' => $action,
                'tokenid' => $token ? (int)$token->id : 0,
                'expiresat' => $token ? (int)$token->validuntil : 0,
                'rotationid' => $rotationid ?? '',
            ],
        ])->trigger();
    }
}
