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
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Uninstall function for the local_corolair plugin.
 *
 * This function performs the following steps:
 * 1. Removes the custom role 'Corolair Manager'.
 * 2. Removes the external service and associated tokens and functions.
 * 3. Retrieves the 'apikey' value before deleting all Corolair-specific config settings.
 * 4. Removes all Corolair-specific config settings from config_plugins.
 * 5. Sends a deregistration request to the external API.
 *
 * @return bool True on success.
 * @throws moodle_exception If an error occurs during the uninstallation process.
 */
function xmldb_local_corolair_uninstall() {
    global $DB, $CFG;

    // Define API URL for deregistration
    $url = "https://services.corolair.com/moodle-integration/plugin/organization/deregister";

    try {
        // Step 1: Remove the custom role 'Corolair Manager'
        $role = $DB->get_record('role', ['shortname' => 'corolair']);
        if ($role) {
            // Unassign role from users and delete the role
            role_unassign_all(['roleid' => $role->id]);
            $DB->delete_records('role', ['id' => $role->id]);
            $DB->delete_records('role_context_levels', ['roleid' => $role->id]);
            $DB->delete_records('role_capabilities', ['roleid' => $role->id]);
        }

        // Step 2: Remove external service and associated tokens and functions
        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $DB->delete_records('external_tokens', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services', ['id' => $service->id]);
        }

        // Step 3: Retrieve the 'apikey' value before deleting all Corolair-specific config settings
        $apikey_record = $DB->get_record('config_plugins', ['plugin' => 'local_corolair', 'name' => 'apikey'], 'value');

        // Step 4: Remove all Corolair-specific config settings from config_plugins
        $DB->delete_records('config_plugins', ['plugin' => 'local_corolair']);

        // Step 5: Send deregistration request to external API
        $apikey = '';
        if ($apikey_record) {
            $apikey = $apikey_record->value;
        }
        $moodlebaseurl = $CFG->wwwroot;
        $postData = json_encode([
            'url' => $moodlebaseurl,
            'apiKey' => $apikey
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);

        // Set options to make the request asynchronous
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Don't wait for response
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100); // 100 ms timeout, just enough to send the request
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 100); // Connection timeout

        // Execute the request
        curl_exec($ch);

        // Close the cURL session immediately without waiting for the response
        curl_close($ch);
        return true;

    } catch (moodle_exception $me) {
        throw $me;
    } catch (Exception $e) {
        throw new moodle_exception('generalexceptionmessage', 'local_corolair', '', null, $e->getMessage());
    }
}
