<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Administrator-requested Corolair token rotation retry.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Runs the same lifecycle maintenance as the scheduled task.
 */
final class retry_webservice_token_rotation_task extends \core\task\adhoc_task {
    /**
     * Retry token rotation.
     *
     * @return void
     */
    public function execute(): void {
        \local_corolair\local\webservice_token_manager::maintain();
    }
}
