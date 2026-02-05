import type { App, Component, DefineComponent, Plugin } from 'vue';

export type Props = Record<string, unknown>;

export interface PageHeader extends Props {
    title: string | null;
    meta_title: string | null;
    meta_description: string | null;
}

export interface PageObject {
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

export type ResolveResult = DefineComponent
                          | Promise<DefineComponent>
                          | { default: DefineComponent }
                          | Promise<{ default: DefineComponent }>;

export type ResolveCallback = (name: string) => ResolveResult;

export type ResolveTitle = (title: string) => string;

export type ResolvedComponent = DefineComponent & { layout?: any; inheritAttrs?: boolean };

export interface LaikaPayload<PageProps extends Props = Props, SharedProps extends Props = Props> {
    component: string;
    version: string | null;
    page: PageObject;
    pageProps: PageProps;
    sharedProps: SharedProps;
    fragments?: Record<string, string>;
    redirect?: string;
}

export interface LaikaAppComponentProps<PageProps extends Props = Props, SharedProps extends Props = Props> {
    initialPayload: LaikaPayload<PageProps, SharedProps>;
    initialComponent?: DefineComponent;
    resolveComponent: ResolveCallback;
    title?: ResolveTitle;
}

export type LaikaAppComponent<PageProps extends Props = Props, SharedProps extends Props = Props>
    = DefineComponent<LaikaAppComponentProps<PageProps, SharedProps>>;

export type LaikaPlugin = Plugin;

export interface LaikaRuntime<PageProps extends Props = Props, SharedProps extends Props = Props> {
    payload: () => LaikaPayload<PageProps, SharedProps> | undefined;
    page: () => LaikaPayload<PageProps, SharedProps>['page'] | undefined;
    visit: (url: string, opts?: { replace?: boolean; preserveState?: boolean }) => Promise<void>;
    request: (handler: string, data?: Record<string, unknown>) => Promise<unknown>;
    setLayout?: (layout: any) => void;
}

export interface LaikaSetup<PageProps extends Props = Props, SharedProps extends Props = Props> {
    root: HTMLElement;
    App: LaikaAppComponent<PageProps, SharedProps> | Component;
    props: LaikaAppComponentProps<PageProps, SharedProps>;
    payload: LaikaPayload<PageProps, SharedProps>;
    plugin: LaikaPlugin;
}

export interface LaikaOptions<PageProps extends Props = Props, SharedProps extends Props = Props> {
    title?: (title: string) => string;
    resolve: (name: string) => ResolveResult;
    setup: (options: LaikaSetup<PageProps, SharedProps>) => App;
    rootId?: string;
    onError?: (err: unknown) => void;
}

export interface LaikaComposable<PageProps extends Props = Props, SharedProps extends Props = Props> {
    component: DefineComponent | undefined;
    layout: any;
    key: number | undefined;
    payload: LaikaPayload<SharedProps, PageProps> | undefined;
    version: string | null | undefined;
    page: PageObject | undefined;
    pageProps: PageProps | undefined;
    sharedProps: SharedProps | undefined;
    fragments: Record<string, string> | undefined;
    redirect: string | undefined;
    runtime: LaikaRuntime<SharedProps, PageProps>;
}
