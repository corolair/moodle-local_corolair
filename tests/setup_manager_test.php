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
 * Tests for the consent-gated setup manager.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\integration_disclosure;
use local_corolair\local\setup_manager;
use local_corolair\local\webservice_token_manager;

/**
 * Verifies that nothing site-wide changes without an authorized, informed administrator.
 *
 * activate() turns on web services and the REST protocol for the whole site. That is a
 * real expansion of the attack surface, so it is gated three ways: the caller must be a
 * site administrator, must have acknowledged the current disclosure, and must have
 * separately approved enabling anything that is still off.
 */
final class setup_manager_test extends \advanced_testcase {
    /**
     * Set the site web-service configuration, in both the database and $CFG.
     *
     * setup_manager reads $CFG directly, and set_config() alone does not refresh the
     * already-loaded global, so both have to move together.
     *
     * @param int $enabled Value for enablewebservices.
     * @param string $protocols Value for webserviceprotocols.
     * @return void
     */
    private function set_webservice_config(int $enabled, string $protocols): void {
        global $CFG;

        set_config('enablewebservices', $enabled);
        set_config('webserviceprotocols', $protocols);
        $CFG->enablewebservices = $enabled;
        $CFG->webserviceprotocols = $protocols;
    }

    /**
     * Count the queued registration tasks.
     *
     * @return int
     */
    private function queued_registration_tasks(): int {
        global $DB;

        return $DB->count_records('task_adhoc', [
            'classname' => '\local_corolair\task\setup_corolair_connection_task',
        ]);
    }

    /**
     * Activation enables the missing requirements and keeps the protocols already in use.
     *
     * Replacing the protocol list rather than appending to it would silently disable
     * SOAP for every other integration on the site.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_enables_requirements_and_preserves_protocols(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_webservice_config(0, 'soap');

        setup_manager::acknowledge_disclosure((int)$USER->id);
        $queued = setup_manager::activate((int)$USER->id, true);

        $this->assertTrue($queued);
        $this->assertEquals(1, get_config('local_corolair', 'setupconsented'));
        $this->assertEquals(1, get_config('local_corolair', 'setupconsentrequired'));
        $this->assertEquals((int)$USER->id, (int)get_config('local_corolair', 'setupconsentedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupconsentedat'));
        $this->assertEquals(0, get_config('local_corolair', 'setupcompleted'));
        $this->assertTrue(setup_manager::webservices_enabled());
        $this->assertSame(['soap', 'rest'], setup_manager::get_enabled_protocols());
        $this->assertSame(1, $this->queued_registration_tasks());
    }

    /**
     * Activating twice does not duplicate REST or queue a second registration.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_is_idempotent_while_a_task_is_pending(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_webservice_config(1, 'rest,soap');

        setup_manager::acknowledge_disclosure((int)$USER->id);
        setup_manager::activate((int)$USER->id);
        setup_manager::activate((int)$USER->id);

        $this->assertEquals(0, get_config('local_corolair', 'setupconsentrequired'));
        $this->assertSame(['rest', 'soap'], setup_manager::get_enabled_protocols());
        $this->assertSame(1, $this->queued_registration_tasks());
    }

    /**
     * A user without moodle/site:config cannot activate the integration.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_requires_site_configuration_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        setup_manager::activate((int)$user->id);
    }

    /**
     * A deleted administrator cannot activate the integration.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_rejects_a_deleted_administrator(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        setup_manager::activate(-1);
    }

    /**
     * Enabling missing site requirements needs its own explicit approval.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_rejects_missing_enablement_consent(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_webservice_config(0, '');

        setup_manager::acknowledge_disclosure((int)$USER->id);

        try {
            setup_manager::activate((int)$USER->id);
            $this->fail('Site settings should not change without enablement consent.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('setupconsentmissing', $exception->errorcode);
        }
        $this->assertFalse(setup_manager::webservices_enabled());
        $this->assertSame(0, $this->queued_registration_tasks());
    }

    /**
     * Activation cannot bypass the disclosure.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_requires_disclosure_acknowledgment(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_webservice_config(1, 'rest');

        try {
            setup_manager::activate((int)$USER->id);
            $this->fail('Setup should not run without an acknowledged disclosure.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('disclosuremissing', $exception->errorcode);
        }
        $this->assertEmpty(get_config('local_corolair', 'setupconsentedby'));
        $this->assertSame(0, $this->queued_registration_tasks());
    }

    /**
     * A failed activation leaves no consent record and no queued work behind.
     *
     * The whole body runs inside a delegated transaction precisely so a rejection cannot
     * leave the site half-configured.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_rejected_activation_records_nothing(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_webservice_config(0, 'soap');
        setup_manager::acknowledge_disclosure((int)$USER->id);

        $this->expectException(\moodle_exception::class);
        try {
            setup_manager::activate((int)$USER->id, false);
        } finally {
            $this->assertEmpty(get_config('local_corolair', 'setupconsentedat'));
            $this->assertSame(['soap'], setup_manager::get_enabled_protocols());
            $this->assertSame(0, $this->queued_registration_tasks());
        }
    }

    /**
     * A stale acknowledgment does not satisfy the current disclosure.
     *
     * @covers \local_corolair\local\setup_manager::disclosure_acknowledged
     * @return void
     */
    public function test_stale_disclosure_acknowledgment_is_rejected(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('setupdisclosureversion', 'an-older-version', 'local_corolair');
        set_config('setupdisclosureacknowledgedby', (int)$USER->id, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', time(), 'local_corolair');

        $this->assertFalse(setup_manager::disclosure_acknowledged((int)$USER->id));
    }

    /**
     * An acknowledgment belongs to the administrator who made it, and is audited.
     *
     * @covers \local_corolair\local\setup_manager::acknowledge_disclosure
     * @return void
     */
    public function test_acknowledgment_is_administrator_bound_and_audited(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        setup_manager::acknowledge_disclosure((int)$USER->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(
            integration_disclosure::VERSION,
            get_config('local_corolair', 'setupdisclosureversion')
        );
        $this->assertEquals((int)$USER->id, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupdisclosureacknowledgedat'));
        $this->assertTrue(setup_manager::disclosure_acknowledged((int)$USER->id));
        $this->assertFalse(
            setup_manager::disclosure_acknowledged((int)$other->id),
            'One administrator must not be able to acknowledge on behalf of another.'
        );

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\local_corolair\event\integration_disclosure_acknowledged::class, $event);
        $this->assertSame(integration_disclosure::VERSION, $event->other['version']);
        $this->assertEquals((int)$USER->id, (int)$event->userid);
    }

    /**
     * Only a site administrator may acknowledge the disclosure.
     *
     * @covers \local_corolair\local\setup_manager::acknowledge_disclosure
     * @return void
     */
    public function test_acknowledgment_requires_site_configuration_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        setup_manager::acknowledge_disclosure((int)$user->id);
    }

    /**
     * A completed integration is not interrupted by a new disclosure revision.
     *
     * Upgrades routinely bump the disclosure version. Re-gating a working integration on
     * a fresh acknowledgment would take a live site down until an administrator noticed.
     *
     * @covers \local_corolair\local\setup_manager::disclosure_acknowledged
     * @return void
     */
    public function test_completed_integration_is_grandfathered(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('setupcompleted', 1, 'local_corolair');

        $this->assertTrue(setup_manager::disclosure_acknowledged((int)$USER->id));
    }

    /**
     * Protocol strings and the list they should parse to.
     *
     * @return array[] Data sets of [configured value, expected protocols].
     */
    public static function protocol_provider(): array {
        return [
            'empty' => ['', []],
            'single' => ['rest', ['rest']],
            'padded' => [' rest , soap ', ['rest', 'soap']],
            'duplicated' => ['rest,rest,soap', ['rest', 'soap']],
            'trailing separator' => ['rest,', ['rest']],
            'only separators' => [',,', []],
        ];
    }

    /**
     * The protocol list is parsed leniently but reported exactly.
     *
     * @dataProvider protocol_provider
     * @covers \local_corolair\local\setup_manager::get_enabled_protocols
     * @param string $configured Raw $CFG->webserviceprotocols value.
     * @param string[] $expected Protocols that should be reported.
     * @return void
     */
    public function test_get_enabled_protocols(string $configured, array $expected): void {
        $this->resetAfterTest();
        $this->set_webservice_config(1, $configured);

        $this->assertSame($expected, setup_manager::get_enabled_protocols());
        $this->assertSame(in_array('rest', $expected, true), setup_manager::rest_enabled());
    }

    /**
     * Site states and whether setup would have to change them.
     *
     * @return array[] Data sets of [enablewebservices, protocols, consent required].
     */
    public static function enablement_provider(): array {
        return [
            'nothing enabled' => [0, '', true],
            'web services off but rest listed' => [0, 'rest', true],
            'web services on without rest' => [1, 'soap', true],
            'fully ready' => [1, 'rest', false],
            'fully ready with extras' => [1, 'soap,rest', false],
        ];
    }

    /**
     * Consent is required exactly when setup would have to change a site setting.
     *
     * @dataProvider enablement_provider
     * @covers \local_corolair\local\setup_manager::enablement_consent_required
     * @param int $enabled Value for enablewebservices.
     * @param string $protocols Value for webserviceprotocols.
     * @param bool $expected Whether enablement consent should be demanded.
     * @return void
     */
    public function test_enablement_consent_required(int $enabled, string $protocols, bool $expected): void {
        $this->resetAfterTest();
        $this->set_webservice_config($enabled, $protocols);

        $this->assertSame($expected, setup_manager::enablement_consent_required());
    }

    /**
     * Put the site one step away from activation.
     *
     * @return void
     */
    private function make_site_ready_to_activate(): void {
        global $USER;

        $this->setAdminUser();
        $this->set_webservice_config(1, 'rest');
        setup_manager::acknowledge_disclosure((int)$USER->id);
    }

    /**
     * Return the token lifecycle actions a sink recorded, in order.
     *
     * @param \phpunit_event_sink $sink Event sink capturing the run.
     * @return string[]
     */
    private function lifecycle_actions(\phpunit_event_sink $sink): array {
        $actions = [];
        foreach ($sink->get_events() as $event) {
            if ($event instanceof \local_corolair\event\webservice_token_lifecycle) {
                $actions[] = $event->other['action'];
            }
        }
        return $actions;
    }

    /**
     * Choosing not to rotate during setup is recorded before the token is ever minted.
     *
     * This is the whole point of offering the choice here: the registration task queued by
     * activate() reads the configured lifetime when it mints the token, so recording the
     * choice now produces a correctly shaped first token instead of a fifteen-day one that
     * the next maintenance run immediately replaces.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_records_the_rotation_choice(): void {
        global $USER;

        $this->resetAfterTest();
        $this->make_site_ready_to_activate();

        $sink = $this->redirectEvents();
        setup_manager::activate((int)$USER->id, true, true);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertTrue(webservice_token_manager::rotation_disabled());
        $this->assertSame(
            webservice_token_manager::NON_EXPIRING_LIFETIME,
            webservice_token_manager::token_lifetime(),
            'The queued registration task would still mint a fifteen-day token.'
        );
        $this->assertSame(['rotation_disabled'], $actions);
        $this->assertSame(1, $this->queued_registration_tasks());
    }

    /**
     * The opposite choice is recorded just as explicitly.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_records_choosing_to_keep_rotation(): void {
        global $USER;

        $this->resetAfterTest();
        $this->make_site_ready_to_activate();
        set_config('disabletokenrotation', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        setup_manager::activate((int)$USER->id, true, false);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertFalse(webservice_token_manager::rotation_disabled());
        $this->assertSame(webservice_token_manager::TOKEN_LIFETIME, webservice_token_manager::token_lifetime());
        $this->assertSame(['rotation_enabled'], $actions);
    }

    /**
     * A caller that expresses no preference leaves the policy exactly as it was.
     *
     * Every pre-existing caller of activate() passes two arguments or fewer, so the default
     * has to be inert -- both in what it writes and in what it logs.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_leaves_the_rotation_setting_alone_by_default(): void {
        global $USER;

        $this->resetAfterTest();
        $this->make_site_ready_to_activate();

        // Compared before and after rather than asserted absent: settings.php declares a
        // default, which Moodle materialises into config_plugins when the plugin installs.
        $before = $this->stored_rotation_setting();

        $sink = $this->redirectEvents();
        setup_manager::activate((int)$USER->id, true);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame($before, $this->stored_rotation_setting());
        $this->assertSame([], $actions);
    }

    /**
     * Return the stored rotation setting, bypassing any forced override.
     *
     * get_config() applies $CFG->forced_plugin_settings on top of the row, which is exactly
     * what these tests need to see past.
     *
     * @return false|string The stored value, or false when no row exists.
     */
    private function stored_rotation_setting() {
        global $DB;

        return $DB->get_field('config_plugins', 'value', [
            'plugin' => 'local_corolair',
            'name' => 'disabletokenrotation',
        ]);
    }

    /**
     * Repeating a choice that is already in force records nothing new.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_does_not_log_an_unchanged_rotation_choice(): void {
        global $USER;

        $this->resetAfterTest();
        $this->make_site_ready_to_activate();
        set_config('disabletokenrotation', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        setup_manager::activate((int)$USER->id, true, true);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertTrue(webservice_token_manager::rotation_disabled());
        $this->assertSame([], $actions);
    }

    /**
     * A policy pinned in config.php is never overwritten by the setup page.
     *
     * set_config() does not consult $CFG->forced_plugin_settings, so writing the row would
     * appear to succeed while get_config() kept returning the forced value. Recording a
     * consent decision that cannot take effect is worse than not offering it.
     *
     * @covers \local_corolair\local\setup_manager::activate
     * @return void
     */
    public function test_activate_does_not_override_a_forced_rotation_setting(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->make_site_ready_to_activate();
        set_config('disabletokenrotation', 0, 'local_corolair');
        $CFG->forced_plugin_settings['local_corolair']['disabletokenrotation'] = 0;

        $this->assertTrue(webservice_token_manager::rotation_setting_is_forced());

        $sink = $this->redirectEvents();
        setup_manager::activate((int)$USER->id, true, true);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame(
            '0',
            $this->stored_rotation_setting(),
            'A forced setting must not be written, even though the write would appear to succeed.'
        );
        $this->assertFalse(webservice_token_manager::rotation_disabled());
        $this->assertSame([], $actions);
        $this->assertSame(1, $this->queued_registration_tasks());
    }

    /**
     * An unset forced-settings list does not look like a forced setting.
     *
     * @covers \local_corolair\local\webservice_token_manager::rotation_setting_is_forced
     * @return void
     */
    public function test_rotation_setting_is_not_forced_by_default(): void {
        global $CFG;

        $this->resetAfterTest();
        unset($CFG->forced_plugin_settings);

        $this->assertFalse(webservice_token_manager::rotation_setting_is_forced());

        // A forced value of null still counts as forced, which is why the check cannot use isset().
        $CFG->forced_plugin_settings['local_corolair']['disabletokenrotation'] = null;
        $this->assertTrue(webservice_token_manager::rotation_setting_is_forced());
    }

    /**
     * A site where nobody opened setup.php reports setup as outstanding.
     *
     * @covers \local_corolair\local\setup_manager::setup_pending
     * @return void
     */
    public function test_setup_pending_on_a_freshly_installed_site(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();

        $this->assertTrue(setup_manager::setup_pending());
    }

    /**
     * Recording consent is what ends it, not completing registration.
     *
     * Waiting for setupcompleted would keep telling an administrator to do something they
     * have already done, for as long as their registration takes to run -- which on a site
     * whose cron is behind is long enough for them to conclude it did not take, and repeat it.
     *
     * @covers \local_corolair\local\setup_manager::setup_pending
     * @return void
     */
    public function test_setup_pending_ends_when_consent_is_recorded(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();
        set_config('setupconsented', 1, 'local_corolair');

        $this->assertFalse(setup_manager::setup_pending());
    }

    /**
     * A site upgrading from a version that predates the consent record is left alone.
     *
     * It carries none of the setup flags, yet it holds a working credential and its consent is
     * grandfathered asynchronously by the credential migration. Reading the flags alone would
     * report a demonstrably connected site as never set up, and send its trainers to a page
     * telling them to go and ask an administrator to set up what is already working.
     *
     * @covers \local_corolair\local\setup_manager::setup_pending
     * @return void
     */
    public function test_setup_pending_spares_a_connected_legacy_site(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();
        set_config('apikey', 'a-real-inherited-key', 'local_corolair');

        $this->assertFalse(setup_manager::setup_pending(), 'An inherited credential means a connected site.');

        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $this->assertFalse(setup_manager::setup_pending(), 'A migration in flight is not an unconfigured site.');
    }

    /**
     * Put the site in the state a command-line installation leaves behind.
     *
     * @return void
     */
    private function make_site_freshly_installed(): void {
        set_config('setupconsented', 0, 'local_corolair');
        set_config('setupcompleted', 0, 'local_corolair');
        unset_config('legacycredentialmigrationpending', 'local_corolair');
        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
    }
}
