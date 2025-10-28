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
 * Get Course section availability - restrict data.
 *
 * This file contains the definition of local_corolair_get_section_availability function.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

defined('MOODLE_INTERNAL') || die();

use context_course;

global $CFG;

// Ensure externals are available on 4.0.x paths that haven't loaded them yet.
if (!class_exists('\\core_external\\external_api') && !class_exists('\\external_api')) {
    require_once($CFG->libdir . '/externallib.php');
}

// If we're on 4.0.x (globals), alias them into core_external so imports below work uniformly.
if (!class_exists('\\core_external\\external_api') && class_exists('\\external_api')) {
    class_alias('\\external_api', '\\core_external\\external_api');
    class_alias('\\external_function_parameters', '\\core_external\\external_function_parameters');
    class_alias('\\external_single_structure', '\\core_external\\external_single_structure');
    class_alias('\\external_value', '\\core_external\\external_value');
}

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function to retrieve raw section availability (restrict access) JSON.
 *
 * This exposes the value of `course_sections.availability` for a specific course section (topic).
 * It supports lookup by either section ID, or by course ID + section number.
 * Only users who can view the course and have editing or hidden-section capabilities
 * can see the raw availability JSON.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class get_section_availability extends external_api {

    /**
     * Describes the parameters for the web service function.
     *
     * @return external_function_parameters
     */

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sectionid'   => new external_value(PARAM_INT, 'Course section id', VALUE_DEFAULT, 0),
            'courseid'    => new external_value(PARAM_INT, 'Course id (alternative to sectionid)', VALUE_DEFAULT, 0),
            'sectionnum'  => new external_value(PARAM_INT, 'Section number (topic index, with courseid)', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Retrieves the raw availability JSON for a given section (topic).
     *
     * Behavior:
     * - If sectionid is provided, retrieves that section directly.
     * - Otherwise requires both courseid and sectionnum to find the section.
     * - The user must be able to view the course (`moodle/course:view`).
     * - The raw availability JSON is only returned if the user has either
     *   `moodle/course:update` or `moodle/course:viewhiddensections` capability.
     *
     * @param int $sectionid  The section ID (optional).
     * @param int $courseid   The course ID (alternative to sectionid).
     * @param int $sectionnum The section number (topic index, used with courseid).
     * @return array{
     *     sectionid:int,
     *     courseid:int,
     *     sectionnum:int,
     *     availability_raw:?string
     * }
     * @throws \invalid_parameter_exception If required parameters are missing or invalid.
     * @throws \required_capability_exception If the user lacks required capabilities.
     */

    public static function execute($sectionid, $courseid, $sectionnum) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sectionid'  => $sectionid,
            'courseid'   => $courseid,
            'sectionnum' => $sectionnum,
        ]);

        // Locate the section.
        if (!empty($params['sectionid'])) {
            $section = $DB->get_record('course_sections', ['id' => $params['sectionid']], '*', MUST_EXIST);
            $courseid = (int)$section->course;
        } else {
            if (empty($params['courseid']) || $params['sectionnum'] < 0) {
                throw new \invalid_parameter_exception('Provide either sectionid OR (courseid and sectionnum).');
            }
            $courseid = (int)$params['courseid'];
            $section  = $DB->get_record('course_sections', [
                'course'  => $courseid,
                'section' => (int)$params['sectionnum']
            ], '*', MUST_EXIST);
        }

        // Check permissions.
        $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        // Only users with editing rights or visibility can see raw data.
        $canseeraw = (
            has_capability('moodle/course:update', $context) ||
            has_capability('moodle/course:viewhiddensections', $context)
        );

        $availabilityraw = null;
        if (!empty($section->availability) && $canseeraw) {
            $availabilityraw = (string)$section->availability;
        }

        return [
            'sectionid'        => (int)$section->id,
            'courseid'         => (int)$course->id,
            'sectionnum'       => (int)$section->section,
            'availability_raw' => $availabilityraw,
        ];
    }

    /**
     * Describes the structure of the web service response.
     *
     * @return external_single_structure Response definition for the web service.
     */

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sectionid'        => new external_value(PARAM_INT, 'Section id'),
            'courseid'         => new external_value(PARAM_INT, 'Course id'),
            'sectionnum'       => new external_value(PARAM_INT, 'Section number (topic index)'),
            'availability_raw' => new external_value(PARAM_RAW, 'Raw availability JSON (null if not permitted)', VALUE_DEFAULT, null),
        ]);
    }
}
