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
 * Reset the default css of the Trainer page.
 *
 * This page handles the reset of the default styles for the Trainer page
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER;

$existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
if (!$existingservice) {
    redirect(
        new moodle_url('/local/corolair/trainer.php'),
        get_string('createtoken_error', 'local_corolair'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
    return;
}
$serviceid = $existingservice->id;
$token = (object)[
    'token' => md5(uniqid(rand(), true)),
    'userid' => $USER->id,
    'tokentype' => 0,
    'contextid' => \context_system::instance()->id,
    'creatorid' => $USER->id,
    'timecreated' => time(),
    'validuntil' => 0,
    'externalserviceid' => $serviceid,
    'privatetoken' => random_string(64),
    'name' => get_string('tokenname', 'local_corolair'),
];
$insertedtoken = $DB->insert_record('external_tokens', $token);
if (!$insertedtoken) {
    redirect(
        new moodle_url('/local/corolair/trainer.php'),
        get_string('createtoken_error', 'local_corolair'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
    return;
}
set_config('apikey', '', 'local_corolair');
redirect(
    new moodle_url('/local/corolair/trainer.php'),
    get_string('createtoken_success', 'local_corolair'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
