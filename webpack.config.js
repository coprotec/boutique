import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    // Styles et JS communs à toutes les pages.
    .addEntry('app', './assets/app.js')
    // Composants Vue (registre dans assets/vue/vue.js, pattern /coplanif), chargés sur les pages qui en ont besoin.
    .addEntry('vue', './assets/vue/vue.js')

    // Images référencées depuis Twig via asset('build/images/…') (résolu par le manifest).
    .copyFiles({ from: './assets/images', to: Encore.isProduction() ? 'images/[path][name].[hash:8].[ext]' : 'images/[path][name].[ext]' })

    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabel((config) => {
        config.plugins.push(['polyfill-corejs3', { method: 'usage-global', version: '3.49' }]);
    })
    .enableVueLoader(() => {}, { version: 3, runtimeCompilerBuild: false })
    .configureDefinePlugin((options) => {
        options.__VUE_OPTIONS_API__ = false;
        options.__VUE_PROD_DEVTOOLS__ = false;
        options.__VUE_PROD_HYDRATION_MISMATCH_DETAILS__ = false;
    })
;

export default await Encore.getWebpackConfig();
