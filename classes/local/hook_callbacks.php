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
 * Hook callbacks for the Raison plugin.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

use core\hook\output\before_footer_html_generation;

/**
 * Routes core output hooks to the plugin's rendering entry points.
 *
 * Only the dispatch lives here. The decision of whether the widget belongs on this page,
 * and the rendering itself, stay in lib.php, which is still the entry point on sites older
 * than the hook it replaces -- there the legacy callback runs and this class is never loaded.
 */
final class hook_callbacks {
    /**
     * Adds the course widget to the page footer.
     *
     * The hook is dispatched while the document is already open, which is the whole point of
     * rendering here: output flushed earlier, from a navigation callback, lands before
     * <!DOCTYPE html> and puts the page into quirks mode.
     *
     * @param before_footer_html_generation $hook The footer hook being dispatched.
     * @return void
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/corolair/lib.php');

        $hook->add_html(local_corolair_before_footer());
    }
}
