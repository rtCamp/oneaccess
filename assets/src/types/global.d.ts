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

interface OneAccessProfileRequest {
	id: number;
	user_id: number;
	status: 'pending' | 'approved' | 'rejected';
	request_data: Record< string, unknown >;
	comment: string | null;
	created_at: string;
	updated_at: string;
}

interface OneAccessProfile {
	restUrl: string;
	nonce: string;
	api_key: string;
	setupUrl: string;
	siteType: SiteType;
	userId: number;
	request: OneAccessProfileRequest | null;
}

interface OneAccess {
	nonce: string;
	api_key: string;
	restUrl: string;
	setupUrl: string;
	siteType: SiteType;
	availableRoles: Record< string, string >;
	availableSites: {
		id?: string;
		name: string;
		url: string;
		api_key: string;
	}[];
}

declare global {
	interface Window {
		OneAccessSettings: OneAccessSettings;
		OneAccessOnboarding: OneAccessOnboarding;
		OneAccessProfile: OneAccessProfile;
		OneAccess: OneAccess;
	}
}
