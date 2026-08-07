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
 * Helper for (re)registering the Moodle instance with the Raison backend.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

use context;
use curl;
use JsonException;

/**
 * Calls the Raison organization-registration endpoint.
 *
 * The endpoint always rotates the Corolair secret and returns a fresh API key,
 * invalidating any previously issued key. It is used both by the trainer-page
 * registration recovery and by the admin-triggered API key rotation.
 */
final class registration_client {
    /** Registration endpoint URL. */
    private const REGISTER_URL =
        'https://services.corolair.dev/moodle-integration/plugin/organization/register';

    /**
     * Register (or re-register) the Moodle instance and return the issued API key.
     *
     * The token record is taken rather than the token string so the call can also report the
     * expiration. Omitting it made Raison record "no expiration" for the site, which used to
     * be inert but now means the token never expires -- so an ordinary API-key rotation on a
     * site that does rotate its token silently told Raison the opposite.
     *
     * @param string $url Moodle site root URL.
     * @param \stdClass $token Raison web-service token record used to authenticate the call.
     * @param string $email Admin user email.
     * @param string $firstname Admin user first name.
     * @param string $lastname Admin user last name.
     * @param string $sitename Moodle site name.
     * @param context $context Moodle context associated with the request.
     * @param int $userid Moodle user performing the request.
     * @return string|null The new API key on success, or null on any failure.
     */
    public static function register(
        string $url,
        \stdClass $token,
        string $email,
        string $firstname,
        string $lastname,
        string $sitename,
        context $context,
        int $userid
    ): ?string {
        global $CFG;
        // The curl class lives in filelib.php and is not autoloaded; ensure it is
        // available even when called before any page output has loaded it.
        require_once($CFG->libdir . '/filelib.php');
        $curl = new curl();
        $postdata = json_encode([
            'url' => $url,
            'webserviceToken' => $token->token,
            'expiresAt' => webservice_token_manager::expiration_iso8601($token),
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'siteName' => $sitename,
        ]);
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            "CURLOPT_CONNECTTIMEOUT" => 15,
            "CURLOPT_TIMEOUT" => 60,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = audited_request::execute(
            $curl,
            function () use ($curl, $postdata, $options) {
                return $curl->post(self::REGISTER_URL, $postdata, $options);
            },
            audited_request::OP_ORGANIZATION_REGISTER,
            $context,
            $userid
        );
        $errno = $curl->get_errno();
        $info = $curl->get_info();
        $httpstatus = (int)($info['http_code'] ?? 0);
        if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
            return null;
        }
        try {
            $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            debugging('Invalid JSON received while registering Corolair.', DEBUG_DEVELOPER);
            return null;
        }
        if (
            is_array($jsonresponse) &&
            isset($jsonresponse['apiKey']) &&
            is_string($jsonresponse['apiKey']) &&
            $jsonresponse['apiKey'] !== ''
        ) {
            return $jsonresponse['apiKey'];
        }
        return null;
    }
}
