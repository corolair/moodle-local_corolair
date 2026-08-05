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
 * Tests for the scoped manager-role assignment web-service function.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;
use local_corolair\local\role_provisioner;

/**
 * Verifies the function can only ever grant the one role it is named after.
 *
 * This replaced core_role_assign_roles in the service allow-list. That function let the
 * holder of the token assign *any* role in *any* context, including manager at system
 * level -- a full privilege-escalation primitive handed to a third party. The whole
 * value of this replacement is that the role and the context are fixed in code, so the
 * tests here are mostly about what it refuses to do.
 */
final class assign_manager_role_test extends \core_external\tests\externallib_testcase {
    /**
     * Return the plugin role ID.
     *
     * @return int
     */
    private function role_id(): int {
        global $DB;

        return (int)$DB->get_field('role', 'id', ['shortname' => role_provisioner::SHORTNAME], MUST_EXIST);
    }

    /**
     * Whether a user holds the plugin role at system context.
     *
     * @param int $userid User to check.
     * @return bool
     */
    private function holds_role(int $userid): bool {
        global $DB;

        return $DB->record_exists('role_assignments', [
            'roleid' => $this->role_id(),
            'userid' => $userid,
            'contextid' => \context_system::instance()->id,
        ]);
    }

    /**
     * Grant the calling user permission to assign the manager role.
     *
     * @return void
     */
    private function login_with_permission(): void {
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);
        $this->assignUserCapability('local/corolair:assignmanagerrole', \context_system::instance()->id);
    }

    /**
     * The requested users receive the plugin role at system context.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_assigns_the_plugin_role(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        $result = external_api::clean_returnvalue(
            assign_manager_role::execute_returns(),
            assign_manager_role::execute([(int)$first->id, (int)$second->id])
        );

        $this->assertSame([(int)$first->id, (int)$second->id], $result);
        $this->assertTrue($this->holds_role((int)$first->id));
        $this->assertTrue($this->holds_role((int)$second->id));
    }

    /**
     * A repeated user id is assigned once, not twice.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_duplicate_user_ids_are_collapsed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $user = $this->getDataGenerator()->create_user();

        $result = assign_manager_role::execute([(int)$user->id, (int)$user->id, (int)$user->id]);

        $this->assertSame([(int)$user->id], $result);
        $this->assertSame(1, $DB->count_records('role_assignments', [
            'roleid' => $this->role_id(),
            'userid' => (int)$user->id,
            'contextid' => \context_system::instance()->id,
        ]));
    }

    /**
     * Calling twice for the same user does not stack assignments.
     *
     * Corolair re-invites trainers routinely, so this runs repeatedly for the same user.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_repeated_calls_are_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $user = $this->getDataGenerator()->create_user();

        assign_manager_role::execute([(int)$user->id]);
        assign_manager_role::execute([(int)$user->id]);

        $this->assertSame(1, $DB->count_records('role_assignments', [
            'roleid' => $this->role_id(),
            'userid' => (int)$user->id,
        ]));
    }

    /**
     * An empty request is rejected rather than treated as a no-op.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_empty_user_list_is_rejected(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $this->expectException(\invalid_parameter_exception::class);
        assign_manager_role::execute([]);
    }

    /**
     * A user who does not exist is rejected.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_unknown_user_is_rejected(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $this->expectException(\invalid_parameter_exception::class);
        assign_manager_role::execute([-1]);
    }

    /**
     * A deleted user is rejected.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_deleted_user_is_rejected(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $user = $this->getDataGenerator()->create_user();
        delete_user($user);

        $this->expectException(\invalid_parameter_exception::class);
        assign_manager_role::execute([(int)$user->id]);
    }

    /**
     * One bad user id rejects the whole batch, assigning nothing.
     *
     * The validation loop runs to completion before any assignment, so a partially
     * applied batch would be both surprising and hard to undo.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_a_bad_user_rejects_the_whole_batch(): void {
        $this->resetAfterTest();
        $this->login_with_permission();
        $good = $this->getDataGenerator()->create_user();

        try {
            assign_manager_role::execute([(int)$good->id, -1]);
            $this->fail('A batch containing an invalid user should be rejected.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertStringContainsString('does not exist', $exception->getMessage());
        }

        $this->assertFalse(
            $this->holds_role((int)$good->id),
            'No user should be assigned when the batch is rejected.'
        );
    }

    /**
     * Assignment requires the dedicated plugin capability.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_capability_is_required(): void {
        $this->resetAfterTest();

        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);
        $target = $this->getDataGenerator()->create_user();

        try {
            assign_manager_role::execute([(int)$target->id]);
            $this->fail('Role assignment must be capability-gated.');
        } catch (\required_capability_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertFalse($this->holds_role((int)$target->id));
    }

    /**
     * Holding the plugin capability does not confer any other role.
     *
     * The point of replacing core_role_assign_roles was that the role is fixed. This
     * asserts the caller cannot end up with manager, or anything else, as a side effect.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_no_other_role_is_granted(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $target = $this->getDataGenerator()->create_user();

        assign_manager_role::execute([(int)$target->id]);

        $assigned = $DB->get_fieldset_select(
            'role_assignments',
            'roleid',
            'userid = ?',
            [(int)$target->id]
        );
        $this->assertSame([$this->role_id()], array_map('intval', $assigned));
    }

    /**
     * The role is granted at system context and nowhere else.
     *
     * @covers \local_corolair\external\assign_manager_role::execute
     * @return void
     */
    public function test_role_is_granted_only_at_system_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_user();

        assign_manager_role::execute([(int)$target->id]);

        $contexts = $DB->get_fieldset_select(
            'role_assignments',
            'contextid',
            'userid = ? AND roleid = ?',
            [(int)$target->id, $this->role_id()]
        );
        $this->assertSame(
            [(int)\context_system::instance()->id],
            array_map('intval', $contexts)
        );
    }
}
