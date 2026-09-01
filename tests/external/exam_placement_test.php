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
 * Tests for the LTI exam-placement web-service functions.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;
use local_corolair\local\placement_registry;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/lti/locallib.php');
// Provides course_delete_module(), used to simulate a teacher removing a placement by hand.
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Verifies exam placements are created, renamed and removed within their declared bounds.
 *
 * These three functions are the plugin's only write surface on course content, so the
 * question each test answers is not just "does it work" but "can it reach anything it
 * should not".
 *
 * The bound is the ownership record written at creation time, not the capability check. That
 * distinction is the point: the service account holds moodle/course:manageactivities at system
 * context, so the capability passes in every course on the site and never narrowed anything.
 * Creation is bounded separately, by refusing any tool type that launches outside Raison.
 */
final class exam_placement_test extends \core_external\tests\externallib_testcase {
    /** @var \stdClass Course the placements are created in. */
    private $course;

    /** @var \stdClass Section the placements are created in. */
    private $section;

    /** @var int Configured LTI tool type. */
    private $typeid;

    /**
     * Build a course with sections and a configured LTI tool type.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course(
            ['numsections' => 3],
            ['createsections' => true]
        );
        $this->section = $this->section_record(1);
        $this->typeid = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Raison exam tool',
            // Must be a Raison launch URL: placement now refuses any tool type that launches
            // elsewhere, so a generic fixture host would fail every creation test.
            'baseurl' => 'https://services.corolair.dev/integration/lti/launch',
            'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);
    }

    /**
     * Return a section record of the fixture course by section number.
     *
     * @param int $sectionnum Section number.
     * @return \stdClass
     */
    private function section_record(int $sectionnum): \stdClass {
        global $DB;

        return $DB->get_record('course_sections', [
            'course' => $this->course->id,
            'section' => $sectionnum,
        ], '*', MUST_EXIST);
    }

    /**
     * Return the ordered course-module IDs of the fixture section.
     *
     * @return int[]
     */
    private function section_sequence(): array {
        global $DB;

        $sequence = (string)$DB->get_field('course_sections', 'sequence', ['id' => $this->section->id]);
        return $sequence === '' ? [] : array_map('intval', explode(',', $sequence));
    }

    /**
     * Create a placement and validate it against the declared return structure.
     *
     * @param string $name Activity name.
     * @param int $position Zero-based position, or -1 to append.
     * @return array Cleaned result.
     */
    private function create(string $name, int $position = -1): array {
        return external_api::clean_returnvalue(
            create_exam_placement::execute_returns(),
            create_exam_placement::execute(
                (int)$this->course->id,
                (int)$this->section->id,
                $this->typeid,
                $name,
                $position
            )
        );
    }

    /**
     * A placement is created in the requested section as an LTI activity.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_places_an_lti_activity(): void {
        global $DB;

        $result = $this->create('Midterm exam');

        $this->assertSame((int)$this->section->id, $result['sectionid']);
        $this->assertSame('Midterm exam', $result['name']);
        $this->assertGreaterThan(0, $result['ltiinstanceid']);
        $this->assertGreaterThan(0, $result['coursemoduleid']);

        $lti = $DB->get_record('lti', ['id' => $result['ltiinstanceid']], '*', MUST_EXIST);
        $this->assertSame('Midterm exam', $lti->name);
        $this->assertEquals((int)$this->course->id, (int)$lti->course);
        $this->assertEquals($this->typeid, (int)$lti->typeid);

        // The tool type accepts grades, so creation reaches lti_grade_item_update().
        // That reads $lti->grade unconditionally; leaving it unset warned on every
        // placement. A grade item must exist and must not have been built from an
        // undefined value.
        $this->assertSame(0, (int)$lti->grade);

        $cm = $DB->get_record('course_modules', ['id' => $result['coursemoduleid']], '*', MUST_EXIST);
        $this->assertEquals((int)$this->section->id, (int)$cm->section);
        $this->assertEquals(
            (int)$DB->get_field('modules', 'id', ['name' => 'lti']),
            (int)$cm->module
        );
        $this->assertSame([$result['coursemoduleid']], $this->section_sequence());
    }

    /**
     * The name is trimmed before it is stored.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_trims_the_name(): void {
        $result = $this->create('  Final exam  ');

        $this->assertSame('Final exam', $result['name']);
    }

    /**
     * Without a position the placement is appended to the section.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_appends_by_default(): void {
        $first = $this->create('First');
        $second = $this->create('Second');

        $this->assertSame(
            [$first['coursemoduleid'], $second['coursemoduleid']],
            $this->section_sequence()
        );
    }

    /**
     * A position inserts the placement before the module currently at that index.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_honours_a_position(): void {
        $first = $this->create('First');
        $second = $this->create('Second');

        $inserted = $this->create('Inserted', 1);

        $this->assertSame(
            [$first['coursemoduleid'], $inserted['coursemoduleid'], $second['coursemoduleid']],
            $this->section_sequence()
        );
    }

    /**
     * Position zero puts the placement at the front of the section.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_can_place_first(): void {
        $existing = $this->create('Existing');

        $inserted = $this->create('Inserted', 0);

        $this->assertSame(
            [$inserted['coursemoduleid'], $existing['coursemoduleid']],
            $this->section_sequence()
        );
    }

    /**
     * A position past the end of the section appends rather than failing.
     *
     * Corolair does not track Moodle's section contents, so it can legitimately ask for
     * a position that no longer exists. Appending is the documented behaviour.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_appends_when_the_position_is_out_of_range(): void {
        $existing = $this->create('Existing');

        $inserted = $this->create('Inserted', 99);

        $this->assertSame(
            [$existing['coursemoduleid'], $inserted['coursemoduleid']],
            $this->section_sequence()
        );
    }

    /**
     * Names that cannot identify an activity.
     *
     * @return array[] Data sets of [name].
     */
    public static function empty_name_provider(): array {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'tab and newline' => ["\t\n"],
        ];
    }

    /**
     * A blank name is rejected.
     *
     * @dataProvider empty_name_provider
     * @covers \local_corolair\external\create_exam_placement::execute
     * @param string $name Requested activity name.
     * @return void
     */
    public function test_create_rejects_a_blank_name(string $name): void {
        $this->expectException(\invalid_parameter_exception::class);
        create_exam_placement::execute(
            (int)$this->course->id,
            (int)$this->section->id,
            $this->typeid,
            $name
        );
    }

    /**
     * A position below the append sentinel is rejected.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_rejects_a_negative_position(): void {
        $this->expectException(\invalid_parameter_exception::class);
        create_exam_placement::execute(
            (int)$this->course->id,
            (int)$this->section->id,
            $this->typeid,
            'Exam',
            -2
        );
    }

    /**
     * An unknown LTI tool type is rejected.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_rejects_an_unknown_tool_type(): void {
        $this->expectException(\dml_missing_record_exception::class);
        create_exam_placement::execute(
            (int)$this->course->id,
            (int)$this->section->id,
            -1,
            'Exam'
        );
    }

    /**
     * A section belonging to a different course cannot be targeted.
     *
     * The section is looked up by ID *and* course, so a caller cannot place an activity
     * into a course it did not name and was not authorized against.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_rejects_a_section_from_another_course(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course(
            ['numsections' => 1],
            ['createsections' => true]
        );
        $othersection = $DB->get_record('course_sections', [
            'course' => $othercourse->id,
            'section' => 1,
        ], '*', MUST_EXIST);

        $this->expectException(\dml_missing_record_exception::class);
        create_exam_placement::execute(
            (int)$this->course->id,
            (int)$othersection->id,
            $this->typeid,
            'Exam'
        );
    }

    /**
     * Creating a placement requires activity management rights in the course.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_requires_activity_management(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$this->course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        create_exam_placement::execute(
            (int)$this->course->id,
            (int)$this->section->id,
            $this->typeid,
            'Exam'
        );
    }

    /**
     * An existing placement can be renamed.
     *
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @return void
     */
    public function test_manage_renames_a_placement(): void {
        global $DB;

        $created = $this->create('Original name');

        $result = external_api::clean_returnvalue(
            manage_exam_placement::execute_returns(),
            manage_exam_placement::execute($created['ltiinstanceid'], '  Renamed exam  ')
        );

        $this->assertSame($created['ltiinstanceid'], $result['ltiinstanceid']);
        $this->assertSame($created['coursemoduleid'], $result['coursemoduleid']);
        $this->assertSame('Renamed exam', $result['name']);
        $this->assertSame(
            'Renamed exam',
            $DB->get_field('lti', 'name', ['id' => $created['ltiinstanceid']])
        );
    }

    /**
     * Renaming does not move the activity or change its section.
     *
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @return void
     */
    public function test_manage_leaves_the_placement_in_place(): void {
        $first = $this->create('First');
        $second = $this->create('Second');

        manage_exam_placement::execute($first['ltiinstanceid'], 'First renamed');

        $this->assertSame(
            [$first['coursemoduleid'], $second['coursemoduleid']],
            $this->section_sequence()
        );
    }

    /**
     * A blank new name is rejected.
     *
     * @dataProvider empty_name_provider
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @param string $name Requested activity name.
     * @return void
     */
    public function test_manage_rejects_a_blank_name(string $name): void {
        $created = $this->create('Original name');

        $this->expectException(\invalid_parameter_exception::class);
        manage_exam_placement::execute($created['ltiinstanceid'], $name);
    }

    /**
     * An unknown LTI instance cannot be renamed.
     *
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @return void
     */
    public function test_manage_rejects_an_unknown_instance(): void {
        try {
            manage_exam_placement::execute(-1, 'Exam');
            $this->fail('An unowned instance must not be renameable.');
        } catch (\moodle_exception $exception) {
            // Asserted on the error code rather than the class: dml_missing_record_exception is
            // itself a moodle_exception, so an instanceof check would have passed against the old
            // behaviour this test exists to rule out.
            $this->assertSame('placementnotowned', $exception->errorcode);
        }
    }

    /**
     * Renaming requires activity management rights in the course.
     *
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @return void
     */
    public function test_manage_requires_activity_management(): void {
        $created = $this->create('Original name');

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$this->course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        manage_exam_placement::execute($created['ltiinstanceid'], 'Renamed');
    }

    /**
     * Deleting removes both the course module and the LTI instance.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_removes_the_placement(): void {
        global $DB;

        $created = $this->create('Doomed exam');

        $result = external_api::clean_returnvalue(
            delete_exam_placement::execute_returns(),
            delete_exam_placement::execute($created['ltiinstanceid'])
        );

        $this->assertTrue($result['deleted']);
        $this->assertSame($created['ltiinstanceid'], $result['ltiinstanceid']);
        $this->assertSame($created['coursemoduleid'], $result['coursemoduleid']);
        $this->assertFalse($DB->record_exists('lti', ['id' => $created['ltiinstanceid']]));
        $this->assertFalse($DB->record_exists('course_modules', ['id' => $created['coursemoduleid']]));
        $this->assertSame([], $this->section_sequence());
    }

    /**
     * Deleting one placement leaves its neighbours alone.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_is_scoped_to_one_placement(): void {
        global $DB;

        $keep = $this->create('Keep');
        $remove = $this->create('Remove');

        delete_exam_placement::execute($remove['ltiinstanceid']);

        $this->assertTrue($DB->record_exists('lti', ['id' => $keep['ltiinstanceid']]));
        $this->assertSame([$keep['coursemoduleid']], $this->section_sequence());
    }

    /**
     * An unknown LTI instance cannot be deleted.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_rejects_an_unknown_instance(): void {
        try {
            delete_exam_placement::execute(-1);
            $this->fail('An unowned instance must not be deletable.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementnotowned', $exception->errorcode);
        }
    }

    /**
     * The function cannot be turned on a non-LTI activity.
     *
     * The bound is now ownership rather than module type: nothing this plugin did not create is
     * reachable, whatever table it lives in. That also removes the identifier-collision caveat
     * this test used to carry, because a page instance ID sharing a value with a real LTI row is
     * still refused unless that LTI row is a recorded placement.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_cannot_reach_a_non_lti_activity(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'section' => 1,
        ]);

        try {
            delete_exam_placement::execute((int)$page->id);
            $this->fail('A non-LTI activity must not be reachable through this function.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementnotowned', $exception->errorcode);
        }
        $this->assertTrue(
            $DB->record_exists('page', ['id' => (int)$page->id]),
            'The unrelated activity must survive.'
        );
    }

    /**
     * Deleting requires activity management rights in the course.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_requires_activity_management(): void {
        global $DB;

        $created = $this->create('Protected exam');

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$this->course->id, 'student');
        $this->setUser($user);

        try {
            delete_exam_placement::execute($created['ltiinstanceid']);
            $this->fail('Deletion must be capability-gated.');
        } catch (\required_capability_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertTrue($DB->record_exists('lti', ['id' => $created['ltiinstanceid']]));
    }

    /**
     * Creation records the placement so the manage and delete functions can recognise it.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_records_ownership(): void {
        global $DB;

        $created = $this->create('Recorded exam');

        $record = $DB->get_record(placement_registry::TABLE, [
            'ltiinstanceid' => $created['ltiinstanceid'],
        ], '*', MUST_EXIST);
        $this->assertSame((int)$this->course->id, (int)$record->courseid);
        $this->assertSame((int)$this->typeid, (int)$record->typeid);
        $this->assertSame(
            1,
            $DB->count_records(placement_registry::TABLE, ['ltiinstanceid' => $created['ltiinstanceid']])
        );
    }

    /**
     * A tool type that launches somewhere other than Raison cannot be placed.
     *
     * This is what stops the integration attaching an arbitrary external tool to a course, given
     * that the plugin has no way to know which tool type ID belongs to Raison.
     *
     * @covers \local_corolair\external\create_exam_placement::execute
     * @return void
     */
    public function test_create_rejects_a_foreign_tool_host(): void {
        global $DB;

        $foreign = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Unrelated tool',
            'baseurl' => 'https://tool.example.com/launch',
            'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);

        try {
            create_exam_placement::execute(
                (int)$this->course->id,
                (int)$this->section->id,
                $foreign,
                'Exam'
            );
            $this->fail('A tool launching from another host must not be placeable.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementtoolnotallowed', $exception->errorcode);
        }
        $this->assertSame([], $this->section_sequence(), 'No activity may be left behind.');
        $this->assertSame(0, $DB->count_records(placement_registry::TABLE));
    }

    /**
     * An LTI activity created outside this plugin is out of reach of both write functions.
     *
     * The capability check alone never bounded this: the service account holds
     * moodle/course:manageactivities at system context, so it passes in every course.
     *
     * @covers \local_corolair\external\manage_exam_placement::execute
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_write_functions_cannot_reach_a_foreign_lti_activity(): void {
        global $DB;

        $foreign = $this->getDataGenerator()->create_module('lti', [
            'course' => $this->course->id,
            'section' => 1,
        ]);

        try {
            manage_exam_placement::execute((int)$foreign->id, 'Renamed');
            $this->fail('A foreign LTI activity must not be renameable.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementnotowned', $exception->errorcode);
        }

        try {
            delete_exam_placement::execute((int)$foreign->id);
            $this->fail('A foreign LTI activity must not be deletable.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('placementnotowned', $exception->errorcode);
        }

        $this->assertTrue($DB->record_exists('lti', ['id' => (int)$foreign->id]));
        $foreignname = $DB->get_field('lti', 'name', ['id' => (int)$foreign->id], MUST_EXIST);
        $this->assertSame($foreign->name, $foreignname, 'The unrelated activity must be untouched.');
    }

    /**
     * Deleting a placement whose activity a teacher already removed reports success.
     *
     * Raison has to be able to converge its own state. Throwing here would leave it retrying
     * against an activity that is never coming back.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_is_idempotent_once_the_activity_is_gone(): void {
        global $DB;

        $created = $this->create('Removed by hand');
        course_delete_module($created['coursemoduleid']);
        $this->assertTrue(
            $DB->record_exists(placement_registry::TABLE, ['ltiinstanceid' => $created['ltiinstanceid']]),
            'The ownership row outlives the activity until the next call collects it.'
        );

        $result = external_api::clean_returnvalue(
            delete_exam_placement::execute_returns(),
            delete_exam_placement::execute($created['ltiinstanceid'])
        );

        $this->assertTrue($result['deleted']);
        $this->assertFalse(
            $DB->record_exists(placement_registry::TABLE, ['ltiinstanceid' => $created['ltiinstanceid']])
        );
    }

    /**
     * A successful deletion drops the ownership row with the activity.
     *
     * @covers \local_corolair\external\delete_exam_placement::execute
     * @return void
     */
    public function test_delete_forgets_the_placement(): void {
        global $DB;

        $created = $this->create('Transient exam');
        delete_exam_placement::execute($created['ltiinstanceid']);

        $this->assertFalse(
            $DB->record_exists(placement_registry::TABLE, ['ltiinstanceid' => $created['ltiinstanceid']])
        );
    }

    /**
     * A host setting that cannot be trusted falls back to the shipped default.
     *
     * The stored value bypasses admin_setting_configtext::validate() whenever it is written by
     * set_config(), the CLI or $CFG->forced_plugin_settings, so the reader has to treat it as
     * untrusted. Falling back to the default keeps a misconfigured site working against the real
     * host; the alternatives are silently disabling the check or bricking placement entirely.
     *
     * @dataProvider untrusted_host_setting_provider
     * @covers \local_corolair\local\placement_registry::allowed_host
     * @param string $stored Value written to the setting.
     * @return void
     */
    public function test_allowed_host_falls_back_to_the_default(string $stored): void {
        set_config('ltitoolhost', $stored, 'local_corolair');

        $this->assertSame(placement_registry::DEFAULT_TOOL_HOST, placement_registry::allowed_host());
    }

    /**
     * Untrusted values for the host setting.
     *
     * @return array[]
     */
    public static function untrusted_host_setting_provider(): array {
        return [
            'unset' => [''],
            'whitespace' => ['   '],
            'a pasted URL' => ['https://services.corolair.dev/integration/lti/launch'],
            'a host and port' => ['services.corolair.dev:8443'],
            'not a host at all' => ['not a host'],
        ];
    }

    /**
     * An administrator may still point the integration at a different host.
     *
     * @covers \local_corolair\local\placement_registry::allowed_host
     * @return void
     */
    public function test_allowed_host_honours_a_valid_override(): void {
        set_config('ltitoolhost', '  Services.Example.Org  ', 'local_corolair');

        $this->assertSame('services.example.org', placement_registry::allowed_host());
    }
}
