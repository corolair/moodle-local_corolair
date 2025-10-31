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
            $apikey = get_config('local_corolair', 'apikey');
            if (empty($apikey) ||
                strpos($apikey, 'No Corolair Api Key') === 0 ||
                strpos($apikey, 'Aucune Clé API Corolair') === 0||
                strpos($apikey, 'No hay clave API de Corolair') === 0
                ) {
                \core\notification::add(
                    get_string('noapikey', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                \core\notification::add(
                    get_string('calendlydemo', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
            $url = "https://services.corolair.dev/moodle-integration/update";
            $postdata = json_encode(['apiKey' => $apikey]);
            $curl = new curl();
            $options = [
                "CURLOPT_RETURNTRANSFER" => true,
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postdata),
                ],
            ];
            $response = $curl->post($url, $postdata, $options);
            $errno = $curl->get_errno();
            if ($response === false || $errno !== 0) {
                debugging(curl_error($ch), DEBUG_DEVELOPER);
                \core\notification::add(
                    get_string('curlerror', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                \core\notification::add(
                    get_string('calendlydemo', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
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
        debugging($me->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('unexpectederror', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('calendlydemo', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('unexpectederror', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('calendlydemo', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
    return true;
}
