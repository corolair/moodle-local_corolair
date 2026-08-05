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
 * Tests that the upgrade path never blocks on a third party.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\role_provisioner;
use local_corolair\local\upgrade_migrator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/corolair/db/upgrade.php');

/**
 * Verifies the upgrade completes offline and defers everything that needs the network.
 *
 * Raison verifies credentials by calling back into Moodle, and web services are
 * unavailable while an upgrade runs, so no upgrade step may perform network I/O.
 */
final class upgrade_test extends \advanced_testcase {
    /** Version recorded before the plugin gained its convergent role provisioning. */
    private const VERSION_BEFORE_SELF_HEAL = 2026080306;

    /** Version recorded before the retired Raison update ping. */
    private const VERSION_BEFORE_UPDATE_PING = 2024100700;

    /**
     * Rewind the stored plugin version so savepoints can advance.
     *
     * upgrade_plugin_savepoint() throws downgrade_exception when the recorded version is
     * already at or beyond the savepoint, and the test site is installed at the current
     * version, so every upgrade test has to move it back first.
     *
     * @param int $version Version to pretend the site is upgrading from.
     * @return void
     */
    private function rewind_stored_version(int $version): void {
        set_config('version', $version, 'local_corolair');
    }

    /**
     * Return the version recorded for the plugin.
     *
     * @return int
     */
    private function stored_version(): int {
        return (int)get_config('local_corolair', 'version');
    }

    /**
     * An upgrade must complete with no API key configured.
     *
     * The retired step returned false without advancing its savepoint whenever the key
     * was missing or the Raison endpoint was unreachable. Moodle turns that into
     * upgrade_exception, so one plugin's unconfigured integration blocked the upgrade of
     * the whole site. Nothing in this path touches the network any more.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_completes_without_api_key(): void {
        $this->resetAfterTest();

        unset_config('apikey', 'local_corolair');
        $this->rewind_stored_version(self::VERSION_BEFORE_UPDATE_PING);

        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_UPDATE_PING));
        $this->assertGreaterThanOrEqual(2026080307, $this->stored_version());
    }

    /**
     * The same upgrade must complete when the key is still the translated placeholder.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_completes_with_placeholder_api_key(): void {
        $this->resetAfterTest();

        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
        $this->rewind_stored_version(self::VERSION_BEFORE_UPDATE_PING);

        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_UPDATE_PING));
        $this->assertGreaterThanOrEqual(2026080307, $this->stored_version());
    }

    /**
     * Upgrading must repair a role that went missing.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_self_heals_missing_role(): void {
        global $DB;

        $this->resetAfterTest();

        $role = $DB->get_record('role', ['shortname' => role_provisioner::SHORTNAME], 'id');
        if ($role) {
            delete_role((int)$role->id);
        }
        $this->assertFalse($DB->record_exists('role', ['shortname' => role_provisioner::SHORTNAME]));

        $this->rewind_stored_version(self::VERSION_BEFORE_SELF_HEAL);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SELF_HEAL));

        $roleid = $DB->get_field('role', 'id', ['shortname' => role_provisioner::SHORTNAME]);
        $this->assertNotEmpty($roleid);
        $systemcontextid = \context_system::instance()->id;
        foreach (role_provisioner::CAPABILITIES as $capability) {
            $this->assertTrue($DB->record_exists('role_capabilities', [
                'roleid' => $roleid,
                'contextid' => $systemcontextid,
                'capability' => $capability,
                'permission' => CAP_ALLOW,
            ]), "Capability {$capability} was not restored.");
        }
    }

    /**
     * Put the site in the state the credential migration cares about.
     *
     * @param int $tokenowner User the service token belongs to.
     * @return void
     */
    private function make_site_look_migratable(int $tokenowner): void {
        global $DB;

        set_config('apikey', 'org_test.legacysecret', 'local_corolair');
        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest']);
        $this->assertNotEmpty($serviceid, 'db/services.php should have created corolair_rest.');
        $DB->insert_record('external_tokens', (object)[
            'token' => str_repeat('a', 64),
            'privatetoken' => null,
            'tokentype' => 0,
            'userid' => $tokenowner,
            'externalserviceid' => $serviceid,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $tokenowner,
            'iprestriction' => null,
            'validuntil' => 0,
            'timecreated' => time(),
            'lastaccess' => null,
        ]);
    }

    /**
     * A site with no usable administrator must not take the whole upgrade down.
     *
     * resolve_admin_id() falls back to get_admin(), which always resolves on a normal
     * site, so the fallback is removed here by clearing $CFG->siteadmins.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_migration_records_blocked_instead_of_throwing(): void {
        global $CFG;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->make_site_look_migratable((int)$student->id);
        $CFG->siteadmins = '';

        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationblocked'));
        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationpending'));
    }

    /**
     * The whole upgrade must still succeed in that state.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_completes_when_migration_is_blocked(): void {
        global $CFG;

        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->make_site_look_migratable((int)$student->id);
        $CFG->siteadmins = '';

        $this->rewind_stored_version(self::VERSION_BEFORE_UPDATE_PING);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_UPDATE_PING));
        $this->assertGreaterThanOrEqual(2026080307, $this->stored_version());
        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationblocked'));
    }

    /**
     * Once an administrator exists, the hourly retry queues the deferred migration.
     *
     * @covers \local_corolair\local\upgrade_migrator::retry_if_blocked
     * @return void
     */
    public function test_retry_if_blocked_queues_once_an_admin_exists(): void {
        global $DB;

        $this->resetAfterTest();

        $admin = get_admin();
        $this->make_site_look_migratable((int)$admin->id);
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');

        upgrade_migrator::retry_if_blocked();

        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationblocked'));
        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertTrue($DB->record_exists('task_adhoc', [
            'classname' => '\local_corolair\task\migrate_legacy_credentials_task',
        ]));
    }

    /**
     * A blocked flag must clear itself once there is nothing left to migrate.
     *
     * Otherwise the settings warning would stay on screen forever.
     *
     * @covers \local_corolair\local\upgrade_migrator::retry_if_blocked
     * @return void
     */
    public function test_retry_if_blocked_clears_when_nothing_to_migrate(): void {
        $this->resetAfterTest();

        unset_config('apikey', 'local_corolair');
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');

        upgrade_migrator::retry_if_blocked();

        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationblocked'));
    }
}
