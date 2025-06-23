const mix = require('laravel-mix');

// Compile JS and SASS assets
mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css');

// Enable versioning in production
if (mix.inProduction()) {
    mix.version();
}
