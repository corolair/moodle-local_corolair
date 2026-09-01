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
 * Revocation of the Corolair token a rotation superseded.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Deletes the previous token once its grace window has elapsed.
 *
 * Queued by webservice_token_manager::activate_candidate() with a delayed run time, because
 * the hourly scheduled task revokes at the start of a run and would stretch a fifteen-minute
 * window to as much as seventy-five. That hourly path still runs and still converges, so
 * losing this task delays the revocation rather than preventing it.
 */
final class revoke_previous_token_task extends \core\task\adhoc_task {
    /**
     * Revoke the superseded token.
     *
     * @return void
     */
    public function execute(): void {
        \local_corolair\local\webservice_token_manager::revoke_previous_token();
    }
}
