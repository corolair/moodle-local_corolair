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
 * Tests for the widget policy.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair;

use local_corolair\local\widget_policy;

/**
 * Verifies which policy applies, and how the site's former settings are read and handed over.
 *
 * The policy decides whether the assistant appears during assessments, so every case where
 * nothing is known must resolve to hiding it on Raison exams.
 */
final class widget_policy_test extends \advanced_testcase {
    /** A policy as Raison would send it, already parsed. */
    private const RAISON = ['excludedactivities' => ['assign'], 'hideonraisonexam' => false];

    /**
     * Raison's policy applies once the site holds no former setting.
     *
     * @covers \local_corolair\local\widget_policy::effective
     * @return void
     */
    public function test_raison_policy_applies_without_former_settings(): void {
        $this->resetAfterTest();
        widget_policy::forget_legacy();

        $this->assertSame(self::RAISON, widget_policy::effective(self::RAISON));
    }

    /**
     * The site's former settings win until they are handed over, whatever Raison sends.
     *
     * @covers \local_corolair\local\widget_policy::effective
     * @covers \local_corolair\local\widget_policy::legacy_policy
     * @return void
     */
    public function test_former_settings_apply_until_handed_over(): void {
        $this->resetAfterTest();
        set_config(widget_policy::LEGACY_EXCLUDED_SETTING, 'quiz, forum', 'local_corolair');
        set_config(widget_policy::LEGACY_EXAM_SETTING, 1, 'local_corolair');

        $this->assertSame(
            ['excludedactivities' => ['quiz', 'forum'], 'hideonraisonexam' => true],
            widget_policy::effective(self::RAISON)
        );
    }

    /**
     * With neither a former setting nor a policy from Raison, exams hide the assistant.
     *
     * @covers \local_corolair\local\widget_policy::effective
     * @covers \local_corolair\local\widget_policy::defaults
     * @return void
     */
    public function test_nothing_known_hides_the_assistant_on_exams(): void {
        $this->resetAfterTest();
        widget_policy::forget_legacy();

        $this->assertSame(
            ['excludedactivities' => [], 'hideonraisonexam' => true],
            widget_policy::effective(null)
        );
    }

    /**
     * The former exam setting reads as it always did: absent means on, and so do odd values.
     *
     * get_config() returns false for a value never written, and the string 'false' casts to true;
     * both have to read the way an administrator meant them.
     *
     * @dataProvider former_exam_setting_provider
     * @covers \local_corolair\local\widget_policy::legacy_policy
     * @covers \local_corolair\local\widget_policy::handover
     * @param string|null $stored Stored value, or null for none.
     * @param bool $hidden Whether the assistant is hidden on exams.
     * @return void
     */
    public function test_former_exam_setting_reads_as_before(?string $stored, bool $hidden): void {
        $this->resetAfterTest();
        widget_policy::forget_legacy();
        // Keeps the former settings present when the exam one is not.
        set_config(widget_policy::LEGACY_EXCLUDED_SETTING, '', 'local_corolair');
        if ($stored !== null) {
            set_config(widget_policy::LEGACY_EXAM_SETTING, $stored, 'local_corolair');
        }

        $this->assertSame($hidden, widget_policy::legacy_policy()['hideonraisonexam']);
        // Handed over only when stored: an absent value is Raison's default, not this site's.
        $this->assertSame(
            $stored === null ? null : $hidden,
            widget_policy::handover()['hideOnRaisonExams'] ?? null
        );
    }

    /**
     * Stored values of the former exam setting, and what each means.
     *
     * @return array
     */
    public static function former_exam_setting_provider(): array {
        return [
            'never saved' => [null, true],
            'on' => ['1', true],
            'off' => ['0', false],
            'empty' => ['', false],
            'the string false' => ['false', false],
            'the string false, shouted and padded' => [' FALSE ', false],
        ];
    }

    /**
     * Only the former settings still stored are handed over, as Raison stores them.
     *
     * @covers \local_corolair\local\widget_policy::handover
     * @return void
     */
    public function test_handover_carries_what_is_stored(): void {
        $this->resetAfterTest();
        widget_policy::forget_legacy();
        $this->assertNull(widget_policy::handover());

        set_config(widget_policy::LEGACY_EXCLUDED_SETTING, 'Quiz, forum; quiz  assign', 'local_corolair');
        $this->assertSame(['excludedActivities' => ['quiz', 'assign']], widget_policy::handover());

        set_config(widget_policy::LEGACY_EXAM_SETTING, '0', 'local_corolair');
        $this->assertSame(
            ['excludedActivities' => ['quiz', 'assign'], 'hideOnRaisonExams' => false],
            widget_policy::handover()
        );
    }

    /**
     * Forgetting the former settings leaves Raison's policy in charge.
     *
     * @covers \local_corolair\local\widget_policy::forget_legacy
     * @return void
     */
    public function test_forgotten_former_settings_are_gone(): void {
        $this->resetAfterTest();
        set_config(widget_policy::LEGACY_EXCLUDED_SETTING, 'quiz', 'local_corolair');
        set_config(widget_policy::LEGACY_EXAM_SETTING, 0, 'local_corolair');

        widget_policy::forget_legacy();

        $this->assertNull(widget_policy::legacy_policy());
        $this->assertNull(widget_policy::handover());
        $this->assertSame(self::RAISON, widget_policy::effective(self::RAISON));
    }

    /**
     * The policy Raison sends is read, and its names cleaned the way Raison cleans them.
     *
     * @covers \local_corolair\local\widget_policy::parse
     * @return void
     */
    public function test_policy_from_raison_is_read(): void {
        $this->assertSame(
            ['excludedactivities' => ['quiz', 'hvp'], 'hideonraisonexam' => false],
            widget_policy::parse([
                'excludedActivities' => ['Quiz', 'hvp', 'mod/forum', 'quiz', 42],
                'hideOnRaisonExams' => false,
            ])
        );
    }

    /**
     * A malformed policy is no policy at all, rather than a guess.
     *
     * @dataProvider malformed_policy_provider
     * @covers \local_corolair\local\widget_policy::parse
     * @param mixed $value Decoded `widgetPolicy` field.
     * @return void
     */
    public function test_malformed_policy_is_none($value): void {
        $this->assertNull(widget_policy::parse($value));
    }

    /**
     * Malformed `widgetPolicy` fields.
     *
     * @return array
     */
    public static function malformed_policy_provider(): array {
        return [
            'absent' => [null],
            'a string' => ['quiz'],
            'no exam setting' => [['excludedActivities' => []]],
            'an exam setting that is not a boolean' => [['excludedActivities' => [], 'hideOnRaisonExams' => 'false']],
            'no activity list' => [['hideOnRaisonExams' => true]],
            'an activity list that is not a list' => [['excludedActivities' => 'quiz', 'hideOnRaisonExams' => true]],
        ];
    }

    /**
     * Activity names are bounded and validated, as Raison does.
     *
     * @covers \local_corolair\local\widget_policy::normalize_activity_names
     * @return void
     */
    public function test_activity_names_are_bounded(): void {
        $names = array_map(static fn(int $index): string => 'mod' . $index, range(1, 150));

        $this->assertCount(100, widget_policy::normalize_activity_names($names));
        $this->assertSame([], widget_policy::normalize_activity_names([str_repeat('a', 65)]));
        $this->assertSame([], widget_policy::normalize_activity_names(['_quiz', '1quiz', '']));
        $this->assertSame([], widget_policy::normalize_activity_names(42));
    }
}
