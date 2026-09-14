<?php
/**
 * Unit tests for who may receive the Easy Digital Downloads purchase event.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Integration;

use Brain\Monkey\Functions;
use ReflectionClass;
use ReflectionMethod;
use TLA_Media\GTM_Kit\Integration\EasyDigitalDownloads;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The receipt rule is asked in the form every supported release understands.
 *
 * Easy Digital Downloads decides who may see a receipt through
 * `edd_can_view_receipt()`. Releases before 3.1.1 accept only a payment key
 * and answer "no" to anything else, so asking with the order object would
 * silently withhold every purchase on those stores.
 */
final class EasyDigitalDownloadsReceiptTest extends TestCase {

	/**
	 * Ask the integration whether the visitor holding a payment key may see the receipt.
	 *
	 * Private methods only became invokable without this in PHP 8.1, and the
	 * plugin still supports 7.4. Calling it unconditionally would instead
	 * raise a deprecation on PHP 8.5, so it is version-gated.
	 *
	 * @param string $payment_key The payment key the visitor arrived with.
	 *
	 * @return bool
	 */
	private function may_view( string $payment_key ): bool {
		$integration = ( new ReflectionClass( EasyDigitalDownloads::class ) )->newInstanceWithoutConstructor();
		$method      = new ReflectionMethod( EasyDigitalDownloads::class, 'visitor_may_view_receipt' );

		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return (bool) $method->invoke( $integration, $payment_key );
	}

	/**
	 * The rule is given the payment key, not an order object.
	 *
	 * @return void
	 */
	public function test_the_receipt_rule_is_asked_about_the_payment_key(): void {
		Functions\expect( 'edd_can_view_receipt' )->once()->with( 'a1b2c3d4' )->andReturn( true );

		$this->assertTrue( $this->may_view( 'a1b2c3d4' ) );
	}

	/**
	 * A visitor the store's rule turns away gets no purchase event.
	 *
	 * @return void
	 */
	public function test_a_visitor_the_rule_turns_away_is_not_reported(): void {
		Functions\expect( 'edd_can_view_receipt' )->once()->with( 'a1b2c3d4' )->andReturn( false );

		$this->assertFalse( $this->may_view( 'a1b2c3d4' ) );
	}
}
