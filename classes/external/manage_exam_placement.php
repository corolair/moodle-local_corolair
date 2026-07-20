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
 * External service for managing an existing LTI exam placement.
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
 * Manages an existing LTI exam placement.
 *
 * This initial implementation only updates the activity name.
 */
class manage_exam_placement extends external_api {
    /**
     * Describes the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ltiinstanceid' => new external_value(PARAM_INT, 'Existing lti record ID'),
            'name' => new external_value(PARAM_TEXT, 'New name of the LTI activity'),
        ]);
    }

    /**
     * Updates the name of an existing LTI activity.
     *
     * @param int $ltiinstanceid Existing LTI instance ID.
     * @param string $name New activity name.
     * @return array Updated placement identifiers and name.
     */
    public static function execute($ltiinstanceid, $name): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'ltiinstanceid' => $ltiinstanceid,
            'name' => $name,
        ]);

        $name = trim($params['name']);
        if ($name === '') {
            throw new \invalid_parameter_exception('The activity name cannot be empty.');
        }

        $lti = $DB->get_record('lti', ['id' => $params['ltiinstanceid']], '*', MUST_EXIST);
        $module = $DB->get_record('modules', ['name' => 'lti'], 'id', MUST_EXIST);
        $coursemodule = $DB->get_record('course_modules', [
            'course' => $lti->course,
            'module' => $module->id,
            'instance' => $lti->id,
        ], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $lti->course], '*', MUST_EXIST);

        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        require_once($CFG->dirroot . '/course/modlib.php');

        [, , , $moduleinfo] = get_moduleinfo_data($coursemodule, $course);
        // Some supported Moodle versions do not populate all routing fields in
        // get_moduleinfo_data(). Set them explicitly before update_moduleinfo().
        $moduleinfo->modulename = 'lti';
        $moduleinfo->module = (int)$module->id;
        $moduleinfo->course = (int)$course->id;
        $moduleinfo->coursemodule = (int)$coursemodule->id;
        $moduleinfo->instance = (int)$lti->id;
        $moduleinfo->name = $name;

        // update_moduleinfo() uses these computed fields when it builds the
        // course_module_updated event. Raw course_modules records omit them.
        $coursemodule->modname = 'lti';
        $coursemodule->name = (string)$lti->name;
        [, $updatedmodule] = update_moduleinfo($coursemodule, $moduleinfo, $course, null);

        return [
            'coursemoduleid' => (int)$coursemodule->id,
            'ltiinstanceid' => (int)$lti->id,
            'name' => (string)$updatedmodule->name,
        ];
    }

    /**
     * Describes the function response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'coursemoduleid' => new external_value(PARAM_INT, 'Updated course_modules record ID'),
            'ltiinstanceid' => new external_value(PARAM_INT, 'Updated lti record ID'),
            'name' => new external_value(PARAM_TEXT, 'Updated LTI activity name'),
        ]);
    }
}
