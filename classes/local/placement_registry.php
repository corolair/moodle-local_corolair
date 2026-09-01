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
 * Ownership record for the LTI exam activities this plugin creates.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Records which LTI activities this plugin created, and which tool it may create them from.
 *
 * Why this exists: the exam functions used to resolve their target straight out of the {lti}
 * table and check moodle/course:manageactivities. The service account holds that capability at
 * system context, so it passes in every course on the site -- which made any valid {lti}.id a
 * valid target for rename or deletion. A stale identifier from Raison was enough to remove an
 * unrelated teacher's activity. Membership of this table is the real boundary; the capability
 * check stays, but it is no longer the only thing standing between a bad id and course_delete_module().
 *
 * The host allow-list is the other half. Raison supplies the {lti_types}.id to place, and the
 * plugin has no way to know which type id belongs to Raison -- the LTI integration is set up
 * separately from this plugin and may be configured before it, after it, or never. So rather than
 * asking *which type is ours*, this class asks *where does this type actually launch*, which is
 * answerable locally and stays correct however the two integrations were sequenced.
 *
 * Note what this class does not defend against: a site administrator. They can change the setting
 * below, and they could create placements by hand anyway. The boundary here is against a leaked or
 * misused service token, and it holds because the service account provably never receives
 * moodle/site:config -- see plugin_definition_test::test_service_role_grants_no_administrative_capability.
 */
final class placement_registry {
    /** Table holding one row per placement this plugin created. */
    public const TABLE = 'local_corolair_placement';

    /**
     * Host the Raison LTI tool launches from.
     *
     * Kept as a constant, and shipped as the default of an administrator-visible setting rather
     * than a hidden one: an administrator who has to change it is an administrator whose site is
     * already broken, and a setting they cannot see is a setting they cannot use to recover.
     */
    public const DEFAULT_TOOL_HOST = 'services.corolair.dev';

    /** Setting holding the administrator override for the host above. */
    private const HOST_SETTING = 'ltitoolhost';

    /**
     * The host an LTI tool type must launch from to be usable for exam placement.
     *
     * The stored value is treated as untrusted. admin_setting_configtext::validate() only runs for
     * the settings form -- set_config(), CLI and $CFG->forced_plugin_settings all bypass it -- so a
     * site can hold anything here. Every rejected value falls back to the constant, which means a
     * misconfigured site keeps working against the real host. Falling back to "allow everything"
     * would silently disable the control, and falling back to "allow nothing" would make the
     * failure unrecoverable through the very setting meant to recover it.
     *
     * @return string Lower-case host name.
     */
    public static function allowed_host(): string {
        $configured = strtolower(trim((string)get_config('local_corolair', self::HOST_SETTING)));
        if ($configured === '') {
            return self::DEFAULT_TOOL_HOST;
        }
        if (!\core\ip_utils::is_domain_name($configured)) {
            return self::DEFAULT_TOOL_HOST;
        }
        return $configured;
    }

    /**
     * Reject a tool type that does not launch from the allowed host.
     *
     * Reads {lti_types}.baseurl rather than .tooldomain, which is not the same question. Core fills
     * tooldomain from $config->lti_tooldomain whenever an administrator supplies one and only
     * derives it from the URL otherwise (mod/lti/locallib.php), and the regex it derives with drops
     * "www." and keeps any port. baseurl is what actually launches, so baseurl is what is checked.
     *
     * MUST_EXIST is retained: an unknown type id has always raised dml_missing_record_exception
     * here and callers depend on that distinction.
     *
     * @param int $typeid {lti_types}.id supplied by the caller.
     * @return void
     * @throws \dml_missing_record_exception If the tool type does not exist.
     * @throws \moodle_exception If it exists but launches somewhere else.
     */
    public static function assert_tool_host_allowed(int $typeid): void {
        global $DB;

        $type = $DB->get_record('lti_types', ['id' => $typeid], 'id, baseurl', MUST_EXIST);
        $allowed = self::allowed_host();

        if (self::url_host($type->baseurl) !== $allowed) {
            throw new \moodle_exception(
                'placementtoolnotallowed',
                'local_corolair',
                '',
                (object)[
                    'host' => (string)self::url_host($type->baseurl),
                    'allowed' => $allowed,
                ]
            );
        }
    }

    /**
     * Return the host of an https URL, or the empty string if it is not one we accept.
     *
     * Mirrors redirect_url_validator::validate() deliberately, rather than reimplementing URL
     * parsing a second way. The port rule matters more than it looks: without it
     * https://services.corolair.dev:8443/ passes a bare host comparison. The userinfo case is
     * handled for free by comparing the parsed host, since https://services.corolair.dev@evil.com/
     * parses to evil.com.
     *
     * @param string $url URL to inspect.
     * @return string Lower-case host, or '' when the URL is unusable.
     */
    private static function url_host(string $url): string {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        try {
            $parts = parse_url($url);
        } catch (\ValueError $exception) {
            return '';
        }
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = array_key_exists('port', $parts) ? (int)$parts['port'] : null;

        if ($scheme !== 'https' || $host === '' || ($port !== null && $port !== 443)) {
            return '';
        }
        return $host;
    }

    /**
     * Record a placement this plugin just created.
     *
     * The pre-emptive delete is not redundant. MariaDB recomputes AUTO_INCREMENT as MAX(id)+1 on
     * restart rather than persisting it, so an {lti}.id can be handed out twice across a restart if
     * the highest row was deleted in between. Without this, the leftover row from the first life of
     * that id collides with the unique index and rolls back the whole creation.
     *
     * @param int $ltiinstanceid Created {lti}.id.
     * @param int $courseid Course the activity was created in.
     * @param int $typeid Tool type the activity was created from.
     * @return void
     */
    public static function record(int $ltiinstanceid, int $courseid, int $typeid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['ltiinstanceid' => $ltiinstanceid]);
        $DB->insert_record(self::TABLE, (object)[
            'ltiinstanceid' => $ltiinstanceid,
            'courseid' => $courseid,
            'typeid' => $typeid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Return the ownership row for a placement, or refuse.
     *
     * Looked up by ltiinstanceid alone, on purpose. Moodle lets an activity move to another course,
     * updating {course_modules}.course and {lti}.course without telling this plugin, so adding
     * courseid to this predicate would make a legitimately moved placement unmanageable.
     *
     * @param int $ltiinstanceid {lti}.id supplied by the caller.
     * @return \stdClass The ownership row.
     * @throws \moodle_exception If this plugin did not create that activity.
     */
    public static function owned_or_fail(int $ltiinstanceid): \stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['ltiinstanceid' => $ltiinstanceid]);
        if (!$record) {
            throw new \moodle_exception('placementnotowned', 'local_corolair');
        }
        return $record;
    }

    /**
     * Drop the ownership row for a placement, if one is held.
     *
     * @param int $ltiinstanceid {lti}.id.
     * @return void
     */
    public static function forget(int $ltiinstanceid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['ltiinstanceid' => $ltiinstanceid]);
    }

    /**
     * Whether the activity behind an owned placement still exists in Moodle.
     *
     * A teacher can delete the activity through the Moodle interface at any time, which leaves the
     * ownership row behind. Callers use this to tell "already gone" apart from "not yours", which
     * are the same DML error otherwise.
     *
     * @param int $ltiinstanceid {lti}.id.
     * @return bool
     */
    public static function instance_exists(int $ltiinstanceid): bool {
        global $DB;

        return $DB->record_exists('lti', ['id' => $ltiinstanceid]);
    }
}
