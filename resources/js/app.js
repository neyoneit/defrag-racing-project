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

import { createApp, h } from 'vue';
import { reactive } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import Popper from "vue3-popper";
import moment from 'moment-timezone';

import MainLayout from "@/Layouts/MainLayout.vue" 

import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

import CKEditor from '@ckeditor/ckeditor5-vue';

const appName = import.meta.env.VITE_APP_NAME || 'Defrag Racing';

const formatTime = (milliseconds) => {
    milliseconds = Math.max(0, milliseconds);
  
    const hours = Math.floor(milliseconds / 3600000);
    milliseconds %= 3600000;
    const minutes = Math.floor(milliseconds / 60000);
    milliseconds %= 60000;
    const seconds = Math.floor(milliseconds / 1000);
    milliseconds %= 1000;
  
    let formattedTime = '';
  
    if (hours > 0) {
      formattedTime += `${hours}:`;
    }
  
    if (minutes > 0 || hours > 0) {
      formattedTime += `${padZero(minutes)}:`;
    }
  
    formattedTime += `${padZero(seconds)}:${milliseconds.toString().padStart(3, '0')}`;
  
    return formattedTime;
};

const padZero = (num) => {
    return num.toString().padStart(2, '0');
};

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
            return `${duration.minutes()} minutes`;
        }
        
        return `${duration.hours()} hours, ${duration.minutes()} minutes`;
    } else if (duration.asDays() < 365) {
        const months = duration.months();
        const weeks = duration.weeks();
        const days = duration.days() % 7;

        let result = '';

        if (months > 0) {
            result += `${months} ${months === 1 ? 'month' : 'months'}, `;
        }

        if (weeks > 0) {
            result += `${weeks} ${weeks === 1 ? 'week' : 'weeks'}, `;
        }

        if (days > 0) {
            result += `${days} ${days === 1 ? 'day' : 'days'}`;
        }

        return result;
    } else {
        const years = duration.years();
        return `${years} ${years === 1 ? 'year' : 'years'}`;
    }
}

const vuetify = createVuetify({
    components,
    directives,
})

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

createInertiaApp({
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
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)
            .use(CKEditor);

        app.component("Popper", Popper);

        app.config.globalProperties.formatTime = formatTime

        app.config.globalProperties.q3tohtml = q3tohtml

        app.config.globalProperties.timeSince = timeSince

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

        app.mount(el);

        return app;
    },
    progress: {
        color: '#2d85ff'
    },
});
