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
 * Which deployment this tree is built for, and the hosts that follow from it.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Resolves every host and URL the plugin uses from a single declared environment.
 *
 * Why this exists: the endpoints used to be string constants spread across eighteen files, which
 * made the environment a property of the *branch* rather than of the deployment. Two branches that
 * disagree on eighteen files conflict on every merge, and a line the release branch has never
 * counter-changed is taken from the development branch silently -- no conflict marker, no warning.
 * That is not a hypothetical: it is how development endpoints reached a release once already.
 *
 * So the divergence is reduced to the ENV line below plus the presence of hosts_dev, and the
 * release workflow -- not a person resolving a conflict -- is what sets both.
 *
 * Nothing outside this class and the two host tables may write a host name down.
 * plugin_definition_test enforces it, because a single pasted URL re-creates the original problem
 * in a file nobody thinks to check at release time.
 */
final class environment {
    /**
     * The deployment this tree is built for.
     *
     * The one line that differs between the development branch and a release. The release
     * workflow rewrites it, so it is never hand-edited and never hand-merged.
     */
    public const ENV = 'production';

    /** Value of ENV that selects the production host table. */
    private const PRODUCTION = 'production';

    /**
     * The active host table.
     *
     * Production unless this tree both declares itself a development one *and* actually carries
     * the development table. The second condition is the safety property: the release workflow
     * deletes hosts_dev, so a released plugin resolves to production hosts even if the ENV line
     * were somehow wrong. The failure mode is "a developer's site talks to production", which is
     * noisy and self-correcting; the reverse -- a customer site talking to development -- is
     * neither, and this ordering makes it unreachable.
     *
     * class_exists() rather than a try/catch: hosts_dev::class is resolved at compile time and
     * does not autoload, so this asks the question without paying for it.
     *
     * @return array<string, string> Host keyed by role.
     */
    private static function hosts(): array {
        if (self::ENV !== self::PRODUCTION && class_exists(hosts_dev::class)) {
            return hosts_dev::HOSTS;
        }
        return hosts_prod::HOSTS;
    }

    /**
     * The host serving a given role in this environment.
     *
     * @param string $role Role key, as declared in the host tables.
     * @return string Lower-case host name.
     * @throws \coding_exception If the role is not declared.
     */
    public static function host(string $role): string {
        $hosts = self::hosts();
        if (!array_key_exists($role, $hosts)) {
            // A typo here would otherwise resolve to the empty string and be compared against a
            // parsed URL host, which fails closed but reports as "the tool launches from the
            // wrong host" -- a misleading error a long way from its cause.
            throw new \coding_exception('Unknown local_corolair host role: ' . $role);
        }
        return $hosts[$role];
    }

    /**
     * Build an https URL on the host serving a given role.
     *
     * Always https, never a port: every host comparison in the plugin rejects both anything else
     * and any explicit port (see redirect_url_validator::validate() and
     * placement_registry::url_host()), so a URL built here is one those checks accept.
     *
     * @param string $role Role key, as declared in the host tables.
     * @param string $path Path below the host, with or without a leading slash.
     * @return string Absolute URL.
     * @throws \coding_exception If the role is not declared.
     */
    public static function url(string $role, string $path): string {
        return 'https://' . self::host($role) . '/' . ltrim($path, '/');
    }
}
