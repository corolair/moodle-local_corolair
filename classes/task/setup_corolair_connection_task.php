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
 * Adhoc task for the local_corolair plugin to establish a connection between Moodle and Raison.
 * This task handles the setup process required to initiate and maintain the connection.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Class setup_corolair_connection_task
 *
 * Adhoc task to set up the connection between Moodle and Raison.
 *
 * @package    local_corolair
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup_corolair_connection_task extends \core\task\adhoc_task {
    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $DB, $SITE;

        if (!(bool)get_config('local_corolair', 'setupconsented')) {
            throw new \moodle_exception('setupconsentmissing', 'local_corolair');
        }
        if (empty($CFG->enablewebservices)) {
            throw new \moodle_exception('webservicesenableerror', 'local_corolair');
        }
        if (!\local_corolair\local\setup_manager::rest_enabled()) {
            throw new \moodle_exception('restprotocolenableerror', 'local_corolair');
        }

        $data = $this->get_custom_data();
        if (!isset($data->adminid)) {
            throw new \moodle_exception('invalidrequest', 'error');
        }
        $adminid = (int)$data->adminid;
        if ((int)get_config('local_corolair', 'setupconsentedby') !== $adminid) {
            throw new \moodle_exception('setupconsentmissing', 'local_corolair');
        }
        $admin = $DB->get_record('user', ['id' => $adminid, 'deleted' => 0], '*', MUST_EXIST);
        $context = \context_system::instance();
        if (!has_capability('moodle/site:config', $context, $adminid)) {
            throw new \required_capability_exception($context, 'moodle/site:config', 'nopermissions', '');
        }

        $adminemail = $admin->email;
        $moodlerooturl = $CFG->wwwroot;
        $moodlehost = (string)parse_url($moodlerooturl, PHP_URL_HOST);
        if ($moodlehost === 'localhost' || $moodlehost === '127.0.0.1' || $moodlehost === '::1') {
            throw new \moodle_exception('localhosterror', 'local_corolair');
        }
        $adminfirstname = $admin->firstname;
        $adminlastname = $admin->lastname;
        $sitename = $SITE->fullname;
        $existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if (!$existingservice) {
            throw new \moodle_exception('servicecreationerror', 'local_corolair');
        }
        $serviceid = (int)$existingservice->id;
        $apikey = get_config('local_corolair', 'apikey');
        if (
            !empty($apikey) &&
            strpos($apikey, 'No Corolair Api Key') !== 0 &&
            strpos($apikey, 'Aucune Clé API Corolair') !== 0 &&
            strpos($apikey, 'No hay clave API de Corolair') !== 0 &&
            strpos($apikey, 'No Raison Api Key') !== 0 &&
            strpos($apikey, 'Aucune Clé API Raison') !== 0 &&
            strpos($apikey, 'No hay clave API de Raison') !== 0
        ) {
            if ((int)get_config('local_corolair', 'webservicetokenid') <= 0) {
                $tokens = $DB->get_records(
                    'external_tokens',
                    ['externalserviceid' => $serviceid, 'userid' => $adminid, 'tokentype' => 0],
                    'timecreated DESC',
                    '*',
                    0,
                    1
                );
                $existingtoken = reset($tokens);
                if ($existingtoken) {
                    \local_corolair\local\webservice_token_manager::record_initial_token($existingtoken);
                }
            }
            set_config('setupcompleted', 1, 'local_corolair');
            return;
        }
        $tokens = $DB->get_records(
            'external_tokens',
            ['externalserviceid' => $serviceid, 'userid' => $adminid, 'tokentype' => 0],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $token = reset($tokens);
        if (
            !$token ||
            empty($token->validuntil) ||
            $token->validuntil <= time() ||
            $token->validuntil > time() + \local_corolair\local\webservice_token_manager::TOKEN_LIFETIME
        ) {
            $token = \local_corolair\local\webservice_token_manager::create_token($adminid, $serviceid);
        }
        $curl = new \curl();
        $url = "https://services.raison.is/moodle-integration/plugin/organization/register";
        $postdata = json_encode([
            'url' => $moodlerooturl,
            'webserviceToken' => $token->token,
            'expiresAt' => \local_corolair\local\webservice_token_manager::expiration_iso8601($token),
            'email' => $adminemail,
            'firstname' => $adminfirstname,
            'lastname' => $adminlastname,
            'siteName' => $sitename,
        ]);
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            "CURLOPT_CONNECTTIMEOUT" => 15,
            "CURLOPT_TIMEOUT" => 60,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = \local_corolair\local\audited_request::execute(
            $curl,
            function () use ($curl, $url, $postdata, $options) {
                return $curl->post($url, $postdata, $options);
            },
            \local_corolair\local\audited_request::OP_ORGANIZATION_REGISTER,
            \context_system::instance(),
            (int)$adminid
        );
        $errno = $curl->get_errno();
        $info = $curl->get_info();
        $httpstatus = (int)($info['http_code'] ?? 0);
        if ($response === false || $errno !== 0 || $httpstatus < 200 || $httpstatus >= 300) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        try {
            $jsonresponse = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            debugging('Invalid JSON received while registering Corolair.', DEBUG_DEVELOPER);
            throw new \moodle_exception('apikeymissing', 'local_corolair');
        }
        if (
            !is_array($jsonresponse) ||
            !isset($jsonresponse['apiKey']) ||
            !is_string($jsonresponse['apiKey']) ||
            $jsonresponse['apiKey'] === ''
        ) {
            throw new \moodle_exception('apikeymissing', 'local_corolair');
        }
        set_config('apikey', $jsonresponse['apiKey'], 'local_corolair');
        \local_corolair\local\webservice_token_manager::record_initial_token($token);
        set_config('setupcompleted', 1, 'local_corolair');
    }
}
