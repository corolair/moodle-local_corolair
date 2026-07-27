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
 * Trainer integration page for embedding the Raison application.
 *
 * This page handles user authentication and passes required data to embed
 * the Raison application in an iframe within Moodle.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
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

// Check user capability.
require_capability('local/corolair:createtutor', context_system::instance());

// Manual registration recovery is a POST-only, sesskey-protected action.
$retryregistration = optional_param('retryregistration', 0, PARAM_BOOL);
if ($retryregistration) {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
}

// Output header only after authentication and authorization have succeeded.
echo $OUTPUT->header();

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
$israisonserviceexist = false;
$istokenexist = false;
$isretrysuccess = false;
if ($existingservice) {
    $israisonserviceexist = true;
    $token = $DB->get_record('external_tokens', ['externalserviceid' => $existingservice->id]);
    if ($token) {
        $istokenexist = true;
    }
}

// Retrieve plugin configuration settings.
$apikey = get_config('local_corolair', 'apikey');
if (
    empty($apikey) ||
    strpos($apikey, 'No Corolair Api Key') === 0 ||
    strpos($apikey, 'Aucune Clé API Corolair') === 0 ||
    strpos($apikey, 'No hay clave API de Corolair') === 0 ||
    strpos($apikey, 'No Raison Api Key') === 0 ||
    strpos($apikey, 'Aucune Clé API Raison') === 0 ||
    strpos($apikey, 'No hay clave API de Raison') === 0
) {
    if ($retryregistration && $existingservice) {
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
                "CURLOPT_CONNECTTIMEOUT" => 15,
                "CURLOPT_TIMEOUT" => 60,
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postdata),
                ],
            ];
            $response = \local_corolair\local\audited_request::execute(
                $curl,
                function () use ($curl, $url, $postdata, $options) {
                    return $curl->post($url, $postdata, $options);
                },
                \local_corolair\local\audited_request::OP_ORGANIZATION_REGISTER,
                context_system::instance(),
                (int)$USER->id
            );
            $errno = $curl->get_errno();
            $info = $curl->get_info();
            $httpstatus = (int)($info['http_code'] ?? 0);
            if ($response !== false && $errno === 0 && $httpstatus >= 200 && $httpstatus < 300) {
                try {
                    $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                    if (
                        is_array($jsonresponse) &&
                        isset($jsonresponse['apiKey']) &&
                        is_string($jsonresponse['apiKey']) &&
                        $jsonresponse['apiKey'] !== ''
                    ) {
                        set_config('apikey', $jsonresponse['apiKey'], 'local_corolair');
                        $isretrysuccess = true;
                    }
                } catch (JsonException $exception) {
                    debugging('Invalid JSON received while registering Corolair.', DEBUG_DEVELOPER);
                }
            }
        }
    }
    if (!$isretrysuccess) {
        if ($istokenexist) {
            $retryurl = new moodle_url('/local/corolair/trainer.php', [
                'retryregistration' => 1,
            ]);
            echo $OUTPUT->single_button(
                $retryurl,
                get_string('retryregistration', 'local_corolair'),
                'post'
            );
        }
        $output = $PAGE->get_renderer('local_corolair');
        echo $output->render_installation_troubleshoot(
            $moodlerooturl,
            $sitename,
            $iswebserviceenabled,
            $isrestprotocolenabled,
            $israisonserviceexist,
            $istokenexist,
            $useremail,
            $userfirstname,
            $userlastname
        );
        echo $OUTPUT->footer();
        return;
    } else {
        echo get_string('apikeyset', 'local_corolair');
        return;
    }
}

$createtutorwithcapability = get_config('local_corolair', 'createtutorwithcapability') === 'true';
// Handle optional course parameter for embedding.
$raisonsourcecourse = optional_param('raisonsourcecourse', 0, PARAM_INT);
$plugin = optional_param('corolairplugin', '', PARAM_TEXT);
// Prepare payload for external authentication request.
$postdata = json_encode([
    'email' => $USER->email,
    'firstname' => $USER->firstname,
    'lastname' => $USER->lastname,
    'moodleUserId' => $USER->id,
    'createTutorWithCapability' => $createtutorwithcapability,
    'courseId' => $raisonsourcecourse,
    'plugin' => $plugin,
]);
// Send the authentication request.
$curl = new curl();
$options = [
    "CURLOPT_RETURNTRANSFER" => true,
    "CURLOPT_CONNECTTIMEOUT" => 15,
    "CURLOPT_TIMEOUT" => 60,
    'CURLOPT_HTTPHEADER' => [
        'Authorization: Bearer ' . $apikey,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postdata),
    ],
];
$authurl = "https://services.corolair.dev/moodle-integration/auth/v3";

$response = \local_corolair\local\audited_request::execute(
    $curl,
    function () use ($curl, $authurl, $postdata, $options) {
        return $curl->post($authurl, $postdata, $options);
    },
    \local_corolair\local\audited_request::OP_TRAINER_AUTH,
    context_system::instance(),
    (int)$USER->id
);
$errno = $curl->get_errno();
$info = $curl->get_info();
$httpstatus = (int)($info['http_code'] ?? 0);
// Handle the response.
if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
    $output = $PAGE->get_renderer('local_corolair');
    echo $output->render_installation_troubleshoot(
        $moodlerooturl,
        $sitename,
        $iswebserviceenabled,
        $isrestprotocolenabled,
        $israisonserviceexist,
        $istokenexist,
        $useremail,
        $userfirstname,
        $userlastname
    );
    echo $OUTPUT->footer();
    return;
}
try {
    $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    debugging('Invalid JSON received while authenticating with Corolair.', DEBUG_DEVELOPER);
    throw new moodle_exception('errortoken', 'local_corolair');
}
// Validate the response.

if (
    !is_array($jsonresponse) ||
    !isset($jsonresponse['url']) ||
    !is_string($jsonresponse['url']) ||
    $jsonresponse['url'] === '' ||
    !array_key_exists('isDemoDone', $jsonresponse) ||
    !is_bool($jsonresponse['isDemoDone'])
) {
    throw new moodle_exception('errortoken', 'local_corolair');
}
$isdemodone = $jsonresponse['isDemoDone'];
if (!$isdemodone) {
    $output = $PAGE->get_renderer('local_corolair');
    echo $output->render_demo();
    echo $OUTPUT->footer();
    return;
}

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
            'id'    => 'raison-continue',
        ]
    ),
    'raison-fallback',
    ['style' => 'margin-top:20px; text-align:center;']
);
$continueurl = $raisonsourcecourse ? $CFG->wwwroot . '/course/view.php?id=' . $raisonsourcecourse : $CFG->wwwroot;
$PAGE->requires->js_call_amd('local_corolair/trainer_redirect', 'init', [$targeturlout, $continueurl]);

echo $OUTPUT->footer();
