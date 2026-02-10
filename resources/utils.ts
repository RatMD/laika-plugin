/**
 *
 * @param mod
 * @returns
 */
export function unwrapModule<T>(mod: any): T {
    return mod?.default ?? mod;
}

/**
 *
 * @param header
 * @returns
 */
export function parseOnlyHeader(header: string | null): string[] {
    if (!header) {
        return [];
    } else {
        return header.split(',').map(s => s.trim()).filter(Boolean);
    }
}

/**
 *
 * @param obj
 * @param path
 * @returns
 */
export function getByPath(obj: any, path: string): any {
    return path.split('.').reduce((acc, key) => (acc == null ? undefined : acc[key]), obj);
}

/**
 *
 * @param obj
 * @param path
 * @param value
 */
export function setByPath(obj: any, path: string, value: any) {
    const parts = path.split('.');
    if (parts.length === 0) {
        return;
    }

    const last = parts[parts.length - 1];
    let cur = obj;

    for (let i = 0; i < parts.length - 1; i++) {
        const key = parts[i] as string;
        if (cur[key] == null || typeof cur[key] !== 'object') {
            cur[key] = {};
        }
        cur = cur[key];
    }

    cur[last as string] = value;
}
