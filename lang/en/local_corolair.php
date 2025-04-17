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
$string['nocorolairlogin'] = 'No account attached';
$string['createtutorcapability'] = 'Allows users to create and manage AI Tutors within Corolair';
$string['tokenname'] = 'Corolair REST token';
$string['rolename'] = 'Corolair Manager';
$string['roledescription'] = 'Role for managing Corolair AI Tutors';
$string['privacy:metadata:corolair'] = 'Metadata sent to Corolair allows seamless access to your data on the remote system.';
$string['privacy:metadata:corolair:userid'] = 'The user ID is sent to uniquely identify you on Corolair.';
$string['privacy:metadata:corolair:useremail'] = 'Your email address is sent to uniquely identify you on Corolair and enable further communication.';
$string['privacy:metadata:corolair:userfirstname'] = 'Your first name is sent to personalize your experience on Corolair and identify your conversations for your Trainer.';
$string['privacy:metadata:corolair:userlastname'] = 'Your last name is sent to personalize your experience on Corolair and identify your conversations for your Trainer.';
$string['privacy:metadata:corolair:userrolename'] = 'Your role name is sent to manage your permissions on Corolair.';
$string['privacy:metadata:corolair:interaction'] = 'Records of your interactions, such as created tutors and conversations, are sent to enhance your experience.';
$string['localhosterror'] = 'Cannot register Moodle instance with Corolair because the site is running on localhost.';
$string['webservicesenableerror'] = 'Could not enable web services.';
$string['restprotocolenableerror']  = 'Could not enable the REST protocol.';
$string['servicecreationerror'] = 'Could not create the Corolair REST service.';
$string['capabilityassignerror'] = 'Could not assign the capability "{$a}" to the role.';
$string['tokencreationerror'] = 'Could not create the Corolair REST token.';
$string['installtroubleshoot'] = 'If you encounter any issues during installation, please refer to the <a href="https://corolair.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank">troubleshooting guide </a>.';
$string['adhocqueued'] = 'Synchronization with Corolair services should have started in your ad-hoc task <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. If not, trigger an API key generation from <a href="{$a->trainer_page_link}">here</a>.';
$string['corolairtuto'] = 'Learn how to use Corolair by visiting <a href="https://corolair.notion.site/Moodle-Integration-EN-5d5dc1e61f8d4bd89372a6b8009ec4e4?pvs=4" target="_blank">this tutorial</a>.';
$string['customcss'] = 'Custom CSS';
$string['advancedsettings'] = 'Advanced Settings';
$string['advancedsettingsdescription'] = 'These are the advanced settings for the Corolair plugin. If you need assistance, feel free to contact the Corolair team—we\'re happy to help!';
$string['customcss_desc'] = 'Your Moodle theme or settings might affect how the <a href="{$a->trainer_page_link}">Trainer page</a> is displayed, leading to potential layout issues. If you notice display problems, you can enter custom CSS here to override the default styles and improve the page\'s appearance. <strong>Use this option only if necessary and if you are familiar with CSS.</strong> Click <a href="{$a->reset_css_link}">here</a> to reset to the default styles.';
$string['reset_success'] = 'Reset successful';
$string['enablecustomcss'] = 'Enable Custom CSS';
$string['enablecustomcss_desc'] = 'Check this box to allow custom CSS modifications. This is recommended only if you need to fix display issues caused by your Moodle theme or settings.';
