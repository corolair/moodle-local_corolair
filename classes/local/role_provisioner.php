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
 * Convergent provisioning of the "Raison Manager" role.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Creates and repairs the plugin role without assuming a clean site.
 *
 * Installation cannot assume it runs exactly once against pristine state: an
 * uninstall that fails partway leaves the role behind, and create_role() then
 * fails against the unique index on role.shortname. Every operation here is
 * therefore convergent -- it drives the role to the intended end state from
 * whatever state it finds, and produces the same result when run repeatedly.
 */
final class role_provisioner {
    /** Shortname of the role owned by this plugin. */
    public const SHORTNAME = 'corolair';

    /** Capabilities the role must hold at system context. Keep in sync with db/access.php. */
    public const CAPABILITIES = [
        'local/corolair:createtutor',
        'local/corolair:viewroles',
        'local/corolair:assignmanagerrole',
    ];

    /** Context levels the role must be assignable at. */
    public const CONTEXTLEVELS = [CONTEXT_SYSTEM, CONTEXT_COURSE];

    /**
     * Ensure the role exists with the expected context levels and capabilities.
     *
     * Safe to call repeatedly, and safe to call over a role left behind by a
     * previous installation.
     *
     * @return int The role ID.
     */
    public static function ensure_role(): int {
        global $DB;

        $context = \context_system::instance();
        $existing = $DB->get_record('role', ['shortname' => self::SHORTNAME], 'id');

        if ($existing) {
            // Reuse the existing role rather than failing. The name and description are
            // left untouched on purpose: an administrator may have renamed the role, and
            // that customisation should survive a repair.
            $roleid = (int)$existing->id;
        } else {
            $roleid = (int)create_role(
                get_string('rolename', 'local_corolair'),
                self::SHORTNAME,
                get_string('roledescription', 'local_corolair')
            );
        }

        // Core deletes the role's existing rows and reinserts the given set, so this
        // converges no matter what was already recorded.
        set_role_contextlevels($roleid, self::CONTEXTLEVELS);

        foreach (self::CAPABILITIES as $capability) {
            self::ensure_capability($roleid, (int)$context->id, $capability);
        }

        // Capability changes are not visible to already-loaded access caches until the
        // context is marked dirty.
        $context->mark_dirty();

        return $roleid;
    }

    /**
     * Remove the role, if it still exists.
     *
     * The teardown counterpart to ensure_role(), and convergent in the same way: a role
     * that is already gone is not an error. Core cannot do this itself, because a role
     * made with create_role() carries nothing that ties it back to this component.
     *
     * delete_role() rather than direct deletes: it also clears role_assignments,
     * role_capabilities, role_names, role_context_levels and both directions of
     * role_allow_assign / role_allow_override, emits a role_deleted event, and
     * invalidates the access caches.
     *
     * @return void
     */
    public static function remove_role(): void {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME], 'id');
        if (!$role) {
            return;
        }
        // The lookup above is required: delete_role() reads the role record to build its
        // event and fatals on a missing role.
        delete_role((int)$role->id);
    }

    /**
     * Grant one capability to the role at the given context, repairing any existing row.
     *
     * Deliberately writes role_capabilities directly instead of calling
     * assign_capability(). Core runs db/install.php *before* update_capabilities()
     * registers this plugin's db/access.php entries -- see the new-installation branch
     * of upgrade_plugins() in lib/upgradelib.php, where upgrade_component_updated() is
     * invoked only after the install script returns. assign_capability() rejects a
     * capability that is not yet in mdl_capabilities, so using it here would throw on
     * every fresh install.
     *
     * Note that phpunit cannot catch that regression on its own: the test site already
     * has the plugin installed, so the capabilities exist. See the dedicated coverage in
     * tests/install_test.php.
     *
     * @param int $roleid Role to grant to.
     * @param int $contextid Context the grant applies at.
     * @param string $capability Capability name.
     * @return void
     */
    private static function ensure_capability(int $roleid, int $contextid, string $capability): void {
        global $DB;

        $existing = $DB->get_record('role_capabilities', [
            'roleid' => $roleid,
            'contextid' => $contextid,
            'capability' => $capability,
        ]);

        if ($existing) {
            if ((int)$existing->permission === CAP_ALLOW) {
                return;
            }
            // Repair a grant that was removed or overridden to prevent/prohibit.
            $existing->permission = CAP_ALLOW;
            $existing->timemodified = time();
            $DB->update_record('role_capabilities', $existing);
            return;
        }

        $DB->insert_record('role_capabilities', (object)[
            'roleid' => $roleid,
            'contextid' => $contextid,
            'capability' => $capability,
            'permission' => CAP_ALLOW,
            'timemodified' => time(),
            'modifierid' => 0,
        ]);
    }
}
