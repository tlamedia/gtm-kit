import { src, dest, watch, series, parallel } from 'gulp';
import yargs from 'yargs';
import dartSass from 'sass';
import gulpSass from 'gulp-sass';
const sass = gulpSass( dartSass );
import cleanCss from 'gulp-clean-css';
import uglify from 'gulp-uglify';
import gulpif from 'gulp-if';
import postcss from 'gulp-postcss';
import sourcemaps from 'gulp-sourcemaps';
import autoprefixer from 'autoprefixer';
import del from 'del';
import zip from "gulp-zip";
import packageJSON from "./package.json";
import replace from "gulp-replace";


export const compress = () => {
	return src([
		"**/*",
		"!node_modules{,/**}",
		"!bundled{,/**}",
		"!.babelrc",
		"!.gitignore",
		"!gulpfile.babel.js",
		"!package.json",
		"!package-lock.json",
	])
		.pipe(zip(`${packageJSON.name}.zip`))
		.pipe(dest('bundled'));
};

const PRODUCTION = yargs.argv.prod;

export const styles = () => {
	return src(['src/scss/admin.scss'])
		.pipe(gulpif(!PRODUCTION, sourcemaps.init()))
		.pipe(sass().on('error', sass.logError))
		.pipe(gulpif(PRODUCTION, postcss([ autoprefixer ])))
		.pipe(gulpif(PRODUCTION, cleanCss()))
		.pipe(gulpif(!PRODUCTION, sourcemaps.write()))
		.pipe(dest('assets/css'));
}

export const scripts = () => {
	return src(['src/js/admin.js', 'src/js/woocommerce.js', 'src/js/woocommerce-checkout.js', 'src/js/contact-form-7.js'])
		.pipe(gulpif(!PRODUCTION, sourcemaps.init()))
		.pipe(gulpif(PRODUCTION, uglify()))
		.pipe(gulpif(!PRODUCTION, sourcemaps.write()))
		.pipe(dest('assets/js'));
}

export const images = () => {
	return src('src/images/**/*.{jpg,jpeg,png,svg,gif}')
		.pipe(dest('assets/images'));
}

export const watchForChanges = () => {
	watch('src/scss/**/*.scss', styles);
	watch('src/images/**/*.{jpg,jpeg,png,svg,gif}', images);
}

export const replace_version = () => {
	return src( [ 'gtm-kit.php', 'readme.txt' ] )
		.pipe(
			// File header.
			replace(
				/Version:     ((\*)|([0-9]+(\.((\*)|([0-9]+(\.((\*)|([0-9]+)))?)))?))/gm,
				'Version:     ' + packageJSON.version
			)
		)
		.pipe(
			// PHP constant.
			replace(
				/define\( 'GTMKIT_VERSION', '((\*)|([0-9]+(\.((\*)|([0-9]+(\.((\*)|([0-9]+)))?)))?))' \);/gm,
				'define( \'GTMKIT_VERSION\', \'' + packageJSON.version + '\' );'
			)
		)
		.pipe(
			// stable tag.
			replace(
				/Stable tag: ((\*)|([0-9]+(\.((\*)|([0-9]+(\.((\*)|([0-9]+)))?)))?))/gm,
				'Stable tag: ' + packageJSON.version
			)
		)
		.pipe( dest( './' ) );
}


export const clean = () => del(['assets']);

export const dev = series(clean, parallel(styles, scripts, images), watchForChanges)
export const build = series(clean, parallel(styles, images, scripts), replace_version, compress);
export default dev;

