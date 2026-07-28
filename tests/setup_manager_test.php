<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the consent-gated setup manager.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

/**
 * Tests for setup activation.
 *
 * @covers \local_corolair\local\setup_manager
 */
final class setup_manager_test extends \advanced_testcase {
    /**
     * Activation records consent and preserves existing protocols.
     */
    public function test_activate_enables_requirements_and_preserves_protocols(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablewebservices', 0);
        set_config('webserviceprotocols', 'soap');
        $CFG->enablewebservices = 0;
        $CFG->webserviceprotocols = 'soap';

        \local_corolair\local\setup_manager::acknowledge_disclosure((int)$USER->id);
        \local_corolair\local\setup_manager::activate((int)$USER->id, true);

        $this->assertTrue((bool)get_config('local_corolair', 'setupconsented'));
        $this->assertTrue((bool)get_config('local_corolair', 'setupconsentrequired'));
        $this->assertSame((string)$USER->id, (string)get_config('local_corolair', 'setupconsentedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupconsentedat'));
        $this->assertFalse((bool)get_config('local_corolair', 'setupcompleted'));
        $this->assertTrue(\local_corolair\local\setup_manager::webservices_enabled());
        $this->assertSame(['soap', 'rest'], \local_corolair\local\setup_manager::get_enabled_protocols());
        $this->assertSame(
            1,
            $DB->count_records('task_adhoc', [
                'classname' => '\\local_corolair\\task\\setup_corolair_connection_task',
            ])
        );
    }

    /**
     * Repeated activation does not duplicate REST or pending tasks.
     */
    public function test_activate_is_idempotent_while_task_is_pending(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest,soap');
        $CFG->enablewebservices = 1;
        $CFG->webserviceprotocols = 'rest,soap';

        \local_corolair\local\setup_manager::acknowledge_disclosure((int)$USER->id);
        \local_corolair\local\setup_manager::activate((int)$USER->id);
        \local_corolair\local\setup_manager::activate((int)$USER->id);

        $this->assertFalse((bool)get_config('local_corolair', 'setupconsentrequired'));
        $this->assertSame(['rest', 'soap'], \local_corolair\local\setup_manager::get_enabled_protocols());
        $this->assertSame(
            1,
            $DB->count_records('task_adhoc', [
                'classname' => '\\local_corolair\\task\\setup_corolair_connection_task',
            ])
        );
    }

    /**
     * A user without site configuration capability cannot activate the integration.
     */
    public function test_activate_requires_site_configuration_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        \local_corolair\local\setup_manager::activate((int)$user->id);
    }

    /**
     * Missing requirements cannot be enabled without explicit consent.
     */
    public function test_activate_rejects_missing_enablement_consent(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablewebservices', 0);
        set_config('webserviceprotocols', '');
        $CFG->enablewebservices = 0;
        $CFG->webserviceprotocols = '';

        \local_corolair\local\setup_manager::acknowledge_disclosure((int)$USER->id);
        $this->expectException(\moodle_exception::class);
        \local_corolair\local\setup_manager::activate((int)$USER->id);
    }

    /**
     * Activation cannot bypass disclosure acknowledgment.
     */
    public function test_activate_requires_current_disclosure_acknowledgment(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest');
        $CFG->enablewebservices = 1;
        $CFG->webserviceprotocols = 'rest';

        $this->expectException(\moodle_exception::class);
        \local_corolair\local\setup_manager::activate((int)$USER->id);
    }

    /**
     * A stale disclosure acknowledgment is rejected.
     */
    public function test_stale_disclosure_acknowledgment_is_rejected(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('setupdisclosureversion', 'old-version', 'local_corolair');
        set_config('setupdisclosureacknowledgedby', (int)$USER->id, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', time(), 'local_corolair');

        $this->assertFalse(
            \local_corolair\local\setup_manager::disclosure_acknowledged((int)$USER->id)
        );
    }

    /**
     * An acknowledgment belongs only to the administrator who made it.
     */
    public function test_disclosure_acknowledgment_is_administrator_bound(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $otheruser = $this->getDataGenerator()->create_user();
        $eventsink = $this->redirectEvents();
        \local_corolair\local\setup_manager::acknowledge_disclosure((int)$USER->id);

        $this->assertSame(
            \local_corolair\local\integration_disclosure::VERSION,
            get_config('local_corolair', 'setupdisclosureversion')
        );
        $this->assertSame(
            (string)$USER->id,
            (string)get_config('local_corolair', 'setupdisclosureacknowledgedby')
        );
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupdisclosureacknowledgedat'));
        $this->assertTrue(
            \local_corolair\local\setup_manager::disclosure_acknowledged((int)$USER->id)
        );
        $this->assertFalse(
            \local_corolair\local\setup_manager::disclosure_acknowledged((int)$otheruser->id)
        );
        $events = $eventsink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(
            \local_corolair\event\integration_disclosure_acknowledged::class,
            reset($events)
        );
    }

    /**
     * Completed integrations are not interrupted by a disclosure version change.
     */
    public function test_completed_integration_is_grandfathered(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('setupcompleted', 1, 'local_corolair');

        $this->assertTrue(
            \local_corolair\local\setup_manager::disclosure_acknowledged((int)$USER->id)
        );
    }
}
