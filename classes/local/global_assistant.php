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
 * Where the organization's global assistant may appear on this site.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * The page rules of the global assistant that do not depend on who is looking.
 *
 * The global assistant is the organization's assistant that sends each question to the right
 * course assistant. Raison decides almost everything about it: whether the organization switched
 * it on, which pages it stays off, and who may use it. It sends the first two with the course
 * list (see widget_course_list), as a `globalAssistant` field that is null while the assistant is
 * off, and answers the third per person (see global_assistant_session).
 *
 * What stays here is what Raison cannot see or must not decide: matching this page against the
 * pages Raison excludes, and the cases where nothing may be sent at all whatever Raison says --
 * someone Moodle is still holding back (site policy, forced password change, unfinished profile),
 * a page shown in a popup or a frame, and this plugin's own pages, which already embed Raison.
 */
final class global_assistant {
    /** Most excluded-page patterns kept from what Raison sends. */
    private const MAX_PATTERNS = 200;

    /** Longest excluded-page pattern kept. */
    private const MAX_PATTERN_LENGTH = 255;

    /**
     * Page layouts that never show it: small windows, frames inside other pages, printouts,
     * and the pages Moodle shows before or instead of the site itself.
     */
    private const EXCLUDED_LAYOUTS = [
        'embedded',
        'frametop',
        'login',
        'maintenance',
        'popup',
        'print',
        'redirect',
        'secure',
    ];

    /** Path prefix of this plugin's own pages, which embed Raison already. */
    private const OWN_PAGES = '/local/corolair/';

    /**
     * Read the `globalAssistant` field of a course list response.
     *
     * Null, absent or malformed all mean off: a backend that predates the global assistant sends
     * nothing, and nothing must be what keeps it off.
     *
     * @param mixed $value The decoded field.
     * @return array|null ['excludedpages' => string[]] while it is on, null while it is off.
     */
    public static function parse($value): ?array {
        if (!is_array($value)) {
            return null;
        }
        $pages = $value['excludedPages'] ?? [];
        if (!is_array($pages)) {
            return null;
        }
        return ['excludedpages' => self::normalize_patterns($pages)];
    }

    /**
     * Excluded-page patterns as they are matched: trimmed strings, each once, in order.
     *
     * @param array $entries Patterns as Raison sent them.
     * @return string[]
     */
    public static function normalize_patterns(array $entries): array {
        $patterns = [];
        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $pattern = trim($entry);
            if ($pattern === '' || strlen($pattern) > self::MAX_PATTERN_LENGTH) {
                continue;
            }
            $patterns[$pattern] = $pattern;
            if (count($patterns) === self::MAX_PATTERNS) {
                break;
            }
        }
        return array_values($patterns);
    }

    /**
     * Whether one of Raison's patterns keeps the global assistant off this page.
     *
     * A pattern starting with "/" is a path below the site root, as in /my/courses.php; anything
     * else is a Moodle page type, as in my-index. Either matches exactly, or as a prefix when it
     * ends with "*": admin-* is every administration page. Paths are what tell apart two pages
     * Moodle gives one page type, such as the dashboard and My courses.
     *
     * @param string $pagetype The page's type, from $PAGE->pagetype.
     * @param string $path The page's path below the site root, from local_path().
     * @param string[] $patterns The patterns, from parse().
     * @return bool
     */
    public static function page_excluded(string $pagetype, string $path, array $patterns): bool {
        foreach ($patterns as $pattern) {
            $subject = $pattern[0] === '/' ? $path : $pagetype;
            if ($subject === '') {
                continue;
            }
            if (substr($pattern, -1) === '*') {
                if (strpos($subject, substr($pattern, 0, -1)) === 0) {
                    return true;
                }
            } else if ($subject === $pattern) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the page layout leaves no room for, or no place to, a floating assistant.
     *
     * @param string $layout The page's layout, from $PAGE->pagelayout.
     * @return bool
     */
    public static function layout_excluded(string $layout): bool {
        return in_array($layout, self::EXCLUDED_LAYOUTS, true);
    }

    /**
     * Whether the path is one of this plugin's own pages.
     *
     * @param string $path The page's path below the site root, from local_path().
     * @return bool
     */
    public static function own_page(string $path): bool {
        return strpos($path, self::OWN_PAGES) === 0;
    }

    /**
     * Whether Moodle is still holding this person back from the site.
     *
     * The same three checks require_login() makes before it lets anyone in: a site policy not
     * yet accepted, a forced password change, a profile missing what the site requires. On the
     * pages that resolve them nothing about the person may go to Raison -- above all not before
     * they accepted the site's policies.
     *
     * @param \stdClass $user The person viewing the page.
     * @return bool
     */
    public static function person_must_act_first(\stdClass $user): bool {
        // Only when it is really them, as require_login() decides.
        if (get_user_preferences('auth_forcepasswordchange', false, $user) && !\core\session\manager::is_loggedinas()) {
            return true;
        }
        if (user_not_fully_set_up($user, true)) {
            return true;
        }
        if (empty($user->policyagreed) && !is_siteadmin($user) && ($user->auth ?? '') !== 'webservice') {
            $manager = new \core_privacy\local\sitepolicy\manager();
            if ($manager->get_redirect_url(false) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * The page's path below the site root, without its query: /my/courses.php.
     *
     * Moodle can live in a subdirectory, where the URL's own path starts with it; patterns are
     * written against the site root, so the path is taken relative to it.
     *
     * @param \moodle_url $url The page's URL.
     * @return string The path, or an empty string when the URL is not on this site.
     */
    public static function local_path(\moodle_url $url): string {
        try {
            $local = $url->out_as_local_url(false);
        } catch (\coding_exception $exception) {
            return '';
        }
        $path = parse_url($local, PHP_URL_PATH);
        return is_string($path) ? $path : '';
    }
}
