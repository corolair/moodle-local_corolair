<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for Corolair trainer redirect validation.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

/**
 * Redirect allowlist tests.
 *
 * @covers \local_corolair\local\redirect_url_validator
 */
final class redirect_url_validator_test extends \advanced_testcase {
    /**
     * Trusted HTTPS URLs with no port or port 443 are accepted.
     */
    public function test_trusted_https_urls_are_accepted(): void {
        $withoutport = \local_corolair\local\redirect_url_validator::validate(
            'https://staging.corolair.dev/auth/ticket-auth?token=test'
        );
        $withport = \local_corolair\local\redirect_url_validator::validate(
            'https://staging.corolair.dev:443/auth/ticket-auth?token=test'
        );

        $this->assertSame('staging.corolair.dev', parse_url($withoutport->out(false), PHP_URL_HOST));
        $this->assertSame('staging.corolair.dev', parse_url($withport->out(false), PHP_URL_HOST));
    }

    /**
     * Untrusted redirect variants are rejected.
     *
     * @dataProvider invalid_url_provider
     * @param string $url Untrusted URL.
     */
    public function test_untrusted_urls_are_rejected(string $url): void {
        $this->expectException(\moodle_exception::class);
        \local_corolair\local\redirect_url_validator::validate($url);
    }

    /**
     * Invalid redirect variants.
     *
     * @return array[]
     */
    public static function invalid_url_provider(): array {
        return [
            'malformed' => ['not a url'],
            'http' => ['http://staging.corolair.dev/auth/ticket-auth'],
            'different host' => ['https://evil.example/auth/ticket-auth'],
            'lookalike host' => [
                'https://staging.corolair.dev.evil.example/auth/ticket-auth',
            ],
            'non-443 port' => ['https://staging.corolair.dev:8443/auth/ticket-auth'],
        ];
    }
}
