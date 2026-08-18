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
 * Remove the state that only this plugin knows about.
 *
 * Deliberately narrow. Core's uninstall_plugin() runs immediately after this function
 * and already removes the web-service tokens and service (delete_service_descriptions),
 * every local_corolair configuration value (unset_all_config_for_plugin), pending ad-hoc
 * tasks (task_adhoc by component) and the capability grants (capabilities_cleanup).
 * Repeating any of that here would only create a second contract to keep correct. What
 * core cannot do is deregister the organization remotely, and delete a role created with
 * create_role(), which carries nothing tying it back to this component.
 *
 * NOTHING HERE MAY THROW. Core calls this function unguarded -- "Do not verify result,
 * let plugin complain if necessary" in lib/adminlib.php -- so an escaping exception
 * aborts the uninstall before any of that core cleanup runs, leaving live tokens and the
 * full configuration behind. Every step is isolated from the others for the same reason:
 * failing to deregister must not stop the roles from being removed, and a role that cannot
 * be deleted must not stop the service account from being suspended.
 *
 * Deregistration runs first, while the credentials it needs still exist.
 *
 * @return bool Always true.
 */
function xmldb_local_corolair_uninstall() {
    $deregistered = local_corolair_uninstall_deregister();
    local_corolair_uninstall_remove_role();
    local_corolair_uninstall_remove_service_role();
    local_corolair_uninstall_suspend_service_account();

    if (!$deregistered) {
        \core\notification::add(
            get_string('deregisterfailed', 'local_corolair'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
    return true;
}

/**
 * Ask Raison to remove the organization's data.
 *
 * @return bool True when deregistration was confirmed, or when there was nothing to
 *              deregister. False only when a real credential failed to be released.
 */
function local_corolair_uninstall_deregister(): bool {
    global $CFG;

    try {
        $apikey = \local_corolair\local\api_key::get();
        if ($apikey === null) {
            // The site never registered, so there is no organization to remove and no
            // reason to warn. Reading the raw setting instead would send the translated
            // "no API key" placeholder as a bearer token, which fails every time.
            return true;
        }
        return \local_corolair\local\organization_deregistration::execute($apikey, $CFG->wwwroot);
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Delete the "Raison Manager" role.
 *
 * @return void
 */
function local_corolair_uninstall_remove_role(): void {
    try {
        \local_corolair\local\role_provisioner::remove_role();
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('uninstallroleremovalfailed', 'local_corolair', $e->getMessage()),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

/**
 * Delete the role that carries the service account's capabilities.
 *
 * Not optional, and the one step here whose failure genuinely matters. Core's
 * capabilities_cleanup() removes role_capabilities rows for local/corolair capabilities
 * only, so everything else this role holds -- moodle/course:view, moodle/user:viewalldetails
 * and the rest -- survives the uninstall untouched. A role granting those at system context
 * is worse residue than any credential.
 *
 * @return void
 */
function local_corolair_uninstall_remove_service_role(): void {
    try {
        \local_corolair\local\service_account_provisioner::remove_role();
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('uninstallserviceroleremovalfailed', 'local_corolair', $e->getMessage()),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

/**
 * Suspend the service account.
 *
 * Suspended rather than deleted. delete_user() is irreversible and mangles the username, so
 * every reinstall would create another account and leave the previous one behind as a
 * tombstone -- and reinstalling this plugin is ordinary enough that the whole provisioning
 * path is written to be convergent. Suspension is both reversible and a stronger guarantee
 * than relying on the role having been deleted: a suspended user is refused by the web
 * service layer outright. The account carries no personal data to begin with.
 *
 * @return void
 */
function local_corolair_uninstall_suspend_service_account(): void {
    try {
        \local_corolair\local\service_account_provisioner::suspend();
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('serviceaccountremovalfailed', 'local_corolair', (object)[
                'username' => \local_corolair\local\service_account_provisioner::USERNAME,
                'error' => $e->getMessage(),
            ]),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}
