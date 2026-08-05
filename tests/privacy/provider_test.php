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
 * Tests for the local_corolair privacy provider.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_corolair\event\remote_request_completed;

/**
 * Verifies the locally retained accountability records, and the unregistered-site guard.
 *
 * Almost all of this plugin's personal data lives in Raison rather than Moodle, so the
 * provider is mostly a client of a remote service. Two things do not need that service
 * and are covered here: the setup accountability record the plugin keeps in its own
 * configuration (who consented, who acknowledged the disclosure, and when), and the
 * guard that stops every entry point before it makes a request when no real API key is
 * configured. Sending the translated "no API key" placeholder as a bearer token would
 * turn a subject access request on an unregistered site into a failed remote call.
 */
final class provider_test extends \advanced_testcase {
    /**
     * Record a consenting administrator and a disclosure acknowledger.
     *
     * @param int $consentedby Administrator who consented.
     * @param int|null $acknowledgedby Administrator who acknowledged the disclosure.
     * @return void
     */
    private function record_setup(int $consentedby, ?int $acknowledgedby = null): void {
        set_config('setupconsentedby', $consentedby, 'local_corolair');
        set_config('setupconsentedat', 1700000000, 'local_corolair');
        set_config('setupdisclosureacknowledgedby', $acknowledgedby ?? $consentedby, 'local_corolair');
        set_config('setupdisclosureacknowledgedat', 1700000001, 'local_corolair');
    }

    /**
     * Put the site in the "never registered" state.
     *
     * @return void
     */
    private function make_site_unregistered(): void {
        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
    }

    /**
     * Assert no outbound request was audited while running the given callback.
     *
     * @param callable $callback Provider call to run.
     * @return void
     */
    private function assert_makes_no_request(callable $callback): void {
        $sink = $this->redirectEvents();
        $callback();
        $events = $sink->get_events();
        $sink->close();

        foreach ($events as $event) {
            $this->assertNotInstanceOf(
                remote_request_completed::class,
                $event,
                'An unregistered site must not contact Raison.'
            );
        }
    }

    /**
     * The metadata declares both the remote destination and the local record.
     *
     * @covers \local_corolair\privacy\provider::get_metadata
     * @return void
     */
    public function test_metadata_declares_every_destination(): void {
        $collection = provider::get_metadata(new collection('local_corolair'));

        $types = [];
        foreach ($collection->get_collection() as $item) {
            $types[$item->get_name()] = $item;
        }

        $this->assertArrayHasKey('raison', $types, 'The remote service must be declared.');
        $this->assertArrayHasKey(
            'config_plugins',
            $types,
            'The locally retained setup record must be declared.'
        );
        $this->assertSame(
            [
                'setupconsentedby',
                'setupconsentedat',
                'setupdisclosureacknowledgedby',
                'setupdisclosureacknowledgedat',
            ],
            array_keys($types['config_plugins']->get_privacy_fields())
        );
    }

    /**
     * The consenting administrator has a local record, so the system context applies.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_consenting_administrator_has_a_context(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);

        $this->assert_makes_no_request(function () use ($admin) {
            $contextlist = provider::get_contexts_for_userid((int)$admin->id);
            $this->assertSame(
                [(int)\context_system::instance()->id],
                array_map('intval', $contextlist->get_contextids())
            );
        });
    }

    /**
     * The disclosure acknowledger is also covered, even if someone else consented.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_disclosure_acknowledger_has_a_context(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $consenter = $this->getDataGenerator()->create_user();
        $acknowledger = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$consenter->id, (int)$acknowledger->id);

        $contextlist = provider::get_contexts_for_userid((int)$acknowledger->id);

        $this->assertSame(
            [(int)\context_system::instance()->id],
            array_map('intval', $contextlist->get_contextids())
        );
    }

    /**
     * A user with no local record and no remote data has no contexts.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_unrelated_user_has_no_contexts(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $admin = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);

        $this->assert_makes_no_request(function () use ($other) {
            $contextlist = provider::get_contexts_for_userid((int)$other->id);
            $this->assertSame([], $contextlist->get_contextids());
        });
    }

    /**
     * The accountability record is exported in a readable form.
     *
     * @covers \local_corolair\privacy\provider::export_user_data
     * @return void
     */
    public function test_setup_record_is_exported(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();
        writer::reset();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $context = \context_system::instance();

        provider::export_user_data(new approved_contextlist(
            $admin,
            'local_corolair',
            [$context->id]
        ));

        $subcontext = [get_string('privacy:setupsubcontext', 'local_corolair')];
        $exported = writer::with_context($context)->get_data($subcontext);

        $this->assertNotEmpty($exported);
        $this->assertEquals((int)$admin->id, (int)$exported->setupconsentedby);
        $this->assertEquals((int)$admin->id, (int)$exported->setupdisclosureacknowledgedby);
        $this->assertNotEmpty($exported->setupconsentedat);
        $this->assertNotEmpty($exported->setupdisclosureacknowledgedat);
        $this->assertIsString(
            $exported->setupconsentedat,
            'Timestamps must be transformed into readable dates.'
        );
    }

    /**
     * Only the fields belonging to the requesting user are exported.
     *
     * @covers \local_corolair\privacy\provider::export_user_data
     * @return void
     */
    public function test_export_is_scoped_to_the_requesting_user(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();
        writer::reset();

        $consenter = $this->getDataGenerator()->create_user();
        $acknowledger = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$consenter->id, (int)$acknowledger->id);
        $context = \context_system::instance();

        provider::export_user_data(new approved_contextlist(
            $acknowledger,
            'local_corolair',
            [$context->id]
        ));

        $exported = writer::with_context($context)->get_data(
            [get_string('privacy:setupsubcontext', 'local_corolair')]
        );

        $this->assertObjectHasProperty('setupdisclosureacknowledgedby', $exported);
        $this->assertObjectNotHasProperty(
            'setupconsentedby',
            $exported,
            "One administrator's export must not disclose another's record."
        );
    }

    /**
     * A user with no local record exports nothing.
     *
     * @covers \local_corolair\privacy\provider::export_user_data
     * @return void
     */
    public function test_unrelated_user_exports_nothing(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();
        writer::reset();

        $admin = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($other, $context) {
            provider::export_user_data(new approved_contextlist(
                $other,
                'local_corolair',
                [$context->id]
            ));
        });

        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * Both recorded administrators appear in the system-context user list.
     *
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_both_actors_are_listed_at_system_context(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $consenter = $this->getDataGenerator()->create_user();
        $acknowledger = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$consenter->id, (int)$acknowledger->id);

        $userlist = new userlist(\context_system::instance(), 'local_corolair');
        $this->assert_makes_no_request(function () use ($userlist) {
            provider::get_users_in_context($userlist);
        });

        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $expected = [(int)$consenter->id, (int)$acknowledger->id];
        sort($expected);
        $this->assertSame($expected, $found);
    }

    /**
     * One administrator in both roles is listed once.
     *
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_a_single_actor_is_listed_once(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);

        $userlist = new userlist(\context_system::instance(), 'local_corolair');
        provider::get_users_in_context($userlist);

        $this->assertSame([(int)$admin->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * The local record lives at system context and nowhere else.
     *
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_no_actors_are_listed_at_course_context(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $course = $this->getDataGenerator()->create_course();

        $userlist = new userlist(\context_course::instance($course->id), 'local_corolair');
        $this->assert_makes_no_request(function () use ($userlist) {
            provider::get_users_in_context($userlist);
        });

        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Deleting one user's data does not erase the integration's owner record.
     *
     * The consenting administrator's identity is retained under legitimate interest for
     * as long as the plugin is installed; it is the accountability record for the active
     * integration and is removed by uninstalling, not by a subject request.
     *
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_deleting_a_user_keeps_the_accountability_record(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($admin, $context) {
            provider::delete_data_for_user(new approved_contextlist(
                $admin,
                'local_corolair',
                [$context->id]
            ));
        });

        $this->assertEquals((int)$admin->id, (int)get_config('local_corolair', 'setupconsentedby'));
        $this->assertEquals(1700000000, (int)get_config('local_corolair', 'setupconsentedat'));
    }

    /**
     * An unregistered site makes no request when deleting every user in a context.
     *
     * @covers \local_corolair\privacy\provider::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_context_deletion_makes_no_request_when_unregistered(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $course = $this->getDataGenerator()->create_course();

        $this->assert_makes_no_request(function () use ($course) {
            provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
            provider::delete_data_for_all_users_in_context(\context_system::instance());
        });
    }

    /**
     * An unregistered site makes no request when deleting a list of users.
     *
     * @covers \local_corolair\privacy\provider::delete_data_for_users
     * @return void
     */
    public function test_userlist_deletion_makes_no_request_when_unregistered(): void {
        $this->resetAfterTest();
        $this->make_site_unregistered();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($user, $context) {
            provider::delete_data_for_users(new approved_userlist(
                $context,
                'local_corolair',
                [(int)$user->id]
            ));
        });
    }

    /**
     * An entirely unset API key is treated the same as the placeholder.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::export_user_data
     * @return void
     */
    public function test_absent_api_key_also_short_circuits(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($user, $context) {
            provider::get_contexts_for_userid((int)$user->id);
            provider::export_user_data(new approved_contextlist($user, 'local_corolair', [$context->id]));
            provider::get_users_in_context(new userlist($context, 'local_corolair'));
            provider::delete_data_for_user(new approved_contextlist($user, 'local_corolair', [$context->id]));
            provider::delete_data_for_all_users_in_context($context);
        });
    }
}
