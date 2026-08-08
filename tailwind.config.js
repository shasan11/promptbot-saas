import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: {
                    50: '#F1F5F9',
                    100: '#E2E8F0',
                    200: '#CBD5E1',
                    300: '#94A3B8',
                    400: '#64748B',
                    500: '#475569',
                    600: '#334155',
                    700: '#1E293B',
                    800: '#0F172A',
                    900: '#0B1220',
                    950: '#070B14',
                },
                brand: {
                    50: '#ECFDF5',
                    100: '#D1FAE5',
                    200: '#A7F3D0',
                    300: '#6EE7B7',
                    400: '#34D399',
                    500: '#10B981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065F46',
                    900: '#064E3B',
                },
            },
            borderRadius: {
                sm: '8px',
                DEFAULT: '10px',
                md: '10px',
                lg: '14px',
                xl: '16px',
            },
            boxShadow: {
                soft: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                'soft-lg': '0 4px 6px -1px rgb(15 23 42 / 0.06), 0 8px 16px -4px rgb(15 23 42 / 0.08)',
            },
            spacing: {
                sidebar: '17rem',
                'sidebar-collapsed': '4.5rem',
                header: '4rem',
            },
            transitionDuration: {
                DEFAULT: '150ms',
            },
        },
    },

    plugins: [forms],
};
