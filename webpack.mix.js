const mix = require("laravel-mix");
const path = require("path");
const webpack = require("webpack");
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */
mix.js("resources/js/app.js", "public/js");
mix.sass("resources/js/src/himoto-app.scss", "public/css");
mix.version();

mix.copy(
    "node_modules/html2pdf.js/dist/html2pdf.bundle.min.js",
    "public/vendor/html2pdf.bundle.min.js"
);

if (!mix.inProduction()) {
    mix.sourceMaps();
}

mix.webpackConfig({
    plugins: [
        new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/),
        new webpack.DefinePlugin({
            "process.env.BASE_URL": JSON.stringify("/")
        })
    ],
    resolve: {
        alias: {
            vue$: "vue/dist/vue.runtime.esm.js",
            // eslint-disable-next-line no-undef
            "@": path.resolve(__dirname, "resources/js/src/")
        }
    }
});
