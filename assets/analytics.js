import 'vanilla-cookieconsent/dist/cookieconsent.css';
import * as CookieConsent from 'vanilla-cookieconsent';

const configElement = document.getElementById('analytics-config');

if (configElement) {
    const config = JSON.parse(configElement.textContent);
    let analyticsEnabled = false;
    let choiceSequence = 0;
    let choiceQueue = Promise.resolve();
    let savedChoice = null;
    let trackedPage = null;

    const post = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
            keepalive: true,
        });

        if (!response.ok) {
            throw new Error(`Analytics request failed with ${response.status}.`);
        }
    };

    const track = async (name, properties = {}) => {
        if (!analyticsEnabled) {
            return;
        }

        try {
            await post(config.eventEndpoint, {name, properties});
        } catch {
            // Analytics must never interrupt the action being measured.
        }
    };

    const trackPageView = async (sequence) => {
        const pageKey = `${savedChoice}:${config.route}`;
        if (!analyticsEnabled || trackedPage === pageKey || sequence !== choiceSequence) {
            return;
        }

        trackedPage = pageKey;
        await track('page_view', {
            locale: document.documentElement.lang,
            route: config.route,
        });
    };

    const synchronizeConsent = (cookie, saveChoice) => {
        const allowed = CookieConsent.acceptedCategory('analytics');
        const choice = `${cookie.consentId}:${cookie.revision}:${allowed}`;
        const sequence = ++choiceSequence;

        analyticsEnabled = false;
        choiceQueue = choiceQueue.then(async () => {
            if (saveChoice && savedChoice !== choice) {
                await post(config.consentEndpoint, {
                    consentId: cookie.consentId,
                    analyticsAllowed: allowed,
                    revision: cookie.revision,
                });
                savedChoice = choice;
            }

            if (sequence !== choiceSequence) {
                return;
            }

            analyticsEnabled = allowed;
            await trackPageView(sequence);
        }).catch(() => {
            analyticsEnabled = false;
        });
    };

    CookieConsent.run({
        mode: 'opt-in',
        hideFromBots: false,
        revision: config.revision,
        cookie: {
            name: 'bb_consent',
            expiresAfterDays: config.retentionDays,
            sameSite: 'Lax',
        },
        guiOptions: {
            consentModal: {
                layout: 'box',
                position: 'bottom left',
                equalWeightButtons: true,
            },
            preferencesModal: {
                layout: 'bar wide',
                position: 'left',
                equalWeightButtons: true,
            },
        },
        categories: {
            necessary: {
                enabled: true,
                readOnly: true,
            },
            analytics: {
                enabled: false,
            },
        },
        onFirstConsent: ({cookie}) => synchronizeConsent(cookie, true),
        onConsent: ({cookie}) => synchronizeConsent(cookie, false),
        onChange: ({cookie, changedCategories}) => {
            if (changedCategories.includes('analytics')) {
                synchronizeConsent(cookie, true);
            }
        },
        language: {
            default: 'ro',
            autoDetect: 'document',
            translations: config.translations,
        },
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const element = event.target.closest('[data-analytics-event]');
        if (!element) {
            return;
        }

        const properties = {
            locale: document.documentElement.lang,
        };
        if (element.dataset.analyticsValue) {
            properties.value = element.dataset.analyticsValue;
        }

        void track(element.dataset.analyticsEvent, properties);
    });
}
