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

/**
 * Installation script for the local_corolair plugin.
 */
function xmldb_local_corolair_install() {
    global $DB, $CFG, $USER, $SITE;
    try {
        $moodlerooturl = $CFG->wwwroot;
        // Check if the Moodle instance is running on localhost.
        if (strpos($moodlerooturl, 'localhost') !== false || strpos($moodlerooturl, '127.0.0.1') !== false) {
            \core\notification::add(
                get_string('localhosterror', 'local_corolair'),
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
        // Enable web services.
        $configrecord = $DB->get_record('config', ['name' => 'enablewebservices']);
        if ($configrecord) {
            $configrecord->value = 1;
            $DB->update_record('config', $configrecord);
        } else {
            $DB->insert_record('config', (object)['name' => 'enablewebservices', 'value' => 1]);
        }
        // Enable REST protocol.
        $webserviceprotocols = $DB->get_record('config', ['name' => 'webserviceprotocols']);
        if ($webserviceprotocols) {
            if (empty($webserviceprotocols->value)) {
                $webserviceprotocols->value = 'rest';
                $DB->update_record('config', $webserviceprotocols);
            } else if (strpos($webserviceprotocols->value, 'rest') === false) {
                $webserviceprotocols->value .= ',rest';
                $DB->update_record('config', $webserviceprotocols);
            }
        } else {
            $DB->insert_record('config', (object)['name' => 'webserviceprotocols', 'value' => 'rest']);
        }
        // Create "Corolair Manager" role.
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
        $DB->insert_record('role_capabilities', (object)[
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
            'capability' => 'local/corolair:createtutor',
            'permission' => CAP_ALLOW,
            'timemodified' => time(),
        ]);
        $adminid = $USER->id;
        role_assign($roleid, $adminid, context_system::instance()->id);
        $adminemail = $USER->email;
        set_config('corolairlogin', $adminemail, 'local_corolair');
        $defaultcssvalue = '

#page-local-corolair-trainer #topofscroll {
    margin: 0 !important;
    padding: 0 !important;
    height: 100%;
    width: 100%;
    max-width: 100%;
}

#page-local-corolair-trainer #corolair-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

#page-local-corolair-trainer #page {
    overflow: hidden !important;
    height: 100vh !important;
    box-sizing: border-box !important;
    width: 100vw !important;
    padding: 0 !important;
}

#page-local-corolair-trainer #page-content {
    padding: 0 !important;
    padding: 0 !important;
    height: 100%;
}

#page-local-corolair-trainer #region-main-box {
    height: 100%;
}

#page-local-corolair-trainer #region-main {
    height: 100%;
}

#page-local-corolair-trainer div[role="main"] {
    height: 100%;
    padding: 0 !important;
}

#page-local-corolair-trainer #page-header {
    display: none;
}';
        set_config('customcss', $defaultcssvalue, 'local_corolair');
        set_config('enablecustomcss' , 0, 'local_corolair');
        $adminfirstname = $USER->firstname;
        $adminlastname = $USER->lastname;
        $sitename = $SITE->fullname;
        $task = new \local_corolair\task\setup_corolair_connection_task();
        $task->set_custom_data((object) [
            'adminid' => $adminid,
            'adminemail' => $adminemail,
            'moodlerooturl' => $moodlerooturl,
            'adminfirstname' => $adminfirstname,
            'adminlastname' => $adminlastname,
            'sitename' => $sitename,
        ]);
        \core\task\manager::queue_adhoc_task($task);
        $adhoctasklink = (new moodle_url('/admin/tasklogs.php'))->out();
        $trainerpagelink = (new moodle_url('/local/corolair/trainer.php'))->out();
        $links = (object) [
            'adhoc_link' => $adhoctasklink,
            'trainer_page_link' => $trainerpagelink,
        ];
        \core\notification::add(
            get_string('adhocqueued', 'local_corolair', $links),
            \core\output\notification::NOTIFY_SUCCESS
        );
        \core\notification::add(
            get_string('corolairtuto', 'local_corolair'),
            \core\output\notification::NOTIFY_SUCCESS
        );
        \core\notification::add(
            get_string('calendlydemo', 'local_corolair'),
            \core\output\notification::NOTIFY_SUCCESS
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
