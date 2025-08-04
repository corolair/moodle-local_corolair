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
 * Services definitions For Corolair Integration with Moodle.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = [
    'Corolair REST Service' => [
        'functions' => [
            'core_user_get_users',
            'core_user_get_users_by_field',
            'core_course_get_courses',
            'core_course_get_contents',
            'mod_resource_get_resources_by_courses',
            'core_enrol_get_users_courses',
            'core_enrol_get_enrolled_users',
            'core_webservice_get_site_info',
            'core_enrol_get_enrolled_users_with_capability',
            'core_course_get_categories',
            'mod_lesson_get_lessons_by_courses',
            'mod_lesson_get_lesson',
            'mod_lesson_get_pages',
            'mod_lesson_get_page_data',
            'core_grading_get_definitions',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'corolair_rest',
        'uploadfiles' => 1,
        'downloadfiles' => 1,
    ],
];
