/**
 *
 * @param mod
 * @returns
 */
export function unwrapModule<T>(mod: any): T {
    return mod?.default ?? mod;
}
