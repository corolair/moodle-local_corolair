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
 * Scheduled Corolair web-service token maintenance.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Rotates expiring tokens and retries failed rotations.
 */
final class rotate_webservice_token_task extends \core\task\scheduled_task {
    /**
     * Return the localized task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskrotatewebservicetoken', 'local_corolair');
    }

    /**
     * Run token lifecycle maintenance.
     *
     * Also retries a credential migration that the upgrade could not queue, so a site
     * that upgraded without a usable administrator recovers on its own once one exists,
     * and re-queues one that was queued but has since lost its ad-hoc task. Both run
     * before maintenance, which stands down while a migration is pending.
     *
     * @return void
     */
    public function execute(): void {
        \local_corolair\local\upgrade_migrator::retry_if_blocked();
        \local_corolair\local\upgrade_migrator::requeue_if_stalled();
        \local_corolair\local\webservice_token_manager::maintain();
    }
}
