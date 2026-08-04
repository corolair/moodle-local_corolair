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
 * @param int $oldversion The current version of the plugin before the upgrade.
 * @return bool True on success, false on failure.
 * @throws moodle_exception If critical errors occur during the upgrade process.
 */
function xmldb_local_corolair_upgrade($oldversion) {
    global $DB;
    try {
        // Step 1: Remove the "Corolair" menu item if present in custommenuitems.
        if ($oldversion < 2024091600) {
            $custommenuitems = $DB->get_record('config', ['name' => 'custommenuitems']);
            $newmenuitem = "Corolair|/local/corolair/trainer.php";
            if ($custommenuitems && strpos($custommenuitems->value, $newmenuitem) !== false) {
                $custommenuitems->value = str_replace($newmenuitem, '', $custommenuitems->value);
                $DB->update_record('config', $custommenuitems);
            }
            upgrade_plugin_savepoint(true, 2024091600, 'local', 'corolair');
        }
        // Step 2: Notify external Raison service of the update.
        if ($oldversion < 2024100701) {
            $apikey = get_config('local_corolair', 'apikey');
            if (
                empty($apikey) ||
                strpos($apikey, 'No Corolair Api Key') === 0 ||
                strpos($apikey, 'Aucune Clé API Corolair') === 0 ||
                strpos($apikey, 'No hay clave API de Corolair') === 0 ||
                strpos($apikey, 'No Raison Api Key') === 0 ||
                strpos($apikey, 'Aucune Clé API Raison') === 0 ||
                strpos($apikey, 'No hay clave API de Raison') === 0
            ) {
                \core\notification::add(
                    get_string('noapikey', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                \core\notification::add(
                    get_string('calendlydemo', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
            $url = "https://services.corolair.dev/moodle-integration/v2/update";
            $postdata = '{}';
            $curl = new curl();
            $options = [
                "CURLOPT_RETURNTRANSFER" => true,
                "CURLOPT_CONNECTTIMEOUT" => 15,
                "CURLOPT_TIMEOUT" => 60,
                'CURLOPT_HTTPHEADER' => [
                    'Authorization: Bearer ' . $apikey,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postdata),
                ],
            ];
            $response = \local_corolair\local\audited_request::execute(
                $curl,
                function () use ($curl, $url, $postdata, $options) {
                    return $curl->post($url, $postdata, $options);
                },
                \local_corolair\local\audited_request::OP_ORGANIZATION_UPDATE,
                \context_system::instance()
            );
            $errno = $curl->get_errno();
            $info = $curl->get_info();
            $httpstatus = (int)($info['http_code'] ?? 0);
            if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
                debugging('Corolair update request failed with curl error ' . $errno . '.', DEBUG_DEVELOPER);
                \core\notification::add(
                    get_string('curlerror', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                \core\notification::add(
                    get_string('calendlydemo', 'local_corolair'),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
            try {
                $responsedata = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                debugging('Invalid JSON received from the Corolair update endpoint.', DEBUG_DEVELOPER);
                return false;
            }
            if (!is_array($responsedata) || ($responsedata['status'] ?? null) !== 'updated') {
                debugging('Unexpected response received from the Corolair update endpoint.', DEBUG_DEVELOPER);
                return false;
            }
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
            \local_corolair\local\upgrade_migrator::migrate_if_required();
            upgrade_plugin_savepoint(true, 2026080303, 'local', 'corolair');
        }
        // Corrective migration: an active token alone did not prove that the API key exposed by
        // an older plugin version had been replaced. Queue a verifiable replacement unless a
        // completed migration (or fresh registration) has recorded explicit provenance.
        if ($oldversion < 2026080305) {
            \local_corolair\local\upgrade_migrator::migrate_if_required();
            upgrade_plugin_savepoint(true, 2026080305, 'local', 'corolair');
        }
    } catch (moodle_exception $me) {
        debugging($me->getMessage(), DEBUG_DEVELOPER);
        throw $me;
    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        throw new moodle_exception(
            'legacycredentialmigrationfailed',
            'local_corolair',
            '',
            null,
            $e->getMessage()
        );
    }
    return true;
}
