import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            keyframes: {
                // Kilau yang menyapu dari kiri ke kanan di atas kotak skeleton.
                shimmer: { '100%': { transform: 'translateX(100%)' } },
            },
            animation: {
                shimmer: 'shimmer 1.4s ease-in-out infinite',
            },
            fontFamily: {
                // Inter dipakai halaman pelanggan; halaman admin/Breeze tetap Figtree lewat layout masing-masing.
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                inter: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Warna merek halaman pelanggan (merah-oranye "Mie Gacor").
                brand: {
                    50: '#fff6f4',
                    100: '#ffe7e2',
                    200: '#ffd3ca',
                    300: '#f9a99a',
                    400: '#ee7660',
                    500: '#e2503a',
                    600: '#d9381e',
                    700: '#b81f0b',
                    800: '#921808',
                    900: '#6d1206',
                },
            },
        },
    },

    plugins: [forms],
};
