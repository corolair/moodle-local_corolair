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
 * Tests for the scoped activity-chooser toggle.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;
use local_corolair\local\environment;
use local_corolair\local\service_account_provisioner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Verifies the chooser toggle reaches the Raison tool in the course named, and nothing else.
 *
 * The calls run as the provisioned service account rather than an administrator. It holds
 * mod/lti:addcoursetool at system context, so the capability passes in every course, and the
 * refusals below are what actually bound the token.
 *
 * @covers \local_corolair\external\toggle_exam_tool_in_chooser
 */
final class toggle_exam_tool_in_chooser_test extends \core_external\tests\externallib_testcase {
    /** @var \stdClass Course the toggle is called for. */
    private $course;

    /** @var int Site-level tool type that launches from Raison. */
    private $typeid;

    /**
     * Build a course, a Raison tool type and the service account.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        // Pinned to this tree's environment, for the reason given in exam_placement_test::setUp().
        set_config('ltitoolhost', environment::host('services'), 'local_corolair');

        $this->course = $this->getDataGenerator()->create_course();
        $this->typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Raison exam tool',
            'baseurl' => environment::url('services', 'integration/lti/launch'),
            'state' => LTI_TOOL_STATE_CONFIGURED,
            'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
            'lti_coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);

        $this->setUser(service_account_provisioner::ensure());
    }

    /**
     * Call the function and validate the result against the declared return structure.
     *
     * @param int $typeid Tool type ID.
     * @param int $courseid Course ID.
     * @param bool $show Whether to show the tool.
     * @return bool Cleaned result.
     */
    private function toggle(int $typeid, int $courseid, bool $show = true): bool {
        return external_api::clean_returnvalue(
            toggle_exam_tool_in_chooser::execute_returns(),
            toggle_exam_tool_in_chooser::execute($typeid, $courseid, $show)
        );
    }

    /**
     * Return the course-level override core keeps for a site tool, if any.
     *
     * @param int $typeid Tool type ID.
     * @param int $courseid Course ID.
     * @return int|false The stored coursevisible value, or false when there is none.
     */
    private function course_override(int $typeid, int $courseid) {
        global $DB;

        return $DB->get_field('lti_coursevisible', 'coursevisible', ['typeid' => $typeid, 'courseid' => $courseid]);
    }

    /**
     * The Raison site tool is shown in the course named, through a per-course override.
     *
     * @return void
     */
    public function test_shows_the_raison_site_tool_in_the_course(): void {
        global $DB;

        $this->assertTrue($this->toggle($this->typeid, (int)$this->course->id));

        $this->assertEquals(
            LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            $this->course_override($this->typeid, (int)$this->course->id)
        );
        $this->assertEquals(
            LTI_COURSEVISIBLE_PRECONFIGURED,
            $DB->get_field('lti_types', 'coursevisible', ['id' => $this->typeid]),
            'A site tool is overridden per course; the type itself must not change.'
        );
    }

    /**
     * The same call can hide the tool again.
     *
     * @return void
     */
    public function test_hides_the_raison_site_tool_again(): void {
        $this->toggle($this->typeid, (int)$this->course->id, true);

        $this->assertTrue($this->toggle($this->typeid, (int)$this->course->id, false));
        $this->assertEquals(
            LTI_COURSEVISIBLE_PRECONFIGURED,
            $this->course_override($this->typeid, (int)$this->course->id)
        );
    }

    /**
     * A Raison tool added at course level is shown when it belongs to the course named.
     *
     * @return void
     */
    public function test_shows_a_raison_course_tool_of_the_same_course(): void {
        global $DB;

        $coursetool = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_course_tool_types([
            'name' => 'Raison exam tool, course copy',
            'baseurl' => environment::url('services', 'integration/lti/launch'),
            'course' => $this->course->id,
            'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);

        $this->assertTrue($this->toggle($coursetool, (int)$this->course->id));
        $this->assertEquals(
            LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
            $DB->get_field('lti_types', 'coursevisible', ['id' => $coursetool])
        );
    }

    /**
     * A tool that launches anywhere but Raison is refused, and left untouched.
     *
     * This is the finding the function exists for: core's own toggle accepted any tool type.
     *
     * @return void
     */
    public function test_refuses_a_tool_from_another_host(): void {
        $foreign = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Unrelated tool',
            'baseurl' => 'https://tool.example.com/launch',
            'state' => LTI_TOOL_STATE_CONFIGURED,
            'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
            'lti_coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);

        try {
            $this->toggle($foreign, (int)$this->course->id);
            $this->fail('A tool launching from another host must not be toggled.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementtoolnotallowed', $exception->errorcode);
        }
        $this->assertFalse($this->course_override($foreign, (int)$this->course->id));
    }

    /**
     * A course-level tool belonging to another course is refused, even when it launches from Raison.
     *
     * Core updates a course-level type in place without checking it belongs to the course named,
     * so without this the call would change another course's chooser while naming this one.
     *
     * @return void
     */
    public function test_refuses_a_course_tool_of_another_course(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course();
        $othertool = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_course_tool_types([
            'name' => 'Raison exam tool, other course',
            'baseurl' => environment::url('services', 'integration/lti/launch'),
            'course' => $othercourse->id,
            'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
        ]);

        try {
            $this->toggle($othertool, (int)$this->course->id);
            $this->fail('A course tool of another course must not be toggled.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('choosertoolnotincourse', $exception->errorcode);
        }
        $this->assertEquals(
            LTI_COURSEVISIBLE_PRECONFIGURED,
            $DB->get_field('lti_types', 'coursevisible', ['id' => $othertool])
        );
    }

    /**
     * An unknown tool type is rejected.
     *
     * @return void
     */
    public function test_refuses_an_unknown_tool_type(): void {
        $this->expectException(\dml_missing_record_exception::class);
        $this->toggle(-1, (int)$this->course->id);
    }

    /**
     * A caller without mod/lti:addcoursetool in the course is refused before anything else.
     *
     * @return void
     */
    public function test_requires_the_course_tool_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$this->course->id, 'student');
        $this->setUser($user);

        try {
            $this->toggle($this->typeid, (int)$this->course->id);
            $this->fail('A caller without mod/lti:addcoursetool must be refused.');
        } catch (\required_capability_exception $exception) {
            // Enrolled, so validate_context() passes and only the capability check can refuse.
            $this->assertSame('nopermissions', $exception->errorcode);
        }
        $this->assertFalse($this->course_override($this->typeid, (int)$this->course->id));
    }
}
