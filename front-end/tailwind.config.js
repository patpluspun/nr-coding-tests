/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["**/*.{html,js}"],
  theme: {
    screens: {
      'md': '768px',
      'lg': '1024px'
    },
    extend: {
      fontFamily: {
        'sans': ['"Open Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
