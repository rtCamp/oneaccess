const fieldsToDisable = [
	'admin_color',
	'first_name',
	'last_name',
	'nickname',
	'facebook',
	'instagram',
	'linkedin',
	'myspace',
	'pinterest',
	'soundcloud',
	'tumblr',
	'wikipedia',
	'twitter',
	'youtube',
	'description',
	'display_name',
	'email',
	'url',
];

const UserProfileRequest = window.OneAccessProfile;

document.addEventListener(
	'DOMContentLoaded',
	() => {
		if ( UserProfileRequest?.request?.status !== 'pending' ) {
			return;
		}
		fieldsToDisable.forEach( ( field ) => {
			const input = document.querySelector< HTMLInputElement >(
				'#' + field
			);
			if ( input ) {
				input.disabled = true;
			}
		} );
		const submitBtn = document.querySelector< HTMLInputElement >(
			'input[type="submit"][name="submit"][id="submit"]'
		);
		if ( submitBtn ) {
			submitBtn.disabled = true;
		}
	},
	{ once: true }
);
