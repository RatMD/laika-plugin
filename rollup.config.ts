import path from "node:path";
import resolve from "@rollup/plugin-node-resolve";
import commonjs from "@rollup/plugin-commonjs";
import terser from "@rollup/plugin-terser";
import typescript from "@rollup/plugin-typescript";
import dts from "rollup-plugin-dts";

const input = "resources/index.ts";
const outDir = "assets/dist";

export default [
    {
        input,
        output: [
            {
                name: 'Laika',
                file: path.join(outDir, "laika.mjs"),
                format: "esm",
                sourcemap: true,
                globals: {
                    vue: 'Vue'
                }
            },
            {
                name: 'Laika',
                file: path.join(outDir, "laika.cjs"),
                format: "cjs",
                sourcemap: true,
                exports: "named",
                globals: {
                    vue: 'Vue'
                }
            },
            {
                name: 'Laika',
                file: path.join(outDir, "laika.js"),
                format: "umd",
                sourcemap: true,
                globals: {
                    vue: 'Vue'
                }
            },
        ],
        plugins: [
            resolve({ preferBuiltins: true }),
            commonjs(),
            typescript({ tsconfig: "./tsconfig.json", outputToFilesystem: true }),
            terser()
        ],
        external: ["vue"],
    },
    {
        input: "assets/dist/types/index.d.ts",
        output: [{ file: path.join(outDir, "laika.d.ts"), format: "es" }],
        plugins: [dts()],
        external: ["vue"],
    }
];
