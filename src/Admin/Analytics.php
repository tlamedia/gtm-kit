<?php

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Options;

class Analytics {

	/**
	 * Instance of this class
	 *
	 * @var Analytics
	 */
	public static $instance;

	/**
	 * Register analytics
	 */
	public static function register(): void {
		self::$instance = $page = new static();

		if ( $page->send_analytics_data() ) {
			add_action( 'admin_print_scripts', [ $page, 'add_mixpanel_script' ] );
		}

	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Analytics
	 */
	public static function get_instance(): Analytics {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Add Mixpanel tracking script
	 */
	public function add_mixpanel_script(): void {
		?>
		<!-- start Mixpanel -->
		<script type="text/javascript">
			(function(f,b){if(!b.__SV){var e,g,i,h;window.mixpanel=b;b._i=[];b.init=function(e,f,c){function g(a,d){var b=d.split(".");2==b.length&&(a=a[b[0]],d=b[1]);a[d]=function(){a.push([d].concat(Array.prototype.slice.call(arguments,0)))}}var a=b;"undefined"!==typeof c?a=b[c]=[]:c="mixpanel";a.people=a.people||[];a.toString=function(a){var d="mixpanel";"mixpanel"!==c&&(d+="."+c);a||(d+=" (stub)");return d};a.people.toString=function(){return a.toString(1)+".people (stub)"};i="disable time_event track track_pageview track_links track_forms track_with_groups add_group set_group remove_group register register_once alias unregister identify name_tag set_config reset opt_in_tracking opt_out_tracking has_opted_in_tracking has_opted_out_tracking clear_opt_in_out_tracking start_batch_senders people.set people.set_once people.unset people.increment people.append people.union people.track_charge people.clear_charges people.delete_user people.remove".split(" ");
				for(h=0;h<i.length;h++)g(a,i[h]);var j="set set_once union unset remove delete".split(" ");a.get_group=function(){function b(c){d[c]=function(){call2_args=arguments;call2=[c].concat(Array.prototype.slice.call(call2_args,0));a.push([e,call2])}}for(var d={},e=["get_group"].concat(Array.prototype.slice.call(arguments,0)),c=0;c<j.length;c++)b(j[c]);return d};b._i.push([e,f,c])};b.__SV=1.2;e=f.createElement("script");e.type="text/javascript";e.async=!0;e.src="undefined"!==typeof MIXPANEL_CUSTOM_LIB_URL?
				MIXPANEL_CUSTOM_LIB_URL:"file:"===f.location.protocol&&"//cdn.mxpnl.com/libs/mixpanel-2-latest.min.js".match(/^\/\//)?"https://cdn.mxpnl.com/libs/mixpanel-2-latest.min.js":"//cdn.mxpnl.com/libs/mixpanel-2-latest.min.js";g=f.getElementsByTagName("script")[0];g.parentNode.insertBefore(e,g)}})(document,window.mixpanel||[]);

			mixpanel.init('a84d538948ddda17265f86785c80ca37', {
				'ip': false,
				'property_blacklist': ['$initial_referrer', '$current_url', '$initial_referring_domain', '$referrer', '$referring_domain']
			});
			mixpanel.track('GTM Kit', <?php echo wp_json_encode( $this->get_analytics_data() ); ?> );
		</script>
		<!-- end Mixpanel -->
		<?php
	}

	/**
	 * Determines if we should send the analytics data
	 *
	 * @return bool True if we should send them, false otherwise
	 */
	function send_analytics_data(): bool {
		if ( ! Options::init()->get( 'general', 'analytics_active' ) ) {
			return false;
		}

		if ( ! current_user_can( 'administrator' ) ) {
			return false;
		}

		if ( false === get_transient( 'gtmkit_send_analytics_data' ) ) {
			set_transient( 'gtmkit_send_analytics_data', 1, 7 * DAY_IN_SECONDS );

			return true;
		}

		return false;
	}

	/**
	 * Gets all data to send to the analytics system
	 *
	 * @return array An array of data
	 */
	function get_analytics_data(): array {
		global $wp_version, $is_nginx, $is_apache, $is_iis7, $is_IIS;

		$options = Options::init()->get_all_raw();
		if ( ! $options ) {
			return [];
		}

		unset( $options['general']['gtm_id'] );

		$anonymize_general_options = [ 'datalayer_name', 'sgtm_domain', 'sgtm_container_identifier' ];

		foreach ( $anonymize_general_options as $option ) {
			if ( ! empty( $options['general'][ $option ] ) ) {
				$options['general'][ $option ] = $option;
			}
		}

		$data['options']    = $options;
		$theme              = wp_get_theme();
		$locale             = explode( '_', get_locale() );
		$data['web_server'] = 'Unknown';

		if ( $is_nginx ) {
			$data['web_server'] = 'NGINX';
		} elseif ( $is_apache ) {
			$data['web_server'] = 'Apache';
		} elseif ( $is_iis7 ) {
			$data['web_server'] = 'IIS 7';
		} elseif ( $is_IIS ) {
			$data['web_server'] = 'IIS';
		}

		$data['php_version']       = preg_replace( '@^(\d\.\d+).*@', '\1', phpversion() );
		$data['wordpress_version'] = preg_replace( '@^(\d\.\d+).*@', '\1', $wp_version );
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$wc_version                  = get_plugin_data( GTMKIT_PATH . '../woocommerce/woocommerce.php' )['Version'];
			$data['woocommerce_version'] = preg_replace( '@^(\d\.\d+).*@', '\1', $wc_version );
		}
		if ( is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
			$edd_version         = get_plugin_data( GTMKIT_PATH . '../easy-digital-downloads/easy-digital-downloads.php' )['Version'];
			$data['edd_version'] = preg_replace( '@^(\d\.\d+).*@', '\1', $edd_version );
		}
		if ( is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ) {
			$edd_version         = get_plugin_data( GTMKIT_PATH . '../easy-digital-downloads-pro/easy-digital-downloads.php' )['Version'];
			$data['edd-pro_version'] = preg_replace( '@^(\d\.\d+).*@', '\1', $edd_version );
		}
		$data['current_theme']  = $theme->get( 'Name' );
		$data['active_plugins'] = $this->get_active_plugins();
		$data['locale']         = $locale[0];
		$data['multisite']      = is_multisite();

		return $data;
	}

	/**
	 * Gets names of all active plugins.
	 *
	 * @return array An array of active plugins names.
	 */
	function get_active_plugins(): array {
		$plugins        = [];
		$active_plugins = array_intersect_key( get_plugins(), array_flip( array_filter( array_keys( get_plugins() ), 'is_plugin_active' ) ) );

		foreach ( $active_plugins as $plugin ) {
			$plugins[] = $plugin['Name'];
		}

		return $plugins;
	}

}

