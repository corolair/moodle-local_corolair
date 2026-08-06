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
 * External service for deleting an LTI exam placement.
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
 * Deletes an existing LTI exam placement.
 *
 * The caller supplies the LTI instance ID returned by create_exam_placement (stored by
 * Corolair as the placement content_id). The function resolves the single course module
 * backing that LTI instance and deletes only it. It cannot delete non-LTI modules, which
 * bounds the destructive surface exposed to the web-service token.
 */
class delete_exam_placement extends external_api {
    /**
     * Describes the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ltiinstanceid' => new external_value(PARAM_INT, 'Existing lti record ID to delete'),
        ]);
    }

    /**
     * Deletes the LTI activity backing the supplied LTI instance.
     *
     * @param int $ltiinstanceid Existing LTI instance ID.
     * @return array Deleted placement identifiers.
     */
    public static function execute($ltiinstanceid): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'ltiinstanceid' => $ltiinstanceid,
        ]);

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

        // Confirm the target really is an LTI activity before deleting. MUST_EXIST with the
        // 'lti' modname guarantees this function can only ever remove an LTI placement.
        $cminfo = get_coursemodule_from_id('lti', $coursemodule->id, $course->id, false, MUST_EXIST);

        require_once($CFG->dirroot . '/course/lib.php');

        $coursemoduleid = (int)$cminfo->id;
        course_delete_module($coursemoduleid);

        return [
            'ltiinstanceid' => (int)$lti->id,
            'coursemoduleid' => $coursemoduleid,
            'deleted' => true,
        ];
    }

    /**
     * Describes the function response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ltiinstanceid' => new external_value(PARAM_INT, 'Deleted lti record ID'),
            'coursemoduleid' => new external_value(PARAM_INT, 'Deleted course_modules record ID'),
            'deleted' => new external_value(PARAM_BOOL, 'Whether the activity was deleted'),
        ]);
    }
}
