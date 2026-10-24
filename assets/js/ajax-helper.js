/**
 * MartPro Core AJAX Helper & Notification Engine
 */

window.MartPro = window.MartPro || {};

MartPro.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/**
 * Show a floating toast notification
 */
MartPro.notify = function(type, message, title) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toastId = 'toast-' + Math.random().toString(36).substring(2, 9);
    const bgClass = type === 'success' ? 'bg-success text-white' : 
                    type === 'error' || type === 'danger' ? 'bg-danger text-white' : 
                    type === 'warning' ? 'bg-warning text-dark' : 'bg-info text-white';

    const icon = type === 'success' ? 'bi-check-circle-fill' : 
                 type === 'error' || type === 'danger' ? 'bi-exclamation-triangle-fill' : 
                 type === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill';

    const html = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 mb-2 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi ${icon} me-2 fs-5"></i>
                    <div>
                        ${title ? '<strong>' + title + '</strong><br>' : ''}
                        ${message}
                    </div>
                </div>
                <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(toastId);
    if (window.bootstrap && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    } else {
        setTimeout(() => toastEl.remove(), 4000);
    }
};

/**
 * Robust AJAX request handler
 */
MartPro.ajax = function(url, options = {}) {
    const defaults = {
        method: 'POST',
        data: null,
        dataType: 'json',
        showLoader: true,
        button: null
    };
    const settings = Object.assign({}, defaults, options);

    // Button loading state
    let originalBtnHtml = '';
    if (settings.button) {
        const $btn = $(settings.button);
        originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...');
    }

    // Set CSRF header
    const headers = {
        'X-CSRF-TOKEN': MartPro.csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
    };

    return new Promise((resolve, reject) => {
        $.ajax({
            url: url,
            type: settings.method,
            data: settings.data,
            headers: headers,
            dataType: settings.dataType,
            processData: !(settings.data instanceof FormData),
            contentType: (settings.data instanceof FormData) ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            success: function(response) {
                if (settings.button) {
                    $(settings.button).prop('disabled', false).html(originalBtnHtml);
                }
                if (response && response.success === false) {
                    MartPro.notify('error', response.message || 'An error occurred.');
                    reject(response);
                } else {
                    resolve(response);
                }
            },
            error: function(xhr) {
                if (settings.button) {
                    $(settings.button).prop('disabled', false).html(originalBtnHtml);
                }
                let errorMsg = 'Server communication error.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        errorMsg = res.message || errorMsg;
                    } catch (e) {
                        if (xhr.status === 401) {
                            errorMsg = 'Session expired. Redirecting to login...';
                            setTimeout(() => {
                                window.location.href = (window.MartPro?.baseUrl || '') + '/login.php';
                            }, 1200);
                        } else if (xhr.status === 403) {
                            errorMsg = 'Access Denied: You lack required permissions.';
                        } else if (xhr.status === 404) {
                            errorMsg = 'Requested resource not found.';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Server internal error (500). Please check system logs.';
                        }
                    }
                }
                if (!settings.silentError) {
                    MartPro.notify('error', errorMsg);
                }
                reject(xhr);
            }
        });
    });
};
