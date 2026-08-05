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
 * Upgrade script for local_corolair plugin.
 * @package   local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Executes the upgrade steps for the local_corolair plugin.
 *
 * No step performs network I/O. Raison has to call back into Moodle to verify a
 * credential, and web services are unavailable while an upgrade is running, so
 * anything needing the network is queued as an ad-hoc task instead -- see
 * {@see \local_corolair\local\upgrade_migrator}. Nor does any step return false:
 * that raises upgrade_exception without advancing the savepoint, which used to let
 * an unreachable third party block the upgrade of the entire site.
 *
 * @param int $oldversion The current version of the plugin before the upgrade.
 * @return bool Always true; failures surface as exceptions carrying their own message.
 */
function xmldb_local_corolair_upgrade($oldversion) {
    global $DB;
    // Step 1: Remove the "Corolair" menu item if present in custommenuitems.
    if ($oldversion < 2024091600) {
        // set_config() rather than a raw update_record() on {config}, so the core
        // configuration cache is purged along with the stored value.
        $custommenuitems = (string)get_config('core', 'custommenuitems');
        $menuitem = 'Corolair|/local/corolair/trainer.php';
        if ($custommenuitems !== '' && strpos($custommenuitems, $menuitem) !== false) {
            set_config('custommenuitems', str_replace($menuitem, '', $custommenuitems));
        }
        upgrade_plugin_savepoint(true, 2024091600, 'local', 'corolair');
    }
    // Step 2: Retired. This step used to POST to the Raison "update" endpoint from
    // inside the upgrade request. It sent an empty body, so it carried no version or
    // payload the backend could act on, and any site old enough to reach this step is
    // re-registered by the credential migration further down. More importantly it
    // returned false on any failure without advancing the savepoint, which raises
    // upgrade_exception -- an unreachable endpoint, or simply no API key, blocked the
    // whole site upgrade. Network work belongs in an ad-hoc task, never here: Raison
    // has to call back into Moodle, and web services are unavailable mid-upgrade.
    if ($oldversion < 2024100701) {
        upgrade_plugin_savepoint(true, 2024100701, 'local', 'corolair');
    }
    // Step 3: Add required capabilities to the external "Corolair REST" service.
    if ($oldversion < 2024101100) {
        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $capabilities = [
                'core_course_get_categories',
                'core_enrol_get_enrolled_users_with_capability',
            ];
            foreach ($capabilities as $capability) {
                $existing = $DB->get_record('external_services_functions', [
                    'externalserviceid' => $service->id,
                    'functionname' => $capability,
                ]);
                if (!$existing) {
                    $function = new stdClass();
                    $function->externalserviceid = $service->id;
                    $function->functionname = $capability;
                    $DB->insert_record('external_services_functions', $function);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2024101100, 'local', 'corolair');
    }
    // Step 4: Drop the unused local copy of the administrator email (COR-PRIV-004).
    if ($oldversion < 2026080300) {
        unset_config('corolairlogin', 'local_corolair');
        upgrade_plugin_savepoint(true, 2026080300, 'local', 'corolair');
    }
    // Replace broad arbitrary-role assignment with the plugin-scoped manager-role function.
    if ($oldversion < 2026080302) {
        $context = \context_system::instance();
        $role = $DB->get_record('role', ['shortname' => 'corolair'], 'id');
        if ($role) {
            $capabilityrecord = $DB->get_record('role_capabilities', [
                'roleid' => (int)$role->id,
                'contextid' => $context->id,
                'capability' => 'local/corolair:assignmanagerrole',
            ]);
            if ($capabilityrecord) {
                $capabilityrecord->permission = CAP_ALLOW;
                $capabilityrecord->timemodified = time();
                $DB->update_record('role_capabilities', $capabilityrecord);
            } else {
                $DB->insert_record('role_capabilities', (object)[
                    'roleid' => (int)$role->id,
                    'contextid' => $context->id,
                    'capability' => 'local/corolair:assignmanagerrole',
                    'permission' => CAP_ALLOW,
                    'timemodified' => time(),
                ]);
            }
        }

        $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if ($service) {
            $DB->delete_records('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'core_role_assign_roles',
            ]);
            if (
                !$DB->record_exists('external_services_functions', [
                    'externalserviceid' => $service->id,
                    'functionname' => 'local_corolair_assign_manager_role',
                ])
            ) {
                $DB->insert_record('external_services_functions', (object)[
                    'externalserviceid' => $service->id,
                    'functionname' => 'local_corolair_assign_manager_role',
                ]);
            }
        }
        upgrade_plugin_savepoint(true, 2026080302, 'local', 'corolair');
    }
    // Queue post-upgrade invalidation of credentials inherited from the pre-1.9 lifecycle.
    // Raison must call back into Moodle to verify the replacement token, which cannot be
    // guaranteed while Moodle is still running this upgrade request.
    if ($oldversion < 2026080303) {
        local_corolair_queue_legacy_migration();
        upgrade_plugin_savepoint(true, 2026080303, 'local', 'corolair');
    }
    // Corrective migration: an active token alone did not prove that the API key exposed by
    // an older plugin version had been replaced. Queue a verifiable replacement unless a
    // completed migration (or fresh registration) has recorded explicit provenance.
    if ($oldversion < 2026080305) {
        local_corolair_queue_legacy_migration();
        upgrade_plugin_savepoint(true, 2026080305, 'local', 'corolair');
    }
    // Repair installations whose role was never provisioned, or was provisioned before
    // the install script became convergent. ensure_role() is safe to run repeatedly.
    if ($oldversion < 2026080307) {
        \local_corolair\local\role_provisioner::ensure_role();
        upgrade_plugin_savepoint(true, 2026080307, 'local', 'corolair');
    }
    return true;
}

/**
 * Queue the legacy-credential migration without letting it block the upgrade.
 *
 * migrate_if_required() only does local work, but it can still fail -- most often
 * because no usable site administrator owns the integration. Propagating that would
 * abort the upgrade for the entire site, core and every other plugin included, and it
 * would not protect the inherited credentials it is worried about. Record the problem
 * instead so settings.php can surface it and the hourly task can retry.
 *
 * @return void
 */
function local_corolair_queue_legacy_migration(): void {
    try {
        \local_corolair\local\upgrade_migrator::migrate_if_required();
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        set_config('legacycredentialmigrationblocked', 1, 'local_corolair');
        \core\notification::add(
            get_string('legacycredentialmigrationdeferred', 'local_corolair'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}
