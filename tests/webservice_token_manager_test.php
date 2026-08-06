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
        $adminid = $this->make_site_connected();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        $token = webservice_token_manager::create_token($adminid, $this->service_id());
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
        $adminid = $this->make_site_connected();
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        $token = webservice_token_manager::create_token($adminid, $this->service_id());
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
        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($adminid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        $previous = webservice_token_manager::create_token($adminid, $serviceid);
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
        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($adminid, $serviceid);
        webservice_token_manager::record_initial_token($current);
        $previous = webservice_token_manager::create_token($adminid, $serviceid);
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
     * Cleanup never deletes a token that does not belong to this integration.
     *
     * previouswebservicetokenid is a plain configuration value. If it were ever wrong --
     * mis-set, or carried over from a restored database -- an unguarded delete would
     * revoke somebody else's live web-service token.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_cleanup_refuses_a_token_owned_by_someone_else(): void {
        global $DB;

        $this->resetAfterTest();
        $adminid = $this->make_site_connected();
        $serviceid = $this->service_id();
        $DB->delete_records('external_tokens', ['externalserviceid' => $serviceid]);

        $current = webservice_token_manager::create_token($adminid, $serviceid);
        webservice_token_manager::record_initial_token($current);

        $stranger = $this->getDataGenerator()->create_user();
        $foreign = webservice_token_manager::create_token((int)$stranger->id, $serviceid);
        set_config('previouswebservicetokenid', (int)$foreign->id, 'local_corolair');
        set_config('previouswebservicetokenrevokeby', time() - 1, 'local_corolair');

        $sink = $this->redirectEvents();
        webservice_token_manager::maintain();
        $actions = $this->lifecycle_actions($sink);
        $sink->close();

        $this->assertTrue(
            $DB->record_exists('external_tokens', ['id' => (int)$foreign->id]),
            "Another user's token must never be revoked by this cleanup."
        );
        $this->assertSame([], $actions);
        $this->assertFalse(get_config('local_corolair', 'previouswebservicetokenid'));
    }
}
