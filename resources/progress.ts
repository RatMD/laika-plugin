import { reactive } from "vue";

const progress = reactive({
    color: '#DE3163',
    active: false,
    percent: 0,
    timestamp: 0
});
let progressTimer: number | null = null;

export function useProgressBar() {
    /**
     * Start ProgressBar
     */
    function start() {
        progress.active = true;
        progress.percent = 10;
        progress.timestamp = Date.now();

        if (progressTimer) {
            window.clearInterval(progressTimer);
        }

        // Tickle up to 90 % to fake some interactivity
        progressTimer = window.setInterval(() => {
            if (!progress.active) return;
            if (progress.percent < 90) {
                progress.percent += Math.max(1, Math.round((90 - progress.percent) * 0.08));
            }
        }, 200);
    }

    /**
     * Done
     * @returns
     */
    function done(force: boolean = false) {
        if (progressTimer) {
            window.clearInterval(progressTimer);
            progressTimer = null;
        }

        if (!progress.active && !force) {
            return;
        }

        progress.percent = 100;

        // Indicate 100%
        window.setTimeout(() => {
            progress.active = false;
            progress.percent = 0;
        }, 150);
    }

    /**
     * Failed
     * @returns
     */
    function fail() {
        done(true);
    }

    // Export Composable
    return {
        state: progress,
        start,
        done,
        fail,
    };
}
