<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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

    /** Start rotation seven days before expiration. */
    public const ROTATE_BEFORE_EXPIRY = 7 * DAYSECS;

    /** Warn administrators five days before expiration if rotation is unresolved. */
    public const WARN_BEFORE_EXPIRY = 5 * DAYSECS;

    /** Do not send the same warning more than once per day. */
    public const WARNING_INTERVAL = DAYSECS;

    /** External service shortname. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /** Corolair rotation endpoint. */
    private const ROTATION_ENDPOINT =
        'https://services.corolair.dev/moodle-integration/v2/plugin/organization/webservice-token/rotate';

    /**
     * Create a token that expires after the configured lifetime.
     *
     * @param int $userid Token owner.
     * @param int $serviceid External service ID.
     * @return \stdClass Inserted token record.
     */
    public static function create_token(int $userid, int $serviceid): \stdClass {
        global $DB;

        $now = time();
        $token = (object)[
            'token' => bin2hex(random_bytes(32)),
            'userid' => $userid,
            'tokentype' => 0,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $userid,
            'timecreated' => $now,
            'validuntil' => $now + self::TOKEN_LIFETIME,
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
     * Determine whether a token needs rotation.
     *
     * Tokens without an expiration are rotated immediately.
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function rotation_due(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        return empty($token->validuntil) || (int)$token->validuntil - $now <= self::ROTATE_BEFORE_EXPIRY;
    }

    /**
     * Determine whether an unresolved rotation requires an administrator warning.
     *
     * @param \stdClass $token Token record.
     * @param int|null $now Current time override for tests.
     * @return bool
     */
    public static function warning_due(\stdClass $token, ?int $now = null): bool {
        $now = $now ?? time();
        return !empty($token->validuntil) && (int)$token->validuntil - $now <= self::WARN_BEFORE_EXPIRY;
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
        $apikey = self::get_api_key();
        if ($apikey === null) {
            return;
        }

        self::cleanup_previous_token();

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], '*', MUST_EXIST);
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
    }

    /**
     * Return ISO-8601 expiration metadata for Corolair.
     *
     * @param \stdClass $token Token record.
     * @return string
     */
    public static function expiration_iso8601(\stdClass $token): string {
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
        $payload = json_encode([
            'rotationId' => $rotationid,
            'webserviceToken' => $candidate->token,
            'expiresAt' => self::expiration_iso8601($candidate),
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
        if (
            !is_array($result) ||
            ($result['status'] ?? null) !== 'activated' ||
            ($result['rotationId'] ?? null) !== $rotationid ||
            !is_string($result['verifiedAt'] ?? null) ||
            !is_string($result['expiresAt'] ?? null)
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
            : get_string('unknown');
        $details = (object)['expiry' => $expiry, 'error' => $errorcode];
        $message = new \core\message\message();
        $message->component = 'local_corolair';
        $message->name = 'tokenexpirywarning';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $admin;
        $message->subject = get_string('tokenexpirywarningsubject', 'local_corolair');
        $message->fullmessage = get_string('tokenexpirywarningbody', 'local_corolair', $details);
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
