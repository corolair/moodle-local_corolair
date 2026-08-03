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
 * The remote deregistration is attempted FIRST, while the API key and configuration are
 * still present, so the external provider is asked to remove the organization's data
 * before the local deletion path is destroyed. Local cleanup then proceeds regardless of
 * the remote outcome (Moodle removes the plugin either way); if deregistration could not
 * be confirmed, the administrator is warned to complete it manually.
 *
 * Steps:
 * 1. Send and validate the deregistration request to the external API (credentials intact).
 * 2. Remove the custom role 'Raison Manager'.
 * 3. Remove the external service and associated tokens and functions.
 * 4. Remove all Raison-specific config settings using Moodle's configuration API.
 *
 * @return bool True on success.
 * @throws moodle_exception If an error occurs during the uninstallation process.
 */
function xmldb_local_corolair_uninstall() {
    global $DB, $CFG;
    // Define API URL for deregistration.
    $url = "https://services.corolair.dev/moodle-integration/v2/plugin/organization/deregister";
    try {
        // Step 1: Confirm remote deregistration BEFORE erasing any local state.
        // A failure here must not abort local cleanup, but the admin is warned so the
        // remote deletion can be completed manually.
        $apikey = (string) (get_config('local_corolair', 'apikey') ?? '');
        $deregistered = false;
        try {
            $moodlebaseurl = $CFG->wwwroot;
            $postdata = json_encode([
                'url' => $moodlebaseurl,
            ]);
            $curl = new curl();
            $options = [
                "CURLOPT_RETURNTRANSFER" => true,
                "CURLOPT_CONNECTTIMEOUT" => 15,
                "CURLOPT_TIMEOUT" => 60,
                'CURLOPT_HTTPHEADER' => [
                    'Authorization: Bearer ' . $apikey,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postdata),
                ],
            ];
            $response = \local_corolair\local\audited_request::execute(
                $curl,
                function () use ($curl, $url, $postdata, $options) {
                    return $curl->post($url, $postdata, $options);
                },
                \local_corolair\local\audited_request::OP_ORGANIZATION_DEREGISTER,
                \context_system::instance()
            );
            $errno = $curl->get_errno();
            $info = $curl->get_info();
            $httpstatus = (int)($info['http_code'] ?? 0);
            if ($response !== false && $errno === 0 && $httpstatus >= 200 && $httpstatus < 300) {
                $responsedata = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($responsedata) && ($responsedata['status'] ?? null) === 'disconnected') {
                    $deregistered = true;
                }
            }
        } catch (Exception $deregisterexception) {
            debugging($deregisterexception->getMessage(), DEBUG_DEVELOPER);
        }
        if (!$deregistered) {
            \core\notification::add(
                get_string('deregisterfailed', 'local_corolair'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        // Step 2: Remove the custom role 'Corolair Manager'.
        $role = $DB->get_record('role', ['shortname' => 'corolair']);
        if ($role) {
            // Unassign role from users and delete the role.
            role_unassign_all(['roleid' => $role->id]);
            $DB->delete_records('role', ['id' => $role->id]);
            $DB->delete_records('role_context_levels', ['roleid' => $role->id]);
            $DB->delete_records('role_capabilities', ['roleid' => $role->id]);
        }
        // Step 3: Remove external service and associated tokens and functions.
        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $DB->delete_records('external_tokens', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services', ['id' => $service->id]);
        }
        // Step 4: Remove all Raison-specific config settings via the configuration API.
        $pluginconfig = (array) get_config('local_corolair');
        foreach (array_keys($pluginconfig) as $configname) {
            unset_config($configname, 'local_corolair');
        }
        return true;
    } catch (moodle_exception $me) {
        debugging($me->getMessage(), DEBUG_DEVELOPER);
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
