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
 * Tests for the role lookup web-service function.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

use core_external\external_api;

/**
 * Verifies role lookups are capability-gated and return the declared structure.
 *
 * The web-service token is held by Raison, so every function it can reach is part of
 * the plugin's exposed surface. This one reads the site role table, which is how
 * Corolair maps its own permissions onto Moodle roles.
 */
final class get_roles_test extends \core_external\tests\externallib_testcase {
    /**
     * Grant the calling user permission to read roles.
     *
     * @return \stdClass The calling user.
     */
    private function login_with_permission(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assignUserCapability('local/corolair:viewroles', \context_system::instance()->id);
        return $user;
    }

    /**
     * Run the function and validate the result against its declared return structure.
     *
     * A raw execute() result can satisfy assertions the real web-service layer would
     * reject, so everything is put through clean_returnvalue() first.
     *
     * @param int|null $id Role ID filter.
     * @param string|null $shortname Role shortname filter.
     * @return array Cleaned result.
     */
    private function call($id = null, $shortname = null): array {
        return external_api::clean_returnvalue(
            get_roles::execute_returns(),
            get_roles::execute($id, $shortname)
        );
    }

    /**
     * With no filter, every site role is returned in sort order.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_returns_every_role_in_sort_order(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();

        $result = $this->call();

        $this->assertCount($DB->count_records('role'), $result);
        $sortorders = array_column($result, 'sortorder');
        $sorted = $sortorders;
        sort($sorted);
        $this->assertSame($sorted, $sortorders, 'Roles should come back in sortorder.');

        $shortnames = array_column($result, 'shortname');
        $this->assertContains('manager', $shortnames);
        $this->assertContains(
            'corolair',
            $shortnames,
            "The plugin's own role should be visible to the integration."
        );
    }

    /**
     * A role can be fetched by ID.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_returns_a_role_by_id(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $expected = $DB->get_record('role', ['shortname' => 'corolair'], '*', MUST_EXIST);

        $result = $this->call((int)$expected->id);

        $this->assertCount(1, $result);
        $this->assertSame((int)$expected->id, $result[0]['id']);
        $this->assertSame('corolair', $result[0]['shortname']);
    }

    /**
     * A role can be fetched by shortname.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_returns_a_role_by_shortname(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $result = $this->call(null, 'editingteacher');

        $this->assertCount(1, $result);
        $this->assertSame('editingteacher', $result[0]['shortname']);
        $this->assertSame('editingteacher', $result[0]['archetype']);
    }

    /**
     * The ID filter wins when both filters are supplied.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_id_takes_precedence_over_shortname(): void {
        global $DB;

        $this->resetAfterTest();
        $this->login_with_permission();
        $expected = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $result = $this->call((int)$expected->id, 'editingteacher');

        $this->assertCount(1, $result);
        $this->assertSame('student', $result[0]['shortname']);
    }

    /**
     * An unknown role is an error rather than an empty list.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_unknown_role_id_is_rejected(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $this->expectException(\dml_missing_record_exception::class);
        get_roles::execute(-1);
    }

    /**
     * An unknown shortname is an error rather than an empty list.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_unknown_shortname_is_rejected(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $this->expectException(\dml_missing_record_exception::class);
        get_roles::execute(null, 'nosuchrole');
    }

    /**
     * A shortname outside PARAM_ALPHANUMEXT is rejected before it reaches the database.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_shortname_is_parameter_validated(): void {
        $this->resetAfterTest();
        $this->login_with_permission();

        $this->expectException(\invalid_parameter_exception::class);
        get_roles::execute(null, "student' OR '1'='1");
    }

    /**
     * Reading roles requires the plugin capability.
     *
     * @covers \local_corolair\external\get_roles::execute
     * @return void
     */
    public function test_capability_is_required(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_roles::execute();
    }
}
