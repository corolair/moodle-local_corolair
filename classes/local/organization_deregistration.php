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
 * Organization deregistration with bounded synchronous retries.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Deregisters this Moodle organization while its API credential is still available.
 */
final class organization_deregistration {
    /** Remote deregistration endpoint. */
    private const ENDPOINT = 'https://services.corolair.dev/moodle-integration/v2/plugin/organization/deregister';

    /** Maximum number of synchronous attempts. */
    private const MAX_ATTEMPTS = 3;

    /** Delay before attempts two and three, in seconds. */
    private const RETRY_DELAYS = [1, 2];

    /**
     * Request remote deregistration and require an explicit disconnected response.
     *
     * The optional callbacks make retry behaviour testable without making remote
     * requests or adding delays. Production callers must omit both callbacks.
     *
     * @param string $apikey Organization API key.
     * @param string $moodlebaseurl Moodle site URL used to identify the organization.
     * @param callable|null $attemptcallback Test request callback returning response, errno, and httpstatus.
     * @param callable|null $sleepcallback Test sleep callback accepting a delay in seconds.
     * @return bool Whether remote deregistration was explicitly confirmed.
     */
    public static function execute(
        string $apikey,
        string $moodlebaseurl,
        ?callable $attemptcallback = null,
        ?callable $sleepcallback = null
    ): bool {
        if ($apikey === '') {
            return false;
        }

        $attemptcallback = $attemptcallback ?? [self::class, 'request'];
        $sleepcallback = $sleepcallback ?? 'sleep';

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $result = $attemptcallback($apikey, $moodlebaseurl);
            } catch (\Throwable) {
                $result = [
                    'response' => false,
                    'errno' => -1,
                    'httpstatus' => 0,
                ];
            }

            if (self::is_confirmed($result)) {
                return true;
            }
            if ($attempt === self::MAX_ATTEMPTS || !self::is_retryable($result)) {
                return false;
            }

            $sleepcallback(self::RETRY_DELAYS[$attempt - 1]);
        }

        return false;
    }

    /**
     * Make one audited remote request.
     *
     * @param string $apikey Organization API key.
     * @param string $moodlebaseurl Moodle site URL.
     * @return array Request result without credentials or request headers.
     */
    private static function request(string $apikey, string $moodlebaseurl): array {
        $postdata = json_encode(['url' => $moodlebaseurl]);
        if ($postdata === false) {
            throw new \coding_exception('Could not encode the deregistration request.');
        }

        $curl = new \curl();
        // Tighter than the 15/60 used elsewhere in the plugin, on purpose. This runs
        // synchronously during uninstall, three times, and blocks core's own cleanup
        // until it returns -- 15/60 puts the worst case near three minutes inside a web
        // request. An endpoint silent for 15 seconds will not answer within 60.
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Bearer ' . $apikey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $postdata, $options) {
                return $curl->post(self::ENDPOINT, $postdata, $options);
            },
            audited_request::OP_ORGANIZATION_DEREGISTER,
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
     * Whether an attempt explicitly confirms deregistration.
     *
     * @param array $result Request result.
     * @return bool
     */
    private static function is_confirmed(array $result): bool {
        $response = $result['response'] ?? false;
        $errno = (int)($result['errno'] ?? -1);
        $httpstatus = (int)($result['httpstatus'] ?? 0);
        if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
            return false;
        }

        try {
            $responsedata = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return is_array($responsedata) && ($responsedata['status'] ?? null) === 'disconnected';
    }

    /**
     * Whether an unsuccessful attempt should be retried.
     *
     * @param array $result Request result.
     * @return bool
     */
    private static function is_retryable(array $result): bool {
        $response = $result['response'] ?? false;
        $errno = (int)($result['errno'] ?? -1);
        $httpstatus = (int)($result['httpstatus'] ?? 0);

        if ($response === false || $errno !== 0 || $httpstatus === 0) {
            return true;
        }
        if ($httpstatus === 408 || $httpstatus === 429 || $httpstatus >= 500) {
            return true;
        }

        // A 2xx response without valid JSON or an explicit disconnected status is retryable.
        return $httpstatus >= 200 && $httpstatus < 300;
    }
}
