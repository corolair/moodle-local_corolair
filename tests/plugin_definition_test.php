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

use local_corolair\local\role_provisioner;

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
     * The message provider used by the token warning is declared and registered.
     *
     * @covers \local_corolair\local\webservice_token_manager::maintain
     * @return void
     */
    public function test_message_provider_is_registered(): void {
        global $DB;

        $providers = $this->load_definition('db/messages.php', ['messageproviders'])['messageproviders'];

        $this->assertArrayHasKey('tokenexpirywarning', $providers);
        $this->assertTrue($DB->record_exists('message_providers', [
            'component' => 'local_corolair',
            'name' => 'tokenexpirywarning',
        ]));
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
