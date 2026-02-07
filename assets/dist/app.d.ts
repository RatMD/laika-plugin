import type { LaikaAppComponent, LaikaComposable, Props } from "./types";
import { type Plugin } from "vue";
/**
 * Laika Application Component
 */
export declare const App: LaikaAppComponent;
/**
 * Laika Plugin
 */
export declare const plugin: Plugin;
/**
 * Laika Composable (similar to inertias "usePage").
 * @returns
 */
export declare function useLaika<PageProps extends Props = Props, SharedProps extends Props = Props>(): LaikaComposable<PageProps, SharedProps>;
//# sourceMappingURL=app.d.ts.map