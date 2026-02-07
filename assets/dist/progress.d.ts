export declare function useProgressBar(): {
    state: {
        color: string;
        active: boolean;
        percent: number;
        timestamp: number;
    };
    start: () => void;
    done: (force?: boolean) => void;
    fail: () => void;
};
//# sourceMappingURL=progress.d.ts.map