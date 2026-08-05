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
 * Tests for the retry policy of remote organization deregistration.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\organization_deregistration;

/**
 * Verifies deregistration retries exactly what is worth retrying, and no more.
 *
 * This runs synchronously inside the uninstall request, blocking core's own cleanup, so
 * the retry policy is a latency budget as much as a correctness one: retry what a second
 * attempt could plausibly fix, give up immediately on anything else. Success is defined
 * narrowly -- only an explicit "disconnected" acknowledgment counts -- because reporting
 * a deletion that never happened is the one outcome with privacy consequences.
 */
final class organization_deregistration_test extends \advanced_testcase {
    /**
     * Run deregistration against a scripted sequence of attempt results.
     *
     * @param array[] $results One result per attempt, in order.
     * @return array{0: bool, 1: int, 2: int[]} Outcome, attempts made, and delays slept.
     */
    private function run_with(array $results): array {
        $attempts = 0;
        $sleeps = [];

        $outcome = organization_deregistration::execute(
            'org_test.secret',
            'https://moodle.example.com',
            function () use (&$attempts, $results) {
                $result = $results[$attempts] ?? ['response' => false, 'errno' => 7, 'httpstatus' => 0];
                $attempts++;
                if ($result instanceof \Throwable) {
                    throw $result;
                }
                return $result;
            },
            function (int $seconds) use (&$sleeps): void {
                $sleeps[] = $seconds;
            }
        );

        return [$outcome, $attempts, $sleeps];
    }

    /**
     * Build a well-formed attempt result.
     *
     * @param int $httpstatus HTTP status.
     * @param mixed $body Response body.
     * @param int $errno cURL error number.
     * @return array
     */
    private function attempt_result(int $httpstatus, $body, int $errno = 0): array {
        return ['response' => $body, 'errno' => $errno, 'httpstatus' => $httpstatus];
    }

    /**
     * A confirmed disconnect on the first attempt stops immediately.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_confirmation_stops_at_once(): void {
        [$outcome, $attempts, $sleeps] = $this->run_with([
            $this->attempt_result(200, json_encode(['status' => 'disconnected'])),
        ]);

        $this->assertTrue($outcome);
        $this->assertSame(1, $attempts);
        $this->assertSame([], $sleeps);
    }

    /**
     * A transient failure is retried, and a later confirmation still counts.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_confirmation_after_a_transient_failure(): void {
        [$outcome, $attempts, $sleeps] = $this->run_with([
            $this->attempt_result(503, ''),
            $this->attempt_result(200, json_encode(['status' => 'disconnected'])),
        ]);

        $this->assertTrue($outcome);
        $this->assertSame(2, $attempts);
        $this->assertSame([1], $sleeps, 'Only the gap before the successful attempt should be slept.');
    }

    /**
     * An empty API key is not worth a single request.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_missing_credential_makes_no_request(): void {
        $attempted = false;

        $outcome = organization_deregistration::execute(
            '',
            'https://moodle.example.com',
            function () use (&$attempted): array {
                $attempted = true;
                return ['response' => '{}', 'errno' => 0, 'httpstatus' => 200];
            },
            function (): void {
                $this->fail('Nothing should be retried without a credential.');
            }
        );

        $this->assertFalse($outcome);
        $this->assertFalse($attempted);
    }

    /**
     * Results that should be retried until the attempt limit is reached.
     *
     * @return array[] Data sets of [attempt result].
     */
    public static function retryable_result_provider(): array {
        return [
            'transport failure' => [['response' => false, 'errno' => 7, 'httpstatus' => 0]],
            'no http status' => [['response' => '', 'errno' => 0, 'httpstatus' => 0]],
            'request timeout' => [['response' => '', 'errno' => 0, 'httpstatus' => 408]],
            'rate limited' => [['response' => '', 'errno' => 0, 'httpstatus' => 429]],
            'server error' => [['response' => '', 'errno' => 0, 'httpstatus' => 500]],
            'bad gateway' => [['response' => '', 'errno' => 0, 'httpstatus' => 502]],
            'accepted but not confirmed' => [
                ['response' => '{"status":"pending"}', 'errno' => 0, 'httpstatus' => 200],
            ],
            'accepted but unparseable' => [
                ['response' => 'not json', 'errno' => 0, 'httpstatus' => 200],
            ],
            'accepted but not an object' => [
                ['response' => '"disconnected"', 'errno' => 0, 'httpstatus' => 200],
            ],
        ];
    }

    /**
     * A retryable failure uses all three attempts, with the documented backoff.
     *
     * lang/en disclosureuninstall promises administrators "up to three times", so the
     * attempt count is part of what they were told would happen.
     *
     * @dataProvider retryable_result_provider
     * @covers \local_corolair\local\organization_deregistration::execute
     * @param array $result Attempt result to repeat.
     * @return void
     */
    public function test_retryable_failures_use_every_attempt(array $result): void {
        [$outcome, $attempts, $sleeps] = $this->run_with([$result, $result, $result]);

        $this->assertFalse($outcome);
        $this->assertSame(3, $attempts);
        $this->assertSame([1, 2], $sleeps, 'Backoff runs between attempts, never after the last one.');
    }

    /**
     * Results that a second attempt could not possibly fix.
     *
     * @return array[] Data sets of [attempt result].
     */
    public static function terminal_result_provider(): array {
        return [
            'rejected credential' => [['response' => '{"error":"unauthorized"}', 'errno' => 0, 'httpstatus' => 401]],
            'forbidden' => [['response' => '', 'errno' => 0, 'httpstatus' => 403]],
            'unknown organization' => [['response' => '', 'errno' => 0, 'httpstatus' => 404]],
            'malformed request' => [['response' => '', 'errno' => 0, 'httpstatus' => 400]],
            'conflict' => [['response' => '', 'errno' => 0, 'httpstatus' => 409]],
        ];
    }

    /**
     * A terminal failure gives up after one attempt.
     *
     * Retrying a rejected credential twice more would add seconds to every uninstall of
     * a site whose key was already revoked, and could never succeed.
     *
     * @dataProvider terminal_result_provider
     * @covers \local_corolair\local\organization_deregistration::execute
     * @param array $result Attempt result.
     * @return void
     */
    public function test_terminal_failures_are_not_retried(array $result): void {
        [$outcome, $attempts, $sleeps] = $this->run_with([$result]);

        $this->assertFalse($outcome);
        $this->assertSame(1, $attempts);
        $this->assertSame([], $sleeps);
    }

    /**
     * An exception from the request is treated as a retryable transport failure.
     *
     * This runs during uninstall, where nothing may escape: an exception reaching core
     * aborts the uninstall before it removes tokens and configuration.
     *
     * @covers \local_corolair\local\organization_deregistration::execute
     * @return void
     */
    public function test_a_throwing_request_is_absorbed_and_retried(): void {
        [$outcome, $attempts, $sleeps] = $this->run_with([
            new \RuntimeException('DNS is down'),
            new \RuntimeException('DNS is still down'),
            new \RuntimeException('DNS remains down'),
        ]);

        $this->assertFalse($outcome);
        $this->assertSame(3, $attempts);
        $this->assertSame([1, 2], $sleeps);
    }

    /**
     * A 2xx body must say "disconnected" and nothing else counts.
     *
     * @return array[] Data sets of [response body, whether it confirms].
     */
    public static function confirmation_body_provider(): array {
        return [
            'exact status' => [json_encode(['status' => 'disconnected']), true],
            'with extra fields' => [json_encode(['status' => 'disconnected', 'id' => 'x']), true],
            'different status' => [json_encode(['status' => 'deleted']), false],
            'uppercase status' => [json_encode(['status' => 'DISCONNECTED']), false],
            'missing status' => [json_encode(['ok' => true]), false],
            'empty object' => ['{}', false],
            'empty body' => ['', false],
            'null body' => ['null', false],
        ];
    }

    /**
     * Only an explicit disconnected acknowledgment is treated as success.
     *
     * @dataProvider confirmation_body_provider
     * @covers \local_corolair\local\organization_deregistration::execute
     * @param string $body Response body returned with HTTP 200.
     * @param bool $confirms Whether the body should be accepted as confirmation.
     * @return void
     */
    public function test_only_an_explicit_acknowledgment_confirms(string $body, bool $confirms): void {
        [$outcome, $attempts] = $this->run_with(array_fill(0, 3, $this->attempt_result(200, $body)));

        $this->assertSame($confirms, $outcome);
        $this->assertSame($confirms ? 1 : 3, $attempts);
    }
}
