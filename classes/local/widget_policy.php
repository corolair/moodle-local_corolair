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
 * Where the AI tutor widget may appear on this site.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * The widget policy: which activity types and whether Raison exams keep the widget off.
 *
 * Until 1.9.20 these were two settings on this plugin's own page, "Excluded activities" and
 * "Hide the AI Tutor during Raison exams". They are now set per organization in Raison
 * (Organization, Integrations, Moodle, Settings) and arrive with the course list that
 * widget_course_list already caches, so the plugin still enforces them itself, before anything
 * about a learner is sent. Only Moodle can: Raison sees page URLs, not which external tool is an
 * exam (see placement_registry::is_raison_activity()).
 *
 * A site upgraded from an earlier version keeps applying its own stored values until Raison has
 * taken them over. The course list request hands them over, Raison imports each one the
 * organization holds no value for yet, and only once it answers that they are stored are the
 * local values deleted. Until then -- an older backend, a failed import -- the site behaves
 * exactly as it did before the upgrade.
 *
 * A policy is an array with two keys: 'excludedactivities' (activity module short names) and
 * 'hideonraisonexam' (bool).
 */
final class widget_policy {
    /** Former setting: comma- or space-separated activity module short names. */
    public const LEGACY_EXCLUDED_SETTING = 'excludedmods';

    /** Former setting: whether the widget stays off Raison exam pages. */
    public const LEGACY_EXAM_SETTING = 'hideonraisonexam';

    /** Most activity names kept, matching the bound Raison applies. */
    private const MAX_ACTIVITIES = 100;

    /** An activity module short name, as Raison validates it (normalizeMoodleActivityNames). */
    private const ACTIVITY_NAME = '/^[a-z][a-z0-9_]{0,63}$/';

    /**
     * The policy to enforce.
     *
     * @param array|null $fromraison The policy Raison sent with the course list, or null when
     *      there is none: an older backend, a first fetch that failed, or a missing route.
     * @return array The site's former settings while they are not yet handed over, else what
     *      Raison sent, else the defaults.
     */
    public static function effective(?array $fromraison): array {
        return self::legacy_policy() ?? $fromraison ?? self::defaults();
    }

    /**
     * The policy when nothing says otherwise: no activity excluded, Raison exams hidden.
     *
     * Hidden because the setting guards assessment integrity: not knowing must never be what
     * puts the tutor on an exam page.
     *
     * @return array
     */
    public static function defaults(): array {
        return ['excludedactivities' => [], 'hideonraisonexam' => true];
    }

    /**
     * The former settings as a policy, applied exactly as before 1.9.20, or null once neither
     * is stored any more.
     *
     * @return array|null
     */
    public static function legacy_policy(): ?array {
        $excluded = get_config('local_corolair', self::LEGACY_EXCLUDED_SETTING);
        $exam = get_config('local_corolair', self::LEGACY_EXAM_SETTING);
        if ($excluded === false && $exam === false) {
            return null;
        }
        return [
            // Every entry, as the former setting matched them; only the hand-over normalises.
            'excludedactivities' => array_values(array_filter(
                array_map('trim', preg_split('/[,\s]+/', (string)$excluded)),
                static fn(string $name): bool => $name !== ''
            )),
            'hideonraisonexam' => self::read_legacy_exam_setting($exam),
        ];
    }

    /**
     * What the course list request hands over to Raison: the former settings still stored.
     *
     * @return array|null The `legacyWidgetPolicy` body field, or null when there is nothing left
     *      to hand over.
     */
    public static function handover(): ?array {
        $payload = [];
        $excluded = get_config('local_corolair', self::LEGACY_EXCLUDED_SETTING);
        if ($excluded !== false) {
            $payload['excludedActivities'] = self::normalize_activity_names((string)$excluded);
        }
        $exam = get_config('local_corolair', self::LEGACY_EXAM_SETTING);
        if ($exam !== false) {
            $payload['hideOnRaisonExams'] = self::read_legacy_exam_setting($exam);
        }
        return $payload === [] ? null : $payload;
    }

    /**
     * Delete the former settings, once Raison has confirmed it holds a value for each.
     *
     * @return void
     */
    public static function forget_legacy(): void {
        unset_config(self::LEGACY_EXCLUDED_SETTING, 'local_corolair');
        unset_config(self::LEGACY_EXAM_SETTING, 'local_corolair');
    }

    /**
     * Read the `widgetPolicy` of a course list response.
     *
     * @param mixed $value The decoded field.
     * @return array|null The policy, or null when it is absent or malformed.
     */
    public static function parse($value): ?array {
        if (!is_array($value)) {
            return null;
        }
        $excluded = $value['excludedActivities'] ?? null;
        $hide = $value['hideOnRaisonExams'] ?? null;
        if (!is_array($excluded) || !is_bool($hide)) {
            return null;
        }
        return [
            'excludedactivities' => self::normalize_activity_names($excluded),
            'hideonraisonexam' => $hide,
        ];
    }

    /**
     * Activity names as Raison stores them: lower case, valid, each once, in order.
     *
     * @param string|array $value Comma- or space-separated text, or a list.
     * @return string[]
     */
    public static function normalize_activity_names($value): array {
        $entries = is_string($value) ? preg_split('/[,\s]+/', $value) : (is_array($value) ? $value : []);
        $names = [];
        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $name = strtolower(trim($entry));
            if (preg_match(self::ACTIVITY_NAME, $name)) {
                $names[$name] = $name;
            }
            if (count($names) === self::MAX_ACTIVITIES) {
                break;
            }
        }
        return array_values($names);
    }

    /**
     * Read the former exam setting by hand rather than by cast, because two things look false.
     *
     * Absence: get_config() returns false for a value never written, and nothing in an install or
     * upgrade wrote this one, so a site that never saved the settings page holds nothing. Absent
     * has to mean on. And the string 'false', which (bool) reads as true: an administrator pinning
     * the setting through $CFG->forced_plugin_settings had every reason to write it and mean it.
     *
     * @param mixed $configured The stored value, or false when there is none.
     * @return bool
     */
    private static function read_legacy_exam_setting($configured): bool {
        if ($configured === false) {
            return true;
        }
        return !in_array(strtolower(trim((string)$configured)), ['0', '', 'false'], true);
    }
}
