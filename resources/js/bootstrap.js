import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.interceptors.request.use((config) => {
    const locale = localStorage.getItem('lang') || 'en';
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    config.headers = config.headers || {};
    config.headers['X-Locale'] = locale;
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }

    return config;
});

const originalFetch = window.fetch.bind(window);
window.fetch = (input, init = {}) => {
    const locale = localStorage.getItem('lang') || 'en';
    const sourceHeaders = input instanceof Request ? input.headers : init.headers;
    const headers = new Headers(sourceHeaders || {});
    if (!headers.has('X-Locale')) {
        headers.set('X-Locale', locale);
    }
    return originalFetch(input, {
        ...init,
        headers,
    });
};
