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
 * Need Savy module for handling Savy chatbot initialization.
 *
 * @module     theme_moove/need_savy
 * @copyright  2025 Patrick Thibaudeau
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

// Protect against spam.
let savyInitializedAlready = false;

/**
 * Inject the Savy script into the page.
 *
 * @param {Object} response - The response from the API
 * @param {string} response.script - The script content to inject
 * @param {string} response.botId - The bot ID
 */
const injectsavyScript = (response) => {
    const container = document.querySelector("#savy-container");

    // Validate response
    if (!response || !response.script) {
        console.error('Invalid Savy response:', response);
        alert('Failed to initialize Savy: Invalid response from server');
        return;
    }

    // Check if script contains HTML (error response)
    if (response.script.trim().startsWith('<')) {
        console.error('Savy returned HTML instead of JavaScript:', response.script.substring(0, 200));
        alert('Failed to initialize Savy: Server returned an error. Please check the console and verify your Savy settings.');
        return;
    }

    const embedScript = document.createElement("script");
    embedScript.type = 'text/javascript';

    try {
        embedScript.text = response.script;
        container.appendChild(embedScript);

        // Wait a moment for script to execute
        setTimeout(() => {
            if (window.CRIA && window.CRIA[response.botId]) {
                window.SAVY = window.CRIA[response.botId];
                injectMutationObserver();
                addSavyClickListener();
            } else {
                console.error('CRIA object not found after script execution');
                alert('Failed to initialize Savy: CRIA not loaded. Please verify your Savy configuration.');
            }
        }, 100);
    } catch (error) {
        console.error('Error injecting Savy script:', error);
        alert('Failed to initialize Savy: ' + error.message);
    }
};

/**
 * Add click listener to the Savy button.
 */
const addSavyClickListener = () => {
    const savyBtn = document.querySelector('#savy-btn');

    if (!savyBtn) {
        return;
    }

    // Add the listener.
    savyBtn.addEventListener('click', () => {
        // Check if SAVY is initialized, if not wait a bit and try again.
        if (window.SAVY) {
            window.SAVY.switch();
        } else {
            // Wait a bit for initialization to complete.
            setTimeout(() => {
                if (window.SAVY) {
                    window.SAVY.switch();
                }
            }, 1000);
        }

        // Click the popover to hide it.
        const popover = document.querySelector(".btn-footer-popover");
        if (popover) {
            popover.click();
        }
    });
};

/**
 * The popover deletes the HTML for the savy btn, so we use a mutation-observer to re-add it each time.
 */
const injectMutationObserver = () => {
    const targetNode = document.body;

    // Options for the observer (which mutations to observe).
    const config = {childList: true, subtree: true};

    // Callback function to execute when mutations are observed.
    const callback = (mutationsList) => {
        for (const mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.id === 'savy-btn' || (node.querySelector && node.querySelector('#savy-btn'))) {
                        addSavyClickListener();
                    }
                });
            }
        }
    };

    // Create an instance of the observer with the callback function.
    const observer = new MutationObserver(callback);

    // Start observing the target node for configured mutations.
    observer.observe(targetNode, config);
};

/**
 * Initialize Savy by calling the web service.
 */
const initsavy = () => {
    if (savyInitializedAlready) {
        return;
    }

    savyInitializedAlready = true;

    const request = {
        methodname: "theme_moove_launchsavy",
        args: {}
    };

    // Make call & reload.
    Ajax.call([request])[0]
        .then((response) => {
            injectsavyScript(response);
        })
        .catch((error) => {
            // eslint-disable-next-line no-console
            console.error('Failed to initialize Savy:', error);
        });
};

/**
 * Initialize the module.
 *
 * @returns {void}
 */
export const init = () => {
    window.initsavy = initsavy;
};

