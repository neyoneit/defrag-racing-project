/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Auto-refresh page on expired CSRF token (419 response) - max once per page load
let axiosCsrfReloaded = false;
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419 && !axiosCsrfReloaded) {
            axiosCsrfReloaded = true;
            window.location.reload();
        }
        // Redirect to email verification on 403 from verified middleware
        if (error.response?.status === 403 && error.response?.data?.message === 'Your email address is not verified.') {
            window.location.href = '/email/verify';
            return new Promise(() => {});
        }
        return Promise.reject(error);
    }
);

// Log API errors to backend for admin review
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        // Don't log errors from the error logging endpoint itself
        const url = error.config?.url || '';
        if (!url.includes('/api/frontend-errors') && error.response?.status >= 400) {
            try {
                fetch('/api/frontend-errors', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        type: 'api_error',
                        message: error.response?.data?.message || error.message || 'API request failed',
                        url: window.location.href,
                        endpoint: error.config?.method?.toUpperCase() + ' ' + url,
                        status_code: error.response?.status,
                        request_data: JSON.stringify(error.config?.data || null)?.substring(0, 2000),
                        response_data: typeof error.response?.data === 'string' && error.response.data.includes('<!DOCTYPE')
                        ? '[HTML error page]'
                        : JSON.stringify(error.response?.data || null)?.substring(0, 2000),
                    }),
                    keepalive: true,
                }).catch(() => {});
            } catch (e) {
                // Silently fail
            }
        }
        return Promise.reject(error);
    }
);

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
