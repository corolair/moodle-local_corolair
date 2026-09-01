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
    $dbman = $DB->get_manager();
    // Step 1: Remove the "Corolair" menu item if present in custommenuitems.
    if ($oldversion < 2024091600) {
        // Use set_config() rather than a raw update_record() on {config}, so the core
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
    // Bound any inherited token still carrying no expiration. Sites already running 1.9.x with a
    // migration that never confirmed hold a live pre-1.9 credential, and nothing expires it. This
    // is a local field update with no network call and no throw, so unlike the migration itself it
    // is safe to run inline here rather than through local_corolair_queue_legacy_migration().
    if ($oldversion < 2026081400) {
        \local_corolair\local\upgrade_migrator::bound_legacy_token_lifetime();
        upgrade_plugin_savepoint(true, 2026081400, 'local', 'corolair');
    }
    // Authorise the current token owners before core restricts the service to authorised
    // users. Core rewrites the service flags from db/services.php inside
    // external_update_descriptions(), which upgrade_plugins() calls *after* this function
    // returns -- so this step runs while restrictedusers is still 0, and the rows it writes
    // are what keeps the live administrator token working the instant the flag flips.
    if ($oldversion < 2026081700) {
        local_corolair_authorise_existing_token_owners();
        upgrade_plugin_savepoint(true, 2026081700, 'local', 'corolair');
    }
    // Bring a site that rotated under the old seven-day overlap onto the new grace window.
    // Without this the superseded credential on every mid-overlap site stays live for up to a
    // week after the upgrade that was supposed to end exactly that, and the deadline is a
    // plain configuration value, so shortening it is all the revocation path needs.
    if ($oldversion < 2026090101) {
        $revokeby = (int)get_config('local_corolair', 'previouswebservicetokenrevokeby');
        if ($revokeby > 0) {
            // Lowered, never raised. A deadline already in the past belongs to a token the
            // hourly task is about to collect, and pushing it forward would resurrect it.
            set_config(
                'previouswebservicetokenrevokeby',
                min($revokeby, time() + \local_corolair\local\webservice_token_manager::PREVIOUS_TOKEN_GRACE),
                'local_corolair'
            );
            // Queued rather than revoked inline: web services are unavailable during an
            // upgrade, and converge_authorised() rewrites the rows that gate them. The task
            // does only local work, so this respects the no-network-IO rule at the top of
            // this file.
            $task = new \local_corolair\task\revoke_previous_token_task();
            $task->set_next_run_time(time() + \local_corolair\local\webservice_token_manager::PREVIOUS_TOKEN_GRACE);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        upgrade_plugin_savepoint(true, 2026090101, 'local', 'corolair');
    }
    // The plugin's first table. It records which LTI activities this plugin created, which is what
    // now authorises the manage and delete operations -- previously any {lti}.id on the site was a
    // valid target, because the capability those functions check is held at system context and so
    // passes in every course.
    //
    // Deliberately no back-fill. Adopting pre-existing activities would mean guessing which ones
    // were ours, and the guess would have to be permissive enough to be useless as a boundary.
    // Placements created before this upgrade stop being manageable through the API and have to be
    // recreated -- a one-off cost paid only by sites that already had exams placed.
    if ($oldversion < 2026090102) {
        $table = new xmldb_table(\local_corolair\local\placement_registry::TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ltiinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ltiinstanceid', XMLDB_INDEX_UNIQUE, ['ltiinstanceid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026090102, 'local', 'corolair');
    }
    return true;
}

/**
 * Authorise every user who currently owns a token for the Raison service.
 *
 * Unlike every other step in this file, this one is deliberately allowed to throw, and the
 * comment at the top of the file about never blocking a site upgrade does not apply to it.
 * The two outcomes are not symmetric: a failure here followed by a successful flag flip
 * takes the integration offline until an administrator notices, whereas a failed upgrade is
 * visible immediately and can simply be retried. A throw also prevents the flip outright,
 * because upgrade_component_updated() is never reached when this function raises.
 *
 * Note what this does *not* do: it does not create the service account. Two of the
 * capabilities that account needs belong to this plugin, and core has not registered them
 * yet at this point in the upgrade, so assign_capability() would throw. Provisioning happens
 * on the next scheduled run instead, and the handover to it is an ordinary rotation.
 *
 * @return void
 */
function local_corolair_authorise_existing_token_owners(): void {
    global $DB;

    $service = $DB->get_record('external_services', ['shortname' => 'corolair_rest'], 'id');
    if (!$service) {
        // The service is created by core from db/services.php, so its absence means this
        // site has never registered and holds no token to protect.
        return;
    }
    $serviceid = (int)$service->id;

    $owners = $DB->get_fieldset_select(
        'external_tokens',
        'DISTINCT userid',
        'externalserviceid = :serviceid AND tokentype = 0',
        ['serviceid' => $serviceid]
    );
    $consentedby = (int)get_config('local_corolair', 'setupconsentedby');
    if ($consentedby > 0) {
        $owners[] = $consentedby;
    }
    foreach (array_unique(array_map('intval', $owners)) as $userid) {
        \local_corolair\local\service_account_provisioner::ensure_authorised($serviceid, $userid);
    }

    // Seed the owner of the active token. Before this release the owner was implied by
    // setupconsentedby; from here on it is recorded explicitly, and the first post-upgrade
    // maintenance run needs it to find the token it is about to rotate away from.
    $tokenid = (int)get_config('local_corolair', 'webservicetokenid');
    $token = $tokenid > 0 ? $DB->get_record('external_tokens', ['id' => $tokenid], 'id, userid') : false;
    $ownerid = $token ? (int)$token->userid : $consentedby;
    if ($ownerid > 0) {
        set_config('webservicetokenownerid', $ownerid, 'local_corolair');
        // Only meaningful where there is actually an administrator-owned token to move off.
        set_config('serviceaccountmigrationpending', 1, 'local_corolair');
    }
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
