<?php
namespace local_corolair\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use context;
use context_user;
use context_system;
use context_course;

defined('MOODLE_INTERNAL') || die();

if (interface_exists('\core_privacy\local\request\core_userlist_provider')) {
    interface lc_userlist_provider extends \core_privacy\local\request\core_userlist_provider{}
} else {
    interface lc_userlist_provider {};
}

class provider implements \core_privacy\local\metadata\provider,

\core_privacy\local\request\subsystem\provider,

\core_privacy\local\request\subsystem\plugin_provider,

lc_userlist_provider{
    public static function get_metadata(collection $collection): collection {
        // Here you will add more items into the collection.
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
        // Initialize an empty context list.
        $contextlist = new contextlist();
        // Retrieve the API key from the configuration.
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            // Return the empty context list if the API key is not set or invalid.
            return $contextlist;
        }

        // Construct the URL for the external API request.
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/' . $userid . '/contexts?apikey=' . urlencode($apikey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if the response is valid and the HTTP status code is not an error.
        if ($response === false || $httpcode >= 400) {
            // Return the empty context list if the API request failed.
            return $contextlist;
        }

        // Decode the JSON response from the external API.
        $responseData = json_decode($response, true);
        if (is_array($responseData)) {
            // Iterate over the contexts returned by the API.
            foreach ($responseData as $contextData) {
            $contextLevelName = $contextData['contextIdentifier'];
            $payload = $contextData['payload'];

            // Prepare the SQL query and parameters based on the context level.
            if ($contextLevelName === 'CONTEXT_COURSE') {
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
                        // Add the contexts to the context list using the SQL query and parameters.
                        $contextlist->add_from_sql($sql, $params);
                    }
                }
            } else if ($contextLevelName === 'CONTEXT_SYSTEM') {
                $sql = "SELECT c.id 
                    FROM {context} c 
                    WHERE c.contextlevel = :contextlevel";
                $params = [
                'contextlevel' => CONTEXT_SYSTEM,
                ];
            }

            // Add the contexts to the context list using the SQL query and parameters.
            $contextlist->add_from_sql($sql, $params);
            }
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $approved_contextlist) {
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            return;
        }
        $user = $approved_contextlist->get_user();
        $userid = $user->id;
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/' . $userid . '/export?apikey=' . urlencode($apikey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if the response is valid and the HTTP status code is not an error.
        if ($response === false || $httpcode >= 400) {
            // Return the empty context list if the API request failed.
            return;
        }
        // Decode the JSON response from the external API.
        $responseData = json_decode($response, true);
        
         $context = context_system::instance();

        if (is_array($responseData)) {
            foreach ($responseData as $data) {
            $payload = $data['payload'];
            $subcontext = $data['subcontext'];

            \core_privacy\local\request\writer::with_context($context)
                ->export_data($subcontext, (object) $payload);
            }
        }
    }

    public static function get_users_in_context(userlist $userlist) {
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            return;
        }
        $context = $userlist->get_context();
        $contextLevel = '';
        if ($context->contextlevel == CONTEXT_COURSE) {
            $contextLevel = 'course';
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $contextLevel = 'system';
        } else {
            return;
        }

        $url = 'https://services.corolair.dev/moodle-integration/privacy/contexts/users?apikey=' . urlencode($apikey) . '&contextlevel=' . $contextLevel;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpcode < 400) {
            $responseData = json_decode($response, true);
            if (isset($responseData['userIds']) && is_array($responseData['userIds'])) {
                $userids = $responseData['userIds'];
                $userlist->add_users($userids);
            }
            }
        return;
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            return;
        }
        $contextLevel = '';
        if ($context->contextlevel == CONTEXT_COURSE) {
            $contextLevel = 'course';
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $contextLevel = 'system';
        } else {
            return;
        }
        $url = 'https://services.corolair.dev/moodle-integration/privacy/contexts/delete?apikey=' . urlencode($apikey) . '&contextlevel=' . $contextLevel;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return;
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            return;
        }
        $user = $contextlist->get_user();
        $userid = $user->id;
        
        $url = 'https://services.corolair.dev/moodle-integration/privacy/users/' . $userid . '/delete?apikey=' . urlencode($apikey);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

    }
    
    

    public static function delete_data_for_users(approved_userlist $userlist) {
        $apikey = get_config('local_corolair', 'apikey');
        if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {  
            return;
        }

        $users = $userlist->get_userids();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

        foreach ($users as $userid) {
            $url = 'https://services.corolair.dev/moodle-integration/privacy/users/' . $userid . '/delete?apikey=' . urlencode($apikey);
            curl_setopt($ch, CURLOPT_URL, $url);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);
        
    } 
}
