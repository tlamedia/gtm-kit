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

		$deps_file  = $path . 'assets/admin/' . $script_handle . '.asset.php';
		$dependency = [];
		$version    = false;

		if ( \file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		if ( $settings_dependency ) {
			$dependency[] = 'gtmkit-settings-script';
		}

		if ( \file_exists( $path . 'assets/admin/' . $script_handle . '.css' ) ) {
			\wp_enqueue_style( 'gtmkit-' . $script_handle . '-style', $url . 'assets/admin/' . $script_handle . '.css', array( 'wp-components' ), $version );
		}

		\wp_enqueue_script( 'gtmkit-' . $script_handle . '-script', $url . 'assets/admin/' . $script_handle . '.js', $dependency, $version, true );

		if ( $localize ) {
			$this->localize_script( $page_slug, $script_handle );
		}

		\wp_set_script_translations( 'gtmkit-' . $script_handle . '-script', $domain );
	}
}
