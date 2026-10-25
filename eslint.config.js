import js from '@eslint/js';
import globals from 'globals';
import { defineConfig, globalIgnores } from 'eslint/config';

// Sources are browser ES modules that Vite bundles. Nothing here knows about frameworks: the ruleset
// is ESLint's own recommended set, plus the globals each file actually has.
export default defineConfig([
    // Build output, dependencies and generated caches are not ours to lint.
    globalIgnores(['node_modules/', 'public/', 'vendor/', 'var/']),
    {
        files: ['**/*.js', '**/*.mjs'],
        extends: [js.configs.recommended],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: globals.browser,
        },
    },
    {
        // These run in Node, not in the page, so `process` and friends are legitimate there.
        files: ['eslint.config.js', 'vite.config.mjs'],
        languageOptions: {
            globals: globals.node,
        },
    },
]);
