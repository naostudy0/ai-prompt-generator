import js from '@eslint/js';
import globals from 'globals';
import { defineConfig, globalIgnores } from 'eslint/config';

export default defineConfig([
    globalIgnores([
        'vendor/**',
        'node_modules/**',
        'public/**',
        'storage/**',
        'bootstrap/cache/**',
    ]),
    {
        files: ['**/*.{js,mjs,cjs}'],
        extends: [js.configs.recommended],
        rules: {
            eqeqeq: ['error', 'always'],
            curly: ['error', 'all'],
            'no-var': 'error',
            'prefer-const': 'error',
            'no-console': 'error',
        },
    },
    {
        files: ['resources/js/**/*.{js,mjs}'],
        languageOptions: { globals: globals.browser },
    },
    {
        files: ['*.config.{js,mjs,cjs}', 'scripts/**/*.{js,mjs,cjs}'],
        languageOptions: { globals: globals.node },
    },
]);
