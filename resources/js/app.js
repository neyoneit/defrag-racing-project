import './bootstrap';
import '../css/app.css';
import '../css/items.css';




// Auto-reload on stale chunk after deploy
window.addEventListener('vite:preloadError', () => {
    const lastReload = sessionStorage.getItem('chunk_reload');
    if (!lastReload || Date.now() - Number(lastReload) > 10000) {
        sessionStorage.setItem('chunk_reload', Date.now());
        window.location.reload();
    }
});

// Auto-refresh page on expired CSRF token (419 response) - max once per page load
let csrfReloaded = false;
const originalFetch = window.fetch;
window.fetch = async (...args) => {
    const response = await originalFetch(...args);
    if (response.status === 419 && !csrfReloaded) {
        csrfReloaded = true;
        window.location.reload();
    }
    return response;
};

import { createApp, h } from 'vue';
import { reactive } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import Popper from "vue3-popper";
import moment from 'moment-timezone';

import MainLayout from "@/Layouts/MainLayout.vue" 

import { createVuetify } from 'vuetify'

const appName = import.meta.env.VITE_APP_NAME || 'Defrag Racing';

// Lives in utils/time.js now, because the separator before the milliseconds
// is a user preference and every place that prints a run time has to agree
// on it.
import { formatTime, setTimeFormat } from './utils/time';

// Translation. English strings stay written in the templates and a language
// file overrides the ones it has - see utils/i18n.js.
import { t, tChoice, setLocale } from './utils/i18n';

const q3tohtml = (name) => {
    if (!name) return '';
    let result = '';
    let color = '7';
    let buffer = '';

    const escapeHtml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const flush = () => {
        if (buffer) {
            result += `<span class="q3c-${color}">${escapeHtml(buffer)}</span>`;
            buffer = '';
        }
    };

    for (let i = 0; i < name.length; i++) {
        if (name[i] == '^') {
            if (name[i + 1] == '^') {
                buffer += '^';
            } else {
                flush();
                color = name[i + 1];
                i++;
            }
        } else {
            buffer += name[i];
        }
    }
    flush();

    return result;
};

const timeSince = (date) => {
    const currentDate = moment.tz();
    const inputDate = moment.tz(date, "Europe/Berlin");
    const duration = moment.duration(currentDate.diff(inputDate));

    if (duration.asDays() < 1) {
        if (duration.hours() == 0) {
            return tChoice(':count minute|:count minutes', duration.minutes());
        }

        return [
            tChoice(':count hour|:count hours', duration.hours()),
            tChoice(':count minute|:count minutes', duration.minutes()),
        ].join(', ');
    } else if (duration.asDays() < 365) {
        const months = duration.months();
        const weeks = duration.weeks();
        const days = duration.days() % 7;

        // Joined rather than appended: the old version left a trailing ", "
        // behind whenever the smaller unit came out at zero.
        const parts = [];

        if (months > 0) {
            parts.push(tChoice(':count month|:count months', months));
        }

        if (weeks > 0) {
            parts.push(tChoice(':count week|:count weeks', weeks));
        }

        if (days > 0) {
            parts.push(tChoice(':count day|:count days', days));
        }

        return parts.join(', ');
    } else {
        return tChoice(':count year|:count years', duration.years());
    }
}

const vuetify = createVuetify({})

// Frontend error logger - sends errors to backend for admin review
const logFrontendError = (data) => {
    try {
        fetch('/api/frontend-errors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                ...data,
                url: window.location.href,
            }),
            keepalive: true,
        }).catch(() => {});
    } catch (e) {
        // Silently fail - don't cause more errors
    }
};

// Catch unhandled JS errors
window.addEventListener('error', (event) => {
    logFrontendError({
        type: 'js_error',
        message: event.message || 'Unknown error',
        stack: event.error?.stack || null,
        component: event.filename || null,
    });
});

// Catch unhandled promise rejections (skip axios errors - already logged by interceptor)
window.addEventListener('unhandledrejection', (event) => {
    if (event.reason?.isAxiosError) return;
    const message = event.reason?.message || event.reason?.toString() || 'Unhandled promise rejection';
    logFrontendError({
        type: 'js_error',
        message: message,
        stack: event.reason?.stack || null,
    });
});

// The language file has to be in memory before the first render, or every
// page would flash English and then re-render. `<html lang>` is written
// server-side by the root template, so the locale is known here without
// waiting for Inertia's props.
setLocale(document.documentElement.lang || 'en').then(() => createInertiaApp({
    title: (title) => `${title} - Defrag Racing`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))

        // Only apply MainLayout if layout is undefined (not null)
        if (page.default.layout === undefined) {
            page.default.layout = MainLayout
        }

        return page
    },
    setup({ el, App, props, plugin }) {
        // Applied before the first render, and again on every visit, because
        // saving the preference comes back as an ordinary Inertia response
        // carrying the new shared props.
        setTimeFormat(props.initialPage.props.timeFormat);
        router.on('success', (event) => {
            setTimeFormat(event.detail.page.props.timeFormat);
            // Switching the language comes back as an ordinary Inertia
            // response too. `messages` is a ref, so the swap re-renders what
            // is on screen instead of waiting for a full page load.
            setLocale(event.detail.page.props.locale);
        });

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify);

        app.component("Popper", Popper);

        app.config.globalProperties.formatTime = formatTime

        app.config.globalProperties.q3tohtml = q3tohtml

        app.config.globalProperties.timeSince = timeSince

        app.config.globalProperties.$t = t

        app.config.globalProperties.$tc = tChoice

        app.config.globalProperties.$state = reactive({
            globalBackgroundImage: '/images/bg-image.png'
        })

        // Vue error handler
        app.config.errorHandler = (err, instance, info) => {
            console.error('Vue error:', err);
            logFrontendError({
                type: 'vue_error',
                message: err?.message || 'Vue error',
                stack: err?.stack || null,
                component: instance?.$options?.name || instance?.$options?.__name || info || null,
            });
        };

        // Global image error handler: fallback for missing profile photos and thumbnails
        document.addEventListener('error', (e) => {
            if (e.target.tagName === 'IMG' && !e.target.dataset.fallbackApplied) {
                const src = e.target.src || '';
                e.target.dataset.fallbackApplied = 'true';
                if (src.includes('/storage/profile-photos/')) {
                    e.target.src = '/images/null.jpg';
                } else if (src.includes('/storage/thumbs/')) {
                    e.target.src = '/images/unknown.jpg';
                }
            }
        }, true);

        app.mount(el);

        return app;
    },
    progress: {
        color: '#2d85ff'
    },
}));
