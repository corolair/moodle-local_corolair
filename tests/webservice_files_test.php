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
 * Tests for the service's file-transfer and authorised-user boundary.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\service_account_provisioner;

/**
 * Covers the parts of the access boundary that the function allow-list does not reach.
 *
 * Moodle's two file endpoints are gated only by the service's own flags and by
 * authenticate_user(). Neither consults external_services_functions at all, so the entire
 * allow-list -- and every drift test guarding it -- is irrelevant to them. A token that
 * cannot call a single web-service function can still download every file on the site if
 * downloadfiles is set, and can still write into its owner's draft area if uploadfiles is.
 * The three service flags are therefore a distinct part of the disclosed access boundary,
 * and this class is what holds them in place.
 *
 * The honest limit: a genuine end-to-end test would drive webservice_server over HTTP, which
 * this suite has no scaffolding for. What follows asserts the state core's checks consume,
 * not the checks themselves.
 */
final class webservice_files_test extends \advanced_testcase {
    /**
     * Return the shipped service record.
     *
     * @return \stdClass
     */
    private function service(): \stdClass {
        global $DB;

        return $DB->get_record('external_services', ['shortname' => 'corolair_rest'], '*', MUST_EXIST);
    }

    /**
     * File upload is disabled on the installed service.
     *
     * webservice/upload.php checks this flag and nothing else -- no capability, no function
     * grant -- and writes into the token owner's draft file area. Nothing in the plugin or
     * in Raison uploads to Moodle, so leaving it enabled would be pure unused surface.
     *
     * @coversNothing
     * @return void
     */
    public function test_file_upload_is_disabled(): void {
        $this->resetAfterTest();

        $this->assertSame(0, (int)$this->service()->uploadfiles);
    }

    /**
     * File download is enabled on the installed service.
     *
     * Asserted as deliberately as the disabling above. Raison rewrites every Moodle file URL
     * through webservice/pluginfile.php with the token appended, so turning this off would
     * silently break course resources, SCORM packages and user pictures.
     *
     * @coversNothing
     * @return void
     */
    public function test_file_download_is_enabled(): void {
        $this->resetAfterTest();

        $this->assertSame(1, (int)$this->service()->downloadfiles);
    }

    /**
     * The service accepts only explicitly authorised users.
     *
     * @coversNothing
     * @return void
     */
    public function test_service_is_restricted_to_authorised_users(): void {
        $this->resetAfterTest();

        $this->assertSame(1, (int)$this->service()->restrictedusers);
    }

    /**
     * Only the service account is authorised once provisioning has converged.
     *
     * This is what a token belonging to any other user runs into, on both the function-call
     * path and the file endpoints.
     *
     * @covers \local_corolair\local\service_account_provisioner::converge_authorised
     * @return void
     */
    public function test_only_the_service_account_is_authorised(): void {
        global $DB;

        $this->resetAfterTest();

        $serviceid = (int)$this->service()->id;
        $stranger = (int)$this->getDataGenerator()->create_user()->id;
        service_account_provisioner::ensure_authorised($serviceid, $stranger);

        $userid = service_account_provisioner::ensure();
        service_account_provisioner::converge_authorised($serviceid, [$userid]);

        $authorised = $DB->get_fieldset_select(
            'external_services_users',
            'userid',
            'externalserviceid = ?',
            [$serviceid]
        );

        $this->assertSame([$userid], array_map('intval', $authorised));
    }

    /**
     * A site administrator is not authorised merely by being an administrator.
     *
     * The whole point of the restriction: any token for this service used to work, and
     * the token that existed belonged to an administrator. Administrator status now buys
     * nothing here.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure
     * @return void
     */
    public function test_a_site_administrator_is_not_authorised_by_default(): void {
        global $DB;

        $this->resetAfterTest();

        $serviceid = (int)$this->service()->id;
        service_account_provisioner::ensure();

        $admin = get_admin();
        $this->assertFalse(
            $DB->record_exists('external_services_users', [
                'externalserviceid' => $serviceid,
                'userid' => (int)$admin->id,
            ]),
            'Provisioning must not authorise anyone but the service account.'
        );
    }

    /**
     * The service account holds no capability that would let it read a private file area.
     *
     * Download authorisation is delegated to each component's pluginfile callback, evaluated
     * as the token owner. So the read set has to be checked from the other direction too:
     * the account can see course content, and must not be able to see a user's private
     * files or another user's draft area.
     *
     * @covers \local_corolair\local\service_account_provisioner::ensure_capabilities
     * @return void
     */
    public function test_service_account_cannot_reach_private_file_areas(): void {
        $this->resetAfterTest();

        $userid = service_account_provisioner::ensure();
        $other = $this->getDataGenerator()->create_user();
        accesslib_clear_all_caches_for_unit_testing();

        $usercontext = \context_user::instance((int)$other->id);
        $this->assertFalse(has_capability('moodle/user:editprofile', $usercontext, $userid));
        $this->assertFalse(has_capability('moodle/user:update', $usercontext, $userid));
        $this->assertFalse(has_capability('moodle/user:loginas', \context_system::instance(), $userid));
    }
}
