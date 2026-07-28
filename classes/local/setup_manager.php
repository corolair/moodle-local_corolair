<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

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
     * @return bool True when a new task was queued.
     */
    public static function activate(int $adminid, bool $enablementconsent = false): bool {
        global $CFG, $DB;

        $context = \context_system::instance();
        $admin = $DB->get_record('user', ['id' => $adminid, 'deleted' => 0], 'id', MUST_EXIST);
        if (!has_capability('moodle/site:config', $context, $admin->id)) {
            throw new \required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
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

        $queued = self::queue_registration_task($admin->id);
        $transaction->allow_commit();

        return $queued;
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
