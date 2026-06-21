/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url: string ): boolean => {
	try {
		new URL( url );
		return true;
	} catch {
		return false;
	}
};

/**
 * Validates if a given string is a valid email address.
 *
 * @param {string} email - The email string to validate.
 *
 * @return {boolean} True if the email is valid, false otherwise.
 */
export const isValidEmail = ( email: string ): boolean => {
	const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
	return pattern.test( email );
};

/**
 * Checks the strength of a given password.
 *
 * @param {string} password - The password to check.
 *
 * @return {string} The strength of the password: 'very-weak', 'weak', 'medium', or 'strong'.
 */

export const checkPasswordStrength = ( password: string ): string => {
	let strength = 'weak';
	if (
		password.length >= 12 &&
		/[A-Z]/.test( password ) &&
		/[0-9]/.test( password ) &&
		/[^A-Za-z0-9]/.test( password )
	) {
		strength = 'strong';
	} else if (
		password.length >= 8 &&
		/[A-Z]/.test( password ) &&
		/[a-z]/.test( password ) &&
		/[0-9]/.test( password )
	) {
		strength = 'medium';
	} else if ( password.length >= 8 ) {
		strength = 'weak';
	} else if ( password.length > 0 ) {
		strength = 'very-weak';
	}
	return strength;
};

/**
 * Mapping of password strength levels to their corresponding width percentages.
 *
 * @return {Object} An object mapping password strength levels to width percentages.
 */
export const strengthWidths = {
	'very-weak': '25%',
	weak: '50%',
	medium: '75%',
	strong: '100%',
	default: '0%',
};

/**
 * Gets the color associated with a given password strength.
 *
 * @param {string} passwordStrength - The strength of the password.
 *
 * @return {string} The color code associated with the password strength.
 */
export const getStrengthColor = ( passwordStrength: string ): string => {
	switch ( passwordStrength ) {
		case 'very-weak':
			return '#dc3545';
		case 'weak':
			return '#ff6b6b';
		case 'medium':
			return '#ffc107';
		case 'strong':
			return '#28a745';
		default:
			return '#e1e5e9';
	}
};
