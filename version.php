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
 * Version information for the Corolair plugin.
 *
 * This file defines the version and other metadata for the "local_corolair" plugin.
 * It ensures compatibility and proper registration with Moodle.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$plugin->component = 'local_corolair';    // Full name of the plugin (used for diagnostics).
$plugin->version   = 2025051200;          // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2020110900;          // Minimum required Moodle version.
$plugin->maturity  = MATURITY_STABLE;     // Plugin maturity level: MATURITY_ALPHA, MATURITY_BETA, MATURITY_RC, MATURITY_STABLE.
$plugin->release   = '1.8.6';             // Human-readable version name.
