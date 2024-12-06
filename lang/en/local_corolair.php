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
 * Language strings for the Corolair Local Plugin.
 *
 * @package   local_corolair
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Corolair Local Plugin';
$string['sidepanel'] = 'AI Tutor positioning on screen';
$string['sidepaneldesc'] = 'Choose whether you prefer to display AI Tutors on the right-hand side of courses as a Side Panel (recommended) or in the bottom-right corner like a classic Chatbot.';
$string['true'] = 'Side Panel';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Corolair Api Key';
$string['apikeydesc'] = 'This key is generated during plugin installation. Please keep it secret. It may be requested by the Corolair support team.';
$string['corolairlogin'] = 'Corolair account';
$string['corolairlogindesc'] = 'The master Corolair account is associated with this email. It may be requested by the Corolair support team.';
$string['plugininstalledsuccess'] = 'Plugin installed successfully. You can now create and share AI Tutors from the Corolair tab. You can also allow teachers/trainers to create AI Tutors by assigning them the Corolair Manager role from Users > Permissions > Assign System Roles. If you encounter any problems, please contact the Corolair Team.';
$string['curlerror'] = 'An error occurred while communicating with the Corolair API. Could not register your moodle instance, please try again. If error persists, please contact the Corolair team';
$string['apikeymissing'] = 'API key not found in the response from the Corolair API.';
$string['servicecreationfailed'] = 'Failed to create the Corolair REST service.';
$string['corolair:createtutor'] = 'Allows the user to create and manage tutors within the Corolair plugin.';
$string['noapikey'] = 'No Corolair Api Key';
$string['errortoken'] = 'Error getting token';
$string['missingcapability'] = 'No Permission to access this page';
$string['roleproblem'] = 'We encountered a problem while creating or assigning the new Corolair Manager role. You can still configure it manually by allowing the "Corolair Local Plugin" capability to any system role. If you encounter any problems, please contact the Corolair Team via contact@corolair.com.';
$string['coursenodetitle'] = 'Corolair: Create AI Tutor';
$string['frontpagenodetitle'] = 'Corolair';
$string['createtutorcapability'] = 'Exclude courses without editing capability';
$string['createtutorcapabilitydesc'] = 'The user will not be able to create AI Tutors from courses they cannot manage. If set to False, they can create AI Tutors from courses they are just enrolled in.';
$string['capabilitytrue'] = 'True';
$string['capabilityfalse'] = 'False';
$string['unexpectederror'] = 'An unexpected error occurred. Please try again. If the error persists, please contact the Corolair Team.';
$string['trainerpage'] = 'Corolair';
