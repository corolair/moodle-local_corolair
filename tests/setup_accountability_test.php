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
 * Tests for the setup accountability record.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\setup_accountability;

/**
 * Verifies the record keeps its facts while the people it names can leave it.
 *
 * Issue #115: the identity of the administrators who set the integration up was kept for as
 * long as the plugin was installed, because the same setting also gated token maintenance
 * and addressed its warnings. These tests pin the rule that replaced that: the identity
 * leaves on erasure or account deletion, the facts stay, and warnings find someone who can
 * act on them.
 */
final class setup_accountability_test extends \advanced_testcase {
    /**
     * Record both setup acts, with fixed times.
     *
     * @param int $consentedby Administrator who consented.
     * @param int $acknowledgedby Administrator who acknowledged the disclosure.
     * @return void
     */
    private function record_setup(int $consentedby, int $acknowledgedby): void {
        set_config('setupconsented', 1, 'local_corolair');
        set_config('setupconsentedby', $consentedby, 'local_corolair');
        set_config('setupconsentedat', 1700000000, 'local_corolair');
        set_config('setupdisclosureversion', 'v-test', 'local_corolair');
        set_config('setupdisclosureacknowledgedby', $acknowledgedby, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', 1700000001, 'local_corolair');
    }

    /**
     * Erasing a user removes only their identity, and leaves the facts and the other actor.
     *
     * @covers \local_corolair\local\setup_accountability::erase
     * @covers \local_corolair\local\setup_accountability::consent_recorded
     * @return void
     */
    public function test_erase_removes_the_identity_and_keeps_the_facts(): void {
        $this->resetAfterTest();

        $consenter = (int)$this->getDataGenerator()->create_user()->id;
        $acknowledger = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup($consenter, $acknowledger);

        setup_accountability::erase($consenter);

        $this->assertFalse(get_config('local_corolair', 'setupconsentedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupconsentedbyerasedat'));
        $this->assertEquals(1700000000, (int)get_config('local_corolair', 'setupconsentedat'));
        $this->assertEquals(1, (int)get_config('local_corolair', 'setupconsented'));
        $this->assertTrue(setup_accountability::consent_recorded());

        $this->assertEquals($acknowledger, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedbyerasedat'));
        $this->assertSame('v-test', get_config('local_corolair', 'setupdisclosureversion'));
    }

    /**
     * One administrator named in both places leaves both.
     *
     * @covers \local_corolair\local\setup_accountability::erase
     * @return void
     */
    public function test_erase_covers_both_acts_by_the_same_administrator(): void {
        $this->resetAfterTest();

        $admin = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup($admin, $admin);

        setup_accountability::erase($admin);

        $this->assertFalse(get_config('local_corolair', 'setupconsentedby'));
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertTrue(setup_accountability::consent_recorded());
        $this->assertTrue(setup_accountability::disclosure_acknowledgement_recorded());
        $this->assertEquals(1700000001, (int)get_config('local_corolair', 'setupdisclosureacknowledgedat'));
    }

    /**
     * Erasing someone the record does not name changes nothing.
     *
     * @covers \local_corolair\local\setup_accountability::erase
     * @return void
     */
    public function test_erase_ignores_a_user_the_record_does_not_name(): void {
        $this->resetAfterTest();

        $admin = (int)$this->getDataGenerator()->create_user()->id;
        $other = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup($admin, $admin);

        setup_accountability::erase($other);

        $this->assertEquals($admin, (int)get_config('local_corolair', 'setupconsentedby'));
        $this->assertFalse(get_config('local_corolair', 'setupconsentedbyerasedat'));
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedbyerasedat'));
    }

    /**
     * A site that never consented does not look as though it did.
     *
     * @covers \local_corolair\local\setup_accountability::consent_recorded
     * @covers \local_corolair\local\setup_accountability::disclosure_acknowledgement_recorded
     * @return void
     */
    public function test_nothing_is_recorded_on_a_fresh_site(): void {
        $this->resetAfterTest();

        unset_config('setupconsentedby', 'local_corolair');
        unset_config('setupdisclosureacknowledgedby', 'local_corolair');

        $this->assertFalse(setup_accountability::consent_recorded());
        $this->assertFalse(setup_accountability::disclosure_acknowledgement_recorded());
    }

    /**
     * Recording a new act names its author and clears the earlier erasure.
     *
     * @covers \local_corolair\local\setup_accountability::record_consent
     * @covers \local_corolair\local\setup_accountability::record_disclosure_acknowledgement
     * @return void
     */
    public function test_recording_again_replaces_an_erased_administrator(): void {
        $this->resetAfterTest();

        $departed = (int)$this->getDataGenerator()->create_user()->id;
        $successor = (int)get_admin()->id;
        $this->record_setup($departed, $departed);
        setup_accountability::erase($departed);

        setup_accountability::record_consent($successor);
        setup_accountability::record_disclosure_acknowledgement($successor);

        $this->assertEquals($successor, (int)get_config('local_corolair', 'setupconsentedby'));
        $this->assertEquals($successor, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertFalse(get_config('local_corolair', 'setupconsentedbyerasedat'));
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedbyerasedat'));
        $this->assertGreaterThan(1700000001, (int)get_config('local_corolair', 'setupconsentedat'));
    }

    /**
     * Deleting an account directly, with no erasure request, also removes it from the record.
     *
     * @covers \local_corolair\local\setup_accountability::user_deleted
     * @return void
     */
    public function test_deleting_the_account_removes_it_from_the_record(): void {
        $this->resetAfterTest();

        $departed = $this->getDataGenerator()->create_user();
        $acknowledger = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup((int)$departed->id, $acknowledger);

        delete_user($departed);

        $this->assertFalse(get_config('local_corolair', 'setupconsentedby'));
        $this->assertGreaterThan(0, (int)get_config('local_corolair', 'setupconsentedbyerasedat'));
        $this->assertEquals($acknowledger, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));
    }

    /**
     * The upgrade sweep removes accounts that are gone, and only those.
     *
     * @covers \local_corolair\local\setup_accountability::erase_deleted_actors
     * @return void
     */
    public function test_erase_deleted_actors_removes_only_missing_accounts(): void {
        global $DB;

        $this->resetAfterTest();

        $departed = (int)$this->getDataGenerator()->create_user()->id;
        $current = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup($departed, $current);
        // As a deletion before the observer existed left it: flagged, and still referenced.
        $DB->set_field('user', 'deleted', 1, ['id' => $departed]);

        setup_accountability::erase_deleted_actors();

        $this->assertFalse(get_config('local_corolair', 'setupconsentedby'));
        $this->assertEquals($current, (int)get_config('local_corolair', 'setupdisclosureacknowledgedby'));

        // A record naming an ID with no account at all is cleared the same way.
        set_config('setupdisclosureacknowledgedby', 987654321, 'local_corolair');
        setup_accountability::erase_deleted_actors();
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertTrue(setup_accountability::disclosure_acknowledgement_recorded());
    }

    /**
     * Erasing everyone keeps the facts and names no one.
     *
     * @covers \local_corolair\local\setup_accountability::erase_all
     * @return void
     */
    public function test_erase_all_names_no_one(): void {
        $this->resetAfterTest();

        $consenter = (int)$this->getDataGenerator()->create_user()->id;
        $acknowledger = (int)$this->getDataGenerator()->create_user()->id;
        $this->record_setup($consenter, $acknowledger);

        setup_accountability::erase_all();

        $this->assertFalse(get_config('local_corolair', 'setupconsentedby'));
        $this->assertFalse(get_config('local_corolair', 'setupdisclosureacknowledgedby'));
        $this->assertTrue(setup_accountability::consent_recorded());
        $this->assertTrue(setup_accountability::disclosure_acknowledgement_recorded());
    }

    /**
     * Warnings go to the administrator who consented while they can still act on them.
     *
     * @covers \local_corolair\local\setup_accountability::alert_recipients
     * @return void
     */
    public function test_the_consenting_administrator_is_warned_while_they_can_act(): void {
        $this->resetAfterTest();

        $admin = (int)get_admin()->id;
        set_config('setupconsentedby', $admin, 'local_corolair');

        $this->assertSame([$admin], $this->recipient_ids());
    }

    /**
     * The ways the consenting administrator can stop being able to act on a warning.
     *
     * @return array[] Data sets of [what happened to them].
     */
    public static function departed_administrator_provider(): array {
        return [
            'erased from the record' => ['erased'],
            'account deleted' => ['deleted'],
            'account suspended' => ['suspended'],
            'no longer an administrator' => ['demoted'],
        ];
    }

    /**
     * Otherwise warnings go to every active site administrator.
     *
     * An erased administrator who is still an administrator is among them -- the warning is
     * about the site, not about them. One whose account is deleted, suspended or demoted is not.
     *
     * @dataProvider departed_administrator_provider
     * @covers \local_corolair\local\setup_accountability::alert_recipients
     * @param string $departure What happened to the consenting administrator.
     * @return void
     */
    public function test_warnings_fall_back_to_the_site_administrators(string $departure): void {
        global $DB;

        $this->resetAfterTest();

        $primary = (int)get_admin()->id;
        $second = (int)$this->getDataGenerator()->create_user()->id;
        $dormant = (int)$this->getDataGenerator()->create_user(['suspended' => 1])->id;
        $departed = (int)$this->getDataGenerator()->create_user()->id;
        $admins = [$primary, $second, $dormant];
        if ($departure !== 'demoted') {
            $admins[] = $departed;
        }
        set_config('siteadmins', implode(',', $admins));
        set_config('setupconsentedby', $departed, 'local_corolair');

        $expected = [$primary, $second];
        switch ($departure) {
            case 'erased':
                setup_accountability::erase($departed);
                $expected[] = $departed;
                break;
            case 'deleted':
                $DB->set_field('user', 'deleted', 1, ['id' => $departed]);
                break;
            case 'suspended':
                $DB->set_field('user', 'suspended', 1, ['id' => $departed]);
                break;
        }
        accesslib_clear_all_caches_for_unit_testing();

        sort($expected);
        $this->assertSame($expected, $this->recipient_ids());
    }

    /**
     * Return the IDs alert_recipients() resolves to, sorted.
     *
     * @return int[]
     */
    private function recipient_ids(): array {
        $ids = array_map(function ($user) {
            return (int)$user->id;
        }, setup_accountability::alert_recipients());
        sort($ids);
        return $ids;
    }
}
