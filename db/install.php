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

defined('MOODLE_INTERNAL') || die();

function xmldb_local_corolair_install() {
    global $DB, $CFG, $USER, $SITE;

    $admin_id = $USER->id;
    $url = "https://services.corolair.com/moodle-integration/plugin/organization/register";

    try {
        // Step 1: Enable web services
        $config_record = $DB->get_record('config', ['name' => 'enablewebservices']);
        if ($config_record) {
            $config_record->value = 1;
            $DB->update_record('config', $config_record);
        } else {
            $DB->insert_record('config', (object)['name' => 'enablewebservices', 'value' => 1]);
        }

        // Step 2: Enable REST protocol
        $webservice_protocols = $DB->get_record('config', ['name' => 'webserviceprotocols']);
        if ($webservice_protocols) {
            if (empty($webservice_protocols->value)) {
                $webservice_protocols->value = 'rest';
                $DB->update_record('config', $webservice_protocols);
            } elseif (strpos($webservice_protocols->value, 'rest') === false) {
                $webservice_protocols->value .= ',rest';
                $DB->update_record('config', $webservice_protocols);
            }
        } else {
            $DB->insert_record('config', (object)['name' => 'webserviceprotocols', 'value' => 'rest']);
        }

        // Step 3: Create external service
        $service = (object)[
            'name' => get_string('servicename', 'local_corolair'),
            'shortname' => 'corolair_rest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'timemodified' => time(),
            'uploadfiles' => 1,
            'downloadfiles' => 1,
            'timecreated' => time()
        ];
        $serviceid = $DB->insert_record('external_services', $service);

        if (!$serviceid) {
            throw new moodle_exception('servicecreationfailed', 'local_corolair');
        }

        // Step 4: Assign capabilities to the service
        $capabilities = [
            'core_user_get_users', 'core_user_get_users_by_field', 'core_course_get_courses',
            'core_course_get_contents', 'mod_resource_get_resources_by_courses',
            'core_enrol_get_users_courses', 'core_enrol_get_enrolled_users',
            'core_webservice_get_site_info', 'core_enrol_get_enrolled_users_with_capability',
            'core_course_get_categories'
        ];
        foreach ($capabilities as $capability) {
            $DB->insert_record('external_services_functions', (object)[
                'externalserviceid' => $serviceid,
                'functionname' => $capability
            ]);
        }

        // Step 5: Generate a token
        $token = (object)[
            'token' => md5(uniqid(rand(), true)),
            'userid' => $admin_id,
            'tokentype' => 0,
            'contextid' => context_system::instance()->id,
            'creatorid' => $admin_id,
            'timecreated' => time(),
            'validuntil' => 0,
            'externalserviceid' => $serviceid,
            'privatetoken' => random_string(64),
            'name' => get_string('tokenname', 'local_corolair')
        ];
        $DB->insert_record('external_tokens', $token);

        // Step 6: Create "Corolair Manager" role
        $roleid = create_role(
            get_string('rolename', 'local_corolair'),
            'corolair',
            get_string('roledescription', 'local_corolair'),
            null,
            null
        );

        if ($roleid) {
            foreach ([CONTEXT_SYSTEM, CONTEXT_COURSE] as $contextlevel) {
                $DB->insert_record('role_context_levels', (object)[
                    'roleid' => $roleid,
                    'contextlevel' => $contextlevel
                ]);
            }
            $DB->insert_record('role_capabilities', (object)[
                'roleid' => $roleid,
                'contextid' => context_system::instance()->id,
                'capability' => 'local/corolair:createtutor',
                'permission' => CAP_ALLOW,
                'timemodified' => time()
            ]);
            role_assign($roleid, $USER->id, context_system::instance()->id);
        }

        // Step 7: Register Moodle instance with Corolair
        $postData = json_encode([
            'url' => $CFG->wwwroot,
            'webserviceToken' => $token->token,
            'email' => $USER->email,
            'firstname' => $USER->firstname,
            'lastname' => $USER->lastname,
            'siteName' => $SITE->fullname
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $response = curl_exec($ch);
        if (!$response || curl_errno($ch)) {
            throw new moodle_exception('curlerror', 'local_corolair', '', null, curl_error($ch));
        }
        $json_response = json_decode($response, true);
        if (!isset($json_response['apiKey'])) {
            throw new moodle_exception('apikeymissing', 'local_corolair');
        }
        set_config('apikey', $json_response['apiKey'], 'local_corolair');
        set_config('corolairlogin', $USER->email, 'local_corolair');

        curl_close($ch);
    } catch (Exception $e) {
        throw new moodle_exception('generalexceptionmessage', 'local_corolair', '', null, $e->getMessage());
    }
}
