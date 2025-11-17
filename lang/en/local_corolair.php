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
 * Language strings for the Raison Local Plugin.
 *
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Raison Local Plugin';
$string['sidepanel'] = 'AI Tutor positioning on screen';
$string['sidepaneldesc'] = 'Choose whether you prefer to display AI Tutors on the right-hand side of courses as a Side Panel (recommended) or in the bottom-right corner like a classic Chatbot.';
$string['true'] = 'Side Panel';
$string['false'] = 'Chatbot';
$string['apikey'] = 'Raison Api Key';
$string['apikeydesc'] = 'This key is generated during plugin installation. Please keep it secret. It may be requested by the Raison support team.';
$string['raisonlogin'] = 'Raison account';
$string['raisonlogindesc'] = 'The master Raison account is associated with this email. It may be requested by the Raison support team.';
$string['curlerror'] = 'An error occurred while communicating with the Raison API. Could not register your moodle instance, please try again. If error persists, please contact the Raison team';
$string['apikeymissing'] = 'API key not found in the response from the Raison API.';
$string['corolair:createtutor'] = 'Allows the user to create and manage tutors within the Raison plugin.';
$string['noapikey'] = 'No Raison Api Key';
$string['errortoken'] = 'Error getting token';
$string['missingcapability'] = 'No Permission to access this page';
$string['roleproblem'] = 'We encountered a problem while creating or assigning the new Raison Manager role. You can still configure it manually by allowing the "Raison Local Plugin" capability to any system role. If you encounter any problems, please contact the Raison Team via contact@raison.is.';
$string['coursenodetitle'] = 'Raison AI Assistant';
$string['frontpagenodetitle'] = 'Raison';
$string['createtutorcapabilitydesc'] = 'The user will not be able to create AI Tutors from courses they cannot manage. If set to False, they can create AI Tutors from courses they are just enrolled in.';
$string['capabilitytrue'] = 'True';
$string['capabilityfalse'] = 'False';
$string['unexpectederror'] = 'An unexpected error occurred. Please try again. If the error persists, please contact the Raison Team.';
$string['trainerpage'] = 'Raison';
$string['noraisonlogin'] = 'No account attached';
$string['createtutorcapability'] = 'Allows users to create and manage AI Tutors within Raison';
$string['tokenname'] = 'Raison REST token';
$string['rolename'] = 'Raison Manager';
$string['roledescription'] = 'Role for managing Raison AI Tutors';
$string['privacy:metadata:raison'] = 'Metadata sent to Raison allows seamless access to your data on the remote system.';
$string['privacy:metadata:raison:userid'] = 'The user ID is sent to uniquely identify you on Raison.';
$string['privacy:metadata:raison:useremail'] = 'Your email address is sent to uniquely identify you on Raison and enable further communication.';
$string['privacy:metadata:raison:userfirstname'] = 'Your first name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userlastname'] = 'Your last name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userrolename'] = 'Your role name is sent to manage your permissions on Raison.';
$string['privacy:metadata:raison:interaction'] = 'Records of your interactions, such as created tutors and conversations, are sent to enhance your experience.';
$string['localhosterror'] = 'Cannot register Moodle instance with Raison because the site is running on localhost.';
$string['webservicesenableerror'] = 'Could not enable web services.';
$string['restprotocolenableerror']  = 'Could not enable the REST protocol.';
$string['servicecreationerror'] = 'Could not create the Raison REST service.';
$string['capabilityassignerror'] = 'Could not assign the capability "{$a}" to the role.';
$string['tokencreationerror'] = 'Could not create the Raison REST token.';
$string['installtroubleshoot'] = 'If you encounter any issues during installation, please refer to the <a href="http://troubleshoot-moodle.raison.is" target="_blank">troubleshooting guide </a>.';
$string['adhocqueued'] = 'Synchronization with Raison services should have started in your ad-hoc task <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. If not, trigger an API key generation from <a href="{$a->trainer_page_link}">here</a>.';
$string['raisontuto'] = 'Learn how to use Raison by visiting <a href="http://troubleshoot-moodle.raison.is" target="_blank">this tutorial</a>.';
$string['redirectingmessage'] = 'If you are not redirected automatically, please click the button below to continue to Raison.';
$string['calendlydemo'] = 'To help us assist you effectively, please first describe your use case and needs in a discovery call with the Raison Team. Once we understand your requirements, our developers will prioritize resolving the connection issues with your Moodle instance. Schedule your call <strong> <a href="http://discoverycall.raison.is/" target="_blank">here</a> </strong>.';

