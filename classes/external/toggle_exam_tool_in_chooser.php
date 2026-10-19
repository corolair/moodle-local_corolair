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
 * External service for showing the Raison LTI tool in a course's activity chooser.
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
use core_external\external_value;

/**
 * Shows or hides the Raison LTI tool in one course's activity chooser.
 *
 * Replaces mod_lti_toggle_showinactivitychooser on the service. The core function checks
 * mod/lti:addcoursetool in the course it is given and nothing else, and the service account holds
 * that capability at system context, so the token could show or hide any tool type in any course.
 * The course it names does not even bound the change: for a course-level tool type, core updates
 * the type itself, without checking that the type belongs to that course.
 *
 * Two checks are added before core is reached. The type must launch from the Raison host, which
 * placement_registry checks the same way, failing closed, as it does for create_exam_placement.
 * And a course-level type must belong to the course named; a site-level type is fine, because
 * core then records a per-course override rather than changing the type.
 *
 * Takes the same parameters and returns the same value as the core function, so Raison can call
 * whichever of the two a site exposes.
 */
class toggle_exam_tool_in_chooser extends external_api {
    /**
     * Describes the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'tooltypeid' => new external_value(PARAM_INT, 'Configured Moodle LTI tool type ID'),
            'courseid' => new external_value(PARAM_INT, 'Course whose activity chooser changes'),
            'showinactivitychooser' => new external_value(PARAM_BOOL, 'Show in activity chooser'),
        ]);
    }

    /**
     * Shows or hides the Raison tool type in the course's activity chooser.
     *
     * @param int $tooltypeid Configured Moodle LTI tool type ID.
     * @param int $courseid Course whose activity chooser changes.
     * @param bool $showinactivitychooser Whether the tool is offered in the activity chooser.
     * @return bool False when the tool type is configured never to be offered in a chooser.
     */
    public static function execute($tooltypeid, $courseid, $showinactivitychooser): bool {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'tooltypeid' => $tooltypeid,
            'courseid' => $courseid,
            'showinactivitychooser' => $showinactivitychooser,
        ]);

        $context = context_course::instance((int)$params['courseid']);
        self::validate_context($context);
        require_capability('mod/lti:addcoursetool', $context);

        // After the capability check, as in create_exam_placement, so an unauthorised caller fails
        // on authorisation rather than learning which tools are configured. An unknown type id
        // raises dml_missing_record_exception here.
        \local_corolair\local\placement_registry::assert_tool_host_allowed((int)$params['tooltypeid']);

        $type = $DB->get_record('lti_types', ['id' => $params['tooltypeid']], 'id, course', MUST_EXIST);
        if ((int)$type->course !== (int)SITEID && (int)$type->course !== (int)$params['courseid']) {
            throw new \moodle_exception('choosertoolnotincourse', 'local_corolair');
        }

        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        // The same call core's external function makes. It checks mod/lti:addcoursetool again, and
        // refuses a site tool restricted to course categories this course is not in.
        return \mod_lti\local\types_helper::override_type_showinactivitychooser(
            (int)$params['tooltypeid'],
            (int)$params['courseid'],
            $context,
            (bool)$params['showinactivitychooser']
        );
    }

    /**
     * Describes the function response.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'Whether the setting was applied');
    }
}
