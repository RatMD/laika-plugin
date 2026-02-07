import type { LaikaRuntime, LaikaPayload } from "./laika";
declare module 'vue' {
    interface ComponentCustomProperties {
        $laika: LaikaRuntime;
        $payload: LaikaPayload | undefined;
    }
}
export {};
//# sourceMappingURL=globals.d.ts.map