/**
 * External dependencies
 */
import { useState, useEffect } from 'react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Notice,
	Button,
	SelectControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { SiteType } from '@/types/global';

const BRAND_SITE = 'brand-site' as const;
const GOVERNING_SITE = 'governing-site' as const;

interface NoticeState {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

const {
	nonce,
	setup_url: setupUrl,
	site_type: initialSiteType,
} = window.OneAccessOnboarding;

/**
 * Create NONCE middleware for apiFetch
 */
apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: {
	value: SiteType;
	setSiteType: ( v: SiteType ) => void;
} ) => (
	<SelectControl
		label={ __( 'Site Type', 'oneaccess' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This setting cannot be changed later and affects available features and configurations.",
			'oneaccess'
		) }
		onChange={ ( v: SiteType ) => {
			setSiteType( v );
		} }
		__nextHasNoMarginBottom
		__next40pxDefaultSize
		options={ [
			{ label: __( 'Select…', 'oneaccess' ), value: '' },
			{ label: __( 'Brand Site', 'oneaccess' ), value: BRAND_SITE },
			{
				label: __( 'Governing site', 'oneaccess' ),
				value: GOVERNING_SITE,
			},
		] }
	/>
);

const OnboardingScreen = () => {
	const [ siteType, setSiteType ] = useState< SiteType >(
		initialSiteType || ''
	);
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		apiFetch< { oneaccess_site_type?: SiteType } >( {
			path: '/wp/v2/settings',
		} )
			.then( ( settings ) => {
				if ( settings?.oneaccess_site_type ) {
					setSiteType( settings.oneaccess_site_type );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type.', 'oneaccess' ),
				} );
			} );
	}, [] );

	const handleSiteTypeChange = async ( value: SiteType ) => {
		// Optimistically set site type.
		setSiteType( value );
		setIsSaving( true );

		try {
			await apiFetch< { oneaccess_site_type?: SiteType } >( {
				// @todo replace with wp/v2/settings .
				path: '/oneaccess/v1/site-type',
				method: 'POST',
				data: { site_type: value },
			} ).then( ( settings ) => {
				if ( ! settings?.site_type ) {
					throw new Error(
						__( 'No site type in response', 'oneaccess' )
					);
				}

				setSiteType( settings.site_type );

				// Redirect user to setup page.
				if ( setupUrl ) {
					window.location.href = setupUrl;
				}
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'oneaccess' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<Card>
			{ !! notice?.message && (
				<Notice
					status={ notice?.type ?? 'success' }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice?.message }
				</Notice>
			) }

			<CardHeader>
				<h2>{ __( 'OneAccess', 'oneaccess' ) }</h2>
			</CardHeader>

			<CardBody className="oneaccess-onboarding-page">
				<SiteTypeSelector
					value={ siteType }
					setSiteType={ setSiteType }
				/>
				<Button
					variant="primary"
					onClick={ () => handleSiteTypeChange( siteType ) }
					disabled={ isSaving || ! siteType }
					style={ { marginTop: '1.5rem' } }
					className={ isSaving ? 'is-busy' : '' }
				>
					{ __( 'Select Current Site Type', 'oneaccess' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default OnboardingScreen;
