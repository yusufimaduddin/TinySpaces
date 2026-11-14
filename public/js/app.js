/**
 * Render user-controlled Markdown safely.
 *
 * marked.parse() keeps raw HTML, so the result must be sanitized before it is
 * assigned through Alpine's x-html. If DOMPurify is unavailable we fail closed
 * and render the source as escaped plain text instead of unsafe HTML.
 */
window.renderMarkdown = (text) => {
    if (typeof text !== 'string' || text.trim() === '') {
        return '';
    }

    if (typeof window.marked === 'undefined') {
        return '';
    }

    const html = window.marked.parse(text);

    if (window.DOMPurify) {
        return window.DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });
    }

    const container = document.createElement('div');
    container.textContent = text;
    return container.innerHTML.replace(/\n/g, '<br>');
};

document.addEventListener('alpine:init', () => {
    // Toast Notification Store
    if (!Alpine.store('toast')) {
        Alpine.store('toast', {
            visible: false,
            message: '',
            type: 'success',
            show(message, type = 'success') {
                this.message = message;
                this.type = type;
                this.visible = true;
                setTimeout(() => {
                    this.visible = false;
                }, 3000);
            }
        });
    }

    // Make toast globally available
    window.showAlpineToast = (message, type) => Alpine.store('toast').show(message, type);

    /**
     * Safe JSON fetch helper.
     * - Never surfaces raw HTML as an error message
     * - Handles expired sessions globally (401 / SESSION_EXPIRED)
     */
    window.apiFetch = async (url, options = {}) => {
        const opts = { credentials: 'same-origin', ...options };
        const headers = new Headers(opts.headers || {});

        if (!headers.has('Accept')) {
            headers.set('Accept', 'application/json');
        }
        if (!headers.has('X-Requested-With')) {
            headers.set('X-Requested-With', 'XMLHttpRequest');
        }
        opts.headers = headers;

        let response;
        try {
            response = await fetch(url, opts);
        } catch (error) {
            const networkError = new Error('Tidak dapat terhubung ke server. Periksa koneksi Anda.');
            networkError.name = 'NetworkError';
            throw networkError;
        }

        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.toLowerCase().includes('application/json');
        let data = null;

        if (isJson) {
            try {
                data = await response.json();
            } catch (error) {
                data = null;
            }
        } else {
            try {
                await response.text();
            } catch (error) {
                // ignore body read errors
            }
        }

        const sessionExpired = response.status === 401 || (data && data.code === 'SESSION_EXPIRED');
        if (sessionExpired) {
            const authError = new Error('Sesi Anda telah berakhir. Silakan login kembali.');
            authError.name = 'AuthError';
            authError.status = 401;
            authError.data = data;

            if (!window.__sessionExpiredHandled) {
                window.__sessionExpiredHandled = true;
                window.showAlpineToast(authError.message, 'error');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1200);
            }

            throw authError;
        }

        if (!isJson) {
            const invalidError = new Error(
                response.ok
                    ? 'Respons server tidak valid. Silakan coba lagi.'
                    : 'Terjadi kesalahan pada server. Silakan coba lagi.'
            );
            invalidError.name = 'InvalidResponseError';
            invalidError.status = response.status;
            throw invalidError;
        }

        if (!response.ok && (!data || typeof data.success === 'undefined')) {
            const httpError = new Error((data && data.message) || 'Terjadi kesalahan pada server.');
            httpError.name = 'HttpError';
            httpError.status = response.status;
            httpError.data = data;
            throw httpError;
        }

        return data;
    };

    // Double-click handler for modal backdrops
    // Closes the modal only if clicked twice within 300ms
    window.handleBackdropDoubleTap = (callback) => {
        const now = new Date().getTime();
        if (window.lastBackdropTap && (now - window.lastBackdropTap < 300)) {
            callback();
            window.lastBackdropTap = 0;
        } else {
            window.lastBackdropTap = now;
        }
    };
});
