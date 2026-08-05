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
 * Audited remote request helper.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

use context;
use curl;

/**
 * Executes Corolair requests and records safe transport metadata.
 */
final class audited_request {
    /** Widget session creation operation. */
    public const OP_WIDGET_SESSION = 'widget_session';

    /** Trainer authentication operation. */
    public const OP_TRAINER_AUTH = 'trainer_auth';

    /** Organization registration operation. */
    public const OP_ORGANIZATION_REGISTER = 'organization_register';

    /** Organization deregistration operation. */
    public const OP_ORGANIZATION_DEREGISTER = 'organization_deregister';

    /** Moodle web-service token rotation operation. */
    public const OP_WEBSERVICE_TOKEN_ROTATION = 'webservice_token_rotation';

    /** Privacy context retrieval operation. */
    public const OP_PRIVACY_CONTEXTS = 'privacy_contexts';

    /** Privacy data export operation. */
    public const OP_PRIVACY_EXPORT = 'privacy_export';

    /** Privacy context user retrieval operation. */
    public const OP_PRIVACY_CONTEXT_USERS = 'privacy_context_users';

    /** Privacy context deletion operation. */
    public const OP_PRIVACY_CONTEXT_DELETE = 'privacy_context_delete';

    /** Privacy user deletion operation. */
    public const OP_PRIVACY_USER_DELETE = 'privacy_user_delete';

    /** @var string[] Allowed operation identifiers. */
    private const OPERATIONS = [
        self::OP_WIDGET_SESSION,
        self::OP_TRAINER_AUTH,
        self::OP_ORGANIZATION_REGISTER,
        self::OP_ORGANIZATION_DEREGISTER,
        self::OP_WEBSERVICE_TOKEN_ROTATION,
        self::OP_PRIVACY_CONTEXTS,
        self::OP_PRIVACY_EXPORT,
        self::OP_PRIVACY_CONTEXT_USERS,
        self::OP_PRIVACY_CONTEXT_DELETE,
        self::OP_PRIVACY_USER_DELETE,
    ];

    /**
     * Execute an outbound request and emit exactly one Moodle event.
     *
     * @param curl $curl Moodle curl client used by the request.
     * @param callable $request Callback that performs and returns the request result.
     * @param string $operation Allow-listed operation identifier.
     * @param context $context Moodle context associated with the request.
     * @param int|null $relateduserid Related Moodle user, when applicable.
     * @return mixed Request result.
     */
    public static function execute(
        curl $curl,
        callable $request,
        string $operation,
        context $context,
        ?int $relateduserid = null
    ) {
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw new \coding_exception('Unknown Corolair audit operation.');
        }

        try {
            $response = $request();
        } catch (\Throwable $exception) {
            self::trigger_event($curl, $operation, 'exception', $context, $relateduserid);
            throw $exception;
        }

        $errno = (int)$curl->get_errno();
        $info = $curl->get_info();
        $httpstatus = (int)($info['http_code'] ?? 0);
        if ($response === false || $errno !== 0) {
            $outcome = 'transport_failure';
        } else if ($httpstatus < 200 || $httpstatus >= 300) {
            $outcome = 'http_failure';
        } else {
            $outcome = 'success';
        }
        self::trigger_event($curl, $operation, $outcome, $context, $relateduserid);

        return $response;
    }

    /**
     * Trigger the audit event without recording request or response content.
     *
     * @param curl $curl Moodle curl client used by the request.
     * @param string $operation Allow-listed operation identifier.
     * @param string $outcome Transport outcome.
     * @param context $context Moodle context associated with the request.
     * @param int|null $relateduserid Related Moodle user, when applicable.
     * @return void
     */
    private static function trigger_event(
        curl $curl,
        string $operation,
        string $outcome,
        context $context,
        ?int $relateduserid
    ): void {
        $info = $curl->get_info();
        $eventdata = [
            'context' => $context,
            'other' => [
                'operation' => $operation,
                'outcome' => $outcome,
                'httpstatus' => (int)($info['http_code'] ?? 0),
                'curlerrno' => (int)$curl->get_errno(),
            ],
        ];
        if ($relateduserid !== null && $relateduserid > 0) {
            $eventdata['relateduserid'] = $relateduserid;
        }
        \local_corolair\event\remote_request_completed::create($eventdata)->trigger();
    }
}
