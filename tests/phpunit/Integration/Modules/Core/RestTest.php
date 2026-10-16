<?php
/**
 * Rest unit tests.
 *
 * @package OneAccess\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneAccess\Tests\Integration\Modules\Core;

use OneAccess\Modules\Core\Rest;
use OneAccess\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Rest core module.
 */
#[CoversClass( \OneAccess\Modules\Core\Rest::class )]
final class RestTest extends TestCase {
	/**
	 * Tests no errors on class instantiation.
	 */
	public function test_class_instantiation(): void {
		$rest = new Rest();

		$rest->register_hooks();

		$this->assertTrue( true );
	}

	/**
	 * Tests that the OneAccess headers are added once.
	 */
	public function test_allowed_cors_headers_adds_OneAccess_headers_once(): void {
		$rest = new Rest();

		$this->assertSame(
			[ 'X-WP-Nonce', 'X-OneAccess-Token', 'X-OneAccess-Site-URL' ],
			$rest->allowed_cors_headers( [ 'X-WP-Nonce' ] ),
			'Headers should be added'
		);

		$this->assertSame(
			[ 'X-OneAccess-Token', 'X-OneAccess-Site-URL' ],
			$rest->allowed_cors_headers( [ 'X-OneAccess-Token', 'X-OneAccess-Site-URL' ] ),
			'Headers should not be readded'
		);
	}
}
