<?php
/**
 * Unit tests for the message the Google tag gateway fallback notice carries.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\GoogleTagGatewayNotice}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use ReflectionMethod;
use TLA_Media\GTM_Kit\Admin\GoogleTagGatewayNotice;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The notice reads as prose once the dashboard has taken the link out of it.
 *
 * This message is what a merchant reads while the gateway is down, on the one
 * screen the feature points them to, so the thing worth asserting is not the
 * markup but what is left of it after the dashboard has parsed it: every link
 * is lifted out and rendered as an action button, and the remaining text
 * becomes the description. A sentence built around its link therefore arrives
 * with a hole in it, and paragraph markup contributes no separator at all.
 */
final class GoogleTagGatewayNoticeTest extends TestCase {

	/**
	 * Stub the WordPress functions the message is built from.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = [] ) => array_merge( (array) $defaults, (array) $args )
		);
		Functions\stubs( [ 'get_current_user_id' => 1 ] );
	}

	/**
	 * The message as the notice hands it to the dashboard.
	 *
	 * @return string
	 */
	private function message(): string {

		$notice = ( new \ReflectionClass( GoogleTagGatewayNotice::class ) )->newInstanceWithoutConstructor();

		$build = new ReflectionMethod( GoogleTagGatewayNotice::class, 'build' );
		$build->setAccessible( true );

		return $build->invoke( $notice )->render()['message'];
	}

	/**
	 * What the dashboard shows: the text with every anchor removed.
	 *
	 * Mirrors the parsing in `dashboardData.js`, which strips the anchors out
	 * into action buttons and then collapses the whitespace of what is left.
	 *
	 * @return string
	 */
	private function description(): string {

		$without_links = (string) preg_replace( '#<a\b[^>]*>.*?</a>#s', '', $this->message() );

		$text = (string) preg_replace( '#<[^>]+>#', '', $without_links );

		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * The description is whole sentences, separated, with nothing missing.
	 *
	 * @return void
	 */
	public function test_the_description_reads_as_prose_without_the_link(): void {

		$description = $this->description();

		$this->assertStringContainsString(
			'Your tracking is still running. Site Health shows',
			$description,
			'The sentences must be separated once the markup is gone.'
		);
		$this->assertStringEndsWith(
			'what to ask your host.',
			$description,
			'The last sentence must end where it means to, not at a removed link.'
		);
	}

	/**
	 * The link is the last thing in the message.
	 *
	 * A link anywhere else is a hole in the sentence it was part of, because
	 * the dashboard takes it out.
	 *
	 * @return void
	 */
	public function test_the_link_comes_last(): void {

		$message = $this->message();

		$this->assertStringEndsWith( '</a>', $message, 'The link must be the last thing in the message.' );
		$this->assertSame( 1, substr_count( $message, '<a ' ), 'The message carries one link.' );
		$this->assertStringContainsString( 'site-health.php', $message, 'The link points at Site Health.' );
	}

	/**
	 * The link keeps a label of its own for the action button.
	 *
	 * @return void
	 */
	public function test_the_link_carries_its_own_label(): void {

		$this->assertMatchesRegularExpression(
			'#<a [^>]*>\s*\S[^<]*</a>#',
			$this->message(),
			'The action button takes its label from the anchor text.'
		);
	}
}
