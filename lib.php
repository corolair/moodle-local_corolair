<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends the course navigation with a custom node for Corolair.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object.
 * @param context $context The context of the course.
 */
function local_corolair_extend_navigation_course($navigation, $course, $context) {
    global $PAGE, $USER, $CFG;
    $course_id = $course->id;

    // Key to identify the node
    $corolair_node_key = get_string('coursenodetitle', 'local_corolair');

    // Check if the user has the specific capability in this course context
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist
        if (!$navigation->find($corolair_node_key, navigation_node::TYPE_SETTING)) {
            $corolair_node = navigation_node::create(
                get_string('coursenodetitle', 'local_corolair'),
                new moodle_url("/local/corolair/trainer.php?corolairsourcecourse=$course_id"),
                navigation_node::TYPE_SETTING,
                null,
                null,
                null
            );
            $navigation->add_node($corolair_node);
        }
    } else {
        // Remove the node if it exists
        if ($node_to_remove = $navigation->find($corolair_node_key, navigation_node::TYPE_SETTING)) {
            $node_to_remove->remove();
        }
    }

    // URL for the POST request
    $url = "https://services.corolair.dev/moodle-integration/courses/instances/tutor";

    // Get the API key from the configuration
    $apikey = get_config('local_corolair', 'apikey');
    if (!$apikey || strpos($apikey, 'No Corolair Api Key') === 0) {
        return false;
    }

    // Get the current page URL
    $page_url_str = $PAGE->url->out();
    $course_mod_url = new moodle_url('/mod/');
    $course_mod_url_str = $course_mod_url->out();
    $course_view_url = new moodle_url('/course/view.php', array('id' => $course_id));
    $course_view_url_str = $course_view_url->out();

    // Check if the current page is a course view or module page
    $compare_position_page_with_mod = strpos($page_url_str, $course_mod_url_str);
    $compare_position_page_with_course_view = strpos($page_url_str, $course_view_url_str);

    // Access to all
    if (($compare_position_page_with_course_view !== false) || ($compare_position_page_with_mod !== false)) {
        // Decide whether to animate message or not
        $animate = ($compare_position_page_with_course_view !== false) ? 'true' : 'false';

        // Collect user details
        $userid = $USER->id;
        $moodlebaseurl = $CFG->wwwroot;
        $user_email = $USER->email;
        $user_firstname = $USER->firstname;
        $user_lastname = $USER->lastname;
        $roles = get_user_roles($context, $USER->id, true);
        $role = key($roles);
        $role_name = $roles[$role]->shortname;

        // Prepare the data to send in the POST request
        $postData = json_encode([
            'courseId' => $course_id,
            'url' => $moodlebaseurl,
            'moodleId' => $userid,
            'email' => $user_email,
            'firstName' => $user_firstname,
            'lastName' => $user_lastname,
            'role' => $role_name,
            'apiKey' => $apikey
        ]);

        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options for POST request
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);

        // Execute the cURL request and get the response
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch)); // Log the error instead of echoing it
        } else {
            // Decode the JSON response to an associative array
            $responseData = json_decode($response, true);

            // Check for the expected response data
            if (isset($responseData['tutorId']) && isset($responseData['participantId'])) {
                $tutorId = $responseData['tutorId'];
                $participantId = $responseData['participantId'];
            } else {
                $tutorId = 'default-tutor-id'; // Provide a default value or handle accordingly
                $participantId = 'default-participant-id'; // Provide a default value or handle accordingly
            }
        }

        // Close the cURL session
        curl_close($ch);

        // Get the sidepanel setting value
        $sidepanel = get_config('local_corolair', 'sidepanel');
        $sidepanel = ($sidepanel === 'true') ? 'true' : 'false'; // Ensure it's either 'true' or 'false'

        // Render the embed script
        $output = $PAGE->get_renderer('local_corolair');
        echo $output->render_embed_script($tutorId, $participantId, $sidepanel, $animate);
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
    // Key to identify the node
    $corolair_node_key = get_string('frontpagenodetitle', 'local_corolair');

    // Check if the user has the specific capability in this course context
    if (has_capability('local/corolair:createtutor', $context)) {
        // Add the node if it doesn't already exist
        if (!$parentnode->find($corolair_node_key, navigation_node::TYPE_SETTING)) {
            $corolair_node = navigation_node::create(
                get_string('frontpagenodetitle', 'local_corolair'),
                new moodle_url('/local/corolair/trainer.php'),
                navigation_node::TYPE_SETTING,
                null,
                $corolair_node_key,
                null
            );
            $parentnode->add_node($corolair_node);
        }
    } else {
        // Remove the node if it exists
        if ($node_to_remove = $parentnode->find($corolair_node_key, navigation_node::TYPE_SETTING)) {
            $node_to_remove->remove();
        }
    }
}
?>
