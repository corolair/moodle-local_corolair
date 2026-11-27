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
 * Tests for the global assistant's page rules.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\global_assistant;

/**
 * Verifies the rules that decide, without asking Raison, where the global assistant may not go.
 *
 * @covers \local_corolair\local\global_assistant
 */
final class global_assistant_test extends \advanced_testcase {
    /**
     * Raison's field reads as off unless it is an object.
     *
     * @return void
     */
    public function test_parse_reads_off_unless_an_object(): void {
        $this->assertNull(global_assistant::parse(null));
        $this->assertNull(global_assistant::parse(true));
        $this->assertNull(global_assistant::parse('on'));
        $this->assertNull(global_assistant::parse(['excludedPages' => 'admin-*']));
        $this->assertSame(['excludedpages' => []], global_assistant::parse(['excludedPages' => []]));
        // A rule set without a list excludes nothing.
        $this->assertSame(['excludedpages' => []], global_assistant::parse([]));
    }

    /**
     * Patterns are trimmed and kept once; what cannot be a pattern is dropped.
     *
     * @return void
     */
    public function test_patterns_are_normalised(): void {
        $sent = [' admin-* ', 'admin-*', '', '   ', 42, null, '/my/courses.php', str_repeat('a', 256)];
        $this->assertSame(['admin-*', '/my/courses.php'], global_assistant::normalize_patterns($sent));
        $many = array_map(fn(int $i): string => 'type-' . $i, range(1, 250));
        $this->assertCount(200, global_assistant::normalize_patterns($many));
    }

    /**
     * A page type matches exactly, or as a prefix when the pattern ends with a star.
     *
     * @return void
     */
    public function test_page_types_match_exactly_or_by_prefix(): void {
        $this->assertTrue(global_assistant::page_excluded('my-index', '/my/', ['my-index']));
        $this->assertTrue(global_assistant::page_excluded('admin-setting-manageauths', '/admin/settings.php', ['admin-*']));
        $this->assertFalse(global_assistant::page_excluded('admin-setting-manageauths', '/admin/settings.php', ['admin']));
        $this->assertFalse(global_assistant::page_excluded('user-profile', '/user/profile.php', ['admin-*', 'my-index']));
        $this->assertTrue(global_assistant::page_excluded('anything', '/whatever.php', ['*']));
        $this->assertFalse(global_assistant::page_excluded('my-index', '/my/', []));
    }

    /**
     * A path pattern tells apart two pages that share a page type.
     *
     * The dashboard and My courses are both my-index; only their paths differ.
     *
     * @return void
     */
    public function test_paths_tell_apart_pages_sharing_a_type(): void {
        $this->assertTrue(global_assistant::page_excluded('my-index', '/my/courses.php', ['/my/courses.php']));
        $this->assertFalse(global_assistant::page_excluded('my-index', '/my/index.php', ['/my/courses.php']));
        $this->assertTrue(global_assistant::page_excluded('my-index', '/my/courses.php', ['/my/*']));
        // A path pattern never matches a page type, nor a type pattern a path.
        $this->assertFalse(global_assistant::page_excluded('my-index', '/my/index.php', ['/my-index']));
        $this->assertFalse(global_assistant::page_excluded('', '', ['/my/*', 'my-*']));
    }

    /**
     * Small windows, frames and printouts never get it; ordinary layouts may.
     *
     * @return void
     */
    public function test_layouts(): void {
        foreach (['popup', 'embedded', 'frametop', 'print', 'redirect', 'login', 'secure', 'maintenance'] as $layout) {
            $this->assertTrue(global_assistant::layout_excluded($layout), $layout);
        }
        foreach (['standard', 'base', 'mydashboard', 'frontpage', 'admin', 'course', 'incourse', 'mypublic'] as $layout) {
            $this->assertFalse(global_assistant::layout_excluded($layout), $layout);
        }
    }

    /**
     * The plugin's own pages are recognised by their path.
     *
     * @return void
     */
    public function test_own_pages(): void {
        $this->assertTrue(global_assistant::own_page('/local/corolair/trainer.php'));
        $this->assertFalse(global_assistant::own_page('/local/other/index.php'));
        $this->assertFalse(global_assistant::own_page(''));
    }

    /**
     * The path is taken below the site root, without the query.
     *
     * @return void
     */
    public function test_local_path(): void {
        $this->assertSame('/my/courses.php', global_assistant::local_path(new \moodle_url('/my/courses.php', ['tab' => 2])));
        $this->assertSame('', global_assistant::local_path(new \moodle_url('https://elsewhere.example.com/my/courses.php')));
    }

    /**
     * Someone with nothing pending may get it.
     *
     * @return void
     */
    public function test_person_with_nothing_pending(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(global_assistant::person_must_act_first($user));
    }

    /**
     * A forced password change comes first.
     *
     * @return void
     */
    public function test_forced_password_change_comes_first(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference('auth_forcepasswordchange', 1, $user);

        $this->assertTrue(global_assistant::person_must_act_first($user));
    }

    /**
     * A site policy not yet accepted comes first, except for administrators, as in require_login().
     *
     * @return void
     */
    public function test_site_policy_comes_first(): void {
        $this->resetAfterTest();
        set_config('sitepolicy', 'https://example.com/policy.html');
        $user = $this->getDataGenerator()->create_user(['policyagreed' => 0]);

        $this->assertTrue(global_assistant::person_must_act_first($user));

        $user->policyagreed = 1;
        $this->assertFalse(global_assistant::person_must_act_first($user));
        $this->assertFalse(global_assistant::person_must_act_first(get_admin()));
    }

    /**
     * A profile missing what the site requires comes first.
     *
     * @return void
     */
    public function test_unfinished_profile_comes_first(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $user->email = '';

        $this->assertTrue(global_assistant::person_must_act_first($user));
    }
}
