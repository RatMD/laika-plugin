import type {
    LaikaAppComponent,
    LaikaComposable,
    LaikaPayload,
    LaikaRuntime,
    Props,
    ResolvedComponent,
    ResolveResult,
} from "./types";
import {
    type DefineComponent,
    type Plugin,
    type PropType,
    type VNode,
    computed,
    defineComponent,
    h,
    markRaw,
    reactive,
    ref,
    shallowRef,
    Fragment,
} from "vue";
import { ProgressBar } from "./components";
import { getByPath, parseOnlyHeader, setByPath, unwrapModule } from "./utils";
import { useProgressBar } from "./progress";


// States
const component = shallowRef<DefineComponent>();
const payload = shallowRef<LaikaPayload>();
const layout = shallowRef<any>(null);
const key = ref<number | undefined>(undefined);
const runtime: LaikaRuntime = {
    payload: () => undefined,
    page: () => undefined,
    visit: async () => { throw new Error("Laika runtime not ready"); },
    request: async () => { throw new Error("Laika runtime not ready"); },
};

/**
 * Laika Application Component
 */
export const App: LaikaAppComponent = defineComponent({
    /**
     * Component Name
     */
    name: "Laika",

    /**
     * Component Properties
     */
    props: {
        initialPayload: {
            type: Object as PropType<LaikaPayload>,
            required: true
        },
        initialComponent: {
            type: Object as PropType<DefineComponent>,
            required: false,
        },
        resolveComponent: {
            type: Function as PropType<(name: string) => ResolveResult>,
            required: true,
        },
        title: {
            type: Function as PropType<(title: string) => string>,
            required: false,
            default: (t: string) => t,
        },
    },

    /**
     * Setup Laika Application
     * @param props
     * @returns
     */
    setup({ initialPayload, initialComponent, resolveComponent, title: titleCallback }) {
        payload.value = initialPayload;
        key.value = void 0;

        // Composable
        const progress = useProgressBar();

        // Set Component
        component.value = initialComponent ? markRaw(unwrapModule<ResolvedComponent>(initialComponent)) : void 0;
        if (!component.value) {
            Promise
                .resolve(resolveComponent(initialPayload.page.component))
                .then(mod => { component.value = markRaw(unwrapModule<ResolvedComponent>(mod)) })
                .catch(console.error);
        }

        /**
         * Fetch Payload
         * @param url
         * @returns
         */
        async function fetchPayload(url: string) {
            const res = await fetch(url, {
                headers: {
                    Accept: "application/json",
                    "X-Laika": "1",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
            });

            if (res.status === 409) {
                const loc = res.headers.get("X-Laika-Location");
                if (loc) {
                    window.location.assign(loc);
                }
                throw new Error("Laika redirect");
            }

            if (!res.ok) {
                window.location.assign(url);
                throw new Error("Navigation fallback");
            }

            const only = parseOnlyHeader(res.headers.get("X-Laika-Only"));
            const data = (await res.json()) as LaikaPayload;

            return { data, only };
        }

        /**
         *
         * @param current
         * @param next
         * @param only
         * @returns
         */
        function patchPayload(current: LaikaPayload, next: LaikaPayload, only: string[]): LaikaPayload {
            const out: any = { ...(current as any) };
            const top = new Set(only.filter(k => !k.startsWith('shared.')));

            for (const key of top) {
                if (key in (next as any)) {
                    out[key] = (next as any)[key];
                }
            }

            const sharedPaths = only.filter(k => k.startsWith('shared.'));
            if (sharedPaths.length) {
                out.shared = { ...(out.shared ?? {}) };

                for (const path of sharedPaths) {
                    const val = getByPath(next as any, path);
                    if (val !== undefined) {
                        setByPath(out as any, path, val);
                    }
                }
            }

            return out as LaikaPayload;
        }

        /**
         * Swap Component
         * @param nextPayload
         * @param preserveState
         */
        async function swap(nextPayload: LaikaPayload, preserveState?: boolean, only?: string[]) {
            const mod = await resolveComponent(nextPayload.page.component);
            component.value = markRaw(unwrapModule<ResolvedComponent>(mod));

            if (only && only.length && payload.value) {
                payload.value = patchPayload(payload.value, nextPayload, only);
            } else {
                payload.value = nextPayload;
            }

            const title = payload.value?.page?.title;
            if (title) {
                document.title = (titleCallback ?? ((x) => x))(title);
            }

            key.value = preserveState ? key.value : Date.now();
        }

        // Laika Runtime API
        runtime.payload = () => payload.value;
        runtime.page = () => payload.value?.page;
        runtime.visit = async (url: string, opts?: { replace?: boolean, preserveState?: boolean }) => {
            progress.start();

            try {
                if (opts?.replace) {
                    history.replaceState({}, "", url);
                } else {
                    history.pushState({}, "", url);
                }

                const { data: nextPayload, only } = await fetchPayload(url);
                await swap(nextPayload, opts?.preserveState, only);
                progress.done();
            } catch (err) {
                console.error(err);
                progress.fail();
            }
        };
        runtime.request = async (handler: string, data?: Record<string, unknown>) => ({ handler, data });
        runtime.setLayout = (next: any) => {
            layout.value = next;
        };

        // Popstate
        window.addEventListener("popstate", () => window.location.reload());

        // Return
        return () => {
            if (!component.value || !payload.value) {
                return null;
            }

            const props = payload.value.page.props ?? {};
            const child = h(component.value, { ...(props as any), key: key.value });

            // Handle Component Layout
            if (layout.value) {
                component.value.layout = layout.value;
                layout.value = null;
            }

            // Get User Component
            let userComponent: VNode;

            const childLayout = component.value.layout;
            if (!childLayout) {
                userComponent = child;
            } else if (typeof childLayout === "function") {
                userComponent = childLayout(h, child);
            } else {
                const layouts = (Array.isArray(childLayout) ? childLayout : [childLayout]) as any[];
                userComponent = layouts
                    .slice()
                    .reverse()
                    .reduce<VNode>((acc, L) => h(L, { ...(props as any) }, () => acc), child as any);
            }

            // Render
            return h(Fragment, null, [
                h(ProgressBar),
                userComponent,
            ]);
        };
    },
});

/**
 * Laika Plugin
 */
export const plugin: Plugin = {
    install(app) {
        Object.defineProperty(app.config.globalProperties, '$laika', {
            get: () => runtime
        });
        Object.defineProperty(app.config.globalProperties, '$payload', {
            get: () => payload.value
        });
    },
};

/**
 * Laika Composable (similar to inertias "usePage").
 * @returns
 */
export function useLaika<PageProps extends Props = Props, SharedProps extends Props = Props>(): LaikaComposable<PageProps, SharedProps> {
    return reactive({
        component: computed(() => component.value),
        layout: computed(() => layout.value),
        key: computed(() => key.value),
        payload: computed(() => payload.value),
        version: computed(() => payload.value?.version),
        page: computed(() => payload.value?.page),
        shared: computed(() => payload.value?.shared),
        theme: computed(() => payload.value?.theme),
        components: computed(() => payload.value?.components),
        runtime: computed(() => runtime),
    }) as LaikaComposable<PageProps, SharedProps>;
}
