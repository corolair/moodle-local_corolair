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
 * Tests for the one-time legacy credential migration.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\integration_disclosure;
use local_corolair\local\service_account_provisioner;
use local_corolair\local\upgrade_migrator;

/**
 * Verifies which installations are migrated, and that retries stay idempotent.
 *
 * Installations from before 1.9.0 hold a non-expiring web-service token and an API key
 * that was delivered to the browser. Deciding whether a given site is one of those is
 * the whole job of migrate_if_required(), and it runs inside db/upgrade.php, where a
 * wrong answer either leaves exposed credentials in place or takes the site upgrade
 * down. Neither this class's decision logic nor the local half of run() touches the
 * network, so both are covered here; the remote replacement itself is not.
 */
final class upgrade_migrator_test extends \advanced_testcase {
    /** Shortname of the service the plugin installs. */
    private const SERVICE_SHORTNAME = 'corolair_rest';

    /**
     * Return the installed external service ID.
     *
     * @return int
     */
    private function service_id(): int {
        global $DB;

        $id = (int)$DB->get_field('external_services', 'id', ['shortname' => self::SERVICE_SHORTNAME]);
        $this->assertGreaterThan(0, $id, 'db/services.php should have created the corolair_rest service.');
        return $id;
    }

    /**
     * Insert a service token, optionally without an expiry as pre-1.9.0 releases did.
     *
     * @param int $userid Token owner.
     * @param int $validuntil Expiry timestamp, or 0 for a non-expiring legacy token.
     * @return \stdClass The inserted token record.
     */
    private function add_token(int $userid, int $validuntil = 0): \stdClass {
        global $DB;

        $token = (object)[
            'token' => bin2hex(random_bytes(32)),
            'privatetoken' => null,
            'tokentype' => 0,
            'userid' => $userid,
            'externalserviceid' => $this->service_id(),
            'contextid' => \context_system::instance()->id,
            'creatorid' => $userid,
            'iprestriction' => null,
            'validuntil' => $validuntil,
            'timecreated' => time(),
            'lastaccess' => null,
        ];
        $token->id = $DB->insert_record('external_tokens', $token);
        return $token;
    }

    /**
     * Put the site in the state a connected pre-1.9.0 installation is in.
     *
     * @param int|null $tokenowner Token owner; defaults to the site administrator.
     * @return \stdClass The legacy token.
     */
    private function make_legacy_installation(?int $tokenowner = null): \stdClass {
        global $DB;

        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        set_config('apikey', 'org_instance.inheritedsecret', 'local_corolair');
        return $this->add_token($tokenowner ?? (int)get_admin()->id);
    }

    /**
     * Return the queued migration tasks.
     *
     * @return \core\task\adhoc_task[]
     */
    private function queued_migrations(): array {
        return \core\task\manager::get_adhoc_tasks('\local_corolair\task\migrate_legacy_credentials_task');
    }

    /**
     * Assert the site was left with nothing to migrate.
     *
     * @param string $message Context added to failure output.
     * @return void
     */
    private function assert_nothing_scheduled(string $message): void {
        $this->assertFalse(
            get_config('local_corolair', 'legacycredentialmigrationpending'),
            "Migration was scheduled unnecessarily. {$message}"
        );
        $this->assertFalse(
            get_config('local_corolair', 'legacycredentialmigrationblocked'),
            "A stale blocked flag would keep warning the administrator. {$message}"
        );
        $this->assertCount(0, $this->queued_migrations(), $message);
    }

    /**
     * An unregistered site has no credentials worth rotating.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_site_without_an_api_key_is_not_migrated(): void {
        $this->resetAfterTest();

        unset_config('apikey', 'local_corolair');
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assert_nothing_scheduled('A site with no API key has nothing to migrate.');
    }

    /**
     * A placeholder API key is not a credential either.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_site_with_a_placeholder_api_key_is_not_migrated(): void {
        $this->resetAfterTest();

        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assert_nothing_scheduled('The placeholder must not be treated as an inherited key.');
    }

    /**
     * A site whose external service is gone has nothing to rotate.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_site_without_the_service_is_not_migrated(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('apikey', 'org_instance.inheritedsecret', 'local_corolair');
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');
        $DB->set_field('external_services', 'shortname', 'corolair_rest_renamed', [
            'shortname' => self::SERVICE_SHORTNAME,
        ]);

        upgrade_migrator::migrate_if_required();

        $this->assert_nothing_scheduled('Without the service there is no token to replace.');
    }

    /**
     * A site with an API key but no service token has nothing to rotate.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_site_without_a_token_is_not_migrated(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('apikey', 'org_instance.inheritedsecret', 'local_corolair');
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);

        upgrade_migrator::migrate_if_required();

        $this->assert_nothing_scheduled('There is no inherited token to invalidate.');
    }

    /**
     * A legacy installation is grandfathered, marked pending, and queued exactly once.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_legacy_installation_is_scheduled_once(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_legacy_installation($adminid);

        upgrade_migrator::migrate_if_required();
        // A second upgrade step calls this again; it must not queue a second task.
        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationblocked'));

        $tasks = $this->queued_migrations();
        $this->assertCount(1, $tasks);
        $this->assertSame($adminid, (int)reset($tasks)->get_custom_data()->adminid);
    }

    /**
     * Scheduling grandfathers the existing integration as consented.
     *
     * The token lifecycle refuses to operate without a consent record, and a site
     * upgrading from before that record existed has none. Migrating without writing one
     * would replace the credentials and then be unable to maintain them.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_existing_integration_is_grandfathered(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_legacy_installation($adminid);

        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'setupconsented'));
        $this->assertEquals(0, get_config('local_corolair', 'setupconsentrequired'));
        $this->assertEquals($adminid, (int)get_config('local_corolair', 'setupconsentedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupconsentedat'));
        $this->assertSame(
            integration_disclosure::VERSION,
            get_config('local_corolair', 'setupdisclosureversion')
        );
        $this->assertEquals($adminid, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupdisclosureacknowledgedat'));
    }

    /**
     * Grandfathering must not overwrite a consent an administrator actually gave.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_grandfathering_preserves_a_real_consent_record(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_legacy_installation($adminid);
        set_config('setupconsentedby', $adminid, 'local_corolair');
        set_config('setupconsentedat', 1234567890, 'local_corolair');
        set_config('setupdisclosureacknowledgedby', $adminid, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', 1234567890, 'local_corolair');
        set_config('setupdisclosureversion', 'the-version-they-actually-read', 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1234567890, (int)get_config('local_corolair', 'setupconsentedat'));
        $this->assertEquals(1234567890, (int)get_config('local_corolair', 'setupdisclosureacknowledgedat'));
        $this->assertSame(
            'the-version-they-actually-read',
            get_config('local_corolair', 'setupdisclosureversion')
        );
    }

    /**
     * The recorded consenting administrator owns the migration when they still can.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_recorded_administrator_owns_the_migration(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->make_legacy_installation((int)$student->id);
        $adminid = (int)get_admin()->id;
        set_config('setupconsentedby', $adminid, 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $tasks = $this->queued_migrations();
        $this->assertCount(1, $tasks);
        $this->assertSame($adminid, (int)reset($tasks)->get_custom_data()->adminid);
    }

    /**
     * A token owned by someone who cannot administer the site is not trusted.
     *
     * The replacement token is minted with the owner's capabilities, so inheriting a
     * demoted user's ownership would quietly shrink what the integration can read.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_unusable_token_owner_falls_back_to_the_site_administrator(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user();
        $this->make_legacy_installation((int)$student->id);

        upgrade_migrator::migrate_if_required();

        $tasks = $this->queued_migrations();
        $this->assertCount(1, $tasks);
        $this->assertSame((int)get_admin()->id, (int)reset($tasks)->get_custom_data()->adminid);
    }

    /**
     * A completed migration with a live recorded token is left alone.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_completed_migration_with_a_live_token_is_skipped(): void {
        $this->resetAfterTest();

        $token = $this->make_legacy_installation();
        $token->validuntil = time() + (10 * DAYSECS);
        $this->update_token($token);
        set_config('webservicetokenid', (int)$token->id, 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assert_nothing_scheduled('A completed migration with a live token needs no work.');
    }

    /**
     * A completed migration whose token has expired is migrated again.
     *
     * An expired token means the site is disconnected, and an expired credential is not
     * evidence that the inherited one was ever replaced.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_completed_migration_with_an_expired_token_is_redone(): void {
        $this->resetAfterTest();

        $token = $this->make_legacy_installation();
        $token->validuntil = time() - DAYSECS;
        $this->update_token($token);
        set_config('webservicetokenid', (int)$token->id, 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * A completion record without a recorded token identifier is not trusted either.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @return void
     */
    public function test_completed_migration_without_a_recorded_token_is_redone(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        unset_config('webservicetokenid', 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');

        upgrade_migrator::migrate_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * The compatibility alias behaves exactly like the current entry point.
     *
     * Intermediate plugin versions call this name from their own upgrade steps.
     *
     * @covers \local_corolair\local\upgrade_migrator::schedule_if_required
     * @return void
     */
    public function test_schedule_if_required_is_an_alias(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_legacy_installation($adminid);

        upgrade_migrator::schedule_if_required();

        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * The hourly retry does nothing when no migration was ever blocked.
     *
     * @covers \local_corolair\local\upgrade_migrator::retry_if_blocked
     * @return void
     */
    public function test_retry_does_nothing_when_not_blocked(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        unset_config('legacycredentialmigrationblocked', 'local_corolair');

        upgrade_migrator::retry_if_blocked();

        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertCount(0, $this->queued_migrations());
    }

    /**
     * run() is a no-op unless a migration is actually pending.
     *
     * The task can be retried by Moodle after the migration already succeeded; minting
     * another token then would leave a credential nobody tracks.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_run_does_nothing_when_no_migration_is_pending(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_legacy_installation();
        unset_config('legacycredentialmigrationpending', 'local_corolair');
        $before = $DB->count_records('external_tokens', ['externalserviceid' => $this->service_id()]);

        upgrade_migrator::run((int)get_admin()->id);

        $this->assertSame(
            $before,
            $DB->count_records('external_tokens', ['externalserviceid' => $this->service_id()])
        );
        $this->assertFalse(get_config('local_corolair', 'legacymigrationtokenid'));
    }

    /**
     * run() refuses to continue if the inherited key disappeared mid-flight.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_run_requires_the_inherited_api_key(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        unset_config('apikey', 'local_corolair');

        try {
            upgrade_migrator::run((int)get_admin()->id);
            $this->fail('The migration cannot proceed without the credential it replaces.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('noapikey', $exception->errorcode);
        }
    }

    /**
     * Inherited keys that are not "instance.secret" cannot produce a replacement.
     *
     * @return array[] Data sets of [stored API key].
     */
    public static function malformed_api_key_provider(): array {
        return [
            'no separator' => ['inheritedsecretwithoutinstance'],
            'empty instance' => ['.inheritedsecret'],
        ];
    }

    /**
     * A malformed inherited key fails safely, without touching the stored credential.
     *
     * @dataProvider malformed_api_key_provider
     * @covers \local_corolair\local\upgrade_migrator::run
     * @param string $apikey Inherited API key.
     * @return void
     */
    public function test_run_rejects_a_malformed_api_key(string $apikey): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        set_config('apikey', $apikey, 'local_corolair');

        try {
            upgrade_migrator::run((int)get_admin()->id);
            $this->fail('A malformed inherited key should not be migrated.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
        }
        $this->assertSame(
            $apikey,
            get_config('local_corolair', 'apikey'),
            'A failed migration must leave the inherited credential in place.'
        );
        $this->assertEquals(1, get_config('local_corolair', 'legacycredentialmigrationpending'));
    }

    /**
     * Retrying reuses the candidate token, migration ID and replacement secret.
     *
     * Moodle retries the ad-hoc task until it succeeds. Minting fresh values each time
     * would orphan a token per attempt and defeat the backend's idempotency key.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_retries_reuse_the_pending_migration_state(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        // Fails after the local state is built, which is exactly the retry scenario.
        set_config('apikey', 'malformedinheritedkey', 'local_corolair');
        $adminid = (int)get_admin()->id;

        $observed = [];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                upgrade_migrator::run($adminid);
            } catch (\moodle_exception $exception) {
                $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
            }
            $observed[] = [
                'tokenid' => (int)get_config('local_corolair', 'legacymigrationtokenid'),
                'migrationid' => (string)get_config('local_corolair', 'legacycredentialmigrationid'),
                'secret' => (string)get_config('local_corolair', 'legacyreplacementapikeysecret'),
            ];
        }

        $this->assertGreaterThan(0, $observed[0]['tokenid']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $observed[0]['migrationid'],
            'The migration ID should be a version-4 UUID.'
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $observed[0]['secret']);
        $this->assertSame($observed[0], $observed[1]);
        $this->assertSame($observed[0], $observed[2]);

        // One inherited token plus exactly one candidate, however many attempts ran.
        $this->assertSame(2, $DB->count_records('external_tokens', ['externalserviceid' => $this->service_id()]));
    }

    /**
     * A candidate token that expired between attempts is replaced, not reused.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_expired_candidate_token_is_replaced(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        set_config('apikey', 'malformedinheritedkey', 'local_corolair');
        $adminid = (int)get_admin()->id;

        $stale = $this->add_token($adminid, time() - DAYSECS);
        set_config('legacymigrationtokenid', (int)$stale->id, 'local_corolair');

        try {
            upgrade_migrator::run($adminid);
        } catch (\moodle_exception $exception) {
            $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
        }

        $this->assertFalse(
            $DB->record_exists('external_tokens', ['id' => (int)$stale->id]),
            'An expired candidate must be discarded rather than left behind.'
        );
        $candidateid = (int)get_config('local_corolair', 'legacymigrationtokenid');
        $this->assertNotEquals((int)$stale->id, $candidateid);
        $this->assertTrue($DB->record_exists('external_tokens', ['id' => $candidateid]));
    }

    /**
     * The migration always mints a normally expiring token, whatever the rotation policy.
     *
     * The migration endpoint requires a bounded expiration and recognises a replayed attempt
     * by comparing the supplied expiration against the stored one -- a comparison that cannot
     * succeed with no expiration on either side. Retiring an exposed legacy credential must
     * not depend on any of that, so it opts out of the policy and lets the next maintenance
     * run converge the token instead.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_migration_token_ignores_the_rotation_policy(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        set_config('disabletokenrotation', 1, 'local_corolair');
        // Fails before any network call, once the local state has been built.
        set_config('apikey', 'malformedinheritedkey', 'local_corolair');

        try {
            upgrade_migrator::run((int)get_admin()->id);
        } catch (\moodle_exception $exception) {
            $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
        }

        $candidateid = (int)get_config('local_corolair', 'legacymigrationtokenid');
        $this->assertGreaterThan(0, $candidateid);
        $candidate = $DB->get_record('external_tokens', ['id' => $candidateid], '*', MUST_EXIST);
        $this->assertFalse(
            \local_corolair\local\webservice_token_manager::is_non_expiring($candidate),
            'The migration must not mint a non-expiring token.'
        );
        $this->assertLessThanOrEqual(
            time() + \local_corolair\local\webservice_token_manager::TOKEN_LIFETIME,
            (int)$candidate->validuntil
        );
    }

    /**
     * Scheduling the replacement puts a deadline on the inherited token.
     *
     * Without this the inherited credential lives exactly as long as it takes Raison to
     * confirm the swap, which on an unreachable backend is forever.
     *
     * @covers \local_corolair\local\upgrade_migrator::migrate_if_required
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_scheduling_bounds_the_inherited_token(): void {
        global $DB;

        $this->resetAfterTest();

        $legacy = $this->make_legacy_installation();
        $this->assertSame(0, (int)$legacy->validuntil);

        $before = time();
        upgrade_migrator::migrate_if_required();
        $after = time();

        $validuntil = (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $legacy->id]);
        $this->assertGreaterThanOrEqual($before + upgrade_migrator::LEGACY_TOKEN_GRACE, $validuntil);
        $this->assertLessThanOrEqual($after + upgrade_migrator::LEGACY_TOKEN_GRACE, $validuntil);
    }

    /**
     * A bounded token is never given more time by a later pass.
     *
     * migrate_if_required() runs from several places, so a rule that re-stamped an already
     * bounded token would push the deadline out on every call and never expire anything.
     *
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_a_bounded_token_is_never_given_more_time(): void {
        global $DB;

        $this->resetAfterTest();

        $legacy = $this->make_legacy_installation();
        upgrade_migrator::migrate_if_required();

        // Bring the deadline forward, then confirm a second pass cannot push it back out.
        $imminent = time() + MINSECS;
        $DB->set_field('external_tokens', 'validuntil', $imminent, ['id' => $legacy->id]);

        upgrade_migrator::migrate_if_required();

        $this->assertSame(
            $imminent,
            (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $legacy->id]),
            'Re-running the scheduler must not extend a token that is already bounded.'
        );
    }

    /**
     * A token that already expires is not an inherited credential and is left alone.
     *
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_a_token_that_already_expires_is_left_alone(): void {
        global $DB;

        $this->resetAfterTest();

        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        set_config('apikey', 'org_instance.inheritedsecret', 'local_corolair');
        $expiry = time() + DAYSECS;
        $token = $this->add_token((int)get_admin()->id, $expiry);

        upgrade_migrator::migrate_if_required();

        $this->assertSame(
            $expiry,
            (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $token->id])
        );
    }

    /**
     * Bounding inherited tokens must not cut down the replacement this class just minted.
     *
     * The candidate is a fifteen-day token on the same service, so any rule broader than
     * "no expiration at all" -- notably "expires later than the deadline" -- would crush it
     * to the grace period the next time anything called migrate_if_required().
     *
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_the_migration_candidate_survives_a_later_scheduling_pass(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        // Fails once the candidate has been minted, before any network call.
        set_config('apikey', 'malformedinheritedkey', 'local_corolair');
        try {
            upgrade_migrator::run((int)get_admin()->id);
        } catch (\moodle_exception $exception) {
            $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
        }
        $candidateid = (int)get_config('local_corolair', 'legacymigrationtokenid');
        $this->assertGreaterThan(0, $candidateid);

        upgrade_migrator::migrate_if_required();

        $this->assertGreaterThan(
            time() + upgrade_migrator::LEGACY_TOKEN_GRACE,
            (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $candidateid]),
            'The replacement token must keep its full lifetime.'
        );
    }

    /**
     * The upgrade entry point bounds an inherited token without queueing anything.
     *
     * This is what db/upgrade.php calls, so that a site already running 1.9.x with a
     * migration that never confirmed stops carrying a live pre-1.9 credential.
     *
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_bounding_alone_schedules_nothing(): void {
        global $DB;

        $this->resetAfterTest();

        $legacy = $this->make_legacy_installation();

        $before = time();
        upgrade_migrator::bound_legacy_token_lifetime();
        $after = time();

        $validuntil = (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $legacy->id]);
        $this->assertGreaterThanOrEqual($before + upgrade_migrator::LEGACY_TOKEN_GRACE, $validuntil);
        $this->assertLessThanOrEqual($after + upgrade_migrator::LEGACY_TOKEN_GRACE, $validuntil);
        $this->assertCount(0, $this->queued_migrations());
    }

    /**
     * Bounding is inert on a site whose service is gone, since it runs during upgrade.
     *
     * @covers \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime
     * @return void
     */
    public function test_bounding_is_inert_without_the_service(): void {
        global $DB;

        $this->resetAfterTest();

        $legacy = $this->make_legacy_installation();
        $DB->set_field('external_services', 'shortname', 'corolair_rest_renamed', [
            'shortname' => self::SERVICE_SHORTNAME,
        ]);

        upgrade_migrator::bound_legacy_token_lifetime();

        $this->assertSame(0, (int)$DB->get_field('external_tokens', 'validuntil', ['id' => $legacy->id]));
    }

    /**
     * A migration left pending with no ad-hoc task to run it is queued again.
     *
     * Nothing else recovers this: retry_if_blocked() looks only at the blocked flag, and
     * maintain() stands down on the pending one, so the lifecycle would stay frozen.
     *
     * @covers \local_corolair\local\upgrade_migrator::requeue_if_stalled
     * @return void
     */
    public function test_a_stalled_migration_is_requeued(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        $this->assertCount(0, $this->queued_migrations());

        upgrade_migrator::requeue_if_stalled();

        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * A migration that still has its task is left for core to retry.
     *
     * @covers \local_corolair\local\upgrade_migrator::requeue_if_stalled
     * @return void
     */
    public function test_a_queued_migration_is_not_requeued(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        upgrade_migrator::migrate_if_required();
        $this->assertCount(1, $this->queued_migrations());

        upgrade_migrator::requeue_if_stalled();

        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * A site with no pending migration is not given one.
     *
     * @covers \local_corolair\local\upgrade_migrator::requeue_if_stalled
     * @return void
     */
    public function test_requeue_ignores_a_site_with_nothing_pending(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        unset_config('legacycredentialmigrationpending', 'local_corolair');

        upgrade_migrator::requeue_if_stalled();

        $this->assertCount(0, $this->queued_migrations());
    }

    /**
     * A completed migration that never cleared its flag is settled rather than repeated.
     *
     * run() records the completion timestamp several statements before it clears the pending
     * flag. A process dying in between leaves a migrated site flagged as pending forever,
     * which freezes token maintenance permanently.
     *
     * @covers \local_corolair\local\upgrade_migrator::requeue_if_stalled
     * @return void
     */
    public function test_a_completed_migration_clears_a_stranded_pending_flag(): void {
        global $DB;

        $this->resetAfterTest();

        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        set_config('apikey', 'org_instance.replacementsecret', 'local_corolair');
        $active = $this->add_token((int)get_admin()->id, time() + DAYSECS);
        set_config('webservicetokenid', (int)$active->id, 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time() - MINSECS, 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        upgrade_migrator::requeue_if_stalled();

        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationpending'));
        $this->assertCount(0, $this->queued_migrations());
    }

    /**
     * A recorded completion with no live token behind it is not treated as complete.
     *
     * @covers \local_corolair\local\upgrade_migrator::requeue_if_stalled
     * @return void
     */
    public function test_a_completion_without_a_live_token_is_requeued(): void {
        $this->resetAfterTest();

        $this->make_legacy_installation();
        set_config('legacycredentialmigrationcompletedat', time() - MINSECS, 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        unset_config('webservicetokenid', 'local_corolair');

        upgrade_migrator::requeue_if_stalled();

        $this->assertCount(1, $this->queued_migrations());
    }

    /**
     * Persist a modified token record.
     *
     * @param \stdClass $token Token record to write back.
     * @return void
     */
    private function update_token(\stdClass $token): void {
        global $DB;

        $DB->update_record('external_tokens', $token);
    }

    /**
     * The migration authorises its own token owner before minting.
     *
     * The owner here is resolved separately from the ordinary token owner, and the fallback
     * chain ends at get_admin() -- a user who may own no token at all, and who therefore
     * received no authorisation from the upgrade step. Under a service restricted to
     * authorised users, a token minted for such a user is refused the first time it is used.
     * That would be a bad failure to have precisely here: this path exists to retire a
     * credential that is already exposed.
     *
     * @covers \local_corolair\local\upgrade_migrator::run
     * @return void
     */
    public function test_migration_authorises_its_token_owner(): void {
        global $DB;

        $this->resetAfterTest();

        $owner = (int)$this->getDataGenerator()->create_user()->id;
        $this->make_legacy_installation($owner);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        $serviceid = $this->service_id();
        $DB->delete_records('external_services_users', ['externalserviceid' => $serviceid]);

        try {
            upgrade_migrator::run($owner);
        } catch (\Throwable $exception) {
            // Expected: the remote replacement cannot succeed in a test run.
            $this->assertNotEmpty($exception->getMessage());
        }

        $row = $DB->get_record('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $owner,
        ], '*', MUST_EXIST);
        $this->assertNull($row->validuntil);
    }
}
