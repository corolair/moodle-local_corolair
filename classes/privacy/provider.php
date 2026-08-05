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
 * Privacy Subsystem implementation for local_corolair.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use context;
use context_system;
use curl;

defined('MOODLE_INTERNAL') || die();

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses, Generic.Classes.DuplicateClassName.Found
if (interface_exists('\core_privacy\local\request\core_userlist_provider')) {
    /**
     * Interface for extending core_userlist_provider.
     *
     * This interface is used when \core_privacy\local\request\core_userlist_provider exists,
     * ensuring compatibility with the Moodle privacy API.
     *
     * @package   local_corolair
     * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    interface local_corolair_userlist_provider extends \core_privacy\local\request\core_userlist_provider {
    }
} else {
    /**
     * Fallback interface when core_userlist_provider is not available.
     *
     * This interface ensures the codebase can operate without relying
     * on the \core_privacy\local\request\core_userlist_provider interface.
     *
     * @package   local_corolair
     * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    interface local_corolair_userlist_provider {
    }
}

/**
 * Class Provider
 *
 * Implementation of the privacy subsystem plugin provider for the local_corolair plugin.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    local_corolair_userlist_provider {
    // phpcs:enable PSR1.Classes.ClassDeclaration.MultipleClasses, Generic.Classes.DuplicateClassName.Found

    /** Maximum time allowed to establish an external connection, in seconds. */
    private const CONNECTION_TIMEOUT = 15;

    /** Maximum total duration of an external request, in seconds. */
    private const REQUEST_TIMEOUT = 60;

    /** Maximum number of identifiers accepted in one privacy response. */
    private const MAX_PRIVACY_IDENTIFIERS = 10000;

    /**
     * Return the standard options for authenticated calls to the Corolair service.
     *
     * @param string $apikey Corolair API key.
     * @return array
     */
    private static function get_curl_options(string $apikey): array {
        return [
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECTION_TIMEOUT,
            'CURLOPT_TIMEOUT' => self::REQUEST_TIMEOUT,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
            ],
        ];
    }

    /**
     * Decode an external JSON response as an associative array.
     *
     * @param string $response Raw response body.
     * @return array Decoded response.
     */
    private static function decode_json_response(string $response): array {
        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            debugging('Invalid JSON received from the Corolair privacy service.', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'local_corolair');
        }

        if (!is_array($data)) {
            debugging('Unexpected JSON structure received from the Corolair privacy service.', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        return $data;
    }

    /**
     * Check both the transport result and HTTP status of an external request.
     *
     * @param curl $curl Moodle curl client used for the request.
     * @param mixed $response Response body, or false on a transport failure.
     * @return bool
     */
    private static function request_succeeded(curl $curl, $response): bool {
        if ($response === false || $curl->get_errno() !== 0) {
            return false;
        }
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        return $status >= 200 && $status < 300;
    }

    /**
     * Validate and normalise a bounded list of positive integer identifiers.
     *
     * @param mixed $values Values received from the external service.
     * @return int[]
     */
    private static function validate_identifier_list($values): array {
        if (!is_array($values) || count($values) > self::MAX_PRIVACY_IDENTIFIERS) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $identifiers = [];
        foreach ($values as $value) {
            if (
                (!is_int($value) && !(is_string($value) && ctype_digit($value))) ||
                (int)$value <= 0
            ) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            $identifiers[(int)$value] = (int)$value;
        }
        return array_values($identifiers);
    }

    /**
     * Validate a completed deletion response and return its safe audit fields.
     *
     * @param string $response Raw response body.
     * @param string $contextlevel Expected scope: system or course.
     * @param int|null $scopeid Expected course identifier.
     * @param int|null $contextid Expected Moodle context identifier.
     * @param int|null $moodleuserid Expected Moodle user identifier.
     * @return array
     */
    private static function validate_deletion_response(
        string $response,
        string $contextlevel,
        ?int $scopeid = null,
        ?int $contextid = null,
        ?int $moodleuserid = null
    ): array {
        $data = self::decode_json_response($response);
        if (
            ($data['status'] ?? null) !== 'completed' ||
            !is_string($data['operationId'] ?? null) ||
            strlen($data['operationId']) < 1 ||
            strlen($data['operationId']) > 128 ||
            !is_array($data['scope'] ?? null) ||
            ($data['scope']['contextLevel'] ?? null) !== $contextlevel ||
            !is_array($data['affected'] ?? null)
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if (
            $contextid !== null &&
            (!isset($data['scope']['contextId']) ||
            (string)$data['scope']['contextId'] !== (string)$contextid)
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if (
            $contextlevel === 'course' &&
            (!isset($data['scope']['courseId']) ||
            (string)$data['scope']['courseId'] !== (string)$scopeid)
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if (
            $moodleuserid !== null &&
            (!isset($data['scope']['moodleUserId']) ||
            (int)$data['scope']['moodleUserId'] !== $moodleuserid)
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        foreach (['associations', 'conversations', 'learners', 'users'] as $field) {
            if (
                !isset($data['affected'][$field]) ||
                !is_int($data['affected'][$field]) ||
                $data['affected'][$field] < 0
            ) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
        }
        return [
            'operationid' => $data['operationId'],
            'affected' => $data['affected'],
        ];
    }

    /**
     * Build the remote scope parameters for a supported Moodle context.
     *
     * @param context $context Moodle context to scope.
     * @return array|null Scope parameters, or null for unsupported contexts.
     */
    private static function get_context_scope(context $context): ?array {
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return [
                'contextlevel' => 'system',
                'contextid' => (int)$context->id,
            ];
        }
        if ($context->contextlevel == CONTEXT_COURSE) {
            return [
                'contextlevel' => 'course',
                'contextid' => (int)$context->id,
                'courseid' => (int)$context->instanceid,
            ];
        }
        return null;
    }

    /**
     * Verify that a remote response was produced for the requested context.
     *
     * @param mixed $scope Remote response scope.
     * @param context $context Requested Moodle context.
     * @param int|null $moodleuserid Expected user identifier, when applicable.
     * @return void
     */
    private static function validate_response_scope(
        $scope,
        context $context,
        ?int $moodleuserid = null
    ): void {
        $expected = self::get_context_scope($context);
        if (
            $expected === null ||
            !is_array($scope) ||
            ($scope['contextLevel'] ?? null) !== $expected['contextlevel'] ||
            !isset($scope['contextId']) ||
            (string)$scope['contextId'] !== (string)$expected['contextid']
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if (
            isset($expected['courseid']) &&
            (!isset($scope['courseId']) ||
            (string)$scope['courseId'] !== (string)$expected['courseid'])
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if (
            $moodleuserid !== null &&
            (!isset($scope['moodleUserId']) ||
            (int)$scope['moodleUserId'] !== $moodleuserid)
        ) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
    }

    /**
     * Trigger a local audit event after a verified privacy deletion.
     *
     * @param context $context Moodle context affected by the operation.
     * @param string $scope Scope returned by Corolair.
     * @param array $outcome Validated deletion outcome.
     * @param int|null $relateduserid Related Moodle user, when applicable.
     * @return void
     */
    private static function record_deletion_event(
        context $context,
        string $scope,
        array $outcome,
        ?int $relateduserid = null
    ): void {
        $eventdata = [
            'context' => $context,
            'other' => [
                'scope' => $scope,
                'operationid' => $outcome['operationid'],
                'affected' => $outcome['affected'],
            ],
        ];
        if ($relateduserid !== null) {
            $eventdata['relateduserid'] = $relateduserid;
        }
        \local_corolair\event\privacy_deletion_completed::create($eventdata)->trigger();
    }

    /**
     * Returns metadata about the external location link for Raison.
     *
     * @param collection $collection The initial collection to add metadata to.
     * @return collection The updated collection with Raison metadata added.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('raison', [
            'userid' => 'privacy:metadata:raison:userid',
            'useremail' => 'privacy:metadata:raison:useremail',
            'userfirstname' => 'privacy:metadata:raison:userfirstname',
            'userlastname' => 'privacy:metadata:raison:userlastname',
            'userrolename' => 'privacy:metadata:raison:userrolename',
            'interaction' => 'privacy:metadata:raison:interaction',
        ], 'privacy:metadata:raison');
        // Local plugin configuration records the identity of the administrator who
        // consented to the integration and acknowledged the disclosure, together with
        // the times they did so. These are retained as operational accountability
        // records for the active integration owner.
        $collection->add_database_table('config_plugins', [
            'setupconsentedby' => 'privacy:metadata:config_plugins:setupconsentedby',
            'setupconsentedat' => 'privacy:metadata:config_plugins:setupconsentedat',
            'setupdisclosureacknowledgedby' => 'privacy:metadata:config_plugins:setupdisclosureacknowledgedby',
            'setupdisclosureacknowledgedat' => 'privacy:metadata:config_plugins:setupdisclosureacknowledgedat',
        ], 'privacy:metadata:config_plugins');
        return $collection;
    }

    /**
     * Return the locally retained setup accountability record for a user, if any.
     *
     * The plugin stores the administrator who consented to the integration and
     * acknowledged the disclosure (with timestamps) in plugin configuration. This
     * returns a human-readable export payload when $userid is that administrator.
     *
     * @param int $userid Moodle user ID.
     * @return array|null Export payload, or null if the user has no local record.
     */
    private static function get_local_setup_record(int $userid): ?array {
        $consentedby = (int)get_config('local_corolair', 'setupconsentedby');
        $disclosureby = (int)get_config('local_corolair', 'setupdisclosureacknowledgedby');
        if ($userid !== $consentedby && $userid !== $disclosureby) {
            return null;
        }
        $record = [];
        if ($userid === $consentedby) {
            $record['setupconsentedby'] = $userid;
            $consentedat = (int)get_config('local_corolair', 'setupconsentedat');
            if ($consentedat > 0) {
                $record['setupconsentedat'] = transform::datetime($consentedat);
            }
        }
        if ($userid === $disclosureby) {
            $record['setupdisclosureacknowledgedby'] = $userid;
            $acknowledgedat = (int)get_config('local_corolair', 'setupdisclosureacknowledgedat');
            if ($acknowledgedat > 0) {
                $record['setupdisclosureacknowledgedat'] = transform::datetime($acknowledgedat);
            }
        }
        return $record !== [] ? $record : null;
    }

    /**
     * Export the locally retained setup accountability record for the target user.
     *
     * @param approved_contextlist $approvedcontextlist Approved contexts for the user.
     * @return void
     */
    private static function export_local_setup_records(approved_contextlist $approvedcontextlist): void {
        $user = $approvedcontextlist->get_user();
        $record = self::get_local_setup_record((int)$user->id);
        if ($record === null) {
            return;
        }
        foreach ($approvedcontextlist->get_contexts() as $context) {
            if ((int)$context->contextlevel === CONTEXT_SYSTEM) {
                \core_privacy\local\request\writer::with_context($context)->export_data(
                    [get_string('privacy:setupsubcontext', 'local_corolair')],
                    (object)$record
                );
                break;
            }
        }
    }

    /**
     * Retrieves the list of contexts for a given user ID.
     *
     * This function fetches the contexts associated with a user ID from an external service
     * and adds them to a context list. If the external service is unavailable or returns an error,
     * an empty context list is returned.
     *
     * @param int $userid The ID of the user whose contexts are being retrieved.
     * @return contextlist The list of contexts associated with the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        // Locally retained setup accountability records live in the system context and
        // exist independently of the remote API key.
        if (self::get_local_setup_record($userid) !== null) {
            $contextlist->add_from_sql(
                "SELECT id FROM {context} WHERE contextlevel = :contextlevel",
                ['contextlevel' => CONTEXT_SYSTEM]
            );
        }
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return $contextlist;
        }
        $url = 'https://services.raison.is/moodle-integration/v2/privacy/users/'
             . $userid . '/contexts';
        $curl = new curl();
        $options = self::get_curl_options($apikey);
        $response = \local_corolair\local\audited_request::execute(
            $curl,
            function () use ($curl, $url, $options) {
                return $curl->get($url, [], $options);
            },
            \local_corolair\local\audited_request::OP_PRIVACY_CONTEXTS,
            context_system::instance(),
            $userid
        );
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $responsedata = self::decode_json_response($response);
        if (count($responsedata) > self::MAX_PRIVACY_IDENTIFIERS) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        foreach ($responsedata as $contextdata) {
            if (
                !is_array($contextdata) ||
                !is_string($contextdata['contextIdentifier'] ?? null) ||
                !array_key_exists('payload', $contextdata)
            ) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            if ($contextdata['contextIdentifier'] === 'CONTEXT_SYSTEM') {
                if ($contextdata['payload'] !== null) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
                $contextlist->add_from_sql(
                    "SELECT id FROM {context} WHERE contextlevel = :contextlevel",
                    ['contextlevel' => CONTEXT_SYSTEM]
                );
            } else if ($contextdata['contextIdentifier'] === 'CONTEXT_COURSE') {
                $courseids = self::validate_identifier_list($contextdata['payload']);
                if (!$courseids) {
                    continue;
                }
                [$insql, $params] = $DB->get_in_or_equal(
                    $courseids,
                    SQL_PARAMS_NAMED,
                    'privacycourse'
                );
                $params['contextlevel'] = CONTEXT_COURSE;
                $sql = "SELECT id
                          FROM {context}
                         WHERE contextlevel = :contextlevel
                           AND instanceid {$insql}";
                $contextlist->add_from_sql($sql, $params);
            } else {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
        }
        return $contextlist;
    }

    /**
     * Exports user data for the given approved context list.
     *
     * This function retrieves the API key from the configuration, constructs a URL to an external service,
     * and sends a request to export user data. If the API key is not set or invalid, or if the request fails,
     * the function returns without exporting any data. If the request is successful, the function decodes the
     * JSON response and exports the data using Moodle's privacy API.
     *
     * @param approved_contextlist $approvedcontextlist The list of approved contexts for the user.
     */
    public static function export_user_data(approved_contextlist $approvedcontextlist) {
        // Export locally retained setup accountability records first; these exist
        // independently of the remote API key.
        self::export_local_setup_records($approvedcontextlist);
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $user = $approvedcontextlist->get_user();
        $userid = $user->id;
        $curl = new curl();
        foreach ($approvedcontextlist->get_contexts() as $approvedcontext) {
            $scope = self::get_context_scope($approvedcontext);
            if ($scope === null) {
                continue;
            }
            $url = new \moodle_url(
                'https://services.raison.is/moodle-integration/v2/privacy/users/'
                    . $userid . '/export',
                $scope
            );
            $urlout = $url->out(false);
            $options = self::get_curl_options($apikey);
            $response = \local_corolair\local\audited_request::execute(
                $curl,
                function () use ($curl, $urlout, $options) {
                    return $curl->get($urlout, [], $options);
                },
                \local_corolair\local\audited_request::OP_PRIVACY_EXPORT,
                $approvedcontext,
                $userid
            );
            if (!self::request_succeeded($curl, $response)) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            $responsedata = self::decode_json_response($response);
            self::validate_response_scope($responsedata['scope'] ?? null, $approvedcontext);
            $exports = $responsedata['data'] ?? null;
            if (!is_array($exports) || count($exports) > self::MAX_PRIVACY_IDENTIFIERS) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            foreach ($exports as $data) {
                if (
                    !is_array($data) ||
                    !is_string($data['contextIdentifier'] ?? null) ||
                    !is_array($data['payload'] ?? null) ||
                    !is_array($data['subcontext'] ?? null) ||
                    count($data['subcontext']) > 20
                ) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
                foreach ($data['subcontext'] as $subcontextpart) {
                    if (
                        !is_string($subcontextpart) ||
                        $subcontextpart === '' ||
                        strlen($subcontextpart) > 255
                    ) {
                        throw new \moodle_exception('curlerror', 'local_corolair');
                    }
                }
                if (
                    $scope['contextlevel'] === 'system' &&
                    ($data['contextIdentifier'] !== 'CONTEXT_SYSTEM' ||
                    array_key_exists('courseId', $data))
                ) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
                if (
                    $scope['contextlevel'] === 'course' &&
                    ($data['contextIdentifier'] !== 'CONTEXT_COURSE' ||
                    (string)($data['courseId'] ?? '') !== (string)$scope['courseid'])
                ) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
                \core_privacy\local\request\writer::with_context($approvedcontext)
                    ->export_data($data['subcontext'], (object)$data['payload']);
            }
        }
    }

    /**
     * Retrieves the list of users in a given context and adds them to the user list.
     *
     * @param userlist $userlist The user list object to which users will be added.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        // Locally retained setup accountability records live in the system context and
        // exist independently of the remote API key.
        $context = $userlist->get_context();
        if ((int)$context->contextlevel === CONTEXT_SYSTEM) {
            foreach (['setupconsentedby', 'setupdisclosureacknowledgedby'] as $configkey) {
                $actorid = (int)get_config('local_corolair', $configkey);
                if ($actorid > 0) {
                    $userlist->add_user($actorid);
                }
            }
        }

        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $urlparams = self::get_context_scope($context);
        if ($urlparams === null) {
            return;
        }
        $url = new \moodle_url(
            'https://services.raison.is/moodle-integration/v2/privacy/contexts/users',
            $urlparams
        );
        $curl = new curl();
        $urlout = $url->out(false);
        $options = self::get_curl_options($apikey);
        $response = \local_corolair\local\audited_request::execute(
            $curl,
            function () use ($curl, $urlout, $options) {
                return $curl->get($urlout, [], $options);
            },
            \local_corolair\local\audited_request::OP_PRIVACY_CONTEXT_USERS,
            $context
        );
        if (self::request_succeeded($curl, $response)) {
            $responsedata = self::decode_json_response($response);
            self::validate_response_scope($responsedata['scope'] ?? null, $context);
            if (!array_key_exists('userIds', $responsedata)) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            $userids = self::validate_identifier_list($responsedata['userIds']);
            if ($userids) {
                $existingusers = $DB->get_records_list('user', 'id', $userids, '', 'id');
                $userlist->add_users(array_map('intval', array_keys($existingusers)));
            }
        } else {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        return;
    }

    /**
     * Deletes data for all users in the given context.
     *
     * @param \context $context The context from which to delete data.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $urlparams = self::get_context_scope($context);
        if ($urlparams === null) {
            return;
        }
        $url = new \moodle_url(
            'https://services.raison.is/moodle-integration/v2/privacy/contexts/delete',
            $urlparams
        );
        $curl = new curl();
        $urlout = $url->out(false);
        $options = self::get_curl_options($apikey);
        $response = \local_corolair\local\audited_request::execute(
            $curl,
            function () use ($curl, $urlout, $options) {
                return $curl->delete($urlout, [], $options);
            },
            \local_corolair\local\audited_request::OP_PRIVACY_CONTEXT_DELETE,
            $context
        );
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $outcome = self::validate_deletion_response(
            $response,
            $urlparams['contextlevel'],
            $urlparams['courseid'] ?? null,
            $urlparams['contextid']
        );
        self::record_deletion_event($context, $urlparams['contextlevel'], $outcome);
        return;
    }

    /**
     * Delete one user's remote data within one approved Moodle context.
     *
     * @param int $userid Moodle user ID.
     * @param context $context Approved context.
     * @param string $apikey Corolair API key.
     * @param curl $curl Moodle curl client.
     * @return void
     */
    private static function delete_user_in_context(
        int $userid,
        context $context,
        string $apikey,
        curl $curl
    ): void {
        $scope = self::get_context_scope($context);
        if ($scope === null) {
            return;
        }
        $url = new \moodle_url(
            'https://services.raison.is/moodle-integration/v2/privacy/users/'
                . $userid . '/delete',
            $scope
        );
        $urlout = $url->out(false);
        $options = self::get_curl_options($apikey);
        $response = \local_corolair\local\audited_request::execute(
            $curl,
            function () use ($curl, $urlout, $options) {
                return $curl->delete($urlout, [], $options);
            },
            \local_corolair\local\audited_request::OP_PRIVACY_USER_DELETE,
            $context,
            $userid
        );
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $outcome = self::validate_deletion_response(
            $response,
            $scope['contextlevel'],
            $scope['courseid'] ?? null,
            $scope['contextid'],
            $userid
        );
        self::record_deletion_event($context, $scope['contextlevel'], $outcome, $userid);
    }

    /**
     * Deletes data for a user based on the provided context list.
     *
     * This function retrieves the API key from the configuration and checks if it is valid.
     * If the API key is not set or is invalid, the function returns without performing any action.
     * Otherwise, it constructs a URL to the Raison service to delete the user's data and sends
     * a DELETE request to that URL.
     *
     * @param approved_contextlist $contextlist The context list containing the user whose data is to be deleted.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // The locally retained setup accountability records (consenting/acknowledging
        // administrator identity and timestamps) are the operational owner identity of
        // the active integration and are retained under legitimate interest for as long
        // as the plugin is installed. They are declared in get_metadata and exportable;
        // uninstalling the plugin removes them (see db/uninstall.php). They are therefore
        // intentionally not erased here.
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $user = $contextlist->get_user();
        $userid = $user->id;
        $curl = new curl();
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_in_context($userid, $context, $apikey, $curl);
        }
    }

    /**
     * Deletes data for users specified in the approved user list.
     *
     * This function sends a DELETE request to the external Raison service to delete user data.
     * It retrieves the API key from the local configuration and constructs the request URL for each user.
     * If the API key is not set or is invalid, the function returns without performing any action.
     *
     * @param approved_userlist $userlist The list of approved users whose data needs to be deleted.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $users = $userlist->get_userids();
        $curl = new curl();
        $context = $userlist->get_context();
        foreach ($users as $userid) {
            self::delete_user_in_context((int)$userid, $context, $apikey, $curl);
        }
    }
}
