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
 * Tests for the plugin's scheduled and ad-hoc tasks.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\event\remote_request_completed;
use local_corolair\local\webservice_token_manager;
use local_corolair\task\migrate_legacy_credentials_task;
use local_corolair\task\retry_webservice_token_rotation_task;
use local_corolair\task\rotate_webservice_token_task;
use local_corolair\task\setup_corolair_connection_task;

/**
 * Verifies the registration task refuses to run unless every precondition holds.
 *
 * This task is what actually registers the site with Raison, sending the administrator's
 * name and email and receiving the API key. It runs unattended, out of band from the
 * request that queued it, so by the time it executes the site may no longer be in the
 * state that justified queuing it: consent may have been withdrawn, web services turned
 * back off, or the consenting administrator demoted. Each guard is re-checked at run
 * time for that reason, and each one is asserted here to fail closed.
 */
final class task_test extends \advanced_testcase {
    /**
     * Put the site in the state the registration task requires.
     *
     * @param int $adminid Administrator recorded as having consented.
     * @return void
     */
    private function make_setup_ready(int $adminid): void {
        global $CFG;

        set_config('setupconsented', 1, 'local_corolair');
        set_config('setupconsentedby', $adminid, 'local_corolair');
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest');
        $CFG->enablewebservices = 1;
        $CFG->webserviceprotocols = 'rest';
    }

    /**
     * Build the registration task with the given custom data.
     *
     * @param array|null $customdata Custom data, or null to set none.
     * @return setup_corolair_connection_task
     */
    private function registration_task(?array $customdata): setup_corolair_connection_task {
        $task = new setup_corolair_connection_task();
        $task->set_custom_data((object)($customdata ?? []));
        return $task;
    }

    /**
     * Run the task and assert it failed with the expected error, contacting nobody.
     *
     * @param setup_corolair_connection_task $task Task to run.
     * @param string $errorcode Expected moodle_exception error code.
     * @return void
     */
    private function assert_refuses(setup_corolair_connection_task $task, string $errorcode): void {
        $sink = $this->redirectEvents();
        try {
            $task->execute();
            $this->fail("The task should have refused to run with '{$errorcode}'.");
        } catch (\moodle_exception $exception) {
            $this->assertSame($errorcode, $exception->errorcode);
        } finally {
            $events = $sink->get_events();
            $sink->close();
        }

        foreach ($events as $event) {
            $this->assertNotInstanceOf(
                remote_request_completed::class,
                $event,
                'A refused registration must not reach Raison.'
            );
        }
    }

    /**
     * Return the installed external service ID.
     *
     * @return int
     */
    private function service_id(): int {
        global $DB;

        return (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest'], MUST_EXIST);
    }

    /**
     * Registration cannot run without a recorded consent.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_requires_consent(): void {
        $this->resetAfterTest();

        set_config('setupconsented', 0, 'local_corolair');

        $this->assert_refuses($this->registration_task(['adminid' => (int)get_admin()->id]), 'setupconsentmissing');
    }

    /**
     * Registration cannot run once web services have been turned back off.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_requires_web_services(): void {
        global $CFG;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        set_config('enablewebservices', 0);
        $CFG->enablewebservices = 0;

        $this->assert_refuses($this->registration_task(['adminid' => $adminid]), 'webservicesenableerror');
    }

    /**
     * Registration cannot run once REST has been turned back off.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_requires_rest(): void {
        global $CFG;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        set_config('webserviceprotocols', 'soap');
        $CFG->webserviceprotocols = 'soap';

        $this->assert_refuses($this->registration_task(['adminid' => $adminid]), 'restprotocolenableerror');
    }

    /**
     * A task with no administrator in its custom data is rejected.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_requires_an_administrator_in_custom_data(): void {
        $this->resetAfterTest();

        $this->make_setup_ready((int)get_admin()->id);

        $this->assert_refuses($this->registration_task(null), 'invalidrequest');
    }

    /**
     * The task's administrator must be the one who actually consented.
     *
     * Otherwise a queued task could register the site under an administrator who never
     * saw the disclosure.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_rejects_a_substituted_administrator(): void {
        $this->resetAfterTest();

        $this->make_setup_ready((int)get_admin()->id);
        $other = $this->getDataGenerator()->create_user();

        $this->assert_refuses(
            $this->registration_task(['adminid' => (int)$other->id]),
            'setupconsentmissing'
        );
    }

    /**
     * A consenting administrator who has since been demoted cannot register the site.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_rejects_a_demoted_administrator(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->make_setup_ready((int)$user->id);

        $sink = $this->redirectEvents();
        try {
            $this->registration_task(['adminid' => (int)$user->id])->execute();
            $this->fail('A user without moodle/site:config must not register the site.');
        } catch (\required_capability_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        } finally {
            $events = $sink->get_events();
            $sink->close();
        }

        foreach ($events as $event) {
            $this->assertNotInstanceOf(remote_request_completed::class, $event);
        }
    }

    /**
     * A consenting administrator who has been deleted cannot register the site.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_rejects_a_deleted_administrator(): void {
        $this->resetAfterTest();

        $this->make_setup_ready(-1);

        $this->expectException(\dml_missing_record_exception::class);
        $this->registration_task(['adminid' => -1])->execute();
    }

    /**
     * Loopback site URLs cannot be registered.
     *
     * Raison has to call back into Moodle to verify the token, which a loopback address
     * makes impossible. Failing here gives a clear reason instead of an opaque timeout.
     *
     * @return array[] Data sets of [wwwroot].
     */
    public static function loopback_wwwroot_provider(): array {
        return [
            'localhost' => ['http://localhost/moodle'],
            'localhost with a port' => ['http://localhost:8080/moodle'],
            'uppercase localhost' => ['http://LOCALHOST/moodle'],
            'ipv4 loopback' => ['http://127.0.0.1/moodle'],
            // PHP reports this host as "[::1]", brackets included, so a literal
            // '::1' comparison silently missed it and the task attempted a real request.
            'ipv6 loopback' => ['http://[::1]/moodle'],
        ];
    }

    /**
     * A site that Raison cannot reach is refused before any request is attempted.
     *
     * @dataProvider loopback_wwwroot_provider
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @param string $wwwroot Site URL under test.
     * @return void
     */
    public function test_registration_rejects_a_loopback_site(string $wwwroot): void {
        global $CFG;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        $CFG->wwwroot = $wwwroot;

        $this->assert_refuses($this->registration_task(['adminid' => $adminid]), 'localhosterror');
    }

    /**
     * Registration cannot run if the external service is missing.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_requires_the_external_service(): void {
        global $DB;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        $DB->set_field('external_services', 'shortname', 'corolair_rest_renamed', [
            'shortname' => 'corolair_rest',
        ]);

        $this->assert_refuses($this->registration_task(['adminid' => $adminid]), 'servicecreationerror');
    }

    /**
     * A pending credential migration defers registration instead of racing it.
     *
     * Both would replace the API key. Letting registration proceed while a migration is
     * queued would leave whichever finished second holding a key the other invalidated.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_defers_to_a_pending_migration(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        set_config('setupcompleted', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        $this->registration_task(['adminid' => $adminid])->execute();
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(0, get_config('local_corolair', 'setupcompleted'));
        foreach ($events as $event) {
            $this->assertNotInstanceOf(remote_request_completed::class, $event);
        }
    }

    /**
     * An already-migrated site with a live key completes without re-registering.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_completes_for_an_already_connected_site(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');
        $token = webservice_token_manager::create_token($adminid, $this->service_id());
        set_config('webservicetokenid', (int)$token->id, 'local_corolair');

        $sink = $this->redirectEvents();
        $this->registration_task(['adminid' => $adminid])->execute();
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(1, get_config('local_corolair', 'setupcompleted'));
        foreach ($events as $event) {
            $this->assertNotInstanceOf(remote_request_completed::class, $event);
        }
    }

    /**
     * A migrated site with an untracked token adopts it rather than minting another.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task::execute
     * @return void
     */
    public function test_registration_adopts_an_untracked_token(): void {
        global $DB;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $this->make_setup_ready($adminid);
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('legacycredentialmigrationcompletedat', time(), 'local_corolair');
        unset_config('webservicetokenid', 'local_corolair');
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        $token = webservice_token_manager::create_token($adminid, $this->service_id());

        $this->registration_task(['adminid' => $adminid])->execute();

        $this->assertEquals((int)$token->id, (int)get_config('local_corolair', 'webservicetokenid'));
        $this->assertEquals(1, get_config('local_corolair', 'setupcompleted'));
        $this->assertSame(1, $DB->count_records('external_tokens', [
            'externalserviceid' => $this->service_id(),
        ]));
    }

    /**
     * The migration task drops out when no administrator can be resolved.
     *
     * Looping forever against a site with no usable owner would retry indefinitely for
     * something only an administrator can resolve.
     *
     * @covers \local_corolair\task\migrate_legacy_credentials_task::execute
     * @return void
     */
    public function test_migration_task_stops_without_an_administrator(): void {
        $this->resetAfterTest();

        unset_config('setupconsentedby', 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        $task = new migrate_legacy_credentials_task();
        $task->set_custom_data((object)[]);
        $task->execute();

        $this->assertEquals(
            1,
            get_config('local_corolair', 'legacycredentialmigrationpending'),
            'The migration should remain pending for an administrator to resolve.'
        );
        $this->assertFalse(get_config('local_corolair', 'legacymigrationtokenid'));
    }

    /**
     * The migration task falls back to the recorded consenting administrator.
     *
     * @covers \local_corolair\task\migrate_legacy_credentials_task::execute
     * @return void
     */
    public function test_migration_task_falls_back_to_the_recorded_administrator(): void {
        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        set_config('setupconsentedby', $adminid, 'local_corolair');
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');
        // Fails inside run() after the local state is built, which is enough to show the
        // administrator was resolved without needing the network.
        set_config('apikey', 'malformedinheritedkey', 'local_corolair');

        $task = new migrate_legacy_credentials_task();
        $task->set_custom_data((object)[]);

        try {
            $task->execute();
            $this->fail('A malformed inherited key should surface as an error.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('legacycredentialmigrationfailed', $exception->errorcode);
        }
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'legacymigrationtokenid'));
    }

    /**
     * The scheduled task has a resolvable, localized name.
     *
     * A missing string here shows up in the site's scheduled task administration screen.
     *
     * @covers \local_corolair\task\rotate_webservice_token_task::get_name
     * @return void
     */
    public function test_scheduled_task_has_a_name(): void {
        $name = (new rotate_webservice_token_task())->get_name();

        $this->assertNotEmpty($name);
        $this->assertStringNotContainsString('[[', $name);
        $this->assertSame(get_string('taskrotatewebservicetoken', 'local_corolair'), $name);
    }

    /**
     * Both maintenance tasks are safe to run on an unconfigured site.
     *
     * The scheduled one runs hourly on every install, including those that never
     * completed setup, so it has to be a no-op there rather than an hourly cron failure.
     *
     * @covers \local_corolair\task\rotate_webservice_token_task::execute
     * @covers \local_corolair\task\retry_webservice_token_rotation_task::execute
     * @return void
     */
    public function test_maintenance_tasks_are_safe_on_an_unconfigured_site(): void {
        $this->resetAfterTest();

        set_config('setupcompleted', 0, 'local_corolair');
        unset_config('apikey', 'local_corolair');
        unset_config('legacycredentialmigrationblocked', 'local_corolair');

        $sink = $this->redirectEvents();
        (new rotate_webservice_token_task())->execute();
        (new retry_webservice_token_rotation_task())->execute();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
    }

    /**
     * The hourly task recovers a migration that has lost its ad-hoc task.
     *
     * This is the only automatic path back: maintain() stands down while the pending flag
     * is set, so without the recovery the site would keep the inherited credential and stop
     * maintaining tokens altogether.
     *
     * @covers \local_corolair\task\rotate_webservice_token_task::execute
     * @return void
     */
    public function test_scheduled_task_requeues_a_stalled_migration(): void {
        global $DB;

        $this->resetAfterTest();

        $adminid = (int)get_admin()->id;
        $DB->delete_records('external_tokens', ['externalserviceid' => $this->service_id()]);
        set_config('apikey', 'org_instance.inheritedsecret', 'local_corolair');
        $DB->insert_record('external_tokens', (object)[
            'token' => bin2hex(random_bytes(32)),
            'privatetoken' => null,
            'tokentype' => 0,
            'userid' => $adminid,
            'externalserviceid' => $this->service_id(),
            'contextid' => \context_system::instance()->id,
            'creatorid' => $adminid,
            'iprestriction' => null,
            'validuntil' => 0,
            'timecreated' => time(),
            'lastaccess' => null,
        ]);
        set_config('legacycredentialmigrationpending', 1, 'local_corolair');

        (new rotate_webservice_token_task())->execute();

        $this->assertCount(
            1,
            \core\task\manager::get_adhoc_tasks('\local_corolair\task\migrate_legacy_credentials_task')
        );
    }
}
