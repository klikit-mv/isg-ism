@auth
{{-- Alpine state for turning push notifications on and off (profile page and the first-launch banner). --}}
<script>
    window.scoutPush = function () {
        return {
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        on: false, busy: false, message: '',
        csrf: document.querySelector('meta[name=csrf-token]').content,
        async init() {
            if (! this.supported) return;
            const reg = await navigator.serviceWorker.ready;
            this.on = !! (await reg.pushManager.getSubscription()) && Notification.permission === 'granted';
        },
        toBytes(b64) {
            const pad = '='.repeat((4 - b64.length % 4) % 4);
            const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
            return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
        },
        async call(url, method, body) {
            return fetch(url, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf }, body: body ? JSON.stringify(body) : undefined });
        },
        async enable() {
            this.busy = true; this.message = '';
            try {
                const keyResponse = await fetch(@js(route('profile.push.key')), { headers: { 'Accept': 'application/json' } });
                if (! keyResponse.ok) { this.message = 'Push notifications are not available on this server yet. Please tell an administrator.'; return; }
                const key = (await keyResponse.json()).key;
                if (await Notification.requestPermission() !== 'granted') { this.message = 'Notifications are blocked. Allow them in your browser or phone settings, then try again.'; return; }
                const reg = await navigator.serviceWorker.ready;
                const sub = (await reg.pushManager.getSubscription()) || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.toBytes(key) });
                const res = await this.call(@js(route('profile.push.subscribe')), 'POST', sub.toJSON());
                this.on = res.ok;
                this.message = res.ok ? 'Notifications are on for this device.' : 'Could not turn notifications on. Please try again.';
            } catch (e) { this.message = 'Could not turn notifications on on this device.'; }
            finally { this.busy = false; }
        },
        async disable() {
            this.busy = true;
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) { await this.call(@js(route('profile.push.unsubscribe')), 'DELETE', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
                this.on = false; this.message = 'Notifications are off for this device.';
            } finally { this.busy = false; }
        },
        async test() {
            const res = await this.call(@js(route('profile.push.test')), 'POST');
            const data = res.ok ? await res.json() : {};
            this.message = data.sent ? 'Test sent. It should arrive in a moment.' : 'The test could not be delivered.';
        },
        };
    };

    // Shown once in the installed app (home-screen launch) while the permission is still undecided.
    window.scoutPushBanner = function () {
        const base = window.scoutPush();
        const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        let dismissed = false;
        try { dismissed = localStorage.getItem('scout-push-dismissed') === '1'; } catch (e) {}

        return Object.assign(base, {
            show: false,
            async init() {
                this.show = standalone && this.supported && ! dismissed && Notification.permission === 'default';
            },
            async turnOn() {
                await this.enable();
                this.show = ! this.on && Notification.permission === 'default';
            },
            later() {
                this.show = false;
                try { localStorage.setItem('scout-push-dismissed', '1'); } catch (e) {}
            },
        });
    };
</script>
@endauth
