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
 * @copyright 2024 Corolair
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
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    local_corolair_userlist_provider {

    /**
     * Returns metadata about the external location link for Corolair.
     *
     * @param collection $collection The initial collection to add metadata to.
     * @return collection The updated collection with Corolair metadata added.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('corolair', [
            'userid' => 'privacy:metadata:corolair:userid',
            'useremail' => 'privacy:metadata:corolair:useremail',
            'userfirstname' => 'privacy:metadata:corolair:userfirstname',
            'userlastname' => 'privacy:metadata:corolair:userlastname',
            'userrolename' => 'privacy:metadata:corolair:userrolename',
            'interaction' => 'privacy:metadata:corolair:interaction',
        ], 'privacy:metadata:corolair');
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
        $contextlist = new contextlist();
        $apikey = get_config('local_corolair', 'apikey');
        $noapikey = get_string('noapikey', 'local_corolair');
        if (!$apikey || strpos($apikey, $noapikey) === 0) {
            return $contextlist;
        }
        $url = 'https://services.corolair.com/moodle-integration/privacy/users/'
             . $userid . '/contexts?apikey=' . urlencode($apikey);
        $curl = new curl();
        $response = $curl->get($url);
        $errno = $curl->get_errno();
        if ($response === false || $errno !== 0) {
            return $contextlist;
        }
        $responsedata = json_decode($response, true);
        if (is_array($responsedata)) {
            foreach ($responsedata as $contextdata) {
                $contextlevelname = $contextdata['contextIdentifier'];
                $payload = $contextdata['payload'];
                if ($contextlevelname === 'CONTEXT_COURSE') {
                    if (!empty($payload) && is_array($payload)) {
                        foreach ($payload as $instanceid) {
                            $sql = "SELECT c.id
                                    FROM {context} c
                                    WHERE c.contextlevel = :contextlevel
                                    AND c.instanceid = :instanceid";
                            $params = [
                                'instanceid' => $instanceid,
                                'contextlevel' => CONTEXT_COURSE,
                            ];
                            $contextlist->add_from_sql($sql, $params);
                        }
                    }
                } else if ($contextlevelname === 'CONTEXT_SYSTEM') {
                    $sql = "SELECT c.id
                            FROM {context} c
                            WHERE c.contextlevel = :contextlevel";
                    $params = [
                        'contextlevel' => CONTEXT_SYSTEM,
                    ];
                    $contextlist->add_from_sql($sql, $params);
                }
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
        $url = 'https://services.corolair.com/moodle-integration/privacy/users/'
             . $userid . '/export?apikey=' . urlencode($apikey);
        $curl = new curl();
        $response = $curl->get($url);
        $errno = $curl->get_errno();
        if ($response === false || $errno !== 0) {
            return;
        }
        $responsedata = json_decode($response, true);
        $context = context_system::instance();
        if (is_array($responsedata)) {
            foreach ($responsedata as $data) {
                $payload = $data['payload'];
                $subcontext = $data['subcontext'];
                \core_privacy\local\request\writer::with_context($context)
                    ->export_data($subcontext, (object) $payload);
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
        $url = 'https://services.corolair.com/moodle-integration/privacy/contexts/users?apikey='
             . urlencode($apikey) . '&contextlevel=' . $contextlevel;
        $curl = new curl();
        $response = $curl->get($url);
        $errno = $curl->get_errno();
        if ($response !== false && $errno === 0) {
            $responsedata = json_decode($response, true);
            if (isset($responsedata['userIds']) && is_array($responsedata['userIds'])) {
                $userids = $responsedata['userIds'];
                $userlist->add_users($userids);
            }
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
        $url = 'https://services.corolair.com/moodle-integration/privacy/contexts/delete?apikey='
             . urlencode($apikey) . '&contextlevel=' . $contextlevel;
        $curl = new curl();
        $response = $curl->delete($url);
        return;
    }

    /**
     * Deletes data for a user based on the provided context list.
     *
     * This function retrieves the API key from the configuration and checks if it is valid.
     * If the API key is not set or is invalid, the function returns without performing any action.
     * Otherwise, it constructs a URL to the Corolair service to delete the user's data and sends
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
        $url = 'https://services.corolair.com/moodle-integration/privacy/users/'
             . $userid . '/delete?apikey=' . urlencode($apikey);
        $curl = new curl();
        $curl->delete($url);
    }

    /**
     * Deletes data for users specified in the approved user list.
     *
     * This function sends a DELETE request to the external Corolair service to delete user data.
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
            $url = 'https://services.corolair.com/moodle-integration/privacy/users/'
                 . $userid . '/delete?apikey=' . urlencode($apikey);
            $curl->delete($url);
        }
    }
}
