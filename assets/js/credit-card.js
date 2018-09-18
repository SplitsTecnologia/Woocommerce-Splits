/* global wcsplitsParams, splits */
(function ($) {
	'use strict';

	$(function () {

		/**
		 * Process the credit card data when submit the checkout form.
		 */
		$('body').on('click', '#place_order', function () {
			if (!$('#payment_method_splits-credit-card').is(':checked')) {
				return true;
			}


			Splits.encryption_key = wcsplitsParams.encryptionKey;

			var form = $('form.checkout, form#order_review'),
				creditCard = new Splits.creditCard(),
				creditCardForm = $('#splits-credit-cart-form', form),
				errors = null,
				errorHtml = '';

			// Lock the checkout form.
			form.addClass('processing');

			// Set the Credit card data.
			creditCard.cardHolderName = $('#splits-card-holder-name', form).val();
			creditCard.cardExpirationMonth = $('#splits-card-expiry', form).val().replace(/[^\d]/g, '').substr(0, 2);
			creditCard.cardExpirationYear = $('#splits-card-expiry', form).val().replace(/[^\d]/g, '').substr(2);
			creditCard.cardNumber = $('#splits-card-number', form).val().replace(/[^\d]/g, '');
			creditCard.cardCVV = $('#splits-card-cvc', form).val();

			// Get the errors.
			errors = creditCard.fieldErrors();

			// Display the errors in credit card form.
			if (!$.isEmptyObject(errors)) {
				form.removeClass('processing');
				$('.woocommerce-error', creditCardForm).remove();

				errorHtml += '<ul>';
				$.each(errors, function (key, value) {
					errorHtml += '<li>' + value + '</li>';
				});
				errorHtml += '</ul>';

				creditCardForm.prepend('<div class="woocommerce-error">' + errorHtml + '</div>');
			} else {
				form.removeClass('processing');
				$('.woocommerce-error', creditCardForm).remove();

				// Generate the hash.
				creditCard.generateHash(function (cardHash) {
					console.log(cardHash);
					// Remove any old hash input.
					$('input[name=splits_card_hash]', form).remove();

					// Add the hash input.
					form.append($('<input name="splits_card_hash" type="hidden" />').val(cardHash));

					// Submit the form.
					form.submit();
				});
			}

			return false;
		});
	});

}(jQuery));
