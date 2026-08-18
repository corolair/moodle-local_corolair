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
 * Tests for the integration status report.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;
use local_corolair\local\service_account_provisioner;

/**
 * Covers the answers Raison acts on before it syncs content or places an exam.
 *
 * What makes these worth writing is that every field here is consumed by a decision with a
 * destructive failure mode. A wrong "privileged" makes a content sync archive live course
 * material; a wrong "examplacementenabled" sends Raison down a fallback path that can delete
 * any course module rather than only its own. So the tests care less about the happy path
 * than about the answer staying honest when provisioning is incomplete and when a
 * course-level override contradicts the system grant.
 */
final class integration_status_test extends \core_external\tests\externallib_testcase {
    /**
     * Call the function and validate the result against its declared return structure.
     *
     * A raw execute() result can satisfy assertions the real web-service layer would reject.
     *
     * @param int $courseid Course to evaluate in, or 0 for system context.
     * @return array Cleaned result.
     */
    private function call(int $courseid = 0): array {
        return external_api::clean_returnvalue(
            get_integration_status::execute_returns(),
            get_integration_status::execute($courseid)
        );
    }

    /**
     * The provisioned service account reports itself as fully privileged.
     *
     * This is the test that fails if a capability is added to VISIBILITY_CAPABILITIES
     * without also being granted, or granted without being disclosed. Reporting
     * privileged=false on a correctly provisioned site is not a cosmetic problem: it is what
     * suppresses archival, silently, for as long as it lasts.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_the_service_account_is_privileged(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($userid);

        $result = $this->call();

        $this->assertTrue($result['privileged']);
        $this->assertSame([], $result['missingcapabilities']);
        $this->assertTrue($result['serviceaccount']);
        $this->assertFalse($result['siteadmin']);
        $this->assertSame('', $result['healthproblem']);
        $this->assertSame('system', $result['contextlevel']);
        $this->assertSame(0, $result['courseid']);
    }

    /**
     * An ordinary user is reported as unprivileged, and told exactly what is missing.
     *
     * The converse of the test above. Without it, a function that returned true
     * unconditionally would still pass everything else here.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_a_bare_user_is_not_privileged(): void {
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());

        $result = $this->call();

        $this->assertFalse($result['privileged']);
        $this->assertNotEmpty($result['missingcapabilities']);
        $this->assertFalse($result['serviceaccount']);
        foreach ($result['missingcapabilities'] as $capability) {
            $this->assertContains($capability, get_integration_status::VISIBILITY_CAPABILITIES);
        }
    }

    /**
     * A course-level override is respected, and is the reason the parameter exists.
     *
     * The role is granted at system context but the sync runs per course, and those are not
     * the same question: a prevent override in one course takes the capability away there
     * while the system grant still reads as held. Answering at system scope would report
     * privileged=true for a course whose hidden activities the token cannot see, and the
     * sync would archive them. Without this test the courseid parameter looks redundant and
     * someone will remove it.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_a_course_level_prevent_is_respected(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        assign_capability('moodle/course:viewhiddenactivities', CAP_PREVENT, $roleid, $coursecontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($userid);

        $scoped = $this->call((int)$course->id);
        $this->assertFalse($scoped['privileged'], 'A prevent override in this course must be visible here.');
        $this->assertSame(['moodle/course:viewhiddenactivities'], $scoped['missingcapabilities']);
        $this->assertSame('course', $scoped['contextlevel']);
        $this->assertSame((int)$course->id, $scoped['courseid']);

        $system = $this->call();
        $this->assertTrue($system['privileged'], 'The system grant is untouched by a course override.');
    }

    /**
     * An unknown course falls back to system scope instead of throwing.
     *
     * This function is a diagnostic. Failing on a course the caller cannot resolve would
     * make it useless in exactly the situation it exists to describe.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_an_unknown_course_falls_back_to_system_scope(): void {
        $this->resetAfterTest();

        $this->setUser(service_account_provisioner::ensure());

        $result = $this->call(987654321);

        $this->assertSame('system', $result['contextlevel']);
        $this->assertSame(0, $result['courseid'], 'The echoed course tells the caller which scope answered.');
    }

    /**
     * Exam placement is reported as available.
     *
     * It was briefly an opt-in setting and is now unconditional, but the field stays in the
     * payload because Raison is already deployed reading it: an absent key deserialises to
     * false there and would make it refuse every placement. Pinned so that removing the
     * field, which looks like harmless cleanup, fails here rather than in production.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_exam_placement_is_reported_as_available(): void {
        $this->resetAfterTest();

        $this->setUser(service_account_provisioner::ensure());

        $result = $this->call();

        $this->assertArrayHasKey('examplacementenabled', $result);
        $this->assertTrue($result['examplacementenabled']);
    }

    /**
     * A broken service identity is reported rather than hidden behind a permission error.
     *
     * The reason this function has no capability check: a diagnostic that requires the thing
     * it diagnoses is worthless.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_a_broken_service_identity_is_reported(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($userid);

        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest'], MUST_EXIST);
        $DB->delete_records('external_services_users', ['externalserviceid' => $serviceid, 'userid' => $userid]);

        $this->assertSame('service_account_not_authorised', $this->call()['healthproblem']);
    }

    /**
     * The installed plugin version is reported, and is what gates the caller's behaviour.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_the_installed_version_is_reported(): void {
        $this->resetAfterTest();

        $this->setUser(service_account_provisioner::ensure());

        $result = $this->call();

        $this->assertSame((int)get_config('local_corolair', 'version'), $result['pluginversion']);
        $this->assertNotSame('', $result['pluginrelease']);
    }

    /**
     * A site administrator is reported as privileged and as an administrator.
     *
     * Covers the ownership handover window, during which the token still belongs to the
     * administrator who set the integration up. The caller uses siteadmin to tell a
     * completed cutover from one still in progress.
     *
     * @covers \local_corolair\external\get_integration_status::execute
     * @return void
     */
    public function test_an_administrator_is_privileged_but_not_the_service_account(): void {
        $this->resetAfterTest();

        service_account_provisioner::ensure();
        $this->setAdminUser();

        $result = $this->call();

        $this->assertTrue($result['privileged']);
        $this->assertTrue($result['siteadmin']);
        $this->assertFalse($result['serviceaccount']);
    }
}
