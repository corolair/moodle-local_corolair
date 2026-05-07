/**
 * Trainer redirect helpers.
 *
 * @module     local_corolair/trainer_redirect
 * @copyright  2025 Raison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const FALLBACK_ID = 'raison-fallback';
const CONTINUE_BUTTON_ID = 'raison-continue';
const REDIRECT_DELAY_MS = 500;

const redirectCurrentWindow = (continueUrl) => {
    window.location.assign(continueUrl);
};

const hideFallback = () => {
    const fallback = document.getElementById(FALLBACK_ID);
    if (fallback) {
        fallback.style.display = 'none';
    }
};

const bindContinueButton = (continueUrl) => {
    const continueButton = document.getElementById(CONTINUE_BUTTON_ID);
    if (!continueButton) {
        return;
    }

    continueButton.addEventListener('click', () => {
        window.setTimeout(() => {
            redirectCurrentWindow(continueUrl);
        }, REDIRECT_DELAY_MS);
    });
};

export const init = (targetUrl, continueUrl) => {
    const popup = window.open(targetUrl, '_blank');

    if (popup && !popup.closed && typeof popup.closed !== 'undefined') {
        hideFallback();
        redirectCurrentWindow(continueUrl);
        return;
    }

    bindContinueButton(continueUrl);
};
