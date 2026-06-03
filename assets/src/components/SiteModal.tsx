/**
 * WordPress dependencies
 */
import { useState, useMemo } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { isValidUrl } from '../js/utils';
/**
 * External dependencies
 */
import type { defaultBrandSite } from '@/admin/settings/page';

interface SiteModalProps {
	formData: typeof defaultBrandSite;
	setFormData: ( data: typeof defaultBrandSite ) => void;
	onSubmit: () => Promise< Response | void >;
	onClose: () => void;
	editing: boolean;
	sites: ( typeof defaultBrandSite )[];
	originalData: typeof defaultBrandSite | undefined;
}

interface ErrorsType {
	name: string;
	url: string;
	api_key: string;
	message: string;
}

const SiteModal = ( {
	formData,
	setFormData,
	onSubmit,
	onClose,
	editing,
	sites,
	originalData,
}: SiteModalProps ) => {
	const [ errors, setErrors ] = useState< ErrorsType >( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState< boolean >( false );
	const [ isProcessing, setIsProcessing ] = useState< boolean >( false );

	// Check if form data has changed from original data (only for editing mode)
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData.name !== originalData?.name ||
			formData.url !== originalData?.url ||
			formData.api_key !== originalData?.api_key
		);
	}, [ editing, formData, originalData ] );

	const handleSubmit = async (): Promise< void > => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'oneaccess' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __(
				'Enter a valid URL (must start with http or https).',
				'oneaccess'
			);
		}

		const newErrors = {
			name: ! formData.name.trim()
				? __( 'Site Name is required.', 'oneaccess' )
				: '',
			url: siteUrlError,
			api_key: ! formData.api_key.trim()
				? __( 'API Key is required.', 'oneaccess' )
				: '',
			message: '',
		};

		// make sure site name is under 20 characters
		if ( formData.name.length > 20 ) {
			newErrors.name = __(
				'Site Name must be under 20 characters.',
				'oneaccess'
			);
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${ formData.url }/wp-json/oneaccess/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneAccess-Token': formData.api_key,
					},
				}
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __(
						"Health check failed, please verify API key and make sure there's no governing site connected.",
						'oneaccess'
					),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			// check if same url is already added or not.
			let isAlreadyExists = false;
			sites.forEach( ( site ) => {
				const trimmedSiteUrl = site.url.endsWith( '/' )
					? site.url
					: `${ site.url }/`;
				const trimmedFormUrl = formData.url.endsWith( '/' )
					? formData.url
					: `${ formData.url }/`;
				if ( trimmedSiteUrl === trimmedFormUrl ) {
					if ( editing && originalData?.url === formData.url ) {
						// allow if url is same as original url in editing mode
						return;
					}
					isAlreadyExists = true;
				}
			} );

			if ( isAlreadyExists ) {
				setErrors( {
					...newErrors,
					message: __(
						'Site URL already exists. Please use a different URL.',
						'oneaccess'
					),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( ! submitResponse?.ok ) {
				const errorData = await submitResponse?.json();
				setErrors( {
					...newErrors,
					message:
						errorData.message ||
						__(
							'An error occurred while saving the site. Please try again.',
							'oneaccess'
						),
				} );
				setShowNotice( true );
			}
		} catch ( error ) {
			setErrors( {
				...newErrors,
				message: __(
					'An unexpected error occurred. Please try again.',
					'oneaccess'
				),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		} finally {
			setIsProcessing( false );
		}
	};

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled =
		isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.api_key ||
		( editing && ! hasChanges );

	return (
		<Modal
			title={
				editing
					? __( 'Edit Brand Site', 'oneaccess' )
					: __( 'Add Brand Site', 'oneaccess' )
			}
			onRequestClose={ onClose }
			size="medium"
			shouldCloseOnClickOutside
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message ||
						errors.name ||
						errors.url ||
						errors.api_key }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'oneaccess' ) }
				value={ formData.name }
				onChange={ ( value ) =>
					setFormData( { ...formData, name: value } )
				}
				help={ __(
					'This is the name of the site that will be registered.',
					'oneaccess'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'Site URL*', 'oneaccess' ) }
				value={ formData.url }
				onChange={ ( value ) =>
					setFormData( { ...formData, url: value } )
				}
				help={ __(
					'It must start with http or https and end with /, like: https://rtcamp.com/',
					'oneaccess'
				) }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'API Key*', 'oneaccess' ) }
				value={ formData.api_key }
				onChange={ ( value ) =>
					setFormData( { ...formData, api_key: value } )
				}
				help={ __(
					'This is the api key that will be used to authenticate the site for OneAccess.',
					'oneaccess'
				) }
				__nextHasNoMarginBottom
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isButtonDisabled }
				style={ { marginTop: '12px' } }
			>
				{ editing
					? __( 'Update Site', 'oneaccess' )
					: __( 'Add Site', 'oneaccess' ) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
