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
use local_corolair\local\environment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/corolair/lib.php');
// Supplies LTI_TOOL_STATE_CONFIGURED, which the tool-type fixtures below need.
require_once($CFG->dirroot . '/mod/lti/locallib.php');

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
     * Without a course on the page, the footer hook renders nothing.
     *
     * @covers ::local_corolair_before_footer
     * @return void
     */
    public function test_footer_hook_renders_nothing_without_a_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $this->set_page_url('/my/index.php');

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
     * Run the course navigation callback and capture any accidental output.
     *
     * Navigation callbacks must never echo: early output before DOCTYPE puts the
     * page into quirks mode and TinyMCE refuses to initialize.
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
     * Point $PAGE at a course so before_footer can resolve course context.
     *
     * @param \stdClass $course Course being viewed.
     * @param string $url Page URL, relative to wwwroot.
     * @return void
     */
    private function set_course_page(\stdClass $course, string $url): void {
        global $PAGE;

        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url($url, ['id' => $course->id]));
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
     * Give a new user the plugin-owned role at a context, and sign them in.
     *
     * @param \context $context Context to assign the role at.
     * @return \stdClass The signed-in user.
     */
    private function signed_in_corolair_manager(\context $context): \stdClass {
        global $DB;

        $roleid = (int)$DB->get_field(
            'role',
            'id',
            ['shortname' => \local_corolair\local\role_provisioner::SHORTNAME],
            MUST_EXIST
        );
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        return $user;
    }

    /**
     * Run the front-page navigation callback the way Moodle does.
     *
     * Moodle passes the front-page *course* context here, not the system context; the
     * callback is expected to ignore it. Reproducing that faithfully is the whole point.
     *
     * @return array The texts of the resulting child nodes.
     */
    private function front_page_node_texts(): array {
        global $SITE;

        $parent = \navigation_node::create('Front page', null, \navigation_node::TYPE_COURSE);
        local_corolair_extend_navigation_frontpage($parent, $SITE, \context_course::instance($SITE->id));

        return $this->child_texts($parent);
    }

    /**
     * A course-level Raison Manager gets the course link.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_course_navigation_offers_the_link_to_a_course_level_manager(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_corolair_manager(\context_course::instance($course->id));
        $this->set_page_url();

        [$navigation] = $this->extend_course_navigation($course);

        $this->assertContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation)
        );
    }

    /**
     * A course-level Raison Manager does not get the front-page link.
     *
     * The front-page link opens the site-wide launch, which is authorised at system context.
     * Gating it on the context Moodle hands the callback -- the front-page course -- would
     * offer it to someone the page then refuses, which is the dead link this change removes.
     *
     * @covers ::local_corolair_extend_navigation_frontpage
     * @return void
     */
    public function test_frontpage_navigation_hides_the_link_from_a_course_level_manager(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_corolair_manager(\context_course::instance($course->id));

        $this->assertNotContains(
            get_string('frontpagenodetitle', 'local_corolair'),
            $this->front_page_node_texts()
        );
    }

    /**
     * A role held only on Site home offers no front-page link either.
     *
     * Site home is a course, so this assignment satisfies the context Moodle passes in while
     * satisfying nothing the launch behind the link requires.
     *
     * @covers ::local_corolair_extend_navigation_frontpage
     * @return void
     */
    public function test_frontpage_navigation_hides_the_link_from_a_front_page_manager(): void {
        global $SITE;

        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $this->signed_in_corolair_manager(\context_course::instance($SITE->id));

        // The gate this replaced. Asserting it holds is what makes the assertion below a
        // regression test rather than a restatement: this user satisfies the context Moodle
        // passes the callback, so checking that context is exactly what produced the link
        // that trainer.php then refused.
        $this->assertTrue(has_capability(
            \local_corolair\local\launch_access::CAPABILITY,
            \context_course::instance($SITE->id)
        ));

        $this->assertNotContains(
            get_string('frontpagenodetitle', 'local_corolair'),
            $this->front_page_node_texts()
        );
    }

    /**
     * A site-wide Raison Manager gets both links.
     *
     * @covers ::local_corolair_extend_navigation_course
     * @covers ::local_corolair_extend_navigation_frontpage
     * @return void
     */
    public function test_navigation_offers_both_links_to_a_system_level_manager(): void {
        $this->resetAfterTest();
        unset_config('apikey', 'local_corolair');
        $course = $this->getDataGenerator()->create_course();
        $this->signed_in_corolair_manager(\context_system::instance());
        $this->set_page_url();

        [$navigation] = $this->extend_course_navigation($course);

        $this->assertContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation)
        );
        $this->assertContains(
            get_string('frontpagenodetitle', 'local_corolair'),
            $this->front_page_node_texts()
        );
    }

    /**
     * Course navigation never flushes embed HTML (preserves standards mode).
     *
     * @covers ::local_corolair_extend_navigation_course
     * @return void
     */
    public function test_course_navigation_never_echoes_the_widget(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $this->set_course_page($course, '/course/view.php');

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
     * An excluded activity module suppresses the widget entirely.
     *
     * The exclusion has to short-circuit before the session request, not merely discard
     * its result: an administrator who excludes /mod/quiz/ is saying that no identity
     * should be sent to Raison from quiz pages at all.
     *
     * @covers ::local_corolair_before_footer
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_excluded_module_makes_no_request(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('excludedmods', 'quiz, forum', 'local_corolair');
        $this->set_course_page($course, '/mod/quiz/view.php');

        $this->assert_silent(function () {
            return local_corolair_before_footer();
        });

        [$navigation] = $this->extend_course_navigation($course);
        $this->assertContains(
            get_string('coursenodetitle', 'local_corolair'),
            $this->child_texts($navigation),
            'Excluding a module hides the widget, not the trainer link.'
        );
    }

    /**
     * A page that is neither a course view nor an activity renders no widget.
     *
     * @covers ::local_corolair_before_footer
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_unrelated_page_renders_no_widget(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        $this->set_course_page($course, '/my/index.php');

        $this->assert_silent(function () {
            return local_corolair_before_footer();
        });
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

    /**
     * A tool type that launches from Raison.
     *
     * The launch URL is built from environment rather than written out: every host in this
     * plugin is a property of the deployment, and plugin_definition_test scans tests/ for
     * literals just as it scans everything else.
     *
     * @return int {lti_types}.id
     */
    private function raison_tool_type(): int {
        // The stored default belongs to whichever environment the test database was installed
        // under, which is not necessarily this tree's. Pin it, as exam_placement_test does.
        set_config('ltitoolhost', environment::host('services'), 'local_corolair');

        return $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Raison exam tool',
            'baseurl' => environment::url('services', 'integration/lti/launch'),
            'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);
    }

    /**
     * Put $PAGE on an activity's view page, the way mod/lti/view.php does.
     *
     * set_course_page() above cannot be reused: it fixes the URL id to the course id and never
     * calls set_cm(), so $PAGE->cm stays null and the activity is invisible to the callback.
     *
     * @param \stdClass $course Course owning the activity.
     * @param \stdClass $module Module record as returned by the data generator.
     * @param string $modname Activity module short name.
     * @return void
     */
    private function set_activity_page(\stdClass $course, \stdClass $module, string $modname): void {
        global $PAGE;

        $cm = get_coursemodule_from_id($modname, $module->cmid, $course->id, false, MUST_EXIST);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url(new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $module->cmid]));
    }

    /**
     * The placement this plugin created is recognised, and suppresses the widget.
     *
     * Asserted through the footer callback rather than the placement helper, because the point
     * is not only that nothing renders: the learner's name and email must not reach Raison from
     * an exam page either, and returning an empty string after making that request would look
     * identical from the outside.
     *
     * @covers ::local_corolair_before_footer
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_raison_placement_makes_no_request(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $created = \local_corolair\external\create_exam_placement::execute(
            (int)$course->id,
            (int)$this->section_id($course, 1),
            $typeid,
            'Final exam'
        );
        $module = (object)['cmid' => $created['coursemoduleid']];
        $this->set_activity_page($course, $module, 'lti');

        $this->assert_silent(function () {
            return local_corolair_before_footer();
        });
    }

    /**
     * An activity nobody recorded is still recognised by the host its tool launches from.
     *
     * This is the case the ownership table cannot answer: exams placed before the table
     * existed were deliberately not back-filled, and a teacher can add the Raison tool by hand
     * at any time. Neither has a row, and both are exams.
     *
     * @covers ::local_corolair_before_footer
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_unrecorded_raison_tool_makes_no_request(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('apikey', 'org_test.realsecret', 'local_corolair');
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'typeid' => $typeid,
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assert_silent(function () {
            return local_corolair_before_footer();
        });
    }

    /**
     * An instance configured by URL rather than from a tool type is recognised too.
     *
     * mod/lti/view.php resolves that shape with lti_get_tool_by_url_match(); the launch URL on
     * the instance answers the same question here.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_raison_tool_url_without_a_type_is_recognised(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        set_config('ltitoolhost', environment::host('services'), 'local_corolair');
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'toolurl' => environment::url('services', 'integration/lti/launch'),
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assertSame([false, 'false'], $this->widget_placement($course));
    }

    /**
     * Another vendor's External tool keeps its assistant.
     *
     * The whole point of the setting: excluding "lti" wholesale was the only option before, and
     * it took the assistant off every tool on the site.
     *
     * Asserted on the placement helper, not the footer callback. A page that renders the widget
     * reaches the session request in local_corolair_render_embed_script(), and this suite makes
     * no network calls.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_foreign_lti_activity_keeps_the_widget(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $this->raison_tool_type();
        $foreign = $this->getDataGenerator()->get_plugin_generator('mod_lti')->create_tool_types([
            'name' => 'Unrelated tool',
            'baseurl' => 'https://tool.example.com/launch',
            'state' => LTI_TOOL_STATE_CONFIGURED,
        ]);
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'typeid' => $foreign,
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assertSame([true, 'false'], $this->widget_placement($course));
    }

    /**
     * Turning the setting off puts the assistant back on exam pages.
     *
     * @covers ::local_corolair_course_widget_placement
     * @covers ::local_corolair_hide_on_raison_exam
     * @return void
     */
    public function test_disabling_the_setting_restores_the_widget(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 0, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'typeid' => $typeid,
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assertSame([true, 'false'], $this->widget_placement($course));
    }

    /**
     * A site that has never saved the setting is protected anyway.
     *
     * get_config() returns false for a setting no upgrade has applied a default for yet, and
     * false is also what a deliberate "off" reads as after a cast. The distinction is the
     * difference between protecting those sites and silently not protecting them.
     *
     * @covers ::local_corolair_hide_on_raison_exam
     * @return void
     */
    public function test_the_setting_defaults_to_on_when_absent(): void {
        $this->resetAfterTest();
        unset_config('hideonraisonexam', 'local_corolair');

        $this->assertTrue(local_corolair_hide_on_raison_exam());

        set_config('hideonraisonexam', 0, 'local_corolair');
        $this->assertFalse(local_corolair_hide_on_raison_exam());
    }

    /**
     * A non-LTI activity is never a Raison exam, whatever the setting says.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_non_lti_activity_keeps_the_widget(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
        ]);
        $this->set_activity_page($course, $module, 'page');

        $this->assertSame([true, 'false'], $this->widget_placement($course));
    }

    /**
     * A site that has never saved the setting is protected on a real exam page.
     *
     * The helper test above proves the value reads as on; this proves the suppression actually
     * happens for it. Both are needed, because nothing writes the default: the phpunit database
     * is installed from scratch and so *does* hold '1', which means the absent path is never
     * exercised unless a test removes the value on purpose.
     *
     * @covers ::local_corolair_course_widget_placement
     * @covers ::local_corolair_hide_on_raison_exam
     * @return void
     */
    public function test_absent_setting_still_suppresses_the_widget(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'typeid' => $typeid,
        ]);
        $this->set_activity_page($course, $module, 'lti');
        unset_config('hideonraisonexam', 'local_corolair');

        $this->assertSame([false, 'false'], $this->widget_placement($course));
    }

    /**
     * A secure launch URL is recognised even when the plain one is not.
     *
     * lti_launch_tool() prefers securetoolurl over toolurl whenever the request is over SSL, so
     * an instance carrying an http toolurl and an https securetoolurl launches from the secure
     * one. Reading only toolurl would leave the assistant on that exam.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_secure_tool_url_is_recognised(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');
        set_config('ltitoolhost', environment::host('services'), 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'toolurl' => 'http://' . environment::host('services') . '/integration/lti/launch',
            'securetoolurl' => environment::url('services', 'integration/lti/launch'),
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assertSame([false, 'false'], $this->widget_placement($course));
    }

    /**
     * A tool reachable only over http is not a Raison tool.
     *
     * url_host() accepts https alone, which is what stops a look-alike launch URL on the right
     * host from passing. Asserted so the rule is recorded rather than incidental.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_plain_http_tool_is_not_recognised(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');
        set_config('ltitoolhost', environment::host('services'), 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'toolurl' => 'http://' . environment::host('services') . '/integration/lti/launch',
        ]);
        $this->set_activity_page($course, $module, 'lti');

        $this->assertSame([true, 'false'], $this->widget_placement($course));
    }

    /**
     * The course page keeps its assistant even when the course holds an exam.
     *
     * Guards the ordering in local_corolair_course_widget_placement(): the suppression is about
     * the page being viewed, not about the course containing an exam somewhere.
     *
     * @covers ::local_corolair_course_widget_placement
     * @return void
     */
    public function test_course_page_keeps_the_widget_when_the_course_holds_an_exam(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('hideonraisonexam', 1, 'local_corolair');

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $this->getDataGenerator()->create_module('lti', [
            'course' => $course->id,
            'section' => 1,
            'typeid' => $typeid,
        ]);
        $this->set_course_page($course, '/course/view.php');

        $this->assertSame([true, 'true'], $this->widget_placement($course));
    }

    /**
     * An identifier that resolves to nothing is answered, not thrown at.
     *
     * The never-throws contract is the whole reason is_raison_activity() reads without
     * MUST_EXIST, and it runs while a learner's page is being rendered.
     *
     * @covers \local_corolair\local\placement_registry::is_raison_activity
     * @return void
     */
    public function test_unknown_activity_is_not_raison(): void {
        $this->resetAfterTest();

        $this->assertFalse(\local_corolair\local\placement_registry::is_raison_activity(-1));
    }

    /**
     * The ownership row answers on its own, after the activity is gone.
     *
     * A teacher can delete the activity through the Moodle interface at any time, which leaves
     * the row behind. The first branch must not depend on the {lti} row still being there.
     *
     * @covers \local_corolair\local\placement_registry::is_raison_activity
     * @return void
     */
    public function test_ownership_row_outlives_the_activity(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->raison_tool_type();
        $created = \local_corolair\external\create_exam_placement::execute(
            (int)$course->id,
            (int)$this->section_id($course, 1),
            $typeid,
            'Final exam'
        );

        course_delete_module((int)$created['coursemoduleid']);

        $this->assertTrue(
            \local_corolair\local\placement_registry::is_raison_activity((int)$created['ltiinstanceid'])
        );
    }

    /**
     * Run the placement decision for the page $PAGE is currently on.
     *
     * @param \stdClass $course Course owning the page.
     * @return array{0: bool, 1: string} Whether to render, and the animate flag.
     */
    private function widget_placement(\stdClass $course): array {
        global $PAGE;

        return local_corolair_course_widget_placement($PAGE->url, (int)$course->id, $PAGE->cm);
    }

    /**
     * The {course_sections}.id of a section number in a course.
     *
     * @param \stdClass $course Course to look in.
     * @param int $number Section number.
     * @return int {course_sections}.id
     */
    private function section_id(\stdClass $course, int $number): int {
        global $DB;

        return (int)$DB->get_field('course_sections', 'id', [
            'course' => $course->id,
            'section' => $number,
        ], MUST_EXIST);
    }
}
