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
 * Tests for the plugin's declarative definition files.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\external\get_integration_status;
use local_corolair\local\integration_disclosure;
use local_corolair\local\role_provisioner;
use local_corolair\local\service_account_provisioner;

/**
 * Verifies the db/ and lang/ declarations stay consistent with the code that uses them.
 *
 * Nothing in this file tests behaviour. It tests the cross-references between files that
 * no single change ever touches together: a capability added to db/access.php but not to
 * the provisioner is never granted, a service function renamed in one place but not the
 * other silently disappears from the allow-list, and a language key added only to English
 * degrades to a raw identifier for everyone else. All of these survive a normal test run
 * and a manual smoke test.
 */
final class plugin_definition_test extends \advanced_testcase {
    /**
     * Load a plugin definition file and return the variables it declares.
     *
     * @param string $relative Path relative to the plugin root.
     * @param string[] $variables Variable names the file is expected to declare.
     * @return array Declared values, keyed by variable name.
     */
    private function load_definition(string $relative, array $variables): array {
        global $CFG;

        $declared = [];
        foreach ($variables as $name) {
            ${$name} = [];
        }
        require($CFG->dirroot . '/local/corolair/' . $relative);
        foreach ($variables as $name) {
            $declared[$name] = ${$name};
        }
        return $declared;
    }

    /**
     * Return the language strings declared for one language.
     *
     * @param string $language Language directory name.
     * @return array
     */
    private function load_strings(string $language): array {
        return $this->load_definition('lang/' . $language . '/local_corolair.php', ['string'])['string'];
    }

    /**
     * Return the function allow-list of the shipped external service.
     *
     * @return string[]
     */
    private function service_functions(): array {
        $services = $this->load_definition('db/services.php', ['functions', 'services'])['services'];
        foreach ($services as $service) {
            if (($service['shortname'] ?? '') === 'corolair_rest') {
                return $service['functions'];
            }
        }
        $this->fail('db/services.php no longer defines the corolair_rest service.');
    }

    /**
     * The provisioned role grants exactly the capabilities the plugin declares.
     *
     * A capability in db/access.php that the provisioner does not grant exists but is
     * never held by anyone, which looks like a broken permission rather than a missing
     * line of code.
     *
     * @covers \local_corolair\local\role_provisioner::CAPABILITIES
     * @return void
     */
    public function test_declared_capabilities_are_all_provisioned(): void {
        $declared = array_keys($this->load_definition('db/access.php', ['capabilities'])['capabilities']);
        $provisioned = role_provisioner::CAPABILITIES;

        sort($declared);
        sort($provisioned);

        $this->assertSame(
            $declared,
            $provisioned,
            'db/access.php and role_provisioner::CAPABILITIES have drifted.'
        );
    }

    /**
     * Every declared capability is registered on the site.
     *
     * @covers \local_corolair\local\role_provisioner::CAPABILITIES
     * @return void
     */
    public function test_declared_capabilities_are_registered(): void {
        global $DB;

        foreach (role_provisioner::CAPABILITIES as $capability) {
            $this->assertTrue(
                $DB->record_exists('capabilities', ['name' => $capability, 'component' => 'local_corolair']),
                "{$capability} is declared but not registered on the site."
            );
        }
    }

    /**
     * Every external function the plugin declares is implemented and callable.
     *
     * @covers \local_corolair\external\get_roles
     * @return void
     */
    public function test_declared_external_functions_are_implemented(): void {
        $functions = $this->load_definition('db/services.php', ['functions', 'services'])['functions'];

        $this->assertNotEmpty($functions);
        foreach ($functions as $name => $definition) {
            $classname = $definition['classname'];
            $methodname = $definition['methodname'];

            $this->assertTrue(class_exists($classname), "{$name} points at a missing class {$classname}.");
            $this->assertTrue(
                method_exists($classname, $methodname),
                "{$name} points at a missing method {$classname}::{$methodname}."
            );
            $this->assertTrue(
                method_exists($classname, $methodname . '_parameters'),
                "{$name} has no parameter definition."
            );
            $this->assertTrue(
                method_exists($classname, $methodname . '_returns'),
                "{$name} has no return definition."
            );
        }
    }

    /**
     * Every function the plugin implements is actually exposed by the service.
     *
     * An implemented but unlisted function is dead code; the token cannot reach it.
     *
     * @covers \local_corolair\external\get_roles
     * @return void
     */
    public function test_declared_external_functions_are_exposed(): void {
        $functions = array_keys(
            $this->load_definition('db/services.php', ['functions', 'services'])['functions']
        );

        foreach ($functions as $name) {
            $this->assertContains(
                $name,
                $this->service_functions(),
                "{$name} is implemented but not granted to the corolair_rest service."
            );
        }
    }

    /**
     * Every function granted to the service exists on the site.
     *
     * A typo, or a core function removed in a later Moodle release, would otherwise only
     * surface as a failed call from Raison.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_names
     * @return void
     */
    public function test_every_granted_function_exists(): void {
        global $DB;

        foreach ($this->service_functions() as $name) {
            $this->assertTrue(
                $DB->record_exists('external_functions', ['name' => $name]),
                "{$name} is granted to the service but is not a registered external function."
            );
        }
    }

    /**
     * The service is installed with exactly the granted functions.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_names
     * @return void
     */
    public function test_installed_service_matches_the_declaration(): void {
        global $DB;

        $serviceid = (int)$DB->get_field('external_services', 'id', ['shortname' => 'corolair_rest'], MUST_EXIST);
        $installed = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$serviceid]
        );
        $declared = $this->service_functions();

        sort($installed);
        sort($declared);

        $this->assertSame(
            $declared,
            $installed,
            'The installed service functions differ from db/services.php.'
        );
    }

    /**
     * The declared and installed service flags both match the intended access boundary.
     *
     * Both sides are asserted because they answer different questions: the declaration is
     * the intent a reviewer reads, and the installed row is what core actually applied. The
     * three flags are the whole of the file and authorised-user boundary -- neither
     * webservice/pluginfile.php nor webservice/upload.php consults the function allow-list
     * at all, so a flag flipped by hand or lost in an upgrade widens the integration in a
     * way that no other test in this suite would notice.
     *
     * @coversNothing
     * @return void
     */
    public function test_installed_service_flags_match_the_declaration(): void {
        global $DB;

        $services = $this->load_definition('db/services.php', ['functions', 'services'])['services'];
        $declared = null;
        foreach ($services as $service) {
            if (($service['shortname'] ?? '') === 'corolair_rest') {
                $declared = $service;
            }
        }
        $this->assertNotNull($declared, 'db/services.php no longer defines the corolair_rest service.');

        $expected = ['restrictedusers' => 1, 'uploadfiles' => 0, 'downloadfiles' => 1, 'enabled' => 1];
        $installed = $DB->get_record('external_services', ['shortname' => 'corolair_rest'], '*', MUST_EXIST);

        foreach ($expected as $flag => $value) {
            $this->assertArrayHasKey(
                $flag,
                $declared,
                "db/services.php must state {$flag} explicitly; core's default for an omitted key is not ours."
            );
            $this->assertSame($value, (int)$declared[$flag], "db/services.php declares an unexpected {$flag}.");
            $this->assertSame($value, (int)$installed->{$flag}, "The installed service has an unexpected {$flag}.");
        }
    }

    /**
     * The service array key is unchanged.
     *
     * Core matches an installed service against db/services.php on the array key, not the
     * shortname. Renaming the key takes the deleted-service branch of
     * external_update_descriptions(), which drops the service row and with it every token,
     * function grant and authorised user -- silently, during an ordinary upgrade.
     *
     * @coversNothing
     * @return void
     */
    public function test_service_name_key_is_pinned(): void {
        $services = $this->load_definition('db/services.php', ['functions', 'services'])['services'];

        $this->assertSame(
            ['Corolair REST Service'],
            array_keys($services),
            'Renaming this key makes core delete the service, its tokens and its authorised users.'
        );
    }

    /**
     * Every capability the service role grants is a real capability on this site.
     *
     * assign_capability() throws a coding_exception for an unknown capability, and the
     * provisioner filters those out at run time so that an uninstalled optional module
     * cannot break the scheduled task. That filter also hides a typo, and would have hidden
     * mod/scorm:view -- a capability that reads as though it must exist but does not. This
     * test is what makes the filter safe.
     *
     * @coversNothing
     * @return void
     */
    public function test_service_role_capabilities_are_registered(): void {
        global $DB;

        $capabilities = array_merge(
            service_account_provisioner::READ_CAPABILITIES,
            service_account_provisioner::WRITE_CAPABILITIES
        );
        $this->assertNotEmpty($capabilities);

        foreach ($capabilities as $capability) {
            $this->assertTrue(
                $DB->record_exists('capabilities', ['name' => $capability]),
                "{$capability} is granted to the service role but is not a registered capability."
            );
        }
    }

    /**
     * A capability is never both granted and revoked.
     *
     * ensure_capabilities() applies the revocations after the grants, so an overlap would
     * silently withdraw a capability the integration needs -- the failure would surface as
     * accessexception from Raison, far from the list that caused it.
     *
     * @coversNothing
     * @return void
     */
    public function test_revoked_capabilities_are_not_also_granted(): void {
        $granted = array_merge(
            service_account_provisioner::READ_CAPABILITIES,
            service_account_provisioner::WRITE_CAPABILITIES
        );

        $overlap = array_intersect($granted, service_account_provisioner::REVOKED_CAPABILITIES);

        $this->assertSame(
            [],
            array_values($overlap),
            'A capability is listed as both granted and revoked; ensure_capabilities() would revoke it.'
        );
    }

    /**
     * Every revoked capability is a real one.
     *
     * unassign_capability() does not validate the name, so a typo here revokes nothing at all
     * and does it silently -- the opposite of assign_capability(), which throws. This is the
     * only thing that catches it.
     *
     * @coversNothing
     * @return void
     */
    public function test_revoked_capabilities_are_registered(): void {
        global $DB;

        foreach (service_account_provisioner::REVOKED_CAPABILITIES as $capability) {
            $this->assertTrue(
                $DB->record_exists('capabilities', ['name' => $capability]),
                "{$capability} is listed for revocation but is not a registered capability."
            );
        }
    }

    /**
     * Every capability the status function evaluates is one the service role actually holds.
     *
     * These are two lists in two files that no single change ever touches together, and the
     * failure is silent in the worst direction: a capability evaluated but never granted
     * makes the function report privileged=false on a correctly provisioned site, and the
     * content sync responds by refusing to archive anything, indefinitely, with no error.
     *
     * @coversNothing
     * @return void
     */
    public function test_visibility_capabilities_are_all_provisioned(): void {
        foreach (get_integration_status::VISIBILITY_CAPABILITIES as $capability) {
            $this->assertContains(
                $capability,
                service_account_provisioner::READ_CAPABILITIES,
                "{$capability} is evaluated by the status function but never granted to the service role."
            );
        }
    }

    /**
     * A site that can answer the status function can also perform a scoped exam deletion.
     *
     * Raison infers exactly this: any site answering the status call is new enough to expose
     * the scoped delete, so it skips a second round trip. That inference is only safe while
     * the two functions ship together, and if it ever stopped being true the consequence is
     * a fall back to core_course_delete_modules, which can remove any course module rather
     * than only this plugin's own placements.
     *
     * @coversNothing
     * @return void
     */
    public function test_the_status_function_implies_the_scoped_delete(): void {
        $granted = $this->service_functions();

        $this->assertContains('local_corolair_get_integration_status', $granted);
        $this->assertContains(
            'local_corolair_delete_exam_placement',
            $granted,
            'Raison treats the status function as proof the scoped delete exists.'
        );
    }

    /**
     * No capability appears in both the always-granted and the opt-in set.
     *
     * An overlap would make disabling exam placement revoke something the read path needs,
     * because ensure_capabilities() applies the sets in that order.
     *
     * @coversNothing
     * @return void
     */
    public function test_read_and_write_capability_sets_are_disjoint(): void {
        $this->assertSame(
            [],
            array_values(array_intersect(
                service_account_provisioner::READ_CAPABILITIES,
                service_account_provisioner::WRITE_CAPABILITIES
            )),
            'A capability in both sets would be revoked when exam placement is switched off.'
        );
    }

    /**
     * The service account holds no capability that lets it administer the site.
     *
     * Written as a deny-list rather than by comparing against the granted set, so that it
     * keeps failing for the right reason if the granted set grows.
     *
     * @coversNothing
     * @return void
     */
    public function test_service_role_grants_no_administrative_capability(): void {
        $forbidden = [
            'moodle/site:config',
            'moodle/site:uploadusers',
            'moodle/user:create',
            'moodle/user:delete',
            'moodle/user:update',
            'moodle/role:assign',
            'moodle/role:manage',
            'moodle/role:override',
            'moodle/course:create',
            'moodle/course:delete',
            'moodle/course:update',
            'moodle/webservice:createtoken',
            'moodle/webservice:managealltokens',
        ];
        $granted = array_merge(
            service_account_provisioner::READ_CAPABILITIES,
            service_account_provisioner::WRITE_CAPABILITIES
        );

        foreach ($forbidden as $capability) {
            $this->assertNotContains(
                $capability,
                $granted,
                "{$capability} must never be granted to the Raison service account."
            );
        }
    }

    /**
     * The opt-in set covers exactly what the exam-placement functions declare they need.
     *
     * db/services.php declares the capabilities per function for the "missing capabilities"
     * admin report; the provisioner is what actually grants them. If the two disagree, the
     * report tells administrators something the site does not do.
     *
     * @coversNothing
     * @return void
     */
    public function test_write_set_covers_what_the_exam_functions_declare(): void {
        $functions = $this->load_definition('db/services.php', ['functions', 'services'])['functions'];

        $declared = [];
        foreach ($functions as $name => $definition) {
            if (strpos($name, '_exam_placement') === false) {
                continue;
            }
            foreach (explode(',', (string)($definition['capabilities'] ?? '')) as $capability) {
                $capability = trim($capability);
                if ($capability !== '') {
                    $declared[$capability] = true;
                }
            }
        }
        $this->assertNotEmpty($declared, 'The exam-placement functions declare no capabilities.');

        foreach (array_keys($declared) as $capability) {
            $this->assertContains(
                $capability,
                service_account_provisioner::WRITE_CAPABILITIES,
                "{$capability} is declared by an exam-placement function but is never granted."
            );
        }
    }

    /**
     * Every capability the service role grants is disclosed, and nothing else is.
     *
     * The disclosed table used to be hand-written and had drifted in both directions. It is
     * now generated from the same constants, and this is what stops it drifting again.
     *
     * @covers \local_corolair\local\integration_disclosure::get_capability_names
     * @return void
     */
    public function test_disclosure_matches_the_granted_capabilities(): void {
        $granted = array_merge(
            service_account_provisioner::READ_CAPABILITIES,
            service_account_provisioner::WRITE_CAPABILITIES
        );
        $disclosed = integration_disclosure::get_capability_names();

        sort($granted);
        sort($disclosed);

        $this->assertSame(
            $granted,
            $disclosed,
            'The disclosed capability table and the granted capability sets have drifted.'
        );
    }

    /**
     * No capability is disclosed twice.
     *
     * A duplicate would let the test above pass while a capability is missing.
     *
     * @covers \local_corolair\local\integration_disclosure::get_capability_names
     * @return void
     */
    public function test_no_capability_is_disclosed_twice(): void {
        $disclosed = integration_disclosure::get_capability_names();

        $this->assertSame(
            count($disclosed),
            count(array_unique($disclosed)),
            'A capability appears in more than one disclosure group.'
        );
    }

    /**
     * The scheduled task is declared, implemented and registered.
     *
     * @covers \local_corolair\task\rotate_webservice_token_task
     * @return void
     */
    public function test_scheduled_task_is_registered(): void {
        $tasks = $this->load_definition('db/tasks.php', ['tasks'])['tasks'];

        $this->assertNotEmpty($tasks);
        foreach ($tasks as $task) {
            $classname = $task['classname'];
            $this->assertTrue(class_exists($classname), "{$classname} is scheduled but does not exist.");
            $this->assertTrue(
                is_subclass_of($classname, \core\task\scheduled_task::class),
                "{$classname} is scheduled but is not a scheduled task."
            );

            $registered = \core\task\manager::get_scheduled_task($classname);
            $this->assertNotFalse($registered, "{$classname} is not registered on the site.");
            $this->assertSame('local_corolair', $registered->get_component());
        }
    }

    /**
     * The ad-hoc task classes referenced elsewhere in the plugin exist.
     *
     * These are referenced by name when queued, so a rename would only fail at run time.
     *
     * @covers \local_corolair\task\setup_corolair_connection_task
     * @covers \local_corolair\task\migrate_legacy_credentials_task
     * @covers \local_corolair\task\retry_webservice_token_rotation_task
     * @return void
     */
    public function test_adhoc_task_classes_exist(): void {
        $adhoc = [
            \local_corolair\task\setup_corolair_connection_task::class,
            \local_corolair\task\migrate_legacy_credentials_task::class,
            \local_corolair\task\retry_webservice_token_rotation_task::class,
        ];

        foreach ($adhoc as $classname) {
            $this->assertTrue(
                is_subclass_of($classname, \core\task\adhoc_task::class),
                "{$classname} is queued as an ad-hoc task but is not one."
            );
        }
    }

    /**
     * Every declared message provider is registered on the site and named in every language.
     *
     * message_send() rejects a provider the site does not know about, so a provider that is
     * declared but never installed turns a warning into a fatal error at the moment the
     * warning was needed. A provider with no messageprovider: string is milder but visible:
     * the notification preferences page lists it as a raw identifier.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @covers \local_corolair\local\setup_reminder::maintain
     * @return void
     */
    public function test_message_providers_are_registered(): void {
        global $DB;

        $providers = $this->load_definition('db/messages.php', ['messageproviders'])['messageproviders'];

        $this->assertArrayHasKey('tokenexpirywarning', $providers);
        $this->assertArrayHasKey('setuppending', $providers);
        foreach (array_keys($providers) as $name) {
            $this->assertTrue(
                $DB->record_exists('message_providers', ['component' => 'local_corolair', 'name' => $name]),
                "The {$name} message provider is declared but not registered on the site."
            );
            foreach (['en', 'fr', 'es'] as $language) {
                $strings = $this->load_strings($language);
                $this->assertArrayHasKey(
                    'messageprovider:' . $name,
                    $strings,
                    "lang/{$language} does not name the {$name} message provider."
                );
            }
        }
    }

    /**
     * The plugin metadata is well formed.
     *
     * @coversNothing
     * @return void
     */
    public function test_version_metadata_is_well_formed(): void {
        global $CFG;

        $plugin = new \stdClass();
        require($CFG->dirroot . '/local/corolair/version.php');

        $this->assertSame('local_corolair', $plugin->component);
        $this->assertMatchesRegularExpression(
            '/^20\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{2}$/',
            (string)$plugin->version,
            'The version should stay in YYYYMMDDXX form so upgrades order correctly.'
        );
        $this->assertGreaterThan(0, (int)$plugin->requires);
        $this->assertNotEmpty($plugin->release);
        $this->assertSame(MATURITY_STABLE, $plugin->maturity);
    }

    /**
     * Every language string used by the disclosure resolves in English.
     *
     * @covers \local_corolair\local\integration_disclosure::get_function_groups
     * @return void
     */
    public function test_disclosure_strings_resolve(): void {
        $strings = $this->load_strings('en');

        foreach (['disclosureaccessread', 'disclosureaccesswrite', 'disclosureplanned'] as $key) {
            $this->assertArrayHasKey($key, $strings, "lang/en is missing {$key}.");
        }
        foreach (['identity', 'content', 'enrolment', 'completion', 'roleassignment', 'examplacement'] as $group) {
            $this->assertArrayHasKey('disclosuregroup' . $group, $strings);
            $this->assertArrayHasKey('disclosuregroup' . $group . 'desc', $strings);
        }
    }

    /**
     * Translations that should not contain keys English does not have.
     *
     * @return array[] Data sets of [language].
     */
    public static function translation_provider(): array {
        return [
            'french' => ['fr'],
            'spanish' => ['es'],
        ];
    }

    /**
     * A translation must not define keys that no longer exist in English.
     *
     * A key removed from English but left in a translation is dead weight, and usually
     * means the English key was renamed and the translation was not updated -- which
     * leaves that language rendering a raw identifier.
     *
     * @dataProvider translation_provider
     * @coversNothing
     * @param string $language Language directory name.
     * @return void
     */
    public function test_translations_define_no_unknown_keys(string $language): void {
        $english = $this->load_strings('en');
        $translated = $this->load_strings($language);

        $this->assertNotEmpty($translated);
        $unknown = array_keys(array_diff_key($translated, $english));

        $this->assertSame(
            [],
            $unknown,
            "lang/{$language} defines keys that lang/en does not: " . implode(', ', $unknown)
        );
    }

    /**
     * A translation must define every key English defines.
     *
     * This is the direction that actually bites. Adding a string to lang/en and forgetting
     * the other packs breaks nothing in English, so it passes review and CI unnoticed, and
     * only speakers of that language see the raw identifier.
     *
     * @dataProvider translation_provider
     * @coversNothing
     * @param string $language Language directory name.
     * @return void
     */
    public function test_translations_define_every_english_key(string $language): void {
        $english = $this->load_strings('en');
        $translated = $this->load_strings($language);

        $this->assertNotEmpty($english);
        $missing = array_keys(array_diff_key($english, $translated));

        $this->assertSame(
            [],
            $missing,
            "lang/{$language} is missing keys defined in lang/en: " . implode(', ', $missing)
        );
    }

    /**
     * Every setting the admin page defines resolves to a real string in every language.
     *
     * phpcs cannot catch a setting whose title or description key was never added to the
     * language packs; the settings page just renders the identifier instead.
     *
     * @coversNothing
     * @return void
     */
    public function test_settings_strings_resolve(): void {
        $keys = [
            'sidepanel', 'sidepaneldesc',
            'createtutorcapability', 'createtutorcapabilitydesc',
            'apikey', 'apikeydesc',
            'excludedmods', 'excludedmodsdesc',
            'disabletokenrotation', 'disabletokenrotationdesc',
        ];
        foreach (['en', 'fr', 'es'] as $language) {
            $strings = $this->load_strings($language);
            foreach ($keys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $strings,
                    "lang/{$language} does not define the settings string '{$key}'."
                );
                $this->assertNotSame('', trim((string)$strings[$key]));
            }
        }
    }
}
