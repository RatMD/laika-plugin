import { defineComponent, h, type DefineComponent } from "vue";
import { useLaika } from "./app";
import { useProgressBar } from "./progress";

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

/**
 * Render Page Content
 */
export const ProgressBar: DefineComponent = defineComponent({
    /**
     *
     * @param props
     * @returns
     */
    setup(props) {
        const progress = useProgressBar();
        return () => {
            if (!progress.state.active) {
                return;
            } else {
                return h("div", {
                    class: "laika-progress-bar",
                    style: {
                        top: 0,
                        left: 0,
                        width: `${progress.state.percent}%`,
                        height: '0.2rem',
                        position: 'fixed',
                        backgroundColor: `var(--laika-progress-bar, ${progress.state.color})`,
                        transition: 'width 120ms linear',
                        zIndex: 99999
                    },
                });
            }
        };
    }
});
