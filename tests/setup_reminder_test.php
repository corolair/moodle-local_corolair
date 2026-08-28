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
 * Tests for the unfinished-setup reminder.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\setup_reminder;

/**
 * Verifies that the reminder reaches administrators, and then stops.
 *
 * The banner only reaches an administrator who loads a page; this reaches the one who was not
 * at the keyboard when the plugin was installed, which after a delegated installation is the
 * person who actually has to finish it. Its value therefore depends on it arriving at all --
 * and its welcome depends on it not arriving forever.
 */
final class setup_reminder_test extends \advanced_testcase {
    /**
     * Put the site in the state a command-line installation leaves behind.
     *
     * @return void
     */
    private function make_site_freshly_installed(): void {
        set_config('setupconsented', 0, 'local_corolair');
        set_config('setupcompleted', 0, 'local_corolair');
        unset_config('legacycredentialmigrationpending', 'local_corolair');
        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
    }

    /**
     * Run one due reminder and return the messages it produced.
     *
     * @return \stdClass[]
     */
    private function run_due_reminder(): array {
        $sink = $this->redirectMessages();
        setup_reminder::maintain();
        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    /**
     * Every site administrator is told, and told where to go.
     *
     * @covers \local_corolair\local\setup_reminder::maintain
     * @return void
     */
    public function test_reminder_reaches_every_site_administrator(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->make_site_freshly_installed();
        $second = $this->getDataGenerator()->create_user();
        $CFG->siteadmins = $CFG->siteadmins . ',' . $second->id;

        $messages = $this->run_due_reminder();

        $this->assertCount(2, $messages);
        $recipients = array_map(function ($message) {
            return (int)$message->useridto;
        }, $messages);
        $this->assertContains((int)$second->id, $recipients);
        $message = reset($messages);
        $this->assertSame('setuppending', $message->eventtype);
        $this->assertStringContainsString('/local/corolair/setup.php', $message->fullmessage);
    }

    /**
     * One reminder a week, however often cron runs the task.
     *
     * @covers \local_corolair\local\setup_reminder::maintain
     * @return void
     */
    public function test_reminder_is_rate_limited(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();

        $this->assertNotEmpty($this->run_due_reminder());
        $this->assertSame([], $this->run_due_reminder(), 'The daily task must not send daily.');
        $this->assertEquals(1, get_config('local_corolair', 'setupremindercount'));
    }

    /**
     * The plugin gives up after three reminders rather than nagging indefinitely.
     *
     * An administrator who has decided not to activate it has made a decision, and the banner
     * goes on stating the same thing for as long as it stays true.
     *
     * @covers \local_corolair\local\setup_reminder::maintain
     * @return void
     */
    public function test_reminder_stops_after_three(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();

        for ($sent = 1; $sent <= 3; $sent++) {
            $this->assertNotEmpty($this->run_due_reminder(), "Reminder {$sent} should have been sent.");
            // A week later, as far as the rate limit is concerned.
            set_config('setupremindersentat', time() - WEEKSECS - 1, 'local_corolair');
        }

        $this->assertSame([], $this->run_due_reminder());
        $this->assertEquals(3, get_config('local_corolair', 'setupremindercount'));
    }

    /**
     * Starting setup ends the reminders and clears what they recorded.
     *
     * Leaving the count behind would let a plugin that is removed and installed again
     * inherit an exhausted budget, and the second installation would go unannounced.
     *
     * @covers \local_corolair\local\setup_reminder::maintain
     * @return void
     */
    public function test_consent_ends_the_reminders_and_clears_their_history(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();
        $this->run_due_reminder();

        set_config('setupconsented', 1, 'local_corolair');
        set_config('setupremindersentat', time() - WEEKSECS - 1, 'local_corolair');

        $this->assertSame([], $this->run_due_reminder());
        $this->assertFalse(get_config('local_corolair', 'setupremindercount'));
        $this->assertFalse(get_config('local_corolair', 'setupremindersentat'));
    }

    /**
     * A site that is already connected is never reminded to connect.
     *
     * @covers \local_corolair\local\setup_reminder::maintain
     * @covers \local_corolair\local\setup_manager::setup_pending
     * @return void
     */
    public function test_reminder_spares_a_connected_legacy_site(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();
        set_config('apikey', 'a-real-inherited-key', 'local_corolair');

        $this->assertSame([], $this->run_due_reminder());
    }

    /**
     * The scheduled task is nothing more than the reminder on a timer.
     *
     * @covers \local_corolair\task\send_setup_reminder_task::execute
     * @return void
     */
    public function test_scheduled_task_sends_the_reminder(): void {
        $this->resetAfterTest();
        $this->make_site_freshly_installed();

        $sink = $this->redirectMessages();
        (new \local_corolair\task\send_setup_reminder_task())->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
    }
}
