// Alpine.js is provided by Livewire's bundled script.

window.scoutTheme = {
    apply(mode) {
        const dark = mode === 'dark' || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    },
    set(mode) {
        try {
            localStorage.setItem('scout-theme', mode);
        } catch (e) {
            // Storage may be unavailable; the choice then lasts for this page only.
        }
        this.apply(mode);
    },
    current() {
        try {
            return localStorage.getItem('scout-theme') || 'system';
        } catch (e) {
            return 'system';
        }
    },
};

window.copyText = async (text, el) => {
    try {
        await navigator.clipboard.writeText(text);
        if (el) {
            const original = el.textContent;
            el.textContent = 'Copied';
            setTimeout(() => (el.textContent = original), 1500);
        }
    } catch (e) {
        window.prompt('Copy this:', text);
    }
};

/**
 * Progress dialog for slow actions such as generating certificates. A form with data-progress="Label" shows it on
 * submit. The percentage is an estimate that climbs towards 95% and completes when the next page loads.
 * Optional: data-progress-count (a selector whose matches are counted) or data-progress-items (a number) lengthen the estimate.
 */
window.scoutProgress = function () {
    return {
        open: false,
        label: '',
        percent: 0,
        timer: null,
        start(label, items) {
            this.label = label;
            this.percent = 1;
            this.open = true;
            document.body.classList.add('overflow-y-hidden');
            const expected = (4 + 2.5 * Math.max(1, items)) * 1000;
            const began = Date.now();
            clearInterval(this.timer);
            this.timer = setInterval(() => {
                const t = (Date.now() - began) / expected;
                this.percent = Math.min(95, Math.round(95 * (1 - Math.exp(-3 * t))));
            }, 200);
        },
        stop() {
            clearInterval(this.timer);
            this.open = false;
            this.percent = 0;
            document.body.classList.remove('overflow-y-hidden');
        },
    };
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.dataset.progress) {
        return;
    }

    if (!form.checkValidity()) {
        return;
    }

    let items = parseInt(form.dataset.progressItems || '1', 10) || 1;

    if (form.dataset.progressCount) {
        items = form.querySelectorAll(form.dataset.progressCount).length || 1;
    }

    window.dispatchEvent(new CustomEvent('scout-progress', { detail: { label: form.dataset.progress, items } }));
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.dispatchEvent(new CustomEvent('scout-progress-stop'));
    }
});
