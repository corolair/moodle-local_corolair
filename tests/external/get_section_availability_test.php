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
 * Tests for the section availability web-service function.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;

/**
 * Verifies raw restrict-access rules are only handed to users allowed to see them.
 *
 * The availability JSON describes who may see a section, and it can name specific users,
 * groups, and grade thresholds. Returning it to a learner would disclose the shape of
 * the course's access rules -- and sometimes other people's membership -- so the
 * function returns the metadata to anyone who can view the course but blanks the rules
 * themselves unless the caller could already see them in the course editor.
 */
final class get_section_availability_test extends \core_external\tests\externallib_testcase {
    /** Availability rule used as the section's restrict-access configuration. */
    private const AVAILABILITY = '{"op":"&","c":[{"type":"date","d":">=","t":1700000000}],"showc":[true]}';

    /**
     * Create a course whose second section carries a restrict-access rule.
     *
     * @return array{0: \stdClass, 1: \stdClass} Course and the restricted section.
     */
    private function create_restricted_course(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 3],
            ['createsections' => true]
        );
        $section = $DB->get_record('course_sections', [
            'course' => $course->id,
            'section' => 1,
        ], '*', MUST_EXIST);
        $DB->set_field('course_sections', 'availability', self::AVAILABILITY, ['id' => $section->id]);
        rebuild_course_cache($course->id, true);

        return [$course, $DB->get_record('course_sections', ['id' => $section->id], '*', MUST_EXIST)];
    }

    /**
     * Run the function and validate the result against its declared return structure.
     *
     * @param int $sectionid Section ID.
     * @param int $courseid Course ID.
     * @param int $sectionnum Section number.
     * @return array Cleaned result.
     */
    private function call(int $sectionid, int $courseid, int $sectionnum): array {
        return external_api::clean_returnvalue(
            get_section_availability::execute_returns(),
            get_section_availability::execute($sectionid, $courseid, $sectionnum)
        );
    }

    /**
     * A section is found by its record ID.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_section_is_found_by_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $section] = $this->create_restricted_course();

        $result = $this->call((int)$section->id, 0, -1);

        $this->assertSame((int)$section->id, $result['sectionid']);
        $this->assertSame((int)$course->id, $result['courseid']);
        $this->assertSame(1, $result['sectionnum']);
        $this->assertSame(self::AVAILABILITY, $result['availability_raw']);
    }

    /**
     * A section is found by course and section number.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_section_is_found_by_course_and_number(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $section] = $this->create_restricted_course();

        $result = $this->call(0, (int)$course->id, 1);

        $this->assertSame((int)$section->id, $result['sectionid']);
        $this->assertSame(self::AVAILABILITY, $result['availability_raw']);
    }

    /**
     * The section ID takes precedence when both lookups are supplied.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_section_id_takes_precedence(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $section] = $this->create_restricted_course();

        $result = $this->call((int)$section->id, (int)$course->id, 0);

        $this->assertSame((int)$section->id, $result['sectionid']);
        $this->assertSame(1, $result['sectionnum']);
    }

    /**
     * A section with no restrict-access rule reports none.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_unrestricted_section_reports_no_rule(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_restricted_course();

        $result = $this->call(0, (int)$course->id, 2);

        $this->assertNull($result['availability_raw']);
        $this->assertSame(2, $result['sectionnum']);
    }

    /**
     * Incomplete lookups that cannot identify a section.
     *
     * @return array[] Data sets of [sectionid, courseid, sectionnum].
     */
    public static function incomplete_lookup_provider(): array {
        return [
            'nothing supplied' => [0, 0, -1],
            'course without a section number' => [0, 1, -1],
            'section number without a course' => [0, 0, 1],
            'negative section number' => [0, 1, -5],
        ];
    }

    /**
     * A lookup that cannot identify a section is rejected.
     *
     * @dataProvider incomplete_lookup_provider
     * @covers \local_corolair\external\get_section_availability::execute
     * @param int $sectionid Section ID.
     * @param int $courseid Course ID.
     * @param int $sectionnum Section number.
     * @return void
     */
    public function test_incomplete_lookup_is_rejected(int $sectionid, int $courseid, int $sectionnum): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        get_section_availability::execute($sectionid, $courseid, $sectionnum);
    }

    /**
     * An unknown section is an error rather than an empty result.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_unknown_section_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        get_section_availability::execute(-1, 0, -1);
    }

    /**
     * A section number that does not exist in the course is an error.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_unknown_section_number_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course] = $this->create_restricted_course();

        $this->expectException(\dml_missing_record_exception::class);
        get_section_availability::execute(0, (int)$course->id, 99);
    }

    /**
     * Viewing the course is not enough to read the rules themselves.
     *
     * This is the disclosure boundary: the caller learns the section exists, but not who
     * or what it is restricted to.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_course_visibility_alone_does_not_disclose_the_rule(): void {
        $this->resetAfterTest();
        [$course, $section] = $this->create_restricted_course();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assignUserCapability('moodle/course:view', \context_course::instance($course->id)->id);

        $result = $this->call((int)$section->id, 0, -1);

        $this->assertSame((int)$section->id, $result['sectionid']);
        $this->assertNull(
            $result['availability_raw'],
            'Restrict-access rules must not be disclosed without editing or hidden-section rights.'
        );
    }

    /**
     * Capabilities that should disclose the raw rule.
     *
     * @return array[] Data sets of [capability].
     */
    public static function disclosing_capability_provider(): array {
        return [
            'course editor' => ['moodle/course:update'],
            'hidden section viewer' => ['moodle/course:viewhiddensections'],
        ];
    }

    /**
     * Either editing rights or hidden-section visibility discloses the rule.
     *
     * @dataProvider disclosing_capability_provider
     * @covers \local_corolair\external\get_section_availability::execute
     * @param string $capability Capability granted in addition to course visibility.
     * @return void
     */
    public function test_privileged_callers_see_the_rule(string $capability): void {
        $this->resetAfterTest();
        [$course, $section] = $this->create_restricted_course();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \context_course::instance($course->id)->id;
        $roleid = $this->assignUserCapability('moodle/course:view', $contextid);
        $this->assignUserCapability($capability, $contextid, $roleid);

        $result = $this->call((int)$section->id, 0, -1);

        $this->assertSame(self::AVAILABILITY, $result['availability_raw']);
    }

    /**
     * A caller with no access to the course at all is stopped before anything is read.
     *
     * validate_context() runs require_login for a course context, so an unenrolled
     * caller never reaches the capability check.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_unenrolled_caller_is_rejected(): void {
        $this->resetAfterTest();
        [, $section] = $this->create_restricted_course();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        try {
            get_section_availability::execute((int)$section->id, 0, -1);
            $this->fail('A caller with no access to the course should be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('requireloginerror', $exception->errorcode);
        }
    }

    /**
     * Being enrolled is not the same as being allowed to view the course record.
     *
     * An enrolled learner clears require_login but still lacks moodle/course:view, which
     * in Moodle means "view this course without being enrolled" and is a manager-level
     * capability. The function gates on it deliberately.
     *
     * @covers \local_corolair\external\get_section_availability::execute
     * @return void
     */
    public function test_enrolled_learner_without_the_capability_is_rejected(): void {
        $this->resetAfterTest();
        [$course, $section] = $this->create_restricted_course();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_section_availability::execute((int)$section->id, 0, -1);
    }
}
