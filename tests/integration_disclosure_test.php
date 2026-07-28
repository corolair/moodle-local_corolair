<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the versioned Corolair integration disclosure.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

/**
 * Disclosure catalog tests.
 *
 * @covers \local_corolair\local\integration_disclosure
 */
final class integration_disclosure_test extends \advanced_testcase {
    /**
     * Every configured service function is disclosed exactly once.
     */
    public function test_disclosure_matches_service_allowlist(): void {
        global $CFG;

        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/corolair/db/services.php');

        $configured = $services['Corolair REST Service']['functions'];
        $documented = \local_corolair\local\integration_disclosure::get_function_names();
        $unique = array_values(array_unique($documented));
        sort($configured);
        sort($documented);
        sort($unique);

        $this->assertCount(26, $documented);
        $this->assertSame($unique, $documented, 'Each disclosed function must appear exactly once.');
        $this->assertSame($configured, $documented, 'The disclosure and service allowlist must not drift.');
    }

    /**
     * Planned completion reads and all writes retain their classifications.
     */
    public function test_sensitive_groups_are_classified(): void {
        $groups = \local_corolair\local\integration_disclosure::get_function_groups();

        $this->assertTrue($groups[3]['planned']);
        $this->assertSame('core_completion_get_activities_completion_status', $groups[3]['functions'][0]['name']);
        $this->assertTrue($groups[4]['functions'][0]['iswrite']);
        foreach ($groups[5]['functions'] as $function) {
            $this->assertTrue($function['iswrite']);
        }
    }
}
