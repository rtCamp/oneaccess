/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import {
	TextareaControl,
	Button,
	Card,
	Notice,
	Spinner,
	CardHeader,
	CardBody,
	TextControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { NoticeType } from '@/admin/settings/page';

const API_NAMESPACE = window.OneAccessSettings.restUrl + '/oneaccess/v1';
const NONCE = window.OneAccessSettings.nonce;
const API_KEY = window.OneAccessSettings.api_key;

const SiteSettings = () => {
	const [ apiKey, setApiKey ] = useState( API_KEY );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );
	const [ governingSite, setGoverningSite ] = useState( '' );
	const [ showDisconnectionModal, setShowDisconnectionModal ] =
		useState( false );

	const fetchApiKey = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
					'X-OneAccess-Token': API_KEY,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setApiKey( data?.secret_key || '' );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch API key. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	// regenerate api key using REST endpoint.
	const regenerateApiKey = useCallback( async () => {
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': NONCE,
					'X-OneAccess-Token': API_KEY,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			if ( data?.secret_key ) {
				setApiKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __(
						'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.',
						'oneaccess'
					),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to regenerate API key. Please try again later.',
						'oneaccess'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error regenerating API key. Please try again later.',
					'oneaccess'
				),
			} );
		}
	}, [] );

	const fetchCurrentGoverningSite = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/governing-site?${ new Date().getTime() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': apiKey,
					},
				}
			);
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setGoverningSite( data?.governing_site_url || '' );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch governing site. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [ apiKey ] );

	const deleteGoverningSiteConnection = useCallback( async () => {
		try {
			const response = await fetch( `${ API_NAMESPACE }/governing-site`, {
				method: 'DELETE',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
					'X-OneAccess-Token': apiKey,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			setGoverningSite( '' );
			setNotice( {
				type: 'success',
				message: __(
					'Governing site disconnected successfully.',
					'oneaccess'
				),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to disconnect governing site. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setShowDisconnectionModal( false );
		}
	}, [ apiKey ] );

	const handleDisconnectGoverningSite = useCallback( async () => {
		setShowDisconnectionModal( true );
	}, [] );

	useEffect( () => {
		fetchApiKey();
		fetchCurrentGoverningSite();
	}, [ fetchApiKey, fetchCurrentGoverningSite ] );

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Card
				className="brand-site-settings"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'API Key', 'oneaccess' ) }</h2>
					<div>
						{ /* Copy to clipboard button */ }
						<Button
							variant="primary"
							onClick={ () => {
								navigator?.clipboard
									?.writeText( apiKey )
									.then( () => {
										setNotice( {
											type: 'success',
											message: __(
												'API key copied to clipboard.',
												'oneaccess'
											),
										} );
									} )
									.catch( ( error ) => {
										setNotice( {
											type: 'error',
											message:
												__(
													'Failed to copy api key. Please try again.',
													'oneaccess'
												) +
												' ' +
												error,
										} );
									} );
							} }
						>
							{ __( 'Copy API Key', 'oneaccess' ) }
						</Button>
						{ /* Regenerate key button */ }
						<Button
							variant="secondary"
							onClick={ regenerateApiKey }
							style={ { marginLeft: '10px' } }
						>
							{ __( 'Regenerate API Key', 'oneaccess' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div>
						<TextareaControl
							value={ apiKey }
							disabled
							help={ __(
								'This key is used for secure communication with the Governing site.',
								'oneaccess'
							) }
							__nextHasNoMarginBottom
							onChange={ () => {} } // to avoid ts warning
						/>
					</div>
				</CardBody>
			</Card>
			<Card
				className="governing-site-connection"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'Governing Site Connection', 'oneaccess' ) }</h2>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDisconnectGoverningSite }
						disabled={
							governingSite.trim().length === 0 || isLoading
						}
					>
						{ __( 'Disconnect Governing Site', 'oneaccess' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'oneaccess' ) }
						value={ governingSite }
						disabled
						help={ __(
							'This is the URL of the Governing site this Brand site is connected to.',
							'oneaccess'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ () => {} } // to avoid ts warning
					/>
				</CardBody>
			</Card>

			{ showDisconnectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'oneaccess' ) }
					onRequestClose={ () => setShowDisconnectionModal( false ) }
					shouldCloseOnClickOutside
				>
					<p>
						{ __(
							'Are you sure you want to disconnect from the governing site? This action cannot be undone.',
							'oneaccess'
						) }
					</p>
					<div
						style={ {
							display: 'flex',
							justifyContent: 'flex-end',
							marginTop: '20px',
							gap: '16px',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => setShowDisconnectionModal( false ) }
						>
							{ __( 'Cancel', 'oneaccess' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ deleteGoverningSiteConnection }
						>
							{ __( 'Disconnect', 'oneaccess' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default SiteSettings;
