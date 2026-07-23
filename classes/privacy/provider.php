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
use context_course;
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
            if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) ||
                    (int)$value <= 0) {
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
     * @param string $contextlevel Expected scope: system, course, or user.
     * @param int|null $scopeid Expected course or Moodle user identifier.
     * @return array
     */
    private static function validate_deletion_response(
        string $response,
        string $contextlevel,
        ?int $scopeid = null
    ): array {
        $data = self::decode_json_response($response);
        if (($data['status'] ?? null) !== 'completed' ||
                !is_string($data['operationId'] ?? null) ||
                strlen($data['operationId']) < 1 ||
                strlen($data['operationId']) > 128 ||
                !is_array($data['scope'] ?? null) ||
                ($data['scope']['contextLevel'] ?? null) !== $contextlevel ||
                !is_array($data['affected'] ?? null)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if ($contextlevel === 'course' &&
                (!isset($data['scope']['courseId']) ||
                (string)$data['scope']['courseId'] !== (string)$scopeid)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        if ($contextlevel === 'user' &&
                (!isset($data['scope']['moodleUserId']) ||
                (int)$data['scope']['moodleUserId'] !== $scopeid)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        foreach (['associations', 'conversations', 'learners', 'users'] as $field) {
            if (!isset($data['affected'][$field]) ||
                    !is_int($data['affected'][$field]) ||
                    $data['affected'][$field] < 0) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
        }
        return [
            'operationid' => $data['operationId'],
            'affected' => $data['affected'],
        ];
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
        return $collection;
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
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return $contextlist;
        }
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/'
             . $userid . '/contexts';
        $curl = new curl();
        $response = $curl->get($url, [], self::get_curl_options($apikey));
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $responsedata = self::decode_json_response($response);
        if (count($responsedata) > self::MAX_PRIVACY_IDENTIFIERS) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        foreach ($responsedata as $contextdata) {
            if (!is_array($contextdata) ||
                    !is_string($contextdata['contextIdentifier'] ?? null) ||
                    !array_key_exists('payload', $contextdata)) {
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
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $user = $approvedcontextlist->get_user();
        $userid = $user->id;
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/'
             . $userid . '/export';
        $curl = new curl();
        $response = $curl->get($url, [], self::get_curl_options($apikey));
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $responsedata = self::decode_json_response($response);
        if (count($responsedata) > self::MAX_PRIVACY_IDENTIFIERS) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $approvedcontexts = [];
        foreach ($approvedcontextlist->get_contexts() as $approvedcontext) {
            $approvedcontexts[$approvedcontext->id] = true;
        }
        foreach ($responsedata as $data) {
            if (!is_array($data) ||
                    !is_string($data['contextIdentifier'] ?? null) ||
                    !is_array($data['payload'] ?? null) ||
                    !is_array($data['subcontext'] ?? null) ||
                    count($data['subcontext']) > 20) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            foreach ($data['subcontext'] as $subcontextpart) {
                if (!is_string($subcontextpart) ||
                        $subcontextpart === '' ||
                        strlen($subcontextpart) > 255) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
            }
            if ($data['contextIdentifier'] === 'CONTEXT_SYSTEM') {
                if (array_key_exists('courseId', $data)) {
                    throw new \moodle_exception('curlerror', 'local_corolair');
                }
                $context = context_system::instance();
            } else if ($data['contextIdentifier'] === 'CONTEXT_COURSE') {
                $courseid = self::validate_identifier_list([$data['courseId'] ?? null])[0];
                $context = context_course::instance($courseid);
            } else {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            if (isset($approvedcontexts[$context->id])) {
                \core_privacy\local\request\writer::with_context($context)
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

        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $context = $userlist->get_context();
        $contextlevel = '';
        if ($context->contextlevel == CONTEXT_COURSE) {
            $contextlevel = 'course';
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $contextlevel = 'system';
        } else {
            return;
        }
        $urlparams = ['contextlevel' => $contextlevel];
        if ($contextlevel === 'course') {
            $urlparams['courseid'] = (int)$context->instanceid;
        }
        $url = new \moodle_url(
            'https://services.corolair.dev/moodle-integration/privacy/contexts/users',
            $urlparams
        );
        $curl = new curl();
        $response = $curl->get($url->out(false), [], self::get_curl_options($apikey));
        if (self::request_succeeded($curl, $response)) {
            $responsedata = self::decode_json_response($response);
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
        $contextlevel = '';
        if ($context->contextlevel == CONTEXT_COURSE) {
            $contextlevel = 'course';
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $contextlevel = 'system';
        } else {
            return;
        }
        $urlparams = ['contextlevel' => $contextlevel];
        if ($contextlevel === 'course') {
            $urlparams['courseid'] = (int)$context->instanceid;
        }
        $url = new \moodle_url(
            'https://services.corolair.dev/moodle-integration/privacy/contexts/delete',
            $urlparams
        );
        $curl = new curl();
        $response = $curl->delete($url->out(false), [], self::get_curl_options($apikey));
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $outcome = self::validate_deletion_response(
            $response,
            $contextlevel,
            $contextlevel === 'course' ? (int)$context->instanceid : null
        );
        self::record_deletion_event($context, $contextlevel, $outcome);
        return;
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
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return;
        }
        $user = $contextlist->get_user();
        $userid = $user->id;
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/'
             . $userid . '/delete';
        $curl = new curl();
        $response = $curl->delete($url, [], self::get_curl_options($apikey));
        if (!self::request_succeeded($curl, $response)) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $outcome = self::validate_deletion_response($response, 'user', $userid);
        self::record_deletion_event(context_system::instance(), 'user', $outcome, $userid);
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
        foreach ($users as $userid) {
            $url = 'https://services.corolair.dev/moodle-integration/privacy/users/'
                 . $userid . '/delete';
            $response = $curl->delete($url, [], self::get_curl_options($apikey));
            if (!self::request_succeeded($curl, $response)) {
                throw new \moodle_exception('curlerror', 'local_corolair');
            }
            $outcome = self::validate_deletion_response($response, 'user', (int)$userid);
            self::record_deletion_event(
                $userlist->get_context(),
                'user',
                $outcome,
                (int)$userid
            );
        }
    }
}
