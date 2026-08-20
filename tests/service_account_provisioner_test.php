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
 * Tests for the dedicated service identity.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\service_account_provisioner;

/**
 * Covers the identity the web-service token runs as.
 *
 * The point of this class is not that provisioning works -- that is the easy half. It is
 * what the account cannot do. The token used to belong to a site administrator, so
 * every capability question answered yes and no test could have told the difference between
 * a capability the integration needs and one it merely inherited. Most of what follows is
 * therefore negative: an explicit deny-list, a sweep over every RISK_CONFIG capability on
 * the site, and course-context checks that assert the absence of write access.
 *
 * Nothing here touches the network, and nothing here needs to: provisioning is entirely
 * local. The one thing that is deliberately *not* covered is core's own authorised-user
 * join, which needs webservice_server HTTP scaffolding this suite does not have; the tests
 * below assert the state that join consumes, not the join itself.
 */
final class service_account_provisioner_test extends \advanced_testcase {
    /**
     * Return the ID of the shipped external service.
     *
     * @return int
     */
    private function service_id(): int {
        global $DB;

        return (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest'], MUST_EXIST);
    }

    /**
     * Provisioning produces an account that is not, and cannot become, an administrator.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure
     * @return void
     */
    public function test_ensure_creates_a_non_administrator_identity(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $this->assertSame(service_account_provisioner::USERNAME, $user->username);
        $this->assertSame(1, (int)$user->confirmed);
        $this->assertSame(0, (int)$user->deleted);
        $this->assertSame(0, (int)$user->suspended);
        $this->assertSame((int)$CFG->mnet_localhost_id, (int)$user->mnethostid);

        $this->assertFalse(is_siteadmin($userid), 'The service account must never be a site administrator.');
        $this->assertFalse(
            has_capability('moodle/site:config', \context_system::instance(), $userid),
            'The service account must not be able to administer the site.'
        );
    }

    /**
     * The account authenticates as auth_webservice, and only as auth_webservice.
     *
     * Three separate behaviours in core depend on this exact value, and none of them is
     * obvious from the plugin alone. auth_plugin_webservice::user_login() always returns
     * false, so the account has no interactive login. require_login() exempts this auth
     * method -- and only this one -- from the site policy gate, so on a site with a policy
     * configured any other value would make every function that calls validate_context()
     * throw sitepolicynotagreed. And 'nologin', the value that reads like the safest choice
     * of all, is refused outright by the web-service layer, taking the integration down.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_service_account_auth_is_pinned_to_webservice(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();

        $this->assertSame('webservice', $DB->get_field('user', 'auth', ['id' => $userid]));
        $this->assertSame(1, (int)$DB->get_field('user', 'policyagreed', ['id' => $userid]));
    }

    /**
     * The account can actually use the REST protocol.
     *
     * Its own test rather than one row of the capability matrix, because the dependency is
     * invisible from this plugin's code: webservice/rest:use is declared with no archetypes,
     * a stock Moodle supplies it through the authenticated user role, and an administrator
     * would satisfy it anyway through the is_siteadmin short-circuit. All three of those can
     * stop being true on a hardened site without anything here changing, and the symptom is
     * lopsided enough to waste a day -- file downloads keep working, because
     * webservice/pluginfile.php never checks the protocol capability, while every function
     * call returns accessexception before the service or the function is even considered.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_can_use_the_rest_protocol(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(
            has_capability('webservice/rest:use', \context_system::instance(), $userid),
            'Without this the token cannot call anything at all.'
        );
    }

    /**
     * A missing protocol capability is reported as a health problem.
     *
     * The symptom on a live site is a registration that fails with an opaque transport
     * error, so the health check has to name it.
     *
     * @covers \local_corolair\local\service_account_provisioner::health_problem
     * @return void
     */
    public function test_health_problem_reports_a_missing_protocol_capability(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull(service_account_provisioner::health_problem());

        // Prohibited rather than unassigned, because a stock Moodle also grants this through
        // the authenticated user role, so removing our own grant alone changes nothing.
        // That is the whole reason we grant it explicitly: the only site where our grant is
        // load-bearing is one that has hardened the authenticated user role.
        assign_capability(
            'webservice/rest:use',
            CAP_PROHIBIT,
            $roleid,
            \context_system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame(
            'service_account_capabilities_missing',
            service_account_provisioner::health_problem()
        );
    }

    /**
     * Repeated provisioning converges rather than accumulating.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure
     * @return void
     */
    public function test_ensure_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();

        $first = service_account_provisioner::ensure();
        $second = service_account_provisioner::ensure();
        $third = service_account_provisioner::ensure();

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertSame(1, $DB->count_records('user', ['username' => service_account_provisioner::USERNAME]));
        $this->assertSame(1, $DB->count_records('role', ['shortname' => service_account_provisioner::ROLE_SHORTNAME]));

        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $this->assertSame(1, $DB->count_records('role_assignments', [
            'roleid' => $roleid,
            'userid' => $first,
            'contextid' => \context_system::instance()->id,
        ]));
        $this->assertSame(1, $DB->count_records('external_services_users', [
            'externalserviceid' => $this->service_id(),
            'userid' => $first,
        ]));
    }

    /**
     * An account suspended by uninstall is reactivated, not duplicated.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_ensure_repairs_a_suspended_account(): void {
        global $DB;

        $this->resetAfterTest();

        $first = service_account_provisioner::ensure();
        service_account_provisioner::suspend();
        $this->assertSame(1, (int)$DB->get_field('user', 'suspended', ['id' => $first]));

        $second = service_account_provisioner::ensure();

        $this->assertSame($first, $second, 'Reinstalling must reuse the account, not create another.');
        $this->assertSame(0, (int)$DB->get_field('user', 'suspended', ['id' => $first]));
        $this->assertSame(1, $DB->count_records('user', ['username' => service_account_provisioner::USERNAME]));
    }

    /**
     * The account carries the published Raison address.
     *
     * Worth pinning because the value is the only part of the account that tells an
     * administrator, looking at an unfamiliar user they did not create, who to ask about it.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_service_account_uses_the_published_address(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $this->assertSame(service_account_provisioner::EMAIL, $user->email);
        // The site must never mail the address, and ordinary users must not see it.
        $this->assertSame(1, (int)$user->emailstop);
        $this->assertSame(0, (int)$user->maildisplay);
    }

    /**
     * An account provisioned by an earlier release is moved to the current address.
     *
     * Without this the change would only reach sites that provision for the first time, and
     * every existing installation would keep the placeholder indefinitely -- including the
     * ones most likely to be looked at.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_a_previously_provisioned_address_is_brought_forward(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $DB->set_field('user', 'email', service_account_provisioner::FALLBACK_EMAIL, ['id' => $userid]);

        $this->assertSame($userid, service_account_provisioner::ensure());
        $this->assertSame(
            service_account_provisioner::EMAIL,
            $DB->get_field('user', 'email', ['id' => $userid])
        );
    }

    /**
     * An address an administrator chose is left alone.
     *
     * The same rule the display name and description follow: repair restores what the
     * integration depends on, and nothing else. Nothing here depends on the address.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_a_customised_address_survives_repair(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $DB->set_field('user', 'email', 'moodle-admin@example.com', ['id' => $userid]);

        service_account_provisioner::ensure();

        $this->assertSame('moodle-admin@example.com', $DB->get_field('user', 'email', ['id' => $userid]));
    }

    /**
     * A real user already holding the address keeps it to themselves.
     *
     * user.email has no unique index, so Moodle would accept the duplicate silently and the
     * damage would surface somewhere else entirely: get_complete_user_data('email', ...)
     * backs the forgot-password flow with get_record(), which stops returning a usable
     * answer once two rows match. A Raison employee with an account on a customer's site is
     * not a hypothetical, so the collision is stepped around rather than argued about.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_a_colliding_account_pushes_the_service_to_its_fallback(): void {
        global $DB;

        $this->resetAfterTest();

        $person = $this->getDataGenerator()->create_user(['email' => service_account_provisioner::EMAIL]);

        $userid = service_account_provisioner::ensure();

        $this->assertSame(
            service_account_provisioner::FALLBACK_EMAIL,
            $DB->get_field('user', 'email', ['id' => $userid])
        );
        $this->assertSame(
            service_account_provisioner::EMAIL,
            $DB->get_field('user', 'email', ['id' => (int)$person->id]),
            'The real account keeps the address it had.'
        );
    }

    /**
     * A collision appearing later moves the account back off the address.
     *
     * The order that actually happens on a live site: the integration is provisioned first
     * and the person is added months later. Repair is the only thing that sees it.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_a_later_collision_is_stepped_back_from(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $this->assertSame(service_account_provisioner::EMAIL, $DB->get_field('user', 'email', ['id' => $userid]));

        $this->getDataGenerator()->create_user(['email' => service_account_provisioner::EMAIL]);
        service_account_provisioner::ensure();

        $this->assertSame(
            service_account_provisioner::FALLBACK_EMAIL,
            $DB->get_field('user', 'email', ['id' => $userid])
        );
    }

    /**
     * A deleted account is replaced rather than resurrected.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_ensure_ignores_a_deleted_account(): void {
        global $DB;

        $this->resetAfterTest();

        $first = service_account_provisioner::ensure();
        // Emulate delete_user() closely enough for the lookups: the flag plus the mangled
        // username, which is what makes the username lookup miss on a real site too.
        $DB->set_field('user', 'deleted', 1, ['id' => $first]);
        $DB->set_field('user', 'username', 'corolair_webservice.1755000000', ['id' => $first]);

        $second = service_account_provisioner::ensure();

        $this->assertNotSame($first, $second);
        $this->assertSame(0, (int)$DB->get_field('user', 'deleted', ['id' => $second]));
        $this->assertSame((int)$second, (int)get_config('local_corolair', 'serviceaccountid'));
    }

    /**
     * An administrator's rename of the account is preserved.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_user
     * @return void
     */
    public function test_ensure_finds_a_renamed_account_by_its_recorded_id(): void {
        global $DB;

        $this->resetAfterTest();

        $first = service_account_provisioner::ensure();
        $DB->set_field('user', 'username', 'renamed_by_an_admin', ['id' => $first]);

        $second = service_account_provisioner::ensure();

        $this->assertSame($first, $second);
        $this->assertSame(1, $DB->count_records('user', ['id' => $first]));
    }

    /**
     * A role whose shortname was changed is repaired, not orphaned.
     *
     * Orphaning matters more than the duplication does: the abandoned role keeps every
     * capability it was granted, at system context, with nothing left pointing at it.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_role
     * @return void
     */
    public function test_ensure_recovers_from_a_changed_role_shortname(): void {
        global $DB;

        $this->resetAfterTest();

        service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $DB->set_field('role', 'shortname', 'renamed_service_role', ['id' => $roleid]);

        $this->assertSame($roleid, service_account_provisioner::ensure_role());
        $this->assertSame(1, $DB->count_records('role', ['id' => $roleid]));
        $this->assertFalse(
            $DB->record_exists('role', ['shortname' => service_account_provisioner::ROLE_SHORTNAME]),
            'A second role must not be created beside the renamed one.'
        );
    }

    /**
     * A grant an administrator overrode to prohibit is restored.
     *
     * This is what assign_capability()'s $overwrite argument buys, and the reason this
     * class does not copy role_provisioner's direct role_capabilities write.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_ensure_repairs_a_prohibited_capability(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $context = \context_system::instance();

        assign_capability('moodle/course:view', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability('moodle/course:view', $context, $userid));

        service_account_provisioner::ensure_capabilities($roleid);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(has_capability('moodle/course:view', $context, $userid));
    }

    /**
     * Every declared read capability is actually held.
     *
     * @dataProvider read_capability_provider
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @param string $capability Capability name.
     * @return void
     */
    public function test_service_account_holds_each_declared_read_capability(string $capability): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(
            has_capability($capability, \context_system::instance(), $userid),
            "{$capability} is declared but not actually held."
        );
    }

    /**
     * Supply each declared read capability.
     *
     * @return array[]
     */
    public static function read_capability_provider(): array {
        $cases = [];
        foreach (service_account_provisioner::READ_CAPABILITIES as $capability) {
            $cases[$capability] = [$capability];
        }
        return $cases;
    }

    /**
     * The account holds none of the capabilities that would make it an administrator.
     *
     * @dataProvider forbidden_capability_provider
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @param string $capability Capability name.
     * @return void
     */
    public function test_service_account_holds_no_forbidden_capability(string $capability): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse(
            has_capability($capability, \context_system::instance(), $userid),
            "{$capability} must never be held by the Raison service account."
        );
    }

    /**
     * Supply capabilities the service account must never hold.
     *
     * @return array[]
     */
    public static function forbidden_capability_provider(): array {
        $forbidden = [
            'moodle/site:config',
            'moodle/site:uploadusers',
            'moodle/user:create',
            'moodle/user:delete',
            'moodle/user:update',
            'moodle/role:assign',
            'moodle/role:manage',
            'moodle/role:override',
            'moodle/course:create',
            'moodle/course:delete',
            'moodle/course:update',
            'moodle/webservice:createtoken',
            'moodle/webservice:managealltokens',
        ];
        $cases = [];
        foreach ($forbidden as $capability) {
            $cases[$capability] = [$capability];
        }
        return $cases;
    }

    /**
     * The account holds no capability carrying a configuration risk.
     *
     * Deliberately a sweep rather than a list. A named deny-list only catches what someone
     * thought to name; this catches a careless future addition to READ_CAPABILITIES that
     * nobody realised was a configuration capability at all.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_holds_no_risk_config_capability(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();
        $context = \context_system::instance();

        $held = [];
        foreach ($DB->get_records('capabilities') as $capability) {
            if (!((int)$capability->riskbitmask & RISK_CONFIG)) {
                continue;
            }
            if (has_capability($capability->name, $context, $userid)) {
                $held[] = $capability->name;
            }
        }

        $this->assertSame(
            [],
            $held,
            'The service account holds configuration-risk capabilities: ' . implode(', ', $held)
        );
    }

    /**
     * The write capabilities are granted, and they are the only core writes there are.
     *
     * Stated positively rather than left implicit. These four exist for exam placement and
     * nothing else, and what bounds them is the function surface rather than the capability
     * set: Raison can only reach them through this plugin's three exam-placement functions,
     * and the deletion one resolves its target through the {lti} table with MUST_EXIST, so
     * it cannot remove an arbitrary course module.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_ensure_grants_the_write_capabilities(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();
        $context = \context_system::instance();

        foreach (service_account_provisioner::WRITE_CAPABILITIES as $capability) {
            $this->assertTrue(
                has_capability($capability, $context, $userid),
                "{$capability} is declared as a write capability but was not granted."
            );
        }
    }

    /**
     * A learner who hides their address is still matched by it.
     *
     * The behaviour moodle/course:useremail exists for, tested through core rather than by
     * asserting the grant, because the grant on its own proves nothing about the payload.
     *
     * The configuration matters and is the whole point: with the stock showuseridentity list
     * the address arrives through moodle/site:viewuseridentity and this test would pass with
     * the capability removed. Narrowing that list is what an administrator tightening user
     * privacy does, and on such a site the address disappears for every user whose
     * maildisplay is hidden. Nothing raises: Raison falls back to matching accounts by
     * address when it holds no Moodle user ID, finds nobody, and the person is shown no
     * courses at all.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_reads_an_address_the_owner_hides(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');

        set_config('showuseridentity', '');

        $userid = service_account_provisioner::ensure();
        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user(['maildisplay' => 0]);
        $this->getDataGenerator()->enrol_user($learner->id, $course->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($userid);

        $details = user_get_user_details($learner, $course, ['email']);

        $this->assertSame(
            $learner->email,
            $details['email'] ?? null,
            'Without moodle/course:useremail the address is omitted rather than refused.'
        );
    }

    /**
     * The service role is assignable at system context only.
     *
     * A course-assignable role would look like scoping while providing a second, unaudited
     * way to hand these capabilities to interactive users.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_role
     * @return void
     */
    public function test_service_role_is_system_context_only(): void {
        global $DB;

        $this->resetAfterTest();

        service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');

        $levels = $DB->get_fieldset_select('role_context_levels', 'contextlevel', 'roleid = ?', [$roleid]);
        $this->assertSame([CONTEXT_SYSTEM], array_map('intval', $levels));
    }

    /**
     * The account can read any course without being enrolled in it.
     *
     * Stated positively on purpose. This is the one broad grant the design accepts, it is
     * unavoidable for a catalogue-level integration, and a reviewer should be able to see
     * that it was deliberate rather than inferred from its absence.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_can_view_any_course_without_enrolment(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $course = $this->getDataGenerator()->create_course();
        accesslib_clear_all_caches_for_unit_testing();

        $context = \context_course::instance($course->id);
        $this->assertTrue(has_capability('moodle/course:view', $context, $userid));
        $this->assertFalse(is_enrolled($context, $userid), 'The account must not be enrolled anywhere.');
    }

    /**
     * The account can place an activity in a course but cannot reshape the course itself.
     *
     * The distinction the write set rests on. Adding, renaming and removing an External tool
     * activity is what exam placement needs; changing the course, creating or deleting
     * courses, moving them between categories and managing their groups are not, and none of
     * them is granted.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_cannot_reshape_a_course(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $course = $this->getDataGenerator()->create_course();
        accesslib_clear_all_caches_for_unit_testing();

        $context = \context_course::instance($course->id);
        $this->assertFalse(has_capability('moodle/course:update', $context, $userid));
        $this->assertFalse(has_capability('moodle/course:delete', $context, $userid));
        $this->assertFalse(has_capability('moodle/course:create', $context, $userid));
        $this->assertFalse(has_capability('moodle/course:changecategory', $context, $userid));
        $this->assertFalse(has_capability('moodle/course:managegroups', $context, $userid));
    }

    /**
     * The authorised-user row carries no expiry.
     *
     * Core compares this column in opposite directions on its two code paths: the file
     * scripts treat a past timestamp as expired, while the function-call join accepts a row
     * only when the column is null or already in the past. A future expiry therefore breaks
     * every web-service call while file downloads carry on working, with no error that
     * points at the cause. Null is the only value that behaves consistently.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_authorised
     * @return void
     */
    public function test_authorised_row_has_null_validuntil(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $row = $DB->get_record('external_services_users', [
            'externalserviceid' => $this->service_id(),
            'userid' => $userid,
        ], '*', MUST_EXIST);

        $this->assertNull($row->validuntil);
        $this->assertNull($row->iprestriction);
    }

    /**
     * Converging the authorised users removes strangers and collapses duplicates.
     *
     * There is no unique index on (externalserviceid, userid), so duplicates are possible
     * and make core's UNION return the service twice.
     *
     * @covers \local_corolair\local\service_account_provisioner::converge_authorised
     * @return void
     */
    public function test_converge_authorised_removes_duplicates_and_strangers(): void {
        global $DB;

        $this->resetAfterTest();

        $serviceid = $this->service_id();
        $keep = service_account_provisioner::ensure();
        $stranger = (int)$this->getDataGenerator()->create_user()->id;

        foreach ([$keep, $keep, $stranger] as $userid) {
            $DB->insert_record('external_services_users', (object)[
                'externalserviceid' => $serviceid,
                'userid' => $userid,
                'iprestriction' => null,
                'validuntil' => null,
                'timecreated' => time(),
            ]);
        }

        service_account_provisioner::converge_authorised($serviceid, [$keep]);

        $this->assertSame(1, $DB->count_records('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $keep,
        ]));
        $this->assertSame(0, $DB->count_records('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $stranger,
        ]));
    }

    /**
     * Service flags changed by hand in the web services UI are restored.
     *
     * Core rewrites them from db/services.php on a version bump and never again, so without
     * this an administrator who re-enables file upload keeps it enabled indefinitely.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_service_flags
     * @return void
     */
    public function test_ensure_service_flags_repairs_manual_drift(): void {
        global $DB;

        $this->resetAfterTest();

        $serviceid = $this->service_id();
        $DB->set_field('external_services', 'restrictedusers', 0, ['id' => $serviceid]);
        $DB->set_field('external_services', 'uploadfiles', 1, ['id' => $serviceid]);
        $DB->set_field('external_services', 'downloadfiles', 0, ['id' => $serviceid]);

        service_account_provisioner::ensure_service_flags();

        $service = $DB->get_record('external_services', ['id' => $serviceid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$service->restrictedusers);
        $this->assertSame(0, (int)$service->uploadfiles);
        $this->assertSame(1, (int)$service->downloadfiles);
    }

    /**
     * Health reporting distinguishes the ways the identity can be broken.
     *
     * @covers \local_corolair\local\service_account_provisioner::health_problem
     * @return void
     */
    public function test_health_problem_reports_each_failure_it_can_see(): void {
        global $DB;

        $this->resetAfterTest();

        $this->assertSame('service_account_missing', service_account_provisioner::health_problem());

        $userid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertNull(service_account_provisioner::health_problem());

        $serviceid = $this->service_id();
        $DB->delete_records('external_services_users', ['externalserviceid' => $serviceid, 'userid' => $userid]);
        $this->assertSame('service_account_not_authorised', service_account_provisioner::health_problem());

        service_account_provisioner::ensure_authorised($serviceid, $userid);
        $DB->set_field('external_services', 'uploadfiles', 1, ['id' => $serviceid]);
        $this->assertSame('service_uploadfiles_drift', service_account_provisioner::health_problem());

        $DB->set_field('external_services', 'uploadfiles', 0, ['id' => $serviceid]);
        $DB->set_field('user', 'suspended', 1, ['id' => $userid]);
        $this->assertSame('service_account_unusable', service_account_provisioner::health_problem());
    }

    /**
     * A locate() call never creates anything.
     *
     * Settings pages and the privacy provider both call it, and neither is an appropriate
     * moment to create a user.
     *
     * @covers \local_corolair\local\service_account_provisioner::locate
     * @return void
     */
    public function test_locate_never_provisions(): void {
        global $DB;

        $this->resetAfterTest();

        $this->assertSame(0, service_account_provisioner::locate());
        $this->assertSame(0, $DB->count_records('user', ['username' => service_account_provisioner::USERNAME]));
        $this->assertSame(0, $DB->count_records('role', ['shortname' => service_account_provisioner::ROLE_SHORTNAME]));
    }

    /**
     * Suspending leaves the account in place for the next installation.
     *
     * @covers \local_corolair\local\service_account_provisioner::suspend
     * @return void
     */
    public function test_suspend_does_not_delete_the_account(): void {
        global $DB;

        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        service_account_provisioner::suspend();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$user->suspended);
        $this->assertSame(0, (int)$user->deleted);
        $this->assertSame(service_account_provisioner::USERNAME, $user->username);
    }

    /**
     * Removing the role takes its capability grants with it.
     *
     * Core's capabilities_cleanup() only removes rows for this plugin's own capabilities at
     * uninstall, so anything left behind would keep granting core capabilities at system
     * context with nothing pointing at it.
     *
     * @covers \local_corolair\local\service_account_provisioner::remove_role
     * @return void
     */
    public function test_remove_role_clears_its_capability_grants(): void {
        global $DB;

        $this->resetAfterTest();

        service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $this->assertTrue($DB->record_exists('role_capabilities', ['roleid' => $roleid]));

        service_account_provisioner::remove_role();

        $this->assertFalse($DB->record_exists('role', ['id' => $roleid]));
        $this->assertFalse($DB->record_exists('role_capabilities', ['roleid' => $roleid]));
        $this->assertFalse($DB->record_exists('role_assignments', ['roleid' => $roleid]));
    }

    /**
     * Removing a role that is already gone is not an error.
     *
     * @covers \local_corolair\local\service_account_provisioner::remove_role
     * @return void
     */
    public function test_remove_role_is_convergent(): void {
        $this->resetAfterTest();

        service_account_provisioner::ensure();
        service_account_provisioner::remove_role();
        service_account_provisioner::remove_role();

        $this->assertTrue(true, 'Removing an absent role must not throw.');
    }

    /**
     * A capability the role used to hold is actually withdrawn, not merely stopped being granted.
     *
     * This is the whole reason REVOKED_CAPABILITIES exists. ensure_capabilities() is otherwise
     * additive, so deleting an entry from READ_CAPABILITIES changes nothing on a site that
     * already has the row -- the capability would stay granted for the life of the install, and
     * a test that only checked the constant would pass while every existing site kept it.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_revoked_capabilities_are_withdrawn_from_an_existing_role(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $this->assertGreaterThan(0, $roleid);

        // Put the site back in the state a pre-1.9.5 install is in.
        $context = \context_system::instance();
        foreach (service_account_provisioner::REVOKED_CAPABILITIES as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        accesslib_clear_all_caches_for_unit_testing();
        foreach (service_account_provisioner::REVOKED_CAPABILITIES as $capability) {
            $this->assertTrue(
                has_capability($capability, $context, $userid),
                "The fixture failed to grant {$capability}, so this test proves nothing."
            );
        }

        service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        foreach (service_account_provisioner::REVOKED_CAPABILITIES as $capability) {
            $this->assertFalse(
                has_capability($capability, $context, $userid),
                "{$capability} is listed as revoked but the service account still holds it."
            );
        }
    }

    /**
     * Revocation keeps happening, so a site cannot regain the capability by converging again.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_revocation_survives_repeated_convergence(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        service_account_provisioner::ensure();
        service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        foreach (service_account_provisioner::REVOKED_CAPABILITIES as $capability) {
            $this->assertFalse(
                has_capability($capability, \context_system::instance(), $userid),
                "{$capability} came back after a repeated convergence run."
            );
        }
    }
}
