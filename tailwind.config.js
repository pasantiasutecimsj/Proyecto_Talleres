import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                azul: {
                    DEFAULT: '#023CA1',
                    dark: '#0B2E6A',
                    extraDark: '#0C2C5E',
                    claro: '#195cd1',
                },
                amarillo: {
                    DEFAULT: '#FFDE21',
                },
                rojo: {
                    DEFAULT: '#EF4B4F',
                    dark: '#D32F2F',
                },
                verde: {
                    DEFAULT: '#00A859',
                },
            },
            boxShadow: {
                azul: '0px 0px 3px #023CA1',
            },
        },
    },

    plugins: [forms],
};
