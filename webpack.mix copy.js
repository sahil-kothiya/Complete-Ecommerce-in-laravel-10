const mix = require('laravel-mix');
require('laravel-mix-purgecss');
require('laravel-mix-clean');

const CompressionPlugin = require('compression-webpack-plugin');

// Combine ALL CSS files to reduce HTTP requests
mix.styles([
    'public/frontend/css/bootstrap.css',
    'public/frontend/css/style.min.css',
    'public/frontend/css/responsive.css',
    'public/frontend/css/animate.css',
    'public/frontend/css/reset.min.css',
    'public/frontend/css/themify-icons.css',
    'public/frontend/css/jquery.fancybox.min.css',
    'public/frontend/css/magnific-popup.min.css',
    'public/frontend/css/niceselect.css',
    'public/frontend/css/owl-carousel.css',
    'public/frontend/css/flex-slider.min.css'
], 'public/css/all-styles.min.css');

mix.combine([
    'public/frontend/js/jquery.min.js',
    'public/frontend/js/popper.min.js',
    'public/frontend/js/bootstrap.min.js',
    'public/frontend/js/owl-carousel.js',
    'public/frontend/js/magnific-popup.js',
    'public/frontend/js/active.js'
], 'public/js/all-scripts.js');

// Combine ALL scripts into one bundle for simplicity
// mix.combine([
//     'public/frontend/js/jquery.min.js',
//     'public/frontend/js/jquery-migrate-3.0.0.js',
//     'public/frontend/js/popper.min.js',
//     'public/frontend/js/bootstrap.min.js',
//     // 'public/frontend/js/slicknav.min.js',
//     // 'public/frontend/js/waypoints.min.js',
//     // 'public/frontend/js/nicesellect.js',
//     'public/frontend/js/scrollup.js',
//     'public/frontend/js/onepage-nav.min.js',
//     // 'public/frontend/js/easing.js',
//     // 'public/frontend/js/active.js',
//     'public/frontend/js/owl-carousel.js',
//     'public/frontend/js/magnific-popup.js',
//     // 'public/frontend/js/finalcountdown.min.js',
//     'public/frontend/js/isotope/isotope.pkgd.min.js',
//     // 'public/frontend/js/flex-slider.js'
// ], 'public/js/all-scripts.js');

// Keep your existing Vue/Sass compilation
mix.js('resources/js/app.js', 'public/js')
    .vue()
    .sass('resources/sass/app.scss', 'public/css', {
        sassOptions: {
            quietDeps: true, // Suppress Bootstrap deprecation warnings
        }
    })
    .options({
        terser: {
            terserOptions: {
                compress: {
                    drop_console: true,
                    drop_debugger: true,
                    pure_funcs: ['console.log', 'console.info'],
                    passes: 3,
                },
                mangle: {
                    safari10: true,
                },
            },
        },
        processCssUrls: false
    })
    .purgeCss({
        content: [
            './resources/views/**/*.blade.php',
            './resources/js/**/*.vue',
            './resources/js/**/*.js',
        ],
        safelist: ['html', 'body'],
    });

mix.webpackConfig({
    plugins: [
        new CompressionPlugin({
            filename: '[path][base].gz',
            algorithm: 'gzip',
            test: /\.(js|css|html|svg)$/,
            threshold: 1024, // Only compress files larger than 1KB
            minRatio: 0.8,
            deleteOriginalAssets: false, // Keep original files
        }),
        // Add Brotli compression for even better compression
        new CompressionPlugin({
            filename: '[path][base].br',
            algorithm: 'brotliCompress',
            test: /\.(js|css|html|svg)$/,
            compressionOptions: {
                params: {
                    [require('zlib').constants.BROTLI_PARAM_QUALITY]: 11,
                },
            },
            threshold: 1024,
            minRatio: 0.8,
            deleteOriginalAssets: false,
        }),
    ],
});

if (mix.inProduction()) {
    mix.version();
}

mix.clean({
    cleanOnceBeforeBuildPatterns: ['public/frontend/css/*.min.css', 'public/js/*.js']
});