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

const BRAND_SITE = 'brand-site';
const GOVERNING_SITE = 'governing-site';

export type SiteType = typeof BRAND_SITE | typeof GOVERNING_SITE;

interface NoticeState {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

const SiteTypeSelector = ( {
	value,
	setSiteType,
}: {
	value: SiteType | '';
	setSiteType: ( v: SiteType | '' ) => void;
} ) => (
	<SelectControl
		label={ __( 'Site Type', 'oneaccess' ) }
		value={ value }
		help={ __(
			"Choose your site's primary purpose. This setting cannot be changed later and affects available features and configurations.",
			'oneaccess'
		) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
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
	// WordPress provides snake_case keys here. Using them intentionally.
	// eslint-disable-next-line camelcase
	const { nonce, setup_url, site_type } = window.OneAccessOnboarding;

	const [ siteType, setSiteType ] = useState< SiteType | '' >(
		site_type || ''
	);
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ isSaving, setIsSaving ] = useState< boolean >( false );

	useEffect( () => {
		apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
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
	} );

	const handleSiteTypeChange = async ( value: SiteType | '' ) => {
		// Optimistically set site type.
		setSiteType( value );
		setIsSaving( true );

		try {
			// need to use custom endpoint as after site type change we need to update user role & create brand admin or network admin accordingly.
			await apiFetch< { site_type?: SiteType } >( {
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
				if ( setup_url ) {
					window.location.href = setup_url;
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
