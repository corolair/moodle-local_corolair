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
 * Trainer integration page for embedding the Corolair application.
 *
 * This page handles user authentication and passes required data to embed 
 * the Corolair application in an iframe within Moodle.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Ensure global scope access.
global $USER;

// Constants for external URLs.
$auth_url = "https://services.corolair.com/moodle-integration/auth";
$front_url = "https://embed.corolair.com/auth";

try {
    // Set up the Moodle page.
    $PAGE->set_url(new moodle_url('/local/corolair/trainer.php'));
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('trainerpage', 'local_corolair'));

    // Output header.
    echo $OUTPUT->header();

    // Check user capability.
    if (!has_capability('local/corolair:createtutor', context_system::instance(), $USER->id)) {
        throw new moodle_exception('missingcapability', 'local_corolair');
    }

    // Retrieve plugin configuration settings.
    $apikey = get_config('local_corolair', 'apikey');
    if (empty($apikey) || strpos($apikey, 'No Corolair Api Key') === 0) {
        throw new moodle_exception('noapikey', 'local_corolair');
    }

    $create_tutor_with_capability = get_config('local_corolair', 'createtutorwithcapability') === 'true';

    // Prepare payload for external authentication request.
    $postData = json_encode([
        'email' => $USER->email,
        'apiKey' => $apikey,
        'firstname' => $USER->firstname,
        'lastname' => $USER->lastname,
        'moodleUserId' => $USER->id,
        'createTutorWithCapability' => $create_tutor_with_capability
    ]);

    // Send the authentication request.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $auth_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new moodle_exception('curlerror', 'local_corolair', '', null, curl_error($ch));
    }

    // Validate the response.
    $json_response = json_decode($response, true);
    if (!isset($json_response['userId'])) {
        throw new moodle_exception('errortoken', 'local_corolair');
    }

    $userId = $json_response['userId'];
    curl_close($ch);

    // Handle optional course parameter for embedding.
    $corolairsourcecourse = optional_param('corolairsourcecourse', 0, PARAM_INT);
    $provider = $corolairsourcecourse ? 'moodle' : '';
    $courseId = $corolairsourcecourse ?: '';

    // Embed the Corolair application.
    $output = $PAGE->get_renderer('local_corolair');
    echo $output->render_trainer($userId, $provider, $courseId);

} catch (moodle_exception $e) {
    // Handle Moodle-specific errors.
    echo $OUTPUT->notification($e->getMessage(), 'notifyproblem');
    echo $OUTPUT->footer();
    die();
} catch (Exception $e) {
    // Handle generic exceptions.
    echo $OUTPUT->notification(get_string('unexpectederror', 'local_corolair') . $e->getMessage(), 'notifyproblem');
    echo $OUTPUT->footer();
    die();
}

// Output footer.
echo $OUTPUT->footer();
?>
