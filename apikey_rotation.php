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
 * Administrator action for rotating the Raison API key.
 *
 * Regenerates the Corolair API key on the Raison backend (invalidating the
 * previous key) and stores the freshly issued key locally.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $USER, $CFG, $SITE, $DB;

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_corolair']);
$rotateurl = new moodle_url('/local/corolair/apikey_rotation.php');
$PAGE->set_url($rotateurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('apikeyrotate', 'local_corolair'));
$PAGE->set_heading(get_string('apikeyrotate', 'local_corolair'));

$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($confirm) {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();

    $existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
    $token = $existingservice
        ? $DB->get_record('external_tokens', ['externalserviceid' => $existingservice->id])
        : false;
    if (!$token) {
        redirect(
            $settingsurl,
            get_string('apikeyrotatenotoken', 'local_corolair'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $newapikey = \local_corolair\local\registration_client::register(
        $CFG->wwwroot,
        $token,
        $USER->email,
        $USER->firstname,
        $USER->lastname,
        $SITE->fullname,
        $context,
        (int)$USER->id
    );

    if ($newapikey !== null) {
        set_config('apikey', $newapikey, 'local_corolair');
        redirect(
            $settingsurl,
            get_string('apikeyrotatesuccess', 'local_corolair'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect(
        $settingsurl,
        get_string('apikeyrotatefailed', 'local_corolair'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

echo $OUTPUT->header();
$continueurl = new moodle_url($rotateurl, ['confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(
    get_string('apikeyrotateconfirm', 'local_corolair'),
    new single_button($continueurl, get_string('apikeyrotate', 'local_corolair'), 'post'),
    new single_button($settingsurl, get_string('cancel'), 'get')
);
echo $OUTPUT->footer();
