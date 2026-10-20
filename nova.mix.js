const mix = require('laravel-mix')

/** Vue comes from Nova at runtime; bundling a second copy would break it. */
class NovaExtension {
  name() {
    return 'nova-extension'
  }

  register(name) {
    this.name = name
  }

  webpackConfig(webpackConfig) {
    webpackConfig.externals = { vue: 'Vue' }
    webpackConfig.output = { uniqueName: this.name }
  }
}

mix.extend('nova', new NovaExtension())
