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
 * @copyright  2025 Raison
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
     * Render the versioned pre-consent disclosure.
     *
     * @param array $data Disclosure template context.
     * @return string
     */
    public function render_setup_disclosure(array $data): string {
        return $this->render_from_template('local_corolair/setup_disclosure', $data);
    }

    /**
     * Renders the embed script template.
     *
     * This method prepares the data and renders the 'local_corolair/embed_script' template.
     *
     * @param string $sidepanel Whether to embed as a side panel.
     * @param bool $animate Whether to animate the embed script.
     * @param string $datausertoken The short-lived Moodle widget session token.
     * @return string The rendered template.
     */
    public function render_embed_script($sidepanel, $animate, $datausertoken) {
        $data = [
            'sidepanel' => htmlspecialchars($sidepanel, ENT_QUOTES, 'UTF-8'),
            'animate' => $animate,
            'datausertoken' => $datausertoken,
        ];
        return $this->render_from_template('local_corolair/embed_script', $data);
    }

    /**
     * Renders the installation troubleshoot template with the provided site data.
     *
     * Only non-personal technical status flags are forwarded to the external diagnostic
     * page. Personal data (operator email/name) and site identifiers are intentionally
     * excluded from the cross-origin URL to avoid leaking them into browser history,
     * proxies, and provider logs.
     *
     * @param bool $iswebserviceenabled Whether the web service is enabled.
     * @param bool $isrestprotocolenabled Whether the REST protocol is enabled.
     * @param bool $israisonserviceexist Whether the Raison service exists.
     * @param bool $istokenexist Whether the token exists.
     * @return string The rendered HTML content.
     */
    public function render_installation_troubleshoot(
        $iswebserviceenabled,
        $isrestprotocolenabled,
        $israisonserviceexist,
        $istokenexist
    ) {
        $iswebserviceenabledstring = 'false';
        if ($iswebserviceenabled) {
            $iswebserviceenabledstring = 'true';
        }
        $isrestprotocolenabledstring = 'false';
        if ($isrestprotocolenabled) {
            $isrestprotocolenabledstring = 'true';
        }
        $israisonserviceexiststring = 'false';
        if ($israisonserviceexist) {
            $israisonserviceexiststring = 'true';
        }
        $istokenexiststring = 'false';
        if ($istokenexist) {
            $istokenexiststring = 'true';
        }
        $troubleshooturl = new \moodle_url('https://share.raison.is/troubleshoot/moodle', [
            'isWebServiceEnabled' => $iswebserviceenabledstring,
            'isRestProtocolEnabled' => $isrestprotocolenabledstring,
            'isCorolairServiceExist' => $israisonserviceexiststring,
            'isTokenExist' => $istokenexiststring,
        ]);
        $data = [
            'troubleshootUrl' => $troubleshooturl->out(false),
        ];
        return $this->render_from_template('local_corolair/installation_troubleshoot', $data);
    }

    /**
     * Renders the demo template.
     *
     * @return string The rendered HTML content.
     */
    public function render_demo() {
        return $this->render_from_template('local_corolair/demo', []);
    }
}
