import type { ComponentCustomProperties } from "vue";
import type { LaikaRuntime, LaikaPayload } from "./laika";

declare module 'vue' {
    export interface ComponentCustomProperties {
        $laika: LaikaRuntime;
        $payload: LaikaPayload | undefined;
    }
}

// Module
export {};
