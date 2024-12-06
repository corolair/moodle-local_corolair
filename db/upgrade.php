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
 * Upgrade script for local_corolair plugin.
 * @package   local_corolair
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Executes the upgrade steps for the local_corolair plugin.
 *
 * @param int $oldversion The current version of the plugin before the upgrade.
 * @return bool True on success, false on failure.
 * @throws moodle_exception If critical errors occur during the upgrade process.
 */
function xmldb_local_corolair_upgrade($oldversion) {
    global $DB;

    $result = true;

    try {
        // Step 1: Remove the "Corolair" menu item if present in custommenuitems.
        if ($result && $oldversion < 2024091600) {
            $custommenuitems = $DB->get_record('config', ['name' => 'custommenuitems']);
            $newmenuitem = "Corolair|/local/corolair/trainer.php";

            if ($custommenuitems && strpos($custommenuitems->value, $newmenuitem) !== false) {
                $custommenuitems->value = str_replace($newmenuitem, '', $custommenuitems->value);
                $DB->update_record('config', $custommenuitems);
            }
        }

        // Step 2: Notify external Corolair service of the update.
        if ($result && $oldversion < 2024100701) {
            $url = "https://services.corolair.dev/moodle-integration/update";
            $apikey = get_config('local_corolair', 'apikey');

            if (empty($apikey) || strpos($apikey, 'No Corolair Api Key') === 0) {
                throw new moodle_exception('noapikey', 'local_corolair');
            }

            $postData = json_encode(['apiKey' => $apikey]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postData),
            ]);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new moodle_exception('curlerror', 'local_corolair', '', null, curl_error($ch));
            }

            curl_close($ch);
        }

        // Step 3: Add required capabilities to the external "Corolair REST" service.
        if ($result && $oldversion < 2024101100) {
            $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);

            if ($service) {
                $capabilities = [
                    'core_course_get_categories',
                    'core_enrol_get_enrolled_users_with_capability',
                ];

                foreach ($capabilities as $capability) {
                    $existing = $DB->get_record('external_services_functions', [
                        'externalserviceid' => $service->id,
                        'functionname' => $capability,
                    ]);

                    if (!$existing) {
                        $function = new stdClass();
                        $function->externalserviceid = $service->id;
                        $function->functionname = $capability;
                        $DB->insert_record('external_services_functions', $function);
                    }
                }
            }
        }
    } catch (moodle_exception $me) {
        // Log and rethrow Moodle-specific exceptions.
        $result = false;
        debugging($me->getMessage(), DEBUG_DEVELOPER);
        throw $me;
    } catch (Exception $e) {
        // Convert generic exceptions to Moodle exceptions for better handling.
        $result = false;
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        throw new moodle_exception('generalexceptionmessage', 'local_corolair', '', null, $e->getMessage());
    }

    return $result;
}
