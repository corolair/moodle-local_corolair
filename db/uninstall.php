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
 * Uninstall script for local_corolair plugin.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Uninstall function for the local_corolair plugin.
 *
 * This function performs the following steps:
 * 1. Removes the custom role 'Raison Manager'.
 * 2. Removes the external service and associated tokens and functions.
 * 3. Retrieves the 'apikey' value before deleting all Raison-specific config settings.
 * 4. Removes all Raison-specific config settings from config_plugins.
 * 5. Sends a deregistration request to the external API.
 *
 * @return bool True on success.
 * @throws moodle_exception If an error occurs during the uninstallation process.
 */
function xmldb_local_corolair_uninstall() {
    global $DB, $CFG;
    // Define API URL for deregistration.
    $url = "https://services.corolair.com/moodle-integration/plugin/organization/deregister";
    try {
        // Step 1: Remove the custom role 'Corolair Manager'.
        $role = $DB->get_record('role', ['shortname' => 'corolair']);
        if ($role) {
            // Unassign role from users and delete the role.
            role_unassign_all(['roleid' => $role->id]);
            $DB->delete_records('role', ['id' => $role->id]);
            $DB->delete_records('role_context_levels', ['roleid' => $role->id]);
            $DB->delete_records('role_capabilities', ['roleid' => $role->id]);
        }
        // Step 2: Remove external service and associated tokens and functions.
        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $DB->delete_records('external_tokens', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services', ['id' => $service->id]);
        }
        // Step 3: Retrieve the 'apikey' value before deleting all Raison-specific config settings.
        $apikeyrecord = $DB->get_record('config_plugins', ['plugin' => 'local_corolair', 'name' => 'apikey'], 'value');
        // Step 4: Remove all Raison-specific config settings from config_plugins.
        $DB->delete_records('config_plugins', ['plugin' => 'local_corolair']);
        // Step 5: Send deregistration request to external API.
        $apikey = '';
        if ($apikeyrecord) {
            $apikey = $apikeyrecord->value;
        }
        $moodlebaseurl = $CFG->wwwroot;
        $postdata = json_encode([
            'url' => $moodlebaseurl,
            'apiKey' => $apikey,
        ]);
        $curl = new curl();
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = $curl->post($url, $postdata, $options);
        return true;

    } catch (moodle_exception $me) {
        debugging($me->getMessage() , DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('unexpectederror', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;

    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('unexpectederror', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
}
