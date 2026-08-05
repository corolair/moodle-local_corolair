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
 * Tests for the local_corolair uninstall path.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\organization_deregistration;
use local_corolair\local\role_provisioner;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/corolair/db/install.php');
require_once($CFG->dirroot . '/local/corolair/db/uninstall.php');

/**
 * Verifies uninstall removes what core cannot, and never throws while doing it.
 *
 * Core calls xmldb_local_corolair_uninstall() unguarded and performs the rest of the
 * cleanup afterwards, so an exception escaping this path would strand tokens, config
 * and pending tasks on the site.
 */
final class uninstall_test extends \advanced_testcase {
    /**
     * Assert nothing of the plugin role survives.
     *
     * @param int|null $formerroleid Role ID before deletion, when known, so the
     *                               dependent-row checks can be scoped to it.
     * @return void
     */
    private function assert_role_fully_removed(?int $formerroleid = null): void {
        global $DB;

        $this->assertFalse(
            $DB->record_exists('role', ['shortname' => role_provisioner::SHORTNAME]),
            'The corolair role should be gone.'
        );
        if ($formerroleid === null) {
            return;
        }
        // Core's delete_role() clears all of these. The raw deletes the old code used
        // left role_names and role_allow_* behind.
        foreach (['role_context_levels', 'role_capabilities', 'role_assignments', 'role_names'] as $table) {
            $this->assertSame(
                0,
                $DB->count_records($table, ['roleid' => $formerroleid]),
                "{$table} still holds rows for the deleted role."
            );
        }
    }

    /**
     * Uninstalling must delete the role core cannot attribute to this plugin.
     *
     * @covers ::xmldb_local_corolair_uninstall
     * @return void
     */
    public function test_uninstall_removes_the_role(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $roleid = role_provisioner::ensure_role();
        $this->assertTrue($DB->record_exists('role', ['id' => $roleid]));

        $this->assertTrue(xmldb_local_corolair_uninstall());

        $this->assert_role_fully_removed($roleid);
    }

    /**
     * Uninstalling a site whose role is already gone must not fail.
     *
     * @covers ::xmldb_local_corolair_uninstall
     * @return void
     */
    public function test_uninstall_is_safe_when_role_already_gone(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        role_provisioner::remove_role();

        $this->assertTrue(xmldb_local_corolair_uninstall());
        $this->assert_role_fully_removed();
    }

    /**
     * remove_role() must converge, like ensure_role().
     *
     * @covers \local_corolair\local\role_provisioner::remove_role
     * @return void
     */
    public function test_remove_role_is_idempotent(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        role_provisioner::ensure_role();
        role_provisioner::remove_role();
        role_provisioner::remove_role();

        $this->assert_role_fully_removed();
    }

    /**
     * A site that never registered must not contact Raison during uninstall.
     *
     * Reading the raw setting would send the translated "no API key" placeholder as a
     * bearer token, producing a failed request and an alarming warning on a site that
     * was never connected.
     *
     * @covers ::local_corolair_uninstall_deregister
     * @return void
     */
    public function test_uninstall_makes_no_request_when_unregistered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');

        $sink = $this->redirectEvents();
        $this->assertTrue(xmldb_local_corolair_uninstall());
        $events = $sink->get_events();
        $sink->close();

        foreach ($events as $event) {
            $this->assertNotInstanceOf(
                \local_corolair\event\remote_request_completed::class,
                $event,
                'An unregistered site must not call Raison during uninstall.'
            );
        }
    }

    /**
     * Uninstalling then installing must land on the same state as a clean install.
     *
     * @covers ::xmldb_local_corolair_uninstall
     * @return void
     */
    public function test_uninstall_install_roundtrip(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(xmldb_local_corolair_uninstall());
        $this->assertTrue(xmldb_local_corolair_install());

        $roles = $DB->get_records('role', ['shortname' => role_provisioner::SHORTNAME], '', 'id');
        $this->assertCount(1, $roles);
        $roleid = (int)reset($roles)->id;

        $systemcontextid = \context_system::instance()->id;
        foreach (role_provisioner::CAPABILITIES as $capability) {
            $this->assertTrue($DB->record_exists('role_capabilities', [
                'roleid' => $roleid,
                'contextid' => $systemcontextid,
                'capability' => $capability,
                'permission' => CAP_ALLOW,
            ]), "Capability {$capability} missing after a round trip.");
        }
        $this->assertSame(2, $DB->count_records('role_context_levels', ['roleid' => $roleid]));
    }

    /**
     * Deregistration must make exactly the three attempts the disclosure promises.
     *
     * lang/en/local_corolair.php disclosureuninstall tells administrators the plugin
     * tries "up to three times", so the attempt count is part of what they consented to.
     * execute() takes injectable attempt and sleep callbacks, so this needs no network
     * and no real sleeping.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_deregistration_gives_up_after_three_attempts(): void {
        $this->resetAfterTest();

        $attempts = 0;
        $sleeps = [];
        $result = organization_deregistration::execute(
            'org_test.secret',
            'https://moodle.example.com',
            function () use (&$attempts): array {
                $attempts++;
                // Transport failure: retryable, so the loop runs to its limit.
                return ['response' => false, 'errno' => 7, 'httpstatus' => 0];
            },
            function (int $seconds) use (&$sleeps): void {
                $sleeps[] = $seconds;
            }
        );

        $this->assertFalse($result);
        $this->assertSame(3, $attempts);
        $this->assertSame([1, 2], $sleeps, 'Should back off between attempts, not after the last.');
    }

    /**
     * A confirmed disconnect must stop immediately.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_deregistration_stops_on_confirmation(): void {
        $this->resetAfterTest();

        $attempts = 0;
        $result = organization_deregistration::execute(
            'org_test.secret',
            'https://moodle.example.com',
            function () use (&$attempts): array {
                $attempts++;
                return [
                    'response' => json_encode(['status' => 'disconnected']),
                    'errno' => 0,
                    'httpstatus' => 200,
                ];
            },
            function (int $seconds): void {
                $this->fail('Should not sleep after a confirmed deregistration.');
            }
        );

        $this->assertTrue($result);
        $this->assertSame(1, $attempts);
    }

    /**
     * A rejected credential must fail fast rather than burn all three attempts.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_deregistration_does_not_retry_a_rejected_credential(): void {
        $this->resetAfterTest();

        $attempts = 0;
        $result = organization_deregistration::execute(
            'org_test.secret',
            'https://moodle.example.com',
            function () use (&$attempts): array {
                $attempts++;
                return ['response' => '{"error":"unauthorized"}', 'errno' => 0, 'httpstatus' => 401];
            },
            function (int $seconds): void {
                $this->fail('A 401 is not retryable.');
            }
        );

        $this->assertFalse($result);
        $this->assertSame(1, $attempts);
    }
}
