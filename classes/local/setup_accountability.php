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
 * The record of who authorised the integration, and who answers for it now.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Keeps the setup accountability record apart from the people it names.
 *
 * Setup records two facts: that an administrator consented to the integration, and that an
 * administrator acknowledged its disclosure, each with the time it happened. The facts are
 * the evidence. The administrator's user ID beside each one is personal data, and it used to
 * be kept for as long as the plugin was installed, because the same setting also decided who
 * received token warnings and whether token maintenance ran at all.
 *
 * Those three jobs are separate here. The facts stay. The identity is kept while the
 * administrator still has an account on the site and has not had their data erased; when
 * either ends, it is removed and the time it was removed is recorded in its place, so the
 * site can still show consent was given without saying by whom. Warnings go to whoever can
 * act on them when they are sent, never to an identity stored once at setup. Uninstalling
 * removes all of it, as before.
 */
final class setup_accountability {
    /** @var string Setting naming the administrator who consented to the integration. */
    public const CONSENTED_BY = 'setupconsentedby';

    /** @var string Setting naming the administrator who acknowledged the disclosure. */
    public const ACKNOWLEDGED_BY = 'setupdisclosureacknowledgedby';

    /**
     * Each setting that names an administrator, with the setting recording when it was erased.
     *
     * The erasure time is what lets the record keep saying "consent was given" without a
     * name. A missing ID alone cannot say that: it is also what a site that never consented
     * holds, and grandfather_consent() in upgrade_migrator would read it that way and write a
     * different administrator in, dated today -- evidence of an act that never happened.
     */
    private const ERASED_AT = [
        self::CONSENTED_BY => 'setupconsentedbyerasedat',
        self::ACKNOWLEDGED_BY => 'setupdisclosureacknowledgedbyerasedat',
    ];

    /**
     * Record that an administrator consented to the integration, now.
     *
     * @param int $adminid Administrator who consented.
     * @return void
     */
    public static function record_consent(int $adminid): void {
        self::record(self::CONSENTED_BY, 'setupconsentedat', $adminid);
    }

    /**
     * Record that an administrator acknowledged the disclosure, now.
     *
     * @param int $adminid Administrator who acknowledged it.
     * @return void
     */
    public static function record_disclosure_acknowledgement(int $adminid): void {
        self::record(self::ACKNOWLEDGED_BY, 'setupdisclosureacknowledgedat', $adminid);
    }

    /**
     * Whether consent was recorded, whether or not the administrator is still named.
     *
     * @return bool
     */
    public static function consent_recorded(): bool {
        return self::recorded(self::CONSENTED_BY);
    }

    /**
     * Whether a disclosure acknowledgement was recorded, whether or not its author is still named.
     *
     * @return bool
     */
    public static function disclosure_acknowledgement_recorded(): bool {
        return self::recorded(self::ACKNOWLEDGED_BY);
    }

    /**
     * Remove a user from the record, keeping the facts and when they happened.
     *
     * Only settings that name this user change. Nothing else is touched: the consent flag,
     * the times and the disclosure version are what the record is evidence of, and token
     * maintenance keeps running on them.
     *
     * @param int $userid User whose identity is to be removed.
     * @return void
     */
    public static function erase(int $userid): void {
        if ($userid <= 0) {
            return;
        }
        foreach (array_keys(self::ERASED_AT) as $setting) {
            if ((int)get_config('local_corolair', $setting) === $userid) {
                self::erase_setting($setting);
            }
        }
    }

    /**
     * Remove every administrator from the record, keeping the facts.
     *
     * @return void
     */
    public static function erase_all(): void {
        foreach (array_keys(self::ERASED_AT) as $setting) {
            if ((int)get_config('local_corolair', $setting) > 0) {
                self::erase_setting($setting);
            }
        }
    }

    /**
     * Remove administrators whose accounts no longer exist.
     *
     * Used once on upgrade, for sites where an administrator's account was deleted before
     * the user_deleted observer existed, and left the record pointing at it.
     *
     * @return void
     */
    public static function erase_deleted_actors(): void {
        global $DB;

        foreach (array_keys(self::ERASED_AT) as $setting) {
            $userid = (int)get_config('local_corolair', $setting);
            if ($userid > 0 && !$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
                self::erase_setting($setting);
            }
        }
    }

    /**
     * Observer: a deleted account leaves the record.
     *
     * A privacy erasure request reaches the provider first, but an administrator can also
     * delete an account directly, and that never reaches the provider. Either way the account
     * is gone, and the record has no one left to name.
     *
     * @param \core\event\user_deleted $event The deletion.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        self::erase((int)$event->objectid);
    }

    /**
     * The administrators to tell when the integration needs attention.
     *
     * The administrator who consented, while they can still act on it. Otherwise every
     * active site administrator: someone has to hear that the integration is failing, and
     * the person who set it up may have left, lost the role, or had their data erased.
     * Resolved at send time, so it follows the site's administrators as they change.
     *
     * @return \stdClass[] User records; empty only on a site with no active administrator.
     */
    public static function alert_recipients(): array {
        $consentedby = (int)get_config('local_corolair', self::CONSENTED_BY);
        if (self::is_usable_admin($consentedby)) {
            return [\core_user::get_user($consentedby, '*', MUST_EXIST)];
        }
        return array_values(array_filter(get_admins(), function ($admin) {
            return empty($admin->deleted) && empty($admin->suspended);
        }));
    }

    /**
     * Whether a user exists, is active, and can administer the site.
     *
     * @param int $userid User ID.
     * @return bool
     */
    public static function is_usable_admin(int $userid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
            return false;
        }
        return has_capability('moodle/site:config', \context_system::instance(), $userid);
    }

    /**
     * Record an administrator's act, now, replacing any erased predecessor.
     *
     * @param string $setting Setting naming the administrator.
     * @param string $timesetting Setting holding when they acted.
     * @param int $adminid Administrator who acted.
     * @return void
     */
    private static function record(string $setting, string $timesetting, int $adminid): void {
        set_config($setting, $adminid, 'local_corolair');
        set_config($timesetting, time(), 'local_corolair');
        unset_config(self::ERASED_AT[$setting], 'local_corolair');
    }

    /**
     * Whether a setting names an administrator, or did until they were erased.
     *
     * @param string $setting Setting naming the administrator.
     * @return bool
     */
    private static function recorded(string $setting): bool {
        return (int)get_config('local_corolair', $setting) > 0
            || (int)get_config('local_corolair', self::ERASED_AT[$setting]) > 0;
    }

    /**
     * Replace the identity in one setting with the time it was removed.
     *
     * @param string $setting Setting naming the administrator.
     * @return void
     */
    private static function erase_setting(string $setting): void {
        unset_config($setting, 'local_corolair');
        set_config(self::ERASED_AT[$setting], time(), 'local_corolair');
    }
}
