<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy deletion completed event.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Records a deletion acknowledged by the Corolair privacy service.
 */
class privacy_deletion_completed extends \core\event\base {
    /**
     * Initialise event properties.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventprivacydeletioncompleted', 'local_corolair');
    }

    /**
     * Describe the event without including remote payloads or secrets.
     *
     * @return string
     */
    public function get_description(): string {
        return "The Raison privacy service completed deletion operation " .
            "'{$this->other['operationid']}' for scope '{$this->other['scope']}'.";
    }

    /**
     * Declare that the technical audit fields do not map to stored user data.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'scope' => false,
            'operationid' => false,
            'affected' => false,
        ];
    }
}
