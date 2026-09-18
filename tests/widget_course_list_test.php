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
 * Tests for the cached list of courses that have a widget tutor.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\widget_course_list;

/**
 * Verifies which course pages may request a widget session, and how often Raison is asked.
 *
 * The list replaces a session request on every course page view, so the two properties that
 * matter are that an unlisted course never asks, and that the list itself is fetched at most
 * once per refresh interval. Every request goes through a fake transport: nothing here may
 * reach the network.
 */
final class widget_course_list_test extends \advanced_testcase {
    /** API key the tests fetch with. */
    private const KEY = 'org_test.realsecret';

    /** Fixed starting time, so freshness is exact. */
    private const NOW = 1700000000;

    /** @var int Requests the fake transports have answered. */
    private int $requests = 0;

    /**
     * A transport answering with a fixed result.
     *
     * @param int $httpstatus HTTP status to report.
     * @param mixed $body Body to report: an array is JSON-encoded, false means no response.
     * @param int $errno cURL error number to report.
     * @return callable
     */
    private function transport(int $httpstatus, $body, int $errno = 0): callable {
        return function (string $apikey) use ($httpstatus, $body, $errno): array {
            $this->requests++;
            return [
                'response' => is_array($body) ? json_encode($body) : $body,
                'errno' => $errno,
                'httpstatus' => $httpstatus,
            ];
        };
    }

    /**
     * A transport listing the given courses.
     *
     * @param array $courseids Course ids, as the backend would send them.
     * @return callable
     */
    private function listing(array $courseids): callable {
        return $this->transport(200, ['courseIds' => $courseids]);
    }

    /**
     * A transport that fails the test if it is ever called.
     *
     * @return callable
     */
    private function no_request(): callable {
        return function (): array {
            $this->fail('The cached list should have answered without a request.');
        };
    }

    /**
     * Listed courses may request a session, others may not, from one fetch.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_only_listed_courses_are_allowed(): void {
        $this->resetAfterTest();

        $this->assertTrue(widget_course_list::allows(5, self::KEY, $this->listing(['5', '7']), self::NOW));
        $this->assertFalse(widget_course_list::allows(6, self::KEY, $this->no_request(), self::NOW + 1));
        $this->assertTrue(
            widget_course_list::allows(7, self::KEY, $this->no_request(), self::NOW + widget_course_list::FRESH_FOR - 1)
        );
        $this->assertSame(1, $this->requests);
    }

    /**
     * Integer ids and duplicates are accepted as they come.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_integer_and_duplicate_ids_are_accepted(): void {
        $this->resetAfterTest();

        $this->assertTrue(widget_course_list::allows(5, self::KEY, $this->listing([5, '5', 9]), self::NOW));
        $this->assertTrue(widget_course_list::allows(9, self::KEY, $this->no_request(), self::NOW));
    }

    /**
     * A stale list is fetched again, and the new answer replaces the old one.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_stale_list_is_refreshed(): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->listing(['5']), self::NOW);

        $later = self::NOW + widget_course_list::FRESH_FOR;
        $this->assertTrue(widget_course_list::allows(6, self::KEY, $this->listing(['6']), $later));
        $this->assertFalse(widget_course_list::allows(5, self::KEY, $this->no_request(), $later + 1));
        $this->assertSame(2, $this->requests);
    }

    /**
     * A missing route falls back to requesting a session for every course, and is probed again.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_missing_route_falls_back_to_every_course(): void {
        $this->resetAfterTest();
        $notfound = $this->transport(404, ['message' => 'Cannot POST /tutor-handling/widget/moodle/courses']);

        $this->assertTrue(widget_course_list::allows(42, self::KEY, $notfound, self::NOW));
        $this->assertTrue(widget_course_list::allows(43, self::KEY, $this->no_request(), self::NOW + 1));

        // Once the route is deployed, the next probe switches back to the list.
        $later = self::NOW + widget_course_list::RECHECK_MISSING_ROUTE_AFTER;
        $this->assertTrue(widget_course_list::allows(43, self::KEY, $this->listing(['43']), $later));
        $this->assertFalse(widget_course_list::allows(42, self::KEY, $this->no_request(), $later + 1));
    }

    /**
     * Refresh failures that are not a missing route.
     *
     * @return array[] Data sets of [httpstatus, body, errno].
     */
    public static function failure_provider(): array {
        return [
            'unauthorised' => [401, ['message' => 'Invalid Corolair Api Key'], 0],
            'server error' => [500, ['message' => 'Internal server error'], 0],
            'unavailable' => [503, 'Service Unavailable', 0],
            'transport error' => [0, false, 28],
            'not json' => [200, '<html></html>', 0],
            'no course list' => [200, ['courses' => ['5']], 0],
            'non-numeric id' => [200, ['courseIds' => ['abc']], 0],
            'negative id' => [200, ['courseIds' => ['-3']], 0],
            'zero id' => [200, ['courseIds' => [0]], 0],
        ];
    }

    /**
     * A failed refresh keeps the previous list, and is retried shortly -- never the fallback.
     *
     * @dataProvider failure_provider
     * @covers \local_corolair\local\widget_course_list::allows
     * @param int $httpstatus HTTP status of the failed refresh.
     * @param mixed $body Body of the failed refresh.
     * @param int $errno cURL error number of the failed refresh.
     * @return void
     */
    public function test_failed_refresh_keeps_the_previous_list(int $httpstatus, $body, int $errno): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->listing(['5']), self::NOW);

        $stale = self::NOW + widget_course_list::FRESH_FOR;
        $this->assertTrue(widget_course_list::allows(5, self::KEY, $this->transport($httpstatus, $body, $errno), $stale));
        $this->assertFalse(widget_course_list::allows(6, self::KEY, $this->no_request(), $stale + 1));

        $retry = $stale + widget_course_list::RETRY_AFTER;
        $this->assertTrue(widget_course_list::allows(6, self::KEY, $this->listing(['6']), $retry));
        $this->assertSame(3, $this->requests);
    }

    /**
     * A failed first fetch renders no widget anywhere until the retry succeeds.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_failed_first_fetch_allows_nothing(): void {
        $this->resetAfterTest();

        $this->assertFalse(widget_course_list::allows(5, self::KEY, $this->transport(503, 'Service Unavailable'), self::NOW));
        $this->assertFalse(
            widget_course_list::allows(5, self::KEY, $this->no_request(), self::NOW + widget_course_list::RETRY_AFTER - 1)
        );
        $this->assertTrue(
            widget_course_list::allows(5, self::KEY, $this->listing(['5']), self::NOW + widget_course_list::RETRY_AFTER)
        );
    }

    /**
     * A transport that throws is a failed refresh, not an error on the page.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_throwing_transport_is_a_failed_refresh(): void {
        $this->resetAfterTest();

        $this->assertFalse(widget_course_list::allows(5, self::KEY, function (): array {
            throw new \RuntimeException('Connection reset');
        }, self::NOW));
    }

    /**
     * A failed probe while in fallback stays in fallback; it does not end it either.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_failed_probe_keeps_the_fallback(): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->transport(404, ['message' => 'Not Found']), self::NOW);

        $probe = self::NOW + widget_course_list::RECHECK_MISSING_ROUTE_AFTER;
        $this->assertTrue(widget_course_list::allows(6, self::KEY, $this->transport(503, 'Service Unavailable'), $probe));
        $this->assertTrue(widget_course_list::allows(7, self::KEY, $this->no_request(), $probe + 1));
    }

    /**
     * A list fetched with another API key is not reused.
     *
     * @covers \local_corolair\local\widget_course_list::allows
     * @return void
     */
    public function test_changed_key_fetches_its_own_list(): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->listing(['5']), self::NOW);

        $this->assertFalse(widget_course_list::allows(5, 'org_test.rotatedsecret', $this->listing(['6']), self::NOW + 1));
        $this->assertTrue(widget_course_list::allows(6, 'org_test.rotatedsecret', $this->no_request(), self::NOW + 2));
        $this->assertSame(2, $this->requests);
    }

    /**
     * A course whose session request found no tutor is dropped until the next refresh.
     *
     * @covers \local_corolair\local\widget_course_list::forget
     * @return void
     */
    public function test_forgotten_course_is_no_longer_allowed(): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->listing(['5', '6']), self::NOW);

        widget_course_list::forget(5, self::KEY);

        $this->assertFalse(widget_course_list::allows(5, self::KEY, $this->no_request(), self::NOW + 1));
        $this->assertTrue(widget_course_list::allows(6, self::KEY, $this->no_request(), self::NOW + 1));
    }

    /**
     * Forgetting a course changes nothing while the route is missing.
     *
     * In fallback every course is asked about, and a 404 from the session route is the normal
     * answer for most of them; it must not be mistaken for a listed course losing its tutor.
     *
     * @covers \local_corolair\local\widget_course_list::forget
     * @return void
     */
    public function test_forget_leaves_the_fallback_alone(): void {
        $this->resetAfterTest();
        widget_course_list::allows(5, self::KEY, $this->transport(404, ['message' => 'Not Found']), self::NOW);

        widget_course_list::forget(5, self::KEY);

        $this->assertTrue(widget_course_list::allows(5, self::KEY, $this->no_request(), self::NOW + 1));
    }
}
