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
$string['apikeyrotate'] = 'Rotate API key';
$string['apikeyrotateconfirm'] = 'This generates a new Raison API key and immediately invalidates the current one. Continue?';
$string['apikeyrotatefailed'] = 'The Raison API key rotation failed. Check web services and connectivity, then try again.';
$string['apikeyrotatenotoken'] = 'No Raison web-service token was found. Complete plugin setup before rotating the API key.';
$string['apikeyrotatesuccess'] = 'A new Raison API key was generated. The previous key is now invalid.';
$string['apikeyset'] = 'The API key was set successfully.';
$string['assignmanagerrolecapability'] = 'Assign the Raison Manager role through the scoped integration API.';
$string['calendlydemo'] = 'To help us assist you effectively, please first describe your use case and needs in a discovery call with the Raison Team. Once we understand your requirements, our developers will prioritize resolving the connection issues with your Moodle instance. Schedule your call <strong> <a href="https://discoverycall.raison.is/" target="_blank">here</a> </strong>.';
$string['capabilityassignerror'] = 'Could not assign the capability "{$a}" to the role.';
$string['capabilityfalse'] = 'False';
$string['capabilitytrue'] = 'True';
$string['corolair:assignmanagerrole'] = 'Assign the Raison Manager role through the scoped integration API.';
$string['corolair:createtutor'] = 'Allows the user to create and manage tutors within the Raison plugin.';
$string['corolair:viewroles'] = 'Allows the user to retrieve Moodle role metadata for the Raison integration.';
$string['coursenodetitle'] = 'Raison AI Assistant';
$string['createtutorcapability'] = 'Allows users to create and manage AI Tutors within Raison';
$string['createtutorcapabilitydesc'] = 'The user will not be able to create AI Tutors from courses they cannot manage. If set to False, they can create AI Tutors from courses they are just enrolled in.';
$string['curlerror'] = 'An error occurred while communicating with the Raison API. Could not register your moodle instance, please try again. If error persists, please contact the Raison team';
$string['deregisterfailed'] = 'Raison could not confirm remote deregistration after the available attempts. Moodle access has been revoked and the local integration credentials have been removed, but deletion of data already held by Raison was not technically confirmed. Contact contact@raison.is under your applicable service or data processing agreement and provide this Moodle site URL to complete the deletion process.';
$string['disabletokenrotation'] = 'Disable web-service token rotation';
$string['disabletokenrotationdesc'] = 'By default the Raison web-service token expires after 15 days and is replaced automatically before it expires. Enable this only if that automatic replacement cannot be relied on, for example where Moodle cron does not run regularly or where outbound access to Raison is not guaranteed. Three consequences: the token no longer expires; whenever this setting changes, the previous token stays usable for up to 7 more days so requests already in flight are not broken; and Raison stops periodically re-verifying that this site still grants the functions the integration needs, so misconfiguration is detected later than it otherwise would be. Applying a change requires cron and a successful call to Raison; progress is reported above.';
$string['disclosureaccess'] = 'Access';
$string['disclosureaccessread'] = 'Read';
$string['disclosureaccesswrite'] = 'Write';
$string['disclosureacknowledgebutton'] = 'I acknowledge this integration disclosure';
$string['disclosureacknowledgmentnote'] = 'This acknowledgment confirms that you reviewed the integration scope. It is not presented as legal consent.';
$string['disclosureallowlist'] = 'The token is restricted to the fixed corolair_rest service function allowlist. It cannot invoke Moodle web-service functions outside that list.';
$string['disclosurecapabilitiesheading'] = 'Why Moodle capabilities are involved';
$string['disclosurecapabilitiesintro'] = 'Moodle evaluates the token owner’s capabilities as well as the service allowlist. Some capabilities that sound like writes are used by core read functions as gates for full or hidden fields; they do not change the declared read function into a write. The token owner is an administrator and therefore holds capabilities beyond those listed below.';
$string['disclosurecapability'] = 'Capability';
$string['disclosurecapcompletion'] = 'Allows completion reads reserved for planned progress-aware tutoring and analytics. Completion data is not currently processed.';
$string['disclosurecapcontent'] = 'Allows course and activity content to be read, including full or hidden content where Moodle uses course:update as a visibility gate.';
$string['disclosurecapcoursevisibility'] = 'Allows course and category structure, including hidden items, to be read as tutor source and organization context.';
$string['disclosurecapexam'] = 'Allows the feature-specific exam-placement workflow to create and manage LTI course activities.';
$string['disclosurecapidentity'] = 'Allows identity and email fields required for account matching, invitations, personalization, and access scoping. user:update acts as a read-field gate in the listed core reads.';
$string['disclosurecapparticipants'] = 'Allows participants and content across groups to be read so access can be scoped correctly.';
$string['disclosurecaproleassign'] = 'Allows a trainer invited from Raison to be assigned the Raison Manager role.';
$string['disclosuredataaccess'] = 'Enrolments, roles, groups, and participant relationships are used to determine who can access each tutor.';
$string['disclosuredatacompletion'] = 'Activity and course completion are reserved for a planned progress-aware feature and are not currently processed.';
$string['disclosuredatacourse'] = 'Course categories, structure, sections, resources, lessons, SCORM, and LTI metadata are used as tutor source material and organization context.';
$string['disclosuredataexam'] = 'When the exam-placement feature is invoked, LTI activity and placement information is used to create, update, or remove the requested placement.';
$string['disclosuredataheading'] = 'Personal and learning data use';
$string['disclosuredataidentity'] = 'Administrator and user IDs, names, emails, and roles support account provisioning, invitations, identity matching, personalization, and session access.';
$string['disclosuredatasite'] = 'The Moodle site URL, site name, and service identity register and identify this installation.';
$string['disclosurefiletransfer'] = 'File download and upload are enabled for this service so supported course resources and feature-specific files can be transferred.';
$string['disclosurefunction'] = 'Web-service function';
$string['disclosurefunctionsheading'] = 'Fixed web-service function allowlist';
$string['disclosurefunctionsintro'] = 'The standardized plugin exposes the following 26 functions. Expand each group to review the exact names and read/write classification.';
$string['disclosuregroupcompletion'] = 'Completion reads';
$string['disclosuregroupcompletiondesc'] = 'Read activity and course completion for planned progress-aware tutoring. This data is not currently processed.';
$string['disclosuregroupcontent'] = 'Course and learning-content reads';
$string['disclosuregroupcontentdesc'] = 'Read course catalogs, sections, resources, lessons, SCORM packages, and LTI metadata used to build and organize tutors.';
$string['disclosuregroupenrolment'] = 'Enrollment, participant, and role reads';
$string['disclosuregroupenrolmentdesc'] = 'Read course membership, participants, capabilities, and Moodle roles used to scope tutor access.';
$string['disclosuregroupexamplacement'] = 'Exam-placement and activity writes';
$string['disclosuregroupexamplacementdesc'] = 'Feature-specific writes used only when an administrator invokes the exam-placement workflow.';
$string['disclosuregroupidentity'] = 'Site and identity reads';
$string['disclosuregroupidentitydesc'] = 'Read the Moodle site identity and user profile fields required for registration, account matching, and personalization.';
$string['disclosuregrouproleassignment'] = 'Trainer-role assignment write';
$string['disclosuregrouproleassignmentdesc'] = 'Assign the Raison Manager role when an authorized trainer invitation is initiated from Raison.';
$string['disclosureheading'] = 'Review the Raison integration disclosure';
$string['disclosureintro'] = 'Before any web-service setting is enabled or a token is created, review the exact access boundary, function list, and data purposes below.';
$string['disclosuremissing'] = 'The current integration disclosure must be acknowledged by the administrator starting setup.';
$string['disclosureopensource'] = 'The plugin source is publicly available for security review:';
$string['disclosureplanned'] = 'Planned use';
$string['disclosureposttrialagreements'] = 'If your organization chooses to continue using Raison after the free trial, continued service will require formal agreements between Raison and your organization. These agreements will define each party’s responsibilities and ensure alignment on data protection, privacy, security, and applicable regulatory requirements.';
$string['disclosureprivacycontact'] = 'For questions about external processing, retention, or deletion, contact contact@raison.is.';
$string['disclosurepurpose'] = 'Purpose';
$string['disclosurerole'] = 'The Raison Manager role is separate from the token. It grants local/corolair:createtutor and local/corolair:viewroles to interactive Moodle users.';
$string['disclosuresecurityheading'] = 'Token ownership and security boundary';
$string['disclosurestandardised'] = 'The service function set is standardized and identical across supported installations; it is not expanded dynamically for individual clients.';
$string['disclosuretokenowner'] = 'The web-service token is created for the administrator who starts setup. Moodle evaluates calls using that administrator’s capabilities.';
$string['disclosuretokenrotationdisabled'] = 'This site has disabled token rotation. The token does not expire, and Raison no longer periodically re-verifies that this site still grants the functions listed below.';
$string['disclosuretokentransfer'] = 'The token is transferred to Raison over HTTPS for the integration to call the allowlisted Moodle functions. By default it expires after 15 days and is rotated before expiry; a site administrator can disable rotation, in which case it does not expire.';
$string['disclosureuninstall'] = 'On uninstall, the plugin attempts remote deregistration up to three times before revoking the Moodle web-service access and removing local integration credentials. If remote deletion cannot be confirmed, previously transferred data remains subject to the applicable service or data processing agreement, and the administrator must contact contact@raison.is with the Moodle site URL to complete the deletion process.';
$string['disclosureversion'] = 'Disclosure version';
$string['errortoken'] = 'Error getting token';
$string['eventintegrationdisclosureacknowledged'] = 'Integration disclosure acknowledged';
$string['eventprivacydeletioncompleted'] = 'Raison privacy deletion completed';
$string['eventremoterequestcompleted'] = 'Raison remote request completed';
$string['eventwebservicetokenlifecycle'] = 'Raison web-service token lifecycle updated';
$string['excludedmods'] = 'Excluded activities';
$string['excludedmodsdesc'] = 'Use this list to disable assistants in specific activity types, for example to prevent students from using it during assessments. Provide a comma-separated list of activity module short names (e.g. "quiz, assign"). The short name is the folder shown in the activity\'s URL after \'/mod/\' (e.g. \'/mod/quiz/\' → \'quiz\'). This also works for activity modules provided by external plugins.';
$string['false'] = 'Chatbot';
$string['frontpagenodetitle'] = 'Raison';
$string['installfailed'] = 'The Raison plugin could not complete its installation: {$a}';
$string['installtroubleshoot'] = 'If you encounter any issues during installation, please refer to the <a href="https://troubleshoot-moodle.raison.is" target="_blank">troubleshooting guide </a>.';
$string['invalidredirecturl'] = 'Raison returned an untrusted redirect destination.';
$string['legacycredentialmigrationblockednotice'] = 'The inherited Raison credentials have not been replaced yet, because no site administrator is currently able to own the integration. Assign the "moodle/site:config" capability to an active administrator; Raison retries automatically every hour.';
$string['legacycredentialmigrationdeferred'] = 'The replacement of inherited Raison credentials could not be started during the upgrade. The upgrade completed, and Raison will retry automatically every hour. Check Site administration > Plugins > Local plugins > Raison for the current status.';
$string['legacycredentialmigrationfailed'] = 'The inherited Raison credentials could not be verifiably replaced. Moodle will retry the migration automatically using its ad-hoc task runner.';
$string['localhosterror'] = 'Cannot register Moodle instance with Raison because the site is running on localhost.';
$string['missingcapability'] = 'No Permission to access this page';
$string['noapikey'] = 'No Raison Api Key';
$string['pluginname'] = 'Raison Local Plugin';
$string['privacy:metadata:config_plugins'] = 'The plugin stores, in its local configuration, a record of the administrator who consented to the integration and acknowledged the disclosure.';
$string['privacy:metadata:config_plugins:setupconsentedat'] = 'The time at which the administrator consented to enabling the Raison integration.';
$string['privacy:metadata:config_plugins:setupconsentedby'] = 'The ID of the administrator who consented to enabling the Raison integration.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedat'] = 'The time at which the administrator acknowledged the integration disclosure.';
$string['privacy:metadata:config_plugins:setupdisclosureacknowledgedby'] = 'The ID of the administrator who acknowledged the integration disclosure.';
$string['privacy:metadata:raison'] = 'Metadata sent to Raison allows seamless access to your data on the remote system.';
$string['privacy:metadata:raison:interaction'] = 'Records of your interactions, such as created tutors and conversations, are sent to enhance your experience.';
$string['privacy:metadata:raison:useremail'] = 'Your email address is sent to uniquely identify you on Raison and enable further communication.';
$string['privacy:metadata:raison:userfirstname'] = 'Your first name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userid'] = 'The user ID is sent to uniquely identify you on Raison.';
$string['privacy:metadata:raison:userlastname'] = 'Your last name is sent to personalize your experience on Raison and identify your conversations for your Trainer.';
$string['privacy:metadata:raison:userrolename'] = 'Your role name is sent to manage your permissions on Raison.';
$string['privacy:setupsubcontext'] = 'Raison integration setup';
$string['raisontuto'] = 'Learn how to use Raison by visiting <a href="https://troubleshoot-moodle.raison.is" target="_blank">this tutorial</a>.';
$string['redirectingmessage'] = 'If you are not redirected automatically, please click the button below to continue to Raison.';
$string['reloadpage'] = 'Reload the page';
$string['restprotocolenableerror']  = 'Could not enable the REST protocol.';
$string['retryregistration'] = 'Retry Raison registration';
$string['retryseparator'] = 'or';
$string['roledescription'] = 'Role for managing Raison AI Tutors';
$string['rolename'] = 'Raison Manager';
$string['roleproblem'] = 'We encountered a problem while creating or assigning the new Raison Manager role. You can still configure it manually by allowing the "Raison Local Plugin" capability to any system role. If you encounter any problems, please contact the Raison Team via contact@raison.is.';
$string['servicecreationerror'] = 'Could not create the Raison REST service.';
$string['setupaction'] = 'Open Raison setup';
$string['setupchangeregistration'] = 'A Moodle web-service token will be created and sent to Raison to register this site.';
$string['setupchangerest'] = 'The REST protocol will be added while preserving every protocol that is already enabled.';
$string['setupchangetokenlifetime'] = 'By default the token will expire after 15 days and be replaced automatically before it expires.';
$string['setupchangewebservices'] = 'Moodle web services will be enabled if they are currently disabled.';
$string['setupconfirmbutton'] = 'Enable web services and REST';
$string['setupconfirmquestion'] = 'Do you consent to these site-wide configuration changes and to starting Raison registration?';
$string['setupconsentdescription'] = 'Raison is currently inactive. Activating it makes the following site-wide changes:';
$string['setupconsentheading'] = 'Review and approve Raison activation';
$string['setupconsentmissing'] = 'Raison registration cannot run without recorded administrator consent.';
$string['setupcontinuebutton'] = 'Start Raison registration';
$string['setupcontinuequestion'] = 'Start Raison registration now?';
$string['setupcurrentstatus'] = 'Current status — Moodle web services: {$a->webservices}; REST: {$a->rest}.';
$string['setupdisablerotation'] = 'Do not rotate the web-service token';
$string['setupdisablerotationdesc'] = 'Leave this unticked unless automatic replacement cannot be relied on here, for example where Moodle cron does not run regularly or where outbound access to Raison is not guaranteed. If you tick it, the token created for this site will not expire and will not be replaced, and Raison will stop periodically re-verifying that this site still grants the functions the integration needs. You can change this later in the plugin settings.';
$string['setupdisablerotationforcedoff'] = 'Token rotation is enforced by this server\'s configuration and cannot be disabled here. The token created for this site will expire after 15 days and be replaced automatically.';
$string['setupdisablerotationforcedon'] = 'Token rotation is disabled by this server\'s configuration and cannot be enabled here. The token created for this site will not expire.';
$string['setuppagetitle'] = 'Raison setup';
$string['setupqueued'] = 'Your consent was recorded and Raison registration was queued.';
$string['setupqueuedwithoutconsent'] = 'Raison registration was queued. No site-wide web-service setting was changed.';
$string['setupreadydescription'] = 'Moodle web services and REST are already enabled, so no enablement consent is required and these settings will not be changed.';
$string['setupreadyheading'] = 'Web-service requirements are already enabled';
$string['setupreadynotification'] = 'Raison was installed. Moodle web services and REST are already enabled. A site administrator must <a href="{$a}">review the integration disclosure and start registration</a>.';
$string['setuprequirednotification'] = 'Raison was installed but remains inactive. A site administrator must <a href="{$a}">review the integration disclosure and approve the required web-service changes</a>.';
$string['setupstatus'] = 'Integration status';
$string['setupstatuscomplete'] = 'Connected. Administrator consent is recorded and Raison registration completed.';
$string['setupstatuspending'] = 'Administrator consent is recorded and registration is pending. {$a}';
$string['setupstatusready'] = 'Web services and REST are already enabled. Review the integration disclosure, then start registration without enablement consent. {$a}';
$string['setupstatusrequired'] = 'Setup requires integration-disclosure acknowledgment and administrator consent for the web-service changes. {$a}';
$string['sidepanel'] = 'AI Tutor positioning on screen';
$string['sidepaneldesc'] = 'Choose whether you prefer to display AI Tutors on the right-hand side of courses as a Side Panel (recommended) or in the bottom-right corner like a classic Chatbot.';
$string['taskrotatewebservicetoken'] = 'Rotate the Raison Moodle web-service token';
$string['tokencreationerror'] = 'Could not create the Raison REST token.';
$string['tokenexpiryunknown'] = 'an unknown date';
$string['tokenexpirywarningbody'] = 'Raison could not rotate its Moodle web-service token. The current token expires on {$a->expiry}. Safe error code: {$a->error}. Open the Raison plugin settings and verify cron and connectivity.';
$string['tokenexpirywarningbodylifetime'] = 'Raison could not apply the current token rotation setting, so the Moodle web-service token still uses its previous lifetime. Safe error code: {$a->error}. Open the Raison plugin settings and verify cron and connectivity.';
$string['tokenexpirywarningsubject'] = 'Raison token rotation requires attention';
$string['tokenmissing'] = 'The current Raison web-service token could not be found.';
$string['tokenname'] = 'Raison REST token';
$string['tokenrotationdisabled'] = 'Token rotation is disabled. The Raison web-service token does not expire and is not replaced automatically.';
$string['tokenrotationdisabledpending'] = 'Token rotation is disabled, but the current token still carries its original expiry. Moodle applies the change on the next scheduled run, which requires cron and a successful call to Raison.';
$string['tokenrotationrequestfailed'] = 'The Raison token rotation request failed.';
$string['tokenrotationresponseinvalid'] = 'Raison returned an invalid token rotation acknowledgment.';
$string['tokenrotationretry'] = 'Retry token rotation';
$string['tokenrotationretryconfirm'] = 'Queue an immediate retry using the existing candidate token and rotation ID?';
$string['tokenrotationretryqueued'] = 'The Raison token rotation retry was queued.';
$string['tokenrotationstatusfailed'] = 'Token rotation has not succeeded. The current token expires on {$a->expiry}. Safe error code: {$a->error}. Moodle will retry automatically. <a href="{$a->retryurl}">Retry now</a>.';
$string['tokenrotationstatusfailedlifetime'] = 'The token rotation setting has not been applied yet, so the current token still uses the previous lifetime. Safe error code: {$a->error}. This usually means Moodle could not reach Raison, or that Raison has not yet been updated to accept non-expiring tokens. Moodle will retry automatically. <a href="{$a->retryurl}">Apply now</a>.';
$string['trainerpage'] = 'Raison';
$string['true'] = 'Side Panel';
$string['unexpectederror'] = 'An unexpected error occurred. Please try again. If the error persists, please contact the Raison Team.';
$string['uninstallroleremovalfailed'] = 'The Raison Manager role could not be removed during uninstallation and may still exist in Site administration > Users > Permissions > Define roles. You can delete it manually. Details: {$a}';
$string['viewrolescapability'] = 'Allows users to retrieve Moodle roles through the Raison web service';
$string['webservicesenableerror'] = 'Could not enable web services.';
