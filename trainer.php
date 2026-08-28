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

// Handle optional course parameter for embedding, and enforce Moodle course access
// before any of its identity is forwarded to the remote provider. This must run
// before header output so require_login can redirect cleanly.
$raisonsourcecourse = optional_param('raisonsourcecourse', 0, PARAM_INT);
if ($raisonsourcecourse) {
    $course = get_course($raisonsourcecourse);
    require_login($course);
    require_capability('local/corolair:createtutor', context_course::instance($course->id));
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
$apikey = \local_corolair\local\api_key::get();
if ($apikey === null) {
    if ($retryregistration && $existingservice) {
        $token = $DB->get_record('external_tokens', ['externalserviceid' => $existingservice->id]);
        if ($token) {
            // Attempt to register the moodle instance again.
            $newapikey = \local_corolair\local\registration_client::register(
                $moodlerooturl,
                $token,
                $useremail,
                $userfirstname,
                $userlastname,
                $sitename,
                context_system::instance(),
                (int)$USER->id
            );
            if ($newapikey !== null) {
                set_config('apikey', $newapikey, 'local_corolair');
                $isretrysuccess = true;
            }
        }
    }
    if (!$isretrysuccess) {
        $output = $PAGE->get_renderer('local_corolair');
        // A site nobody ever set up is not a site with a fault to diagnose. Sending the
        // visitor to the troubleshoot page here answers a question they did not ask -- and
        // it is the likeliest way to arrive here after a command-line installation, where
        // the request to open setup.php is emitted into the install request and then lost.
        if (\local_corolair\local\setup_manager::setup_pending()) {
            // This page is reached with local/corolair:createtutor, which trainers hold and
            // setup.php does not accept: it requires moodle/site:config. Pointing a trainer
            // at it would trade a page that cannot help them for a permission error, so they
            // are told what is missing and who can fix it, and nothing else.
            $cansetup = has_capability('moodle/site:config', context_system::instance());
            echo $OUTPUT->notification(
                get_string($cansetup ? 'setuprequiredtrainer' : 'setuprequiredtrainernoaccess', 'local_corolair'),
                \core\output\notification::NOTIFY_WARNING
            );
            if (!$cansetup) {
                echo $OUTPUT->footer();
                return;
            }
            echo html_writer::div(
                $OUTPUT->single_button(
                    new moodle_url('/local/corolair/setup.php'),
                    get_string('setupaction', 'local_corolair'),
                    'get'
                ),
                'corolair-retry-registration'
            );
            // The troubleshoot page still follows, below the action that is almost certainly
            // the right one: an administrator who knows setup was completed elsewhere, and is
            // here because something else is wrong, should not lose access to it.
            echo html_writer::div(
                html_writer::span(get_string('retryseparator', 'local_corolair')),
                'corolair-retry-separator'
            );
        }
        if ($istokenexist) {
            $retryurl = new moodle_url('/local/corolair/trainer.php');
            $retryform = html_writer::tag(
                'form',
                html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'retryregistration',
                    'value' => 1,
                ]) .
                html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => 'sesskey',
                    'value' => sesskey(),
                ]) .
                html_writer::tag(
                    'button',
                    get_string('retryregistration', 'local_corolair'),
                    ['type' => 'submit', 'class' => 'btn btn-primary btn-lg']
                ),
                [
                    'method' => 'post',
                    'action' => $retryurl->out(false),
                    'class' => 'corolair-retry-form',
                ]
            );
            echo html_writer::div($retryform, 'corolair-retry-registration');
            echo html_writer::div(
                html_writer::span(get_string('retryseparator', 'local_corolair')),
                'corolair-retry-separator'
            );
        }
        echo $output->render_installation_troubleshoot(
            $iswebserviceenabled,
            $isrestprotocolenabled,
            $israisonserviceexist,
            $istokenexist
        );
        echo $OUTPUT->footer();
        return;
    } else {
        echo $OUTPUT->notification(
            get_string('apikeyset', 'local_corolair'),
            \core\output\notification::NOTIFY_SUCCESS
        );
        $reloadparams = [];
        if ($raisonsourcecourse) {
            $reloadparams['raisonsourcecourse'] = $raisonsourcecourse;
        }
        $reloadplugin = optional_param('corolairplugin', '', PARAM_TEXT);
        if ($reloadplugin !== '') {
            $reloadparams['corolairplugin'] = $reloadplugin;
        }
        $reloadurl = new moodle_url('/local/corolair/trainer.php', $reloadparams);
        echo html_writer::div(
            $OUTPUT->single_button(
                $reloadurl,
                get_string('reloadpage', 'local_corolair'),
                'get'
            ),
            'corolair-retry-registration'
        );
        echo $OUTPUT->footer();
        return;
    }
}

$createtutorwithcapability = get_config('local_corolair', 'createtutorwithcapability') === 'true';
// The course parameter ($raisonsourcecourse) was resolved and authorized earlier, before header output.
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
        $iswebserviceenabled,
        $isrestprotocolenabled,
        $israisonserviceexist,
        $istokenexist
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
$targeturl = \local_corolair\local\redirect_url_validator::validate($targeturlresponse);
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
