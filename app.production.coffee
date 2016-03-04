axis         = require 'axis'
rupture      = require 'rupture'
autoprefixer = require 'autoprefixer'
js_pipeline  = require 'js-pipeline'
css_pipeline = require 'css-pipeline'
precss       = require 'precss'

module.exports =
  ignores: ['readme.md','**/layout.*', '**/_*', '.gitignore', 'ship.*conf', 'project.sublime-project']

  extensions: [
    js_pipeline(files: 'assets/js/*.coffee', out: 'js/build.js', minify: true, hash: true),
    css_pipeline(files: 'assets/css/*.css', out: 'css/build.css', minify: true, hash: true)
  ]

  postcss:
    use:[precss(),autoprefixer()]