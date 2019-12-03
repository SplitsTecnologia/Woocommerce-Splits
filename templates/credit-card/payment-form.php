<?php

/**
 * Credit Card - Checkout form.
 *
 * @author  Splits Tecnologia
 * @package WooCommerce_splits/Templates
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
// echo '<pre>';
// print_r($installments);
// echo '</pre>';
// die();
$resp = json_decode($installments['body'], true)['data']['installments_values'];
?>

<fieldset id="splits-credit-cart-form">
	<p class="form-row form-row-first">
		<label for="splits-card-holder-name"><?php esc_html_e('Titular do cartão', 'woocommerce-splits'); ?><span class="required">*</span></label>
		<input id="splits-card-holder-name" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="splits-card-number"><?php esc_html_e('Número do cartão', 'woocommerce-splits'); ?> <span class="required">*</span></label>
		<input id="splits-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<p class="form-row form-row-first">
		<label for="splits-card-expiry"><?php esc_html_e('Expiração (MM/YY)', 'woocommerce-splits'); ?> <span class="required">*</span></label>
		<input id="splits-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="<?php esc_html_e('MM / YY', 'woocommerce-splits'); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<p class="form-row form-row-last">
		<label for="splits-card-cvc"><?php esc_html_e('CVV', 'woocommerce-splits'); ?> <span class="required">*</span></label>
		<input id="splits-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="<?php esc_html_e('CVC', 'woocommerce-splits'); ?>" style="font-size: 1.5em; padding: 8px;" />
	</p>
	<div class="clear"></div>
	<?php if (apply_filters('wc_splits_allow_credit_card_installments', 1 < $max_installment)) : ?>
		<p class="form-row form-row-wide">
			<label for="splits-card-installments"><?php esc_html_e('Parcelas', 'woocommerce-splits'); ?> <span class="required">*</span></label>
			<select name="splits_installments" id="splits-installments" style="font-size: 1.5em; padding: 8px; width: 100%;">
				<?php
					foreach ($resp as $installment) :
						?>
					<option value="<?php echo absint($installment['parcel']); ?>"><?php printf(esc_html__('%1$dx de R$ %2$s / R$ %3$s', 'woocommerce-splits'), absint($installment['parcel']), esc_html(money_format('%i', $installment['value'] / 100)), esc_html(money_format('%i', $installment['total'] / 100))); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php endif; ?>
	<?php if (class_exists('WC_Subscriptions_Manager')) : ?>
		<p class="checkbox-container">
			<label for="splits-subscription"><input type="checkbox" checked name="splits-subscription" id="splits-subscription" /> Fazer incrição</label>
		</p>
		<small>* Todo mês será cobrado o valor de<?= money_format('R$', $resp[0]['total'] / 100) ?></small>
	<?php endif; ?>
</fieldset>