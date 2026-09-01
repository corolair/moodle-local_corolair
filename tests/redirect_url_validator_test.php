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
 * Tests for the trainer redirect allow-list.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\redirect_url_validator;

/**
 * Verifies that only the two trusted Corolair hosts can receive a trainer redirect.
 *
 * The URL under test arrives in the response of an external service, so treating it as
 * trusted would hand that service an open redirect out of an authenticated Moodle page.
 */
final class redirect_url_validator_test extends \advanced_testcase {
    /**
     * URLs that must be accepted.
     *
     * @return array[] Data sets of [url, expected host].
     */
    public static function trusted_url_provider(): array {
        return [
            'staging' => [
                'https://staging.corolair.dev/auth/ticket-auth?token=test',
                'staging.corolair.dev',
            ],
            'embed' => [
                'https://embed.corolair.dev/auth/ticket-auth?token=test',
                'embed.corolair.dev',
            ],
            'explicit default port' => [
                'https://staging.corolair.dev:443/auth/ticket-auth?token=test',
                'staging.corolair.dev',
            ],
            'uppercase host' => [
                'https://EMBED.corolair.dev/auth/ticket-auth',
                'embed.corolair.dev',
            ],
            'bare root' => [
                'https://embed.corolair.dev/',
                'embed.corolair.dev',
            ],
        ];
    }

    /**
     * A trusted HTTPS destination is returned as a usable moodle_url.
     *
     * @dataProvider trusted_url_provider
     * @covers \local_corolair\local\redirect_url_validator::validate
     * @param string $url Destination returned by the authentication service.
     * @param string $expectedhost Host the destination must resolve to.
     * @return void
     */
    public function test_trusted_urls_are_accepted(string $url, string $expectedhost): void {
        $validated = redirect_url_validator::validate($url);

        $this->assertInstanceOf(\moodle_url::class, $validated);
        $this->assertSame(
            $expectedhost,
            strtolower((string)parse_url($validated->out(false), PHP_URL_HOST))
        );
    }

    /**
     * The ticket carried in the query string has to survive validation.
     *
     * Dropping it would send the trainer to a Corolair page that cannot authenticate them.
     *
     * @covers \local_corolair\local\redirect_url_validator::validate
     * @return void
     */
    public function test_query_string_is_preserved(): void {
        $validated = redirect_url_validator::validate(
            'https://embed.corolair.dev/auth/ticket-auth?token=abc123&source=moodle'
        );

        $out = $validated->out(false);
        $this->assertStringContainsString('token=abc123', $out);
        $this->assertStringContainsString('source=moodle', $out);
        $this->assertStringContainsString('/auth/ticket-auth', $out);
    }

    /**
     * URLs that must be rejected.
     *
     * @return array[] Data sets of [url].
     */
    public static function untrusted_url_provider(): array {
        return [
            'empty' => [''],
            'malformed' => ['not a url'],
            'relative' => ['/auth/ticket-auth'],
            'scheme only' => ['https://'],
            'plain http' => ['http://staging.corolair.dev/auth/ticket-auth'],
            'javascript' => ['javascript:alert(1)'],
            'data uri' => ['data:text/html,<script>alert(1)</script>'],
            'ftp' => ['ftp://embed.corolair.dev/auth'],
            'different host' => ['https://evil.example/auth/ticket-auth'],
            'suffix lookalike' => ['https://staging.corolair.dev.evil.example/auth/ticket-auth'],
            'prefix lookalike' => ['https://evilstaging.corolair.dev/auth'],
            'subdomain of a trusted host' => ['https://a.embed.corolair.dev/auth'],
            'parent domain' => ['https://corolair.dev/auth/ticket-auth'],
            'userinfo smuggling' => ['https://embed.corolair.dev@evil.example/auth'],
            'password smuggling' => ['https://embed.corolair.dev:x@evil.example/auth'],
            'non-default port' => ['https://staging.corolair.dev:8443/auth/ticket-auth'],
            'http port on https' => ['https://staging.corolair.dev:80/auth'],
        ];
    }

    /**
     * Anything outside the allow-list raises the invalid-redirect error.
     *
     * @dataProvider untrusted_url_provider
     * @covers \local_corolair\local\redirect_url_validator::validate
     * @param string $url Untrusted destination.
     * @return void
     */
    public function test_untrusted_urls_are_rejected(string $url): void {
        try {
            redirect_url_validator::validate($url);
            $this->fail("'{$url}' should not be an accepted redirect destination.");
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidredirecturl', $exception->errorcode);
        }
    }
}
