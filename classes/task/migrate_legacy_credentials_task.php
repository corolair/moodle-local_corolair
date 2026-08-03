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
 * Adhoc task that rotates legacy Corolair credentials after an upgrade.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Runs the deferred legacy-credential rotation queued by the upgrade step.
 *
 * The network re-registration is performed here (rather than inline in db/upgrade.php) so it
 * executes against a live site. Throwing on failure lets Moodle retry the task with backoff
 * until Raison is reachable; the legacy credentials are retained until it succeeds.
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
