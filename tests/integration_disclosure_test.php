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
 * Tests that the integration disclosure describes the integration that actually exists.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\integration_disclosure;

/**
 * Verifies the disclosure and the service allow-list cannot drift apart.
 *
 * An administrator consents on the strength of this page. If db/services.php grants a
 * function the disclosure does not name, the site is exposing something nobody agreed
 * to; if the disclosure names one the service does not grant, the page is describing a
 * capability the plugin does not have. Both are defects, so the two lists are asserted
 * equal rather than merely overlapping.
 */
final class integration_disclosure_test extends \advanced_testcase {
    /**
     * Return the function allow-list of the shipped external service.
     *
     * Read from db/services.php rather than the installed external_services_functions
     * rows, so the test compares the two files a developer edits.
     *
     * @return string[] Function names granted to the corolair_rest service.
     */
    private function configured_functions(): array {
        global $CFG;

        $functions = [];
        $services = [];
        require($CFG->dirroot . '/local/corolair/db/services.php');

        $service = null;
        foreach ($services as $definition) {
            if (($definition['shortname'] ?? '') === 'corolair_rest') {
                $service = $definition;
                break;
            }
        }
        $this->assertNotNull($service, 'db/services.php no longer defines the corolair_rest service.');

        return $service['functions'];
    }

    /**
     * Every granted function is disclosed, and nothing else is.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_names
     * @return void
     */
    public function test_disclosure_matches_the_service_allowlist(): void {
        $configured = $this->configured_functions();
        $documented = integration_disclosure::get_function_names();

        sort($configured);
        sort($documented);

        $this->assertSame(
            $configured,
            $documented,
            'The disclosure and db/services.php have drifted. Every granted function must be ' .
            'disclosed, and the disclosure must not name functions the service does not grant.'
        );
    }

    /**
     * No function is disclosed twice.
     *
     * A duplicate would inflate the count administrators are told about and make the
     * comparison above pass for the wrong reason.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_names
     * @return void
     */
    public function test_no_function_is_disclosed_twice(): void {
        $documented = integration_disclosure::get_function_names();

        $this->assertSame(
            array_values(array_unique($documented)),
            $documented,
            'A function appears in more than one disclosure group.'
        );
    }

    /**
     * The count quoted in the disclosure text matches the list itself.
     *
     * lang/en states the number in prose, where nothing else would catch it going stale.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_names
     * @return void
     */
    public function test_quoted_function_count_is_accurate(): void {
        $documented = integration_disclosure::get_function_names();
        $intro = get_string('disclosurefunctionsintro', 'local_corolair');

        $this->assertStringContainsString(
            (string)count($documented),
            $intro,
            'The disclosure prose quotes a function count that no longer matches the list.'
        );
    }

    /**
     * Every group renders with real language strings and a coherent access classification.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_groups
     * @return void
     */
    public function test_groups_are_fully_described(): void {
        $groups = integration_disclosure::get_function_groups();

        $this->assertNotEmpty($groups);
        foreach ($groups as $index => $group) {
            $this->assertNotEmpty($group['title'], "Group {$index} has no title.");
            $this->assertStringNotContainsString('[[', $group['title'], "Group {$index} title is a missing string.");
            $this->assertNotEmpty($group['description'], "Group {$index} has no description.");
            $this->assertStringNotContainsString(
                '[[',
                $group['description'],
                "Group {$index} description is a missing string."
            );
            $this->assertNotEmpty($group['functions'], "Group {$index} discloses no functions.");
            $this->assertIsBool($group['planned']);
            $this->assertSame(
                $group['planned'],
                $group['plannedlabel'] !== '',
                "Group {$index} planned flag and label disagree."
            );

            foreach ($group['functions'] as $function) {
                $this->assertNotEmpty($function['name']);
                $this->assertNotEmpty($function['access']);
                $this->assertStringNotContainsString('[[', $function['access']);
                $this->assertNotSame(
                    $function['isread'],
                    $function['iswrite'],
                    "{$function['name']} is classified as both a read and a write, or as neither."
                );
            }
        }
    }

    /**
     * Read and write classifications match what the functions actually do.
     *
     * The read/write split is the heart of the disclosure: an administrator scanning the
     * page reads the write list to decide what the token can change. Anything named
     * "get" is a read; the plugin's own writes and the LTI toggle are writes.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_groups
     * @return void
     */
    public function test_writes_are_classified_as_writes(): void {
        $writes = [];
        $reads = [];
        foreach (integration_disclosure::get_function_groups() as $group) {
            foreach ($group['functions'] as $function) {
                if ($function['iswrite']) {
                    $writes[] = $function['name'];
                } else {
                    $reads[] = $function['name'];
                }
            }
        }

        foreach ($reads as $name) {
            $this->assertStringContainsString(
                '_get_',
                $name,
                "{$name} is disclosed as a read but is not a getter."
            );
        }
        $this->assertNotEmpty($writes);
        foreach ($writes as $name) {
            $this->assertStringNotContainsString(
                '_get_',
                $name,
                "{$name} is disclosed as a write but looks like a getter."
            );
        }
    }

    /**
     * Completion reads stay flagged as planned rather than current use.
     *
     * The plugin does not process completion data yet, and the disclosure says so. If
     * that flag were lost the page would overstate what the integration does today.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_groups
     * @return void
     */
    public function test_completion_reads_are_flagged_as_planned(): void {
        $planned = [];
        foreach (integration_disclosure::get_function_groups() as $group) {
            if (!$group['planned']) {
                continue;
            }
            foreach ($group['functions'] as $function) {
                $planned[] = $function['name'];
            }
        }
        sort($planned);

        $this->assertSame(
            [
                'core_completion_get_activities_completion_status',
                'core_completion_get_course_completion_status',
            ],
            $planned,
            'The set of functions disclosed as planned-but-unused has changed.'
        );
    }

    /**
     * The disclosure version is a usable, comparable identifier.
     *
     * setup_manager::disclosure_acknowledged() compares it as a string, so an empty or
     * non-scalar value would silently accept any acknowledgment.
     *
     * @covers \local_corolair\local\integration_disclosure::VERSION
     * @return void
     */
    public function test_version_is_a_dated_identifier(): void {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}-\d+$/',
            integration_disclosure::VERSION,
            'The disclosure version should stay in YYYY-MM-DD-N form so revisions order.'
        );
    }
}
