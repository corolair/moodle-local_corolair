<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event emitted when an administrator acknowledges the integration disclosure.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\event;

/**
 * Versioned disclosure acknowledgment audit event.
 */
final class integration_disclosure_acknowledged extends \core\event\base {
    /**
     * Initialize event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localized event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventintegrationdisclosureacknowledged', 'local_corolair');
    }

    /**
     * Describe the acknowledgment.
     *
     * @return string
     */
    public function get_description(): string {
        return "User {$this->userid} acknowledged Corolair integration disclosure version " .
            "'{$this->other['version']}'.";
    }

    /**
     * Declare non-user-data event fields.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return ['version' => false];
    }
}
