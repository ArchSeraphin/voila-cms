/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './app/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary:   '#1e40af',
        secondary: '#64748b',
        accent:    '#f59e0b',
      },
      fontFamily: {
        sans:    ['Inter', 'sans-serif'],
        display: ['Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
