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
 * Tests for the outbound request audit trail.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\event\remote_request_completed;
use local_corolair\local\audited_request;

/**
 * Verifies every outbound request is recorded exactly once, and without secrets.
 *
 * The audit event is the only evidence an administrator has that the plugin talked to
 * Raison, so it has to be emitted on every path -- including the one where the request
 * threw -- and it must never carry the URL, the bearer token, or any personal data that
 * happened to be in the request.
 */
final class audited_request_test extends \advanced_testcase {
    /**
     * Build a curl double that reports a fixed transport result.
     *
     * The real client is never asked to make a request: audited_request::execute() takes
     * the request itself as a callback and only reads get_errno()/get_info() off the
     * client afterwards, which is exactly what this overrides.
     *
     * @param int $httpstatus HTTP status the double reports.
     * @param int $errno cURL error number the double reports.
     * @return \curl
     */
    private function make_curl(int $httpstatus, int $errno): \curl {
        return new class ($httpstatus, $errno) extends \curl {
            /** @var int Stubbed cURL error number. */
            private int $stuberrno;

            /** @var int Stubbed HTTP status. */
            private int $stubhttpstatus;

            /**
             * Record the result this double reports.
             *
             * @param int $httpstatus HTTP status to report.
             * @param int $errno cURL error number to report.
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
        };
    }

    /**
     * Assert the event carries only the four allow-listed technical fields.
     *
     * @param array $other Event "other" payload.
     * @return void
     */
    private function assert_payload_is_safe(array $other): void {
        $this->assertSame(
            ['operation', 'outcome', 'httpstatus', 'curlerrno'],
            array_keys($other),
            'The audit payload gained a field; check it cannot carry request content.'
        );
        // Only the values are scanned. Two of the field *names* legitimately contain
        // substrings a naive scan would flag ("curlerrno" contains "url").
        foreach ($other as $field => $value) {
            $this->assertTrue(
                is_int($value) || is_string($value),
                "The audit field {$field} is no longer a scalar; a structure could carry content."
            );
            $haystack = strtolower((string)$value);
            foreach (['://', '@', 'bearer', 'corolair.dev', 'apikey'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $haystack,
                    "The audit field {$field} carries request content."
                );
            }
        }
    }

    /**
     * Transport results and the outcome each one must be recorded as.
     *
     * @return array[] Data sets of [response, http status, errno, expected outcome].
     */
    public static function transport_outcome_provider(): array {
        return [
            'success' => ['{"ok":true}', 200, 0, 'success'],
            'created' => ['{"ok":true}', 201, 0, 'success'],
            'last success status' => ['{"ok":true}', 299, 0, 'success'],
            'client error' => ['{"error":"nope"}', 400, 0, 'http_failure'],
            'unauthorized' => ['', 401, 0, 'http_failure'],
            'server error' => ['', 503, 0, 'http_failure'],
            'redirect is not success' => ['', 302, 0, 'http_failure'],
            'no status at all' => ['', 0, 0, 'http_failure'],
            'curl error number' => [false, 0, 7, 'transport_failure'],
            'false body outranks a 200' => [false, 200, 0, 'transport_failure'],
            'errno outranks a 200' => ['{"ok":true}', 200, 28, 'transport_failure'],
        ];
    }

    /**
     * Each transport result is classified and recorded once.
     *
     * @dataProvider transport_outcome_provider
     * @covers \local_corolair\local\audited_request::execute
     * @param mixed $response Value the request callback returns.
     * @param int $httpstatus HTTP status reported by the client.
     * @param int $errno cURL error number reported by the client.
     * @param string $expectedoutcome Outcome the event must record.
     * @return void
     */
    public function test_transport_outcomes($response, int $httpstatus, int $errno, string $expectedoutcome): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $curl = $this->make_curl($httpstatus, $errno);
        $sink = $this->redirectEvents();

        $result = audited_request::execute(
            $curl,
            static function () use ($response) {
                return $response;
            },
            audited_request::OP_WIDGET_SESSION,
            \context_system::instance(),
            (int)$user->id
        );

        $events = $sink->get_events();
        $sink->close();

        $this->assertSame($response, $result, 'The response must be passed through untouched.');
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(remote_request_completed::class, $event);
        $this->assertSame(audited_request::OP_WIDGET_SESSION, $event->other['operation']);
        $this->assertSame($expectedoutcome, $event->other['outcome']);
        $this->assertSame($httpstatus, $event->other['httpstatus']);
        $this->assertSame($errno, $event->other['curlerrno']);
        $this->assertSame((int)$user->id, (int)$event->relateduserid);
        $this->assert_payload_is_safe($event->other);
    }

    /**
     * A request that throws is still audited, and the exception still reaches the caller.
     *
     * Swallowing it would leave the caller believing the request succeeded; not auditing
     * it would leave the loudest failure as the only one with no record.
     *
     * @covers \local_corolair\local\audited_request::execute
     * @return void
     */
    public function test_exception_is_audited_and_rethrown(): void {
        $this->resetAfterTest();

        $curl = $this->make_curl(0, 0);
        $sink = $this->redirectEvents();

        try {
            audited_request::execute(
                $curl,
                static function () {
                    throw new \RuntimeException('Sensitive response must not be logged.');
                },
                audited_request::OP_TRAINER_AUTH,
                \context_system::instance()
            );
            $this->fail('The request exception was swallowed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Sensitive response must not be logged.', $exception->getMessage());
        }

        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(remote_request_completed::class, $event);
        $this->assertSame(audited_request::OP_TRAINER_AUTH, $event->other['operation']);
        $this->assertSame('exception', $event->other['outcome']);
        $this->assert_payload_is_safe($event->other);
        $this->assertStringNotContainsString(
            'Sensitive response',
            (string)json_encode($event->get_data()),
            'The exception message must not leak into the audit record.'
        );
    }

    /**
     * Every operation constant is accepted by the allow-list.
     *
     * The list and the constants are maintained separately, so a constant added without
     * its list entry would throw on the first real request rather than in a test.
     *
     * @covers \local_corolair\local\audited_request::execute
     * @return void
     */
    public function test_every_declared_operation_is_allowed(): void {
        $this->resetAfterTest();

        $operations = (new \ReflectionClass(audited_request::class))->getConstants();
        $declared = [];
        foreach ($operations as $name => $value) {
            if (strpos($name, 'OP_') === 0) {
                $declared[] = $value;
            }
        }
        $this->assertNotEmpty($declared, 'Precondition: the class must declare operation constants.');

        $sink = $this->redirectEvents();
        foreach ($declared as $operation) {
            audited_request::execute(
                $this->make_curl(200, 0),
                static function () {
                    return '{}';
                },
                $operation,
                \context_system::instance()
            );
        }
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(count($declared), $events);
        $this->assertSame($declared, array_map(static function ($event) {
            return $event->other['operation'];
        }, array_values($events)));
    }

    /**
     * An operation outside the allow-list is a programming error, not a runtime outcome.
     *
     * @covers \local_corolair\local\audited_request::execute
     * @return void
     */
    public function test_unknown_operation_is_rejected(): void {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        try {
            audited_request::execute(
                $this->make_curl(200, 0),
                static function () {
                    return '{}';
                },
                'not_an_operation',
                \context_system::instance()
            );
            $this->fail('An unknown operation should not be auditable.');
        } catch (\coding_exception $exception) {
            $this->assertStringContainsString('audit operation', $exception->getMessage());
        }
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events, 'A rejected operation must not produce an audit record.');
    }

    /**
     * Values that must not be recorded as a related user.
     *
     * @return array[] Data sets of [related user id].
     */
    public static function absent_related_user_provider(): array {
        return [
            'not supplied' => [null],
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /**
     * A request with no real related user must not claim one.
     *
     * @dataProvider absent_related_user_provider
     * @covers \local_corolair\local\audited_request::execute
     * @param int|null $relateduserid Related user identifier passed by the caller.
     * @return void
     */
    public function test_related_user_is_omitted_when_absent(?int $relateduserid): void {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        audited_request::execute(
            $this->make_curl(200, 0),
            static function () {
                return '{}';
            },
            audited_request::OP_ORGANIZATION_DEREGISTER,
            \context_system::instance(),
            $relateduserid
        );
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertNull(reset($events)->relateduserid);
    }

    /**
     * The event is recorded against the context the caller supplied.
     *
     * Privacy operations are audited per course, so a hard-coded system context would
     * make the record useless for scoping.
     *
     * @covers \local_corolair\local\audited_request::execute
     * @return void
     */
    public function test_event_is_recorded_against_the_supplied_context(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $sink = $this->redirectEvents();
        audited_request::execute(
            $this->make_curl(200, 0),
            static function () {
                return '{}';
            },
            audited_request::OP_PRIVACY_EXPORT,
            $context
        );
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame((int)$context->id, (int)reset($events)->contextid);
    }
}
