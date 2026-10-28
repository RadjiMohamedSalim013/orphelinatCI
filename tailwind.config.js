/** @type {import('tailwindcss').Config} */

// Les couleurs pointent vers les tokens CSS de assets/styles/app.css (canaux RGB),
// ce qui permet les opacités Tailwind (bg-brand-600/10) et un réglage centralisé.
const token = (name) => `rgb(var(--${name}) / <alpha-value>)`;
const scale = (name, steps) => Object.fromEntries(steps.map((s) => [s, token(`${name}-${s}`)]));
const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];

module.exports = {
    content: [
        './assets/**/*.js',
        './templates/**/*.html.twig',
    ],
    theme: {
        extend: {
            colors: {
                brand: scale('brand', steps),
                trust: scale('trust', steps),
                ink: scale('ink', [...steps, 950]),
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'sans-serif'],
                display: ['"Plus Jakarta Sans"', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                DEFAULT: 'var(--radius-md)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
                xl: 'var(--radius-xl)',
            },
            boxShadow: {
                card: 'var(--shadow-card)',
                raised: 'var(--shadow-raised)',
                overlay: 'var(--shadow-overlay)',
            },
            maxWidth: {
                content: 'var(--content-width)',
            },
            keyframes: {
                'fade-up': { from: { opacity: '0', transform: 'translateY(6px)' }, to: { opacity: '1', transform: 'none' } },
            },
            animation: {
                'fade-up': 'fade-up .18s ease-out both',
            },
        },
    },
    plugins: [],
};
