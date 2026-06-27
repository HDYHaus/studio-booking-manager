<?php
/**
 * Commerce admin screen.
 *
 * @package StudioBookingManager
 */

namespace StudioBookingManager\Commerce\WooCommerce;

use StudioBookingManager\Admin\PageHeader;

defined( 'ABSPATH' ) || exit;

/**
 * Displays commerce integration status.
 */
final class CommerceAdmin {
	/**
	 * Render screen.
	 */
	public function render(): void {
		?>
		<div class="wrap sbm-admin-page">
			<?php PageHeader::render( __( 'Commerce', 'studio-booking-manager' ) ); ?>
			<div class="sbm-card">
				<h2><?php echo esc_html__( 'WooCommerce Integration', 'studio-booking-manager' ); ?></h2>
				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
					<p><?php echo esc_html__( 'WooCommerce is active. Configure Studio Booking access rules inside WooCommerce products and variations.', 'studio-booking-manager' ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__( 'WooCommerce is not active. Studio Booking Manager continues to work without commerce features.', 'studio-booking-manager' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
