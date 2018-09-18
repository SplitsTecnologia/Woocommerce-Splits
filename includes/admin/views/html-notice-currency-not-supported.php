<?php
/**
 * Notice: Currency not supported.
 *
 * @package WooCommerce_splits/Admin/Notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="error inline">
	<p><strong><?php esc_html_e( 'Splits Tecnologia Disabled', 'woocommerce-splits' ); ?></strong>: <?php printf( wp_kses( __( 'Currency %s is not supported. Works only with Brazilian Real.', 'woocommerce-splits' ), array( 'code' => array() ) ), '<code>' . esc_html( get_woocommerce_currency() ) . '</code>' ); ?>
	</p>
</div>

<?php
