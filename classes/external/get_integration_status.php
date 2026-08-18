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
 * External service: report what this integration can currently do.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;
use local_corolair\local\service_account_provisioner;

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
 * Reports the integration's own capability, feature and version state.
 *
 * Raison has to answer two questions before it can act safely, and until this function
 * existed it guessed at both from core_webservice_get_site_info, which can express neither.
 *
 * The first is whether the token can see hidden and restricted course content. Raison used
 * "is the token owner a site administrator" as a stand-in, which worked only because the
 * token used to belong to one. Under the service account it is permanently false, and the
 * consequence is silent: a content sync that cannot trust its own view of a course stops
 * archiving deleted material and reports nothing.
 *
 * The second is whether exam placement is enabled. Raison used to look for the exam
 * functions in the site-info function list, but those stay registered when an administrator
 * switches the feature off -- the refusal happens at call time. So the answer was always
 * yes, and the call always failed.
 *
 * Neither question has anything to do with site data, which is why this returns none.
 */
class get_integration_status extends external_api {
    /**
     * Capabilities that together decide whether the caller sees a course in full.
     *
     * All five are required, and none is redundant. course:view is what lets an unenrolled
     * account reach a course at all. viewhiddencourses, viewhiddensections and
     * viewhiddenactivities each cover one level of the eye icon. ignoreavailabilityrestrictions
     * covers a different mechanism entirely: an activity or section behind a restriction set
     * to "hide entirely" is omitted from core_course_get_contents rather than returned
     * marked unavailable, and no amount of viewhidden* recovers it.
     *
     * Keep in sync with service_account_provisioner::READ_CAPABILITIES, which is what
     * actually grants them; the drift test in tests/plugin_definition_test.php enforces it.
     */
    public const VISIBILITY_CAPABILITIES = [
        'moodle/course:view',
        'moodle/course:viewhiddencourses',
        'moodle/course:viewhiddensections',
        'moodle/course:viewhiddenactivities',
        'moodle/course:ignoreavailabilityrestrictions',
    ];

    /**
     * Describe parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(
                PARAM_INT,
                'Evaluate visibility in this course context; 0 evaluates at system context',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Report the current integration status.
     *
     * Deliberately has no capability check. The service is restricted to a single authorised
     * user and this function is on its fixed allowlist, so the access boundary is already
     * closed; the payload contains no site data, only this plugin's own configuration and
     * the caller's answers about itself; and a capability gate would make the diagnostic
     * fail in exactly the situation it exists to report, namely a service account whose
     * capabilities went missing.
     *
     * It is also deliberately not behind the exam-placement gate. Reporting that exam
     * placement is switched off is half of what it is for.
     *
     * @param int $courseid Course to evaluate visibility in, or 0 for system context.
     * @return array
     */
    public static function execute($courseid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        // Validated at system context even when a course was named. validate_context() runs
        // require_login() for the context it is given, which throws outright on a course the
        // caller cannot reach -- turning a diagnostic into an exception at the one moment it
        // would be most useful. The course below is used to evaluate capabilities, not to
        // authorise the call.
        self::validate_context(context_system::instance());

        $context = context_system::instance();
        $scopedcourseid = 0;
        if ($params['courseid'] > 0) {
            // The role is granted at system context, but a course-level override can take a
            // capability away again, and the caller is asking about one specific course. An
            // unknown or unreachable course falls back to system scope rather than failing;
            // the echoed courseid tells the caller which scope actually answered.
            $coursecontext = context_course::instance($params['courseid'], IGNORE_MISSING);
            if ($coursecontext) {
                $context = $coursecontext;
                $scopedcourseid = (int)$params['courseid'];
            }
        }

        $missing = [];
        foreach (self::VISIBILITY_CAPABILITIES as $capability) {
            if (!has_capability($capability, $context)) {
                $missing[] = $capability;
            }
        }

        $serviceaccountid = service_account_provisioner::locate();

        return [
            'privileged' => $missing === [],
            'missingcapabilities' => $missing,
            'contextlevel' => $scopedcourseid > 0 ? 'course' : 'system',
            'courseid' => $scopedcourseid,
            // Always true, and retained only for compatibility. Exam placement was briefly an
            // opt-in setting; the capabilities it gated are now granted unconditionally and
            // this plugin no longer has a refusal to emit. The field stays because a Raison
            // deployment that still gates on it would read an absent key as false and refuse
            // every placement. Once no such deployment remains it can be dropped, and the
            // matching entry in execute_returns() with it.
            'examplacementenabled' => true,
            'serviceaccount' => $serviceaccountid > 0 && (int)$USER->id === $serviceaccountid,
            'siteadmin' => is_siteadmin($USER->id),
            'healthproblem' => service_account_provisioner::health_problem() ?? '',
            'pluginversion' => (int)get_config('local_corolair', 'version'),
            'pluginrelease' => self::plugin_release(),
        ];
    }

    /**
     * Return the human-readable release string from version.php.
     *
     * Not from configuration: core records the integer version there but never the release,
     * so get_config() returns nothing for it. The integer is what callers gate behaviour on;
     * this is for logs and support, hence the empty-string fallback rather than a failure.
     *
     * @return string
     */
    private static function plugin_release(): string {
        $info = \core_plugin_manager::instance()->get_plugin_info('local_corolair');
        return $info && !empty($info->release) ? (string)$info->release : '';
    }

    /**
     * Describe the function response.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'privileged' => new external_value(
                PARAM_BOOL,
                'Whether the caller sees hidden and access-restricted content in the evaluated scope'
            ),
            'missingcapabilities' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Capability name'),
                'Visibility capabilities the caller does not hold in the evaluated scope'
            ),
            'contextlevel' => new external_value(PARAM_ALPHA, 'Scope the answer was evaluated in: system or course'),
            'courseid' => new external_value(PARAM_INT, 'Course the answer was evaluated in, or 0 for system'),
            'examplacementenabled' => new external_value(PARAM_BOOL, 'Always true; retained for callers that still gate on it'),
            'serviceaccount' => new external_value(PARAM_BOOL, 'Whether the caller is the dedicated service account'),
            'siteadmin' => new external_value(PARAM_BOOL, 'Whether the caller is a site administrator'),
            'healthproblem' => new external_value(PARAM_ALPHANUMEXT, 'Service identity problem code, empty when healthy'),
            'pluginversion' => new external_value(PARAM_INT, 'Installed plugin version'),
            'pluginrelease' => new external_value(PARAM_TEXT, 'Installed plugin release'),
        ]);
    }
}
