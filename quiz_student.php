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
 * Quiz integration page for embedding the Corolair application.
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
global $USER, $CFG, $SITE;

// Set up the Moodle page.
$PAGE->set_url(new moodle_url('/local/corolair/quiz_student.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('quizstudentpage', 'local_corolair'));

$iscustomcssenabled = get_config('local_corolair', 'enablecustomcss');
// Inject custom CSS.
$customcss = get_config('local_corolair', 'customcss');
if ($iscustomcssenabled && !empty($customcss)) {
    $customcss = trim($customcss);
    $customcss = str_replace(["\r", "\n"], ' ', $customcss); // Convert new lines to spaces.
    $customcss = preg_replace('/[^{}#.;:%\-\w\s\(\),!\'"\/]/', '', $customcss); // Keep only valid CSS characters.
    $PAGE->requires->js_init_code("
        document.head.insertAdjacentHTML('beforeend', '<style>" . addslashes($customcss) . "</style>');
    ");
}

// Output header.
echo $OUTPUT->header();

// Retrieve plugin configuration settings.
$apikey = get_config('local_corolair', 'apikey');
if (empty($apikey) ||
    strpos($apikey, 'No Corolair Api Key') === 0 ||
    strpos($apikey, 'Aucune Clé API Corolair') === 0 ||
    strpos($apikey, 'No hay clave API de Corolair') === 0
    ) {
    $output = $PAGE->get_renderer('local_corolair');
    echo $output->render_quiz_student();
    echo $OUTPUT->footer();
    return;
}

$corolairquizid = optional_param('corolairquizid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$data = [
    'email' => urlencode($USER->email),
    'apiKey' => urlencode($apikey),
    'firstName' => urlencode($USER->firstname),
    'lastName' => urlencode($USER->lastname),
    'moodleUserId' => urlencode($USER->id),
    'courseId' => urlencode($courseid),
    'corolairQuizId' => urlencode($corolairquizid),
];


$output = $PAGE->get_renderer('local_corolair');
echo $output->render_quiz_student($data);
// Output footer.
echo $OUTPUT->footer();
