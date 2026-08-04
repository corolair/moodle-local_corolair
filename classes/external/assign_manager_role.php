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
 * Scoped external service for assigning the fixed Raison Manager role.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\external;

defined('MOODLE_INTERNAL') || die();

use context_system;

global $CFG;

if (!class_exists('\\core_external\\external_api') && !class_exists('\\external_api')) {
    require_once($CFG->libdir . '/externallib.php');
}

if (!class_exists('\\core_external\\external_api') && class_exists('\\external_api')) {
    class_alias('\\external_api', '\\core_external\\external_api');
    class_alias('\\external_function_parameters', '\\core_external\\external_function_parameters');
    class_alias('\\external_multiple_structure', '\\core_external\\external_multiple_structure');
    class_alias('\\external_value', '\\core_external\\external_value');
}

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Assigns the plugin-owned manager role without exposing arbitrary role or context selection.
 */
class assign_manager_role extends external_api {
    /**
     * Describe the parameters accepted by the external function.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Moodle user id'),
                'Users who should receive the Raison Manager role'
            ),
        ]);
    }

    /**
     * Assign the fixed Raison Manager role to Moodle users.
     *
     * @param int[] $userids Moodle user ids.
     * @return int[] Assigned user ids.
     */
    public static function execute(array $userids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['userids' => $userids]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/corolair:assignmanagerrole', $context);

        $userids = array_values(array_unique(array_map('intval', $params['userids'])));
        if (empty($userids)) {
            throw new \invalid_parameter_exception('At least one Moodle user id is required.');
        }

        $role = $DB->get_record('role', ['shortname' => 'corolair'], 'id', MUST_EXIST);
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id, deleted');
        foreach ($userids as $userid) {
            if (!isset($users[$userid]) || !empty($users[$userid]->deleted)) {
                throw new \invalid_parameter_exception('A target Moodle user does not exist or is deleted.');
            }
        }

        $transaction = $DB->start_delegated_transaction();
        foreach ($userids as $userid) {
            role_assign((int)$role->id, $userid, $context->id, '', 0);
        }
        $transaction->allow_commit();

        return $userids;
    }

    /**
     * Describe the value returned by the external function.
     *
     * @return external_multiple_structure Return-value definition.
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_value(PARAM_INT, 'User id assigned the Raison Manager role')
        );
    }
}
