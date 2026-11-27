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
 * Tests for Raison's per-person answer on the global assistant.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\global_assistant_session;

/**
 * Verifies what is sent, what is accepted, and how long an answer spares another request.
 *
 * Every request goes through a fake transport: nothing here may reach the network.
 *
 * @covers \local_corolair\local\global_assistant_session
 */
final class global_assistant_session_test extends \advanced_testcase {
    /** API key the tests ask with. */
    private const KEY = 'org_test.realsecret';

    /** Fixed starting time, so reuse windows are exact. */
    private const NOW = 1700000000;

    /** @var int Requests the fake transports have answered. */
    private int $requests = 0;

    /** @var string[] Bodies the fake transports received. */
    private array $bodies = [];

    /**
     * A transport answering with a fixed result.
     *
     * @param int $httpstatus HTTP status to report.
     * @param mixed $body Body to report: an array is JSON-encoded, false means no response.
     * @param int $errno cURL error number to report.
     * @return callable
     */
    private function transport(int $httpstatus, $body, int $errno = 0): callable {
        return function (string $apikey, string $requestbody) use ($httpstatus, $body, $errno): array {
            $this->requests++;
            $this->bodies[] = $requestbody;
            return [
                'response' => is_array($body) ? json_encode($body) : $body,
                'errno' => $errno,
                'httpstatus' => $httpstatus,
            ];
        };
    }

    /**
     * A yes, as Raison sends it.
     *
     * @param int $expiresin Token lifetime.
     * @param int $reusefor How long the answer holds.
     * @return array
     */
    private function yes(int $expiresin = 1800, int $reusefor = 1500): array {
        return [
            'access' => true,
            'token' => 'global-token',
            'expiresIn' => $expiresin,
            'organizationId' => 'org_abc-123',
            'reuseFor' => $reusefor,
        ];
    }

    /**
     * A transport that fails the test if it is ever called.
     *
     * @return callable
     */
    private function no_request(): callable {
        return function (): array {
            $this->fail('The stored answer should have been used without a request.');
        };
    }

    /**
     * Ask for a person.
     *
     * @param \stdClass $user Person viewing the page.
     * @param callable $transport Fake transport.
     * @param int $now Test clock.
     * @param string $apikey API key.
     * @return array|null
     */
    private function ask(\stdClass $user, callable $transport, int $now, string $apikey = self::KEY): ?array {
        return global_assistant_session::get($user, $apikey, \context_system::instance(), $transport, $now);
    }

    /**
     * Only the Moodle id, email and names are sent: no role, page, course or site address.
     *
     * @return void
     */
    public function test_only_identity_is_sent(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);

        $this->ask($user, $this->transport(200, $this->yes()), self::NOW);

        $this->assertSame(
            ['moodleId' => (string)$user->id, 'email' => $user->email, 'firstName' => 'Ada', 'lastName' => 'Lovelace'],
            json_decode($this->bodies[0], true)
        );
    }

    /**
     * A yes gives the token and organization, and spares requests while it holds.
     *
     * @return void
     */
    public function test_yes_is_reused_while_it_holds(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $expected = ['token' => 'global-token', 'organizationid' => 'org_abc-123'];
        $this->assertSame($expected, $this->ask($user, $this->transport(200, $this->yes()), self::NOW));
        $this->assertSame($expected, $this->ask($user, $this->no_request(), self::NOW + 1499));
        $this->assertSame($expected, $this->ask($user, $this->transport(200, $this->yes()), self::NOW + 1500));
        $this->assertSame(2, $this->requests);
    }

    /**
     * A yes whose token would not outlive the page by long enough is asked again early.
     *
     * @return void
     */
    public function test_expiring_token_is_renewed_early(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->ask($user, $this->transport(200, $this->yes(120, 3600)), self::NOW);
        $this->assertNotNull($this->ask($user, $this->no_request(), self::NOW + 60));
        $this->assertNotNull($this->ask($user, $this->transport(200, $this->yes()), self::NOW + 61));
        $this->assertSame(2, $this->requests);
    }

    /**
     * A no renders nothing, and spares requests for as long as Raison said.
     *
     * @return void
     */
    public function test_no_is_reused_while_it_holds(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $no = ['access' => false, 'reuseFor' => 900];

        $this->assertNull($this->ask($user, $this->transport(200, $no), self::NOW));
        $this->assertNull($this->ask($user, $this->no_request(), self::NOW + 899));
        $this->assertNotNull($this->ask($user, $this->transport(200, $this->yes()), self::NOW + 900));
    }

    /**
     * Answers that are not exactly as agreed.
     *
     * @return array[] Data sets of [httpstatus, body, errno].
     */
    public static function failure_provider(): array {
        $yes = [
            'access' => true,
            'token' => 'global-token',
            'expiresIn' => 1800,
            'organizationId' => 'org_abc-123',
            'reuseFor' => 1500,
        ];
        return [
            'a backend without the route' => [404, ['message' => 'Cannot POST'], 0],
            'unauthorised' => [401, ['message' => 'Invalid Corolair Api Key'], 0],
            'server error' => [500, ['message' => 'Internal server error'], 0],
            'transport error' => [0, false, 28],
            'not json' => [200, '<html></html>', 0],
            'no decision' => [200, ['reuseFor' => 900], 0],
            'a decision that is not a boolean' => [200, ['access' => 'true', 'reuseFor' => 900], 0],
            'no reuse time' => [200, ['access' => false], 0],
            'a reuse time too short' => [200, ['reuseFor' => 59] + $yes, 0],
            'a reuse time too long' => [200, ['reuseFor' => 3601] + $yes, 0],
            'no token' => [200, ['token' => ''] + $yes, 0],
            'a token too long' => [200, ['token' => str_repeat('t', 8193)] + $yes, 0],
            'a token lifetime too long' => [200, ['expiresIn' => 3601] + $yes, 0],
            'no token lifetime' => [200, ['expiresIn' => 0] + $yes, 0],
            'an organization id that is not one' => [200, ['organizationId' => 'org" onload="x'] + $yes, 0],
        ];
    }

    /**
     * Anything not exactly as agreed is a no, kept for a minute so an outage costs no request per page.
     *
     * @dataProvider failure_provider
     * @param int $httpstatus HTTP status.
     * @param mixed $body Response body.
     * @param int $errno cURL error number.
     * @return void
     */
    public function test_failure_is_a_short_no(int $httpstatus, $body, int $errno): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertNull($this->ask($user, $this->transport($httpstatus, $body, $errno), self::NOW));
        $this->assertNull($this->ask($user, $this->no_request(), self::NOW + global_assistant_session::RETRY_AFTER - 1));
        $this->assertNotNull(
            $this->ask($user, $this->transport(200, $this->yes()), self::NOW + global_assistant_session::RETRY_AFTER)
        );
    }

    /**
     * Each person has their own answer.
     *
     * @return void
     */
    public function test_answers_are_per_person(): void {
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();

        $this->assertNotNull($this->ask($learner, $this->transport(200, $this->yes()), self::NOW));
        $this->assertNull($this->ask($other, $this->transport(200, ['access' => false, 'reuseFor' => 900]), self::NOW));
        $this->assertNotNull($this->ask($learner, $this->no_request(), self::NOW + 1));
        $this->assertSame(2, $this->requests);
    }

    /**
     * An answer given for another API key is not used.
     *
     * @return void
     */
    public function test_a_new_key_asks_again(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->ask($user, $this->transport(200, $this->yes()), self::NOW);
        $no = $this->transport(200, ['access' => false, 'reuseFor' => 900]);
        $this->assertNull($this->ask($user, $no, self::NOW + 1, 'org_test.rotated'));
        $this->assertSame(2, $this->requests);
    }
}
