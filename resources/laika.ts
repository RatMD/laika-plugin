import type { LaikaOptions, LaikaPayload, PageProps, LaikaPluginApi } from './types';
import type { Plugin } from 'vue';

/**
 *
 * @param mod
 * @returns
 */
function unwrapModule(mod: any) {
    return mod?.default ?? mod;
}

/**
 *
 * @param selector
 * @returns
 */
function readInitialPayload<SharedProps extends PageProps, Props extends PageProps>(
    selector = '[data-laika="payload"]'
): LaikaPayload<SharedProps, Props> {
    const el = document.head.querySelector(selector);
    if (!el) {
        throw new Error(`Laika: payload script tag not found (${selector})`);
    }

    const raw = el.textContent?.trim();
    if (!raw) {
        throw new Error('Laika: payload script tag is empty');
    }

    let payload: unknown;
    try {
        payload = JSON.parse(raw);
    } catch (e) {
        throw new Error('Laika: payload JSON parse failed');
    }

    const p = payload as any;
    if (!p || typeof p !== 'object') {
        throw new Error('Laika: payload is not an object');
    }
    if (typeof p.component !== 'string' || !p.component) {
        throw new Error('Laika: payload.component missing');
    }
    if (typeof p.url !== 'string') {
        throw new Error('Laika: payload.url missing');
    }
    if (!p.props || typeof p.props !== 'object') {
        throw new Error('Laika: payload.props missing');
    }

    return p as LaikaPayload<SharedProps, Props>;
}

/**
 *
 * @param options
 * @returns
 */
export async function createLaikaApp<SharedProps extends PageProps, Props extends PageProps = PageProps>(
    options: LaikaOptions<SharedProps, Props>
) {
    try {
        const root =
            (options.rootId ? document.querySelector(options.rootId) : document.querySelector('.app')) as HTMLElement | null;
        if (!root) {
            throw new Error(`Laika: root element not found (${options.rootId ?? '.app'})`);
        }

        const payload = readInitialPayload<SharedProps, Props>();
        if (payload.head?.title && options.title) {
            document.title = options.title(payload.head.title);
        } else if (payload.head?.title) {
            document.title = payload.head.title;
        }

        const resolved = await options.resolve(payload.component);
        const AppComponent = unwrapModule(resolved);

        // Laika Functions
        const laikaApi: LaikaPluginApi<SharedProps, Props> = {
            page: () => payload,
            visit: async (url: string, opts?: { replace?: boolean }) => {
                if (opts?.replace) {
                    history.replaceState({}, '', url);
                } else {
                    history.pushState({}, '', url);
                }
            },
            request: async (handler: string, data?: Record<string, unknown>) => {
                return { handler, data };
            },
        };

        // Laika Vue Plugin
        const plugin: Plugin = {
            install(app) {
                app.provide('laika', laikaApi);
                (app.config.globalProperties as any).$laika = laikaApi;
            },
        };

        // Setup
        return options.setup({
            root,
            App: AppComponent,
            props: payload.props.page,
            payload,
            plugin,
            laika: laikaApi,
        });
    } catch (err) {
        if (options.onError) {
            options.onError(err);
        } else {
            console.error(err);
        }
        return null;
    }
}
