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
 * Raison's answer, per person, on whether they get the global assistant.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Asks Raison whether this person gets the global assistant, and remembers the answer.
 *
 * Who gets it is Raison's rule alone -- today, a learner it knows with at least one active
 * assistant -- so the plugin sends the person's Moodle id, email and names and renders what comes
 * back. Raison answers yes with a widget token, or no, and says each time how long the answer
 * holds. The answer is kept in this person's Moodle session for that long, so a person is asked
 * about once a session rather than once a page.
 *
 * Anything else -- no answer, an error, a 404 from a backend without the route, a malformed
 * body -- is a no for RETRY_AFTER seconds: an outage must not turn into one request per page view.
 */
final class global_assistant_session {
    /** Seconds a failed request counts as a no before Raison is asked again. */
    public const RETRY_AFTER = 60;

    /** Seconds a token must still be valid to be put on a page. */
    public const MIN_TOKEN_LIFE = 60;

    /** Raison route answering the question, below the services host. */
    private const ENDPOINT_PATH = 'tutor-handling/widget/moodle/global/session';

    /** Session cache area, declared in db/caches.php. */
    private const CACHE_AREA = 'globalassistant';

    /** Longest token accepted, as for the course assistant's session. */
    private const MAX_TOKEN_LENGTH = 8192;

    /** Longest token lifetime accepted, in seconds. */
    private const MAX_EXPIRES_IN = 3600;

    /** Shortest time an answer may be kept, in seconds. */
    private const MIN_REUSE_FOR = 60;

    /** Longest time an answer may be kept, in seconds. */
    private const MAX_REUSE_FOR = 3600;

    /** A Raison organization id, as Raison validates it. */
    private const ORGANIZATION_ID = '/^[A-Za-z0-9_-]{1,128}$/';

    /**
     * The global assistant for this person, or null when they do not get it.
     *
     * The optional callbacks make the request testable without a remote call. Production callers
     * must omit both.
     *
     * @param \stdClass $user The person viewing the page.
     * @param string $apikey The site's API key, as returned by api_key::get().
     * @param \context $context The page's context, recorded with the request.
     * @param callable|null $transport Test request callback, called with the key and the body,
     *      returning response, errno and httpstatus.
     * @param int|null $now Test clock, in seconds since the epoch.
     * @return array|null ['token' => string, 'organizationid' => string], or null.
     */
    public static function get(
        \stdClass $user,
        string $apikey,
        \context $context,
        ?callable $transport = null,
        ?int $now = null
    ): ?array {
        $now = $now ?? time();
        $key = 'user' . (int)$user->id;
        $fingerprint = self::fingerprint($apikey);

        $state = self::load($key, $fingerprint);
        $holds = $state !== null && $now < $state['reuseuntil'];
        if ($holds && !$state['access']) {
            return null;
        }
        // A yes is asked again early when its token would not outlive the page by long enough.
        if (!$holds || !self::token_lives($state, $now)) {
            $state = self::next_state(self::fetch($user, $apikey, $context, $transport), $fingerprint, $now);
            self::cache()->set($key, $state);
        }
        if (!$state['access'] || !self::token_lives($state, $now)) {
            return null;
        }
        return ['token' => $state['token'], 'organizationid' => $state['organizationid']];
    }

    /**
     * Whether a yes carries a token that lives long enough to go on a page.
     *
     * @param array $state A stored answer.
     * @param int $now Current time.
     * @return bool
     */
    private static function token_lives(array $state, int $now): bool {
        return $state['access'] && $state['expiresat'] - $now >= self::MIN_TOKEN_LIFE;
    }

    /**
     * The answer to store after a request.
     *
     * @param array $answer Result of fetch().
     * @param string $fingerprint Fingerprint of the key and services host.
     * @param int $now Current time.
     * @return array
     */
    private static function next_state(array $answer, string $fingerprint, int $now): array {
        if (($answer['status'] ?? '') === 'yes') {
            return [
                'fingerprint' => $fingerprint,
                'access' => true,
                'token' => $answer['token'],
                'organizationid' => $answer['organizationid'],
                'expiresat' => $now + $answer['expiresin'],
                'reuseuntil' => $now + $answer['reusefor'],
            ];
        }
        return [
            'fingerprint' => $fingerprint,
            'access' => false,
            'token' => null,
            'organizationid' => null,
            'expiresat' => 0,
            'reuseuntil' => $now + (($answer['status'] ?? '') === 'no' ? $answer['reusefor'] : self::RETRY_AFTER),
        ];
    }

    /**
     * Ask Raison and classify the answer.
     *
     * @param \stdClass $user The person viewing the page.
     * @param string $apikey The site's API key.
     * @param \context $context The page's context.
     * @param callable|null $transport Test request callback.
     * @return array Status yes (with token, organizationid, expiresin, reusefor), no (with
     *      reusefor) or failed.
     */
    private static function fetch(\stdClass $user, string $apikey, \context $context, ?callable $transport): array {
        $body = json_encode([
            'moodleId' => (string)$user->id,
            'email' => (string)($user->email ?? ''),
            'firstName' => (string)($user->firstname ?? ''),
            'lastName' => (string)($user->lastname ?? ''),
        ]);
        if ($body === false) {
            return ['status' => 'failed'];
        }
        try {
            $result = $transport !== null
                ? $transport($apikey, $body)
                : self::request($apikey, $body, $context, (int)$user->id);
        } catch (\Throwable) {
            return ['status' => 'failed'];
        }
        $response = $result['response'] ?? false;
        $httpstatus = (int)($result['httpstatus'] ?? 0);
        if ($response === false || (int)($result['errno'] ?? -1) !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
            return ['status' => 'failed'];
        }
        try {
            $data = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['status' => 'failed'];
        }
        return self::parse($data);
    }

    /**
     * Validate a decoded answer. Anything not exactly as agreed is a failure, never a yes.
     *
     * @param mixed $data Decoded response body.
     * @return array As for fetch().
     */
    private static function parse($data): array {
        if (!is_array($data)) {
            return ['status' => 'failed'];
        }
        $reusefor = $data['reuseFor'] ?? null;
        if (!is_int($reusefor) || $reusefor < self::MIN_REUSE_FOR || $reusefor > self::MAX_REUSE_FOR) {
            return ['status' => 'failed'];
        }
        $access = $data['access'] ?? null;
        if ($access === false) {
            return ['status' => 'no', 'reusefor' => $reusefor];
        }
        if ($access !== true) {
            return ['status' => 'failed'];
        }
        $token = $data['token'] ?? null;
        $expiresin = $data['expiresIn'] ?? null;
        $organizationid = $data['organizationId'] ?? null;
        if (
            !is_string($token) ||
            $token === '' ||
            strlen($token) > self::MAX_TOKEN_LENGTH ||
            !is_int($expiresin) ||
            $expiresin <= 0 ||
            $expiresin > self::MAX_EXPIRES_IN ||
            !is_string($organizationid) ||
            !preg_match(self::ORGANIZATION_ID, $organizationid)
        ) {
            return ['status' => 'failed'];
        }
        return [
            'status' => 'yes',
            'token' => $token,
            'organizationid' => $organizationid,
            'expiresin' => $expiresin,
            'reusefor' => $reusefor,
        ];
    }

    /**
     * Make one audited request.
     *
     * @param string $apikey The site's API key.
     * @param string $body The JSON body.
     * @param \context $context The page's context.
     * @param int $userid The person the request is about.
     * @return array Request result without credentials or request headers.
     */
    private static function request(string $apikey, string $body, \context $context, int $userid): array {
        $curl = new \curl();
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_TIMEOUT' => 60,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $body, $options) {
                return $curl->post(environment::url('services', self::ENDPOINT_PATH), $body, $options);
            },
            audited_request::OP_GLOBAL_ASSISTANT_SESSION,
            $context,
            $userid
        );
        $info = $curl->get_info();

        return [
            'response' => $response,
            'errno' => (int)$curl->get_errno(),
            'httpstatus' => (int)($info['http_code'] ?? 0),
        ];
    }

    /**
     * Read this person's stored answer, if it was given for this key and services host.
     *
     * @param string $key The person's cache key.
     * @param string $fingerprint Fingerprint of the key and services host.
     * @return array|null
     */
    private static function load(string $key, string $fingerprint): ?array {
        $state = self::cache()->get($key);
        if (
            !is_array($state) ||
            ($state['fingerprint'] ?? null) !== $fingerprint ||
            !is_bool($state['access'] ?? null) ||
            !is_int($state['reuseuntil'] ?? null) ||
            !is_int($state['expiresat'] ?? null) ||
            ($state['access'] && (!is_string($state['token'] ?? null) || !is_string($state['organizationid'] ?? null)))
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
     * The session cache holding the answers.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('local_corolair', self::CACHE_AREA);
    }
}
