<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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
     * @return void
     */
    public function execute(): void {
        \local_corolair\local\webservice_token_manager::maintain();
    }
}
