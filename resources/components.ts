import { defineComponent, h, type DefineComponent } from "vue";
import { useLaika } from "./app";

/**
 * Render Page Content
 */
export const PageContent: DefineComponent = defineComponent({
    /**
     *
     * @param props
     * @returns
     */
    setup(props) {
        const laika = useLaika();
        return () => {
            const innerHTML = laika.page?.content ?? "";
            return h('div', { innerHTML });
        };
    }
});
