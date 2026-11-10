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
 * @param cm_info|null $cm The activity being viewed, or null when the page is not an activity.
 * @return string The rendered embed script, or an empty string when disabled.
 */
function local_corolair_render_embed_script($courseid, $context, ?cm_info $cm = null) {
    global $PAGE, $USER, $CFG;

    // Only course widgets are supported. The front page is a course too -- site
    // announcements live under /mod/ -- but it can never have a tutor. SITEID is defined
    // from the database, so it is a string: compare both sides as integers.
    if (empty($courseid) || (int)$courseid === (int)SITEID) {
        return '';
    }

    // Every shipped placeholder, in every language: comparing against the current user's
    // translation alone let another language's placeholder go out as a bearer token.
    $apikey = \local_corolair\local\api_key::get();
    if ($apikey === null) {
        return '';
    }

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Only a course Raison lists as having a tutor is worth sending this user's identity
    // for. The list is cached for the whole site; see widget_course_list.
    if (!\local_corolair\local\widget_course_list::allows((int)$courseid, $apikey)) {
        return '';
    }

    // The organization's widget policy came with that list. Checked before the session
    // request, so a page the policy keeps the widget off sends nothing about this user.
    $policy = \local_corolair\local\widget_policy::effective(
        \local_corolair\local\widget_course_list::policy($apikey)
    );
    if (!local_corolair_widget_policy_allows($PAGE->url, $cm, $policy)) {
        return '';
    }

    $pageurlstr = $PAGE->url->out(false);
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
                \local_corolair\local\environment::url('services', 'tutor-handling/widget/moodle/session'),
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
    if ($httpstatus === 404) {
        // Listed, but the tutor is gone: stop asking until the next refresh says otherwise.
        \local_corolair\local\widget_course_list::forget((int)$courseid, $apikey);
    }
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

    $output = $PAGE->get_renderer('local_corolair');
    return $output->render_embed_script($responsedata['token']);
}

/**
 * Whether the current page is one that can host the course widget at all.
 *
 * Only the page's kind is judged here -- a course view, a section, or an activity -- which
 * needs nothing from Raison and so runs before anything is fetched. Which of those pages the
 * organization keeps the widget off is local_corolair_widget_policy_allows().
 *
 * @param moodle_url $pageurl The current page URL.
 * @param int $courseid The course the page belongs to, matched against course/view.php.
 * @return bool Whether the page can host the widget.
 */
function local_corolair_course_widget_placement(moodle_url $pageurl, int $courseid): bool {
    $pageurlstr = $pageurl->out();

    $coursemodurlstr = (new moodle_url('/mod/'))->out();
    $courseviewurlstr = (new moodle_url('/course/view.php', ['id' => $courseid]))->out();
    // Moodle 4.4 moved the single-section view off course/view.php?id=X&section=N onto its
    // own page, which every section link in core now points at. It is treated as the course
    // view it replaced. Its id is the section's, not the course's, so
    // only the path is matched: section.php logs the user in to the section's own course,
    // which is the course the caller passed in.
    $coursesectionurlstr = (new moodle_url('/course/section.php'))->out();

    $isonmodpage = strpos($pageurlstr, $coursemodurlstr) !== false;
    $isoncourseview = strpos($pageurlstr, $courseviewurlstr) !== false
        || strpos($pageurlstr, $coursesectionurlstr) !== false;
    return $isonmodpage || $isoncourseview;
}

/**
 * Whether the organization's widget policy lets the widget onto this page.
 *
 * @param moodle_url $pageurl The current page URL.
 * @param cm_info|null $cm The activity being viewed, or null when the page is not an activity.
 * @param array $policy The policy to apply, from \local_corolair\local\widget_policy::effective().
 * @return bool False when the page is an excluded activity type or a hidden Raison exam.
 */
function local_corolair_widget_policy_allows(moodle_url $pageurl, ?cm_info $cm, array $policy): bool {
    $pageurlstr = $pageurl->out();

    foreach ($policy['excludedactivities'] ?? [] as $modname) {
        // For example: /mod/quiz/ or /mod/quiz/view.php?id=....
        if ($modname !== '' && strpos($pageurlstr, '/mod/' . $modname . '/') !== false) {
            return false;
        }
    }

    // A Raison exam is an External tool activity, so excluding "lti" above would take the
    // assistant off every other tool on the site as well -- Zoom, Turnitin, anything. The
    // activity is identified instead, which is possible because the plugin records what it
    // creates and knows where its own tool launches from. Raison cannot make this call: it
    // sees the page URL, not which tool the activity launches.
    if ($cm !== null && $cm->modname === 'lti' && ($policy['hideonraisonexam'] ?? true)) {
        if (\local_corolair\local\placement_registry::is_raison_activity((int)$cm->instance)) {
            return false;
        }
    }

    return true;
}

/**
 * Renders the course widget after the page document has started.
 *
 * Must return HTML from before_footer — never echo from navigation callbacks.
 * Echoing during extend_navigation_course can flush output before <!DOCTYPE html>,
 * which puts Moodle into quirks mode and breaks TinyMCE.
 *
 * On Moodle 4.4 and later this is reached through the hook callback declared in
 * db/hooks.php, and core skips this legacy callback entirely. It stays because the
 * plugin still supports 4.3, where core\hook\output\before_footer_html_generation
 * does not exist and this is the only entry point. Removing it there removes the widget.
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
    if (!local_corolair_course_widget_placement($PAGE->url, $courseid)) {
        return '';
    }

    return local_corolair_render_embed_script($courseid, $PAGE->context, $PAGE->cm);
}

/**
 * Extends the course navigation with a custom node for Raison.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object.
 * @param context $context The context of the course. Unused: the check goes through
 *      launch_access so that this link and the page behind it can never disagree.
 */
function local_corolair_extend_navigation_course($navigation, $course, $context) {
    $courseid = $course->id;

    // Key to identify the node.
    $raisonnodekey = get_string('coursenodetitle', 'local_corolair');
    // Offer the link only when the launch it points at will be admitted. Same course
    // context as before -- what changed is that trainer.php now asks the same question.
    if (\local_corolair\local\launch_access::can_launch((int)$courseid)) {
        // Add the node if it doesn't already exist.
        if (!$navigation->find($raisonnodekey, navigation_node::TYPE_SETTING)) {
            $raisonnode = navigation_node::create(
                get_string('coursenodetitle', 'local_corolair'),
                new moodle_url("/local/corolair/trainer.php?raisonsourcecourse=$courseid"),
                navigation_node::TYPE_SETTING,
                null,
                // The key is what find() matches on: without it the duplicate check above
                // and the removal below could never locate this node.
                $raisonnodekey,
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
 * @param context_course $context The front-page course context. Deliberately unused --
 *      see the system-context check below.
 */
function local_corolair_extend_navigation_frontpage(navigation_node $parentnode, stdClass $course, context_course $context) {
    // Key to identify the node.
    $raisonnodekey = get_string('frontpagenodetitle', 'local_corolair');

    // Checked at system context, not at the context Moodle passes in. Site home is itself a
    // course, so $context is a context_course for SITEID -- which a role assigned on Site
    // home alone satisfies. This link carries no course id, so the launch behind it is the
    // site-wide one and is authorised at system context; gating the link on $context would
    // therefore show it to someone the page then refuses. Passing 0 asks launch_access for
    // exactly the context that launch will be checked against.
    if (\local_corolair\local\launch_access::can_launch(0)) {
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
