axis         = require 'axis'
rupture      = require 'rupture'
autoprefixer = require 'autoprefixer'
js_pipeline  = require 'js-pipeline'
css_pipeline = require 'css-pipeline'
precss       = require 'precss'

module.exports =
  ignores: ['readme.md','**/layout.*', '**/_*', '.gitignore', '.gitattributes', 'ship.*conf', 'project.sublime-project']

  extensions: [
    js_pipeline(files: 'assets/js/*.coffee'),
    css_pipeline(files: 'assets/css/*.css')
  ]

  'coffee-script':
    sourcemap: true

  jade:
    pretty: false

  postcss:
    use:[precss(),autoprefixer()]