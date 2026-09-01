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
 * Development host table.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * The hosts a development deployment talks to.
 *
 * Exists on the development branch only. The release workflow deletes this file when it builds
 * the production tree, so no development host name reaches the released plugin -- and
 * environment::host() falls back to hosts_prod when the class is absent, so a tree without it
 * cannot resolve to a development host whatever environment::ENV happens to say.
 *
 * Keys must match hosts_prod exactly. A role present in one table and missing from the other
 * would fail only on the branch that lacks it, which is the failure mode this whole split
 * exists to prevent, so plugin_definition_test asserts the two key sets are identical.
 */
final class hosts_dev {
    /** Host per role. See hosts_prod for what each role means. */
    public const HOSTS = [
        'services' => 'services.corolair.dev',
        'embed' => 'embed.corolair.dev',
        'app' => 'staging.corolair.dev',
        'widget' => 'widget-dev.corolair.workers.dev',
    ];
}
