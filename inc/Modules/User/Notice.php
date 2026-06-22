<?php
/**
 * This is to create user notices.
 *
 * @package OneAccess
 */

declare(strict_types = 1);

namespace OneAccess\Modules\User;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Core\DB;
use OneAccess\Modules\Settings\Settings;

/**
 * Class Notice
 */
class Notice implements Registrable {
	/**
	 * Rejection comment.
	 *
	 * @var string
	 */
	private static $rejection_comment;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// need to use admin_enqueue_scripts because using admin_init with WP_Screen is not identifying current screen and returning NULL.
		add_action( 'admin_enqueue_scripts', [ $this, 'user_profile_notices' ], 10, 1 );
	}

	/**
	 * Display user profile notices.
	 *
	 * @param string $hook_suffix Admin page name.
	 */
	public function user_profile_notices( $hook_suffix ): void {
		// early return if this is not a brand site.
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		// early return if not on user profile screen or user edit screen.
		if ( ! in_array( $hook_suffix, [ 'profile.php', 'user-edit.php' ], true ) ) {
			return;
		}

		// get user from user_id or current user.
		$user_id = isset( $_GET['user_id'] ) ? filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT ) : get_current_user_id();  // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is to know on which user profile page we are to show notice or not.
		$user_id = (int) $user_id;

		// get user profile request data.
		$profile_request_data = DB::get_latest_profile_request_by_user_id( $user_id );

		if ( ! is_array( $profile_request_data ) || empty( $profile_request_data ) ) {
			return;
		}

		// get user request status.
		$request_status = $profile_request_data['status'] ?? 'pending';

		// if request is pending, show notice.
		if ( 'pending' === $request_status ) {
			add_action( 'admin_notices', [ $this, 'pending_notice' ] );
		} elseif ( 'rejected' === $request_status ) {
			self::$rejection_comment = $profile_request_data['comment'] ?? '';
			add_action( 'admin_notices', [ $this, 'rejected_notice' ] );
		}
	}

	/**
	 * To render pending approval notice.
	 */
	public function pending_notice(): void {
		$notice_message = esc_html__( 'Your profile update is pending approval. You will not be able to edit your profile until it is approved or rejected by the network administrator.', 'oneaccess' );
		?>
		<div class="wrap">
			<div class="notice notice-warning oneaccess-warning-notice">
				<p><?php echo esc_html( $notice_message ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * To render rejected approval notice.
	 */
	public function rejected_notice(): void {
		$notice_message    = esc_html__( 'Your profile update has been rejected. Please contact the network administrator for more information.', 'oneaccess' );
		$rejection_comment = ! empty( self::$rejection_comment ) ? esc_html( self::$rejection_comment ) : '';
		?>
		<div class="wrap">
			<div class="notice notice-error oneaccess-rejection-notice">
				<div class="notice-header">
					<span class="dashicons dashicons-warning"></span>
					<p class="notice-message"><?php echo esc_html( $notice_message ); ?></p>
				</div>
				<?php if ( ! empty( $rejection_comment ) ) : ?>
					<div class="rejection-comment">
						<p><strong><?php esc_html_e( 'Rejection Comment:', 'oneaccess' ); ?></strong> <?php echo esc_html( $rejection_comment ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
