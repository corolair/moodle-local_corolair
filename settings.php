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
 * This file defines the administrative settings for the Corolair plugin,
 * allowing site administrators to configure plugin behavior.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Ensure settings are only defined if the user has site configuration capabilities.
if ($hassiteconfig) {
    // Create a new settings page for the Corolair plugin.
    $settings = new admin_settingpage('local_corolair', get_string('pluginname', 'local_corolair'));

    // Add the settings page to the "Local plugins" category.
    $ADMIN->add('localplugins', $settings);

    // Add a dropdown setting for enabling/disabling the side panel.
    $settings->add(new admin_setting_configselect(
        'local_corolair/sidepanel',
        get_string('sidepanel', 'local_corolair'), // Setting title.
        get_string('sidepaneldesc', 'local_corolair'), // Setting description.
        'true', // Default value.
        [
            'true' => get_string('true', 'local_corolair'),
            'false' => get_string('false', 'local_corolair')
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
            'false' => get_string('capabilityfalse', 'local_corolair')
        ]
    ));

    // Add a text input setting for the Corolair API key.
    $settings->add(new admin_setting_configtext(
        'local_corolair/apikey',
        get_string('apikey', 'local_corolair'), // Setting title.
        get_string('apikeydesc', 'local_corolair'), // Setting description.
        'No Corolair Api Key', // Default value.
        PARAM_TEXT // Validation type.
    ));

    // Add a text input setting for the Corolair login identifier.
    $settings->add(new admin_setting_configtext(
        'local_corolair/corolairlogin',
        get_string('corolairlogin', 'local_corolair'), // Setting title.
        get_string('corolairlogindesc', 'local_corolair'), // Setting description.
        'No Corolair Login', // Default value.
        PARAM_TEXT // Validation type.
    ));

    // Inline CSS to style certain inputs as read-only.
    echo '<style>
        #id_s_local_corolair_apikey,
        #id_s_local_corolair_corolairlogin {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        #id_s_local_corolair_apikey:disabled,
        #id_s_local_corolair_corolairlogin:disabled {
            color: #6c757d;
        }
    </style>';
}