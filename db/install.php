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
 * from setup.php before registration is queued.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Installation script for the local_corolair plugin.
 */
function xmldb_local_corolair_install() {
    global $DB, $USER;
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
        // Create "Raison Manager" role.
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
            \core\notification::add(
                get_string('calendlydemo', 'local_corolair'),
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
        foreach (
            [
                'local/corolair:createtutor',
                'local/corolair:viewroles',
                'local/corolair:assignmanagerrole',
            ] as $capability
        ) {
            $DB->insert_record('role_capabilities', (object)[
                'roleid' => $roleid,
                'contextid' => context_system::instance()->id,
                'capability' => $capability,
                'permission' => CAP_ALLOW,
                'timemodified' => time(),
            ]);
        }
        $adminid = $USER->id;
        role_assign($roleid, $adminid, context_system::instance()->id);
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
        \core\notification::add(
            get_string('calendlydemo', 'local_corolair'),
            \core\output\notification::NOTIFY_ERROR
        );
        return false;
    }
}
