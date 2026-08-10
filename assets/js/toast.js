(function (window, document) {
    'use strict';

    const DEFAULT_DURATION = 4000;
    const MIN_DURATION = 1000;
    const MAX_DURATION = 30000;
    const MAX_VISIBLE = 4;
    const EXIT_DURATION = 320;
    const VALID_TYPES = new Set(['success', 'error', 'warning', 'info']);

    const pending = [];
    const active = new Map();
    let sequence = 0;

    const TITLES = {
        success: 'Success!',
        error: 'Error!',
        warning: 'Warning!',
        info: 'Info!'
    };

    const ICONS = {
        success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.2 4.2L19 7"/></svg>',
        error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5.5 5.7v5.6c0 4.3 2.7 7.8 6.5 9.7 3.8-1.9 6.5-5.4 6.5-9.7V5.7L12 3Z"/><path d="M12 8v5m0 3h.01"/></svg>',
        warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.2 4.4 2.8 17.3A1.8 1.8 0 0 0 4.4 20h15.2a1.8 1.8 0 0 0 1.6-2.7L13.8 4.4a2.1 2.1 0 0 0-3.6 0Z"/><path d="M12 9v4m0 3h.01"/></svg>',
        info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-9h.01"/></svg>'
    };

    function normalizeType(type) {
        let value = String(type || 'info').trim().toLowerCase();
        if (value === 'danger' || value === 'failed' || value === 'failure') {
            value = 'error';
        }
        return VALID_TYPES.has(value) ? value : 'info';
    }

    function cleanText(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim();
    }

    function normalizeDuration(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return DEFAULT_DURATION;
        return Math.min(MAX_DURATION, Math.max(MIN_DURATION, Math.round(parsed)));
    }

    function getContainer() {
        let container = document.getElementById('schoolToastContainer');

        if (!container) {
            container = document.createElement('div');
            container.id = 'schoolToastContainer';
            container.className = 'school-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            container.setAttribute('aria-relevant', 'additions');
            document.body.appendChild(container);
        }

        return container;
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = String(text);
        return element;
    }

    function makeSpark(index) {
        const spark = createElement('span', 'school-toast__spark school-toast__spark--' + index);
        spark.setAttribute('aria-hidden', 'true');
        return spark;
    }

    function buildToast(item) {
        const toast = createElement(
            'article',
            'school-toast school-toast--' + item.type
        );

        toast.dataset.toastId = item.id;
        toast.setAttribute('role', item.type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-label', item.title + ': ' + item.message);

        const colorPanel = createElement('div', 'school-toast__visual');
        colorPanel.appendChild(makeSpark(1));
        colorPanel.appendChild(makeSpark(2));
        colorPanel.appendChild(makeSpark(3));
        colorPanel.appendChild(makeSpark(4));
        colorPanel.appendChild(makeSpark(5));

        const iconRing = createElement('div', 'school-toast__icon-ring');
        const icon = createElement('div', 'school-toast__icon');
        icon.innerHTML = ICONS[item.type] || ICONS.info;
        iconRing.appendChild(icon);
        colorPanel.appendChild(iconRing);

        const body = createElement('div', 'school-toast__body');
        const header = createElement('div', 'school-toast__header');
        const title = createElement('div', 'school-toast__title', item.title);
        const tools = createElement('div', 'school-toast__tools');
        const time = createElement('span', 'school-toast__time', Math.ceil(item.duration / 1000) + 's');
        time.setAttribute('aria-label', 'Auto closes in ' + Math.ceil(item.duration / 1000) + ' seconds');

        const close = createElement('button', 'school-toast__close');
        close.type = 'button';
        close.setAttribute('aria-label', 'Close notification');
        close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';

        tools.appendChild(time);
        tools.appendChild(close);
        header.appendChild(title);
        header.appendChild(tools);

        const message = createElement('div', 'school-toast__message', item.message);
        const progressTrack = createElement('div', 'school-toast__progress-track');
        const progress = createElement('div', 'school-toast__progress');
        progressTrack.appendChild(progress);

        body.appendChild(header);
        body.appendChild(message);
        body.appendChild(progressTrack);

        toast.appendChild(colorPanel);
        toast.appendChild(body);

        return { toast, close, time, progress };
    }

    function updateCountdown(record, now) {
        if (!record || record.paused) return;

        const remaining = Math.max(0, record.deadline - now);
        const ratio = Math.max(0, Math.min(1, remaining / record.duration));
        record.progress.style.transform = 'scaleX(' + ratio + ')';
        record.time.textContent = Math.max(0, Math.ceil(remaining / 1000)) + 's';

        if (remaining <= 0) {
            dismiss(record.id, 'timeout');
            return;
        }

        record.raf = window.requestAnimationFrame(function (timestamp) {
            updateCountdown(record, timestamp);
        });
    }

    function startTimer(record) {
        const now = performance.now();
        record.startedAt = now;
        record.deadline = now + record.remaining;
        record.raf = window.requestAnimationFrame(function (timestamp) {
            updateCountdown(record, timestamp);
        });
    }

    function pauseTimer(record) {
        if (!record || record.paused) return;

        record.paused = true;
        window.cancelAnimationFrame(record.raf);
        record.raf = 0;
        record.remaining = Math.max(0, record.deadline - performance.now());
        record.element.classList.add('school-toast--paused');
    }

    function resumeTimer(record) {
        if (!record || !record.paused || record.remaining <= 0) return;

        record.paused = false;
        record.element.classList.remove('school-toast--paused');
        startTimer(record);
    }

    function render(item) {
        const container = getContainer();
        const parts = buildToast(item);

        const record = {
            id: item.id,
            element: parts.toast,
            progress: parts.progress,
            time: parts.time,
            duration: item.duration,
            remaining: item.duration,
            startedAt: 0,
            deadline: 0,
            paused: false,
            raf: 0
        };

        active.set(item.id, record);
        container.appendChild(parts.toast);

        parts.close.addEventListener('click', function () {
            dismiss(item.id, 'close');
        });

        parts.toast.addEventListener('mouseenter', function () {
            pauseTimer(record);
        });

        parts.toast.addEventListener('mouseleave', function () {
            resumeTimer(record);
        });

        parts.toast.addEventListener('focusin', function () {
            pauseTimer(record);
        });

        parts.toast.addEventListener('focusout', function (event) {
            if (!parts.toast.contains(event.relatedTarget)) {
                resumeTimer(record);
            }
        });

        // Stagger makes queued notifications feel intentional.
        const delay = Math.min(120, active.size * 24);
        window.setTimeout(function () {
            window.requestAnimationFrame(function () {
                parts.toast.classList.add('school-toast--visible');
            });
        }, delay);

        startTimer(record);
    }

    function flush() {
        while (active.size < MAX_VISIBLE && pending.length > 0) {
            render(pending.shift());
        }
    }

    function dismiss(id, reason) {
        const key = String(id);
        const record = active.get(key);
        if (!record) return false;

        active.delete(key);
        window.cancelAnimationFrame(record.raf);
        record.element.classList.add('school-toast--leaving');
        record.element.dataset.dismissReason = reason || 'manual';

        window.setTimeout(function () {
            record.element.remove();
            flush();
        }, EXIT_DURATION);

        return true;
    }

    function showToast(type, message, options) {
        const normalizedType = normalizeType(type);
        const normalizedMessage = cleanText(message);

        if (!normalizedMessage) return null;

        const config = options && typeof options === 'object' ? options : {};
        const duration = normalizeDuration(config.duration);
        const id = String(++sequence);
        const title = cleanText(config.title) || TITLES[normalizedType];

        pending.push({
            id,
            type: normalizedType,
            message: normalizedMessage,
            title,
            duration
        });

        flush();
        return id;
    }

    function clearAll() {
        pending.length = 0;
        Array.from(active.keys()).forEach(function (id) {
            dismiss(id, 'clear');
        });
    }

    function fromAjax(payload, options) {
        if (!payload || typeof payload !== 'object') {
            return showToast('error', 'Invalid server response.', options);
        }

        const success = Boolean(payload.success);
        const type = payload.type
            ? normalizeType(payload.type)
            : (success ? 'success' : 'error');

        const message = cleanText(
            payload.message
            || payload.error
            || payload.status_message
            || (success ? 'Operation completed successfully.' : 'Something went wrong.')
        );

        return showToast(type, message, options);
    }

    function loadSessionToasts() {
        const payloadElement = document.getElementById('schoolToastSessionPayload');
        if (!payloadElement) return;

        const raw = payloadElement.textContent || '[]';
        payloadElement.remove();

        try {
            const payload = JSON.parse(raw);
            if (!Array.isArray(payload)) return;

            payload.forEach(function (item) {
                if (!item || typeof item !== 'object') return;

                showToast(
                    item.type || 'info',
                    item.message || '',
                    {
                        title: item.title || '',
                        duration: DEFAULT_DURATION
                    }
                );
            });
        } catch (error) {
            console.warn('School Toast: invalid PHP session payload.', error);
        }
    }

    // Replay calls made before this file finished loading.
    const preQueue = Array.isArray(window.__schoolToastPreQueue)
        ? window.__schoolToastPreQueue.slice()
        : [];

    window.showToast = showToast;
    window.SchoolToast = Object.freeze({
        show: showToast,
        success: function (message, options) {
            return showToast('success', message, options);
        },
        error: function (message, options) {
            return showToast('error', message, options);
        },
        warning: function (message, options) {
            return showToast('warning', message, options);
        },
        info: function (message, options) {
            return showToast('info', message, options);
        },
        fromAjax: fromAjax,
        dismiss: dismiss,
        clear: clearAll
    });

    preQueue.forEach(function (args) {
        try {
            showToast.apply(null, Array.from(args));
        } catch (error) {
            console.warn('School Toast: queued notification could not be replayed.', error);
        }
    });

    window.__schoolToastPreQueue = [];

    document.addEventListener('school:toast', function (event) {
        const detail = event && event.detail ? event.detail : {};
        showToast(detail.type || 'info', detail.message || '', detail);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSessionToasts, { once: true });
    } else {
        loadSessionToasts();
    }
})(window, document);
