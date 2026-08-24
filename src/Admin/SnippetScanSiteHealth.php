<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use TLA_Media\GTM_Kit\Common\SiteEnvironment;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\SnippetScanDetector;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Reports what a scan of the site's own pages found, in Site Health.
 *
 * The other two GTM Kit tests report configuration: what the plugin is set up
 * to do. This one reports what a request to the site actually returned, which
 * is the only place in the admin where the two can disagree and be seen to.
 *
 * It reads the stored scan result and performs no request of its own, so it
 * stays a fast direct test. Its wording never claims more than a server-side
 * fetch can support: a container absent from the HTML may still be loaded in
 * the browser by a consent platform, and the result says so.
 */
final class SnippetScanSiteHealth {

	/**
	 * The Site Health test id.
	 *
	 * @var string
	 */
	private const TEST_ID = 'gtmkit_tracking_scan';

	/**
	 * An instance of SnippetScan.
	 *
	 * @var SnippetScan
	 */
	private SnippetScan $snippet_scan;

	/**
	 * An instance of Options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param SnippetScan $snippet_scan An instance of SnippetScan.
	 * @param Options     $options An instance of Options.
	 */
	public function __construct( SnippetScan $snippet_scan, Options $options ) {
		$this->snippet_scan = $snippet_scan;
		$this->options      = $options;
	}

	/**
	 * Register the test.
	 *
	 * @param SnippetScan $snippet_scan An instance of SnippetScan.
	 * @param Options     $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( SnippetScan $snippet_scan, Options $options ): void {
		$instance = new self( $snippet_scan, $options );

		add_filter( 'gtmkit_site_health_tests', [ $instance, 'add_test' ], 10, 2 );
	}

	/**
	 * Add the test to GTM Kit's Site Health group.
	 *
	 * @param array<string, array<string, mixed>> $tests The GTM Kit tests.
	 * @param SiteHealth                          $site_health The Site Health integration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_test( array $tests, SiteHealth $site_health ): array {

		$tests[ self::TEST_ID ] = [
			'label' => __( 'Tracking on your pages', 'gtm-kit' ),
			'test'  => function () use ( $site_health ): array {
				return $this->run_test( $site_health );
			},
		];

		return $tests;
	}

	/**
	 * Build the test result from the stored scan.
	 *
	 * @param SiteHealth $site_health The Site Health integration, for its result and link builders.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function run_test( SiteHealth $site_health ): array {

		$result = $this->snippet_scan->get_result();

		if ( $result === null ) {
			return $this->build(
				$site_health,
				__( 'Your pages have not been checked yet', 'gtm-kit' ),
				'recommended',
				'<p>' . esc_html__( 'Once a day, GTM Kit requests one of your pages the way a visitor receives it and reports what it finds. That check has not run yet on this site.', 'gtm-kit' ) . '</p>'
			);
		}

		switch ( (string) $result['state'] ) {
			case SnippetScan::STATE_FOUND:
				return $this->found_result( $site_health, $result );

			case SnippetScan::STATE_NOT_FOUND:
				return $this->not_found_result( $site_health, $result );

			default:
				return $this->inconclusive_result( $site_health, $result );
		}
	}

	/**
	 * Report a page that carries tracking.
	 *
	 * @param SiteHealth           $site_health The Site Health integration.
	 * @param array<string, mixed> $result The stored scan result.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function found_result( SiteHealth $site_health, array $result ): array {

		$duplicate  = (array) $result['duplicate'];
		$type       = (string) $duplicate['type'];
		$containers = array_map( 'strval', (array) $duplicate['containers'] );
		$culprit    = (string) $duplicate['culprit'];

		if ( $type === '' ) {
			return $this->build(
				$site_health,
				__( 'Your pages load tracking', 'gtm-kit' ),
				'good',
				'<p>' . sprintf(
					/* translators: %s is the scanned URL, as a link. */
					esc_html__( 'A request for %s returned a page with one tracking implementation in it.', 'gtm-kit' ),
					$this->scanned_url_link( $result )
				) . '</p>'
				. '<p>' . esc_html__( 'Whether the tags inside your container then fire as intended is a wider question than this check answers.', 'gtm-kit' ) . '</p>',
				$result
			);
		}

		if ( $type === SnippetScan::DUPLICATE_CONTAINERS ) {
			$label       = __( 'Your pages load more than one container', 'gtm-kit' );
			$status      = 'critical';
			$description = '<p>' . sprintf(
				/* translators: %s is a comma-separated list of Google Tag Manager container IDs. */
				esc_html__( 'Two or more Google Tag Manager containers are present: %s. Each fires its own tags, so page views, events and purchases are counted more than once and every report built on them is inflated.', 'gtm-kit' ),
				'<code>' . esc_html( implode( ', ', $containers ) ) . '</code>'
			) . '</p>';
		} elseif ( $type === SnippetScan::DUPLICATE_GTAG ) {
			$label       = __( 'A Google tag loads alongside your container', 'gtm-kit' );
			$status      = 'recommended';
			$description = '<p>' . esc_html__( 'Your pages load a Google tag directly as well as loading your Google Tag Manager container. That is only correct when the same tag does not also fire inside the container; when it does, its data is collected twice.', 'gtm-kit' ) . '</p>';
		} else {
			$label       = __( 'Your pages load the same container twice', 'gtm-kit' );
			$status      = 'critical';
			$description = '<p>' . (
				( $containers !== [] )
				? sprintf(
					/* translators: %s is a Google Tag Manager container ID. */
					esc_html__( 'Container %s is loaded by more than one script on the same page. Everything it measures, purchases included, is counted twice.', 'gtm-kit' ),
					'<code>' . esc_html( $containers[0] ) . '</code>'
				)
				: esc_html__( 'One Google Tag Manager container is loaded by more than one script on the same page. Everything it measures, purchases included, is counted twice.', 'gtm-kit' )
			) . '</p>';
		}

		$description .= '<p>' . (
			( $culprit !== '' )
			? sprintf(
				/* translators: %s is the name of a plugin or tool that also adds tracking code. */
				esc_html__( 'The extra tracking code appears to come from %s. Remove the container from there and leave GTM Kit as the single place your site loads it.', 'gtm-kit' ),
				'<strong>' . esc_html( $culprit ) . '</strong>'
			)
			: esc_html__( 'GTM Kit could not tell what adds the extra tracking code. Your theme\'s header template and any plugin that inserts code into the page head are the usual places to look.', 'gtm-kit' )
		) . '</p>';

		$description .= $this->implementation_list( $result );

		return $this->build( $site_health, $label, $status, $description, $result );
	}

	/**
	 * Report a page that carries no tracking.
	 *
	 * Two cases turn this from a finding into a statement of fact. A site
	 * that reports itself as somewhere the container does not belong has no
	 * container to find, by design. A consent platform legitimately gets one
	 * to the browser without it ever appearing in the server's HTML.
	 *
	 * @param SiteHealth           $site_health The Site Health integration.
	 * @param array<string, mixed> $result The stored scan result.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function not_found_result( SiteHealth $site_health, array $result ): array {

		$cmp_name = CMPDetection::get_display_name( CMPDetection::detect_active_cmp() );

		$intro = '<p>' . sprintf(
			/* translators: %s is the scanned URL, as a link. */
			esc_html__( 'A request for %s returned a page with no Google Tag Manager container and no Google tag in it.', 'gtm-kit' ),
			$this->scanned_url_link( $result )
		) . '</p>';

		if ( SiteEnvironment::suppresses_container( $this->options ) ) {
			return $this->build(
				$site_health,
				__( 'Your pages carry no container, as intended', 'gtm-kit' ),
				'good',
				$intro
				. '<p>' . sprintf(
					/* translators: %s is the kind of site WordPress reports, for example "staging", "development" or "local". */
					esc_html__( 'That is what to expect on this site: WordPress reports it as %s, so GTM Kit leaves the container out and traffic from here stays out of your analytics and advertising audiences.', 'gtm-kit' ),
					'<code>' . esc_html( SiteEnvironment::get_type() ) . '</code>'
				) . '</p>',
				$result
			);
		}

		if ( $cmp_name !== '' && (bool) $result['container_output'] ) {
			return $this->build(
				$site_health,
				__( 'Your consent platform loads your container', 'gtm-kit' ),
				'good',
				$intro
				. '<p>' . sprintf(
					/* translators: %s is the name of the detected consent platform. */
					esc_html__( 'That is what to expect on this site: %s is active, and a consent platform loads the container in the browser after the visitor has answered, which a check of the page your server sends cannot see.', 'gtm-kit' ),
					'<strong>' . esc_html( $cmp_name ) . '</strong>'
				) . '</p>',
				$result
			);
		}

		if ( (bool) $result['container_output'] ) {
			return $this->build(
				$site_health,
				__( 'Your container is missing from your pages', 'gtm-kit' ),
				'critical',
				$intro
				. '<p>' . esc_html__( 'GTM Kit is set up to add the container to your pages, so something is removing it again. An optimisation or caching plugin that strips inline scripts, or a page cache holding a copy from before the container was switched on, are the usual causes.', 'gtm-kit' ) . '</p>'
				. '<p>' . esc_html__( 'Clear your caches and check the page again. If the container is still missing, switch off script optimisation for GTM Kit\'s code.', 'gtm-kit' ) . '</p>',
				$result
			);
		}

		return $this->build(
			$site_health,
			__( 'Nothing on your pages is measuring this site', 'gtm-kit' ),
			'recommended',
			$intro
			. '<p>' . esc_html__( 'GTM Kit is not adding the container, and nothing else in the page is loading one either, so this site is collecting no data. Switch container output back on, unless something that this check cannot see loads the container for you.', 'gtm-kit' ) . '</p>',
			$result
		);
	}

	/**
	 * Report a scan that could not answer the question.
	 *
	 * @param SiteHealth           $site_health The Site Health integration.
	 * @param array<string, mixed> $result The stored scan result.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function inconclusive_result( SiteHealth $site_health, array $result ): array {

		$reason = (string) $result['reason'];

		switch ( $reason ) {
			case SnippetScan::REASON_LOOPBACK_BLOCKED:
				$explanation = __( 'The request never reached your site. A server that cannot open a connection to itself blocks this check, and blocks WordPress features that rely on the same kind of request.', 'gtm-kit' );
				break;

			case SnippetScan::REASON_HTTP_STATUS:
				$explanation = sprintf(
					/* translators: %s is an HTTP status code, for example 503. */
					__( 'The page answered with status %s instead of a normal page.', 'gtm-kit' ),
					(string) $result['status']
				);
				break;

			case SnippetScan::REASON_URL_EXCLUDED:
				$explanation = __( 'That page matches one of your URL exclusion patterns, so GTM Kit adds nothing to it by design and checking it would prove nothing. Point the check at another page to get an answer.', 'gtm-kit' );
				break;

			case SnippetScan::REASON_NOT_HTML:
				$explanation = __( 'The response was not an HTML page.', 'gtm-kit' );
				break;

			case SnippetScan::REASON_EMPTY_RESPONSE:
				$explanation = __( 'The response was empty.', 'gtm-kit' );
				break;

			case SnippetScan::REASON_INCOMPLETE_RESPONSE:
				$explanation = __( 'The response stopped part way through, so a container further down the page would have been missed.', 'gtm-kit' );
				break;

			default:
				$explanation = __( 'The request did not complete.', 'gtm-kit' );
				break;
		}

		// An excluded URL is the one inconclusive case where no request was
		// made at all, so it cannot be described as one that came back unusable.
		$intro = ( $reason === SnippetScan::REASON_URL_EXCLUDED )
			? sprintf(
				/* translators: %s is the URL that would have been checked, as a link. */
				esc_html__( 'GTM Kit did not check %s, so it cannot say whether your pages load tracking.', 'gtm-kit' ),
				$this->scanned_url_link( $result )
			)
			: sprintf(
				/* translators: %s is the scanned URL, as a link. */
				esc_html__( 'GTM Kit requested %s and could not read the result, so it cannot say whether your pages load tracking.', 'gtm-kit' ),
				$this->scanned_url_link( $result )
			);

		return $this->build(
			$site_health,
			__( 'Your pages could not be checked', 'gtm-kit' ),
			'recommended',
			'<p>' . $intro . '</p>'
			. '<p>' . esc_html( $explanation ) . '</p>',
			$result
		);
	}

	/**
	 * List what the scan identified, so the finding can be checked by hand.
	 *
	 * @param array<string, mixed> $result The stored scan result.
	 *
	 * @return string The list markup, or an empty string.
	 */
	private function implementation_list( array $result ): string {

		$items = [];

		foreach ( (array) $result['implementations'] as $implementation ) {
			if ( ! is_array( $implementation ) || ! SnippetScanDetector::counts_as_load( $implementation ) ) {
				continue;
			}

			$container = (string) $implementation['container'];
			$host      = (string) $implementation['host'];

			$items[] = '<li>' . esc_html(
				( $container !== '' )
					? $container
					: __( 'A container with no ID in the page', 'gtm-kit' )
			) . ( ( $host !== '' ) ? ' <code>' . esc_html( $host ) . '</code>' : '' ) . '</li>';
		}

		if ( $items === [] ) {
			return '';
		}

		return '<p>' . esc_html__( 'What was found:', 'gtm-kit' ) . '</p><ul>' . implode( '', $items ) . '</ul>';
	}

	/**
	 * Build the link to the page that was scanned.
	 *
	 * @param array<string, mixed> $result The stored scan result.
	 *
	 * @return string The anchor markup.
	 */
	private function scanned_url_link( array $result ): string {

		$url = (string) $result['final_url'];

		if ( $url === '' ) {
			$url = (string) $result['url'];
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( $url ),
			esc_html( $url )
		);
	}

	/**
	 * Assemble the result, with the scan's age and a rescan action.
	 *
	 * @param SiteHealth                $site_health The Site Health integration.
	 * @param string                    $label The one-line result headline.
	 * @param string                    $status One of `good`, `recommended` or `critical`.
	 * @param string                    $description The result body.
	 * @param array<string, mixed>|null $result The stored scan result, when one exists.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function build( SiteHealth $site_health, string $label, string $status, string $description, ?array $result = null ): array {

		if ( $result !== null ) {
			$description .= '<p>' . sprintf(
				/* translators: %s is a human-readable time difference, for example "3 hours". */
				esc_html__( 'Last checked %s ago.', 'gtm-kit' ),
				esc_html( human_time_diff( (int) $result['scanned_at'] ) )
			) . '</p>';
		}

		return $site_health->build_result(
			self::TEST_ID,
			$label,
			$status,
			$description,
			$site_health->action_link( $this->snippet_scan->get_rescan_url(), __( 'Check my pages again', 'gtm-kit' ) )
		);
	}
}
