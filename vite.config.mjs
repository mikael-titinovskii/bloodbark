import { resolve } from 'node:path';
import { defineConfig } from 'vite';

// Plain Vite, nothing Symfony-aware. Entries under assets/ are bundled to fixed paths under
// public/assets/, so a template links them directly (`/assets/app.js`) and there is no manifest
// to resolve. Add an entry below, then a <script> tag in the template that needs it.
const root = import.meta.dirname;

export default defineConfig({
    // Vite's default publicDir is <root>/public, which here is the web root holding Symfony's
    // front controller; left on, the build would copy index.php into the output.
    publicDir: false,
    build: {
        outDir: resolve(root, 'public/assets'),
        // Only ever empties public/assets, which holds nothing but build output.
        emptyOutDir: true,
        sourcemap: true,
        rollupOptions: {
            input: {
                app: resolve(root, 'assets/app.js'),
                'pages/home': resolve(root, 'assets/pages/home.js'),
            },
            // Stable names, no content hashes: a template can name the built file, and nginx
            // revalidates them instead of caching them for a year.
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: '[name][extname]',
            },
        },
    },
});
