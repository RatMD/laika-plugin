import { VNodeChild, type DefineComponent } from "vue";
import { OctoberComponent } from "./types";
/**
 * Render ProgressBar
 */
export declare const ProgressBar: DefineComponent;
/**
 * Render Page Content
 */
export declare const PageContent: DefineComponent;
export interface PageComponentProps {
    name: string;
}
export interface PageComponentSlots {
    default(props: OctoberComponent): VNodeChild;
}
/**
 * Render Page Component
 */
export declare const PageComponent: DefineComponent<PageComponentProps, {}, {}, {}, {}, {}, {}, {}, string, any, any, PageComponentSlots>;
//# sourceMappingURL=components.d.ts.map