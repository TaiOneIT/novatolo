let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
});

document.querySelectorAll('[data-install-app]').forEach(button => {
    button.addEventListener('click', async () => {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            return;
        }

        const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent);
        const guidance = isAppleMobile
            ? 'To install NOVATOLO, open the Share menu in Safari and choose Add to Home Screen.'
            : 'To install NOVATOLO, open your browser menu and choose Install app or Add to Home screen.';
        window.alert(guidance);
    });
});

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('./sw.js').catch(() => {}));
}