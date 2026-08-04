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
 * Remote deregistration is attempted FIRST, while the API key and configuration are
 * still present, so the external provider is asked to remove the organization's data
 * before the local deletion path is destroyed. Local cleanup then proceeds regardless of
 * the remote outcome (Moodle removes the plugin either way); if deregistration could not
 * be confirmed, the administrator is warned to complete it manually.
 *
 * Steps:
 * 1. Send and validate the deregistration request, with bounded retries (credentials intact).
 * 2. Revoke the external service and remove its tokens and functions.
 * 3. Remove the custom role 'Raison Manager'.
 * 4. Remove all Raison-specific config settings using Moodle's configuration API.
 *
 * @return bool True on success.
 * @throws moodle_exception If an error occurs during the uninstallation process.
 */
function xmldb_local_corolair_uninstall() {
    global $DB, $CFG;
    try {
        // Step 1: Attempt remote deregistration before erasing any local state.
        $apikey = (string) (get_config('local_corolair', 'apikey') ?? '');
        $deregistered = \local_corolair\local\organization_deregistration::execute($apikey, $CFG->wwwroot);
        $deregisterwarning = $deregistered ? '' : get_string('deregisterfailed', 'local_corolair');

        // Step 2: Revoke the external service and remove associated tokens and functions.
        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $DB->delete_records('external_tokens', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
            $DB->delete_records('external_services', ['id' => $service->id]);
        }
        // Step 3: Remove the custom role 'Corolair Manager'.
        $role = $DB->get_record('role', ['shortname' => 'corolair']);
        if ($role) {
            // Unassign role from users and delete the role.
            role_unassign_all(['roleid' => $role->id]);
            $DB->delete_records('role', ['id' => $role->id]);
            $DB->delete_records('role_context_levels', ['roleid' => $role->id]);
            $DB->delete_records('role_capabilities', ['roleid' => $role->id]);
        }
        // Step 4: Remove all Raison-specific config settings via the configuration API.
        $pluginconfig = (array) get_config('local_corolair');
        foreach (array_keys($pluginconfig) as $configname) {
            unset_config($configname, 'local_corolair');
        }
        if ($deregisterwarning !== '') {
            \core\notification::add(
                $deregisterwarning,
                \core\output\notification::NOTIFY_WARNING
            );
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
