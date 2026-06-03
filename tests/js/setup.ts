/**
 * Jest test setup for OneAccess.
 *
 * @package OneAccess
 */

/**
 * External dependencies
 */
import '@testing-library/jest-dom';

const fetchMock = jest.fn<
	ReturnType< typeof fetch >,
	Parameters< typeof fetch >
>();

Object.defineProperty( global, 'fetch', {
	value: fetchMock,
	writable: true,
} );

Object.defineProperty( window, 'OneAccessSettings', {
	value: {
		restUrl: 'https://example.com/wp-json/',
		restNonce: 'nonce',
		api_key: '',
		settingsLink: '/wp-admin/admin.php?page=oneaccess-settings',
		siteType: 'governing-site',
	},
	writable: true,
} );

Object.defineProperty( window, 'OneAccessOnboarding', {
	value: {
		nonce: 'onboarding-nonce',
		site_type: '',
		setup_url: '',
	},
	writable: true,
} );

Object.defineProperty( navigator, 'clipboard', {
	value: {
		writeText: jest.fn().mockResolvedValue( undefined ),
	},
	configurable: true,
} );

beforeEach( () => {
	jest.clearAllMocks();
	fetchMock.mockReset();
} );
