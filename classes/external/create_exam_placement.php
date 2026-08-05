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
 * External service for creating an LTI exam placement.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

defined('MOODLE_INTERNAL') || die();

use context_course;

global $CFG;

// Ensure externals are available on Moodle versions that use the global classes.
if (!class_exists('\\core_external\\external_api') && !class_exists('\\external_api')) {
    require_once($CFG->libdir . '/externallib.php');
}

// Alias the pre-4.2 global classes so this implementation works across supported versions.
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
 * Creates a configured LTI activity in a requested course section.
 *
 * The caller supplies the Moodle LTI tool type ID. The returned LTI instance ID
 * is the identifier Corolair uses as provider_exam.content_id.
 */
class create_exam_placement extends external_api {
    /**
     * Describes the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Target course ID'),
            'sectionid' => new external_value(PARAM_INT, 'Target course_sections record ID'),
            'typeid' => new external_value(PARAM_INT, 'Configured Moodle LTI tool type ID'),
            'name' => new external_value(PARAM_TEXT, 'Name of the LTI activity'),
            'position' => new external_value(
                PARAM_INT,
                'Zero-based position in the section; omitted or beyond the section length appends the activity',
                VALUE_DEFAULT,
                -1
            ),
        ]);
    }

    /**
     * Creates an LTI activity in the requested course section.
     *
     * @param int $courseid Target course ID.
     * @param int $sectionid Target course section record ID.
     * @param int $typeid Configured Moodle LTI tool type ID.
     * @param string $name Activity name.
     * @param int $position Zero-based position in the section, or -1 to append.
     * @return array Created placement identifiers.
     */
    public static function execute($courseid, $sectionid, $typeid, $name, $position = -1): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'typeid' => $typeid,
            'name' => $name,
            'position' => $position,
        ]);

        $name = trim($params['name']);
        if ($name === '') {
            throw new \invalid_parameter_exception('The activity name cannot be empty.');
        }
        if ($params['position'] < -1) {
            throw new \invalid_parameter_exception('Position must be zero or greater when provided.');
        }

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', [
            'id' => $params['sectionid'],
            'course' => $course->id,
        ], '*', MUST_EXIST);

        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('mod/lti:addinstance', $context);

        // Validate that the caller supplied an existing Moodle LTI tool type.
        $DB->get_record('lti_types', ['id' => $params['typeid']], 'id', MUST_EXIST);

        require_once($CFG->dirroot . '/course/modlib.php');

        $module = $DB->get_record('modules', ['name' => 'lti'], 'id', MUST_EXIST);
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'lti';
        $moduleinfo->module = (int)$module->id;
        $moduleinfo->course = (int)$course->id;
        // Add_moduleinfo() expects the section number, not course_sections.id.
        $moduleinfo->section = (int)$section->section;
        $moduleinfo->name = $name;
        $moduleinfo->typeid = (int)$params['typeid'];
        $moduleinfo->intro = '';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->showdescription = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->completion = COMPLETION_TRACKING_NONE;
        // Creation reaches lti_grade_item_update(), which reads $lti->grade
        // unconditionally whenever the tool type accepts grades. Leaving it unset raised
        // "Undefined property: stdClass::$grade" on every placement and then fell through
        // to the zero branch anyway. Zero is therefore the value that has always been in
        // effect: a text-only grade item. Changing that is a grading decision, not a fix.
        $moduleinfo->grade = 0;

        // Moodle positions a module by the course-module ID it should precede.
        // A missing or out-of-range position intentionally appends the activity.
        $sequence = empty($section->sequence)
            ? []
            : array_map('intval', explode(',', $section->sequence));
        $moduleinfo->beforemod = $params['position'] >= 0
            ? ($sequence[$params['position']] ?? null)
            : null;

        $createdmodule = add_moduleinfo($moduleinfo, $course, null);

        return [
            'coursemoduleid' => (int)$createdmodule->coursemodule,
            'ltiinstanceid' => (int)$createdmodule->instance,
            'sectionid' => (int)$section->id,
            'name' => (string)$createdmodule->name,
        ];
    }

    /**
     * Describes the function response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'coursemoduleid' => new external_value(PARAM_INT, 'Created course_modules record ID'),
            'ltiinstanceid' => new external_value(PARAM_INT, 'Created lti record ID'),
            'sectionid' => new external_value(PARAM_INT, 'Target course_sections record ID'),
            'name' => new external_value(PARAM_TEXT, 'Created LTI activity name'),
        ]);
    }
}
