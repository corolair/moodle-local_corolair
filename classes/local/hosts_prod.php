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
 * Production host table.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * The hosts a production deployment talks to.
 *
 * One of two interchangeable tables -- see hosts_dev for the other -- selected by
 * environment::ENV. This file and its sibling are the only places in the plugin where a
 * host name is written down; plugin_definition_test enforces that, because the whole point
 * of the split is defeated the moment a URL is pasted somewhere else.
 *
 * Keys name what the host is *for*, never what it is called. "embed" survives the host being
 * renamed; "share" would have to be renamed with it, and every call site along with it.
 */
final class hosts_prod {
    /** Host per role. Lower case: every comparison in the plugin is against a parsed host. */
    public const HOSTS = [
        // Integration API: registration, token rotation, privacy, trainer auth, widget session.
        // Also where the Raison LTI exam tool launches from, which is why placement checks it.
        'services' => 'services.raison.is',
        // Pages embedded in Moodle in an iframe: troubleshooting, book-a-meeting.
        'embed' => 'share.raison.is',
        // The Raison application itself, where a trainer redirect lands.
        'app' => 'app.raison.is',
        // Static asset host serving the embeddable widget script.
        'widget' => 'static.raison.is',
    ];
}
