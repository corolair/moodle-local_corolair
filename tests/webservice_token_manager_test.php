<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests for the Corolair web-service token lifecycle.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

/**
 * Token timing and creation tests.
 *
 * @covers \local_corolair\local\webservice_token_manager
 */
final class webservice_token_manager_test extends \advanced_testcase {
    /**
     * Tokens expire fifteen days after creation.
     */
    public function test_create_token_uses_fifteen_day_lifetime(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $serviceid = $DB->insert_record('external_services', (object)[
            'name' => 'Corolair test service',
            'shortname' => 'corolair_test',
            'enabled' => 1,
            'restrictedusers' => 0,
            'downloadfiles' => 0,
            'uploadfiles' => 0,
            'timecreated' => time(),
        ]);
        $before = time();
        $token = \local_corolair\local\webservice_token_manager::create_token((int)$USER->id, $serviceid);
        $after = time();

        $this->assertGreaterThanOrEqual(
            $before + \local_corolair\local\webservice_token_manager::TOKEN_LIFETIME,
            (int)$token->validuntil
        );
        $this->assertLessThanOrEqual(
            $after + \local_corolair\local\webservice_token_manager::TOKEN_LIFETIME,
            (int)$token->validuntil
        );
    }

    /**
     * Rotation starts at seven days while warnings start at five days.
     */
    public function test_rotation_and_warning_thresholds(): void {
        $now = 1_700_000_000;
        $token = (object)['validuntil' => $now + (8 * DAYSECS)];
        $this->assertFalse(
            \local_corolair\local\webservice_token_manager::rotation_due($token, $now)
        );
        $this->assertFalse(
            \local_corolair\local\webservice_token_manager::warning_due($token, $now)
        );

        $token->validuntil = $now + (7 * DAYSECS);
        $this->assertTrue(
            \local_corolair\local\webservice_token_manager::rotation_due($token, $now)
        );
        $this->assertFalse(
            \local_corolair\local\webservice_token_manager::warning_due($token, $now)
        );

        $token->validuntil = $now + (5 * DAYSECS);
        $this->assertTrue(
            \local_corolair\local\webservice_token_manager::warning_due($token, $now)
        );
    }

    /**
     * Legacy non-expiring tokens rotate immediately.
     */
    public function test_non_expiring_token_is_due_immediately(): void {
        $token = (object)['validuntil' => 0];
        $this->assertTrue(
            \local_corolair\local\webservice_token_manager::rotation_due($token)
        );
    }
}
