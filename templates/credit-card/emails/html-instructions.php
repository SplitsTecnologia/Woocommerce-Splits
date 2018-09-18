<?php
/**
 * Credit Card - HTML email instructions.
 *
 * @author  Splits Tecnologia
 * @package WooCommerce_splits/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2><?php esc_html_e( 'Payment', 'woocommerce-splits' ); ?></h2>

<p class="order_details"><?php printf( wp_kses( __( 'Payment successfully made using %1$s credit card in %2$s.', 'woocommerce-splits' ), array( 'strong' => array() ) ), '<strong>' . esc_html( $card_brand ) . '</strong>', '<strong>' . intval( $installments ) . 'x</strong>' ); ?></p>
