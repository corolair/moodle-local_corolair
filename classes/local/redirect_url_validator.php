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
 * Validation for redirect destinations returned by Corolair.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Restricts external redirects to explicitly trusted HTTPS hosts.
 */
final class redirect_url_validator {
    /**
     * Exact hostnames that may receive trainer redirects.
     *
     * A method rather than the constant this once was: the hosts now come from environment, and
     * a constant cannot call one. Deliberately still an exact-match list of two -- the roles are
     * named separately so that widening the list stays a decision someone has to write down.
     *
     * @return string[] Lower-case host names.
     */
    private static function allowed_hosts(): array {
        return [
            environment::host('app'),
            environment::host('embed'),
        ];
    }

    /**
     * Validate and return a trusted trainer redirect URL.
     *
     * @param string $url URL returned by the Corolair authentication service.
     * @return \moodle_url Validated URL.
     */
    public static function validate(string $url): \moodle_url {
        try {
            $parts = parse_url($url);
        } catch (\ValueError $exception) {
            throw new \moodle_exception('invalidredirecturl', 'local_corolair');
        }

        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
        $port = is_array($parts) && array_key_exists('port', $parts) ? (int)$parts['port'] : null;

        if (
            $parts === false ||
            filter_var($url, FILTER_VALIDATE_URL) === false ||
            $scheme !== 'https' ||
            !in_array($host, self::allowed_hosts(), true) ||
            ($port !== null && $port !== 443)
        ) {
            throw new \moodle_exception('invalidredirecturl', 'local_corolair');
        }

        return new \moodle_url($url);
    }
}
