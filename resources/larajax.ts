/**
 *
 */
async function larajaxListeners() {
    window.addEventListener('ajax:setup', function (ev: CustomEvent) {
        return;
        const ctx = ev?.detail?.context;
        if (!ctx?.options) {
            return;
        }

        // uses options.headers
        if (!('headers' in ctx.options)) {
            ctx.options.headers = {};
        }
        if (ctx.options instanceof Headers) {
            (ctx.options as Headers).set('X-Laika', '1');
        } else {
            ctx.options.headers['X-Laika'] = '1';
        }
    });
}
larajaxListeners();
