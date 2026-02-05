import type { DefineComponent, App, Plugin } from 'vue';

export type PageProps = Record<string, unknown>;

export interface LaikaPageInfo {
    url: string;
    component: string;
    locale?: string;
}

export interface LaikaHead {
    title?: string;
    html?: string[];
}

export interface LaikaPayload<SharedProps extends PageProps = PageProps, Props extends PageProps = PageProps> {
    component: string;
    url: string;
    version: string | null;
    page?: Record<string, unknown>;
    head?: LaikaHead;
    fragments?: Record<string, string>;
    redirect?: string;
    props: {
        shared: SharedProps;
        page: LaikaPageInfo;
    } & Props;
}

export interface LaikaPluginApi<SharedProps extends PageProps = PageProps, Props extends PageProps = PageProps> {
    page: () => LaikaPayload<SharedProps, Props>;
    visit: (url: string, opts?: { replace?: boolean }) => Promise<void>;
    request: (handler: string, data?: Record<string, unknown>) => Promise<unknown>;
}

export interface LaikaSetup<SharedProps extends PageProps, Props extends PageProps> {
    root: HTMLElement;
    App: DefineComponent;
    props: LaikaPayload<SharedProps, Props>['props']['page'];
    payload: LaikaPayload<SharedProps, Props>;
    plugin: Plugin;
    laika: LaikaPluginApi<SharedProps, Props>;
}

export interface LaikaOptions<SharedProps extends PageProps, Props extends PageProps = PageProps> {
    title?: (title: string) => string;
    resolve: (name: string) =>
        | DefineComponent
        | Promise<DefineComponent>
        | { default: DefineComponent }
        | Promise<{ default: DefineComponent }>;
    setup: (options: LaikaSetup<SharedProps, Props>) => App;
    rootId?: string;
    onError?: (err: unknown) => void;
}
