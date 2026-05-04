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
 * Local plugin "local_corolair" - Library
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Builds the Raison embed script for the current page.
 *
 * @param int $courseid The course id to send to Raison.
 * @param context $context The context used to resolve the current user's role.
 * @param string $animate Whether the widget should animate on load.
 * @param string $supertutor Whether to mark this embed as a super tutor embed.
 * @return string The rendered embed script, or an empty string when disabled.
 */
function local_corolair_render_embed_script($courseid, $context, $animate, $supertutor = '') {
    global $PAGE, $USER, $CFG;

    $apikey = get_config('local_corolair', 'apikey');
    if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {
        return '';
    }

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $pageurlstr = $PAGE->url->out();
    $roles = get_user_roles($context, $USER->id, true);
    $role = reset($roles);
    $rolename = (!empty($role) && !empty($role->shortname)) ? $role->shortname : '';

    $moodleoptions = [
        'courseId' => $courseid,
        'url' => $CFG->wwwroot,
        'moodleId' => $USER->id,
        'email' => $USER->email,
        'firstName' => $USER->firstname,
        'lastName' => $USER->lastname,
        'role' => $rolename,
        'apiKey' => $apikey,
        'currentMoodlePageUrl' => $pageurlstr,
        'provider' => 'moodle',
    ];
    $moodleoptions = json_encode($moodleoptions);

    $sidepanel = get_config('local_corolair', 'sidepanel');
    $sidepanel = ($sidepanel === 'true') ? 'true' : 'false';

    $output = $PAGE->get_renderer('local_corolair');
    return $output->render_embed_script($sidepanel, $animate, $moodleoptions, $supertutor);
}

/**
 * Adds the Raison embed script to non-course pages.
 *
 * @return string The rendered embed script, or an empty string when disabled.
 */
function local_corolair_before_footer() {
    global $PAGE, $SITE;

    $pageurlstr = $PAGE->url->out();
    $courseviewid = $PAGE->url->get_param('id');
    $iscourseviewurl = strpos($pageurlstr, '/course/view.php') !== false;
    $ismoduleurl = strpos($pageurlstr, '/mod/') !== false;

    if (!empty($PAGE->course) && (int)$PAGE->course->id !== (int)$SITE->id) {
        return '';
    }

    if (!empty($PAGE->context)) {
        $contextlevel = $PAGE->context->contextlevel;
        if ($contextlevel === CONTEXT_MODULE) {
            return '';
        }
        if ($contextlevel === CONTEXT_COURSE && (int)$PAGE->context->instanceid !== (int)$SITE->id) {
            return '';
        }
    }

    if ($iscourseviewurl && ((int)$courseviewid !== (int)$SITE->id)) {
        return '';
    }

    if ($ismoduleurl) {
        return '';
    }

    if (strpos($pageurlstr, '/local/corolair/') !== false) {
        return '';
    }

    $context = empty($PAGE->context) ? context_system::instance() : $PAGE->context;
    $courseid = '';
    if (!empty($PAGE->course) && !empty($PAGE->course->id) && (int)$PAGE->course->id !== (int)$SITE->id) {
        $courseid = $PAGE->course->id;
    }

    return local_corolair_render_embed_script($courseid, $context, 'false', 'true');
}

/**
 * Extends the course navigation with a custom node for Raison.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object.
 * @param context $context The context of the course.
 */
function local_corolair_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;
    $courseid = $course->id;

    // Key to identify the node.
    $raisonnodekey = get_string('coursenodetitle', 'local_corolair');
    // Check if the user has the specific capability in this course context.
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist.
        if (!$navigation->find($raisonnodekey, navigation_node::TYPE_SETTING)) {
            $raisonnode = navigation_node::create(
                get_string('coursenodetitle', 'local_corolair'),
                new moodle_url("/local/corolair/trainer.php?raisonsourcecourse=$courseid"),
                navigation_node::TYPE_SETTING,
                null,
                null,
                null
            );
            $navigation->add_node($raisonnode);
        }
    } else {
        // Remove the node if it exists.
        if ($nodetoremove = $navigation->find($raisonnodekey, navigation_node::TYPE_SETTING)) {
            $nodetoremove->remove();
        }
    }

    // Get the current page URL.
    $pageurlstr = $PAGE->url->out();
    // Get excluded mods from config (comma-separated).
    $excludedmodsraw = get_config('local_corolair', 'excludedmods') ?? '';
    $excludedmods = array_filter(array_map('trim', preg_split('/[,\s]+/', $excludedmodsraw)));

    // If current URL contains /mod/{excluded}/ then skip rendering.
    foreach ($excludedmods as $modname) {
        if ($modname === '') {
            continue;
        }
        // e.g. /mod/quiz/, /mod/quiz/view.php?id=...
        if (strpos($pageurlstr, '/mod/' . $modname . '/') !== false) {
            return; // skip plugin rendering
        }
    }

    $coursemodurl = new moodle_url('/mod/');
    $coursemodurlstr = $coursemodurl->out();
    $courseviewurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    $courseviewurlstr = $courseviewurl->out();

    // Check if the current page is a course view or module page.
    $comparepositionpagewithmod = strpos($pageurlstr, $coursemodurlstr);
    $comparepositionpagewithcourseview = strpos($pageurlstr, $courseviewurlstr);

    // Access to all.
    if (($comparepositionpagewithcourseview !== false) || ($comparepositionpagewithmod !== false)) {
        // Decide whether to animate message or not.
        $animate = ($comparepositionpagewithcourseview !== false) ? 'true' : 'false';
        echo local_corolair_render_embed_script($courseid, $context, $animate);
    }
}

/**
 * Extends the frontpage navigation with a custom node for Raison.
 *
 * @param navigation_node $parentnode The parent navigation node to extend.
 * @param stdClass $course The course object.
 * @param context_course $context The context of the course.
 */
function local_corolair_extend_navigation_frontpage(navigation_node $parentnode, stdClass $course, context_course $context) {
    // Key to identify the node.
    $raisonnodekey = get_string('frontpagenodetitle', 'local_corolair');

    // Check if the user has the specific capability in this course context.
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist.
        if (!$parentnode->find($raisonnodekey, navigation_node::TYPE_SETTING)) {
            $raisonnode = navigation_node::create(
                get_string('frontpagenodetitle', 'local_corolair'),
                new moodle_url('/local/corolair/trainer.php'),
                navigation_node::TYPE_SETTING,
                null,
                $raisonnodekey,
                null
            );
            $parentnode->add_node($raisonnode);
        }
    } else {
        // Remove the node if it exists.
        if ($nodetoremove = $parentnode->find($raisonnodekey, navigation_node::TYPE_SETTING)) {
            $nodetoremove->remove();
        }
    }
}
