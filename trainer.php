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
global $USER, $CFG, $SITE;

// Set up the Moodle page.
$PAGE->set_url(new moodle_url('/local/corolair/trainer.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('trainerpage', 'local_corolair'));

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
// Check user capability.
if (!has_capability('local/corolair:createtutor', context_system::instance(), $USER->id)) {
    throw new moodle_exception('missingcapability', 'local_corolair');
}

$sitename = $SITE->fullname;
$moodlerooturl = $CFG->wwwroot;
$useremail = $USER->email;
$userfirstname = $USER->firstname;
$userlastname = $USER->lastname;
$enablewebserviceconfigrecord = $DB->get_record('config', ['name' => 'enablewebservices']);
$iswebserviceenabled = false;
if ($enablewebserviceconfigrecord && $enablewebserviceconfigrecord->value == 1) {
    $iswebserviceenabled = true;
}
$webserviceprotocols = $DB->get_record('config', ['name' => 'webserviceprotocols']);
$isrestprotocolenabled = false;
if ($webserviceprotocols && strpos($webserviceprotocols->value, 'rest') !== false) {
    $isrestprotocolenabled = true;
}
$existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
$iscorolairserviceexist = false;
$istokenexist = false;
$tokenvalue = '';
if ($existingservice) {
    $iscorolairserviceexist = true;
    $token = $DB->get_record('external_tokens', ['externalserviceid' => $existingservice->id]);
    if ($token) {
        $istokenexist = true;
        $tokenvalue = $token->token;
    }
}

// Retrieve plugin configuration settings.
$apikey = get_config('local_corolair', 'apikey');
if (empty($apikey) ||
    strpos($apikey, 'No Corolair Api Key') === 0 ||
    strpos($apikey, 'Aucune Clé API Corolair') === 0 ||
    strpos($apikey, 'No hay clave API de Corolair') === 0
    ) {
    if ($existingservice) {
        $token = $DB->get_record('external_tokens', ['externalserviceid' => $existingservice->id]);
        if ($token) {
            // Attempt to register the moodle instance again.
            $curl = new \curl();
            $url = "https://services.corolair.dev/moodle-integration/plugin/organization/register";
            $postdata = json_encode([
                'url' => $moodlerooturl,
                'webserviceToken' => $token->token,
                'email' => $useremail,
                'firstname' => $userfirstname,
                'lastname' => $userlastname,
                'siteName' => $sitename,
            ]);
            $options = [
                "CURLOPT_RETURNTRANSFER" => true,
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postdata),
                ],
            ];
            $response = $curl->post($url, $postdata, $options);
            $errno = $curl->get_errno();
            if ($response !== false && $errno === 0) {
                $jsonresponse = json_decode($response, true);
                if (isset($jsonresponse['apiKey'])) {
                    set_config('apikey', $jsonresponse['apiKey'], 'local_corolair');
                    $isretrysuccess = true;
                }
            }
        }
    }
    if (!$isretrysuccess) {
        $output = $PAGE->get_renderer('local_corolair');
        echo $output->render_installation_troubleshoot(
            $moodlerooturl,
            $sitename,
            $iswebserviceenabled,
            $isrestprotocolenabled,
            $iscorolairserviceexist,
            $istokenexist,
            $useremail,
            $userfirstname,
            $userlastname,
            $tokenvalue
        );
        echo $OUTPUT->footer();
        return;
    } else {
        echo 'API Key is set, try to reload the page';
        return;
    }
}

$redirectoutside = get_config('local_corolair', 'redirectoutside');
$createtutorwithcapability = get_config('local_corolair', 'createtutorwithcapability') === 'true';
// Handle optional course parameter for embedding.
$corolairsourcecourse = optional_param('corolairsourcecourse', 0, PARAM_INT);
$plugin = optional_param('corolairplugin', '', PARAM_TEXT);
// Prepare payload for external authentication request.
$postdata = json_encode([
    'email' => $USER->email,
    'apiKey' => $apikey,
    'firstname' => $USER->firstname,
    'lastname' => $USER->lastname,
    'moodleUserId' => $USER->id,
    'createTutorWithCapability' => $createtutorwithcapability,
    'courseId' => $corolairsourcecourse,
    'plugin' => $plugin,
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
$authurl = $redirectoutside
    ? "https://services.corolair.dev/moodle-integration/auth/v2"
    : "https://services.corolair.dev/moodle-integration/auth";

$response = $curl->post($authurl, $postdata , $options);
$errno = $curl->get_errno();
// Handle the response.
if ($response === false || $errno !== 0) {
    $output = $PAGE->get_renderer('local_corolair');
    echo $output->render_installation_troubleshoot(
        $moodlerooturl,
        $sitename,
        $iswebserviceenabled,
        $isrestprotocolenabled,
        $iscorolairserviceexist,
        $istokenexist,
        $useremail,
        $userfirstname,
        $userlastname,
        $tokenvalue
    );
    echo $OUTPUT->footer();
    return;
}
$jsonresponse = json_decode($response, true);
// Validate the response.
if (!$redirectoutside && !isset($jsonresponse['userId'])) {
    throw new moodle_exception('errortoken', 'local_corolair');
}
if ($redirectoutside && !isset($jsonresponse['url'])) {
    throw new moodle_exception('errortoken', 'local_corolair');
}
if ($redirectoutside) {
    $targeturlresponse = $jsonresponse['url'];
    $targeturl = new moodle_url($targeturlresponse);
    $targeturlout = $targeturl->out(false);

    echo html_writer::div(
        html_writer::tag('p', get_string('redirectingmessage', 'local_corolair')) .
        html_writer::link(
            $targeturl,
            get_string('continue', 'moodle'),
            [
                'target' => '_blank',
                'class' => 'btn btn-primary',
                'id'    => 'corolair-continue',
            ]
        ),
        'corolair-fallback',
        ['style' => 'margin-top:20px; text-align:center;']
    );
    // JS: try auto-open + handle manual click.
    echo html_writer::tag('script', "
        // Try to auto-open Corolair in a new tab
        var win = window.open('$targeturlout', '_blank');
        if (win && !win.closed && typeof win.closed != 'undefined') {
            // Auto-open worked: hide fallback
            var fb = document.getElementById('corolair-fallback');
            if (fb) fb.style.display = 'none';
            // Redirect Moodle tab home
            window.location.href = '" . $CFG->wwwroot . "';
        }

        // If user clicks Continue manually
        var continueBtn = document.getElementById('corolair-continue');
        if (continueBtn) {
            continueBtn.addEventListener('click', function(e) {
                // Redirect Moodle tab home after opening new tab
                setTimeout(function() {
                    window.location.href = '" . $CFG->wwwroot . "';
                }, 500);
            });
        }
    ");
} else {
    // Render inside Moodle.
    $userid = $jsonresponse['userId'];
    $output = $PAGE->get_renderer('local_corolair');
    if ($corolairsourcecourse) {
        echo $output->render_trainer($userid, 'moodle', $corolairsourcecourse, $plugin);
    } else {
        echo $output->render_dashboard($userid);
    }
}
echo $OUTPUT->footer();
