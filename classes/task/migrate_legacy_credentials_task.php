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
 * Ad-hoc task that rotates legacy credentials after an upgrade.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Runs the deferred legacy-credential rotation queued by the upgrade step.
 *
 * The remote migration runs here rather than in db/upgrade.php so Raison can verify the new token
 * against a live Moodle site. Exceptions are allowed to escape so Moodle retries the task while
 * the inherited credentials remain untouched.
 */
class migrate_legacy_credentials_task extends \core\task\adhoc_task {
    /**
     * Do the job.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        $adminid = (isset($data->adminid) && (int)$data->adminid > 0)
            ? (int)$data->adminid
            : (int)get_config('local_corolair', 'setupconsentedby');
        if ($adminid <= 0) {
            // Nothing safe to act as; drop out rather than loop. Interactive setup remains available.
            return;
        }
        \local_corolair\local\upgrade_migrator::run($adminid);
    }
}
