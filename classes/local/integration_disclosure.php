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
 * Versioned disclosure of the Corolair integration surface.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Supplies the disclosure page and its service-function inventory.
 */
final class integration_disclosure {
    /** Increment whenever the disclosed integration surface materially changes. */
    public const VERSION = '2026-07-31-1';

    /**
     * Return the documented web-service groups.
     *
     * @return array[]
     */
    public static function get_function_groups(): array {
        return [
            self::group('identity', 'read', [
                'core_webservice_get_site_info',
                'core_user_get_users',
                'core_user_get_users_by_field',
            ]),
            self::group('content', 'read', [
                'core_course_get_courses',
                'core_course_get_courses_by_field',
                'core_course_get_contents',
                'core_course_get_categories',
                'local_corolair_get_section_availability',
                'mod_resource_get_resources_by_courses',
                'mod_lesson_get_lessons_by_courses',
                'mod_lesson_get_lesson',
                'mod_lesson_get_pages',
                'mod_lesson_get_page_data',
                'mod_scorm_get_scorms_by_courses',
                'mod_lti_get_ltis_by_courses',
            ]),
            self::group('enrolment', 'read', [
                'core_enrol_get_users_courses',
                'core_enrol_get_enrolled_users',
                'core_enrol_get_enrolled_users_with_capability',
                'local_corolair_get_roles',
            ]),
            self::group('completion', 'read', [
                'core_completion_get_activities_completion_status',
                'core_completion_get_course_completion_status',
            ], true),
            self::group('roleassignment', 'write', [
                'core_role_assign_roles',
            ]),
            self::group('examplacement', 'write', [
                'local_corolair_create_exam_placement',
                'local_corolair_manage_exam_placement',
                'core_course_delete_modules',
                'mod_lti_toggle_showinactivitychooser',
            ]),
        ];
    }

    /**
     * Return every documented function name.
     *
     * @return string[]
     */
    public static function get_function_names(): array {
        $names = [];
        foreach (self::get_function_groups() as $group) {
            foreach ($group['functions'] as $function) {
                $names[] = $function['name'];
            }
        }
        return $names;
    }

    /**
     * Build a localized function group.
     *
     * @param string $key Language-string suffix.
     * @param string $access read or write.
     * @param string[] $functions Exact service functions.
     * @param bool $planned Whether use is planned rather than current.
     * @return array
     */
    private static function group(string $key, string $access, array $functions, bool $planned = false): array {
        $items = [];
        foreach ($functions as $function) {
            $items[] = [
                'name' => $function,
                'access' => get_string('disclosureaccess' . $access, 'local_corolair'),
                'isread' => $access === 'read',
                'iswrite' => $access === 'write',
            ];
        }
        return [
            'title' => get_string('disclosuregroup' . $key, 'local_corolair'),
            'description' => get_string('disclosuregroup' . $key . 'desc', 'local_corolair'),
            'functions' => $items,
            'planned' => $planned,
            'plannedlabel' => $planned ? get_string('disclosureplanned', 'local_corolair') : '',
        ];
    }
}
