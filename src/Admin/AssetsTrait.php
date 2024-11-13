<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

trait AssetsTrait {

	/**
	 * Enqueue assets.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 * @param string $path The plugin path.
	 * @param string $url The plugin URL.
	 * @param string $domain The translation domain.
	 * @param bool   $localize Localize the script.
	 * @param bool   $settings_dependency Is it depending on the settings script.
	 */
	protected function enqueue_assets( string $page_slug, string $script_handle, string $path = '', string $url = '', string $domain = 'gtm-kit', bool $localize = true, bool $settings_dependency = false ): void {
		if ( empty( $path ) ) {
			$path = GTMKIT_PATH;
		}
		if ( empty( $url ) ) {
			$url = GTMKIT_URL;
		}

		$deps_file  = \realpath( $path . 'assets/admin/' . $script_handle . '.asset.php' );
		$dependency = [];
		$version    = false;

		// Ensure the file is within the expected directory.
		if ( $deps_file && \strpos( $deps_file, \realpath( $path . 'assets/admin/' ) ) === 0 && \file_exists( $deps_file ) ) {
			$deps_data  = require $deps_file; // nosemgrep.
			$dependency = $deps_data['dependencies'];
			$version    = $deps_data['version'];
		}

		// Polyfill for WordPress versions earlier than 6.6.
		if ( in_array( 'react-jsx-runtime', $dependency, true ) && ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			wp_register_script(
				'react-jsx-runtime',
				GTMKIT_URL . 'assets/react-jsx-runtime.js',
				[ 'react' ],
				'18.3.1',
				true
			);
		}

		if ( $settings_dependency ) {
			$dependency[] = 'gtmkit-settings-script';
		}

		if ( \file_exists( $path . 'assets/admin/' . $script_handle . '.css' ) ) {
			\wp_enqueue_style( 'gtmkit-' . $script_handle . '-style', $url . 'assets/admin/' . $script_handle . '.css', [ 'wp-components' ], $version );
		}

		\wp_enqueue_script( 'gtmkit-' . $script_handle . '-script', $url . 'assets/admin/' . $script_handle . '.js', $dependency, $version, true );

		if ( $localize ) {
			$this->localize_script( $page_slug, $script_handle );
		}

		\wp_set_script_translations( 'gtmkit-' . $script_handle . '-script', $domain );
	}
}
