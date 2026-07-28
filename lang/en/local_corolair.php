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

$string['adhocqueued'] = 'Synchronization with Raison services should have started in your ad-hoc task <a href="{$a->adhoc_link}">\local_corolair\task\setup_corolair_connection_task</a>. If not, trigger an API key generation from <a href="{$a->trainer_page_link}">here</a>.';
$string['apikey'] = 'Raison Api Key';
$string['apikeydesc'] = 'This key is generated during plugin installation. Please keep it secret. It may be requested by the Raison support team.';
$string['apikeymissing'] = 'API key not found in the response from the Raison API.';
$string['apikeyset'] = 'API Key is set, try to reload the page';
$string['calendlydemo'] = 'To help us assist you effectively, please first describe your use case and needs in a discovery call with the Raison Team. Once we understand your requirements, our developers will prioritize resolving the connection issues with your Moodle instance. Schedule your call <strong> <a href="https://discoverycall.raison.is/" target="_blank">here</a> </strong>.';
$string['capabilityassignerror'] = 'Could not assign the capability "{$a}" to the role.';
$string['capabilityfalse'] = 'False';
$string['capabilitytrue'] = 'True';
$string['corolair:createtutor'] = 'Allows the user to create and manage tutors within the Raison plugin.';
$string['corolair:viewroles'] = 'Allows the user to retrieve Moodle role metadata for the Raison integration.';
$string['coursenodetitle'] = 'Raison AI Assistant';
$string['createtutorcapability'] = 'Allows users to create and manage AI Tutors within Raison';
$string['createtutorcapabilitydesc'] = 'The user will not be able to create AI Tutors from courses they cannot manage. If set to False, they can create AI Tutors from courses they are just enrolled in.';
$string['curlerror'] = 'An error occurred while communicating with the Raison API. Could not register your moodle instance, please try again. If error persists, please contact the Raison team';
$string['errortoken'] = 'Error getting token';
$string['invalidredirecturl'] = 'Corolair returned an untrusted redirect destination.';
$string['eventprivacydeletioncompleted'] = 'Raison privacy deletion completed';
$string['eventremoterequestcompleted'] = 'Raison remote request completed';
$string['eventwebservicetokenlifecycle'] = 'Corolair web-service token lifecycle updated';
$string['excludedmods'] = 'Excluded activities';
$string['excludedmodsdesc'] = 'Use this list to disable assistants in specific activity types, for example to prevent students from using it during assessments. Provide a comma-separated list of activity module short names (e.g. "quiz, assign"). The short name is the folder shown in the activity\'s URL after \'/mod/\' (e.g. \'/mod/quiz/\' → \'quiz\'). This also works for activity modules provided by external plugins.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['installtroubleshoot'] = 'If you encounter any issues during installation, please refer to the <a href="https://troubleshoot-moodle.raison.is" target="_blank">troubleshooting guide </a>.';
$string['localhosterror'] = 'Cannot register Moodle instance with Raison because the site is running on localhost.';
$string['missingcapability'] = 'No Permission to access this page';
$string['noapikey'] = 'No Raison Api Key';
$string['noraisonlogin'] = 'No account attached';
$string['pluginname'] = 'Raison Local Plugin';
$string['privacy:metadata:raison'] = 'Metadata sent to Raison allows seamless access to your data on the remote system.';
$string['privacy:metadata:raison:interaction'] = 'Records of your interactions, such as created tutors and conversations, are sent to enhance your experience.';
$string['privacy:metadata:raison:useremail'] = 'Your email address is sent to uniquely identify you on Raison and enable further communication.';
$string['privacy:metadata:raison:userfirstname'] = 'Your first name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userid'] = 'The user ID is sent to uniquely identify you on Raison.';
$string['privacy:metadata:raison:userlastname'] = 'Your last name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userrolename'] = 'Your role name is sent to manage your permissions on Raison.';
$string['raisonlogin'] = 'Raison account';
$string['raisonlogindesc'] = 'The master Raison account is associated with this email. It may be requested by the Raison support team.';
$string['raisontuto'] = 'Learn how to use Raison by visiting <a href="https://troubleshoot-moodle.raison.is" target="_blank">this tutorial</a>.';
$string['redirectingmessage'] = 'If you are not redirected automatically, please click the button below to continue to Raison.';
$string['restprotocolenableerror']  = 'Could not enable the REST protocol.';
$string['retryregistration'] = 'Retry Raison registration';
$string['roledescription'] = 'Role for managing Raison AI Tutors';
$string['rolename'] = 'Raison Manager';
$string['roleproblem'] = 'We encountered a problem while creating or assigning the new Raison Manager role. You can still configure it manually by allowing the "Raison Local Plugin" capability to any system role. If you encounter any problems, please contact the Raison Team via contact@raison.is.';
$string['servicecreationerror'] = 'Could not create the Raison REST service.';
$string['setupaction'] = 'Open Corolair setup';
$string['setupchangeregistration'] = 'A Moodle web-service token will be created and sent to Corolair to register this site.';
$string['setupchangerest'] = 'The REST protocol will be added while preserving every protocol that is already enabled.';
$string['setupchangewebservices'] = 'Moodle web services will be enabled if they are currently disabled.';
$string['setupconfirmbutton'] = 'Enable web services and REST';
$string['setupconfirmquestion'] = 'Do you consent to these site-wide configuration changes and to starting Corolair registration?';
$string['setupcontinuebutton'] = 'Start Corolair registration';
$string['setupcontinuequestion'] = 'Start Corolair registration now?';
$string['setupconsentdescription'] = 'Corolair is currently inactive. Activating it makes the following site-wide changes:';
$string['setupconsentheading'] = 'Review and approve Corolair activation';
$string['setupconsentmissing'] = 'Corolair registration cannot run without recorded administrator consent.';
$string['setupcurrentstatus'] = 'Current status — Moodle web services: {$a->webservices}; REST: {$a->rest}.';
$string['setuppagetitle'] = 'Corolair setup';
$string['setupqueued'] = 'Your consent was recorded and Corolair registration was queued.';
$string['setupqueuedwithoutconsent'] = 'Corolair registration was queued. No site-wide web-service setting was changed.';
$string['setupreadynotification'] = 'Corolair was installed. Moodle web services and REST are already enabled, so no enablement consent is needed. A site administrator can <a href="{$a}">start Corolair registration</a>.';
$string['setupreadydescription'] = 'Moodle web services and REST are already enabled, so no enablement consent is required and these settings will not be changed.';
$string['setupreadyheading'] = 'Web-service requirements are already enabled';
$string['setuprequirednotification'] = 'Corolair was installed but remains inactive. A site administrator must <a href="{$a}">review and approve the required web-service changes</a>.';
$string['setupstatus'] = 'Integration status';
$string['setupstatuscomplete'] = 'Connected. Administrator consent is recorded and Corolair registration completed.';
$string['setupstatuspending'] = 'Administrator consent is recorded and registration is pending. {$a}';
$string['setupstatusrequired'] = 'Setup requires administrator consent. {$a}';
$string['setupstatusready'] = 'Web services and REST are already enabled. No enablement consent is needed; start registration. {$a}';
$string['sidepanel'] = 'AI Tutor positioning on screen';
$string['sidepaneldesc'] = 'Choose whether you prefer to display AI Tutors on the right-hand side of courses as a Side Panel (recommended) or in the bottom-right corner like a classic Chatbot.';
$string['tokencreationerror'] = 'Could not create the Raison REST token.';
$string['tokenexpirywarningbody'] = 'Corolair could not rotate its Moodle web-service token. The current token expires on {$a->expiry}. Safe error code: {$a->error}. Open the Corolair plugin settings and verify cron and connectivity.';
$string['tokenexpirywarningsubject'] = 'Corolair token rotation requires attention';
$string['tokenmissing'] = 'The current Corolair web-service token could not be found.';
$string['tokenname'] = 'Raison REST token';
$string['tokenrotationrequestfailed'] = 'The Corolair token rotation request failed.';
$string['tokenrotationresponseinvalid'] = 'Corolair returned an invalid token rotation acknowledgment.';
$string['tokenrotationretry'] = 'Retry token rotation';
$string['tokenrotationretryconfirm'] = 'Queue an immediate retry using the existing candidate token and rotation ID?';
$string['tokenrotationretryqueued'] = 'The Corolair token rotation retry was queued.';
$string['tokenrotationstatusfailed'] = 'Token rotation has not succeeded. The current token expires on {$a->expiry}. Safe error code: {$a->error}. Moodle will retry automatically. <a href="{$a->retryurl}">Retry now</a>.';
$string['taskrotatewebservicetoken'] = 'Rotate the Corolair Moodle web-service token';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Side Panel';
$string['unexpectederror'] = 'An unexpected error occurred. Please try again. If the error persists, please contact the Raison Team.';
$string['viewrolescapability'] = 'Allows users to retrieve Moodle roles through the Corolair web service';
$string['webservicesenableerror'] = 'Could not enable web services.';
