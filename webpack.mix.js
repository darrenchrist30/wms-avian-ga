const mix = require("laravel-mix");

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js("resources/js/app.js", "public/js")
    .postCss("resources/css/app.css", "public/css", [
        //
    ])
    // AdminLTE CSS
    .copy(
        "node_modules/admin-lte/dist/css/adminlte.min.css",
        "public/css/adminlte.min.css"
    )
    // AdminLTE JS
    .copy(
        "node_modules/admin-lte/dist/js/adminlte.min.js",
        "public/js/adminlte.min.js"
    )
    // Bootstrap (required by AdminLTE)
    .copy(
        "node_modules/bootstrap/dist/js/bootstrap.bundle.min.js",
        "public/js/bootstrap.bundle.min.js"
    );
