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
 * This script creates the Corolair role and leaves the integration inactive.
 * A site administrator must explicitly approve the site-wide web service changes
 * from setup.php before registration is queued. It makes no outbound requests.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Installation script for the local_corolair plugin.
 *
 * Every step converges rather than assuming a clean site, so installing over state
 * left behind by an uninstall that did not finish repairs that state instead of
 * failing. See {@see \local_corolair\local\role_provisioner}.
 *
 * @return bool True on success.
 */
function xmldb_local_corolair_install() {
    global $USER;
    try {
        // Installation must not make site-wide web service changes without consent.
        set_config('setupconsented', 0, 'local_corolair');
        set_config('setupconsentrequired', 0, 'local_corolair');
        set_config('setupcompleted', 0, 'local_corolair');
        unset_config('legacycredentialmigrationcompletedat', 'local_corolair');
        unset_config('legacycredentialmigrationpending', 'local_corolair');
        unset_config('setupconsentedby', 'local_corolair');
        unset_config('setupconsentedat', 'local_corolair');
        unset_config('setupdisclosureversion', 'local_corolair');
        unset_config('setupdisclosureacknowledgedby', 'local_corolair');
        unset_config('setupdisclosureacknowledgedat', 'local_corolair');
        // Create (or repair) the "Raison Manager" role. This is convergent, so a role
        // left behind by an uninstall that did not finish is reused rather than fatal.
        $roleid = \local_corolair\local\role_provisioner::ensure_role();

        // The $USER->id value is 0 under admin/cli/install.php, where role_assign() would throw.
        // Skipping is safe: site administrators bypass capability checks, and setup.php
        // gates on moodle/site:config rather than on this role.
        if ($USER->id > 0) {
            role_assign($roleid, $USER->id, context_system::instance()->id);
        }
        $setuplink = (new moodle_url('/local/corolair/setup.php'))->out();
        \core\notification::add(
            get_string(
                \local_corolair\local\setup_manager::enablement_consent_required()
                    ? 'setuprequirednotification'
                    : 'setupreadynotification',
                'local_corolair',
                $setuplink
            ),
            \core\output\notification::NOTIFY_WARNING
        );
        return true;
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        // Report the underlying reason to the administrator. Sending it only to
        // debugging() made a failed install invisible unless developer debugging
        // happened to be enabled, leaving the plugin installed without its role.
        \core\notification::add(
            get_string('installfailed', 'local_corolair', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('installtroubleshoot', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('calendlydemo', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
}

/**
 * Repair an installation that was interrupted partway through.
 *
 * Core records the plugin version *before* it runs install.php, so a failure in
 * that script leaves the plugin registered as installed while its role was never
 * provisioned -- and install.php is never invoked again. The escape hatch is this
 * hook: core sets the "installrunning" flag around install.php, and on the next
 * upgrade calls xmldb_<plugin>_install_recovery() if that flag is still set. See
 * upgrade_plugins() in lib/upgradelib.php (public/lib/upgradelib.php from 5.1).
 *
 * Only the role is repaired here. The consent and setup flags are deliberately
 * left alone: the plugin is reachable once registered, so an administrator may
 * already have completed setup.php between the failed install and this recovery,
 * and resetting those would silently discard a real consent record.
 *
 * @return bool True on success.
 */
function xmldb_local_corolair_install_recovery() {
    global $USER;
    try {
        $roleid = \local_corolair\local\role_provisioner::ensure_role();
        if ($USER->id > 0) {
            role_assign($roleid, $USER->id, context_system::instance()->id);
        }
        return true;
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        \core\notification::add(
            get_string('installfailed', 'local_corolair', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
        \core\notification::add(
            get_string('installtroubleshoot', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
}
