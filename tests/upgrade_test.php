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

use local_corolair\local\placement_registry;
use local_corolair\local\role_provisioner;
use local_corolair\local\service_account_provisioner;
use local_corolair\local\upgrade_migrator;
use local_corolair\local\webservice_token_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// Moodle loads upgradelib.php during a real upgrade but not for a PHPUnit run, and
// upgrade_plugin_savepoint() lives there.
require_once($CFG->libdir . '/upgradelib.php');
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

    /** Version recorded before the service was restricted to authorised users. */
    private const VERSION_BEFORE_SERVICE_ACCOUNT = 2026081400;

    /** Version recorded before the token overlap was cut from seven days to fifteen minutes. */
    private const VERSION_BEFORE_SHORT_OVERLAP = 2026090100;

    /** Version recorded before the plugin gained its placement ownership table. */
    private const VERSION_BEFORE_PLACEMENT_TABLE = 2026090101;

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

    /**
     * Every current token owner is authorised before core restricts the service.
     *
     * Ordering is the whole point of this step, and it is a hard property rather than a
     * race: core rewrites the service flags in external_update_descriptions(), which
     * upgrade_plugins() calls only after this function returns. So the rows written here
     * are already in place the instant restrictedusers flips to 1, and a live
     * administrator-owned token keeps working with no interruption.
     *
     * @covers ::local_corolair_authorise_existing_token_owners
     * @return void
     */
    public function test_upgrade_authorises_existing_token_owners(): void {
        global $DB;

        $this->resetAfterTest();

        $owner = (int)$this->getDataGenerator()->create_user()->id;
        $this->make_site_look_migratable($owner);
        set_config('setupconsentedby', $owner, 'local_corolair');
        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest']);

        $this->rewind_stored_version(self::VERSION_BEFORE_SERVICE_ACCOUNT);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SERVICE_ACCOUNT));

        $row = $DB->get_record('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $owner,
        ], '*', MUST_EXIST);
        // Core's two code paths compare this column in opposite directions, so any value
        // other than null breaks web-service calls while leaving file downloads working.
        $this->assertNull($row->validuntil);
        $this->assertSame($owner, (int)get_config('local_corolair', 'webservicetokenownerid'));
        $this->assertTrue((bool)get_config('local_corolair', 'serviceaccountmigrationpending'));
    }

    /**
     * Running the step twice does not create a second authorisation row.
     *
     * There is no unique index on (externalserviceid, userid), so nothing in the database
     * would stop a duplicate, and a duplicate makes core's UNION return the service twice.
     *
     * @covers ::local_corolair_authorise_existing_token_owners
     * @return void
     */
    public function test_upgrade_does_not_duplicate_authorised_rows(): void {
        global $DB;

        $this->resetAfterTest();

        $owner = (int)$this->getDataGenerator()->create_user()->id;
        $this->make_site_look_migratable($owner);
        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest']);

        local_corolair_authorise_existing_token_owners();
        local_corolair_authorise_existing_token_owners();

        $this->assertSame(1, $DB->count_records('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $owner,
        ]));
    }

    /**
     * The upgrade does not provision the service account.
     *
     * Provisioning is deferred to the scheduled task for two reasons that both bite here:
     * the read set includes this plugin's own capabilities, which core has not registered
     * at this point in the upgrade, and creating a user fires user_created into every other
     * component's observers mid-upgrade.
     *
     * @covers ::local_corolair_authorise_existing_token_owners
     * @return void
     */
    public function test_upgrade_does_not_create_the_service_account(): void {
        global $DB;

        $this->resetAfterTest();

        $owner = (int)$this->getDataGenerator()->create_user()->id;
        $this->make_site_look_migratable($owner);

        $this->rewind_stored_version(self::VERSION_BEFORE_SERVICE_ACCOUNT);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SERVICE_ACCOUNT));

        $this->assertSame(0, $DB->count_records('user', [
            'username' => service_account_provisioner::USERNAME,
        ]));
        $this->assertSame(0, $DB->count_records('role', [
            'shortname' => service_account_provisioner::ROLE_SHORTNAME,
        ]));
    }

    /**
     * A pending seven-day overlap is cut to the grace window on upgrade.
     *
     * Without this the credential the release exists to retire stays live for up to a week
     * after the upgrade that retired it, on exactly the sites that were mid-rotation.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_shortens_a_pending_overlap(): void {
        $this->resetAfterTest();

        $deadline = time() + (7 * DAYSECS);
        set_config('previouswebservicetokenid', 1234, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', $deadline, 'local_corolair');

        $this->rewind_stored_version(self::VERSION_BEFORE_SHORT_OVERLAP);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SHORT_OVERLAP));

        $this->assertLessThanOrEqual(
            time() + webservice_token_manager::PREVIOUS_TOKEN_GRACE,
            (int)get_config('local_corolair', 'previouswebservicetokenrevokeby')
        );
        $this->assertCount(
            1,
            \core\task\manager::get_adhoc_tasks(\local_corolair\task\revoke_previous_token_task::class),
            'The upgrade should queue the revocation rather than perform it inline.'
        );
    }

    /**
     * An overlap deadline already in the past is left where it is.
     *
     * The clamp lowers and never raises. Written as max() it would push an elapsed deadline
     * a quarter of an hour into the future and resurrect a token the hourly task was about
     * to collect -- a regression with no visible symptom.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_does_not_extend_an_elapsed_overlap(): void {
        $this->resetAfterTest();

        $elapsed = time() - DAYSECS;
        set_config('previouswebservicetokenid', 1234, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', $elapsed, 'local_corolair');

        $this->rewind_stored_version(self::VERSION_BEFORE_SHORT_OVERLAP);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SHORT_OVERLAP));

        $this->assertSame(
            $elapsed,
            (int)get_config('local_corolair', 'previouswebservicetokenrevokeby'),
            'An elapsed deadline must not be pushed forward.'
        );
    }

    /**
     * A site with no overlap pending queues nothing.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_queues_no_revocation_without_a_pending_overlap(): void {
        $this->resetAfterTest();

        $this->rewind_stored_version(self::VERSION_BEFORE_SHORT_OVERLAP);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_SHORT_OVERLAP));

        $this->assertEmpty(
            \core\task\manager::get_adhoc_tasks(\local_corolair\task\revoke_previous_token_task::class)
        );
    }

    /**
     * A site that never registered has nothing to authorise and records nothing.
     *
     * @covers ::local_corolair_authorise_existing_token_owners
     * @return void
     */
    public function test_upgrade_records_no_handover_on_an_unregistered_site(): void {
        global $DB;

        $this->resetAfterTest();

        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest']);
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);
        unset_config('setupconsentedby', 'local_corolair');
        unset_config('webservicetokenid', 'local_corolair');

        local_corolair_authorise_existing_token_owners();

        $this->assertSame(0, $DB->count_records('external_services_users', ['externalserviceid' => $serviceid]));
        $this->assertFalse(get_config('local_corolair', 'serviceaccountmigrationpending'));
    }

    /**
     * Upgrading an existing site creates the placement table.
     *
     * The test site is installed from db/install.xml, so the table is already present and the
     * upgrade step's table_exists() guard short-circuits -- which means create_table() is never
     * reached by any other test, despite being the only path a real upgrading site takes. The
     * table is therefore dropped here first, so this asserts what those sites actually run.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_creates_the_placement_table(): void {
        global $DB;

        $this->resetAfterTest();

        $dbman = $DB->get_manager();
        $table = new \xmldb_table(placement_registry::TABLE);
        $dbman->drop_table($table);
        $this->assertFalse($dbman->table_exists($table));

        $this->rewind_stored_version(self::VERSION_BEFORE_PLACEMENT_TABLE);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_PLACEMENT_TABLE));

        $this->assertTrue($dbman->table_exists($table));
        // Written and read back, so the columns the registry uses are proven to exist rather
        // than merely the table name.
        $DB->insert_record(placement_registry::TABLE, (object)[
            'ltiinstanceid' => 1,
            'courseid' => 2,
            'typeid' => 3,
            'timecreated' => time(),
        ]);
        $this->assertSame(1, $DB->count_records(placement_registry::TABLE, ['ltiinstanceid' => 1]));
    }

    /**
     * The upgrade step is safe to re-run against a site that already has the table.
     *
     * @covers ::xmldb_local_corolair_upgrade
     * @return void
     */
    public function test_upgrade_leaves_an_existing_placement_table_alone(): void {
        global $DB;

        $this->resetAfterTest();

        $DB->insert_record(placement_registry::TABLE, (object)[
            'ltiinstanceid' => 42,
            'courseid' => 7,
            'typeid' => 9,
            'timecreated' => time(),
        ]);

        $this->rewind_stored_version(self::VERSION_BEFORE_PLACEMENT_TABLE);
        $this->assertTrue(xmldb_local_corolair_upgrade(self::VERSION_BEFORE_PLACEMENT_TABLE));

        $this->assertSame(1, $DB->count_records(placement_registry::TABLE, ['ltiinstanceid' => 42]));
    }
}
