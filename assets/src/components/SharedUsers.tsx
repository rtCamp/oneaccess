/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Spinner,
	Button,
	Snackbar,
	SelectControl,
	Modal,
	DropdownMenu,
	CheckboxControl,
	Notice,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalGrid as Grid,
	Dashicon,
	MenuGroup,
	MenuItem,
	TextControl,
	Icon,
} from '@wordpress/components';
import { moreVertical, people, plus, globe, trash } from '@wordpress/icons';
import { decodeEntities } from '@wordpress/html-entities';
import type { MutableRefObject } from 'react';

/**
 * Internal dependencies
 */
import {
	checkPasswordStrength,
	strengthWidths,
	getStrengthColor,
	type StrengthLevel,
} from '../js/utils';

const NONCE = window.OneAccess.nonce;
const API_NAMESPACE = window.OneAccess.restUrl + '/oneaccess/v1';
const AVAILABLE_ROLES = window.OneAccess.availableRoles || {};
const PER_PAGE = 20;

interface AvailableSite {
	id?: string;
	name: string;
	url: string;
	api_key: string;
}

interface NoticeState {
	type: 'success' | 'error';
	message: string;
}

interface SharedUser {
	id: number;
	username: string;
	email: string;
	full_name: string;
	first_name: string;
	last_name: string;
	sites: Array< {
		site_url: string;
		site_name: string;
		name: string;
		url: string;
		role: string;
		roles: string[] | Record< string, string >;
		user_id: number;
		username?: string;
	} >;
	sites_info: Array< {
		site_url: string;
		site_name: string;
		roles: string[] | Record< string, string >;
		user_id: number;
		username?: string;
	} >;
	all_roles: string[];
	all_sites: string[];
	created_at: string;
	updated_at: string;
}

interface SiteToAdd {
	url: string;
	name: string;
	api_key: string;
	role?: string;
}

interface SiteToDelete {
	site_url: string;
	site_name: string;
}

interface GenericApiResponse {
	success: boolean;
	message?: string;
}

const SharedUsers = ( {
	availableSites,
}: {
	availableSites: AvailableSite[];
} ) => {
	const [ isLoading, setIsLoading ] = useState< boolean >( false );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ users, setUsers ] = useState< SharedUser[] >( [] );
	const [ searchTerm, setSearchTerm ] = useState< string >( '' );
	const [ debounceSearchTerm, setDebounceSearchTerm ] =
		useState< string >( searchTerm );
	const [ selectedSiteFilter, setSelectedSiteFilter ] =
		useState< string >( '' );
	const [ selectedUserRole, setSelectedUserRole ] = useState< string >( '' );
	const [ page, setPage ] = useState< number >( 1 );
	const [ totalPages, setTotalPages ] = useState< number >( 1 );
	const [ isDoingUsersCleanup, setIsDoingUsersCleanup ] =
		useState< boolean >( false );
	const [ isCleanupModalOpen, setIsCleanupModalOpen ] =
		useState< boolean >( false );
	const [ isRebuildingDeduplicatedIndex, setIsRebuildingDeduplicatedIndex ] =
		useState< boolean >( false );
	const [ showRebuildIndexModal, setShowRebuildIndexModal ] =
		useState< boolean >( false );
	const [ password, setPassword ] = useState< string >( '' );
	const [ showPassword, setShowPassword ] = useState< boolean >( false );
	const [ passwordStrength, setPasswordStrength ] = useState<
		StrengthLevel | 'default'
	>( 'default' );
	const [ passwordNotice, setPasswordNotice ] =
		useState< NoticeState | null >( null );
	const passwordRef: MutableRefObject< string > = useRef( password );

	// Modal states
	const [ showManageRolesModal, setShowManageRolesModal ] =
		useState< boolean >( false );
	const [ showAddToSitesModal, setShowAddToSitesModal ] =
		useState< boolean >( false );
	const [ selectedUser, setSelectedUser ] = useState< SharedUser | null >(
		null
	);
	const [ userRoles, setUserRoles ] = useState< Record< string, string > >(
		{}
	);
	const [ selectedSitesToAdd, setSelectedSitesToAdd ] = useState<
		SiteToAdd[]
	>( [] );
	const [ isUpdatingRoles, setIsUpdatingRoles ] =
		useState< boolean >( false );
	const [ isAddingToSites, setIsAddingToSites ] =
		useState< boolean >( false );
	const [ showUserDeletionModal, setShowUserDeletionModal ] =
		useState< boolean >( false );
	const [ selectedSitesToDeleteUser, setSelectedSitesToDeleteUser ] =
		useState< SiteToDelete[] >( [] );
	const [ isDeletingUser, setIsDeletingUser ] = useState< boolean >( false );

	// debounce search query to improve performance.
	useEffect( () => {
		const timer = setTimeout( () => {
			setDebounceSearchTerm( searchTerm );
		}, 300 );

		return () => {
			clearTimeout( timer );
		};
	}, [ searchTerm ] );

	const fetchUsers = useCallback( async () => {
		setIsLoading( true );
		try {
			// Build query parameters
			const params = new URLSearchParams( {
				paged: page.toString(),
				per_page: PER_PAGE.toString(),
			} );

			// Add optional filters
			if ( debounceSearchTerm ) {
				params.append( 'search_query', debounceSearchTerm );
			}
			if ( selectedUserRole ) {
				params.append( 'role', selectedUserRole );
			}
			if ( selectedSiteFilter ) {
				params.append( 'site', selectedSiteFilter );
			}

			const response = await fetch(
				`${ API_NAMESPACE }/new-users?${ params.toString() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to fetch users' );
			}

			const data = ( await response.json() ) as {
				success: boolean;
				message?: string;
				users: Array< {
					id: number;
					email: string;
					full_name?: string;
					first_name: string;
					last_name: string;
					sites_info?: Array< {
						site_url: string;
						site_name: string;
						roles: string[] | Record< string, string >;
						user_id: number;
					} >;
					all_roles?: string[];
					all_sites?: string[];
					created_at: string;
					updated_at: string;
				} >;
				pagination: {
					total_pages: number;
				};
			};

			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to fetch users' );
			}

			// Transform the API response to match component's expected format
			const transformedUsers: SharedUser[] = data.users.map(
				( user ) => ( {
					id: user.id,
					username: user.username || ( user.email.split( '@' )[ 0 ] ?? '' ), // Fallback username from email
					email: user.email,
					full_name:
						user.full_name ||
						`${ user.first_name } ${ user.last_name }`.trim() ||
						user.email,
					first_name: user.first_name,
					last_name: user.last_name,
					sites:
						user.sites_info?.map( ( site ) => ( {
							site_url: site.site_url,
							site_name: site.site_name,
							name: site.site_name,
							url: site.site_url,
							username: site.username || '',
							// Get the first role from roles array, or handle object structure
							role: ( (): string => {
								if ( Array.isArray( site.roles ) ) {
									// return all roles as comma separated string and convert to available roles mapping.
									return site.roles
										.map(
											( role ) =>
												AVAILABLE_ROLES[ role ] || role
										)
										.join( ', ' );
								}
								if (
									typeof site.roles === 'object' &&
									site.roles !== null
								) {
									return Object.values( site.roles )
										.map(
											( role ) =>
												AVAILABLE_ROLES[ role ] || role
										)
										.join( ', ' );
								}
								return 'subscriber';
							} )(),
							roles: site.roles,
							user_id: site.user_id,
						} ) ) || [],
					sites_info: user.sites_info || [],
					all_roles: user.all_roles || [],
					all_sites: user.all_sites || [],
					created_at: user.created_at,
					updated_at: user.updated_at,
				} )
			);

			setUsers( transformedUsers );
			setTotalPages( data.pagination.total_pages );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch users. Please try again later.',
					'oneaccess'
				),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [ page, debounceSearchTerm, selectedUserRole, selectedSiteFilter ] );

	useEffect( () => {
		fetchUsers();
	}, [ fetchUsers ] );

	// Reset to page 1 when filters change
	useEffect( () => {
		if ( page !== 1 ) {
			setPage( 1 );
		}
	}, [ searchTerm, selectedUserRole, selectedSiteFilter ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Get sites available for adding (sites user is not already assigned to)
	const getAvailableSitesForUser = ( user: SharedUser ): AvailableSite[] => {
		const userSiteUrls: string[] =
			user.sites?.map( ( site ) => {
				const url = site.url || site.site_url || '';
				return url.replace( /\/+$/, '' );
			} ) || [];
		return availableSites.filter(
			( site ) =>
				! userSiteUrls.includes( site.url.replace( /\/+$/, '' ) )
		);
	};

	// Handle opening manage roles modal
	const handleManageRoles = ( user: SharedUser ) => {
		setSelectedUser( user );

		// Initialize user roles with current roles
		const initialRoles: Record< string, string > = {};
		user.sites?.forEach( ( site ) => {
			const rolesArray = Array.isArray( site.roles ) ? site.roles : [];
			initialRoles[ site.site_url ] =
				rolesArray.length > 0
					? rolesArray[ 0 ] ?? ''
					: Object.keys( AVAILABLE_ROLES ).find(
							( key ) => AVAILABLE_ROLES[ key ] === site.role
					  ) ?? '';
		} );

		setUserRoles( initialRoles );
		setShowManageRolesModal( true );
	};

	// Handle opening add to sites modal
	const handleAddToSites = ( user: SharedUser ) => {
		setSelectedUser( user );
		setSelectedSitesToAdd( [] );
		setShowAddToSitesModal( true );
		setPasswordNotice( null );
		setPassword( '' );
	};

	const handleUserDeletion = ( user: SharedUser ) => {
		setSelectedUser( user );
		setSelectedSitesToDeleteUser( [] );
		setShowUserDeletionModal( true );
	};

	const handleUserDeletionFromSelectedSites = useCallback( async () => {
		if ( ! selectedUser ) {
			return;
		}
		setIsDeletingUser( true );
		try {
			const currentUser = selectedUser;
			const sitesToDelete = selectedSitesToDeleteUser;

			const response = await fetch(
				`${ API_NAMESPACE }/delete-user-from-sites`,
				{
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: currentUser.username,
						email: currentUser.email,
						sites: sitesToDelete,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to delete user from sites' );
			}

			const data = ( await response.json() ) as GenericApiResponse;
			if ( ! data.success ) {
				throw new Error(
					data.message || 'Failed to delete user from sites'
				);
			}

			setNotice( {
				type: 'success',
				message: __( 'User deleted successfully.', 'oneaccess' ),
			} );

			// Refresh users list
			await fetchUsers();
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to delete user.', 'oneaccess' ),
			} );
		} finally {
			setIsDeletingUser( false );
			setShowUserDeletionModal( false );
			setSelectedUser( null );
			setSelectedSitesToDeleteUser( [] );
		}
	}, [ selectedSitesToDeleteUser, fetchUsers, selectedUser ] );

	// Handle updating user roles
	const handleUpdateRoles = useCallback( async () => {
		if ( ! selectedUser ) {
			return;
		}
		setIsUpdatingRoles( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/update-user-roles`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: selectedUser.username,
						email: selectedUser.email,
						roles: userRoles,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to update roles' );
			}

			const data = ( await response.json() ) as GenericApiResponse;
			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to update roles' );
			}

			setNotice( {
				type: 'success',
				message: __( 'User roles updated successfully.', 'oneaccess' ),
			} );

			// Refresh users list
			await fetchUsers();
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to update user roles. Please try again.',
					'oneaccess'
				),
			} );
		} finally {
			setIsUpdatingRoles( false );
			setShowManageRolesModal( false );
		}
	}, [ userRoles, fetchUsers, selectedUser ] );

	// Handle adding user to sites
	const handleAddUserToSites = useCallback( async () => {
		if ( ! selectedUser ) {
			return;
		}
		setIsAddingToSites( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/add-user-to-sites`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: selectedUser.username,
						fullName: selectedUser.full_name,
						email: selectedUser.email,
						sites: selectedSitesToAdd,
						password,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to add user to sites' );
			}

			const data = ( await response.json() ) as GenericApiResponse;
			if ( ! data.success ) {
				throw new Error(
					data.message || 'Failed to add user to sites'
				);
			}

			const siteNames = selectedSitesToAdd
				.map( ( site ) => site.name )
				.join( ', ' );
			setNotice( {
				type: 'success',
				message:
					__( 'User added to sites successfully:', 'oneaccess' ) +
					siteNames,
			} );

			// Refresh users list
			await fetchUsers();
			setPassword( '' );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to add user to sites. Please try again.',
					'oneaccess'
				),
			} );
		} finally {
			setIsAddingToSites( false );
			setShowAddToSitesModal( false );
		}
	}, [ selectedSitesToAdd, fetchUsers, selectedUser, password ] );

	// Handle page change
	const handlePageChange = ( newPage: number ) => {
		setPage( newPage );
	};

	// Get role label from role value
	const getRoleLabel = ( roleValue: string ): string => {
		return (
			AVAILABLE_ROLES[ roleValue ] ||
			( roleValue ?? __( 'No Role', 'oneaccess' ) )
		);
	};

	const handleUsersCleanup = useCallback( async () => {
		setIsDoingUsersCleanup( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/cleanup-deduplicated-users`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to cleanup disconnected sites users.',
						'oneaccess'
					),
				} );
			}

			const data = ( await response.json() ) as GenericApiResponse;

			if ( ! data.success ) {
				setNotice( {
					type: 'error',
					message:
						data.message ||
						__(
							'Failed to cleanup disconnected sites users.',
							'oneaccess'
						),
				} );
			} else {
				setNotice( {
					type: 'success',
					message: __(
						'Async action is scheduled to do cleanup of disconnected sites users.',
						'oneaccess'
					),
				} );
				// Refresh users list
				await fetchUsers();
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to cleanup disconnected sites users.',
					'oneaccess'
				),
			} );
		} finally {
			setIsDoingUsersCleanup( false );
		}
	}, [ fetchUsers ] );

	const handleRebuildDeduplicatedIndex = useCallback( async () => {
		setIsRebuildingDeduplicatedIndex( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/rebuild-deduplicated-users-index`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				}
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to rebuild deduplicated users index.',
						'oneaccess'
					),
				} );
			}

			const data = ( await response.json() ) as GenericApiResponse;

			if ( ! data.success ) {
				setNotice( {
					type: 'error',
					message:
						data.message ||
						__(
							'Failed to rebuild deduplicated users index.',
							'oneaccess'
						),
				} );
			} else {
				setNotice( {
					type: 'success',
					message: __(
						'Async action is scheduled to rebuild deduplicated users index.',
						'oneaccess'
					),
				} );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to rebuild deduplicated users index.',
					'oneaccess'
				),
			} );
		} finally {
			setIsRebuildingDeduplicatedIndex( false );
			setShowRebuildIndexModal( false );
		}
	}, [] );

	const fetchStrongPassword = useCallback( async () => {
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
					},
				}
			);

			if ( ! response.ok ) {
				setPasswordNotice( {
					type: 'error',
					message: __(
						'Failed to generate password. Please try again later.',
						'oneaccess'
					),
				} );
				throw new Error( 'Failed to generate password' );
			}
			const data = ( await response.json() ) as {
				password?: string;
			};
			if ( ! data.password ) {
				setPasswordNotice( {
					type: 'error',
					message: __(
						'No password generated. Please try again.',
						'oneaccess'
					),
				} );
				return;
			}
			setPasswordNotice( {
				type: 'success',
				message: __( 'Password generated successfully.', 'oneaccess' ),
			} );
			setPassword( data.password );
		} catch {
			setPasswordNotice( {
				type: 'error',
				message: __(
					'Failed to generate a password. Please try again later.',
					'oneaccess'
				),
			} );
		}
	}, [] );

	useEffect( () => {
		const strength = checkPasswordStrength( password );
		setPasswordStrength( strength );
	}, [ password ] );

	const isUserRoleChanged = (): boolean => {
		return Boolean(
			selectedUser?.sites?.some( ( site ) => {
				// Get the current role key stored in state
				const currentRoleInState = userRoles[ site.site_url ];

				// Get the original role key from the user data
				const rolesArray = Array.isArray( site.roles )
					? site.roles
					: [];
				const originalRole =
					rolesArray.length > 0
						? rolesArray[ 0 ] ?? ''
						: Object.keys( AVAILABLE_ROLES ).find(
								( key ) => AVAILABLE_ROLES[ key ] === site.role
						  ) ?? 'subscriber';

				return currentRoleInState !== originalRole;
			} )
		);
	};

	const roleOptions: Array< { label: string; value: string } > =
		Object.entries( AVAILABLE_ROLES ).map( ( [ role, label ] ) => ( {
			label,
			value: role,
		} ) );

	return (
		<>
			<Card>
				<CardHeader>
					<h2>{ __( 'Shared Users', 'oneaccess' ) }</h2>
					<div
						className="oneaccess-shared-users-actions"
						style={ { display: 'flex', gap: '8px' } }
					>
						<Button
							variant="primary"
							onClick={ () => setIsCleanupModalOpen( true ) }
							isBusy={ isDoingUsersCleanup }
							isDestructive
							icon={ trash }
						>
							{ __(
								'Cleanup Disconnected Sites Users',
								'oneaccess'
							) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => {
								setShowRebuildIndexModal( true );
							} }
							isBusy={ isRebuildingDeduplicatedIndex }
							icon={ plus }
						>
							{ __(
								'Rebuild Deduplicated Users Index',
								'oneaccess'
							) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<Grid
						columns={ 4 }
						gap={ 4 }
						style={ { alignItems: 'flex-end' } }
					>
						<TextControl
							placeholder={ __(
								'Search users by name or email..',
								'oneaccess'
							) }
							value={ searchTerm }
							onChange={ ( value ) => setSearchTerm( value ) }
							label={ __( 'Search Users', 'oneaccess' ) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Filter by site', 'oneaccess' ) }
							value={ selectedSiteFilter }
							options={ [
								{
									label: __( 'All Sites', 'oneaccess' ),
									value: '',
								},
								...availableSites.map( ( site ) => ( {
									label: site.name,
									value: site.url,
								} ) ),
							] }
							onChange={ setSelectedSiteFilter }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Filter by role', 'oneaccess' ) }
							value={ selectedUserRole }
							options={ [
								{
									label: __( 'All Roles', 'oneaccess' ),
									value: '',
								},
								...roleOptions,
							] }
							onChange={ setSelectedUserRole }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</Grid>

					<table
						className="wp-list-table widefat fixed striped"
						style={ { marginTop: '16px' } }
					>
						<thead>
							<tr>
								<th>{ __( 'Name', 'oneaccess' ) }</th>
								<th>{ __( 'Email', 'oneaccess' ) }</th>
								<th>{ __( 'Sites', 'oneaccess' ) }</th>
								<th>{ __( 'Actions', 'oneaccess' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ isLoading ? (
								<tr>
									<td
										colSpan={ 4 }
										style={ {
											textAlign: 'center',
											padding: '32px',
										} }
									>
										<Spinner />
										<div style={ { marginTop: '8px' } }>
											{ __(
												'Loading users…',
												'oneaccess'
											) }
										</div>
									</td>
								</tr>
							) : null }

							{ ! isLoading && users.length === 0 && (
								<tr>
									<td
										colSpan={ 4 }
										style={ {
											textAlign: 'center',
											padding: '32px',
										} }
									>
										<p
											style={ {
												margin: 0,
												color: '#6c757d',
											} }
										>
											{ __(
												'No users found.',
												'oneaccess'
											) }
										</p>
									</td>
								</tr>
							) }

							{ ! isLoading &&
								users.map( ( user, index ) => (
									<tr key={ `${ user.id }-${ index }` }>
										<td>
											<strong>
												{ decodeEntities(
													user.full_name ||
														user.username
												) }
											</strong>
										</td>
										<td>{ user.email }</td>
										<td>
											<div
												style={ {
													display: 'flex',
													flexDirection: 'row',
													alignItems: 'center',
													gap: '4px',
													flexWrap: 'wrap',
												} }
											>
												{ user.sites?.length > 0 ? (
													user.sites.map(
														( site, siteIndex ) => (
															<span
																key={ `${ site.site_url }-${ siteIndex }` }
																className="site-badge"
																style={ {
																	display:
																		'inline-block',
																	margin: '0',
																	padding:
																		'4px 10px',
																	backgroundColor:
																		'#007cba',
																	color: '#fff',
																	borderRadius:
																		'4px',
																	fontSize:
																		'12px',
																	fontWeight:
																		'500',
																} }
																title={ `${
																	site.site_name
																} - ${ getRoleLabel(
																	site.role
																) }` }
															>
																{ decodeEntities(
																	site.site_name
																) }{ ' ' }
																(
																{ getRoleLabel(
																	site.role
																) }
																)
															</span>
														)
													)
												) : (
													<span
														style={ {
															color: '#6c757d',
															fontSize: '12px',
														} }
													>
														{ __(
															'No sites assigned',
															'oneaccess'
														) }
													</span>
												) }
											</div>
										</td>
										<td>
											<DropdownMenu
												icon={ moreVertical }
												label={ __(
													'User actions',
													'oneaccess'
												) }
												className="user-actions-dropdown"
												popoverProps={ {
													position: 'bottom left',
												} }
											>
												{ ( {
													onClose,
												}: {
													onClose: () => void;
												} ) => (
													<>
														<MenuGroup>
															<MenuItem
																icon={ people }
																onClick={ () => {
																	handleManageRoles(
																		user
																	);
																	onClose();
																} }
																disabled={
																	! user.sites ||
																	user.sites
																		.length ===
																		0
																}
															>
																{ __(
																	'Manage Roles',
																	'oneaccess'
																) }
															</MenuItem>
															{ getAvailableSitesForUser(
																user
															).length > 0 && (
																<MenuItem
																	icon={
																		globe
																	}
																	onClick={ () => {
																		handleAddToSites(
																			user
																		);
																		onClose();
																	} }
																>
																	{ __(
																		'Add to Sites',
																		'oneaccess'
																	) }
																</MenuItem>
															) }
														</MenuGroup>
														<MenuGroup>
															<MenuItem
																icon={ trash }
																onClick={ () => {
																	handleUserDeletion(
																		user
																	);
																	onClose();
																} }
																isDestructive
																disabled={
																	! user.sites ||
																	user.sites
																		.length ===
																		0
																}
															>
																{ __(
																	'Delete User',
																	'oneaccess'
																) }
															</MenuItem>
														</MenuGroup>
													</>
												) }
											</DropdownMenu>
										</td>
									</tr>
								) ) }
						</tbody>
					</table>

					{
						<div
							style={ {
								marginTop: '16px',
								display: 'flex',
								gap: '8px',
								alignItems: 'center',
								justifyContent: 'center',
							} }
						>
							<Button
								variant="secondary"
								onClick={ () => handlePageChange( page - 1 ) }
								disabled={ page === 1 || isLoading }
							>
								{ __( 'Previous', 'oneaccess' ) }
							</Button>
							<div
								style={ {
									color: '#6c757d',
									fontSize: '14px',
								} }
							>
								{ sprintf(
									/* translators: 1: Current page number. 2: Total pages. */
									__( 'Page %1$s of %2$s', 'oneaccess' ),
									page.toString(),
									( totalPages === 0
										? 1
										: totalPages
									).toString()
								) }
							</div>
							<Button
								variant="secondary"
								onClick={ () => handlePageChange( page + 1 ) }
								disabled={ page >= totalPages || isLoading }
							>
								{ __( 'Next', 'oneaccess' ) }
							</Button>
						</div>
					}
				</CardBody>
			</Card>

			{ /* Manage Roles Modal */ }
			{ showManageRolesModal && selectedUser && (
				<Modal
					title={ __( 'Manage User Roles', 'oneaccess' ) }
					onRequestClose={ () => setShowManageRolesModal( false ) }
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
								{ __( 'Manage roles for user:', 'oneaccess' ) }
								<strong>
									{ selectedUser.full_name ||
										selectedUser.username }
								</strong>{ ' ' }
								( { selectedUser.email } )
							</p>
						</div>

						<div
							style={ {
								maxHeight: '400px',
								overflowY: 'auto',
								border: '1px solid #e1e5e9',
								borderRadius: '8px',
								padding: '16px',
							} }
						>
							<VStack spacing="3">
								{ selectedUser.sites?.map( ( site, index ) => (
									<div
										key={ index }
										style={ {
											padding: '12px',
											border: '1px solid #f0f0f1',
											borderRadius: '4px',
										} }
									>
										<div style={ { marginBottom: '8px' } }>
											<div
												style={ {
													fontWeight: '500',
													color: '#23282d',
												} }
											>
												{ site.site_name }
											</div>
											<div
												style={ {
													fontSize: '12px',
													color: '#6c757d',
												} }
											>
												{ site.site_url }
											</div>
										</div>
										<SelectControl
											value={
												userRoles[ site.site_url ] ||
												'subscriber'
											}
											options={ roleOptions }
											onChange={ ( value ) => {
												setUserRoles( ( prev ) => ( {
													...prev,
													[ site.site_url ]: value,
												} ) );
											} }
											__next40pxDefaultSize
											__nextHasNoMarginBottom
										/>
									</div>
								) ) }
							</VStack>
						</div>

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () =>
									setShowManageRolesModal( false )
								}
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handleUpdateRoles }
								disabled={
									isUpdatingRoles ||
									Object.keys( userRoles ).length === 0 ||
									! isUserRoleChanged()
								}
								isBusy={ isUpdatingRoles }
							>
								<Dashicon
									icon="admin-users"
									style={ { marginRight: '8px' } }
								/>
								{ __( 'Update Roles', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* Add to Sites Modal */ }
			{ showAddToSitesModal && selectedUser && (
				<Modal
					title={ __( 'Add User to Sites', 'oneaccess' ) }
					onRequestClose={ () => {
						setShowAddToSitesModal( false );
						setPassword( '' );
						setPasswordNotice( null );
					} }
					shouldCloseOnClickOutside
					size="medium"
				>
					{ passwordNotice && (
						<Notice
							status={
								passwordNotice.type === 'error'
									? 'error'
									: 'success'
							}
							isDismissible
							onRemove={ () => setPasswordNotice( null ) }
						>
							<p style={ { margin: 0 } }>
								{ passwordNotice.message }
							</p>
						</Notice>
					) }
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
									'Add user to additional sites:',
									'oneaccess'
								) }
								<strong>
									{ selectedUser.full_name ||
										selectedUser.username }
								</strong>{ ' ' }
								( { selectedUser.email } )
							</p>
							<p
								style={ {
									margin: 0,
									color: '#1f1c1a',
									fontSize: '16px',
									fontWeight: 600,
								} }
							>
								{ __(
									'Please note that this will be an async operation which will take a few minutes to complete.',
									'oneaccess'
								) }
							</p>
						</div>

						{ getAvailableSitesForUser( selectedUser ).length >
						0 ? (
							<>
								<HStack justify="flex-start" spacing="3">
									<CheckboxControl
										label={ __(
											'Select All Sites',
											'oneaccess'
										) }
										checked={
											selectedSitesToAdd.length ===
											getAvailableSitesForUser(
												selectedUser
											).length
										}
										onChange={ () => {
											const availableSitesToAddUser =
												getAvailableSitesForUser(
													selectedUser
												);
											if (
												selectedSitesToAdd.length ===
												availableSitesToAddUser.length
											) {
												setSelectedSitesToAdd( [] );
											} else {
												setSelectedSitesToAdd(
													availableSitesToAddUser.map(
														( site ) => ( {
															url: site.url,
															name: site.name,
															api_key:
																site.api_key,
															role:
																site.api_key &&
																'subscriber',
														} )
													)
												);
											}
										} }
										__nextHasNoMarginBottom
									/>
									<Button
										variant="link"
										onClick={ () =>
											setSelectedSitesToAdd( [] )
										}
										disabled={
											selectedSitesToAdd.length === 0
										}
										style={ {
											marginBlockEnd: '0.5rem',
										} }
									>
										{ __( 'Clear Selection', 'oneaccess' ) }
									</Button>
								</HStack>

								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #e1e5e9',
										borderRadius: '8px',
										padding: '16px',
									} }
								>
									<VStack spacing="3">
										{ getAvailableSitesForUser(
											selectedUser
										).map( ( site, index ) => {
											const isSelected =
												selectedSitesToAdd.some(
													( s ) => s.url === site.url
												);
											const selectedSite =
												selectedSitesToAdd.find(
													( s ) => s.url === site.url
												);

											const toggleSite =
												(): SiteToAdd[] => {
													if ( isSelected ) {
														return selectedSitesToAdd.filter(
															( s ) =>
																s.url !==
																site.url
														);
													}
													return [
														...selectedSitesToAdd,
														{
															url: site.url,
															name: site.name,
															api_key:
																site.api_key,
															role: 'subscriber',
														},
													];
												};

											return (
												<div
													key={ index }
													style={ {
														padding: '12px',
														border: '1px solid #f0f0f1',
														borderRadius: '4px',
													} }
												>
													<CheckboxControl
														label={ site.name }
														checked={ isSelected }
														onChange={ () =>
															setSelectedSitesToAdd(
																toggleSite
															)
														}
														__nextHasNoMarginBottom
													/>
													<div
														style={ {
															fontSize: '12px',
															color: '#6c757d',
															marginBottom:
																isSelected
																	? '8px'
																	: '0',
														} }
													>
														{ site.url }
													</div>

													{ isSelected && (
														<div
															style={ {
																marginTop:
																	'8px',
																marginLeft:
																	'24px',
															} }
														>
															<SelectControl
																label={ __(
																	'Role',
																	'oneaccess'
																) }
																value={
																	selectedSite?.role ||
																	'subscriber'
																}
																options={
																	roleOptions
																}
																onChange={ (
																	value
																) => {
																	setSelectedSitesToAdd(
																		(
																			prev
																		) =>
																			prev.map(
																				(
																					s
																				) =>
																					s.url ===
																					site.url
																						? {
																								...s,
																								role: value,
																						  }
																						: s
																			)
																	);
																} }
																__next40pxDefaultSize
																__nextHasNoMarginBottom
															/>
														</div>
													) }
												</div>
											);
										} ) }
									</VStack>
								</div>
							</>
						) : (
							<Notice status="warning" isDismissible={ false }>
								<p style={ { margin: 0 } }>
									{ __(
										'This user is already assigned to all available sites.',
										'oneaccess'
									) }
								</p>
							</Notice>
						) }

						{ /* Add password field if user has no password */ }
						<PasswordComponent
							password={ password }
							showPassword={ showPassword }
							setPassword={ setPassword }
							passwordRef={ passwordRef }
							setShowPassword={ setShowPassword }
							passwordStrength={ passwordStrength }
							fetchStrongPassword={ fetchStrongPassword }
						/>

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setShowAddToSitesModal( false );
									setSelectedSitesToAdd( [] );
								} }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handleAddUserToSites }
								disabled={
									selectedSitesToAdd.length === 0 ||
									isAddingToSites ||
									passwordStrength === 'very-weak' ||
									passwordStrength === 'weak' ||
									password === ''
								}
								isBusy={ isAddingToSites }
							>
								<Icon icon={ plus } />
								{ __( 'Add to Sites', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* User Deletion Modal */ }
			{ showUserDeletionModal && selectedUser && (
				<Modal
					title={ __( 'Delete User', 'oneaccess' ) }
					onRequestClose={ () => setShowUserDeletionModal( false ) }
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
									'Delete User from selected sites:',
									'oneaccess'
								) }
								<strong>
									{ selectedUser.full_name ||
										selectedUser.username }
								</strong>{ ' ' }
								( { selectedUser.email } )
							</p>
						</div>

						{ selectedUser?.sites?.length > 0 ? (
							<>
								<HStack justify="flex-start" spacing="3">
									<CheckboxControl
										label={ __(
											'Select All Sites',
											'oneaccess'
										) }
										checked={
											selectedSitesToDeleteUser.length ===
											selectedUser?.sites?.length
										}
										onChange={ () => {
											const availableSitesToDeleteUser =
												selectedUser?.sites || [];
											if (
												selectedSitesToDeleteUser.length ===
												availableSitesToDeleteUser.length
											) {
												setSelectedSitesToDeleteUser(
													[]
												);
											} else {
												setSelectedSitesToDeleteUser(
													availableSitesToDeleteUser.map(
														( site ) => ( {
															site_url:
																site.site_url,
															site_name:
																site.site_name,
														} )
													)
												);
											}
										} }
										__nextHasNoMarginBottom
									/>
									<Button
										variant="link"
										onClick={ () =>
											setSelectedSitesToDeleteUser( [] )
										}
										disabled={
											selectedSitesToDeleteUser.length ===
											0
										}
										style={ {
											marginBlockEnd: '0.5rem',
										} }
									>
										{ __( 'Clear Selection', 'oneaccess' ) }
									</Button>
								</HStack>

								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #e1e5e9',
										borderRadius: '8px',
										padding: '16px',
									} }
								>
									<VStack spacing="3">
										{ selectedUser?.sites?.map(
											( site, index ) => {
												const isSelected =
													selectedSitesToDeleteUser.some(
														( s ) =>
															s.site_url ===
															site.site_url
													);
												const toggleSite =
													(): SiteToDelete[] => {
														if ( isSelected ) {
															return selectedSitesToDeleteUser.filter(
																( s ) =>
																	s.site_url !==
																	site.site_url
															);
														}
														return [
															...selectedSitesToDeleteUser,
															{
																site_url:
																	site.site_url,
																site_name:
																	site.site_name,
															},
														];
													};
												return (
													<div
														key={ index }
														style={ {
															padding: '12px',
															border: '1px solid #f0f0f1',
															borderRadius: '4px',
														} }
													>
														<CheckboxControl
															className="oneaccess-site-checkbox"
															label={
																site.site_name
															}
															checked={
																isSelected
															}
															onChange={ () =>
																setSelectedSitesToDeleteUser(
																	toggleSite
																)
															}
															__nextHasNoMarginBottom
														/>
														<div
															style={ {
																fontSize:
																	'12px',
																color: '#6c757d',
																marginTop:
																	'2px',
															} }
														>
															{ site.site_url }
														</div>
													</div>
												);
											}
										) }
									</VStack>
								</div>
							</>
						) : (
							<Notice status="warning" isDismissible={ false }>
								<p style={ { margin: 0 } }>
									{ __(
										'This user cannot be deleted.',
										'oneaccess'
									) }
								</p>
							</Notice>
						) }

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setShowUserDeletionModal( false );
									setSelectedSitesToDeleteUser( [] );
								} }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								isDestructive
								onClick={ handleUserDeletionFromSelectedSites }
								disabled={
									selectedSitesToDeleteUser.length === 0 ||
									isDeletingUser
								}
								isBusy={ isDeletingUser }
							>
								<Icon icon={ trash } />
								{ __( 'Delete user', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* Show cleanup modal confirmation */ }
			{ isCleanupModalOpen && (
				<Modal
					title={ __( 'Confirm Cleanup', 'oneaccess' ) }
					onRequestClose={ () => setIsCleanupModalOpen( false ) }
					shouldCloseOnClickOutside
					size="medium"
				>
					<VStack spacing="4">
						<p style={ { color: '#6c757d', fontSize: '14px' } }>
							{ __(
								'Cleaning up users associated with disconnected sites is an async process that may take some time to complete and cannot be undone. Are you sure you want to proceed?',
								'oneaccess'
							) }
						</p>
						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => setIsCleanupModalOpen( false ) }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								isDestructive
								onClick={ () => {
									handleUsersCleanup();
									setIsCleanupModalOpen( false );
								} }
								isBusy={ isDoingUsersCleanup }
								icon={ trash }
							>
								{ __( 'Confirm Cleanup', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* Rebuild Deduplicated Users Index Modal */ }
			{ showRebuildIndexModal && (
				<Modal
					title={ __(
						'Rebuild Deduplicated Users Index',
						'oneaccess'
					) }
					onRequestClose={ () => setShowRebuildIndexModal( false ) }
					shouldCloseOnClickOutside
					size="medium"
				>
					<VStack spacing="4">
						<p style={ { color: '#6c757d', fontSize: '14px' } }>
							{ __(
								'Rebuilding the deduplicated users index is async process that may take some time to complete depending on the number of users in your network. Are you sure you want to proceed?',
								'oneaccess'
							) }
						</p>
						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () =>
									setShowRebuildIndexModal( false )
								}
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => {
									handleRebuildDeduplicatedIndex();
								} }
								isBusy={ isRebuildingDeduplicatedIndex }
								icon={ plus }
							>
								{ __( 'Rebuild Index', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* Notice Snackbar */ }
			{ notice && notice.message && (
				<Snackbar
					className={
						notice.type === 'error'
							? 'oneaccess-error-notice'
							: 'oneaccess-success-notice'
					}
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Snackbar>
			) }
		</>
	);
};

const PasswordComponent = ( {
	password,
	showPassword,
	setPassword,
	passwordRef,
	setShowPassword,
	passwordStrength,
	fetchStrongPassword,
}: {
	password: string;
	showPassword: boolean;
	setPassword: ( value: string ) => void;
	passwordRef: MutableRefObject< string >;
	setShowPassword: ( value: boolean ) => void;
	passwordStrength: StrengthLevel | 'default';
	fetchStrongPassword: () => Promise< void >;
} ) => {
	return (
		<form>
			{ /* Hidden username field to prevent browser autofill */ }
			<input
				type="text"
				name="username"
				style={ { display: 'none' } }
				autoComplete="username"
			/>
			<VStack spacing="2" style={ { gap: '0px' } }>
				<HStack
					alignment="left"
					spacing="2"
					style={ { alignItems: 'flex-start' } }
				>
					<TextControl
						label={ __( 'Password*', 'oneaccess' ) }
						type={ showPassword ? 'text' : 'password' }
						value={ password }
						onChange={ ( value ) => {
							setPassword( value );
							passwordRef.current = value;
						} }
						autoComplete="new-password"
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
						onClick={ () => setShowPassword( ! showPassword ) }
						aria-label={
							showPassword
								? __( 'Hide password', 'oneaccess' )
								: __( 'Show password', 'oneaccess' )
						}
						style={ {
							marginTop: '1.5rem',
							height: '2.5rem',
							minWidth: 'auto',
						} }
						variant="secondary"
					>
						<Icon
							icon={ showPassword ? 'visibility' : 'hidden' }
							size={ 20 }
						/>
					</Button>
				</HStack>
				{ password && (
					<div style={ { marginBottom: '12px' } }>
						<div style={ { fontSize: '12px', color: '#6c757d' } }>
							{ __( 'Password Strength:', 'oneaccess' ) }{ ' ' }
							<span
								style={ {
									color: getStrengthColor( passwordStrength ),
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
										strengthWidths[ passwordStrength ] ||
										strengthWidths[ 'default' ],
									backgroundColor:
										getStrengthColor( passwordStrength ),
									transition: 'width 0.3s ease-in-out',
								} }
							/>
						</div>
					</div>
				) }
				<Button
					variant="secondary"
					onClick={ () => {
						fetchStrongPassword();
					} }
					style={ {
						width: 'fit-content',
						marginBlockStart: '12px',
					} }
				>
					{ __( 'Generate strong password', 'oneaccess' ) }
				</Button>
			</VStack>
		</form>
	);
};

export default SharedUsers;
