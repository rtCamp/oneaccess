/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';
/**
 * Internal dependencies
 */
import OnboardingScreen, { type SiteType } from './page';

interface OneAccessOnboarding {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneAccessOnboarding: OneAccessOnboarding;
	}
}

// Render to the target element.
const target = document.getElementById( 'oneaccess-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
