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
 * Tests for the shared API key accessor.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\api_key;

/**
 * Verifies that placeholder values are never mistaken for a real credential.
 */
final class api_key_test extends \advanced_testcase {
    /**
     * Values that must all be reported as "no API key configured".
     *
     * @return array[] Data sets of [stored value].
     */
    public static function placeholder_provider(): array {
        return [
            'empty' => [''],
            'english current' => ['No Raison Api Key'],
            'french current' => ['Aucune Clé API Raison'],
            'spanish current' => ['No hay clave API de Raison'],
            'portuguese current' => ['Nenhuma chave de API do Raison'],
            'english legacy' => ['No Corolair Api Key'],
            'french legacy' => ['Aucune Clé API Corolair'],
            'spanish legacy' => ['No hay clave API de Corolair'],
        ];
    }

    /**
     * A placeholder must never be handed out as a credential.
     *
     * @dataProvider placeholder_provider
     * @covers \local_corolair\local\api_key::get
     * @param string $stored Value held in plugin configuration.
     * @return void
     */
    public function test_placeholders_are_not_credentials(string $stored): void {
        $this->resetAfterTest();

        set_config('apikey', $stored, 'local_corolair');

        $this->assertNull(api_key::get(), "'{$stored}' must not be treated as a key.");
        $this->assertFalse(api_key::is_configured());
    }

    /**
     * The settings default is the translated placeholder, so it must read as unconfigured.
     *
     * @covers \local_corolair\local\api_key::get
     * @return void
     */
    public function test_translated_settings_default_is_not_a_credential(): void {
        $this->resetAfterTest();

        set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');

        $this->assertNull(api_key::get());
    }

    /**
     * Every shipped language pack, read from lang/ rather than a hand-kept list.
     *
     * @return array[] Data sets of [language directory, path to its string file].
     */
    public static function language_pack_provider(): array {
        $packs = [];
        foreach (glob(dirname(__DIR__) . '/lang/*/local_corolair.php') as $path) {
            $language = basename(dirname($path));
            $packs[$language] = [$language, $path];
        }
        return $packs;
    }

    /**
     * The settings default must read as unconfigured whatever language the site installed in.
     *
     * The test above only sees the language the test run uses. 1.9.16 added Portuguese packs
     * without their placeholder, so Portuguese sites sent it to Raison as a bearer token.
     * Iterating lang/ makes the next new pack fail here until api_key recognises it.
     *
     * @dataProvider language_pack_provider
     * @covers \local_corolair\local\api_key::get
     * @param string $language Language directory name.
     * @param string $path Path to that language's string file.
     * @return void
     */
    public function test_every_translated_placeholder_is_not_a_credential(string $language, string $path): void {
        $this->resetAfterTest();

        $string = [];
        require($path);
        $this->assertArrayHasKey('noapikey', $string, "lang/{$language} does not define noapikey.");

        set_config('apikey', $string['noapikey'], 'local_corolair');

        $this->assertNull(
            api_key::get(),
            "lang/{$language} placeholder '{$string['noapikey']}' is missing from api_key::PLACEHOLDER_PREFIXES."
        );
    }

    /**
     * A real key round-trips unchanged.
     *
     * @covers \local_corolair\local\api_key::get
     * @return void
     */
    public function test_real_key_round_trips(): void {
        $this->resetAfterTest();

        set_config('apikey', 'org_abc123.9f8e7d6c5b4a', 'local_corolair');

        $this->assertSame('org_abc123.9f8e7d6c5b4a', api_key::get());
        $this->assertTrue(api_key::is_configured());
    }
}
