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
 * Tests for the plugin renderer.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\integration_disclosure;

/**
 * Verifies the rendered output, and in particular what the troubleshoot URL leaks.
 *
 * render_installation_troubleshoot() builds a cross-origin URL that the administrator's
 * browser then navigates to. Everything in its query string ends up in browser history,
 * any intermediate proxy, and the receiving provider's access logs -- so the four
 * technical booleans it carries are a deliberate allow-list, not an accident, and
 * nothing identifying the site or the operator may join them.
 */
final class renderer_test extends \advanced_testcase {
    /**
     * Return the plugin renderer.
     *
     * @return \local_corolair\output\renderer
     */
    private function renderer(): \local_corolair\output\renderer {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/local/corolair/trainer.php'));
        $PAGE->set_context(\context_system::instance());
        return $PAGE->get_renderer('local_corolair');
    }

    /**
     * Extract the troubleshoot URL from the rendered markup.
     *
     * @param string $html Rendered output.
     * @return string
     */
    private function extract_troubleshoot_url(string $html): string {
        $this->assertMatchesRegularExpression('/https:\/\/embed\.corolair\.dev\/troubleshoot/', $html);
        preg_match('/(https:\/\/embed\.corolair\.dev\/troubleshoot[^"\'\s>]*)/', $html, $matches);
        $this->assertNotEmpty($matches);
        return html_entity_decode($matches[1]);
    }

    /**
     * Flag combinations and the query values they must produce.
     *
     * @return array[] Data sets of [webservices, rest, service, token].
     */
    public static function troubleshoot_flag_provider(): array {
        return [
            'nothing working' => [false, false, false, false],
            'everything working' => [true, true, true, true],
            'service but no token' => [true, true, true, false],
            'rest missing' => [true, false, true, true],
        ];
    }

    /**
     * The diagnostic flags are forwarded exactly as given.
     *
     * @dataProvider troubleshoot_flag_provider
     * @covers \local_corolair\output\renderer::render_installation_troubleshoot
     * @param bool $webservices Whether web services are enabled.
     * @param bool $rest Whether REST is enabled.
     * @param bool $service Whether the external service exists.
     * @param bool $token Whether a token exists.
     * @return void
     */
    public function test_troubleshoot_flags_are_forwarded(
        bool $webservices,
        bool $rest,
        bool $service,
        bool $token
    ): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->renderer()->render_installation_troubleshoot($webservices, $rest, $service, $token);
        $query = [];
        parse_str((string)parse_url($this->extract_troubleshoot_url($html), PHP_URL_QUERY), $query);

        $this->assertSame($webservices ? 'true' : 'false', $query['isWebServiceEnabled']);
        $this->assertSame($rest ? 'true' : 'false', $query['isRestProtocolEnabled']);
        $this->assertSame($service ? 'true' : 'false', $query['isCorolairServiceExist']);
        $this->assertSame($token ? 'true' : 'false', $query['isTokenExist']);
    }

    /**
     * Nothing beyond the four technical flags reaches the third-party URL.
     *
     * @covers \local_corolair\output\renderer::render_installation_troubleshoot
     * @return void
     */
    public function test_troubleshoot_url_carries_nothing_identifying(): void {
        global $CFG, $SITE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('apikey', 'org_secretinstance.verysecretvalue', 'local_corolair');

        $html = $this->renderer()->render_installation_troubleshoot(true, true, true, true);
        $url = $this->extract_troubleshoot_url($html);
        $query = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(
            ['isWebServiceEnabled', 'isRestProtocolEnabled', 'isCorolairServiceExist', 'isTokenExist'],
            array_keys($query),
            'The troubleshoot URL gained a parameter; it is sent cross-origin.'
        );
        foreach ([$USER->email, $SITE->fullname, $CFG->wwwroot, 'verysecretvalue'] as $secret) {
            $this->assertStringNotContainsString(
                (string)$secret,
                $url,
                'Identifying or secret data must not be placed in a cross-origin URL.'
            );
        }
    }

    /**
     * The widget session token reaches the embed script, and the panel flag is escaped.
     *
     * @covers \local_corolair\output\renderer::render_embed_script
     * @return void
     */
    public function test_embed_script_carries_the_session_token(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->renderer()->render_embed_script('true', 'false', 'session-token-value');

        $this->assertStringContainsString('data-user-token="session-token-value"', $html);
        $this->assertStringContainsString('data-sidepanel="true"', $html);
        $this->assertStringContainsString('data-animate="false"', $html);
    }

    /**
     * A hostile side-panel value cannot break out of its attribute.
     *
     * @covers \local_corolair\output\renderer::render_embed_script
     * @return void
     */
    public function test_embed_script_escapes_the_side_panel_value(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->renderer()->render_embed_script('"><script>alert(1)</script>', 'false', 'token');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('"><', $html);
    }

    /**
     * The disclosure page renders every documented group.
     *
     * @covers \local_corolair\output\renderer::render_setup_disclosure
     * @return void
     */
    public function test_setup_disclosure_renders_every_group(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = $this->renderer()->render_setup_disclosure([
            'version' => integration_disclosure::VERSION,
            'groups' => integration_disclosure::get_function_groups(),
            'actionurl' => (new \moodle_url('/local/corolair/setup.php'))->out(false),
            'cancelurl' => (new \moodle_url('/admin/settings.php', ['section' => 'local_corolair']))->out(false),
            'sesskey' => sesskey(),
            'repositoryurl' => 'https://github.com/corolair/moodle-local_corolair',
        ]);

        $this->assertNotEmpty($html);
        foreach (integration_disclosure::get_function_names() as $function) {
            $this->assertStringContainsString(
                $function,
                $html,
                "The disclosure page does not list {$function}."
            );
        }
    }

    /**
     * The demo template renders without a context of its own.
     *
     * @covers \local_corolair\output\renderer::render_demo
     * @return void
     */
    public function test_demo_renders(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertNotEmpty($this->renderer()->render_demo());
    }
}
