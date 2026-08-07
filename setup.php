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
 * Administrator consent page for enabling the Corolair integration.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$setupurl = new moodle_url('/local/corolair/setup.php');
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_corolair']);
$PAGE->set_url($setupurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('setuppagetitle', 'local_corolair'));
$PAGE->set_heading(get_string('setuppagetitle', 'local_corolair'));

$step = optional_param('step', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$enablementconsent = optional_param('enablementconsent', 0, PARAM_BOOL);
// An unchecked checkbox posts nothing, so this is 0 rather than absent: the consent form
// always states an explicit intent, and null stays reserved for callers that have none.
$disablerotation = optional_param('disabletokenrotation', 0, PARAM_BOOL);
if ($action !== '') {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();

    if ($action === 'acknowledge') {
        \local_corolair\local\setup_manager::acknowledge_disclosure((int)$USER->id);
        redirect(new moodle_url($setupurl, ['step' => 'consent']));
    }
    if ($action !== 'activate') {
        throw new moodle_exception('invalidrequest', 'error');
    }

    $consentrequired = \local_corolair\local\setup_manager::enablement_consent_required();
    \local_corolair\local\setup_manager::activate(
        (int)$USER->id,
        (bool)$enablementconsent,
        (bool)$disablerotation
    );
    redirect(
        new moodle_url('/'),
        get_string($consentrequired ? 'setupqueued' : 'setupqueuedwithoutconsent', 'local_corolair'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$acknowledged = \local_corolair\local\setup_manager::disclosure_acknowledged((int)$USER->id);
if ($step !== 'consent' || !$acknowledged) {
    $renderer = $PAGE->get_renderer('local_corolair');
    echo $OUTPUT->header();
    echo $renderer->render_setup_disclosure([
        'version' => \local_corolair\local\integration_disclosure::VERSION,
        'groups' => \local_corolair\local\integration_disclosure::get_function_groups(),
        // Selected at render time rather than versioned into the disclosure, so the text is
        // truthful whichever policy is configured when it is shown.
        'rotationdisabled' => \local_corolair\local\webservice_token_manager::rotation_disabled(),
        'actionurl' => $setupurl->out(false),
        'cancelurl' => $settingsurl->out(false),
        'sesskey' => sesskey(),
        'repositoryurl' => 'https://github.com/corolair/moodle-local_corolair',
    ]);
    echo $OUTPUT->footer();
    return;
}

$consentrequired = \local_corolair\local\setup_manager::enablement_consent_required();
$webservicesenabled = \local_corolair\local\setup_manager::webservices_enabled();
$restenabled = \local_corolair\local\setup_manager::rest_enabled();
$status = (object)[
    'webservices' => get_string($webservicesenabled ? 'enabled' : 'disabled'),
    'rest' => get_string($restenabled ? 'enabled' : 'disabled'),
];

echo $OUTPUT->header();
if ($consentrequired) {
    echo $OUTPUT->heading(get_string('setupconsentheading', 'local_corolair'), 2);
    echo html_writer::tag('p', get_string('setupconsentdescription', 'local_corolair'));
    echo html_writer::alist([
        get_string('setupchangewebservices', 'local_corolair'),
        get_string('setupchangerest', 'local_corolair'),
        get_string('setupchangeregistration', 'local_corolair'),
        get_string('setupchangetokenlifetime', 'local_corolair'),
    ]);
} else {
    echo $OUTPUT->heading(get_string('setupreadyheading', 'local_corolair'), 2);
    echo html_writer::tag('p', get_string('setupreadydescription', 'local_corolair'));
    echo html_writer::alist([
        get_string('setupchangeregistration', 'local_corolair'),
        get_string('setupchangetokenlifetime', 'local_corolair'),
    ]);
}
echo $OUTPUT->notification(
    get_string('setupcurrentstatus', 'local_corolair', $status),
    \core\output\notification::NOTIFY_INFO
);

// Hand-rolled rather than $OUTPUT->confirm() with a single_button: core builds that form from
// the continue URL's query string and offers no way to add a field, and this page needs the
// administrator to be able to choose the token-rotation policy before the first token exists.
$rotationforced = \local_corolair\local\webservice_token_manager::rotation_setting_is_forced();
$rotationdisabled = \local_corolair\local\webservice_token_manager::rotation_disabled();

$fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$fields .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'activate']);
$fields .= html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'enablementconsent',
    'value' => $consentrequired ? 1 : 0,
]);

if ($rotationforced) {
    // The value is pinned in config.php, so offering a control here would collect a choice
    // that set_config() cannot apply and get_config() would keep overriding. Report the fixed
    // policy instead, so the page never promises something that will not happen.
    $fields .= $OUTPUT->notification(
        get_string(
            $rotationdisabled ? 'setupdisablerotationforcedon' : 'setupdisablerotationforcedoff',
            'local_corolair'
        ),
        \core\output\notification::NOTIFY_INFO
    );
} else {
    $checkboxattributes = [
        'type' => 'checkbox',
        'name' => 'disabletokenrotation',
        'id' => 'corolair-disabletokenrotation',
        'value' => 1,
        'class' => 'mr-2',
    ];
    if ($rotationdisabled) {
        $checkboxattributes['checked'] = 'checked';
    }
    $fields .= html_writer::div(
        html_writer::empty_tag('input', $checkboxattributes) .
        html_writer::tag(
            'label',
            get_string('setupdisablerotation', 'local_corolair'),
            ['for' => 'corolair-disabletokenrotation', 'class' => 'font-weight-bold']
        ) .
        html_writer::tag(
            'div',
            get_string('setupdisablerotationdesc', 'local_corolair'),
            ['class' => 'text-muted small']
        ),
        'my-3'
    );
}

$fields .= html_writer::tag(
    'p',
    get_string($consentrequired ? 'setupconfirmquestion' : 'setupcontinuequestion', 'local_corolair')
);
$fields .= html_writer::tag(
    'button',
    get_string($consentrequired ? 'setupconfirmbutton' : 'setupcontinuebutton', 'local_corolair'),
    ['type' => 'submit', 'class' => 'btn btn-primary']
);
$fields .= html_writer::link($settingsurl, get_string('cancel'), ['class' => 'btn btn-secondary ml-2']);

echo html_writer::tag('form', $fields, [
    'method' => 'post',
    'action' => $setupurl->out(false),
    'class' => 'local-corolair-consent-form',
]);
echo $OUTPUT->footer();
