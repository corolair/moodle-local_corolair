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
use local_corolair\local\api_key;
use local_corolair\local\service_account_provisioner;

/**
 * Verifies the locally retained accountability records, and what happens without a key.
 *
 * Almost all of this plugin's personal data lives in Raison rather than Moodle, so the
 * provider is mostly a client of a remote service. What does not need that service is
 * covered here: the setup accountability record the plugin keeps in its own configuration
 * (who consented, who acknowledged the disclosure, and when), and the two ways a site
 * can be without a real API key. A site that never registered has nothing at Raison, so
 * every entry point stops before it makes a request -- sending the translated "no API
 * key" placeholder as a bearer token would turn a subject access request into a failed
 * remote call. A site that registered and then lost its key is the opposite case: Raison
 * still holds its users' data, so every entry point that needs Raison fails visibly
 * instead of reporting that there is nothing to export or erase.
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
     * Put the site in the "registered, then lost its key" state.
     *
     * An administrator emptying the setting stores an empty string, which is the case the
     * assessment describes. The placeholder is what a site that never registered holds.
     *
     * @return void
     */
    private function make_site_registered_without_key(): void {
        api_key::store_issued('org_test.realsecret');
        set_config('apikey', '', 'local_corolair');
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
                'A site without an API key must not contact Raison.'
            );
        }
    }

    /**
     * Assert the callback fails because the key is missing, without contacting Raison.
     *
     * @param callable $callback Provider call to run.
     * @return void
     */
    private function assert_fails_for_missing_key(callable $callback): void {
        $this->assert_makes_no_request(function () use ($callback) {
            try {
                $callback();
                $this->fail('A registered site without its key must not report that Raison holds nothing.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('privacy:apikeymissing', $exception->errorcode);
            }
        });
    }

    /**
     * Call one provider entry point for a user and a context.
     *
     * @param string $method Entry point name.
     * @param \stdClass $user Data subject.
     * @param \context $context Context to scope the call to.
     * @return void
     */
    private function call_entry_point(string $method, \stdClass $user, \context $context): void {
        switch ($method) {
            case 'get_contexts_for_userid':
                provider::get_contexts_for_userid((int)$user->id);
                return;
            case 'export_user_data':
                provider::export_user_data(new approved_contextlist($user, 'local_corolair', [$context->id]));
                return;
            case 'get_users_in_context':
                provider::get_users_in_context(new userlist($context, 'local_corolair'));
                return;
            case 'delete_data_for_user':
                provider::delete_data_for_user(new approved_contextlist($user, 'local_corolair', [$context->id]));
                return;
            case 'delete_data_for_all_users_in_context':
                provider::delete_data_for_all_users_in_context($context);
                return;
            case 'delete_data_for_users':
                provider::delete_data_for_users(new approved_userlist($context, 'local_corolair', [(int)$user->id]));
                return;
        }
        $this->fail("Unknown entry point {$method}.");
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
        // Sent with the viewer's identity on every widget request, so they are part of what
        // leaves the site and belong in the registry an administrator reads.
        $raisonfields = array_keys($types['raison']->get_privacy_fields());
        $this->assertContains('courseid', $raisonfields);
        $this->assertContains('currentpageurl', $raisonfields);
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
                // Declared because during the ownership handover this briefly holds a real
                // administrator's user ID rather than the synthetic service account's.
                'webservicetokenownerid',
                'serviceaccountid',
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

    /**
     * Placeholders the current user's language would not recognise.
     *
     * The setting is seeded with the placeholder in whatever language was active at the
     * time, and earlier releases seeded the "Corolair" wording, so a site can hold either
     * while the request runs in a different language.
     *
     * @return array[] Data sets of [stored placeholder].
     */
    public static function foreign_placeholder_provider(): array {
        return [
            'french placeholder' => ['Aucune Clé API Raison'],
            'spanish placeholder' => ['No hay clave API de Raison'],
            'portuguese placeholder' => ['Nenhuma chave de API do Raison'],
            'legacy placeholder' => ['No Corolair Api Key'],
        ];
    }

    /**
     * A placeholder in another language or an older wording still short-circuits.
     *
     * @dataProvider foreign_placeholder_provider
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::export_user_data
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @covers \local_corolair\privacy\provider::delete_data_for_all_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_users
     * @param string $placeholder Stored placeholder.
     * @return void
     */
    public function test_foreign_placeholder_also_short_circuits(string $placeholder): void {
        $this->resetAfterTest();
        set_config('apikey', $placeholder, 'local_corolair');

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($user, $context) {
            provider::get_contexts_for_userid((int)$user->id);
            provider::export_user_data(new approved_contextlist($user, 'local_corolair', [$context->id]));
            provider::get_users_in_context(new userlist($context, 'local_corolair'));
            provider::delete_data_for_user(new approved_contextlist($user, 'local_corolair', [$context->id]));
            provider::delete_data_for_all_users_in_context($context);
            provider::delete_data_for_users(new approved_userlist($context, 'local_corolair', [(int)$user->id]));
        });
    }

    /**
     * Every entry point that needs Raison, in each context level Raison holds data for.
     *
     * @return array[] Data sets of [entry point, context level].
     */
    public static function remote_entry_point_provider(): array {
        $cases = [];
        $methods = [
            'get_contexts_for_userid',
            'export_user_data',
            'get_users_in_context',
            'delete_data_for_user',
            'delete_data_for_all_users_in_context',
            'delete_data_for_users',
        ];
        foreach ($methods as $method) {
            foreach (['system', 'course'] as $level) {
                $cases["{$method}, {$level} context"] = [$method, $level];
            }
        }
        return $cases;
    }

    /**
     * A registered site that lost its key fails rather than report nothing at Raison.
     *
     * This is assessment finding COR-PRIV-003. Returning nothing here let Moodle complete an
     * export without the Raison part, and mark an erasure done while Raison still held the
     * data. The privacy manager reports a provider exception to the Data Protection Officers,
     * which is what makes the failure visible.
     *
     * @dataProvider remote_entry_point_provider
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::export_user_data
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @covers \local_corolair\privacy\provider::delete_data_for_all_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_users
     * @param string $method Entry point to call.
     * @param string $level Context level: system or course.
     * @return void
     */
    public function test_registered_site_without_key_fails_visibly(string $method, string $level): void {
        $this->resetAfterTest();
        $this->make_site_registered_without_key();

        $user = $this->getDataGenerator()->create_user();
        $context = $level === 'course'
            ? \context_course::instance($this->getDataGenerator()->create_course()->id)
            : \context_system::instance();

        $this->assert_fails_for_missing_key(function () use ($method, $user, $context) {
            $this->call_entry_point($method, $user, $context);
        });
    }

    /**
     * A site that never registered and has an empty key still reports nothing, quietly.
     *
     * The other half of the distinction. Raison holds nothing for such a site, so an empty
     * answer is the true one, and failing here would block every privacy request forever.
     *
     * @dataProvider remote_entry_point_provider
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::export_user_data
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @covers \local_corolair\privacy\provider::delete_data_for_all_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_users
     * @param string $method Entry point to call.
     * @param string $level Context level: system or course.
     * @return void
     */
    public function test_never_registered_site_without_key_reports_nothing(string $method, string $level): void {
        $this->resetAfterTest();
        set_config('apikey', '', 'local_corolair');
        $this->assertFalse(api_key::site_registered());
        writer::reset();

        $user = $this->getDataGenerator()->create_user();
        $context = $level === 'course'
            ? \context_course::instance($this->getDataGenerator()->create_course()->id)
            : \context_system::instance();

        $this->assert_makes_no_request(function () use ($method, $user, $context) {
            $this->call_entry_point($method, $user, $context);
        });

        $this->assertSame([], provider::get_contexts_for_userid((int)$user->id)->get_contextids());
        $userlist = new userlist($context, 'local_corolair');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
        $this->assertFalse(writer::with_context($context)->has_any_data());
    }

    /**
     * Losing the key does not hide the locally retained record from discovery.
     *
     * Discovery returns the system context for the record's owner instead of throwing. A
     * thrown exception would make the privacy manager discard the whole context list, and
     * the record with it. The user listing keeps its users even though it throws, because
     * the manager keeps the list it passed in.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_local_record_is_still_discovered_when_the_key_is_lost(): void {
        $this->resetAfterTest();
        $this->make_site_registered_without_key();

        $consenter = $this->getDataGenerator()->create_user();
        $acknowledger = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$consenter->id, (int)$acknowledger->id);
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($consenter, $context) {
            $contextlist = provider::get_contexts_for_userid((int)$consenter->id);
            $this->assertSame([(int)$context->id], array_map('intval', $contextlist->get_contextids()));
        });

        $userlist = new userlist($context, 'local_corolair');
        $this->assert_fails_for_missing_key(function () use ($userlist) {
            provider::get_users_in_context($userlist);
        });
        $found = array_map('intval', $userlist->get_userids());
        sort($found);
        $expected = [(int)$consenter->id, (int)$acknowledger->id];
        sort($expected);
        $this->assertSame($expected, $found);
    }

    /**
     * Losing the key does not stop the locally retained record from being exported.
     *
     * The local record is written first, then the export fails for the Raison part, so the
     * administrator gets what Moodle holds and the request is still reported as incomplete.
     *
     * @covers \local_corolair\privacy\provider::export_user_data
     * @return void
     */
    public function test_local_record_is_still_exported_when_the_key_is_lost(): void {
        $this->resetAfterTest();
        $this->make_site_registered_without_key();
        writer::reset();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $context = \context_system::instance();

        $this->assert_fails_for_missing_key(function () use ($admin, $context) {
            provider::export_user_data(new approved_contextlist($admin, 'local_corolair', [$context->id]));
        });

        $exported = writer::with_context($context)->get_data(
            [get_string('privacy:setupsubcontext', 'local_corolair')]
        );
        $this->assertNotEmpty($exported);
        $this->assertEquals((int)$admin->id, (int)$exported->setupconsentedby);
    }

    /**
     * Erasure for the record's owner still fails for the Raison part when the key is lost.
     *
     * Discovery returned the system context for this user rather than throwing, so this is
     * where the missing key has to surface. The local record itself is retained, as always.
     *
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_erasing_the_record_owner_still_fails_when_the_key_is_lost(): void {
        $this->resetAfterTest();
        $this->make_site_registered_without_key();

        $admin = $this->getDataGenerator()->create_user();
        $this->record_setup((int)$admin->id);
        $context = \context_system::instance();

        $this->assert_fails_for_missing_key(function () use ($admin, $context) {
            provider::delete_data_for_user(new approved_contextlist($admin, 'local_corolair', [$context->id]));
        });

        $this->assertEquals((int)$admin->id, (int)get_config('local_corolair', 'setupconsentedby'));
    }

    /**
     * Contexts Raison holds nothing for do not fail for want of a key.
     *
     * Context expiry reaches the deletion paths for user, activity and block contexts as well.
     * Raison scopes data to the system and course contexts only, so those other contexts have
     * nothing to report missing.
     *
     * @covers \local_corolair\privacy\provider::get_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_all_users_in_context
     * @covers \local_corolair\privacy\provider::delete_data_for_users
     * @return void
     */
    public function test_contexts_raison_never_holds_do_not_fail_without_a_key(): void {
        $this->resetAfterTest();
        $this->make_site_registered_without_key();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $userlist = new userlist($context, 'local_corolair');

        $this->assert_makes_no_request(function () use ($user, $context, $userlist) {
            provider::get_users_in_context($userlist);
            provider::delete_data_for_all_users_in_context($context);
            provider::delete_data_for_users(new approved_userlist($context, 'local_corolair', [(int)$user->id]));
        });
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * The service account stays out of every data-subject path when the key is lost.
     *
     * It is not a data subject at all, so there is nothing at Raison to report missing.
     *
     * @covers \local_corolair\privacy\provider::get_contexts_for_userid
     * @covers \local_corolair\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_service_account_is_still_skipped_when_the_key_is_lost(): void {
        global $DB;

        $this->resetAfterTest();
        $this->make_site_registered_without_key();

        $account = $DB->get_record('user', ['id' => service_account_provisioner::ensure()], '*', MUST_EXIST);
        $context = \context_system::instance();

        $this->assert_makes_no_request(function () use ($account, $context) {
            $this->assertSame([], provider::get_contexts_for_userid((int)$account->id)->get_contextids());
            provider::delete_data_for_user(new approved_contextlist($account, 'local_corolair', [$context->id]));
        });
    }
}
