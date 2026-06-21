/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	TextControl,
	SelectControl,
	Button,
	Modal,
	CheckboxControl,
	Notice,
	__experimentalGrid as Grid,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	Dashicon,
	Snackbar,
	SnackbarList,
	Icon,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	isValidEmail,
	checkPasswordStrength,
	strengthWidths,
	getStrengthColor,
} from '../js/utils';

const NONCE = window.OneAccess.nonce;
const API_NAMESPACE = window.OneAccess.restUrl + '/oneaccess/v1';
const API_KEY = window.OneAccess.api_key;
const AVAILABLE_ROLES = window.OneAccess.availableRoles || [];

const CreateUser = ( { availableSites } ) => {
	const [ userFormData, setUserFormData ] = useState( {
		username: '',
		fullName: '',
		email: '',
		password: '',
		role: 'subscriber',
	} );

	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );
	const [ showPassword, setShowPassword ] = useState( false );
	const [ passwordStrength, setPasswordStrength ] = useState( '' );
	const [ userCreationNotices, setUserCreationNotices ] = useState( [] );

	const fetchStrongPassword = useCallback( async () => {
		setUserCreationNotices( [] );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/generate-strong-password?${ new Date()
					.getTime()
					.toString() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to generate password. Please try again later.',
						'oneaccess'
					),
				} );
				throw new Error( 'Failed to generate password' );
			}
			const data = await response.json();
			if ( ! data.password ) {
				setNotice( {
					type: 'error',
					message: __(
						'No password generated. Please try again.',
						'oneaccess'
					),
				} );
				return '';
			}
			setNotice( {
				type: 'success',
				message: __( 'Password generated successfully.', 'oneaccess' ),
			} );
			return data.password;
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to generate a password. Please try again later.',
					'oneaccess'
				),
			} );
			return '';
		}
	}, [] );

	// Site selection modal states
	const [ showSiteSelectionModal, setShowSiteSelectionModal ] =
		useState( false );
	const [ selectedSites, setSelectedSites ] = useState( [] );
	const [ isUserCreating, setIsUserCreating ] = useState( false );

	useEffect( () => {
		setPasswordStrength( checkPasswordStrength( userFormData.password ) );
	}, [ userFormData.password ] );

	const handleInputChange = ( field, value ) => {
		setUserFormData( ( prevData ) => ( {
			...prevData,
			[ field ]: value,
		} ) );
	};

	const handleUserCreation = useCallback( async () => {
		setIsUserCreating( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/create-user-for-sites`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
					body: JSON.stringify( {
						userdata: userFormData,
						sites: selectedSites,
					} ),
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to create user. Please try again later.',
						'oneaccess'
					),
				} );
				throw new Error( 'Failed to create user' );
			}

			const data = await response.json();
			if ( ! data.success ) {
				setNotice( {
					type: 'error',
					message:
						data.message ||
						__(
							'An error occurred while creating the user. Please try again.',
							'oneaccess'
						),
				} );
				return;
			}

			const results = data?.data?.response_data || [];
			const newNotices = results.map( ( result, index ) => ( {
				id: `notice-${ Date.now() }-${ index }`,
				status: result.status === 'success' ? 'success' : 'error',
				content: result.site
					? `${ result.site }: ${ result.message }`
					: result.message ||
					  __( 'No site information available.', 'oneaccess' ),
				className:
					result.status === 'success'
						? 'oneaccess-success-notice'
						: 'oneaccess-error-notice',
			} ) );
			setNotice( {} );
			setUserCreationNotices( newNotices );

			// check if new notices has status as error.
			const hasError = newNotices.filter( ( n ) => n.status === 'error' );

			if ( hasError.length === 0 ) {
				setUserFormData( {
					username: '',
					fullName: '',
					email: '',
					password: '',
					role: 'subscriber',
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to create user. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setIsUserCreating( false );
			setShowSiteSelectionModal( false );
			setSelectedSites( [] );
		}
	}, [ userFormData, selectedSites ] );

	return (
		<>
			<Card>
				<CardHeader>
					<HStack justify="space-between">
						<h2>{ __( 'Create User', 'oneaccess' ) }</h2>
						<Button
							variant="primary"
							onClick={ () => setShowSiteSelectionModal( true ) }
							disabled={
								availableSites.length === 0 ||
								userFormData.username === '' ||
								userFormData.email === '' ||
								userFormData.password === '' ||
								userFormData.fullName === '' ||
								userFormData.role === '' ||
								isValidEmail( userFormData.email ) === false ||
								passwordStrength === 'very-weak' ||
								passwordStrength === 'weak'
							}
						>
							{ __( 'Assign to Sites', 'oneaccess' ) }
						</Button>
					</HStack>
				</CardHeader>
				<CardBody>
					<form>
						{ /* Hidden username field to prevent browser autofill */ }
						<input
							type="text"
							name="username"
							style={ { display: 'none' } }
							autoComplete="username"
						/>
						<Grid columns="2" gap="4">
							<TextControl
								label={ __( 'Username*', 'oneaccess' ) }
								value={ userFormData.username }
								onChange={ ( value ) =>
									handleInputChange( 'username', value )
								}
								required
								help={ __(
									'Enter a unique username for the user account.',
									'oneaccess'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Full Name*', 'oneaccess' ) }
								value={ userFormData.fullName }
								onChange={ ( value ) =>
									handleInputChange( 'fullName', value )
								}
								required
								help={ __(
									"Enter the user's display name.",
									'oneaccess'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Email Address*', 'oneaccess' ) }
								type="email"
								value={ userFormData.email }
								onChange={ ( value ) =>
									handleInputChange( 'email', value )
								}
								required
								help={ __(
									'A valid email address for account notifications.',
									'oneaccess'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<SelectControl
								label={ __( 'User Role*', 'oneaccess' ) }
								value={ userFormData.role }
								onChange={ ( value ) =>
									handleInputChange( 'role', value )
								}
								options={ Object.entries(
									AVAILABLE_ROLES
								)?.map( ( [ role, label ] ) => ( {
									value: role,
									label,
								} ) ) }
								help={ __(
									'Select the user role that determines their capabilities.',
									'oneaccess'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
							<VStack spacing="2" style={ { gap: '0px' } }>
								<HStack
									alignment="left"
									spacing="2"
									style={ { alignItems: 'flex-start' } }
								>
									<TextControl
										label={ __( 'Password*', 'oneaccess' ) }
										type={
											showPassword ? 'text' : 'password'
										}
										value={ userFormData.password }
										onChange={ ( value ) =>
											handleInputChange(
												'password',
												value
											)
										}
										required
										help={ __(
											'Password must be at least 8 characters long. Use a mix of letters (upper & lower case), numbers, and special characters for better security.',
											'oneaccess'
										) }
										style={ { flex: 1 } }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<Button
										onClick={ () =>
											setShowPassword( ! showPassword )
										}
										aria-label={
											showPassword
												? __(
														'Hide password',
														'oneaccess'
												  )
												: __(
														'Show password',
														'oneaccess'
												  )
										}
										style={ {
											marginTop: '1.5rem',
											height: '2.5rem',
											minWidth: 'auto',
										} }
										variant="secondary"
									>
										<Icon
											icon={
												showPassword
													? 'visibility'
													: 'hidden'
											}
											size={ 20 }
										/>
									</Button>
								</HStack>
								{ userFormData.password && (
									<div style={ { marginBottom: '12px' } }>
										<div
											style={ {
												fontSize: '12px',
												color: '#6c757d',
											} }
										>
											{ __(
												'Password Strength:',
												'oneaccess'
											) }{ ' ' }
											<span
												style={ {
													color: getStrengthColor(
														passwordStrength
													),
													fontWeight: '500',
												} }
											>
												{ passwordStrength
													? passwordStrength
															.replace( '-', ' ' )
															.toUpperCase()
													: '' }
											</span>
										</div>
										<div
											style={ {
												height: '4px',
												width: '100%',
												backgroundColor: '#e1e5e9',
												borderRadius: '2px',
												overflow: 'hidden',
											} }
										>
											<div
												style={ {
													height: '100%',
													width:
														strengthWidths[
															passwordStrength
														] ||
														strengthWidths.default,
													backgroundColor:
														getStrengthColor(
															passwordStrength
														),
													transition:
														'width 0.3s ease-in-out',
												} }
											/>
										</div>
									</div>
								) }
								<Button
									variant="secondary"
									onClick={ () => {
										fetchStrongPassword().then(
											( password ) => {
												if ( password ) {
													handleInputChange(
														'password',
														password
													);
												}
											}
										);
									} }
									style={ {
										width: 'fit-content',
										marginBlockStart: '12px',
									} }
								>
									{ __(
										'Generate strong password',
										'oneaccess'
									) }
								</Button>
							</VStack>
						</Grid>
					</form>

					{ notice.message && (
						<Snackbar
							isDismissible
							status={ notice.type }
							className={
								notice.type === 'error'
									? 'oneaccess-error-notice'
									: 'oneaccess-success-notice'
							}
							onRemove={ () =>
								setNotice( { type: '', message: '' } )
							}
						>
							{ notice.message }
						</Snackbar>
					) }

					{ userCreationNotices.length > 0 && (
						<SnackbarList
							notices={ userCreationNotices }
							onRemove={ () => {
								setTimeout( () => {
									setUserCreationNotices( [] );
								}, 3000 );
							} }
						/>
					) }
				</CardBody>
			</Card>

			{ showSiteSelectionModal && (
				<Modal
					title={ __(
						'Select Sites for User Creation',
						'oneaccess'
					) }
					onRequestClose={ () => setShowSiteSelectionModal( false ) }
					shouldCloseOnClickOutside
					size="medium"
				>
					<VStack spacing="4">
						<div>
							<p
								style={ {
									margin: 0,
									color: '#6c757d',
									fontSize: '14px',
								} }
							>
								{ __(
									'Select the sites where you want to create this user.',
									'oneaccess'
								) }
							</p>
						</div>

						{ availableSites.length > 0 && (
							<HStack justify="flex-start" spacing="3">
								<CheckboxControl
									label={ __(
										'Select All Sites',
										'oneaccess'
									) }
									checked={
										selectedSites.length ===
										availableSites.length
									}
									onChange={ () => {
										if (
											selectedSites.length ===
											availableSites.length
										) {
											setSelectedSites( [] );
										} else {
											setSelectedSites(
												availableSites?.map(
													( site ) => ( {
														url: site.url,
														name: site.name,
														api_key: site.api_key,
													} )
												)
											);
										}
									} }
									__nextHasNoMarginBottom
								/>
								<Button
									variant="link"
									onClick={ () => setSelectedSites( [] ) }
									disabled={ selectedSites.length === 0 }
									style={ { marginBlockEnd: '0.5rem' } }
								>
									{ __( 'Clear Selection', 'oneaccess' ) }
								</Button>
							</HStack>
						) }

						<div>
							{ availableSites.length > 0 ? (
								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #e1e5e9',
										borderRadius: '8px',
										padding: '16px',
									} }
								>
									<VStack spacing="2">
										{ availableSites?.map(
											( site, index ) => (
												<div
													key={ index }
													style={ {
														padding: '8px',
														border: '1px solid #f0f0f1',
														borderRadius: '4px',
														cursor: 'pointer',
													} }
													role="button"
													tabIndex={ 0 }
													onKeyDown={ ( e ) => {
														if (
															e.key === 'Enter' ||
															e.key === ' '
														) {
															e.preventDefault();
															setSelectedSites(
																( prev ) =>
																	prev.some(
																		( s ) =>
																			s.url ===
																			site.url
																	)
																		? prev.filter(
																				(
																					s
																				) =>
																					s.url !==
																					site.url
																		  )
																		: [
																				...prev,
																				{
																					url: site.url,
																					name: site.name,
																					api_key:
																						site.api_key,
																				},
																		  ]
															);
														}
													} }
													aria-pressed={ selectedSites.some(
														( s ) =>
															s.url === site.url
													) }
													onClick={ ( event ) => {
														event.stopPropagation();
														setSelectedSites(
															( prev ) =>
																prev.some(
																	( s ) =>
																		s.url ===
																		site.url
																)
																	? prev.filter(
																			(
																				s
																			) =>
																				s.url !==
																				site.url
																	  )
																	: [
																			...prev,
																			{
																				url: site.url,
																				name: site.name,
																				api_key:
																					site.api_key,
																			},
																	  ]
														);
													} }
												>
													<CheckboxControl
														className="oneaccess-site-checkbox"
														label={
															<div>
																<div
																	style={ {
																		fontWeight:
																			'500',
																		color: '#23282d',
																	} }
																>
																	{
																		site.name
																	}
																</div>
																<div
																	style={ {
																		fontSize:
																			'12px',
																		color: '#6c757d',
																	} }
																>
																	{ site.url }
																</div>
															</div>
														}
														checked={ selectedSites.some(
															( s ) =>
																s.url ===
																site.url
														) }
														__nextHasNoMarginBottom
													/>
												</div>
											)
										) }
									</VStack>
								</div>
							) : (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									<p style={ { margin: 0 } }>
										{ __(
											'No sites available. Please add sites first.',
											'oneaccess'
										) }
									</p>
								</Notice>
							) }
						</div>

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setShowSiteSelectionModal( false );
									setSelectedSites( [] );
								} }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handleUserCreation }
								disabled={
									selectedSites.length === 0 || isUserCreating
								}
								isBusy={ isUserCreating }
							>
								<Dashicon
									icon="admin-users"
									style={ { marginRight: '8px' } }
								/>
								{ __( 'Create User on Sites', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }
		</>
	);
};

export default CreateUser;
