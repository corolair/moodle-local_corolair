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
 * Reminds site administrators that Raison setup was never started.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Sends a rate-limited notification while the plugin sits installed and inactive.
 *
 * The banner in {@see setup_notice} only reaches an administrator who happens to load a page.
 * This reaches the ones who do not -- which, when the installation was delegated, includes the
 * person who has to finish it: the notification is delivered by Moodle's messaging system, so
 * it lands in the notification bell and, subject to their preferences, in their mailbox.
 *
 * Deliberately bounded. An administrator who has decided not to activate the plugin should not
 * be chased indefinitely, and the banner keeps saying the same thing for as long as it is true.
 */
final class setup_reminder {
    /** @var int Minimum delay between two reminders. */
    private const INTERVAL = WEEKSECS;

    /** @var int Reminders sent before the plugin stops asking. */
    private const MAX_REMINDERS = 3;

    /**
     * Send a reminder when one is due.
     *
     * @return bool True when a reminder was sent.
     */
    public static function maintain(): bool {
        if (!setup_manager::setup_pending()) {
            self::reset();
            return false;
        }

        $sent = (int)get_config('local_corolair', 'setupremindercount');
        if ($sent >= self::MAX_REMINDERS) {
            return false;
        }
        $lastsent = (int)get_config('local_corolair', 'setupremindersentat');
        if ($lastsent > time() - self::INTERVAL) {
            return false;
        }

        $recipients = self::recipients();
        if (!$recipients) {
            return false;
        }
        foreach ($recipients as $recipient) {
            self::send($recipient);
        }

        // Counted per run rather than per recipient, so adding an administrator does not
        // shorten how long the remaining reminders last.
        set_config('setupremindersentat', time(), 'local_corolair');
        set_config('setupremindercount', $sent + 1, 'local_corolair');
        return true;
    }

    /**
     * Administrators who can finish setup.
     *
     * Site administrators rather than every holder of moodle/site:config: they are the set
     * this can be resolved for without a site-wide capability search on every cron run, and
     * they always pass the capability check setup.php makes. A manager who holds the
     * capability without being a site administrator is not messaged, but still sees the
     * banner on their own pages.
     *
     * @return \stdClass[]
     */
    private static function recipients(): array {
        return array_values(array_filter(get_admins(), function ($admin) {
            return empty($admin->deleted) && empty($admin->suspended);
        }));
    }

    /**
     * Send one reminder.
     *
     * @param \stdClass $recipient Administrator to notify.
     * @return void
     */
    private static function send(\stdClass $recipient): void {
        $setupurl = (new \moodle_url('/local/corolair/setup.php'))->out(false);

        $message = new \core\message\message();
        $message->component = 'local_corolair';
        $message->name = 'setuppending';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = get_string('setuppendingsubject', 'local_corolair');
        $message->fullmessage = get_string('setuppendingbody', 'local_corolair', $setupurl);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $setupurl;
        $message->contexturlname = get_string('setupaction', 'local_corolair');
        message_send($message);
    }

    /**
     * Forget the reminder history once setup is under way.
     *
     * Keeps a plugin that is removed and installed again from inheriting an exhausted
     * reminder budget, which would leave the second installation silent.
     *
     * @return void
     */
    private static function reset(): void {
        if ((int)get_config('local_corolair', 'setupremindercount') === 0) {
            return;
        }
        unset_config('setupremindercount', 'local_corolair');
        unset_config('setupremindersentat', 'local_corolair');
    }
}
