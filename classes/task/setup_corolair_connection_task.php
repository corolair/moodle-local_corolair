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
 * Adhoc task for the local_corolair plugin to establish a connection between Moodle and Corolair.
 * This task handles the setup process required to initiate and maintain the connection.
 *
 * @package   local_corolair
 * @copyright 2024 Corolair
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\task;

/**
 * Class setup_corolair_connection_task
 *
 * Adhoc task to set up the connection between Moodle and Corolair.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup_corolair_connection_task extends \core\task\adhoc_task {

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $adminid = $data->adminid;
        $adminemail = $data->adminemail;
        $moodlerooturl = $data->moodlerooturl;
        $adminfirstname = $data->adminfirstname;
        $adminlastname = $data->adminlastname;
        $sitename = $data->sitename;
        $apikey = get_config('local_corolair', 'apikey');
        if (!empty($apikey) &&
            strpos($apikey, 'No Corolair Api Key') !== 0 &&
            strpos($apikey, 'Aucune Clé API Corolair') !== 0 &&
            strpos($apikey, 'No hay clave API de Corolair') !== 0) {
            return;
        }
        $existingservice = $DB->get_record('external_services', ['shortname' => 'corolair_rest']);
        if (!$existingservice) {
            throw new \moodle_exception('servicecreationerror', 'local_corolair');
        }
        $serviceid = $existingservice->id;
        $token = (object)[
            'token' => md5(uniqid(rand(), true)),
            'userid' => $adminid,
            'tokentype' => 0,
            'contextid' => \context_system::instance()->id,
            'creatorid' => $adminid,
            'timecreated' => time(),
            'validuntil' => 0,
            'externalserviceid' => $serviceid,
            'privatetoken' => random_string(64),
            'name' => get_string('tokenname', 'local_corolair'),
        ];
        $insertedtoken = $DB->insert_record('external_tokens', $token);
        if (!$insertedtoken) {
            throw new \moodle_exception('tokencreationerror', 'local_corolair');
        }
        $curl = new \curl();
        $url = "https://services.corolair.com/moodle-integration/plugin/organization/register";
        $postdata = json_encode([
            'url' => $moodlerooturl,
            'webserviceToken' => $token->token,
            'email' => $adminemail,
            'firstname' => $adminfirstname,
            'lastname' => $adminlastname,
            'siteName' => $sitename,
        ]);
        $options = [
            "CURLOPT_RETURNTRANSFER" => true,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata),
            ],
        ];
        $response = $curl->post($url, $postdata, $options);
        $errno = $curl->get_errno();
        if ($response === false || $errno !== 0) {
            throw new \moodle_exception('curlerror', 'local_corolair');
        }
        $jsonresponse = json_decode($response, true);
        if (!isset($jsonresponse['apiKey'])) {
            throw new \moodle_exception('apikeymissing', 'local_corolair');
        }
        set_config('apikey', $jsonresponse['apiKey'], 'local_corolair');
    }
}
