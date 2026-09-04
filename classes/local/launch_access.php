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
 * The context that authorises opening the Raison Creator page.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Decides, in one place, which context a Raison launch is authorised against.
 *
 * Why this exists: the rule used to be written out separately in each of the three places
 * that needed it, and they disagreed. The Raison Manager role is assignable at course level
 * -- see role_provisioner::CONTEXTLEVELS -- and the course navigation link was gated at course
 * context, so a course-level assignment made the link appear. trainer.php then demanded the
 * same capability at system context and refused. The result was a live link that always
 * failed with nopermissions, which reads to an administrator as "the Moodle role does not
 * work" and sends them looking for a second permission to grant somewhere else.
 *
 * The front page had the same defect wearing a different hat. Site home is itself a course, so
 * Moodle hands local_corolair_extend_navigation_frontpage() a context_course for SITEID; that
 * link was therefore gated at front-page course context while the launch behind it -- which
 * carries no course id -- required system.
 *
 * Routing the navigation callbacks and the page through this class is what makes "the link is
 * shown exactly when the page will open" structural, instead of an invariant three files have
 * to be trusted to keep agreeing on. Change the rule here and both sides move together.
 *
 * Note that this only widens access, never narrows it: a course-context check is satisfied by
 * a system-level assignment too, because Moodle walks the whole context path. A site-wide
 * Raison Manager keeps working everywhere they worked before. The one way a system holder is
 * refused for a single course is an explicit Prevent or Prohibit override on that course,
 * which is the administrator's own carve-out and is meant to win.
 */
final class launch_access {
    /** Capability that opens the Raison Creator page and shows the navigation entries. */
    public const CAPABILITY = 'local/corolair:createtutor';

    /**
     * The context a launch from the given course is authorised against.
     *
     * A launch carrying no course is the site-wide entry point, and there is no course to
     * scope it to, so it is authorised at system context. Zero rather than null because
     * every caller reads the course id from optional_param(..., PARAM_INT), which yields 0
     * when the parameter is absent.
     *
     * @param int $courseid Course the launch came from, or 0 for the site-wide entry point.
     * @return \context System context for 0, that course's context otherwise.
     */
    public static function context_for(int $courseid): \context {
        if ($courseid > 0) {
            return \context_course::instance($courseid);
        }
        return \context_system::instance();
    }

    /**
     * Whether the current user may launch Raison from the given course.
     *
     * For navigation callbacks, which must decide whether to offer a link and must never
     * throw. require_launch() is the enforcing counterpart for the page itself.
     *
     * @param int $courseid Course the link would launch from, or 0 for the site-wide entry.
     * @return bool True when the matching launch would be admitted.
     */
    public static function can_launch(int $courseid): bool {
        return has_capability(self::CAPABILITY, self::context_for($courseid));
    }

    /**
     * Refuse the request unless the current user may launch Raison from the given course.
     *
     * @param int $courseid Course the launch came from, or 0 for the site-wide entry point.
     * @return void
     */
    public static function require_launch(int $courseid): void {
        require_capability(self::CAPABILITY, self::context_for($courseid));
    }
}
