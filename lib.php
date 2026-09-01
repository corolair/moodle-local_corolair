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
 * Apply a token-rotation policy change without blocking the settings request.
 *
 * Only queues work. Saving a setting must not depend on Raison being reachable, for the
 * same reason db/upgrade.php performs no network I/O: the token change has to be verified
 * by Raison calling back into Moodle, which cannot be guaranteed mid-request.
 *
 * Losing this task is not a correctness problem. rotate_webservice_token_task reconciles
 * the same desired-versus-actual state hourly, and that is also the only path available to
 * a value forced through $CFG->forced_plugin_settings or written by CLI set_config(),
 * neither of which fires an updated callback at all.
 *
 * @param string|null $name Full name of the setting that changed.
 * @return void
 */
function local_corolair_disabletokenrotation_updated($name = null) {
    \local_corolair\local\webservice_token_manager::record_rotation_policy_change();
    // The task carries no custom data, so queueing it repeatedly collapses to one pending
    // record. Nothing about the desired state is stored in it -- the task re-reads the
    // configuration when it runs -- which is what makes rapid toggling safe.
    \core\task\manager::queue_adhoc_task(
        new \local_corolair\task\retry_webservice_token_rotation_task(),
        true
    );
}

/**
 * Builds the Raison embed script for the current page.
 *
 * @param int $courseid The course id to send to Raison.
 * @param context $context The context used to resolve the current user's role.
 * @param string $animate Whether the widget should animate on load.
 * @return string The rendered embed script, or an empty string when disabled.
 */
function local_corolair_render_embed_script($courseid, $context, $animate) {
    global $PAGE, $USER, $CFG;

    // Only course widgets are supported.
    if (empty($courseid)) {
        return '';
    }

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

    $moodlecontext = [
        'courseId' => (string)$courseid,
        'url' => $CFG->wwwroot,
        'moodleId' => (string)$USER->id,
        'email' => $USER->email,
        'firstName' => $USER->firstname,
        'lastName' => $USER->lastname,
        'role' => $rolename,
        'currentMoodlePageUrl' => $pageurlstr,
    ];
    $postdata = json_encode($moodlecontext);
    if ($postdata === false) {
        return '';
    }
    require_once($CFG->libdir . '/filelib.php');
    $curl = new curl();
    $options = [
        'CURLOPT_CONNECTTIMEOUT' => 15,
        'CURLOPT_TIMEOUT' => 60,
        'CURLOPT_HTTPHEADER' => [
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postdata),
        ],
    ];
    $response = \local_corolair\local\audited_request::execute(
        $curl,
        function () use ($curl, $postdata, $options) {
            return $curl->post(
                'https://services.raison.is/tutor-handling/widget/moodle/session',
                $postdata,
                $options
            );
        },
        \local_corolair\local\audited_request::OP_WIDGET_SESSION,
        $context,
        (int)$USER->id
    );
    if ($response === false || $curl->get_errno() !== 0) {
        return '';
    }
    $info = $curl->get_info();
    $httpstatus = (int)($info['http_code'] ?? 0);
    if ($httpstatus < 200 || $httpstatus >= 300) {
        return '';
    }
    try {
        $responsedata = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return '';
    }
    if (
        !is_array($responsedata) ||
        !is_string($responsedata['token'] ?? null) ||
        $responsedata['token'] === '' ||
        strlen($responsedata['token']) > 8192 ||
        !is_int($responsedata['expiresIn'] ?? null) ||
        $responsedata['expiresIn'] <= 0 ||
        $responsedata['expiresIn'] > 300
    ) {
        return '';
    }

    $sidepanel = get_config('local_corolair', 'sidepanel');
    $sidepanel = ($sidepanel === 'true') ? 'true' : 'false';

    $output = $PAGE->get_renderer('local_corolair');
    return $output->render_embed_script($sidepanel, $animate, $responsedata['token']);
}

/**
 * Whether the current page URL may host the course widget.
 *
 * @param moodle_url $pageurl The current page URL.
 * @param int $courseid The course id for course-view matching.
 * @return array{0: bool, 1: string} Whether to render, and the animate flag.
 */
function local_corolair_course_widget_placement(moodle_url $pageurl, int $courseid): array {
    $pageurlstr = $pageurl->out();

    // Get excluded mods from config (comma-separated).
    $excludedmodsraw = get_config('local_corolair', 'excludedmods') ?? '';
    $excludedmods = array_filter(array_map('trim', preg_split('/[,\s]+/', $excludedmodsraw)));

    // If current URL contains /mod/{excluded}/ then skip rendering.
    foreach ($excludedmods as $modname) {
        if ($modname === '') {
            continue;
        }
        // For example: /mod/quiz/ or /mod/quiz/view.php?id=....
        if (strpos($pageurlstr, '/mod/' . $modname . '/') !== false) {
            return [false, 'false'];
        }
    }

    $coursemodurlstr = (new moodle_url('/mod/'))->out();
    $courseviewurlstr = (new moodle_url('/course/view.php', ['id' => $courseid]))->out();

    $isonmodpage = strpos($pageurlstr, $coursemodurlstr) !== false;
    $isoncourseview = strpos($pageurlstr, $courseviewurlstr) !== false;
    if (!$isonmodpage && !$isoncourseview) {
        return [false, 'false'];
    }

    return [true, $isoncourseview ? 'true' : 'false'];
}

/**
 * Renders the course widget after the page document has started.
 *
 * Must return HTML from before_footer — never echo from navigation callbacks.
 * Echoing during extend_navigation_course can flush output before <!DOCTYPE html>,
 * which puts Moodle into quirks mode and breaks TinyMCE.
 *
 * @return string The rendered embed script, or an empty string when disabled.
 */
function local_corolair_before_footer() {
    global $PAGE;

    $course = $PAGE->course ?? null;
    if (empty($course) || empty($course->id)) {
        return '';
    }

    $courseid = (int)$course->id;
    [$shouldrender, $animate] = local_corolair_course_widget_placement($PAGE->url, $courseid);
    if (!$shouldrender) {
        return '';
    }

    return local_corolair_render_embed_script($courseid, $PAGE->context, $animate);
}

/**
 * Extends the course navigation with a custom node for Raison.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object.
 * @param context $context The context of the course.
 */
function local_corolair_extend_navigation_course($navigation, $course, $context) {
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
