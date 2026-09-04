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
 * Settings for the "local_corolair" plugin.
 *
 * This file defines the administrative settings for the Raison plugin,
 * allowing site administrators to configure plugin behavior.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Ensure settings are only defined if the user has site configuration capabilities.
if ($hassiteconfig) {
    // Create a new settings page for the Raison plugin.
    $settings = new admin_settingpage('local_corolair', get_string('pluginname', 'local_corolair'));
    // Add the settings page to the "Local plugins" category.
    $ADMIN->add('localplugins', $settings);
    $setupconsented = (bool)get_config('local_corolair', 'setupconsented');
    $setupcompleted = (bool)get_config('local_corolair', 'setupcompleted');
    $setuplink = html_writer::link(
        new moodle_url('/local/corolair/setup.php'),
        get_string('setupaction', 'local_corolair')
    );
    if (!$setupconsented) {
        $setupstatus = get_string(
            \local_corolair\local\setup_manager::enablement_consent_required()
                ? 'setupstatusrequired'
                : 'setupstatusready',
            'local_corolair',
            $setuplink
        );
    } else if (!$setupcompleted) {
        $setupstatus = get_string('setupstatuspending', 'local_corolair', $setuplink);
    } else {
        $setupstatus = get_string('setupstatuscomplete', 'local_corolair');
    }
    if ((bool)get_config('local_corolair', 'legacycredentialmigrationblocked')) {
        // The upgrade could not queue the credential migration, most often because no
        // site administrator was able to own the integration. The hourly task retries.
        $setupstatus .= html_writer::div(
            get_string('legacycredentialmigrationblockednotice', 'local_corolair'),
            'alert alert-warning mt-3'
        );
    }
    if ((bool)get_config('local_corolair', 'legacycredentialmigrationpending')) {
        // Reported separately from the blocked flag above, which is the narrower case of the
        // migration never having been queued. This one covers a migration that is queued and
        // simply has not confirmed yet -- and until it does, the setup heading above still
        // reads "complete" on an upgraded site, because nothing on the upgrade path resets
        // setupcompleted. Without this the whole window is invisible: maintain() stands down
        // on the same flag, so no token warning is sent for as long as it is set.
        $setupstatus .= html_writer::div(
            get_string('legacycredentialmigrationpendingnotice', 'local_corolair'),
            'alert alert-warning mt-3'
        );
    }
    if ((bool)get_config('local_corolair', 'serviceaccountmigrationpending')) {
        // The ownership handover is asynchronous: the upgrade authorises the current
        // administrator owner, and the scheduled task then rotates onto the service account
        // and revokes the old token after its overlap. Nothing is broken during that window,
        // which is exactly why it needs saying -- an administrator who sees a second live
        // token for this service should know it is expected and temporary.
        $setupstatus .= html_writer::div(
            get_string('serviceaccountmigrationpendingnotice', 'local_corolair'),
            'alert alert-info mt-3'
        );
    }
    $expiresat = (int)get_config('local_corolair', 'webservicetokenexpiresat');
    $rotationdisabled = \local_corolair\local\webservice_token_manager::rotation_disabled();
    // Whether the active token's lifetime already reflects the configured policy. When it
    // does not, the pending work is a lifetime change rather than an expiry rotation, and
    // quoting an expiration date is either meaningless (a date a century out) or actively
    // misleading ("your token expires on ..." when nothing is expiring).
    $lifetimeconverged = \local_corolair\local\webservice_token_manager::is_non_expiring(
        (object)['validuntil' => $expiresat]
    ) === $rotationdisabled;
    $rotationstatus = (string)get_config('local_corolair', 'webservicetokenrotationstatus');
    if ($rotationstatus === 'ROTATION_FAILED') {
        $rotationdetails = (object)[
            'expiry' => userdate($expiresat),
            'error' => s((string)get_config('local_corolair', 'webservicetokenlasterror')),
            'retryurl' => (new moodle_url('/local/corolair/token_rotation.php'))->out(false),
        ];
        $setupstatus .= html_writer::div(
            get_string(
                $lifetimeconverged ? 'tokenrotationstatusfailed' : 'tokenrotationstatusfailedlifetime',
                'local_corolair',
                $rotationdetails
            ),
            'alert alert-warning mt-3'
        );
    } else if ($rotationdisabled) {
        $setupstatus .= html_writer::div(
            get_string(
                $lifetimeconverged ? 'tokenrotationdisabled' : 'tokenrotationdisabledpending',
                'local_corolair'
            ),
            'alert ' . ($lifetimeconverged ? 'alert-info' : 'alert-warning') . ' mt-3'
        );
    }
    $settings->add(new admin_setting_heading(
        'local_corolair/setupstatus',
        get_string('setupstatus', 'local_corolair'),
        $setupstatus
    ));
    // Add a dropdown setting for enabling/disabling the side panel.
    $settings->add(new admin_setting_configselect(
        'local_corolair/sidepanel',
        get_string('sidepanel', 'local_corolair'), // Setting title.
        get_string('sidepaneldesc', 'local_corolair'), // Setting description.
        'true', // Default value.
        [
            'true' => get_string('true', 'local_corolair'),
            'false' => get_string('false', 'local_corolair'),
        ]
    ));
    // Add a dropdown setting for enabling tutor creation capability checks.
    $settings->add(new admin_setting_configselect(
        'local_corolair/createtutorwithcapability',
        get_string('createtutorcapability', 'local_corolair'), // Setting title.
        get_string('createtutorcapabilitydesc', 'local_corolair'), // Setting description.
        'true', // Default value.
        [
            'true' => get_string('capabilitytrue', 'local_corolair'),
            'false' => get_string('capabilityfalse', 'local_corolair'),
        ]
    ));
    // Add a masked input setting for the Raison API key. The rotation action is
    // appended to the field description so it sits right next to the key itself.
    $rotatelink = html_writer::link(
        new moodle_url('/local/corolair/apikey_rotation.php'),
        get_string('apikeyrotate', 'local_corolair')
    );
    $apikeydescription = get_string('apikeydesc', 'local_corolair') .
        html_writer::empty_tag('br') . $rotatelink;
    $settings->add(new admin_setting_configpasswordunmask(
        'local_corolair/apikey',
        get_string('apikey', 'local_corolair'), // Setting title.
        $apikeydescription, // Setting description with rotation link.
        get_string('noapikey', 'local_corolair'), // Default value.
        PARAM_TEXT // Validation type.
    ));
    // Add a text input setting for excluded activity modules.
    // Example value: "quiz, lesson, forum".
    $settings->add(new admin_setting_configtext(
        'local_corolair/excludedmods',
        get_string('excludedmods', 'local_corolair'), // Setting title.
        get_string('excludedmodsdesc', 'local_corolair'), // Setting description.
        '', // Default value: none excluded.
        PARAM_TEXT // Validation type.
    ));
    // Keep the assistant off Raison exam pages. A Raison exam is an External tool activity, so
    // the "Excluded activities" list above cannot express this: excluding "lti" would take the
    // assistant off every other tool on the site too. The activity itself is recognised instead,
    // either because this plugin recorded creating it or because the tool it launches uses the
    // host below. Turning this off restores the assistant on exam pages, which is a decision
    // about assessment integrity rather than a display preference -- hence a separate setting.
    $settings->add(new admin_setting_configcheckbox(
        'local_corolair/hideonraisonexam',
        get_string('hideonraisonexam', 'local_corolair'), // Setting title.
        get_string('hideonraisonexamdesc', 'local_corolair'), // Setting description.
        1 // Default value: the assistant is hidden during Raison exams.
    ));
    // Host the Raison LTI exam tool launches from. Exam placement refuses any tool type that
    // launches somewhere else, which is what stops the integration touching unrelated LTI
    // activities. Visible rather than hidden on purpose: an administrator who needs to change it
    // has a site that is already failing, and a setting they cannot see is one they cannot use to
    // recover. Any value that is not a bare host name falls back to the shipped default.
    $settings->add(new admin_setting_configtext(
        'local_corolair/ltitoolhost',
        get_string('ltitoolhost', 'local_corolair'), // Setting title.
        get_string('ltitoolhostdesc', 'local_corolair'), // Setting description.
        \local_corolair\local\placement_registry::default_tool_host(), // Default value.
        PARAM_HOST // Validation type: a bare host name, so a pasted URL is rejected by the form.
    ));
    require_once($CFG->dirroot . '/local/corolair/lib.php');
    // Opt out of the token lifecycle. A checkbox rather than the true/false dropdowns above:
    // those store the literal string "false", and (bool)'false' is true, which is why
    // lib.php has to compare against 'true' by hand. A checkbox stores '1'/'0', so a plain
    // boolean cast is safe -- and this value is read from several places, including paths
    // where it was set by CLI or forced in config.php rather than through this form.
    $rotationsetting = new admin_setting_configcheckbox(
        'local_corolair/disabletokenrotation',
        get_string('disabletokenrotation', 'local_corolair'), // Setting title.
        get_string('disabletokenrotationdesc', 'local_corolair'), // Setting description.
        0 // Default value: rotation stays enabled.
    );
    // Applying the change needs a network round trip, which must not happen inside the
    // settings request. The callback only queues an ad-hoc task for immediacy; the scheduled
    // task reconciles the same desired state hourly and is the only path available when the
    // value is forced in config.php or set from CLI, neither of which fires this callback.
    $rotationsetting->set_updatedcallback('local_corolair_disabletokenrotation_updated');
    $settings->add($rotationsetting);
}
