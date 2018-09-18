<?php
/**
 * Credit Card - Payment instructions.
 *
 * @author  Splits Tecnologia
 * @package WooCommerce_splits/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="woocommerce-message">
	<span><?php printf( wp_kses( __( 'Pagamento realizado com sucesso usando %1$s cartão de crédito em %2$s.', 'woocommerce-splits' ), array( 'strong' => array() ) ), '<strong>' . esc_html( $card_brand ) . '</strong>', '<strong>' . intval( $installments ) . 'x</strong>' ); ?></span>
</div>
