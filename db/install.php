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
 * Install script for local_corolair plugin.
 *
 * This script performs the following actions:
 * 1. Configures Moodle to enable web services and REST protocols.
 * 2. Creates a custom external service and assigns capabilities.
 * 3. Generates and assigns a token for the service.
 * 4. Creates the "Corolair Manager" role with specific permissions.
 * 5. Registers the Moodle instance with the Corolair platform.
 *
 * @package   local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Installation script for the local_corolair plugin.
 */
function xmldb_local_corolair_install() {
    global $DB, $CFG, $USER, $SITE;
    $adminid = $USER->id;
    $url = "https://services.corolair.com/moodle-integration/plugin/organization/register";
    try {
        $moodlerooturl = $CFG->wwwroot;
        // Check if the Moodle instance is running on localhost.
        if (strpos($moodlerooturl, 'localhost') !== false || strpos($moodlerooturl, '127.0.0.1') !== false) {
            \core\notification::add(
                get_string('localhosterror', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            return false;
        }
        // Enable web services.
        $configrecord = $DB->get_record('config', ['name' => 'enablewebservices']);
        if ($configrecord) {
            $configrecord->value = 1;
            $DB->update_record('config', $configrecord);
        } else {
            $DB->insert_record('config', (object)['name' => 'enablewebservices', 'value' => 1]);
        }
        // Enable REST protocol.
        $webserviceprotocols = $DB->get_record('config', ['name' => 'webserviceprotocols']);
        if ($webserviceprotocols) {
            if (empty($webserviceprotocols->value)) {
                $webserviceprotocols->value = 'rest';
                $DB->update_record('config', $webserviceprotocols);
            } else if (strpos($webserviceprotocols->value, 'rest') === false) {
                $webserviceprotocols->value .= ',rest';
                $DB->update_record('config', $webserviceprotocols);
            }
        } else {
            $DB->insert_record('config', (object)['name' => 'webserviceprotocols', 'value' => 'rest']);
        }
        // Check if there is already a service with the same shortname.
        $existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($existingservice) {
            $existingserviceid = $existingservice->id;
            $DB->delete_records('external_tokens', ['externalserviceid' => $existingserviceid]);
            $DB->delete_records('external_services_functions', ['externalserviceid' => $existingserviceid]);
            $DB->delete_records('external_services', ['id' => $existingserviceid]);
        }
        // Create new  external service.
        $service = (object)[
            'name' => get_string('servicename', 'local_corolair'),
            'shortname' => 'corolair_rest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'timemodified' => time(),
            'uploadfiles' => 1,
            'downloadfiles' => 1,
            'timecreated' => time(),
        ];
        $serviceid = $DB->insert_record('external_services', $service);
        if (!$serviceid) {
            \core\notification::add(
                get_string('servicecreationerror', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            return false;
        }
        // Assign capabilities to the service.
        $capabilities = [
            'core_user_get_users',
            'core_user_get_users_by_field',
            'core_course_get_courses',
            'core_course_get_contents',
            'mod_resource_get_resources_by_courses',
            'core_enrol_get_users_courses',
            'core_enrol_get_enrolled_users',
            'core_webservice_get_site_info',
            'core_enrol_get_enrolled_users_with_capability',
            'core_course_get_categories',
        ];
        foreach ($capabilities as $capability) {
            $insertcapability = $DB->insert_record('external_services_functions', (object)[
                'externalserviceid' => $serviceid,
                'functionname' => $capability,
            ]);
            if (!$insertcapability) {
                \core\notification::add(
                    get_string('capabilityassignerror', 'local_corolair', $capability),
                    \core\output\notification::NOTIFY_ERROR
                );
                \core\notification::add(
                    get_string('installtroubleshoot', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
        }
        // Generate a token.
        $token = (object)[
            'token' => md5(uniqid(rand(), true)),
            'userid' => $adminid,
            'tokentype' => 0,
            'contextid' => context_system::instance()->id,
            'creatorid' => $adminid,
            'timecreated' => time(),
            'validuntil' => 0,
            'externalserviceid' => $serviceid,
            'privatetoken' => random_string(64),
            'name' => get_string('tokenname', 'local_corolair'),
        ];
        $insertedtoken = $DB->insert_record('external_tokens', $token);
        if (!$insertedtoken) {
            \core\notification::add(
                get_string('tokencreationerror', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            return false;
        }
        // Create "Corolair Manager" role.
        $roleid = create_role(
            get_string('rolename', 'local_corolair'),
            'corolair',
            get_string('roledescription', 'local_corolair'),
            null,
            null
        );
        if (!$roleid) {
            \core\notification::add(
                get_string('roleproblem', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            return false;
        }
        foreach ([CONTEXT_SYSTEM, CONTEXT_COURSE] as $contextlevel) {
            $DB->insert_record('role_context_levels', (object)[
                'roleid' => $roleid,
                'contextlevel' => $contextlevel,
            ]);
        }
        $DB->insert_record('role_capabilities', (object)[
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
            'capability' => 'local/corolair:createtutor',
            'permission' => CAP_ALLOW,
            'timemodified' => time(),
        ]);
        role_assign($roleid, $USER->id, context_system::instance()->id);
        // Register Moodle instance with Corolair.
        $postdata = json_encode([
            'url' => $moodlerooturl,
            'webserviceToken' => $token->token,
            'email' => $USER->email,
            'firstname' => $USER->firstname,
            'lastname' => $USER->lastname,
            'siteName' => $SITE->fullname,
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        if (!$response || curl_errno($ch)) {
            \core\notification::add(
                get_string('curlerror', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            debugging(curl_error($ch), DEBUG_DEVELOPER);
            return false;
        }
        curl_close($ch);
        $jsonresponse = json_decode($response, true);
        if (!isset($jsonresponse['apiKey'])) {
            \core\notification::add(
                get_string('apikeymissing', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            \core\notification::add(
                get_string('installtroubleshoot', 'local_corolair'),
                \core\output\notification::NOTIFY_ERROR
            );
            return false;
        }
        set_config('apikey', $jsonresponse['apiKey'], 'local_corolair');
        set_config('corolairlogin', $USER->email, 'local_corolair');
        return true;
    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('unexpectederror', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('installtroubleshoot', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
}
