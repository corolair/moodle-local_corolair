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
 * Tests for audited Corolair requests.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use context_system;
use local_corolair\event\remote_request_completed;
use local_corolair\local\audited_request;

/**
 * Configurable Moodle curl stub for transport audit tests.
 */
class audited_request_curl_stub extends \curl {
    /** @var int Stubbed cURL error number. */
    private int $stuberrno;

    /** @var int Stubbed HTTP status. */
    private int $stubhttpstatus;

    /**
     * Create the stub.
     *
     * @param int $httpstatus HTTP status to return.
     * @param int $errno cURL error number to return.
     */
    public function __construct(int $httpstatus, int $errno) {
        $this->stubhttpstatus = $httpstatus;
        $this->stuberrno = $errno;
    }

    /**
     * Return the configured cURL error number.
     *
     * @return int
     */
    public function get_errno() {
        return $this->stuberrno;
    }

    /**
     * Return the configured request information.
     *
     * @return array
     */
    public function get_info() {
        return ['http_code' => $this->stubhttpstatus];
    }
}

/**
 * Audited request tests.
 */
final class audited_request_test extends \advanced_testcase {
    /**
     * Test success, HTTP failure, and transport failure events.
     *
     * @dataProvider transport_outcome_provider
     * @param mixed $response Request callback result.
     * @param int $httpstatus HTTP status.
     * @param int $errno cURL error number.
     * @param string $expectedoutcome Expected event outcome.
     */
    public function test_transport_outcomes(
        $response,
        int $httpstatus,
        int $errno,
        string $expectedoutcome
    ): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $curl = new audited_request_curl_stub($httpstatus, $errno);
        $sink = $this->redirectEvents();

        $result = audited_request::execute(
            $curl,
            static function() use ($response) {
                return $response;
            },
            audited_request::OP_WIDGET_SESSION,
            context_system::instance(),
            (int)$user->id
        );

        $this->assertSame($response, $result);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(remote_request_completed::class, $event);
        $this->assertSame(audited_request::OP_WIDGET_SESSION, $event->other['operation']);
        $this->assertSame($expectedoutcome, $event->other['outcome']);
        $this->assertSame($httpstatus, $event->other['httpstatus']);
        $this->assertSame($errno, $event->other['curlerrno']);
        $this->assertSame((int)$user->id, $event->relateduserid);
        $this->assertSafeEventPayload($event->other);
    }

    /**
     * Provide non-exception request outcomes.
     *
     * @return array[]
     */
    public static function transport_outcome_provider(): array {
        return [
            'success' => ['response', 200, 0, 'success'],
            'http failure' => ['response', 503, 0, 'http_failure'],
            'transport error number' => [false, 0, 7, 'transport_failure'],
        ];
    }

    /**
     * Test that callback exceptions are logged once and rethrown.
     */
    public function test_exception_is_logged_and_rethrown(): void {
        $this->resetAfterTest();
        $curl = new audited_request_curl_stub(0, 0);
        $sink = $this->redirectEvents();

        try {
            audited_request::execute(
                $curl,
                static function() {
                    throw new \RuntimeException('Sensitive response must not be logged.');
                },
                audited_request::OP_TRAINER_AUTH,
                context_system::instance()
            );
            $this->fail('Expected request exception was not rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Sensitive response must not be logged.', $exception->getMessage());
        }

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame('exception', $event->other['outcome']);
        $this->assertSafeEventPayload($event->other);
    }

    /**
     * Assert that only the allow-listed technical fields are present.
     *
     * @param array $other Event other data.
     * @return void
     */
    private function assertSafeEventPayload(array $other): void {
        $this->assertSame(
            ['operation', 'outcome', 'httpstatus', 'curlerrno'],
            array_keys($other)
        );
        $serialized = strtolower(json_encode($other));
        foreach (['url', 'apikey', 'authorization', 'token', 'email', 'firstname', 'lastname', 'request', 'response'] as $field) {
            $this->assertStringNotContainsString($field, $serialized);
        }
    }
}
