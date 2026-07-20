import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const notify = (type, payload) => {
    window.dispatchEvent(new CustomEvent('promptbot:notify', { detail: { type, ...payload } }));

    const message = [payload?.message, payload?.description].filter(Boolean).join(' ');
    if (type === 'error') {
        console.error(message);
        return;
    }

    console.warn(message);
};

window.axios.interceptors.request.use((config) => {
    const appContext = window.__KITELEDGER_APP_CONTEXT__ || {};

    if (appContext.branchId) {
        config.headers['X-Branch-ID'] = appContext.branchId;
    }

    if (appContext.fiscalYearId) {
        config.headers['X-Fiscal-Year-ID'] = appContext.fiscalYearId;
    }

    return config;
});

window.axios.interceptors.response.use(
    (response) => {
        const rules = response?.data?.business_rules;
        if (rules?.has_warnings) {
            notify('warning', {
                message: response?.data?.message || 'Transaction has warnings but can continue.',
            });
        }

        return response;
    },
    (error) => {
        const rules = error?.response?.data?.business_rules;
        if (rules?.has_errors) {
            const first = Array.isArray(rules.checks)
                ? rules.checks.find((check) => check?.status === 'error')?.message
                : null;
            notify('error', {
                message: error?.response?.data?.message || 'Transaction blocked by business rules.',
                description: first || undefined,
            });
        }

        return Promise.reject(error);
    }
);
