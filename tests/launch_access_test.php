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
 * Tests for the context a Raison launch is authorised against.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\launch_access;
use local_corolair\local\role_provisioner;

/**
 * Verifies that a Raison Manager is admitted at exactly the contexts they hold the role at.
 *
 * The bug these cover: the launch page checked the capability at system context however the
 * visitor arrived, so a role assigned inside a course -- which role_provisioner explicitly
 * allows -- produced a navigation link that was always refused. The matrix below is the whole
 * contract, and the two directions matter for different reasons. Admitting a course-level
 * holder in their own course is the fix. Refusing them everywhere else is what keeps the fix
 * from quietly turning a single-course grant into a site-wide one.
 */
final class launch_access_test extends \advanced_testcase {
    /**
     * The plugin-owned role, which is provisioned on install and so always present here.
     *
     * @return int Role id.
     */
    private function corolair_roleid(): int {
        global $DB;

        return (int)$DB->get_field('role', 'id', ['shortname' => role_provisioner::SHORTNAME], MUST_EXIST);
    }

    /**
     * Create a user holding the Raison Manager role at the given context, and sign them in.
     *
     * The assignment is made before setUser() so the access cache is built from the finished
     * state, rather than needing a reload afterwards.
     *
     * @param \context|null $context Context to assign at, or null to assign nothing.
     * @return \stdClass The user.
     */
    private function signed_in_holder(?\context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        if ($context !== null) {
            role_assign($this->corolair_roleid(), $user->id, $context->id);
        }
        $this->setUser($user);

        return $user;
    }

    /**
     * Assert require_launch() refuses the given launch.
     *
     * @param int $courseid Course the launch came from, or 0 for the site-wide entry point.
     * @return void
     */
    private function assert_launch_refused(int $courseid): void {
        $this->assertFalse(launch_access::can_launch($courseid));
        $this->expectException(\required_capability_exception::class);
        launch_access::require_launch($courseid);
    }

    /**
     * A site-wide holder is admitted from a course and from the site-wide entry point.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @covers \local_corolair\local\launch_access::require_launch
     * @return void
     */
    public function test_system_holder_may_launch_from_anywhere(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_holder(\context_system::instance());

        $this->assertTrue(launch_access::can_launch(0));
        $this->assertTrue(launch_access::can_launch((int)$course->id));

        // Neither of these may throw.
        launch_access::require_launch(0);
        launch_access::require_launch((int)$course->id);
    }

    /**
     * A course-level holder is admitted from that course.
     *
     * This is the customer-reported failure: the navigation link appeared and the page
     * refused it.
     *
     * @covers \local_corolair\local\launch_access::require_launch
     * @return void
     */
    public function test_course_holder_may_launch_from_that_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_holder(\context_course::instance($course->id));

        $this->assertTrue(launch_access::can_launch((int)$course->id));
        launch_access::require_launch((int)$course->id);
    }

    /**
     * A course-level holder is refused in a course they were not granted.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @return void
     */
    public function test_course_holder_may_not_launch_from_another_course(): void {
        $this->resetAfterTest();
        $granted = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $this->signed_in_holder(\context_course::instance($granted->id));

        $this->assert_launch_refused((int)$other->id);
    }

    /**
     * A course-level holder is refused at the site-wide entry point.
     *
     * Role assignments propagate down the context path and never up, so a grant inside one
     * course must not open the site-wide page.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @return void
     */
    public function test_course_holder_may_not_launch_site_wide(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_holder(\context_course::instance($course->id));

        $this->assert_launch_refused(0);
    }

    /**
     * A role held only on Site home opens nothing.
     *
     * Site home is a course, so this is an assignment an administrator can plausibly make by
     * mistake. It must not admit the site-wide launch, which is why the front-page navigation
     * link is gated at system context rather than at the context Moodle passes the callback.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @return void
     */
    public function test_front_page_holder_may_not_launch_site_wide(): void {
        global $SITE;

        $this->resetAfterTest();
        $this->signed_in_holder(\context_course::instance($SITE->id));

        $this->assert_launch_refused(0);
    }

    /**
     * A user without the role is refused everywhere.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @return void
     */
    public function test_user_without_the_role_may_not_launch(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_holder(null);

        $this->assertFalse(launch_access::can_launch(0));
        $this->assert_launch_refused((int)$course->id);
    }

    /**
     * An override on one course still overrules a site-wide grant.
     *
     * Checking the course context rather than system is what makes this carve-out reachable
     * at all, and an administrator who sets it means it. role_provisioner repairs the role's
     * capabilities only at system context, so it cannot undo this on the next upgrade.
     *
     * @covers \local_corolair\local\launch_access::can_launch
     * @return void
     */
    public function test_course_prohibit_overrides_a_site_wide_grant(): void {
        $this->resetAfterTest();
        $blocked = $this->getDataGenerator()->create_course();
        $allowed = $this->getDataGenerator()->create_course();
        assign_capability(
            launch_access::CAPABILITY,
            CAP_PROHIBIT,
            $this->corolair_roleid(),
            \context_course::instance($blocked->id)->id,
            true
        );
        $this->signed_in_holder(\context_system::instance());

        $this->assertTrue(launch_access::can_launch((int)$allowed->id));
        $this->assert_launch_refused((int)$blocked->id);
    }

    /**
     * The course id decides the context, and zero means the site-wide entry point.
     *
     * @covers \local_corolair\local\launch_access::context_for
     * @return void
     */
    public function test_context_for_maps_the_course_id(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertInstanceOf(\context_system::class, launch_access::context_for(0));
        $coursecontext = launch_access::context_for((int)$course->id);
        $this->assertInstanceOf(\context_course::class, $coursecontext);
        $this->assertSame(\context_course::instance($course->id)->id, $coursecontext->id);
    }
}
