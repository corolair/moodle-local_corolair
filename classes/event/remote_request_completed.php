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
 * Remote request completed event.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\event;

/**
 * Records safe transport metadata for an outbound Corolair request.
 */
class remote_request_completed extends \core\event\base {
    /**
     * Initialise event properties.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventremoterequestcompleted', 'local_corolair');
    }

    /**
     * Describe the request using safe metadata only.
     *
     * @return string
     */
    public function get_description(): string {
        return "The Corolair remote request '{$this->other['operation']}' completed with outcome " .
            "'{$this->other['outcome']}', HTTP status {$this->other['httpstatus']}, and cURL error " .
            "{$this->other['curlerrno']}.";
    }

    /**
     * Declare that technical audit fields do not map to stored user data.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'operation' => false,
            'outcome' => false,
            'httpstatus' => false,
            'curlerrno' => false,
        ];
    }
}
