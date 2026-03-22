(function(window, document) {
    'use strict';

    const DEFAULT_API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    function getApiUrl() {
        return window.TS_API_URL || DEFAULT_API_URL;
    }

    function getToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function setToken(token) {
        let meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
            meta = document.createElement('meta');
            meta.setAttribute('name', 'csrf-token');
            document.head.appendChild(meta);
        }

        meta.setAttribute('content', token);
    }

    function isCsrfFailure(result) {
        return !!(
            result &&
            result.success === false &&
            typeof result.message === 'string' &&
            result.message.toLowerCase().includes('csrf token validation failed')
        );
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('[csrf-helper] Invalid JSON response:', text);
            throw new Error('Invalid server response');
        }
    }

    async function refreshToken() {
        const response = await fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getCsrfToken', data: {} })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await parseJsonResponse(response);

        if (!result.success || !result.data || !result.data.csrf_token) {
            throw new Error(result.message || 'Failed to refresh CSRF token');
        }

        setToken(result.data.csrf_token);
        return result.data.csrf_token;
    }

    async function apiCall(action, data = {}, options = {}) {
        const retryOnCsrfFail = options.retryOnCsrfFail !== false;
        const requestData = data ? { ...data } : {};
        const csrfToken = getToken();

        if (csrfToken) {
            requestData.csrf_token = csrfToken;
        }

        const response = await fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, data: requestData })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await parseJsonResponse(response);

        if (retryOnCsrfFail && isCsrfFailure(result)) {
            await refreshToken();
            return apiCall(action, data, { ...options, retryOnCsrfFail: false });
        }

        return result;
    }

    window.TestSystemCSRF = {
        getApiUrl,
        getToken,
        setToken,
        refreshToken,
        isCsrfFailure,
        apiCall
    };
})(window, document);
