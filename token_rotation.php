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
 * Administrator action for retrying Corolair token rotation.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'local_corolair']);
$retryurl = new moodle_url('/local/corolair/token_rotation.php');
$PAGE->set_url($retryurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('tokenrotationretry', 'local_corolair'));
$PAGE->set_heading(get_string('tokenrotationretry', 'local_corolair'));

$confirm = optional_param('confirm', 0, PARAM_BOOL);
if ($confirm) {
    if (!data_submitted()) {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    $task = new \local_corolair\task\retry_webservice_token_rotation_task();
    \core\task\manager::queue_adhoc_task($task, true);
    redirect(
        $settingsurl,
        get_string('tokenrotationretryqueued', 'local_corolair'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$continueurl = new moodle_url($retryurl, ['confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(
    get_string('tokenrotationretryconfirm', 'local_corolair'),
    new single_button($continueurl, get_string('tokenrotationretry', 'local_corolair'), 'post'),
    new single_button($settingsurl, get_string('cancel'), 'get')
);
echo $OUTPUT->footer();
