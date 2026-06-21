/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CardHeader,
	Snackbar,
	Card,
	CardBody,
	__experimentalGrid as Grid,
	SelectControl,
	Button,
	Modal,
	TextareaControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { arrowLeft } from '@wordpress/icons';

const NONCE = window.OneAccess.nonce;
const API_NAMESPACE = window.OneAccess.restUrl + '/oneaccess/v1';
const API_KEY = window.OneAccess.api_key;
const PER_PAGE = 20;

const ProfileRequests = ( { setProfileRequestsCount, availableSites } ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( {
		type: '',
		message: '',
	} );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ selectedSiteFilter, setSelectedSiteFilter ] = useState( '' );
	const [ requestStatusFilter, setRequestStatusFilter ] = useState( '' );
	const [ allSitesProfileRequests, setAllSitesProfileRequests ] = useState(
		[]
	);
	const [ isViewModalOpen, setIsViewModalOpen ] = useState( false );
	const [ isRejectModalOpen, setIsRejectModalOpen ] = useState( false );
	const [ selectedRequest, setSelectedRequest ] = useState( null );
	const [ rejectionComment, setRejectionComment ] = useState( '' );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ hasMore, setHasMore ] = useState( false );

	const fetchProfileRequests = useCallback(
		async ( cursor = 0 ) => {
			setIsLoading( true );
			try {
				// Build query params
				const params = new URLSearchParams();
				if ( selectedSiteFilter ) {
					params.append( 'site', selectedSiteFilter );
				}
				if ( requestStatusFilter ) {
					params.append( 'status', requestStatusFilter );
				}
				if ( searchTerm ) {
					params.append( 'search_query', searchTerm );
				}
				if ( cursor > 0 ) {
					params.append( 'cursor', cursor.toString() );
				}

				const response = await fetch(
					`${ API_NAMESPACE }/get-profile-requests?${ params.toString() }&t=${ Date.now() }`,
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': NONCE,
							'Cache-Control': 'no-cache',
						},
					}
				);

				if ( ! response.ok ) {
					setNotice( {
						type: 'error',
						message: __(
							'Failed to fetch profile requests.',
							'oneaccess'
						),
					} );
					throw new Error( 'Failed to fetch profile requests' );
				}

				const data = await response.json();

				// Transform the data to match the component structure
				const transformedRequests = ( data.profile_requests || [] ).map(
					( request ) => {
						const requestData = request.request_data || {};

						return {
							id: request.id,
							user_id: request.user_id,
							user_name: requestData.user_name || '',
							user_email: requestData.user_email || '',
							user_login: requestData.user_login || '',
							requested_by: requestData.requested_by || '',
							requested_at:
								requestData.requested_at || request.created_at,
							status: request.status,
							site_name: request.site_name || '',
							metadata: requestData.metadata || {},
							data: requestData.data || {},
							rejection_comment: request.comment || '',
							created_at: request.created_at,
							updated_at: request.updated_at,
						};
					}
				);

				setAllSitesProfileRequests( transformedRequests );

				// Set total pending count for badge (always unfiltered total pending)
				const pendingCount = data.total_pending_count || 0;
				setProfileRequestsCount( pendingCount );

				// Get pagination data from API
				const pagination = data.pagination || {};
				setHasMore( pagination.has_more || false );
				setTotalPages( pagination.total_pages || 1 );
			} catch {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to fetch profile requests.',
						'oneaccess'
					),
				} );
			} finally {
				setIsLoading( false );
			}
		},
		[
			setProfileRequestsCount,
			selectedSiteFilter,
			requestStatusFilter,
			searchTerm,
		]
	);

	const handleAcceptRequest = async ( request ) => {
		setIsProcessing( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/approve-profile-request`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
					body: JSON.stringify( {
						request_id: request.id,
						user_id: request.user_id,
						user_email: request.user_email,
						user_login: request.user_login,
						site_name: request.site_name,
						metadata: request.metadata,
						data: request.data,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to approve request' );
			}

			setNotice( {
				type: 'success',
				message: __(
					'Profile request approved successfully.',
					'oneaccess'
				),
			} );
			fetchProfileRequests();
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to approve profile request.',
					'oneaccess'
				),
			} );
		} finally {
			setIsProcessing( false );
			setIsViewModalOpen( false );
		}
	};

	const handleRejectRequest = useCallback( async () => {
		if ( ! rejectionComment.trim() ) {
			setNotice( {
				type: 'error',
				message: __(
					'Please provide a rejection comment.',
					'oneaccess'
				),
			} );
			return;
		}
		setIsProcessing( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/reject-profile-request`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
					body: JSON.stringify( {
						request_id: selectedRequest.id,
						user_id: selectedRequest.user_id,
						user_email: selectedRequest.user_email,
						site_name: selectedRequest.site_name,
						rejection_comment: rejectionComment,
					} ),
				}
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to reject request' );
			}

			setNotice( {
				type: 'success',
				message: __(
					'Profile request rejected successfully.',
					'oneaccess'
				),
			} );
			fetchProfileRequests();
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Failed to reject profile request.', 'oneaccess' ),
			} );
		} finally {
			setIsProcessing( false );
			setIsRejectModalOpen( false );
			setIsViewModalOpen( false );
			setRejectionComment( '' );
		}
	}, [ rejectionComment, selectedRequest, fetchProfileRequests ] );

	const openViewModal = ( request ) => {
		setSelectedRequest( request );
		setIsViewModalOpen( true );
	};

	const openRejectModal = () => {
		setIsRejectModalOpen( true );
		setIsViewModalOpen( false );
	};

	const closeModals = () => {
		setIsViewModalOpen( false );
		setIsRejectModalOpen( false );
		setRejectionComment( '' );
	};

	const renderChangesTable = ( request ) => {
		const changes = [];

		// Add data changes (user fields like display_name, first_name, etc.)
		if (
			request.data &&
			typeof request.data === 'object' &&
			Object.keys( request.data ).length > 0
		) {
			Object.entries( request.data ).forEach( ( [ key, value ] ) => {
				if (
					value &&
					typeof value === 'object' &&
					( 'old' in value || 'new' in value )
				) {
					changes.push( {
						field: key
							.replace( /_/g, ' ' )
							.replace( /\b\w/g, ( l ) => l.toUpperCase() ),
						oldValue: value.old || __( 'Empty', 'oneaccess' ),
						newValue: value.new || __( 'Empty', 'oneaccess' ),
						type: 'data',
					} );
				}
			} );
		}

		// Add metadata changes (custom fields like description, bio, etc.)
		if (
			request.metadata &&
			typeof request.metadata === 'object' &&
			Object.keys( request.metadata ).length > 0
		) {
			Object.entries( request.metadata ).forEach( ( [ key, value ] ) => {
				if (
					value &&
					typeof value === 'object' &&
					( 'old' in value || 'new' in value )
				) {
					changes.push( {
						field: key
							.replace( /_/g, ' ' )
							.replace( /\b\w/g, ( l ) => l.toUpperCase() ),
						oldValue: value.old || __( 'Empty', 'oneaccess' ),
						newValue: value.new || __( 'Empty', 'oneaccess' ),
						type: 'metadata',
					} );
				}
			} );
		}

		return (
			<table
				className="wp-list-table widefat fixed striped"
				style={ { marginTop: '16px' } }
			>
				<thead>
					<tr>
						<th>{ __( 'Field', 'oneaccess' ) }</th>
						<th>{ __( 'Old Value', 'oneaccess' ) }</th>
						<th>{ __( 'New Value', 'oneaccess' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ changes.length === 0 ? (
						<tr>
							<td colSpan="3" style={ { textAlign: 'center' } }>
								{ __( 'No changes found.', 'oneaccess' ) }
							</td>
						</tr>
					) : (
						changes.map( ( change, index ) => (
							<tr key={ index }>
								<td>
									<strong>{ change.field }</strong>
								</td>
								<td
									style={ {
										wordBreak: 'break-word',
										whiteSpace: 'pre-wrap',
									} }
								>
									{ decodeEntities(
										String( change.oldValue )
									) }
								</td>
								<td
									style={ {
										wordBreak: 'break-word',
										whiteSpace: 'pre-wrap',
									} }
								>
									{ decodeEntities(
										String( change.newValue )
									) }
								</td>
							</tr>
						) )
					) }
				</tbody>
			</table>
		);
	};

	// Get unique site names for filter dropdown (from API, ensure iterable)
	const siteOptions = [
		{ label: __( 'All Sites', 'oneaccess' ), value: '' },
		...( Array.isArray( availableSites ) ? availableSites : [] ).map(
			( site ) => ( {
				label: decodeEntities( site?.name ),
				value: site?.name,
			} )
		),
	];

	// Request status filter options
	const statusOptions = [
		{ label: __( 'All Status', 'oneaccess' ), value: '' },
		{ label: __( 'Pending', 'oneaccess' ), value: 'pending' },
		{ label: __( 'Rejected', 'oneaccess' ), value: 'rejected' },
		{ label: __( 'Approved', 'oneaccess' ), value: 'approved' },
	];

	const getStatusLabel = ( value ) => {
		const option = statusOptions.find( ( opt ) => opt.value === value );
		return option ? option.label : value;
	};

	const getStatusColor = ( status ) => {
		switch ( status ) {
			case 'pending':
				return { backgroundColor: '#ffc107', color: '#000' };
			case 'rejected':
				return { backgroundColor: '#dc3545', color: '#fff' };
			case 'approved':
				return { backgroundColor: '#28a745', color: '#fff' };
			default:
				return { backgroundColor: '#6c757d', color: '#fff' };
		}
	};

	// Initial load
	useEffect( () => {
		fetchProfileRequests( 0 );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Refetch when filters change (reset to page 1)
	useEffect( () => {
		setPage( 1 );
		setHasMore( false );
		fetchProfileRequests( 0 );
	}, [ selectedSiteFilter, requestStatusFilter, searchTerm ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// No client-side filtering/pagination needed - results come pre-paginated/filtered from API
	const displayedRequests = allSitesProfileRequests;

	// Handle page change
	const handlePageChange = ( newPage ) => {
		setPage( newPage );
		const newCursor = ( newPage - 1 ) * PER_PAGE;
		fetchProfileRequests( newCursor );
	};

	return (
		<>
			<Card>
				<CardHeader>
					<h2>{ __( 'Profile Update Requests', 'oneaccess' ) }</h2>
				</CardHeader>
				<CardBody>
					<Grid
						columns="4"
						gap="4"
						style={ { alignItems: 'flex-end' } }
					>
						<TextControl
							placeholder={ __(
								'Search by name, email, or requested by…',
								'oneaccess'
							) }
							value={ searchTerm }
							onChange={ setSearchTerm }
							label={ __( 'Search Requests', 'oneaccess' ) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Filter by site', 'oneaccess' ) }
							value={ selectedSiteFilter }
							options={ siteOptions }
							onChange={ setSelectedSiteFilter }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={ __( 'Filter by status', 'oneaccess' ) }
							value={ requestStatusFilter }
							options={ statusOptions }
							onChange={ setRequestStatusFilter }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</Grid>
					<table
						className="wp-list-table widefat fixed striped "
						style={ { marginTop: '16px' } }
					>
						<thead>
							<tr>
								<th>{ __( 'Site', 'oneaccess' ) }</th>
								<th>{ __( 'User', 'oneaccess' ) }</th>
								<th>{ __( 'Email', 'oneaccess' ) }</th>
								<th>{ __( 'Requested by', 'oneaccess' ) }</th>
								<th>{ __( 'Requested at', 'oneaccess' ) }</th>
								<th>{ __( 'Status', 'oneaccess' ) }</th>
								<th>{ __( 'Actions', 'oneaccess' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ isLoading && (
								<tr>
									<td
										colSpan="7"
										style={ {
											textAlign: 'center',
											padding: '20px',
										} }
									>
										<Spinner />
									</td>
								</tr>
							) }
							{ ! isLoading && displayedRequests.length === 0 && (
								<tr>
									<td
										colSpan="7"
										style={ { textAlign: 'center' } }
									>
										{ __(
											'No profile requests found.',
											'oneaccess'
										) }
									</td>
								</tr>
							) }
							{ ! isLoading &&
								displayedRequests.map( ( request, index ) => (
									<tr
										key={ `${ request.id }-${ request.site_name }-${ index }` }
									>
										<td>
											{ decodeEntities(
												request.site_name
											) }
										</td>
										<td>
											{ decodeEntities(
												request.user_name
											) }
										</td>
										<td>{ request.user_email }</td>
										<td>
											{ decodeEntities(
												request.requested_by
											) }
										</td>
										<td>{ request.requested_at }</td>
										<td>
											<span
												className={ `status-badge status-${ request.status }` }
												style={ {
													display: 'inline-block',
													padding: '2px 8px',
													borderRadius: '4px',
													fontSize: '12px',
													fontWeight: '400',
													...getStatusColor(
														request.status
													),
												} }
											>
												{ getStatusLabel(
													request.status
												) }
											</span>
										</td>
										<td>
											<Button
												variant="primary"
												onClick={ () =>
													openViewModal( request )
												}
												disabled={ isProcessing }
											>
												{ __(
													'View Details',
													'oneaccess'
												) }
											</Button>
										</td>
									</tr>
								) ) }
						</tbody>
					</table>
					<div
						style={ {
							marginTop: '16px',
							display: 'flex',
							justifyContent: 'center',
							alignItems: 'center',
							gap: '8px',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => handlePageChange( page - 1 ) }
							disabled={ page === 1 || isLoading }
						>
							{ __( 'Previous', 'oneaccess' ) }
						</Button>
						<span>
							{ __( 'Page', 'oneaccess' ) } { page }{ ' ' }
							{ __( 'of', 'oneaccess' ) } { totalPages }
						</span>
						<Button
							variant="secondary"
							onClick={ () => handlePageChange( page + 1 ) }
							disabled={ ! hasMore || isLoading }
						>
							{ __( 'Next', 'oneaccess' ) }
						</Button>
					</div>
				</CardBody>
			</Card>

			{ /* View Request Modal */ }
			{ isViewModalOpen && selectedRequest && (
				<Modal
					title={ __( 'Profile Requested Changes', 'oneaccess' ) }
					onRequestClose={ closeModals }
					style={ { minWidth: '600px' } }
					size="medium"
					shouldCloseOnClickOutside
				>
					<div
						style={ {
							marginBottom: '20px',
							display: 'grid',
							gridTemplateColumns: 'repeat(2,1fr)',
							gap: '12px',
						} }
					>
						<p style={ { margin: '0' } }>
							<strong>{ __( 'User:', 'oneaccess' ) }</strong>{ ' ' }
							{ decodeEntities( selectedRequest.user_name ) }
						</p>
						<p style={ { margin: '0' } }>
							<strong>{ __( 'Email:', 'oneaccess' ) }</strong>{ ' ' }
							{ selectedRequest.user_email }
						</p>
						<p style={ { margin: '0' } }>
							<strong>{ __( 'Site:', 'oneaccess' ) }</strong>{ ' ' }
							{ decodeEntities( selectedRequest.site_name ) }
						</p>
						<p style={ { margin: '0' } }>
							<strong>
								{ __( 'Requested By:', 'oneaccess' ) }
							</strong>{ ' ' }
							{ decodeEntities( selectedRequest.requested_by ) }
						</p>
						<p style={ { margin: '0' } }>
							<strong>
								{ __( 'Requested At:', 'oneaccess' ) }
							</strong>{ ' ' }
							{ selectedRequest.requested_at }
						</p>
						<p style={ { margin: '0' } }>
							<strong>{ __( 'Status:', 'oneaccess' ) }</strong>{ ' ' }
							{ getStatusLabel( selectedRequest.status ) }
						</p>
					</div>

					<h3>{ __( 'Requested Changes:', 'oneaccess' ) }</h3>
					{ renderChangesTable( selectedRequest ) }

					{ selectedRequest.status === 'pending' && (
						<div
							style={ {
								marginTop: '20px',
								display: 'flex',
								gap: '10px',
								justifyContent: 'flex-end',
							} }
						>
							<Button
								variant="secondary"
								isDestructive
								onClick={ openRejectModal }
								disabled={ isProcessing }
							>
								{ __( 'Reject', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ () =>
									handleAcceptRequest( selectedRequest )
								}
								disabled={ isProcessing }
								isBusy={ isProcessing }
							>
								{ __( 'Accept', 'oneaccess' ) }
							</Button>
						</div>
					) }
					{ selectedRequest.status === 'rejected' &&
						selectedRequest.rejection_comment &&
						selectedRequest.rejection_comment.trim().length > 0 && (
							<div
								style={ {
									marginTop: '20px',
									background: '#fee2e2',
									borderLeft: '4px solid #dc2626',
									borderRadius: '4px',
									padding: '12px 16px',
								} }
							>
								<p
									style={ {
										fontSize: '15px',
										color: '#7f1d1d',
										margin: '0',
									} }
								>
									<strong
										style={ {
											color: '#991b1b',
											fontWeight: '600',
										} }
									>
										{ __(
											'Rejection Comment:',
											'oneaccess'
										) }
									</strong>
									<span>
										{ decodeEntities(
											selectedRequest.rejection_comment
										) }
									</span>
								</p>
							</div>
						) }
				</Modal>
			) }

			{ /* Reject Request Modal */ }
			{ isRejectModalOpen && (
				<Modal
					title={ __(
						'Reject Profile Updates Changes',
						'oneaccess'
					) }
					onRequestClose={ () => {
						setIsRejectModalOpen( false );
						setRejectionComment( '' );
					} }
					size="medium"
					shouldCloseOnClickOutside
				>
					<p>
						{ __(
							'Please provide a reason for rejecting this profile update request:',
							'oneaccess'
						) }
					</p>
					<TextareaControl
						placeholder={ __(
							'Enter rejection reason…',
							'oneaccess'
						) }
						value={ rejectionComment }
						onChange={ setRejectionComment }
						rows={ 4 }
						__nextHasNoMarginBottom
					/>
					<div
						style={ {
							marginTop: '20px',
							display: 'flex',
							gap: '10px',
							justifyContent: 'flex-end',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => {
								setIsRejectModalOpen( false );
								setRejectionComment( '' );
								setIsViewModalOpen( true );
							} }
							disabled={ isProcessing }
							icon={ arrowLeft }
						>
							{ __( 'Back', 'oneaccess' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleRejectRequest }
							isDestructive
							disabled={
								isProcessing || ! rejectionComment.trim()
							}
							isBusy={ isProcessing }
						>
							{ __( 'Reject Request', 'oneaccess' ) }
						</Button>
					</div>
				</Modal>
			) }

			{ notice.message && (
				<Snackbar
					isDismissible
					status={ notice.type }
					className={
						notice.type === 'error'
							? 'oneaccess-error-notice'
							: 'oneaccess-success-notice'
					}
					onRemove={ () => setNotice( { type: '', message: '' } ) }
				>
					{ notice.message }
				</Snackbar>
			) }
		</>
	);
};

export default ProfileRequests;
