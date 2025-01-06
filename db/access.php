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
 * Capability definitions for the Corolair plugin.
 *
 * This file contains the capability definitions for the Corolair plugin.
 * Capabilities are used to control access to various features within the plugin.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Capability to create and manage tutors within the Corolair plugin.
    // This capability allows users to create and manage tutors within the Corolair plugin.
    // @captype      write
    // @contextlevel CONTEXT_SYSTEM
    // @description  Allows users to create and manage tutors within the Corolair plugin.
    'local/corolair:createtutor' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'description' => get_string('createtutorcapability', 'local_corolair'),
    ],
];
