import { DefineComponent, Component, Plugin, App } from 'vue';

type ResolveResult = DefineComponent | Promise<DefineComponent> | {
    default: DefineComponent;
} | Promise<{
    default: DefineComponent;
}>;
type ResolveCallback = (name: string) => ResolveResult;
type ResolveTitle = (title: string) => string;
type ResolvedComponent = DefineComponent & {
    layout?: any;
    inheritAttrs?: boolean;
};
type Props = Record<string, unknown>;
interface ThemeObject<ThemeOptions extends Props = Props> {
    name: string | null;
    description: string | null;
    homepage: string | null;
    author: string | null;
    authorCode: string | null;
    code: string | null;
    options: ThemeOptions;
}
interface PageMetaObject extends Props {
    title: string | null;
    meta_title: string | null;
    meta_description: string | null;
}
interface PageObject<PageProps extends Props = Props> {
    id: string | null;
    url: string | null;
    file: string | null;
    component: string;
    props: PageProps;
    layout: string;
    theme: string | number;
    title: string;
    meta: PageMetaObject;
    content: string | null;
}
interface OctoberComponent {
    component: string;
    alias: string;
    class: string;
    options: Props;
    props: Props;
}
interface OctoberComponents {
    [alias: string]: OctoberComponent;
}
interface LaikaPayload<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    version: string | null;
    theme: ThemeObject<ThemeOptions>;
    page: PageObject<PageProps>;
    components: OctoberComponents;
    shared: SharedProps;
}
interface LaikaAppComponentProps<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    initialPayload: LaikaPayload<PageProps, SharedProps, ThemeOptions>;
    initialComponent?: DefineComponent;
    resolveComponent: ResolveCallback;
    title?: ResolveTitle;
}
type LaikaAppComponent<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> = DefineComponent<LaikaAppComponentProps<PageProps, SharedProps, ThemeOptions>>;
type LaikaPlugin = Plugin;
interface LaikaRuntime<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    payload: () => LaikaPayload<PageProps, SharedProps, ThemeOptions> | undefined;
    page: () => LaikaPayload<PageProps, SharedProps, ThemeOptions>['page'] | undefined;
    visit: (url: string, opts?: {
        replace?: boolean;
        preserveState?: boolean;
    }) => Promise<void>;
    request: (handler: string, data?: Record<string, unknown>) => Promise<unknown>;
    setLayout?: (layout: any) => void;
}
interface LaikaSetup<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    root: HTMLElement;
    App: LaikaAppComponent<PageProps, SharedProps, ThemeOptions> | Component;
    props: LaikaAppComponentProps<PageProps, SharedProps, ThemeOptions>;
    payload: LaikaPayload<PageProps, SharedProps, ThemeOptions>;
    plugin: LaikaPlugin;
}
interface LaikaOptions<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    title?: (title: string) => string;
    resolve: (name: string) => ResolveResult;
    setup: (options: LaikaSetup<PageProps, SharedProps, ThemeOptions>) => App;
    rootId?: string;
    onError?: (err: unknown) => void;
}
interface LaikaComposable<PageProps extends Props = Props, SharedProps extends Props = Props, ThemeOptions extends Props = Props> {
    component: DefineComponent | undefined;
    layout: any;
    key: number | undefined;
    payload: LaikaPayload<PageProps, SharedProps, ThemeOptions> | undefined;
    version: string | null | undefined;
    page: PageObject<PageProps> | undefined;
    shared: SharedProps | undefined;
    theme: ThemeObject<ThemeOptions>;
    components: OctoberComponents | undefined;
    runtime: LaikaRuntime<PageProps, SharedProps, ThemeOptions>;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $laika: LaikaRuntime;
        $payload: LaikaPayload | undefined;
    }
}
//# sourceMappingURL=globals.d.ts.map

export type { LaikaAppComponent, LaikaAppComponentProps, LaikaComposable, LaikaOptions, LaikaPayload, LaikaPlugin, LaikaRuntime, LaikaSetup, OctoberComponent, OctoberComponents, PageMetaObject, PageObject, Props, ResolveCallback, ResolveResult, ResolveTitle, ResolvedComponent, ThemeObject };
