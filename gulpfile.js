var gulp 			= require('gulp'),
	bs 				= require('browser-sync').create(),

	changed 		= require('gulp-changed'),
	plumber 		= require('gulp-plumber'),
	pug 			= require('gulp-pug'),
	watch 			= require('gulp-watch'),
	sass			= require('gulp-sass');	

var reload     	 	= bs.reload;

var path 			= {
						SRCPUG: 'src/**/*.pug',
						_SRCPUG: '!src/**/_*.pug',
						DISTPUG: 'docs/',

						SRCCSS: 'src/css/**/*.scss',
						_SRCCSS: '!src/css/**/_*.scss',
						DISTCSS: 'docs/css/',

						SRCIMG: 'src/images/**/*',
						DISTIMG: 'docs/images/'
					};

//Browser Sync
gulp.task('browser-sync', function() {
	bs.init({
		server: {
			baseDir: "docs/"
		}
	});
});


// BASIC BUILD

gulp.task('pug', function() {
	return gulp.src([ path.SRCPUG, path._SRCPUG ])
		.pipe(pug())
		.pipe(gulp.dest( path.DISTPUG ));
});

gulp.task('css', function () {
	return gulp.src([ path.SRCCSS, path._SRCCSS ])
		.pipe(sass().on('error', sass.logError))
		.pipe(gulp.dest( path.DISTCSS ));
});

gulp.task('images', function() {
	return gulp.src( path.SRCIMG )
		.pipe(gulp.dest( path.DISTIMG ));
});


// WATCH TASKS

gulp.task('watch-pug', function () {
    return watch(path.SRCPUG, function () {
        gulp.src([
			path.SRCPUG, path._SRCPUG
		])
			.pipe(plumber())
			.pipe(pug())
			.pipe(gulp.dest( path.DISTPUG ))
			.pipe(bs.stream());
    });
});


gulp.task('watch-css', function () {
	return watch(path.SRCCSS, function () {
		gulp.src([
				path.SRCCSS, path._SRCCSS
			])
			.pipe(sass().on('error', sass.logError))
			.pipe(gulp.dest( path.DISTCSS ))
			.pipe(bs.stream());
	});
});


gulp.task('watch-images', function () {
    return watch(path.SRCIMG, { ignoreInitial: false })
    	.pipe(plumber())
        .pipe(gulp.dest( path.DISTIMG ))
        .pipe(bs.stream());
});


gulp.task('basic-build', [
		'pug',
		'css',
		'images'
]);

//default
gulp.task('default', [
	'basic-build',
	'browser-sync',
	'watch-pug',
	'watch-css',
	'watch-images'
]);