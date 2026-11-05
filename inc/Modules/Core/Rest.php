<?php
/**
 * Handles REST API behavior.
 *
 * @package OneAccess\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneAccess\Modules\Core;

use OneAccess\Contracts\Interfaces\Registrable;

/**
 * Class REST
 */
final class Rest implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'rest_allowed_cors_headers', [ $this, 'allowed_cors_headers' ] );
	}

	/**
	 * Adds plugin CORS headers to those allowed in REST responses.
	 *
	 * @param array<int, string> $headers Existing headers.
	 *
	 * @return array<int, string> Modified headers.
	 */
	public function allowed_cors_headers( $headers ): array {
		// Append only the headers not already present so the list stays de-duplicated.
		foreach ( [ 'X-OneAccess-Token', 'X-OneAccess-Site-URL' ] as $header ) {
			if ( ! in_array( $header, $headers, true ) ) {
				$headers[] = $header;
			}
		}

		return $headers;
	}
}
