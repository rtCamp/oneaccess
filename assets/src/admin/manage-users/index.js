/**
 * WordPress dependencies
 */
import {
	createRoot,
	useState,
	useEffect,
	useCallback,
} from '@wordpress/element';
import { Icon, plus, pencil, people } from '@wordpress/icons';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { Spinner, Snackbar } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CreateUser from '../../components/CreateUser';
import SharedUsers from '../../components/SharedUsers';
import ProfileRequests from '../../components/ProfileRequests';

const NONCE = window.OneAccess.nonce;
const API_NAMESPACE = window.OneAccess.restUrl + '/oneaccess/v1';
const availableSites = window.OneAccess.availableSites || [];

const TabPanel = () => {
	const [ activeTab, setActiveTab ] = useState( 'users' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ profileRequestsCount, setProfileRequestsCount ] = useState( 0 );
	const [ notice, setNotice ] = useState( {
		type: '',
		message: '',
	} );

	const fetchProfileRequestsCount = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/get-profile-requests?${ new Date()
					.getTime()
					.toString() }`,
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
				throw new Error( 'Failed to fetch profile requests count' );
			}
			const data = await response.json();
			setProfileRequestsCount( data?.total_pending_count || 0 );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __(
					'Failed to fetch profile requests count.',
					'oneaccess'
				),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchProfileRequestsCount();
	}, [] ); /* eslint-disable-line react-hooks/exhaustive-deps */

	// Sync activeTab with URL query parameter on mount and popstate
	useEffect( () => {
		const syncTabFromUrl = () => {
			const params = new URLSearchParams( window.location.search );
			const tabFromUrl = params.get( 'tab' );
			if (
				tabFromUrl &&
				tabs.some( ( tab ) => tab.name === tabFromUrl )
			) {
				setActiveTab( tabFromUrl );
			} else {
				setActiveTab( 'users' );
			}
		};

		// Run on mount
		syncTabFromUrl();

		// Listen for popstate event (browser back/forward)
		window.addEventListener( 'popstate', syncTabFromUrl );

		// Cleanup listener on unmount
		return () => window.removeEventListener( 'popstate', syncTabFromUrl );
	}, [] ); /* eslint-disable-line react-hooks/exhaustive-deps */

	// Update URL when activeTab changes
	const handleTabChange = ( tabName ) => {
		setActiveTab( tabName );
		const params = new URLSearchParams( window.location.search );
		params.set( 'tab', tabName );
		window.history.pushState(
			{},
			'',
			`${ window.location.pathname }?${ params.toString() }`
		);
	};

	const tabs = [
		{
			name: 'users',
			title: 'Users',
			icon: people,
			content: <SharedUsers availableSites={ availableSites } />,
		},
		{
			name: 'create-user',
			title: 'Create User',
			icon: plus,
			content: <CreateUser availableSites={ availableSites } />,
		},
		{
			name: 'profile-requests',
			title: 'Profile Requests',
			icon: pencil,
			content: (
				<ProfileRequests
					setProfileRequestsCount={ setProfileRequestsCount }
					availableSites={ availableSites }
				/>
			),
		},
	];

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<>
			<div className="tab-panel">
				{ /* Tab Navigation */ }
				<div className="tab-nav__wrapper">
					<nav className="tab-nav" aria-label="Tabs">
						{ tabs.map( ( tab ) => (
							<button
								key={ tab.name }
								onClick={ () => handleTabChange( tab.name ) }
								className={ `tab-nav__button ${
									activeTab === tab.name
										? 'tab-nav__button--active'
										: ''
								}` }
								aria-current={
									activeTab === tab.name ? 'page' : undefined
								}
							>
								<Icon icon={ tab.icon } size={ 24 } />
								<span>{ decodeEntities( tab.title ) }</span>
								{ tab.name === 'profile-requests' &&
									profileRequestsCount > 0 && (
										<span
											className="tab-nav__badge"
											aria-label={ `${ profileRequestsCount } new profile requests` }
											style={ {
												marginLeft: '0.25rem',
												backgroundColor: '#d63638',
												color: '#fff',
												borderRadius: '2px',
												padding: '0.25rem 0.5rem',
												height: '1rem',
												width: 'auto',
											} }
										>
											{ profileRequestsCount }
										</span>
									) }
							</button>
						) ) }
					</nav>
				</div>

				{ /* Tab Content */ }
				<div className="tab-content__wrapper">
					{ tabs.find( ( tab ) => tab.name === activeTab )?.content }
				</div>
			</div>
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

// Render to Gutenberg admin page with ID: oneaccess-manage-user
const target = document.getElementById( 'oneaccess-manage-user' );
if ( target ) {
	const root = createRoot( target );
	root.render( <TabPanel /> );
}
