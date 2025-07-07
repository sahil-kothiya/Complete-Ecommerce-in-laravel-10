const mix = require('laravel-mix');
require('laravel-mix-purgecss'); // ⬅️ Important

const CompressionPlugin = require('compression-webpack-plugin');

mix.js('resources/js/app.js', 'public/js')
    .vue()
   .sass('resources/sass/app.scss', 'public/css')
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
   .purgeCss({ // ⬅️ Only removes unused CSS in production
       content: [
           './resources/views/**/*.blade.php',
           './resources/js/**/*.vue',
           './resources/js/**/*.js',
       ],
       safelist: ['html', 'body'], // ⬅️ keep base tags safe
   });

mix.webpackConfig({
   plugins: [
       new CompressionPlugin({
           filename: '[path][base].gz',
           algorithm: 'gzip',
           test: /\.(js|css|html|svg)$/,
           threshold: 8192,
           minRatio: 0.8,
       }),
   ],
});

if (mix.inProduction()) {
   mix.version();
}
