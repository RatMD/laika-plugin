import { DefineComponent, Plugin, App } from 'vue';

type PageProps = Record<string, unknown>;
interface LaikaPageInfo {
    url: string;
    component: string;
    locale?: string;
}
interface LaikaHead {
    title?: string;
    html?: string[];
}
interface LaikaPayload<SharedProps extends PageProps = PageProps, Props extends PageProps = PageProps> {
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
interface LaikaPluginApi<SharedProps extends PageProps = PageProps, Props extends PageProps = PageProps> {
    page: () => LaikaPayload<SharedProps, Props>;
    visit: (url: string, opts?: {
        replace?: boolean;
    }) => Promise<void>;
    request: (handler: string, data?: Record<string, unknown>) => Promise<unknown>;
}
interface LaikaSetup<SharedProps extends PageProps, Props extends PageProps> {
    root: HTMLElement;
    App: DefineComponent;
    props: LaikaPayload<SharedProps, Props>['props'];
    payload: LaikaPayload<SharedProps, Props>;
    laikaPlugin: Plugin;
    laika: LaikaPluginApi<SharedProps, Props>;
}
interface LaikaOptions<SharedProps extends PageProps, Props extends PageProps = PageProps> {
    title?: (title: string) => string;
    resolve: (name: string) => DefineComponent | Promise<DefineComponent> | {
        default: DefineComponent;
    } | Promise<{
        default: DefineComponent;
    }>;
    setup: (options: LaikaSetup<SharedProps, Props>) => App;
    rootId?: string;
    onError?: (err: unknown) => void;
}

export type { LaikaHead, LaikaOptions, LaikaPageInfo, LaikaPayload, LaikaPluginApi, LaikaSetup, PageProps };
