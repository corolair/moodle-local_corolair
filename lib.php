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
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the course navigation with a custom node for Corolair.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object.
 * @param context $context The context of the course.
 */
function local_corolair_extend_navigation_course($navigation, $course, $context) {
    global $PAGE, $USER, $CFG;
    $courseid = $course->id;

    // Key to identify the node.
    $corolairnodekey = get_string('coursenodetitle', 'local_corolair');

    // Check if the user has the specific capability in this course context.
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist.
        if (!$navigation->find($corolairnodekey, navigation_node::TYPE_SETTING)) {
            $corolairnode = navigation_node::create(
                get_string('coursenodetitle', 'local_corolair'),
                new moodle_url("/local/corolair/trainer.php?corolairsourcecourse=$courseid"),
                navigation_node::TYPE_SETTING,
                null,
                null,
                null
            );
            $navigation->add_node($corolairnode);
        }
    } else {
        // Remove the node if it exists.
        if ($nodetoremove = $navigation->find($corolairnodekey, navigation_node::TYPE_SETTING)) {
            $nodetoremove->remove();
        }
    }

    // URL for the POST request.
    $url = "https://services.corolair.dev/moodle-integration/courses/instances/tutor";

    // Get the API key from the configuration.
    $apikey = get_config('local_corolair', 'apikey');
    if (!$apikey || strpos($apikey, get_string('noapikey', 'local_corolair')) === 0) {
        return false;
    }

    // Get the current page URL.
    $pageurlstr = $PAGE->url->out();
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

        // Collect user details.
        $userid = $USER->id;
        $moodlebaseurl = $CFG->wwwroot;
        $useremail = $USER->email;
        $userfirstname = $USER->firstname;
        $userlastname = $USER->lastname;
        $roles = get_user_roles($context, $USER->id, true);
        $role = key($roles);
        $rolename = $roles[$role]->shortname;

        // Prepare the data to send in the POST request.
        $postdata = json_encode([
            'courseId' => $courseid,
            'url' => $moodlebaseurl,
            'moodleId' => $userid,
            'email' => $useremail,
            'firstName' => $userfirstname,
            'lastName' => $userlastname,
            'role' => $rolename,
            'apiKey' => $apikey,
        ]);

        // Initialize cURL session.
        $ch = curl_init();

        // Set cURL options for POST request.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postdata),
        ]);

        // Execute the cURL request and get the response.
        $response = curl_exec($ch);

        // Check for cURL errors.
        if (curl_errno($ch)) {
            // Log the error instead of echoing it.
            debugging(get_string('curlerror', 'local_corolair') . curl_error($ch) , DEBUG_DEVELOPER);
        } else {
            // Decode the JSON response to an associative array.
            $responsedata = json_decode($response, true);

            // Check for the expected response data.
            if (isset($responsedata['tutorId']) && isset($responsedata['participantId'])) {
                $tutorid = $responsedata['tutorId'];
                $participantid = $responsedata['participantId'];
            } else {
                $tutorid = null;
                $participantid = null;
            }
        }

        // Close the cURL session.
        curl_close($ch);

        // Get the sidepanel setting value.
        $sidepanel = get_config('local_corolair', 'sidepanel');
        $sidepanel = ($sidepanel === 'true') ? 'true' : 'false'; // Ensure it's either 'true' or 'false'.

        // Render the embed script only if tutorId exists.
        if (!empty($tutorid)) {
            $output = $PAGE->get_renderer('local_corolair');
            echo $output->render_embed_script($tutorid, $participantid, $sidepanel, $animate);
        }
    }
}

/**
 * Extends the frontpage navigation with a custom node for Corolair.
 *
 * @param navigation_node $parentnode The parent navigation node to extend.
 * @param stdClass $course The course object.
 * @param context_course $context The context of the course.
 */
function local_corolair_extend_navigation_frontpage(navigation_node $parentnode, stdClass $course, context_course $context) {
    // Key to identify the node.
    $corolairnodekey = get_string('frontpagenodetitle', 'local_corolair');

    // Check if the user has the specific capability in this course context.
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist.
        if (!$parentnode->find($corolairnodekey, navigation_node::TYPE_SETTING)) {
            $corolairnode = navigation_node::create(
                get_string('frontpagenodetitle', 'local_corolair'),
                new moodle_url('/local/corolair/trainer.php'),
                navigation_node::TYPE_SETTING,
                null,
                $corolairnodekey,
                null
            );
            $parentnode->add_node($corolairnode);
        }
    } else {
        // Remove the node if it exists.
        if ($nodetoremove = $parentnode->find($corolairnodekey, navigation_node::TYPE_SETTING)) {
            $nodetoremove->remove();
        }
    }
}
