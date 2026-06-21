/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';
/**
 * Internal dependencies
 */
import OnboardingScreen from './page';
import type { OneAccessOnboarding } from '@/types/global';

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
