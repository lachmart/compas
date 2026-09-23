import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Player-screen animations; always used behind `motion-safe:`.
            keyframes: {
                shake: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%, 60%': { transform: 'translateX(-10px) rotate(-2deg)' },
                    '40%, 80%': { transform: 'translateX(10px) rotate(2deg)' },
                },
                'shake-soft': {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '25%, 75%': { transform: 'translateX(-4px)' },
                    '50%': { transform: 'translateX(4px)' },
                },
                pop: {
                    '0%': { transform: 'scale(0)', opacity: '0' },
                    '60%': { transform: 'scale(1.15)', opacity: '1' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                // Big star pops in the middle, then flies up to the HUD stars.
                'star-fly': {
                    '0%': { transform: 'translate(0, 0) scale(0) rotate(-30deg)', opacity: '0' },
                    '25%': { transform: 'translate(0, 0) scale(1.2) rotate(8deg)', opacity: '1' },
                    '40%': { transform: 'translate(0, 0) scale(1) rotate(0deg)', opacity: '1' },
                    '65%': { transform: 'translate(0, 0) scale(1) rotate(0deg)', opacity: '1' },
                    '100%': { transform: 'translate(28vw, -42dvh) scale(0.15) rotate(360deg)', opacity: '0.7' },
                },
                'confetti-fall': {
                    '0%': { transform: 'translate3d(0, -5vh, 0) rotate(0deg)', opacity: '1' },
                    '85%': { opacity: '1' },
                    '100%': { transform: 'translate3d(var(--drift, 0), 105dvh, 0) rotate(var(--spin, 540deg))', opacity: '0' },
                },
            },
            animation: {
                shake: 'shake 0.5s ease-in-out',
                'shake-soft': 'shake-soft 0.4s ease-in-out',
                pop: 'pop 0.4s ease-out both',
                'star-fly': 'star-fly 1.6s ease-in-out both',
                'confetti-fall': 'confetti-fall 1.5s ease-in both',
            },
        },
    },

    plugins: [forms],
};
