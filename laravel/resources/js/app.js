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
