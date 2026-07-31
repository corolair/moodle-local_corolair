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
 * Corolair web-service token lifecycle event.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\event;

/**
 * Records safe token lifecycle metadata.
 */
final class webservice_token_lifecycle extends \core\event\base {
    /**
     * Initialize event properties.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localized event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventwebservicetokenlifecycle', 'local_corolair');
    }

    /**
     * Describe the lifecycle operation without exposing credentials.
     *
     * @return string
     */
    public function get_description(): string {
        return "Corolair web-service token lifecycle action '{$this->other['action']}' affected token record " .
            "{$this->other['tokenid']} with expiration {$this->other['expiresat']}.";
    }

    /**
     * Declare technical fields that do not map to stored user data.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'action' => false,
            'tokenid' => false,
            'expiresat' => false,
            'rotationid' => false,
        ];
    }
}
