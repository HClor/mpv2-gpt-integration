(function(window, document) {
    'use strict';

    const DEFAULT_API_URL = '/assets/components/testsystem/ajax/testsystem.php';
    const CSRF_RECOVERY_MESSAGE = 'Не удалось подтвердить действие из-за истекшего защитного токена. Обновите страницу и повторите попытку.';

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

    function withCsrfRecoveryMessage(result) {
        return {
            ...result,
            message: CSRF_RECOVERY_MESSAGE
        };
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('[csrf-helper] Invalid JSON response:', text);
            throw new Error('Сервер вернул некорректный ответ');
        }
    }

    async function refreshToken() {
        const response = await fetch(getApiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'getCsrfToken', data: {} })
        });

        if (!response.ok) {
            throw new Error(`Ошибка HTTP: ${response.status}`);
        }

        const result = await parseJsonResponse(response);

        if (!result.success || !result.data || !result.data.csrf_token) {
            throw new Error(CSRF_RECOVERY_MESSAGE);
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
            throw new Error(`Ошибка HTTP: ${response.status}`);
        }

        const result = await parseJsonResponse(response);

        if (retryOnCsrfFail && isCsrfFailure(result)) {
            try {
                await refreshToken();
            } catch (error) {
                return withCsrfRecoveryMessage(result);
            }

            return apiCall(action, data, { ...options, retryOnCsrfFail: false });
        }

        if (isCsrfFailure(result)) {
            return withCsrfRecoveryMessage(result);
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
