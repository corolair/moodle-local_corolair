<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_corolair;

/**
 * Tests for deciding whether inherited credentials require migration.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_corolair\local\upgrade_migrator
 */
final class upgrade_migrator_test extends \advanced_testcase {
    /**
     * An active token is not evidence that the inherited API key was replaced.
     */
    public function test_active_token_without_completion_marker_queues_migration(): void {
        $this->resetAfterTest();
        [$service, $token] = $this->create_connected_installation();

        set_config('webservicetokenid', $token->id, 'local_corolair');
        \local_corolair\local\upgrade_migrator::migrate_if_required();

        $this->assertSame('1', get_config('local_corolair', 'legacycredentialmigrationpending'));
        $tasks = \core\task\manager::get_adhoc_tasks(
            '\local_corolair\task\migrate_legacy_credentials_task'
        );
        $this->assertCount(1, $tasks);
        $this->assertSame((int)get_admin()->id, (int)$tasks[0]->get_custom_data()->adminid);
    }

    /**
     * Explicit completion provenance plus a live recorded token safely skips migration.
     */
    public function test_completed_migration_with_active_token_is_skipped(): void {
        $this->resetAfterTest();
        [$service, $token] = $this->create_connected_installation();

        set_config('webservicetokenid', $token->id, 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');
        \local_corolair\local\upgrade_migrator::migrate_if_required();

        $this->assertFalse(get_config('local_corolair', 'legacycredentialmigrationpending'));
        $tasks = \core\task\manager::get_adhoc_tasks(
            '\local_corolair\task\migrate_legacy_credentials_task'
        );
        $this->assertCount(0, $tasks);
    }

    /**
     * Create the minimum connected integration state used by scheduling tests.
     *
     * @return array{0: \stdClass, 1: \stdClass} Service and token records.
     */
    private function create_connected_installation(): array {
        global $DB;

        $admin = get_admin();
        $now = time();
        $service = (object)[
            'name' => 'Raison REST service',
            'shortname' => 'corolair_rest',
            'enabled' => 1,
            'restrictedusers' => 0,
            'downloadfiles' => 0,
            'uploadfiles' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $service->id = $DB->insert_record('external_services', $service);

        $token = (object)[
            'token' => bin2hex(random_bytes(32)),
            'tokentype' => 0,
            'userid' => $admin->id,
            'externalserviceid' => $service->id,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $admin->id,
            'iprestriction' => '',
            'validuntil' => $now + DAYSECS,
            'timecreated' => $now,
        ];
        $token->id = $DB->insert_record('external_tokens', $token);

        set_config('apikey', 'instance-id.inherited-secret', 'local_corolair');
        set_config('setupconsentedby', $admin->id, 'local_corolair');
        return [$service, $token];
    }
}
