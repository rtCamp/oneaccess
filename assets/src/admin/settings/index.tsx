/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { OneAccessSettings } from '@/types/global';

/**
 * Internal dependencies
 */
import SettingsPage from './page';

declare global {
	interface Window {
		OneAccessSettings: OneAccessSettings;
	}
}

// Render to Gutenberg admin page with ID: oneaccess-settings-page
const target = document.getElementById( 'oneaccess-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <SettingsPage /> );
}
