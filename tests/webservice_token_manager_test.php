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
 * Tests for the web-service token lifecycle.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\event\webservice_token_lifecycle;
use local_corolair\local\service_account_provisioner;
use local_corolair\local\webservice_token_manager;

/**
 * Verifies token minting, the rotation schedule, and the local half of maintenance.
 *
 * The rotation round-trip itself needs Raison to call back into Moodle, so the paths
 * that reach the network are deliberately not exercised here. Everything up to and
 * around them is: the schedule that decides when to rotate, the state recorded when
 * rotation cannot even start, and the overlap window during which the previous token
 * stays usable.
 */
final class webservice_token_manager_test extends \advanced_testcase {
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
     * Put the site in the state maintain() expects before it does any real work.
     *
     * @return int The consenting administrator's ID.
     */
    private function make_site_connected(): int {
        $admin = get_admin();
        set_config('setupcompleted', 1, 'local_corolair');
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('setupconsentedby', (int)$admin->id, 'local_corolair');
        return (int)$admin->id;
    }

    /**
     * Put the site in the steady state: connected, with the token owned by the service account.
     *
     * Most maintenance tests want this rather than make_site_connected() alone. An
     * administrator-owned token is now a rotation trigger in its own right, so seeding one
     * makes maintain() try to hand ownership over -- and reach the network -- rather than
     * exercising whatever the test is actually about.
     *
     * @return int The service account's ID.
     */
    private function make_site_connected_as_service(): int {
        $this->make_site_connected();
        return service_account_provisioner::ensure();
    }

    /**
     * Return the lifecycle actions recorded by a sink, in order.
     *
     * @param \phpunit_event_sink $sink Event sink capturing the run.
     * @return string[]
     */
    private function lifecycle_actions(\phpunit_event_sink $sink): array {
        $actions = [];
        foreach ($sink->get_events() as $event) {
            if ($event instanceof webservice_token_lifecycle) {
                $actions[] = $event->other['action'];
            }
        }
        return $actions;
    }

    /**
     * A minted token is a fresh CSPRNG secret that expires in fifteen days.
     *
     * A token without an expiry is exactly what the 1.9.0 lifecycle exists to retire, so
     * validuntil being set is the point of this method, not an incidental detail.
     *
     * @covers \local_corolair\local\webservice_token_manager::create_token
     * @return void
     */
    public function test_create_token_mints_an_expiring_credential(): void {
        global $DB;

        $this->resetAfterTest();
        $admin = get_admin();
        $serviceid = $this->service_id();

        $before = time();
        $token = webservice_token_manager::create_token((int)$admin->id, $serviceid);
        $after = time();

        $this->assertGreaterThanOrEqual($before + webservice_token_manager::TOKEN_LIFETIME, (int)$token->validuntil);
        $this->assertLessThanOrEqual($after + webservice_token_manager::TOKEN_LIFETIME, (int)$token->validuntil);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token->token);
        $this->assertSame(0, (int)$token->tokentype);
        $this->assertSame((int)$admin->id, (int)$token->userid);
        $this->assertSame((int)$admin->id, (int)$token->creatorid);
        $this->assertSame($serviceid, (int)$token->externalserviceid);
        $this->assertSame((int)\context_system::instance()->id, (int)$token->contextid);
        $this->assertSame(get_string('tokenname', 'local_corolair'), $token->name);

        $stored = $DB->get_record('external_tokens', ['id' => $token->id], '*', MUST_EXIST);
        $this->assertSame($token->token, $stored->token);
    }

    /**
     * Two tokens minted in a row do not share a secret.
     *
     * @covers \local_corolair\local\webservice_token_manager::create_token
     * @return void
     */
    public function test_created_tokens_are_unique(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $serviceid = $this->service_id();

        $first = webservice_token_manager::create_token((int)$admin->id, $serviceid);
        $second = webservice_token_manager::create_token((int)$admin->id, $serviceid);

        $this->assertNotSame($first->token, $second->token);
        $this->assertNotSame($first->privatetoken, $second->privatetoken);
        $this->assertNotEquals((int)$first->id, (int)$second->id);
    }

    /**
     * Remaining lifetimes and the rotation/warning decisions they should produce.
     *
     * @return array[] Data sets of [seconds remaining, rotation due, warning due].
     */
    public static function schedule_provider(): array {
        return [
            'fresh' => [15 * DAYSECS, false, false],
            'just outside rotation' => [(7 * DAYSECS) + 1, false, false],
            'exactly at rotation' => [7 * DAYSECS, true, false],
            'inside rotation window' => [6 * DAYSECS, true, false],
            'just outside warning' => [(5 * DAYSECS) + 1, true, false],
            'exactly at warning' => [5 * DAYSECS, true, true],
            'inside warning window' => [DAYSECS, true, true],
            'expired' => [-DAYSECS, true, true],
        ];
    }

    /**
     * Rotation starts at seven days; warnings only at five.
     *
     * The gap is the whole design: two days of quiet retries before an administrator is
     * told anything, so a transient outage does not page anyone.
     *
     * @dataProvider schedule_provider
     * @covers \local_corolair\local\webservice_token_manager::rotation_due
     * @covers \local_corolair\local\webservice_token_manager::warning_due
     * @param int $remaining Seconds until the token expires.
     * @param bool $rotation Whether rotation should be due.
     * @param bool $warning Whether a warning should be due.
     * @return void
     */
    public function test_rotation_and_warning_schedule(int $remaining, bool $rotation, bool $warning): void {
        $now = 1700000000;
        $token = (object)['validuntil' => $now + $remaining];

        $this->assertSame($rotation, webservice_token_manager::rotation_due($token, $now));
        $this->assertSame($warning, webservice_token_manager::warning_due($token, $now));
    }

    /**
     * A token with no expiry rotates immediately, but never warns.
     *
     * These are the credentials inherited from before 1.9.0. They must be replaced at
     * once; warning about an expiry they do not have would be meaningless.
     *
     * @covers \local_corolair\local\webservice_token_manager::rotation_due
     * @covers \local_corolair\local\webservice_token_manager::warning_due
     * @return void
     */
    public function test_non_expiring_token_rotates_immediately(): void {
        $token = (object)['validuntil' => 0];

        $this->assertTrue(webservice_token_manager::rotation_due($token));
        $this->assertFalse(webservice_token_manager::warning_due($token));
    }

    /**
     * Expiry metadata is sent as UTC ISO-8601, whatever the site timezone is.
     *
     * @covers \local_corolair\local\webservice_token_manager::expiration_iso8601
     * @return void
     */
    public function test_expiration_is_reported_in_utc(): void {
        $this->resetAfterTest();
        $this->setTimezone('Australia/Perth');

        $token = (object)['validuntil' => 1700000000];

        $this->assertSame(gmdate('c', 1700000000), webservice_token_manager::expiration_iso8601($token));
        $this->assertStringEndsWith('+00:00', webservice_token_manager::expiration_iso8601($token));
    }

    /**
     * Recording the first token marks the integration active and clears rotation state.
     *
     * @covers \local_corolair\local\webservice_token_manager::record_initial_token
     * @return void
     */
    public function test_record_initial_token_activates_and_clears_pending_state(): void {
        $this->resetAfterTest();
        $admin = get_admin();

        // Leftovers from an earlier, abandoned rotation attempt.
        set_config('webservicetokencandidateid', 999, 'local_corolair');
        set_config('webservicetokenrotationid', 'stale-rotation', 'local_corolair');
        set_config('webservicetokenfailurecount', 4, 'local_corolair');

        $token = webservice_token_manager::create_token((int)$admin->id, $this->service_id());

        $sink = $this->redirectEvents();
        webservice_token_manager::record_initial_token($token);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertEquals((int)$token->id, (int)get_config('local_corolair', 'webservicetokenid'));
        $this->assertEquals((int)$token->validuntil, (int)get_config('local_corolair', 'webservicetokenexpiresat'));
        $this->assertSame('ACTIVE', get_config('local_corolair', 'webservicetokenrotationstatus'));
        $this->assertFalse(get_config('local_corolair', 'webservicetokencandidateid'));
        $this->assertFalse(get_config('local_corolair', 'webservicetokenrotationid'));
        $this->assertEquals(0, get_config('local_corolair', 'webservicetokenfailurecount'));
        $this->assertSame(['initial_token_activated'], $actions);
    }

    /**
     * Maintenance does nothing on a site that never finished setup.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_ignores_an_unconfigured_site(): void {
        $this->resetAfterTest();

        set_config('setupcompleted', 0, 'local_corolair');
        set_config('apikey', 'org_test.realsecret', 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
        $this->assertFalse(get_config('local_corolair', 'webservicetokenrotationstatus'));
    }

    /**
     * Maintenance does nothing while the API key is still the translated placeholder.
     *
     * Treating the placeholder as a credential would send it as a bearer token on every
     * scheduled run.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_ignores_a_placeholder_api_key(): void {
        $this->resetAfterTest();

        set_config('setupcompleted', 1, 'local_corolair');
        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
    }

    /**
     * Maintenance refuses to run without a recorded consenting administrator.
     *
     * The token is minted with that administrator's capabilities, so acting without one
     * would mean guessing whose authority the integration runs under.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_requires_a_consenting_administrator(): void {
        $this->resetAfterTest();

        set_config('setupcompleted', 1, 'local_corolair');
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        unset_config('setupconsentedby', 'local_corolair');

        try {
            webservice_token_manager::maintain();
            $this->fail('Maintenance should not run without a consent record.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('setupconsentmissing', $exception->errorcode);
        }
    }

    /**
     * A token that has vanished is reported, recorded, and escalated.
     *
     * Without a current token there is nothing to rotate and the integration is already
     * broken, so this is the one failure that warns immediately rather than retrying
     * quietly.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_reports_a_missing_token(): void {
        global $DB;

        $this->resetAfterTest();
        $adminid = $this->make_site_connected();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);

        $eventsink = $this->redirectEvents();
        $messagesink = $this->redirectMessages();
        try {
            webservice_token_manager::maintain();
            $this->fail('A missing token should surface as an error.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('tokenmissing', $exception->errorcode);
        }
        $actions = $this->lifecycle_actions($eventsink);
        $messages = $messagesink->get_messages();
        $eventsink->close();
        $messagesink->close();

        $this->assertSame('ROTATION_FAILED', get_config('local_corolair', 'webservicetokenrotationstatus'));
        $this->assertEquals(1, get_config('local_corolair', 'webservicetokenfailurecount'));
        $this->assertSame('current_token_missing', get_config('local_corolair', 'webservicetokenlasterror'));
        $this->assertSame(['rotation_failed', 'warning_sent'], $actions);

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($adminid, (int)$message->useridto);
        $this->assertStringNotContainsString(
            'realsecret',
            (string)$message->fullmessage,
            'The warning must not carry the API key.'
        );
        // There is no token, so the body falls back to a placeholder expiry. That
        // fallback used to call get_string('unknown'), which core does not define, so
        // the one message sent when the integration breaks rendered [[unknown]].
        $this->assertStringNotContainsString(
            '[[',
            (string)$message->fullmessage,
            'The warning body contains an unresolved language string.'
        );
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'webservicetokenadminnotifiedat'));
    }

    /**
     * The same warning is not repeated within a day.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_repeated_failures_do_not_repeat_the_warning(): void {
        global $DB;

        $this->resetAfterTest();
        $this->make_site_connected();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);

        $messagesink = $this->redirectMessages();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                webservice_token_manager::maintain();
            } catch (\moodle_exception $exception) {
                $this->assertSame('tokenmissing', $exception->errorcode);
            }
        }
        $messages = $messagesink->get_messages();
        $messagesink->close();

        $this->assertCount(1, $messages, 'The administrator should be told once per day, not once per run.');
        $this->assertEquals(2, get_config('local_corolair', 'webservicetokenfailurecount'));
    }

    /**
     * A token nowhere near expiry is left completely alone.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_leaves_a_healthy_token_untouched(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        $token = webservice_token_manager::create_token($ownerid, $this->service_id());
        webservice_token_manager::record_initial_token($token);

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame([], $actions, 'Nothing should happen while the token is healthy.');
        $this->assertSame('ACTIVE', get_config('local_corolair', 'webservicetokenrotationstatus'));
        $this->assertEquals((int)$token->id, (int)get_config('local_corolair', 'webservicetokenid'));
        $this->assertSame(1, $DB->count_records('external_tokens', ['externalserviceid' => $this->service_id()]));
    }

    /**
     * A token predating the lifecycle metadata is adopted rather than replaced.
     *
     * Sites upgrading from before 1.9.0 have a working token and no webservicetokenid.
     * Minting a second one instead of adopting it would leave two live credentials.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_adopts_a_token_with_no_recorded_id(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        $token = webservice_token_manager::create_token($ownerid, $this->service_id());
        unset_config('webservicetokenid', 'local_corolair');

        webservice_token_manager::maintain();

        $this->assertEquals((int)$token->id, (int)get_config('local_corolair', 'webservicetokenid'));
        $this->assertEquals(
            (int)$token->validuntil,
            (int)get_config('local_corolair', 'webservicetokenexpiresat')
        );
        $this->assertSame(1, $DB->count_records('external_tokens', ['externalserviceid' => $this->service_id()]));
    }

    /**
     * The superseded token is deleted once its overlap window has passed.
     *
     * Corolair may still be using the old token for in-flight requests when rotation
     * completes, so it is kept for a bounded overlap and only then revoked.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_previous_token_is_revoked_after_its_overlap(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($ownerid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        $previous = webservice_token_manager::create_token($ownerid, $serviceid);
        set_config('previouswebservicetokenid', (int)$previous->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() - 1, 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertFalse($DB->record_exists('external_tokens', ['id' => (int)$previous->id]));
        $this->assertTrue($DB->record_exists('external_tokens', ['id' => (int)$current->id]));
        $this->assertSame(['old_token_revoked'], $actions);
        $this->assertFalse(get_config('local_corolair', 'previouswebservicetokenid'));
        $this->assertFalse(get_config('local_corolair', 'previouswebservicetokenrevokeby'));
    }

    /**
     * The superseded token survives until its overlap window has passed.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_previous_token_survives_its_overlap(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($ownerid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        $previous = webservice_token_manager::create_token($ownerid, $serviceid);
        set_config('previouswebservicetokenid', (int)$previous->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() + DAYSECS, 'local_corolair');

        webservice_token_manager::maintain();

        $this->assertTrue($DB->record_exists('external_tokens', ['id' => (int)$previous->id]));
        $this->assertEquals(
            (int)$previous->id,
            (int)get_config('local_corolair', 'previouswebservicetokenid'),
            'The overlap must still be tracked so the token is revoked later.'
        );
    }

    /**
     * Cleanup never deletes a token belonging to a different service.
     *
     * previouswebservicetokenid is a plain configuration value. If it were ever wrong --
     * mis-set, or carried over from a restored database -- an unguarded delete would revoke
     * an unrelated live web-service token.
     *
     * The guard is scoped to the service and no longer to the owner. Requiring the owner to
     * be the consenting administrator was only ever correct while the token was always an
     * administrator's; once ownership moved to the service account, every superseded token
     * would have failed that check and accumulated forever instead of being revoked.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_cleanup_refuses_a_token_from_another_service(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($ownerid, $serviceid);
        webservice_token_manager::record_initial_token($current);

        $otherservice = $DB->insert_record('external_services', (object)[
            'name' => 'Unrelated service',
            'enabled' => 1,
            'restrictedusers' => 0,
            'component' => null,
            'timecreated' => time(),
            'timemodified' => time(),
            'shortname' => 'unrelated_rest',
            'downloadfiles' => 0,
            'uploadfiles' => 0,
        ]);
        $foreign = webservice_token_manager::create_token($ownerid, (int)$otherservice);
        set_config('previouswebservicetokenid', (int)$foreign->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() - 1, 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertTrue(
            $DB->record_exists('external_tokens', ['id' => (int)$foreign->id]),
            'A token belonging to another service must never be revoked by this cleanup.'
        );
        $this->assertSame([], $actions);
        $this->assertFalse(get_config('local_corolair', 'previouswebservicetokenid'));
    }

    /**
     * Desired-versus-actual lifetimes, and the rotation each combination should produce.
     *
     * This is the whole convergence rule in one table. Both directions of the setting have
     * to fall out of the same predicate, and the legacy "no expiry at all" token has to keep
     * its original meaning under either policy.
     *
     * The two columns are deliberately independent. A token can match the configured policy
     * and still be due for rotation, which is the ordinary "about to expire" case; what the
     * mismatch column adds is the rotations that happen for no reason other than the policy.
     *
     * @return array[] Data sets of [seconds remaining, rotation disabled, rotation due, lifetime matches].
     */
    public static function convergence_provider(): array {
        return [
            'expiring token, rotation enabled' => [15 * DAYSECS, false, false, true],
            'expiring token near expiry, rotation enabled' => [6 * DAYSECS, false, true, true],
            'expiring token, rotation disabled' => [15 * DAYSECS, true, true, false],
            'non-expiring token, rotation disabled' => [100 * YEARSECS, true, false, true],
            'non-expiring token, rotation enabled' => [100 * YEARSECS, false, true, false],
            'expired token, rotation enabled' => [-DAYSECS, false, true, false],
            'expired token, rotation disabled' => [-DAYSECS, true, true, false],
        ];
    }

    /**
     * A token whose lifetime no longer matches the configured policy is always rotated.
     *
     * @dataProvider convergence_provider
     * @covers \local_corolair\local\webservice_token_manager::rotation_due
     * @covers \local_corolair\local\webservice_token_manager::lifetime_matches_configuration
     * @param int $remaining Seconds until the token expires.
     * @param bool $disabled Whether rotation is disabled.
     * @param bool $due Whether rotation should be due.
     * @param bool $matches Whether the lifetime should match the configuration.
     * @return void
     */
    public function test_lifetime_convergence(int $remaining, bool $disabled, bool $due, bool $matches): void {
        $this->resetAfterTest();
        set_config('disabletokenrotation', $disabled ? 1 : 0, 'local_corolair');

        $now = 1700000000;
        $token = (object)['validuntil' => $now + $remaining];

        $this->assertSame($due, webservice_token_manager::rotation_due($token, $now));
        $this->assertSame($matches, webservice_token_manager::lifetime_matches_configuration($token, $now));
    }

    /**
     * Re-enabling rotation forces a rotation even though the token is a century from expiry.
     *
     * This is the direction that does not heal itself. A non-expiring token is nowhere near
     * the seven-day rotation window, so without the desired-versus-actual check the ordinary
     * schedule stays false forever and the setting silently never takes effect.
     *
     * @covers \local_corolair\local\webservice_token_manager::rotation_due
     * @return void
     */
    public function test_reenabled_rotation_forces_a_lifetime_change(): void {
        $this->resetAfterTest();
        set_config('disabletokenrotation', 0, 'local_corolair');

        $now = 1700000000;
        $token = (object)['validuntil' => $now + webservice_token_manager::NON_EXPIRING_LIFETIME];

        $this->assertGreaterThan(
            webservice_token_manager::ROTATE_BEFORE_EXPIRY,
            (int)$token->validuntil - $now,
            'The token must be far outside the ordinary rotation window for this test to mean anything.'
        );
        $this->assertTrue(webservice_token_manager::rotation_due($token, $now));
    }

    /**
     * A legacy token with no expiry keeps rotating immediately under either policy.
     *
     * validuntil = 0 is the pre-1.9 marker for an exposed credential, not a deliberate
     * "never expires". Disabling rotation must not turn that marker into an endorsement.
     *
     * @covers \local_corolair\local\webservice_token_manager::rotation_due
     * @covers \local_corolair\local\webservice_token_manager::is_non_expiring
     * @return void
     */
    public function test_legacy_token_still_rotates_when_rotation_is_disabled(): void {
        $this->resetAfterTest();
        set_config('disabletokenrotation', 1, 'local_corolair');

        $token = (object)['validuntil' => 0];

        $this->assertFalse(webservice_token_manager::is_non_expiring($token));
        $this->assertTrue(webservice_token_manager::rotation_due($token));
    }

    /**
     * Minted tokens follow the configured policy, and the override wins over it.
     *
     * @covers \local_corolair\local\webservice_token_manager::create_token
     * @covers \local_corolair\local\webservice_token_manager::token_lifetime
     * @return void
     */
    public function test_create_token_honours_the_rotation_policy(): void {
        $this->resetAfterTest();
        $adminid = (int)get_admin()->id;
        $serviceid = $this->service_id();
        set_config('disabletokenrotation', 1, 'local_corolair');

        $this->assertSame(webservice_token_manager::NON_EXPIRING_LIFETIME, webservice_token_manager::token_lifetime());

        $before = time();
        $token = webservice_token_manager::create_token($adminid, $serviceid);
        $this->assertGreaterThanOrEqual(
            $before + webservice_token_manager::NON_EXPIRING_LIFETIME,
            (int)$token->validuntil
        );
        $this->assertTrue(webservice_token_manager::is_non_expiring($token));

        // The explicit lifetime is what the legacy migration uses to opt out of the policy.
        $before = time();
        $forced = webservice_token_manager::create_token(
            $adminid,
            $serviceid,
            webservice_token_manager::TOKEN_LIFETIME
        );
        $this->assertLessThanOrEqual(
            time() + webservice_token_manager::TOKEN_LIFETIME,
            (int)$forced->validuntil
        );
        $this->assertGreaterThanOrEqual(
            $before + webservice_token_manager::TOKEN_LIFETIME,
            (int)$forced->validuntil
        );
        $this->assertFalse(webservice_token_manager::is_non_expiring($forced));
    }

    /**
     * The transmitted expiry is absent for a non-expiring token and a string otherwise.
     *
     * Corolair caps any supplied expiration at fifteen days and models "no expiration" as a
     * null column, so a far-future date could not be sent even if it were meaningful there.
     *
     * @covers \local_corolair\local\webservice_token_manager::expiration_iso8601
     * @return void
     */
    public function test_expiration_is_omitted_for_a_non_expiring_token(): void {
        $this->resetAfterTest();

        $expiring = (object)['validuntil' => time() + webservice_token_manager::TOKEN_LIFETIME];
        $nonexpiring = (object)['validuntil' => time() + webservice_token_manager::NON_EXPIRING_LIFETIME];

        $this->assertIsString(webservice_token_manager::expiration_iso8601($expiring));
        $this->assertNull(webservice_token_manager::expiration_iso8601($nonexpiring));
    }

    /**
     * A converged non-expiring token is left completely alone.
     *
     * Reaching the network would need rotation to be due, and it is not: this is the steady
     * state the setting exists to produce.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_leaves_a_converged_non_expiring_token_untouched(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);
        set_config('disabletokenrotation', 1, 'local_corolair');

        $token = webservice_token_manager::create_token($ownerid, $serviceid);
        webservice_token_manager::record_initial_token($token);
        // Keep the remote re-verification from firing; it is exercised separately.
        set_config('webservicetokenverifiedat', time(), 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame([], $actions);
        $this->assertSame('ACTIVE', get_config('local_corolair', 'webservicetokenrotationstatus'));
        $this->assertEquals((int)$token->id, (int)get_config('local_corolair', 'webservicetokenid'));
        $this->assertSame(1, $DB->count_records('external_tokens', ['externalserviceid' => $serviceid]));
    }

    /**
     * Recording a non-expiring token as active is called out in the log.
     *
     * @covers \local_corolair\local\webservice_token_manager::record_initial_token
     * @return void
     */
    public function test_activating_a_non_expiring_token_is_recorded(): void {
        $this->resetAfterTest();
        set_config('disabletokenrotation', 1, 'local_corolair');
        $token = webservice_token_manager::create_token((int)get_admin()->id, $this->service_id());

        $sink = $this->redirectEvents();
        webservice_token_manager::record_initial_token($token);
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame(['initial_token_activated', 'nonexpiring_token_activated'], $actions);
    }

    /**
     * Changing the policy is recorded against the administrator who changed it.
     *
     * @covers \local_corolair\local\webservice_token_manager::record_rotation_policy_change
     * @return void
     */
    public function test_rotation_policy_changes_are_recorded(): void {
        $this->resetAfterTest();

        set_config('disabletokenrotation', 1, 'local_corolair');
        $sink = $this->redirectEvents();
        webservice_token_manager::record_rotation_policy_change();
        $this->assertSame(['rotation_disabled'], $this->lifecycle_actions($sink));
        $sink->close();

        set_config('disabletokenrotation', 0, 'local_corolair');
        $sink = $this->redirectEvents();
        webservice_token_manager::record_rotation_policy_change();
        $this->assertSame(['rotation_enabled'], $this->lifecycle_actions($sink));
        $sink->close();
    }

    /**
     * An unresolved lifetime change eventually warns, but only after retrying stops helping.
     *
     * A non-expiring token is never close to expiring, so the ordinary expiry warning can
     * never fire on it. Without this, a site stuck unable to re-enable rotation would hold a
     * never-expiring token indefinitely and nobody would be told.
     *
     * @covers \local_corolair\local\webservice_token_manager::warning_due
     * @return void
     */
    public function test_unresolved_lifetime_change_warns_after_repeated_failures(): void {
        $this->resetAfterTest();
        set_config('disabletokenrotation', 0, 'local_corolair');

        $now = 1700000000;
        $token = (object)['validuntil' => $now + webservice_token_manager::NON_EXPIRING_LIFETIME];

        set_config('webservicetokenfailurecount', 1, 'local_corolair');
        $this->assertFalse(webservice_token_manager::warning_due($token, $now));

        set_config(
            'webservicetokenfailurecount',
            webservice_token_manager::LIFETIME_CHANGE_WARN_AFTER,
            'local_corolair'
        );
        $this->assertTrue(webservice_token_manager::warning_due($token, $now));
    }

    /**
     * Maintenance stands aside while the legacy credential migration owns the token.
     *
     * The migration replaces the API key and deletes every other token for the service, so
     * a rotation running alongside it leaves one side holding a credential the other just
     * invalidated. Convergence makes this reachable: it fires the moment the setting changes,
     * which is exactly when a site is likely to be mid-upgrade.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_defers_to_a_pending_legacy_migration(): void {
        global $DB;

        $this->resetAfterTest();
        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        // A token that would otherwise be rotated at once.
        $token = webservice_token_manager::create_token($adminid, $serviceid, DAYSECS);
        webservice_token_manager::record_initial_token($token);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertSame([], $actions);
        $this->assertSame('ACTIVE', get_config('local_corolair', 'webservicetokenrotationstatus'));
        $this->assertSame(1, $DB->count_records('external_tokens', ['externalserviceid' => $serviceid]));
    }

    /**
     * Maintenance never leaves the rotation lock held, including when it fails.
     *
     * The scheduled task and the administrator-triggered retry hold different core locks, so
     * nothing else stops them overlapping -- and saving the rotation setting queues the ad-hoc
     * task at precisely the moment the scheduled one may be running. That mutual exclusion is
     * only worth anything if the lock is always given back, so a run that throws matters more
     * here than one that succeeds.
     *
     * The exclusion itself is not asserted: both the MySQL and PostgreSQL lock factories are
     * backed by session-scoped advisory locks, which the owning session may re-acquire freely.
     * They exclude separate cron processes, which is the case that matters, but a single-process
     * test cannot stand in for one of them.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintenance_always_releases_the_rotation_lock(): void {
        global $DB;

        $this->resetAfterTest();
        $ownerid = $this->make_site_connected_as_service();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        // A converged token, so maintenance completes without needing the network.
        set_config('disabletokenrotation', 1, 'local_corolair');
        $token = webservice_token_manager::create_token($ownerid, $serviceid);
        webservice_token_manager::record_initial_token($token);
        set_config('webservicetokenverifiedat', time(), 'local_corolair');

        webservice_token_manager::maintain();
        $this->assert_rotation_lock_is_free();

        // The same guarantee has to hold when maintenance throws part way through.
        unset_config('setupconsentedby', 'local_corolair');
        try {
            webservice_token_manager::maintain();
            $this->fail('Maintenance should have failed without a consenting administrator.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('setupconsentmissing', $exception->errorcode);
        }
        $this->assert_rotation_lock_is_free();
    }

    /**
     * Assert the rotation lock can be taken, then give it straight back.
     *
     * @return void
     */
    private function assert_rotation_lock_is_free(): void {
        $lock = \core\lock\lock_config::get_lock_factory('local_corolair_token')->get_lock('rotation', 0);
        $this->assertNotFalse($lock, 'Maintenance left the rotation lock held.');
        $lock->release();
    }

    /**
     * Maintenance provisions the service account and rotates onto it.
     *
     * Network-free: the run reaches send_candidate() and fails there, which is exactly far
     * enough to prove that a candidate was minted for the right owner. Asserting that after
     * the failure is why this uses try/fail/catch rather than expectException.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_rotates_when_the_token_owner_differs(): void {
        global $DB;

        $this->resetAfterTest();

        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $current = webservice_token_manager::create_token($adminid, $serviceid);
        webservice_token_manager::record_initial_token($current);

        try {
            webservice_token_manager::maintain();
        } catch (\Throwable $exception) {
            // Expected: the rotation POST cannot succeed in a test run.
            $this->assertNotEmpty($exception->getMessage());
        }

        $serviceaccountid = service_account_provisioner::locate();
        $this->assertGreaterThan(0, $serviceaccountid, 'Maintenance must provision the service account.');
        $this->assertNotSame($adminid, $serviceaccountid);

        $candidateid = (int)get_config('local_corolair', 'webservicetokencandidateid');
        $this->assertGreaterThan(0, $candidateid, 'A rotation onto the service account should have started.');
        $this->assertSame(
            $serviceaccountid,
            (int)$DB->get_field('external_tokens', 'userid', ['id' => $candidateid]),
            'The candidate token must belong to the service account.'
        );
    }

    /**
     * An in-flight candidate belonging to the previous owner is finished, not abandoned.
     *
     * The candidate lookup must not be scoped to the desired owner. If it were, a candidate
     * minted before the ownership change would not be found -- which also skips the delete
     * branch, so the row is orphaned *and* a fresh rotation ID is minted while Raison is
     * still waiting on the old one. Raison refuses a new rotation ID while one is
     * outstanding, and nothing clears that state except a rotation matching it, so every
     * later attempt would deadlock.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_a_candidate_from_the_previous_owner_is_reused(): void {
        $this->resetAfterTest();

        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $current = webservice_token_manager::create_token($adminid, $serviceid);
        webservice_token_manager::record_initial_token($current);

        // An admin-owned candidate left pending by an earlier cycle.
        $candidate = webservice_token_manager::create_token($adminid, $serviceid);
        set_config('webservicetokencandidateid', (int)$candidate->id, 'local_corolair');
        set_config('webservicetokenrotationid', 'ffffffff-ffff-4fff-bfff-ffffffffffff', 'local_corolair');

        try {
            webservice_token_manager::maintain();
        } catch (\Throwable $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertSame(
            (int)$candidate->id,
            (int)get_config('local_corolair', 'webservicetokencandidateid'),
            'The pending candidate must be reused, not replaced.'
        );
        $this->assertSame(
            'ffffffff-ffff-4fff-bfff-ffffffffffff',
            (string)get_config('local_corolair', 'webservicetokenrotationid'),
            'Minting a new rotation ID while one is outstanding deadlocks every later attempt.'
        );
    }

    /**
     * A superseded token owned by the service account is still revoked.
     *
     * The cleanup guard used to also require the token owner to be the consenting
     * administrator, which held only while the token was always an administrator's. Once
     * ownership moves, every subsequent superseded token belongs to the service account and
     * the guard would fail silently -- old tokens accumulating forever, which is the exact
     * opposite of what this method is for.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_cleanup_revokes_a_token_owned_by_the_service_account(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_site_connected();
        $serviceid = $this->service_id();
        $serviceaccountid = service_account_provisioner::ensure();

        $previous = webservice_token_manager::create_token($serviceaccountid, $serviceid);
        $current = webservice_token_manager::create_token($serviceaccountid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        set_config('previouswebservicetokenid', (int)$previous->id, 'local_corolair');
        set_config('previouswebservicetokenownerid', $serviceaccountid, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() - 1, 'local_corolair');

        try {
            webservice_token_manager::maintain();
        } catch (\Throwable $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertFalse(
            $DB->record_exists('external_tokens', ['id' => (int)$previous->id]),
            'A superseded service-account token must be revoked once its overlap has passed.'
        );
    }

    /**
     * Revoking the old token also withdraws its owner's authorisation.
     *
     * Order matters in one direction only: the administrator keeps its authorised-user row
     * for as long as it holds a live token, because withdrawing it when the new token is
     * activated would break every request made during the overlap -- which is the entire
     * reason the overlap exists.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_cleanup_withdraws_the_previous_owner_authorisation(): void {
        global $DB;

        $this->resetAfterTest();

        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $serviceaccountid = service_account_provisioner::ensure();
        service_account_provisioner::ensure_authorised($serviceid, $adminid);

        $previous = webservice_token_manager::create_token($adminid, $serviceid);
        $current = webservice_token_manager::create_token($serviceaccountid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        set_config('previouswebservicetokenid', (int)$previous->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() - 1, 'local_corolair');
        set_config('serviceaccountmigrationpending', 1, 'local_corolair');

        try {
            webservice_token_manager::maintain();
        } catch (\Throwable $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertFalse(
            $DB->record_exists('external_services_users', [
                'externalserviceid' => $serviceid,
                'userid' => $adminid,
            ]),
            'The administrator loses authorisation once no administrator-owned token survives.'
        );
        $this->assertTrue($DB->record_exists('external_services_users', [
            'externalserviceid' => $serviceid,
            'userid' => $serviceaccountid,
        ]));
        $this->assertFalse(get_config('local_corolair', 'serviceaccountmigrationpending'));
    }

    /**
     * A non-administrator token owner is not reported as a problem.
     *
     * The drift check used to require the owner to hold moodle/site:config. Against the
     * service account that test is now guaranteed to fail, so leaving it in place would mail
     * every administrator a daily warning describing the intended state.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_a_non_administrator_owner_is_not_reported_as_drift(): void {
        $this->resetAfterTest();

        $this->make_site_connected();
        set_config('disabletokenrotation', 1, 'local_corolair');
        $serviceid = $this->service_id();
        $serviceaccountid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $current = webservice_token_manager::create_token($serviceaccountid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        // Weekly remote re-verification would otherwise run; recording it as just done keeps
        // this test entirely local, which is the contract for this suite.
        set_config('webservicetokenverifiedat', time(), 'local_corolair');

        $sink = $this->redirectMessages();
        try {
            webservice_token_manager::maintain();
            $this->assertSame([], $sink->get_messages(), 'A service-account owner is the intended state.');
        } finally {
            $sink->close();
        }
    }

    /**
     * A missing granted function is still reported.
     *
     * The counterpart to the test above: replacing the administrator check must not leave
     * the drift check with nothing left to detect. This is the condition it exists for --
     * the site no longer grants something the integration needs, and with rotation disabled
     * nothing else would ever notice, because the rotate call is the only thing that asks
     * Raison to re-verify the integration end to end.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_a_missing_granted_function_is_reported(): void {
        global $DB;

        $this->resetAfterTest();

        $this->make_site_connected();
        set_config('disabletokenrotation', 1, 'local_corolair');
        $serviceid = $this->service_id();
        $serviceaccountid = service_account_provisioner::ensure();
        accesslib_clear_all_caches_for_unit_testing();

        $current = webservice_token_manager::create_token($serviceaccountid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        set_config('webservicetokenverifiedat', time(), 'local_corolair');

        $DB->delete_records('external_services_functions', [
            'externalserviceid' => $serviceid,
            'functionname' => 'core_course_get_contents',
        ]);

        $sink = $this->redirectMessages();
        try {
            webservice_token_manager::maintain();
            $this->assertNotEmpty($sink->get_messages(), 'A revoked function grant must raise a warning.');
        } finally {
            $sink->close();
        }
    }

    /**
     * Maintenance stands down entirely while the legacy credential migration is pending.
     *
     * It must not provision, must not mint a candidate, and must not call Raison. The
     * migration deletes every token for the service except its own and then asserts that
     * exactly one survives, so a candidate created underneath it breaks both halves of that
     * -- and the migration exists to retire a credential that is already exposed, which
     * makes it the one thing that must not be raced.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_maintain_stands_down_during_the_legacy_migration(): void {
        $this->resetAfterTest();

        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $current = webservice_token_manager::create_token($adminid, $serviceid, 1);
        webservice_token_manager::record_initial_token($current);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        try {
            webservice_token_manager::maintain();
            $events = $sink->get_events();
        } finally {
            $sink->close();
        }

        foreach ($events as $event) {
            $this->assertNotInstanceOf(
                \local_corolair\event\remote_request_completed::class,
                $event,
                'Rotating underneath the credential migration must not happen.'
            );
        }
        $this->assertSame(
            0,
            (int)get_config('local_corolair', 'webservicetokencandidateid'),
            'No candidate may be minted while the migration holds the service.'
        );
        $this->assertSame(
            0,
            service_account_provisioner::locate(),
            'Provisioning must wait until the migration has released the service.'
        );
    }
}
