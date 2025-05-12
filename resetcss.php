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
require_capability('moodle/site:config', context_system::instance());

$defaultcssvalue = '
#page-local-corolair-trainer #topofscroll {
    margin: 0 !important;
    padding: 0 !important;
    height: 100%;
    width: 100%;
    max-width: 100%;
}

#page-local-corolair-trainer #corolair-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

#page-local-corolair-trainer #page {
    overflow: hidden !important;
    height: 100vh !important;
    box-sizing: border-box !important;
    width: 100vw !important;
    padding: 0 !important;
}

#page-local-corolair-trainer #page-content {
    padding: 0 !important;
    padding: 0 !important;
    height: 100%;
}

#page-local-corolair-trainer #region-main-box {
    height: 100%;
}

#page-local-corolair-trainer #region-main {
    height: 100%;
}

#page-local-corolair-trainer div[role="main"] {
    height: 100%;
    padding: 0 !important;
}

#page-local-corolair-trainer #page-header {
    display: none;
}';

set_config('customcss', $defaultcssvalue, 'local_corolair');

redirect(
    new moodle_url('/admin/settings.php', ['section' => 'local_corolair']),
    get_string('reset_success', 'local_corolair'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
