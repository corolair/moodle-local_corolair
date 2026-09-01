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
 * Tests for the convergence of the local_corolair installation script.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\role_provisioner;
use local_corolair\local\service_account_provisioner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/corolair/db/install.php');

/**
 * Verifies that installing is idempotent and survives leftover state.
 *
 * The plugin's one table is created by core from db/install.xml before any of this runs and
 * holds no state an install has to seed, so installation is still almost entirely a matter of
 * driving core role tables to the expected end state. These tests assert that end state is
 * reached regardless of what the site started from.
 */
final class install_test extends \advanced_testcase {
    /**
     * Assert the role is provisioned exactly once, with nothing duplicated.
     *
     * @param string $message Context added to failure output.
     * @return int The role ID.
     */
    private function assert_role_fully_provisioned(string $message = ''): int {
        global $DB;

        $roles = $DB->get_records('role', ['shortname' => role_provisioner::SHORTNAME], '', 'id');
        $this->assertCount(1, $roles, 'Expected exactly one corolair role. ' . $message);
        $roleid = (int)reset($roles)->id;

        // The driver may return these as strings, so normalise before comparing.
        $contextlevels = array_map('intval', $DB->get_fieldset_select(
            'role_context_levels',
            'contextlevel',
            'roleid = ?',
            [$roleid]
        ));
        sort($contextlevels);
        $expected = role_provisioner::CONTEXTLEVELS;
        sort($expected);
        $this->assertSame($expected, $contextlevels, 'Unexpected context levels. ' . $message);

        $systemcontextid = \context_system::instance()->id;
        foreach (role_provisioner::CAPABILITIES as $capability) {
            $this->assertTrue(
                $DB->record_exists('role_capabilities', [
                    'roleid' => $roleid,
                    'contextid' => $systemcontextid,
                    'capability' => $capability,
                    'permission' => CAP_ALLOW,
                ]),
                "Capability {$capability} missing or not allowed. " . $message
            );
        }
        $this->assertSame(
            count(role_provisioner::CAPABILITIES),
            $DB->count_records('role_capabilities', ['roleid' => $roleid]),
            'Unexpected number of capability rows. ' . $message
        );

        return $roleid;
    }

    /**
     * Remove the role provisioned when the test site was built.
     *
     * @return void
     */
    private function delete_provisioned_role(): void {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => role_provisioner::SHORTNAME], 'id');
        if ($role) {
            delete_role((int)$role->id);
        }
    }

    /**
     * Running the installer twice must not duplicate roles, context levels or capabilities.
     *
     * @covers ::xmldb_local_corolair_install
     * @return void
     */
    public function test_install_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(xmldb_local_corolair_install());
        $first = $this->assert_role_fully_provisioned('After first install.');

        $this->assertTrue(xmldb_local_corolair_install());
        $second = $this->assert_role_fully_provisioned('After second install.');

        $this->assertSame($first, $second, 'The role should be reused, not recreated.');
    }

    /**
     * A role left behind by an uninstall that did not finish must be repaired, not fatal.
     *
     * This is the regression test for create_role() failing against the unique index
     * on role.shortname, which previously left the plugin installed with no role and
     * no capabilities, reporting the reason only via debugging().
     *
     * @covers ::xmldb_local_corolair_install
     * @return void
     */
    public function test_install_converges_over_orphaned_role(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Simulate the orphan: a bare role with the plugin's shortname, no context
        // levels and no capabilities, exactly as a failed uninstall leaves it.
        $this->delete_provisioned_role();
        $orphanid = (int)create_role('Leftover', role_provisioner::SHORTNAME, '');
        $this->assertSame(0, $DB->count_records('role_capabilities', ['roleid' => $orphanid]));

        $this->assertTrue(xmldb_local_corolair_install());

        $roleid = $this->assert_role_fully_provisioned('After installing over an orphaned role.');
        $this->assertSame($orphanid, $roleid, 'The orphaned role should be reused.');
    }

    /**
     * Installing without a logged-in user must not fail.
     *
     * admin/cli/install.php runs with $USER->id === 0, where role_assign() throws.
     *
     * @covers ::xmldb_local_corolair_install
     * @return void
     */
    public function test_install_without_ambient_user(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setUser(null);
        $this->assertEquals(0, $USER->id);

        // Compare against the role the test site was built with rather than asserting
        // zero, so the test does not depend on how that site was provisioned.
        $existingrole = $DB->get_record('role', ['shortname' => role_provisioner::SHORTNAME], 'id');
        $before = $existingrole
            ? $DB->count_records('role_assignments', ['roleid' => (int)$existingrole->id])
            : 0;

        $this->assertTrue(xmldb_local_corolair_install());

        $roleid = $this->assert_role_fully_provisioned('After installing with no ambient user.');
        $this->assertSame(
            $before,
            $DB->count_records('role_assignments', ['roleid' => $roleid]),
            'No role assignment should be attempted without a real user.'
        );
    }

    /**
     * The recovery hook must rebuild a role lost to an interrupted install.
     *
     * Core saves the plugin version before running install.php, so a failure there
     * is not retried; it calls xmldb_<plugin>_install_recovery() on the next upgrade
     * instead. Recovery must not disturb consent state, which an administrator may
     * have recorded between the failed install and this call.
     *
     * @covers ::xmldb_local_corolair_install_recovery
     * @return void
     */
    public function test_install_recovery_rebuilds_role_without_touching_consent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Consent granted after the plugin registered but before recovery runs.
        set_config('setupconsented', 1, 'local_corolair');
        set_config('setupconsentedby', 42, 'local_corolair');
        $this->delete_provisioned_role();

        $this->assertTrue(xmldb_local_corolair_install_recovery());

        $this->assert_role_fully_provisioned('After install recovery.');
        $this->assertEquals(1, get_config('local_corolair', 'setupconsented'));
        $this->assertEquals(42, get_config('local_corolair', 'setupconsentedby'));
    }

    /**
     * Provisioning must work before core has registered the plugin's capabilities.
     *
     * Core runs db/install.php before update_capabilities(), so at install time the
     * local/corolair:* entries do not yet exist in mdl_capabilities. Anything that
     * validates against that table (assign_capability()) would throw on every fresh
     * install. The normal test site has the plugin fully installed, so this has to
     * be simulated explicitly -- otherwise the regression is invisible to phpunit.
     *
     * @covers \local_corolair\local\role_provisioner::ensure_role
     * @return void
     */
    public function test_ensure_role_before_capabilities_are_registered(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->delete_provisioned_role();
        $DB->delete_records_select('capabilities', $DB->sql_like('name', '?'), ['local/corolair:%']);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(
            $DB->record_exists_select('capabilities', $DB->sql_like('name', '?'), ['local/corolair:%']),
            'Precondition: the plugin capabilities must be unregistered for this test.'
        );

        role_provisioner::ensure_role();

        $this->assert_role_fully_provisioned('With capabilities not yet registered.');
    }

    /**
     * A role stripped of its capabilities, or with a grant revoked, must be repaired in place.
     *
     * @covers \local_corolair\local\role_provisioner::ensure_role
     * @return void
     */
    public function test_ensure_role_repairs_partial_state(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = role_provisioner::ensure_role();
        $systemcontextid = \context_system::instance()->id;

        // Two distinct kinds of damage: rows removed outright, and a surviving row
        // whose grant was flipped away from allow.
        $revoked = role_provisioner::CAPABILITIES[0];
        $DB->set_field(
            'role_capabilities',
            'permission',
            CAP_PREVENT,
            ['roleid' => $roleid, 'contextid' => $systemcontextid, 'capability' => $revoked]
        );
        $DB->delete_records_select(
            'role_capabilities',
            'roleid = ? AND capability <> ?',
            [$roleid, $revoked]
        );
        $DB->delete_records('role_context_levels', ['roleid' => $roleid]);

        $repaired = role_provisioner::ensure_role();

        $this->assertSame($roleid, $repaired);
        $this->assert_role_fully_provisioned('After repairing a stripped role.');
    }

    /**
     * Installing does not provision the privileged service identity.
     *
     * Three reasons, and each one alone would be enough. Two of the account's capabilities
     * belong to this plugin, and core registers those only after db/install.php returns, so
     * assign_capability() would throw. Creating a user fires user_created into every other
     * component's observers, mid-install. And a site that installs the plugin but never
     * registers with Raison has nothing for such an account to own, so carrying one would be
     * privilege with no purpose.
     *
     * @covers ::xmldb_local_corolair_install
     * @return void
     */
    public function test_install_does_not_provision_the_service_account(): void {
        global $DB;

        $this->resetAfterTest();

        $DB->delete_records('user', ['username' => service_account_provisioner::USERNAME]);
        unset_config('serviceaccountid', 'local_corolair');
        unset_config('serviceroleid', 'local_corolair');

        xmldb_local_corolair_install();

        $this->assertSame(0, $DB->count_records('user', [
            'username' => service_account_provisioner::USERNAME,
        ]));
        $this->assertSame(0, $DB->count_records('role', [
            'shortname' => service_account_provisioner::ROLE_SHORTNAME,
        ]));
        $this->assertFalse(get_config('local_corolair', 'serviceaccountid'));
    }
}
