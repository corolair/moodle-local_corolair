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
 * Access to the configured Raison API key.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Reads the API key, treating the historical placeholder values as "not configured".
 *
 * settings.php seeds local_corolair/apikey with the *translated* "noapikey" string
 * rather than an empty value, so an unconfigured site stores human-readable text where
 * a credential is expected. Every consumer therefore has to recognise those placeholders
 * before using the value as a bearer token. That test used to be copy-pasted verbatim in
 * several classes; it lives here instead so there is one list to maintain.
 *
 * The prefixes cover both the current "Raison" wording and the earlier "Corolair" wording
 * in each shipped language. Adding a language to lang/ without adding its placeholder here
 * would let that placeholder be sent as a real credential -- the reason this whole sentinel
 * approach is scheduled to be replaced by an empty default.
 */
final class api_key {
    /** @var string[] Placeholders that mean "no API key has been issued yet". */
    private const PLACEHOLDER_PREFIXES = [
        'No Corolair Api Key',
        'Aucune Clé API Corolair',
        'No hay clave API de Corolair',
        'No Raison Api Key',
        'Aucune Clé API Raison',
        'No hay clave API de Raison',
    ];

    /**
     * Return the configured API key, or null when none has been issued.
     *
     * @return string|null The key, or null when unset or still a placeholder.
     */
    public static function get(): ?string {
        $apikey = (string)get_config('local_corolair', 'apikey');
        if ($apikey === '') {
            return null;
        }
        foreach (self::PLACEHOLDER_PREFIXES as $placeholder) {
            if (strpos($apikey, $placeholder) === 0) {
                return null;
            }
        }
        return $apikey;
    }

    /**
     * Whether a usable API key is configured.
     *
     * @return bool True when the site holds a real key.
     */
    public static function is_configured(): bool {
        return self::get() !== null;
    }
}
