DESCRIPTION
-----------

Tailwind CSS Starter Kit Theme for Drupal 8.

USAGE
-----

```
mkdir -p themes/custom
cd themes/custom
git clone https://git.drupalcode.org/project/tailwindcss.git
```

To re-compile the CSS:

```
npm install
npm run build
```

BUILD COMMANDS
--------------

- Use `npm run dev` or `yarn dev` to generate the un-minified, expanded CSS.
- This is intended for local development. You can also use `npm run watch` or
  `yarn watch` to re-compile automatically when changes are made.
- Use `npm run prod` or `yarn prod` to generate the minified, compressed CSS.
  These are the versions intended for use on production.

CONFIGURATION OVERRIDES
-----------------------

Overrides made to the default tailwind.js file:

- `options.prefix` has been changed to `tw-`. This is so that some of
  Tailwind's default classes like `block` don't apply to Drupal's default
  markup.
- `options.important` has been changed to `true`.

OPTIMISING FOR PRODUCTION
-------------------------

The theme's `webpack.mix.js` file contains a section on adding PurgeCSS to the
build to remove any unused classes and reduce the generated file size. As we
want the generated CSS to be un-purged by default so people can experiment
with the theme and Tailwind on sites like simplytest.me, this is commented out
by default.

Un-comment the lines to enable PurgeCSS, and any classes not added within the
'templates' directory will be removed from the next production build.

If you add any classes programmatically, such as within a preprocess function,
it can be whitelisted using `whitelistPatterns` or `whitelistPatternsChildren`.
