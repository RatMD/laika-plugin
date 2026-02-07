import { defineComponent, h, PropType, SlotsType, VNodeChild, type DefineComponent } from "vue";
import { useLaika } from "./app";
import { useProgressBar } from "./progress";
import { OctoberComponent } from "./types";

/**
 * Render ProgressBar
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


export interface PageComponentProps {
    name: string;
}

export interface PageComponentSlots {
    default(props: OctoberComponent): VNodeChild;
}

/**
 * Render Page Component
 */
export const PageComponent: DefineComponent<
    PageComponentProps,
    {}, {}, {}, {}, {}, {}, {}, string, any, any,
    PageComponentSlots
> = defineComponent({
    /**
     * Component Properties
     */
    props: {
        name: {
            type: String as PropType<string>,
            required: true
        },
    },

    /**
     * Component Slots
     */
    slots: Object as SlotsType<{
        default: (props: OctoberComponent) => VNodeChild,
    }>,

    /**
     *
     * @param props
     * @returns
     */
    setup(props, { slots }) {
        const laika = useLaika();

        return () => {
            if (!(laika.components && props.name in laika.components)) {
                return null;
            }

            const componentData = laika.components[props.name] as OctoberComponent || undefined;
            if (!componentData) {
                return null;
            }

            const children = slots.default?.({ ...componentData });
            return h('div', { }, children ?? void 0);
        };
    }
});
