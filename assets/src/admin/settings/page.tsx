/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import SiteSettings from '../../components/SiteSettings';
import type { SiteType } from '@/types/global';

const NONCE = window.OneAccessSettings.nonce;
const siteType = ( window.OneAccessSettings.siteType as SiteType ) || '';

export interface NoticeType {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	api_key: string;
}

export const defaultBrandSite: BrandSite = {
	name: '',
	url: '',
	api_key: '',
};

export type EditingIndex = number | null;

const SettingsPage = () => {
	const [ showModal, setShowModal ] = useState< boolean >( false );
	const [ editingIndex, setEditingIndex ] = useState< EditingIndex >( null );
	const [ sites, setSites ] = useState< BrandSite[] >( [] );
	const [ formData, setFormData ] = useState< BrandSite >( defaultBrandSite );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

	useEffect( () => {
		apiFetch< { oneaccess_shared_sites?: BrandSite[] } >( {
			path: '/wp/v2/settings',
		} )
			.then( ( settings ) => {
				if ( settings?.oneaccess_shared_sites ) {
					setSites( settings.oneaccess_shared_sites );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching settings data.', 'oneaccess' ),
				} );
			} );
	}, [] );

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'oneaccess-missing-brand-sites' );
		}
	}, [ sites ] );

	const handleFormSubmit = async (): Promise< void > => {
		const updated: BrandSite[] =
			editingIndex !== null
				? sites.map( ( item, i ) =>
						i === editingIndex ? formData : item
				  )
				: [ ...sites, formData ];
		try {
			apiFetch< { oneaccess_shared_sites?: BrandSite[] } >( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { oneaccess_shared_sites: updated },
			} )
				.then( ( settings ) => {
					if ( ! settings?.oneaccess_shared_sites ) {
						throw new Error( 'No shared sites in response' );
					}
					const previousLength = sites.length;
					setSites( settings.oneaccess_shared_sites );
					if (
						( settings.oneaccess_shared_sites.length === 1 &&
							previousLength === 0 ) ||
						( previousLength === 1 &&
							settings.oneaccess_shared_sites.length === 0 )
					) {
						window.location.reload();
					}
				} )
				.catch( () => {
					throw new Error( 'Failed to update shared sites' );
				} );

			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'oneaccess' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error saving Brand site. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setFormData( defaultBrandSite );
			setShowModal( false );
			setEditingIndex( null );
		}
	};

	const handleDelete = async ( index: number | null ): Promise< void > => {
		const updated: BrandSite[] = sites.filter( ( _, i ) => i !== index );

		try {
			await apiFetch< { oneaccess_shared_sites?: BrandSite[] } >( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { oneaccess_shared_sites: updated },
			} )
				.then( ( settings ) => {
					if ( ! settings?.oneaccess_shared_sites ) {
						throw new Error( 'No shared sites in response' );
					}
					const previousLength = sites.length;
					setSites( settings.oneaccess_shared_sites );
					if (
						( settings.oneaccess_shared_sites.length === 1 &&
							previousLength === 0 ) ||
						( previousLength === 1 &&
							settings.oneaccess_shared_sites.length === 0 )
					) {
						window.location.reload();
					} else {
						document.body.classList.remove(
							'oneaccess-missing-brand-sites'
						);
					}
				} )
				.catch( () => {
					throw new Error( 'Failed to update shared sites' );
				} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error deleting Brand site. Please try again later.',
					'oneaccess'
				),
			} );
		}
	};

	return (
		<>
			<>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Note: To use OneAccess plugin, your role must be Network Admin on Governing site and Brand Admin on Brand site.',
						'oneaccess'
					) }
				</Notice>
			</>
			<>
				{ !! notice && notice?.message?.length > 0 && (
					<Snackbar
						explicitDismiss={ false }
						onRemove={ () => setNotice( null ) }
						className={
							notice?.type === 'error'
								? 'oneaccess-error-notice'
								: 'oneaccess-success-notice'
						}
					>
						{ notice?.message }
					</Snackbar>
				) }
			</>

			{ siteType === 'brand-site' && <SiteSettings /> }

			{ siteType === 'governing-site' && (
				<SiteTable
					sites={ sites }
					onEdit={ setEditingIndex }
					onDelete={ handleDelete }
					setFormData={ setFormData }
					setShowModal={ setShowModal }
				/>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( defaultBrandSite );
					} }
					editing={ editingIndex !== null }
					sites={ sites }
					originalData={
						editingIndex !== null
							? sites[ editingIndex ]
							: undefined
					}
				/>
			) }
		</>
	);
};

export default SettingsPage;
