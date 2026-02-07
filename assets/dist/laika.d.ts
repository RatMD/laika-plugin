import { DefineComponent, Component, Plugin, App } from 'vue';

type Props = Record<string, unknown>;
interface PageHeader extends Props {
    title: string | null;
    meta_title: string | null;
    meta_description: string | null;
}
interface PageObject {
    id: string | null;
    url: string | null;
    file: string | null;
    title: string | null;
    head: PageHeader;
    content: string | null;
    layout: string | null;
    theme: string | null;
    locale: string | null;
}
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
interface OctoberComponent {
    component: string;
    alias: string;
    class: string;
    props: Props;
    vars: Props;
}
interface OctoberComponents {
    [alias: string]: OctoberComponent;
}
interface OctoberTheme {
    name: string | null;
    description: string | null;
    homepage: string | null;
    author: string | null;
    authorCode: string | null;
    code: string | null;
    options: Props;
}
interface LaikaPayload<PageProps extends Props = Props, SharedProps extends Props = Props> {
    component: string;
    version: string | null;
    theme: OctoberTheme;
    page: PageObject;
    pageProps: PageProps;
    sharedProps: SharedProps;
    components?: OctoberComponents;
    fragments?: Record<string, string>;
    redirect?: string;
}
interface LaikaAppComponentProps<PageProps extends Props = Props, SharedProps extends Props = Props> {
    initialPayload: LaikaPayload<PageProps, SharedProps>;
    initialComponent?: DefineComponent;
    resolveComponent: ResolveCallback;
    title?: ResolveTitle;
}
type LaikaAppComponent<PageProps extends Props = Props, SharedProps extends Props = Props> = DefineComponent<LaikaAppComponentProps<PageProps, SharedProps>>;
type LaikaPlugin = Plugin;
interface LaikaRuntime<PageProps extends Props = Props, SharedProps extends Props = Props> {
    payload: () => LaikaPayload<PageProps, SharedProps> | undefined;
    page: () => LaikaPayload<PageProps, SharedProps>['page'] | undefined;
    visit: (url: string, opts?: {
        replace?: boolean;
        preserveState?: boolean;
    }) => Promise<void>;
    request: (handler: string, data?: Record<string, unknown>) => Promise<unknown>;
    setLayout?: (layout: any) => void;
}
interface LaikaSetup<PageProps extends Props = Props, SharedProps extends Props = Props> {
    root: HTMLElement;
    App: LaikaAppComponent<PageProps, SharedProps> | Component;
    props: LaikaAppComponentProps<PageProps, SharedProps>;
    payload: LaikaPayload<PageProps, SharedProps>;
    plugin: LaikaPlugin;
}
interface LaikaOptions<PageProps extends Props = Props, SharedProps extends Props = Props> {
    title?: (title: string) => string;
    resolve: (name: string) => ResolveResult;
    setup: (options: LaikaSetup<PageProps, SharedProps>) => App;
    rootId?: string;
    onError?: (err: unknown) => void;
}
interface LaikaComposable<PageProps extends Props = Props, SharedProps extends Props = Props> {
    component: DefineComponent | undefined;
    layout: any;
    key: number | undefined;
    payload: LaikaPayload<SharedProps, PageProps> | undefined;
    version: string | null | undefined;
    page: PageObject | undefined;
    pageProps: PageProps | undefined;
    sharedProps: SharedProps | undefined;
    theme: OctoberTheme | undefined;
    components: OctoberComponents | undefined;
    fragments: Record<string, string> | undefined;
    redirect: string | undefined;
    runtime: LaikaRuntime<SharedProps, PageProps>;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $laika: LaikaRuntime;
        $payload: LaikaPayload | undefined;
    }
}
//# sourceMappingURL=globals.d.ts.map

export type { LaikaAppComponent, LaikaAppComponentProps, LaikaComposable, LaikaOptions, LaikaPayload, LaikaPlugin, LaikaRuntime, LaikaSetup, OctoberComponent, OctoberComponents, OctoberTheme, PageHeader, PageObject, Props, ResolveCallback, ResolveResult, ResolveTitle, ResolvedComponent };
