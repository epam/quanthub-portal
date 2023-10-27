module.exports = {
  content: ["**/*.twig"],
  theme: {
    extend: {
      screens: {
        '2xl': {'max': '1919px'},
        'xl': {'max': '1536px'},
        'lg': {'max': '1279px'},
        'mobile-menu': {'max': '1119px'},
        'md': {'max': '1023px'},
        'sm': {'max': '719px'},
        'xs': {'max': '428px'},
      },
    },
  },
  variants: {
    extend: {},
  },
  plugins: [],
};
