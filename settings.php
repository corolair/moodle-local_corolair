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
    $rotationstatus = (string)get_config('local_corolair', 'webservicetokenrotationstatus');
    if ($rotationstatus === 'ROTATION_FAILED') {
        $rotationdetails = (object)[
            'expiry' => userdate((int)get_config('local_corolair', 'webservicetokenexpiresat')),
            'error' => s((string)get_config('local_corolair', 'webservicetokenlasterror')),
            'retryurl' => (new moodle_url('/local/corolair/token_rotation.php'))->out(false),
        ];
        $setupstatus .= html_writer::div(
            get_string('tokenrotationstatusfailed', 'local_corolair', $rotationdetails),
            'alert alert-warning mt-3'
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
}
