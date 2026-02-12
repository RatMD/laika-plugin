import "./larajax";

import type { DefineComponent } from "vue";
import type { LaikaAppComponentProps, LaikaOptions, LaikaPayload, Props } from "./types";
import { unwrapModule } from "./utils";
import { App, plugin } from "./app";

/**
 *
 * @param selector
 * @returns
 */
function readInitialPayload<PageProps extends Props, SharedProps extends Props>(
    selector = '[data-laika="payload"]',
): LaikaPayload<PageProps, SharedProps> {
    const element = document.head.querySelector(selector);
    if (!element?.textContent?.trim()) {
        throw new Error("Laika: payload missing/empty");
    }
    return JSON.parse(element.textContent) as LaikaPayload<PageProps, SharedProps>;
}

/**
 * Create Laika Application
 * @param options
 * @returns
 */
export async function createLaikaApp<PageProps extends Props = Props, SharedProps extends Props = Props>(
    options: LaikaOptions<PageProps, SharedProps>
) {
    const root = (options.rootId ? document.querySelector(options.rootId) : document.querySelector(".app")) as HTMLElement | null;
    if (!root) {
        throw new Error(`Laika: root element not found (${options.rootId ?? ".app"})`);
    }

    const payload = readInitialPayload<PageProps, SharedProps>();
    const resolved = await Promise.resolve(options.resolve(payload.page.component));
    const initialComponent = unwrapModule<DefineComponent>(resolved);

    // Create App Properties
    const props: LaikaAppComponentProps<PageProps, SharedProps> = {
        initialPayload: payload,
        initialComponent,
        resolveComponent: options.resolve,
        ...(options.title ? { title: options.title } : {}),
    };

    // Setup
    return options.setup({
        root,
        App,
        props,
        plugin,
        payload,
    });
}
