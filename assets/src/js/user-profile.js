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

const UserProfileRequest = OneAccessProfile || {};

document.addEventListener(
	'DOMContentLoaded',
	() => {
		if ( UserProfileRequest?.request?.status !== 'pending' ) {
			return;
		}
		fieldsToDisable.forEach( function ( field ) {
			const input = document.querySelector( '#' + field );
			if ( input ) {
				input.disabled = true;
			}
		} );
		// JavaScript
		const submitBtn = document.querySelector(
			'input[type="submit"][name="submit"][id="submit"]'
		);
		if ( submitBtn ) {
			submitBtn.disabled = true;
		}
	},
	{ once: true }
);
