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
 * The site's list of courses that have a Raison widget tutor.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Decides, from one cached site-wide list, whether a course page may request a widget session.
 *
 * Why this exists: a widget session request sends the viewer's identity to Raison and writes
 * an audit event, and it used to happen on every course and activity page view of every
 * logged-in user -- although most courses have no tutor and the answer was a 404. Raison now
 * serves the list of courses that do have one; fetching it once every five minutes for the
 * whole site replaces all of those requests.
 *
 * The cached value carries its own freshness instead of a MUC ttl, because the list must
 * outlive it: when a refresh fails the previous list keeps being served, which an expired
 * ttl entry could not do. It also carries a fingerprint of the API key and services host, so
 * a changed key -- by rotation, setup, CLI or forced setting alike -- reads as an empty
 * cache, without a purge at every place that can write the key.
 *
 * A 404 from the list route means the backend does not have the route. Only then does the
 * plugin fall back to what it did before the list existed -- a session request on every
 * course page -- and it probes the route again every few minutes. Raison answers every
 * credential problem on that route with 401, so a 404 cannot be a misconfigured site. Any
 * other failure keeps the previous list, or renders no widget when there is none: neither is
 * a reason to go back to requesting a session per page view.
 */
final class widget_course_list {
    /** Seconds a fetched list is used before it is refreshed. */
    public const FRESH_FOR = 300;

    /** Seconds before a failed refresh is retried. */
    public const RETRY_AFTER = 60;

    /** Seconds between probes of the list route while it is missing. */
    public const RECHECK_MISSING_ROUTE_AFTER = 300;

    /** Raison route serving the list, below the services host. */
    private const ENDPOINT_PATH = 'tutor-handling/widget/moodle/courses';

    /** Most course ids a response may carry before it is rejected as malformed. */
    private const MAX_COURSES = 100000;

    /** Cache area, declared in db/caches.php. */
    private const CACHE_AREA = 'widgetcourses';

    /** The single key the site-wide list lives under. */
    private const CACHE_KEY = 'site';

    /** Lock type serialising refreshes, so a stale list is fetched by one request at a time. */
    private const LOCK_TYPE = 'local_corolair_widget_courses';

    /**
     * Whether a widget session may be requested for a course.
     *
     * Refreshes the list first when it is stale or missing, from whichever request notices,
     * without waiting on a refresh another request is already making.
     *
     * The optional callbacks make the refresh testable without a remote request. Production
     * callers must omit both.
     *
     * @param int $courseid Course the page belongs to.
     * @param string $apikey The site's API key, as returned by api_key::get().
     * @param callable|null $transport Test request callback returning response, errno and httpstatus.
     * @param int|null $now Test clock, in seconds since the epoch.
     * @return bool True when the course is listed, or when the list route is missing.
     */
    public static function allows(
        int $courseid,
        string $apikey,
        ?callable $transport = null,
        ?int $now = null
    ): bool {
        $now = $now ?? time();
        $fingerprint = self::fingerprint($apikey);
        $state = self::load($fingerprint);
        if ($state === null || $now >= $state['nextrefresh']) {
            $state = self::refresh($state, $apikey, $fingerprint, $now, $transport);
        }
        if ($state === null) {
            // Nothing cached yet, and another request is fetching it: no widget this once.
            return false;
        }
        if ($state['legacy']) {
            return true;
        }
        return in_array($courseid, $state['courseids'] ?? [], true);
    }

    /**
     * Drop a course whose session request found no tutor after all.
     *
     * A tutor detached from a course stays listed until the next refresh; dropping the course
     * here stops the requests for it at once instead of repeating a 404 for five minutes.
     * Not locked: two requests racing here can only both remove the same course.
     *
     * @param int $courseid Course the session request answered 404 for.
     * @param string $apikey The site's API key, as returned by api_key::get().
     * @return void
     */
    public static function forget(int $courseid, string $apikey): void {
        $state = self::load(self::fingerprint($apikey));
        if ($state === null || $state['legacy'] || $state['courseids'] === null) {
            return;
        }
        $remaining = array_values(array_diff($state['courseids'], [$courseid]));
        if (count($remaining) === count($state['courseids'])) {
            return;
        }
        $state['courseids'] = $remaining;
        self::cache()->set(self::CACHE_KEY, $state);
    }

    /**
     * Fetch the list under the refresh lock and store the resulting state.
     *
     * @param array|null $state State read before the lock, returned when the lock is taken.
     * @param string $apikey The site's API key.
     * @param string $fingerprint Fingerprint of the key and services host.
     * @param int $now Current time.
     * @param callable|null $transport Test request callback.
     * @return array|null The state to decide with, or null when there is none yet.
     */
    private static function refresh(
        ?array $state,
        string $apikey,
        string $fingerprint,
        int $now,
        ?callable $transport
    ): ?array {
        $lock = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE)->get_lock('refresh', 0);
        if (!$lock) {
            return $state;
        }
        try {
            // Read again under the lock: the previous holder may have just stored a fresh list.
            $current = self::load($fingerprint);
            if ($current !== null && $now < $current['nextrefresh']) {
                return $current;
            }
            $next = self::next_state($current, self::fetch($apikey, $transport), $fingerprint, $now);
            self::cache()->set(self::CACHE_KEY, $next);
            return $next;
        } finally {
            $lock->release();
        }
    }

    /**
     * The state that follows a fetch.
     *
     * @param array|null $previous State before the fetch, or null when there was none.
     * @param array $fetched Result of fetch().
     * @param string $fingerprint Fingerprint of the key and services host.
     * @param int $now Current time.
     * @return array
     */
    private static function next_state(?array $previous, array $fetched, string $fingerprint, int $now): array {
        if ($fetched['status'] === 'listed') {
            return [
                'fingerprint' => $fingerprint,
                'courseids' => $fetched['courseids'],
                'legacy' => false,
                'nextrefresh' => $now + self::FRESH_FOR,
            ];
        }
        if ($fetched['status'] === 'missing') {
            return [
                'fingerprint' => $fingerprint,
                'courseids' => null,
                'legacy' => true,
                'nextrefresh' => $now + self::RECHECK_MISSING_ROUTE_AFTER,
            ];
        }
        // Failed: whatever was decided before stands, and is questioned again shortly.
        return [
            'fingerprint' => $fingerprint,
            'courseids' => $previous['courseids'] ?? null,
            'legacy' => $previous['legacy'] ?? false,
            'nextrefresh' => $now + self::RETRY_AFTER,
        ];
    }

    /**
     * Ask Raison for the list and classify the answer.
     *
     * @param string $apikey The site's API key.
     * @param callable|null $transport Test request callback.
     * @return array{status: string, courseids?: int[]} Status listed, missing or failed.
     */
    private static function fetch(string $apikey, ?callable $transport): array {
        try {
            $result = ($transport ?? [self::class, 'request'])($apikey);
        } catch (\Throwable) {
            return ['status' => 'failed'];
        }
        $response = $result['response'] ?? false;
        $httpstatus = (int)($result['httpstatus'] ?? 0);
        if ($response === false || (int)($result['errno'] ?? -1) !== 0) {
            return ['status' => 'failed'];
        }
        if ($httpstatus === 404) {
            return ['status' => 'missing'];
        }
        if ($httpstatus < 200 || $httpstatus >= 300) {
            return ['status' => 'failed'];
        }
        $courseids = self::parse_course_ids((string)$response);
        if ($courseids === null) {
            return ['status' => 'failed'];
        }
        return ['status' => 'listed', 'courseids' => $courseids];
    }

    /**
     * Make one audited request for the list.
     *
     * @param string $apikey The site's API key.
     * @return array Request result without credentials or request headers.
     */
    private static function request(string $apikey): array {
        $postdata = '{}';
        $curl = new \curl();
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $postdata, $options) {
                return $curl->post(environment::url('services', self::ENDPOINT_PATH), $postdata, $options);
            },
            audited_request::OP_WIDGET_COURSES,
            \context_system::instance()
        );
        $info = $curl->get_info();

        return [
            'response' => $response,
            'errno' => (int)$curl->get_errno(),
            'httpstatus' => (int)($info['http_code'] ?? 0),
        ];
    }

    /**
     * Validate a list response and return its course ids.
     *
     * @param string $response Response body.
     * @return int[]|null Distinct positive course ids, or null when the body is malformed.
     */
    private static function parse_course_ids(string $response): ?array {
        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        $values = is_array($data) ? ($data['courseIds'] ?? null) : null;
        if (!is_array($values) || count($values) > self::MAX_COURSES) {
            return null;
        }
        $courseids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int)$value <= 0) {
                return null;
            }
            $courseids[(int)$value] = (int)$value;
        }
        return array_values($courseids);
    }

    /**
     * Read the stored state, if it belongs to this key and services host.
     *
     * @param string $fingerprint Fingerprint of the key and services host.
     * @return array|null
     */
    private static function load(string $fingerprint): ?array {
        $state = self::cache()->get(self::CACHE_KEY);
        if (
            !is_array($state) ||
            ($state['fingerprint'] ?? null) !== $fingerprint ||
            !is_int($state['nextrefresh'] ?? null) ||
            !is_bool($state['legacy'] ?? null) ||
            !(is_array($state['courseids'] ?? null) || ($state['courseids'] ?? null) === null)
        ) {
            return null;
        }
        return $state;
    }

    /**
     * Identify the key and the host it is used against, without storing the key.
     *
     * @param string $apikey The site's API key.
     * @return string
     */
    private static function fingerprint(string $apikey): string {
        return hash('sha256', environment::host('services') . '|' . $apikey);
    }

    /**
     * The cache holding the list.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('local_corolair', self::CACHE_AREA);
    }
}
