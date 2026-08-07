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
 * Consent-gated Corolair setup manager.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Applies the site-wide settings approved by an administrator and queues registration.
 */
final class setup_manager {
    /**
     * Record the current disclosure acknowledgment.
     *
     * @param int $adminid Administrator acknowledging the disclosure.
     * @return void
     */
    public static function acknowledge_disclosure(int $adminid): void {
        self::require_setup_administrator($adminid);
        set_config('setupdisclosureversion', integration_disclosure::VERSION, 'local_corolair');
        set_config('setupdisclosureacknowledgedby', $adminid, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', time(), 'local_corolair');
        \local_corolair\event\integration_disclosure_acknowledged::create([
            'context' => \context_system::instance(),
            'userid' => $adminid,
            'other' => ['version' => integration_disclosure::VERSION],
        ])->trigger();
    }

    /**
     * Whether this administrator acknowledged the current disclosure.
     *
     * Completed integrations are grandfathered so upgrades do not interrupt them.
     *
     * @param int $adminid Administrator starting setup.
     * @return bool
     */
    public static function disclosure_acknowledged(int $adminid): bool {
        if ((bool)get_config('local_corolair', 'setupcompleted')) {
            return true;
        }
        return (
            (string)get_config('local_corolair', 'setupdisclosureversion') === integration_disclosure::VERSION &&
            (int)get_config('local_corolair', 'setupdisclosureacknowledgedby') === $adminid &&
            (int)get_config('local_corolair', 'setupdisclosureacknowledgedat') > 0
        );
    }

    /**
     * Return the currently enabled web service protocols.
     *
     * @return string[]
     */
    public static function get_enabled_protocols(): array {
        global $CFG;

        $configured = (string)($CFG->webserviceprotocols ?? '');
        $protocols = array_filter(array_map('trim', explode(',', $configured)));
        return array_values(array_unique($protocols));
    }

    /**
     * Whether Moodle web services are enabled.
     *
     * @return bool
     */
    public static function webservices_enabled(): bool {
        global $CFG;
        return !empty($CFG->enablewebservices);
    }

    /**
     * Whether the REST protocol is enabled.
     *
     * @return bool
     */
    public static function rest_enabled(): bool {
        return in_array('rest', self::get_enabled_protocols(), true);
    }

    /**
     * Whether setup would expand the site-wide web-service attack surface.
     *
     * @return bool
     */
    public static function enablement_consent_required(): bool {
        return !self::webservices_enabled() || !self::rest_enabled();
    }

    /**
     * Record consent, enable the required site settings, and queue registration.
     *
     * The caller must authenticate, authorize, and sesskey-protect the request.
     *
     * @param int $adminid Administrator starting setup.
     * @param bool $enablementconsent Whether the administrator approved enabling missing requirements.
     * @param bool|null $disablerotation Chosen token-rotation policy, or null to leave it unchanged.
     * @return bool True when a new task was queued.
     */
    public static function activate(
        int $adminid,
        bool $enablementconsent = false,
        ?bool $disablerotation = null
    ): bool {
        global $CFG, $DB;

        $admin = self::require_setup_administrator($adminid);
        if (!self::disclosure_acknowledged($adminid)) {
            throw new \moodle_exception('disclosuremissing', 'local_corolair');
        }

        $consentrequired = self::enablement_consent_required();
        if ($consentrequired && !$enablementconsent) {
            throw new \moodle_exception('setupconsentmissing', 'local_corolair');
        }
        $transaction = $DB->start_delegated_transaction();

        if (!self::webservices_enabled()) {
            set_config('enablewebservices', 1);
            $CFG->enablewebservices = 1;
        }

        $protocols = self::get_enabled_protocols();
        if (!in_array('rest', $protocols, true)) {
            $protocols[] = 'rest';
            set_config('webserviceprotocols', implode(',', $protocols));
            $CFG->webserviceprotocols = implode(',', $protocols);
        }

        // This legacy flag also means that an authorized administrator initiated setup.
        set_config('setupconsented', 1, 'local_corolair');
        set_config('setupconsentrequired', $consentrequired ? 1 : 0, 'local_corolair');
        set_config('setupconsentedby', $admin->id, 'local_corolair');
        set_config('setupconsentedat', time(), 'local_corolair');
        set_config('setupcompleted', 0, 'local_corolair');

        // Recording the rotation policy here rather than leaving it to the settings page is
        // what makes the very first token the right shape: the registration task queued below
        // reads the configured lifetime when it mints the token, so choosing now saves the
        // site an immediate second rotation.
        //
        // Deliberately not local_corolair_disabletokenrotation_updated(): that callback also
        // queues retry_webservice_token_rotation_task, which here would join the same
        // transaction as the registration task and then do nothing, because maintain()
        // returns while setupcompleted is 0. The event is still emitted so the audit trail
        // matches the settings-page path.
        if ($disablerotation !== null && !webservice_token_manager::rotation_setting_is_forced()) {
            $previous = webservice_token_manager::rotation_disabled();
            set_config('disabletokenrotation', $disablerotation ? 1 : 0, 'local_corolair');
            if ($previous !== $disablerotation) {
                webservice_token_manager::record_rotation_policy_change();
            }
        }

        $queued = self::queue_registration_task($admin->id);
        $transaction->allow_commit();

        return $queued;
    }

    /**
     * Validate an administrator used by the setup flow.
     *
     * @param int $adminid User ID.
     * @return \stdClass Minimal user record.
     */
    private static function require_setup_administrator(int $adminid): \stdClass {
        global $DB;

        $context = \context_system::instance();
        $admin = $DB->get_record('user', ['id' => $adminid, 'deleted' => 0], 'id', MUST_EXIST);
        if (!has_capability('moodle/site:config', $context, $admin->id)) {
            throw new \required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
        }
        return $admin;
    }

    /**
     * Queue one registration task unless one is already pending.
     *
     * @param int $adminid Administrator used for registration.
     * @return bool True when a new task was queued.
     */
    private static function queue_registration_task(int $adminid): bool {
        $task = new \local_corolair\task\setup_corolair_connection_task();
        $task->set_custom_data((object)['adminid' => $adminid]);
        return \core\task\manager::queue_adhoc_task($task, true);
    }
}
