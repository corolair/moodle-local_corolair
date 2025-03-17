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
 * Renderer class for the local_corolair plugin.
 *
 * This class extends the plugin_renderer_base and provides methods to render
 * custom templates for the local_corolair plugin.
 *
 * @package    local_corolair
 * @copyright  2024 Corolair
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\output;

use plugin_renderer_base;

/**
 * Class renderer
 *
 * This class is responsible for rendering templates for the local_corolair plugin.
 */
class renderer extends plugin_renderer_base {

    /**
     * Renders the embed script template.
     *
     * This method prepares the data and renders the 'local_corolair/embed_script' template.
     *
     * @param string $sidepanel Whether to embed as a side panel.
     * @param bool $animate Whether to animate the embed script.
     * @param string $moodleoptions The Moodle options.
     * @return string The rendered template.
     */
    public function render_embed_script($sidepanel, $animate, $moodleoptions) {
        $data = [
            'sidepanel' => htmlspecialchars($sidepanel, ENT_QUOTES, 'UTF-8'),
            'animate' => $animate,
            'moodleoptions' => $moodleoptions,
        ];
        return $this->render_from_template('local_corolair/embed_script', $data);
    }

    /**
     * Renders the trainer template with the provided user, provider, and course data.
     *
     * @param int $userid The ID of the user.
     * @param string $provider The name of the provider.
     * @param int $courseid The ID of the course.
     * @return string The rendered HTML content.
     */
    public function render_trainer($userid, $provider, $courseid) {
        $data = [
            'userid' => htmlspecialchars($userid, ENT_QUOTES, 'UTF-8'),
            'provider' => htmlspecialchars($provider, ENT_QUOTES, 'UTF-8'),
            'courseid' => htmlspecialchars($courseid, ENT_QUOTES, 'UTF-8'),
        ];
        return $this->render_from_template('local_corolair/trainer', $data);
    }

    /**
     * Renders the installation troubleshoot template with the provided site data.
     *
     * @param string $siteurl The URL of the site.
     * @param string $sitename The name of the site.
     * @param bool $iswebserviceenabled Whether the web service is enabled.
     * @param bool $isrestprotocolenabled Whether the REST protocol is enabled.
     * @param bool $iscorolairserviceexist Whether the Corolair service exists.
     * @param bool $istokenexist Whether the token exists.
     * @param string $useremail The email of the user.
     * @param string $userfirstname The first name of the user.
     * @param string $userlastname The last name of the user.
     * @param string $tokenvalue The token value.
     * @return string The rendered HTML content.
     */
    public function render_installation_troubleshoot(
        $siteurl,
        $sitename,
        $iswebserviceenabled,
        $isrestprotocolenabled,
        $iscorolairserviceexist,
        $istokenexist,
        $useremail,
        $userfirstname,
        $userlastname,
        $tokenvalue
    ) {
        $iswebserviceenabledstring = 'false';
        if ($iswebserviceenabled) {
            $iswebserviceenabledstring = 'true';
        }
        $isrestprotocolenabledstring = 'false';
        if ($isrestprotocolenabled) {
            $isrestprotocolenabledstring = 'true';
        }
        $iscorolairserviceexiststring = 'false';
        if ($iscorolairserviceexist) {
            $iscorolairserviceexiststring = 'true';
        }
        $istokenexiststring = 'false';
        if ($istokenexist) {
            $istokenexiststring = 'true';
        }
        $data = [
            'siteUrl' => htmlspecialchars($siteurl, ENT_QUOTES, 'UTF-8'),
            'siteName' => htmlspecialchars($sitename, ENT_QUOTES, 'UTF-8'),
            'isWebServiceEnabled' => $iswebserviceenabledstring,
            'isRestProtocolEnabled' => $isrestprotocolenabledstring,
            'isCorolairServiceExist' => $iscorolairserviceexiststring,
            'isTokenExist' => $istokenexiststring,
            'userEmail' => $useremail,
            'userFirstname' => $userfirstname,
            'userLastname' => $userlastname,
            'tokenValue' => $tokenvalue,
        ];
        return $this->render_from_template('local_corolair/installation_troubleshoot', $data);
    }
}
