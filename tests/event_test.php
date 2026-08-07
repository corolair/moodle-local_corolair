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
 * Tests for the plugin's audit events.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\event\integration_disclosure_acknowledged;
use local_corolair\event\privacy_deletion_completed;
use local_corolair\event\remote_request_completed;
use local_corolair\event\webservice_token_lifecycle;

/**
 * Verifies every audit event is loggable, readable and restorable.
 *
 * These four events are the plugin's entire accountability record: what was sent, what
 * was deleted, who consented, and what happened to the token. A missing language string
 * or an unmapped "other" field only shows up when an administrator opens the site logs,
 * which is exactly when it is least useful to discover.
 */
final class event_test extends \advanced_testcase {
    /**
     * Event classes and a representative payload for each.
     *
     * @return array[] Data sets of [class name, other payload, expected description fragment].
     */
    public static function event_provider(): array {
        return [
            'remote request' => [
                remote_request_completed::class,
                [
                    'operation' => 'widget_session',
                    'outcome' => 'success',
                    'httpstatus' => 200,
                    'curlerrno' => 0,
                ],
                'widget_session',
            ],
            'token lifecycle' => [
                webservice_token_lifecycle::class,
                [
                    'action' => 'rotation_succeeded',
                    'tokenid' => 42,
                    'expiresat' => 1700000000,
                    'rotationid' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
                'rotation_succeeded',
            ],
            'privacy deletion' => [
                privacy_deletion_completed::class,
                [
                    'scope' => 'course',
                    'operationid' => 'op-12345',
                    'affected' => [
                        'associations' => 1,
                        'conversations' => 2,
                        'learners' => 3,
                        'users' => 4,
                    ],
                ],
                'op-12345',
            ],
            'disclosure acknowledged' => [
                integration_disclosure_acknowledged::class,
                ['version' => '2026-08-06-1'],
                '2026-08-06-1',
            ],
        ];
    }

    /**
     * Each event triggers, names itself, and describes itself.
     *
     * @dataProvider event_provider
     * @covers \local_corolair\event\remote_request_completed
     * @covers \local_corolair\event\webservice_token_lifecycle
     * @covers \local_corolair\event\privacy_deletion_completed
     * @covers \local_corolair\event\integration_disclosure_acknowledged
     * @param string $classname Event class.
     * @param array $other Event payload.
     * @param string $fragment Text the description must contain.
     * @return void
     */
    public function test_event_is_loggable(string $classname, array $other, string $fragment): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        $classname::create([
            'context' => \context_system::instance(),
            'other' => $other,
        ])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf($classname, $event);

        $name = $classname::get_name();
        $this->assertNotEmpty($name);
        $this->assertStringNotContainsString('[[', $name, 'The event name has no language string.');

        $description = $event->get_description();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString($fragment, $description);
    }

    /**
     * Each event survives being restored from its stored log record.
     *
     * Moodle rebuilds events from the log table to render the reports, so a description
     * that only works on a freshly created object breaks the log viewer.
     *
     * @dataProvider event_provider
     * @covers \local_corolair\event\remote_request_completed
     * @covers \local_corolair\event\webservice_token_lifecycle
     * @covers \local_corolair\event\privacy_deletion_completed
     * @covers \local_corolair\event\integration_disclosure_acknowledged
     * @param string $classname Event class.
     * @param array $other Event payload.
     * @param string $fragment Text the description must contain.
     * @return void
     */
    public function test_event_restores_from_log_data(string $classname, array $other, string $fragment): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $original = $classname::create([
            'context' => \context_system::instance(),
            'other' => $other,
        ]);
        $restored = \core\event\base::restore($original->get_data(), []);

        $this->assertInstanceOf($classname, $restored);
        $this->assertStringContainsString($fragment, $restored->get_description());
    }

    /**
     * Every field an event carries is declared in its "other" mapping.
     *
     * The mapping is what tells the backup and privacy subsystems whether a field points
     * at user data. A field missing from it is silently treated as unmapped.
     *
     * @dataProvider event_provider
     * @covers \local_corolair\event\remote_request_completed
     * @covers \local_corolair\event\webservice_token_lifecycle
     * @covers \local_corolair\event\privacy_deletion_completed
     * @covers \local_corolair\event\integration_disclosure_acknowledged
     * @param string $classname Event class.
     * @param array $other Event payload.
     * @return void
     */
    public function test_other_fields_are_all_mapped(string $classname, array $other): void {
        $mapping = $classname::get_other_mapping();

        $this->assertIsArray($mapping);
        foreach (array_keys($other) as $field) {
            $this->assertArrayHasKey(
                $field,
                $mapping,
                "{$classname} carries '{$field}' without declaring it in get_other_mapping()."
            );
            $this->assertFalse(
                $mapping[$field],
                "'{$field}' is technical audit metadata and must not be mapped to user data."
            );
        }
    }

    /**
     * The disclosure acknowledgment records who made it.
     *
     * This is the plugin's consent record. An acknowledgment nobody is attributable for
     * is not an accountability record at all.
     *
     * @covers \local_corolair\event\integration_disclosure_acknowledged
     * @return void
     */
    public function test_disclosure_acknowledgment_names_the_administrator(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = $this->getDataGenerator()->create_user();

        $event = integration_disclosure_acknowledged::create([
            'context' => \context_system::instance(),
            'userid' => (int)$admin->id,
            'other' => ['version' => '2026-08-06-1'],
        ]);

        $this->assertEquals((int)$admin->id, (int)$event->userid);
        $this->assertStringContainsString((string)$admin->id, $event->get_description());
    }

    /**
     * The remote-request event reports the transport result it was given.
     *
     * @covers \local_corolair\event\remote_request_completed
     * @return void
     */
    public function test_remote_request_description_reports_the_transport_result(): void {
        $this->resetAfterTest();

        $event = remote_request_completed::create([
            'context' => \context_system::instance(),
            'other' => [
                'operation' => 'organization_register',
                'outcome' => 'http_failure',
                'httpstatus' => 503,
                'curlerrno' => 0,
            ],
        ]);

        $description = $event->get_description();
        $this->assertStringContainsString('organization_register', $description);
        $this->assertStringContainsString('http_failure', $description);
        $this->assertStringContainsString('503', $description);
    }

    /**
     * Events are classified so the log viewer can filter them sensibly.
     *
     * @covers \local_corolair\event\remote_request_completed
     * @covers \local_corolair\event\privacy_deletion_completed
     * @covers \local_corolair\event\webservice_token_lifecycle
     * @covers \local_corolair\event\integration_disclosure_acknowledged
     * @return void
     */
    public function test_events_declare_their_crud_and_education_level(): void {
        $this->resetAfterTest();

        $expected = [
            remote_request_completed::class => 'r',
            privacy_deletion_completed::class => 'd',
            webservice_token_lifecycle::class => 'u',
            integration_disclosure_acknowledged::class => 'c',
        ];
        $payloads = [];
        foreach (self::event_provider() as $dataset) {
            $payloads[$dataset[0]] = $dataset[1];
        }

        foreach ($expected as $classname => $crud) {
            $event = $classname::create([
                'context' => \context_system::instance(),
                'other' => $payloads[$classname],
            ]);
            $data = $event->get_data();

            $this->assertSame($crud, $data['crud'], "{$classname} has the wrong CRUD classification.");
            $this->assertSame(
                \core\event\base::LEVEL_OTHER,
                (int)$data['edulevel'],
                "{$classname} is administrative, not teaching or participation."
            );
        }
    }
}
