/**
 * Global type declarations for OneAccess.
 *
 * These types describe the window globals injected by WordPress PHP code.
 */

export type SiteType = 'governing-site' | 'brand-site' | '';

interface OneAccessSettings {
	restUrl: string;
	nonce: string;
	api_key: string;
	setupUrl: string;
	siteType: SiteType;
}

export interface OneAccessOnboarding {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}
