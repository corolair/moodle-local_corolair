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
 * External service: get roles (all / by id / by shortname).
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

defined('MOODLE_INTERNAL') || die();

use context_system;

global $CFG;

// Ensure externals are available on 4.0.x paths that haven't loaded them yet.
if (!class_exists('\\core_external\\external_api') && !class_exists('\\external_api')) {
    require_once($CFG->libdir . '/externallib.php');
}

// If we're on 4.0.x (globals), alias them into core_external so imports below work uniformly.
if (!class_exists('\\core_external\\external_api') && class_exists('\\external_api')) {
    class_alias('\\external_api', '\\core_external\\external_api');
    class_alias('\\external_function_parameters', '\\core_external\\external_function_parameters');
    class_alias('\\external_multiple_structure', '\\core_external\\external_multiple_structure');
    class_alias('\\external_single_structure', '\\core_external\\external_single_structure');
    class_alias('\\external_value', '\\core_external\\external_value');
}

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function wrapper that exposes role records via web services.
 *
 * Supports fetching by id, by shortname, or returning all roles, mirroring
 * Moodle's default role table schema.
 * 
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class get_roles extends external_api {

    /**
     * Describe parameters for execute().
     *
     * Both params are optional:
     * - if id is set -> get role by id
     * - else if shortname is set -> get role by shortname
     * - if both are null -> return all roles
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(
                PARAM_INT,
                'Role id',
                VALUE_DEFAULT,
                null
            ),
            'shortname' => new external_value(
                PARAM_ALPHANUMEXT,
                'Role shortname',
                VALUE_DEFAULT,
                null
            ),
        ]);
    }

    /**
     * Get roles.
     *
     * @param int|null $id Role id (optional).
     * @param string|null $shortname Role shortname (optional).
     * @return array
     */
    public static function execute($id = null, $shortname = null): array {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'shortname' => $shortname,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        // Adjust capability if you want to restrict this.
        // require_capability('moodle/role:manage', $context);

        $fields = 'id, name, shortname, description, sortorder, archetype';
        $roles = [];

        if (!is_null($params['id'])) {
            // Get role by id.
            $role = $DB->get_record('role', ['id' => $params['id']], $fields, MUST_EXIST);
            $roles[] = $role;

        } else if (!is_null($params['shortname'])) {
            // Get role by shortname.
            $role = $DB->get_record('role', ['shortname' => $params['shortname']], $fields, MUST_EXIST);
            $roles[] = $role;

        } else {
            // No filter -> return all roles.
            $roles = $DB->get_records('role', null, 'sortorder ASC', $fields);
        }

        $result = [];
        foreach ($roles as $role) {
            $result[] = [
                'id' => (int)$role->id,
                'name' => $role->name,
                'shortname' => $role->shortname,
                'description' => (string)$role->description,
                'sortorder' => (int)$role->sortorder,
                'archetype' => (string)$role->archetype,
            ];
        }

        return $result;
    }

    /**
     * Describe return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Role id'),
                'name' => new external_value(PARAM_TEXT, 'Role name', VALUE_DEFAULT, ''),
                'shortname' => new external_value(PARAM_TEXT, 'Role shortname', VALUE_DEFAULT, ''),
                'description' => new external_value(PARAM_RAW, 'Role description', VALUE_DEFAULT, ''),
                'sortorder' => new external_value(PARAM_INT, 'Role sort order', VALUE_DEFAULT, 0),
                'archetype' => new external_value(PARAM_TEXT, 'Role archetype', VALUE_DEFAULT, ''),
            ])
        );
    }
}
