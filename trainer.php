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
require_login();

// Ensure global scope access.
global $USER;
// Constants for external URLs.
$authurl = "https://services.corolair.com/moodle-integration/auth";

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
if (empty($apikey) || strpos($apikey, 'No Corolair Api Key') === 0 || strpos($apikey, 'Aucune Clé API Corolair') === 0) {
    throw new moodle_exception('noapikey', 'local_corolair');
}
$createtutorwithcapability = get_config('local_corolair', 'createtutorwithcapability') === 'true';
// Prepare payload for external authentication request.
$postdata = json_encode([
    'email' => $USER->email,
    'apiKey' => $apikey,
    'firstname' => $USER->firstname,
    'lastname' => $USER->lastname,
    'moodleUserId' => $USER->id,
    'createTutorWithCapability' => $createtutorwithcapability,
]);
// Send the authentication request.
$curl = new curl();
$options = [
    "CURLOPT_RETURNTRANSFER" => true,
    'CURLOPT_HTTPHEADER' => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postdata),
    ],
];
$response = $curl->post($authurl, $postdata , $options);
$errno = $curl->get_errno();
// Handle the response.
if ($response === false) {
    throw new moodle_exception('curlerror', 'local_corolair', '', $curl->error);
}
if ($errno !== 0) {
    throw new moodle_exception('curlerror', 'local_corolair', '', null, $curl->error);
}
$jsonresponse = json_decode($response, true);
// Validate the response.
if (!isset($jsonresponse['userId'])) {
    throw new moodle_exception('errortoken', 'local_corolair');
}
$userid = $jsonresponse['userId'];
// Handle optional course parameter for embedding.
$corolairsourcecourse = optional_param('corolairsourcecourse', 0, PARAM_INT);
$provider = $corolairsourcecourse ? 'moodle' : '';
$courseid = $corolairsourcecourse ?: '';
// Embed the Corolair application.
$output = $PAGE->get_renderer('local_corolair');
echo $output->render_trainer($userid, $provider, $courseid);
// Output footer.
echo $OUTPUT->footer();
