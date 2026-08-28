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
 * Scheduled reminder for an unfinished Raison setup.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Notifies site administrators while the plugin remains installed and inactive.
 */
final class send_setup_reminder_task extends \core\task\scheduled_task {
    /**
     * Return the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendsetupreminder', 'local_corolair');
    }

    /**
     * Send a reminder when one is due.
     *
     * Scheduled daily but rate-limited to one reminder a week, so the schedule only decides
     * how soon after installation the first one goes out.
     *
     * @return void
     */
    public function execute(): void {
        \local_corolair\local\setup_reminder::maintain();
    }
}
