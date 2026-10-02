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
 * Get the Nudgis media of a course's UbiCast activities.
 *
 * This file contains the definition of local_corolair_get_ubicast_media function.
 *
 * @package    local_corolair
 * @copyright  2026 Raison
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
    class_alias('\\external_multiple_structure', '\\core_external\\external_multiple_structure');
    class_alias('\\external_single_structure', '\\core_external\\external_single_structure');
    class_alias('\\external_value', '\\core_external\\external_value');
}

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function naming the Nudgis media each UbiCast activity of a course plays.
 *
 * mod_ubicast (UbiCast's own activity) keeps its media id in its `ubicast` table and
 * nothing else: it has no web-service function and no export_contents, so
 * core_course_get_contents lists the activity with no contents at all, and the video it
 * plays cannot be read any other way. Raison imports the video from the organization's
 * own Nudgis, so it needs the id and the server the site plays it from.
 *
 * Lists the activities the caller can see, as core_course_get_contents does. A site
 * without mod_ubicast answers `installed = false` and lists none: the `ubicast` table is
 * never read there.
 *
 * @package    local_corolair
 * @copyright  2026 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ubicast_media extends external_api {
    /**
     * Describes the parameters for the web service function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * The course's UbiCast activities, each with the Nudgis media id it plays.
     *
     * @param int $courseid The course id.
     * @return array{installed:bool, serverurl:string, media:array}
     * @throws \required_capability_exception If the caller cannot view the course.
     */
    public static function execute($courseid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        // The modules table lists the activity types installed on the site. Without
        // mod_ubicast its own table may not exist, so nothing below may run.
        if (!$DB->record_exists('modules', ['name' => 'ubicast'])) {
            return ['installed' => false, 'serverurl' => '', 'media' => []];
        }

        $cms = [];
        foreach (get_fast_modinfo($course)->get_instances_of('ubicast') as $cm) {
            if ($cm->uservisible) {
                $cms[(int)$cm->instance] = $cm;
            }
        }

        $media = [];
        if ($cms) {
            $records = $DB->get_records_list(
                'ubicast',
                'id',
                array_keys($cms),
                '',
                'id, mediaid, timemodified'
            );
            foreach ($cms as $instance => $cm) {
                if (empty($records[$instance])) {
                    continue;
                }
                $record = $records[$instance];
                $media[] = [
                    'cmid' => (int)$cm->id,
                    'instance' => $instance,
                    'name' => $cm->name,
                    'mediaid' => trim((string)$record->mediaid),
                    'timemodified' => (int)$record->timemodified,
                ];
            }
        }

        return [
            'installed' => true,
            // One server per site: mod_ubicast launches every activity against it.
            'serverurl' => rtrim(trim((string)get_config('ubicast', 'ubicast_url')), '/'),
            'media' => $media,
        ];
    }

    /**
     * Describes the structure of the web service response.
     *
     * @return external_single_structure Response definition for the web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'installed' => new external_value(PARAM_BOOL, 'Whether mod_ubicast is installed on the site'),
            'serverurl' => new external_value(PARAM_RAW, 'The Nudgis server mod_ubicast plays from, empty when unset'),
            'media' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'instance' => new external_value(PARAM_INT, 'UbiCast activity instance id'),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                    'mediaid' => new external_value(PARAM_RAW, 'Nudgis media id (oid) the activity plays'),
                    'timemodified' => new external_value(PARAM_INT, 'When the activity was last saved'),
                ])
            ),
        ]);
    }
}
