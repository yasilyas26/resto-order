import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // app.js = admin/Breeze, visitor.js = halaman pelanggan, staff.js = papan karyawan.
            // Dipisah supaya tiap kelompok pengguna hanya mengunduh JavaScript miliknya.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/visitor.js', 'resources/js/staff.js'],
            refresh: true,
        }),
    ],
});
