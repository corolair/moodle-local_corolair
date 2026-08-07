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
 * Tests for the plugin callbacks in lib.php.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\event\remote_request_completed;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/corolair/lib.php');

/**
 * Verifies the widget is never rendered, and never requested, unless it should be.
 *
 * local_corolair_render_embed_script() posts the current user's identity to Raison
 * before it can render anything, so each of its guards is the difference between "no
 * widget" and "this user's email and name were sent to a third party". Every test here
 * asserts the absence of the audit event as well as the absence of output: returning an
 * empty string after making the request would look identical otherwise.
 */
final class lib_test extends \advanced_testcase {
    /**
     * Assert the callback rendered nothing and contacted nobody.
     *
     * @param callable $callback Callback that should stay silent.
     * @return void
     */
    private function assert_silent(callable $callback): void {
        $sink = $this->redirectEvents();
        $output = $callback();
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('', $output);
        foreach ($events as $event) {
            $this->assertNotInstanceOf(
                remote_request_completed::class,
                $event,
                'No request may be made on a path that renders nothing.'
            );
        }
    }

    /**
     * Set a usable page URL so the callbacks can read $PAGE->url.
     *
     * @param string $url Page URL, relative to wwwroot.
     * @return void
     */
    private function set_page_url(string $url = '/course/view.php'): void {
        global $PAGE;

        $PAGE->set_url(new \moodle_url($url, ['id' => 1]));
    }

    /**
     * Non-course pages get no widget at all.
     *
     * Organization-wide widgets were retired; only courses carry one.
     *
     * @covers ::local_corolair_render_embed_script
     * @return void
     */
    public function test_no_widget_without_a_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_page_url();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');

        $this->assert_silent(function () {
            return local_corolair_render_embed_script(0, \context_system::instance(), 'false');
        });
    }

    /**
     * API key states that mean the site is not connected.
     *
     * @return array[] Data sets of [description, stored value or null to unset].
     */
    public static function unconnected_api_key_provider(): array {
        return [
            'unset' => [null],
            'empty' => [''],
            'placeholder' => ['placeholder'],
        ];
    }

    /**
     * An unconnected site renders no widget and makes no request.
     *
     * @dataProvider unconnected_api_key_provider
     * @covers ::local_corolair_render_embed_script
     * @param string|null $apikey Stored API key, or null to leave it unset.
     * @return void
     */
    public function test_no_widget_without_a_credential(?string $apikey): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_page_url();

        if ($apikey === null) {
            unset_config('apikey', 'local_corolair');
        } else if ($apikey === 'placeholder') {
            set_config('apikey', get_string('noapikey', 'local_corolair'), 'local_corolair');
        } else {
            set_config('apikey', $apikey, 'local_corolair');
        }
        $course = $this->getDataGenerator()->create_course();

        $this->assert_silent(function () use ($course) {
            return local_corolair_render_embed_script(
                (int)$course->id,
                \context_course::instance($course->id),
                'false'
            );
        });
    }

    /**
     * A guest gets no widget, and their session is never registered with Raison.
     *
     * @covers ::local_corolair_render_embed_script
     * @return void
     */
    public function test_no_widget_for_a_guest(): void {
        $this->resetAfterTest();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->setGuestUser();
        $this->set_page_url();

        $this->assert_silent(function () use ($course) {
            return local_corolair_render_embed_script(
                (int)$course->id,
                \context_course::instance($course->id),
                'false'
            );
        });
    }

    /**
     * A logged-out visitor gets no widget.
     *
     * @covers ::local_corolair_render_embed_script
     * @return void
     */
    public function test_no_widget_when_logged_out(): void {
        $this->resetAfterTest();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->setUser(null);
        $this->set_page_url();

        $this->assert_silent(function () use ($course) {
            return local_corolair_render_embed_script(
                (int)$course->id,
                \context_course::instance($course->id),
                'false'
            );
        });
    }

    /**
     * The retired footer hook renders nothing on any page.
     *
     * @covers ::local_corolair_before_footer
     * @return void
     */
    public function test_footer_hook_renders_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');

        $this->assert_silent(function () {
            return local_corolair_before_footer();
        });
    }

    /**
     * Return the visible texts of a navigation node's children.
     *
     * @param \navigation_node $node Node to inspect.
     * @return string[]
     */
    private function child_texts(\navigation_node $node): array {
        $texts = [];
        foreach ($node->children as $child) {
            $texts[] = (string)$child->text;
        }
        return $texts;
    }

    /**
     * Run the course navigation callback and return the node plus any output.
     *
     * @param \stdClass $course Course being viewed.
     * @return array{0: \navigation_node, 1: string} Navigation node and captured output.
     */
    private function extend_course_navigation(\stdClass $course): array {
        $navigation = \navigation_node::create('Course administration', null, \navigation_node::TYPE_COURSE);

        ob_start();
        local_corolair_extend_navigation_course(
            $navigation,
            $course,
            \context_course::instance($course->id)
        );
        $output = (string)ob_get_clean();

        return [$navigation, $output];
    }

    /**
     * A trainer gets the course link.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_course_navigation_offers_the_link_to_trainers(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->set_page_url();

        [$navigation] = $this->extend_course_navigation($course);

        $this->assertContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation)
        );
    }

    /**
     * A learner without the capability does not get the course link.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_course_navigation_hides_the_link_from_learners(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int)$user->id, (int)$course->id, 'student');
        $this->setUser($user);
        $this->set_page_url();

        [$navigation] = $this->extend_course_navigation($course);

        $this->assertNotContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation)
        );
    }

    /**
     * An excluded activity module suppresses the widget entirely.
     *
     * The exclusion has to short-circuit before the session request, not merely discard
     * its result: an administrator who excludes /mod/quiz/ is saying that no identity
     * should be sent to Raison from quiz pages at all.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_excluded_module_makes_no_request(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('excludedmods', 'quiz, forum', 'local_corolair');
        $this->set_page_url('/mod/quiz/view.php');

        $sink = $this->redirectEvents();
        [$navigation, $output] = $this->extend_course_navigation($course);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('', $output);
        foreach ($events as $event) {
            $this->assertNotInstanceOf(remote_request_completed::class, $event);
        }
        $this->assertContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation),
            'Excluding a module hides the widget, not the trainer link.'
        );
    }

    /**
     * A page that is neither a course view nor an activity renders no widget.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_unrelated_page_renders_no_widget(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $this->set_page_url('/my/index.php');

        $sink = $this->redirectEvents();
        [, $output] = $this->extend_course_navigation($course);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('', $output);
        foreach ($events as $event) {
            $this->assertNotInstanceOf(remote_request_completed::class, $event);
        }
    }

    /**
     * A trainer gets the front-page link, and a learner does not.
     *
     * @covers ::local_corolair_extend_navigation_frontpage
     * @return void
     */
    public function test_frontpage_navigation_is_capability_gated(): void {
        global $SITE;

        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $context = \context_course::instance($SITE->id);
        $title = get_string('frontpagenodetitle', 'local_corolair');

        $this->setAdminUser();
        $fortrainer = \navigation_node::create('Front page', null, \navigation_node::TYPE_COURSE);
        local_corolair_extend_navigation_frontpage($fortrainer, $SITE, $context);
        $this->assertContains($title, $this->child_texts($fortrainer));

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $forlearner = \navigation_node::create('Front page', null, \navigation_node::TYPE_COURSE);
        local_corolair_extend_navigation_frontpage($forlearner, $SITE, $context);
        $this->assertNotContains($title, $this->child_texts($forlearner));
    }

    /**
     * The front-page link is removed again if the capability is lost.
     *
     * Unlike the course node, this one is created with an explicit key, so find() can
     * locate it and the removal branch actually works.
     *
     * @covers ::local_corolair_extend_navigation_frontpage
     * @return void
     */
    public function test_frontpage_link_is_removed_without_the_capability(): void {
        global $SITE;

        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $context = \context_course::instance($SITE->id);
        $title = get_string('frontpagenodetitle', 'local_corolair');

        $this->setAdminUser();
        $navigation = \navigation_node::create('Front page', null, \navigation_node::TYPE_COURSE);
        local_corolair_extend_navigation_frontpage($navigation, $SITE, $context);
        $this->assertContains($title, $this->child_texts($navigation));

        // The same tree, now rendered for someone without the capability.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        local_corolair_extend_navigation_frontpage($navigation, $SITE, $context);

        $this->assertNotContains($title, $this->child_texts($navigation));
    }

    /**
     * Saving the rotation setting queues exactly one task and records who changed it.
     *
     * The task deliberately carries no payload, so repeated saves collapse into the single
     * pending record rather than queueing a rotation per click. Nothing about the desired
     * state travels in it either -- the task re-reads the configuration when it runs, which
     * is what makes toggling the setting back and forth safe.
     *
     * @covers ::local_corolair_disabletokenrotation_updated
     * @return void
     */
    public function test_rotation_setting_callback_queues_one_task(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('disabletokenrotation', 1, 'local_corolair');

        $sink = $this->redirectEvents();
        local_corolair_disabletokenrotation_updated('s_local_corolair_disabletokenrotation');
        local_corolair_disabletokenrotation_updated('s_local_corolair_disabletokenrotation');
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(
            1,
            $DB->count_records(
                'task_adhoc',
                ['classname' => '\local_corolair\task\retry_webservice_token_rotation_task']
            )
        );

        $actions = [];
        foreach ($events as $event) {
            if ($event instanceof \local_corolair\event\webservice_token_lifecycle) {
                $actions[] = $event->other['action'];
            }
        }
        $this->assertSame(['rotation_disabled', 'rotation_disabled'], $actions);
    }
}
