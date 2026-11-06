<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * Adds GTM Kit destinations to the WordPress command palette (Ctrl+K or Cmd+K).
 *
 * WordPress already lists the GTM Kit menu entry. The settings screen keeps
 * its sections inside the app rather than in the admin menu, so each section
 * is added here, with the setup wizard and Google Tag Manager itself.
 *
 * The script loads on every admin page, so it is a few inline lines on top of
 * the command palette WordPress already loads, with no file of its own.
 */
final class CommandPalette {

	/**
	 * The script handle.
	 *
	 * @var string
	 */
	public const HANDLE = 'gtmkit-commands';

	/**
	 * Register the commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		// After WordPress registers the command palette at the default priority.
		add_action( 'admin_enqueue_scripts', [ new self(), 'enqueue' ], 20 );
	}

	/**
	 * Enqueue the commands for users who can open the GTM Kit screens.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( is_network_admin() || ! wp_script_is( 'wp-commands', 'registered' ) ) {
			return;
		}

		$commands = $this->get_commands();

		if ( ! $commands ) {
			return;
		}

		$json = wp_json_encode( $commands, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );

		// A translation that is not valid UTF-8 cannot be encoded, and a broken script would run on every admin page.
		if ( ! $json ) {
			return;
		}

		wp_register_script( self::HANDLE, false, [ 'wp-commands', 'wp-data' ], GTMKIT_VERSION, true );
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script(
			self::HANDLE,
			sprintf(
				'( function ( commands, store ) {
	commands.forEach( function ( command ) {
		store.registerCommand( {
			name: command.name,
			label: command.label,
			callback: function ( options ) {
				options.close();
				if ( command.external ) {
					window.open( command.url, "_blank", "noopener" );
				} else {
					window.location.href = command.url;
				}
			},
		} );
	} );
} )( %s, window.wp.data.dispatch( window.wp.commands.store ) );',
				$json
			)
		);
	}

	/**
	 * The commands the current user may run.
	 *
	 * @return array<int, array{name: string, label: string, url: string, external: bool}>
	 */
	public function get_commands(): array {
		$capability = apply_filters( 'gtmkit_admin_capability', 'manage_options' );

		if ( ! current_user_can( $capability ) ) {
			return [];
		}

		/* translators: %s: The name of a GTM Kit settings section or tool, e.g. "Dashboard". */
		$label    = __( 'GTM Kit: %s', 'gtm-kit' );
		$settings = admin_url( 'admin.php?page=gtmkit_general' );

		$sections = [
			'dashboard'     => __( 'Dashboard', 'gtm-kit' ),
			'setup'         => __( 'Setup', 'gtm-kit' ),
			'gtm-templates' => __( 'GTM Templates', 'gtm-kit' ),
			'events'        => __( 'Events & data layer', 'gtm-kit' ),
			'commerce'      => __( 'Commerce', 'gtm-kit' ),
			'consent'       => __( 'Consent & privacy', 'gtm-kit' ),
			'tools'         => __( 'Tools', 'gtm-kit' ),
			'integrations'  => __( 'Integrations', 'gtm-kit' ),
			'license'       => __( 'License', 'gtm-kit' ),
			'support'       => __( 'Support', 'gtm-kit' ),
		];

		$commands = [];

		foreach ( $sections as $id => $section ) {
			$commands[] = [
				'name'     => 'gtmkit/' . $id,
				'label'    => sprintf( $label, $section ),
				'url'      => $settings . '#/' . $id,
				'external' => false,
			];
		}

		// The wizard page is registered for this capability alone, whatever the filter allows.
		if ( current_user_can( 'manage_options' ) ) {
			$commands[] = [
				'name'     => 'gtmkit/setup-wizard',
				'label'    => sprintf( $label, __( 'Setup Wizard', 'gtm-kit' ) ),
				'url'      => SetupWizard::get_site_url(),
				'external' => false,
			];
		}

		$commands[] = [
			'name'     => 'gtmkit/google-tag-manager',
			'label'    => sprintf( $label, __( 'Open Google Tag Manager', 'gtm-kit' ) ),
			'url'      => 'https://tagmanager.google.com/',
			'external' => true,
		];

		return $commands;
	}
}
