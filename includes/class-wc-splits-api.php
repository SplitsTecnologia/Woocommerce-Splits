<?php

/**
 * Splits Tecnologia API
 *
 * @package WooCommerce_splits/API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_splits_API class.
 */
class WC_splits_API {

	/**
	 * API URL.
	 */
	const API_URL = 'https://us-central1-splits-app-194513.cloudfunctions.net/api/';

	/**
	 * Gateway class.
	 *
	 * @var WC_splits_Gateway
	 */
	protected $gateway;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://us-central1-splits-app-194513.cloudfunctions.net/api/';

	/**
	 * JS Library URL.
	 *
	 * @var string
	 */
	protected $js_url = 'https://splits.com.br/scripts/splits.js';

	/**
	 * Checkout JS Library URL.
	 *
	 * @var string
	 */
	protected $checkout_js_url = 'https://splits.com.br/scripts/checkout.js';

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}

	/**
	 * Get JS Library URL.
	 *
	 * @return string
	 */
	public function get_js_url() {
		return $this->js_url;
	}

	/**
	 * Get Checkout JS Library URL.
	 *
	 * @return string
	 */
	public function get_checkout_js_url() {
		return $this->checkout_js_url;
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' === get_woocommerce_currency();
	}

	/**
	 * Only numbers.
	 *
	 * @param  string|int $string String to convert.
	 *
	 * @return string|int
	 */
	protected function only_numbers( $string ) {
		return preg_replace( '([^0-9])', '', $string );
	}

	/**
	 * Get the smallest installment amount.
	 *
	 * @return int
	 */
	public function get_smallest_installment() {
		return ( 5 > $this->gateway->smallest_installment ) ? 500 : wc_format_decimal( $this->gateway->smallest_installment ) * 100;
	}

	/**
	 * Get the interest rate.
	 *
	 * @return float
	 */
	public function get_interest_rate() {
		return wc_format_decimal( $this->gateway->interest_rate );
	}

	/**
	 * Do requests in the Splits Tecnologia API.
	 *
	 * @param  string $endpoint API Endpoint.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $endpoint, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'  => $method,
			'timeout' => 60,
		);

		if ( ! empty( $data ) ) {
			$params['body'] = $data;
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		}
		return wp_safe_remote_post( $this->get_api_url() . $endpoint, $params );
	}

	/**
	 * Get the installments.
	 *
	 * @param float $amount Order amount.
	 *
	 * @return array
	 */
	public function get_installments( $amount ) {
		// Set the installment data.
		$data = array(
			'encryption_key'    => $this->gateway->api_key,
			'amount'            => $amount * 100,
			// 'interest_rate'     => $this->get_interest_rate(),
			'max_installments'  => $this->gateway->max_installment,
			'free_installments' => $this->gateway->free_installments,
		);

	
		$query = array(
			'query' => 'query installments_values($gateway_token: ID!, $amount: Int!, $max_installments: Int, $free_installments: Int!, $smallest_installment: Int!) {installments_values(gateway_token: $gateway_token, amount: $amount, max_installments: $max_installments, free_installments: $free_installments, smallest_installment: $smallest_installment) { parcel value total }}',
			'variables' => array (
				'gateway_token' => $this->gateway->api_key,
				'amount' => $amount * 100,
				'max_installments' => $this->gateway->max_installment,
				'free_installments' => $this->gateway->free_installments,
				'smallest_installment' => ($this->gateway->max_installment == 1 ? 0 : $this->gateway->smallest_installment * 100)
			)
		);
	
	//	echo '<pre>';
	//	print_r($this->gateway->max_installment);
	//	print_r($query);
	//	echo '</pre>';
		
		$headers = array( 
			'x-gateway-token' => $this->gateway->api_key
		);
		

		$response = $this->do_request( 'gateway', 'POST', $query, $headers);
		// echo '<pre>';
		// print_r($response);
		// echo '</pre>';
		return $response;
	}

	/**
	 * Get max installment.
	 *
	 * @param float $amount Order amount.
	 *
	 * @return int
	 */
	public function get_max_installment( $amount ) {
		$installments         = $this->get_installments( $amount );
		$smallest_installment = $this->get_smallest_installment();
		$max                  = 1;

		foreach ( $installments as $number => $installment ) {
			if ( $smallest_installment > $installment['installment_amount'] ) {
				break;
			}

			$max = $number;
		}

		return $max;
	}

	/**
	 * Generate the transaction data.
	 *
	 * @param  WC_Order $order  Order data.
	 * @param  array    $posted Form posted data.
	 *
	 * @return array            Transaction data.
	 */
	public function generate_transaction_data( $order, $posted ) {

		$query = array(
			'query' => 'mutation create_sale_open($valor:String!, $token_cartao: String, $plano_cobranca: PlanoCobrancaInput!) { create_sale_open( valor:$valor, token_cartao:$token_cartao, plano_cobranca:$plano_cobranca ) { status message } }',
			'variables' => array (
				'valor' => $order->total * 100,
				'token_cartao' => $posted['splits_card_hash'],
				'plano_cobranca' => array (
					'tipo' => 'MENSAL',
					'quantidade' => $posted['splits_installments']
				)
			)
		);
		
		// print_r($query);
		
		$headers = array( 
			'x-gateway-token' => $this->gateway->api_key
		);
		$response = $this->do_request( 'gateway', 'POST', $query, $headers);
		$res = json_decode($response['body'], true);
		
		
		// print_r($response);
		
		$data = array (
			'transaction_id' => $order->id,
			'payment_method'  => $order->payment_method,
			'installments'    => $posted['splits_installments'],
			'response_request' => $res['data']['create_sale_open']
		);
		
		return apply_filters( 'wc_splits_transaction_data', $data , $order );
		// return apply_filters( 'wc_splits_transaction_data', $data , $order );
	}

	/**
	 * Get customer data from checkout pay page.
	 *
	 * @return array
	 */
	public function get_customer_data_from_checkout_pay_page() {
		global $wp;

		$order    = wc_get_order( (int) $wp->query_vars['order-pay'] );
		$data     = $this->generate_transaction_data( $order, array() );
		$customer = array();

		if ( empty( $data['customer'] ) ) {
			return $customer;
		}

		$_customer = $data['customer'];
		$customer['customerName']  = $_customer['name'];
		$customer['customerEmail'] = $_customer['email'];

		if ( isset( $_customer['document_number'] ) ) {
			$customer['customerDocumentNumber'] = $_customer['document_number'];
		}

		if ( isset( $_customer['address'] ) ) {
			$customer['customerAddressStreet']        = $_customer['address']['street'];
			$customer['customerAddressComplementary'] = $_customer['address']['complementary'];
			$customer['customerAddressZipcode']       = $_customer['address']['zipcode'];

			if ( isset( $_customer['address']['street_number'] ) ) {
				$customer['customerAddressStreetNumber'] = $_customer['address']['street_number'];
			}
			if ( isset( $_customer['address']['neighborhood'] ) ) {
				$customer['customerAddressNeighborhood'] = $_customer['address']['neighborhood'];
			}
		}

		if ( isset( $_customer['phone'] ) ) {
			$customer['customerPhoneDdd']    = $_customer['phone']['ddd'];
			$customer['customerPhoneNumber'] = $_customer['phone']['number'];
		}

		return $customer;
	}

	/**
	 * Get transaction data.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  string   $token Checkout token.
	 *
	 * @return array           Response data.
	 */
	public function get_transaction_data( $order, $token ) {
		if ( 'yes' === $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Getting transaction data for order ' . $order->get_order_number() . '...' );
		}

		$response = $this->do_request( 'transactions/' . $token, 'GET', array( 'api_key' => $this->gateway->api_key ) );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in getting transaction data: ' . $response->get_error_message() );
			}

			return array();
		} else {
			$data = json_decode( $response['body'], true );

			if ( isset( $data['errors'] ) ) {
				if ( 'yes' === $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Failed to get transaction data: ' . print_r( $response, true ) );
				}

				return $data;
			}

			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Transaction data obtained successfully!' );
			}

			return $data;
		}
	}

	/**
	 * Generate checkout data.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  string   $token Checkout token.
	 *
	 * @return array           Checkout data.
	 */
	public function generate_checkout_data( $order, $token ) {
		$transaction  = $this->get_transaction_data( $order, $token );
		$installments = $this->get_installments( $order->get_total() );

		// Valid transaction.
		if ( ! isset( $transaction['amount'] ) ) {
			return array( 'error' => __( 'Invalid transaction data.', 'woocommerce-splits' ) );
		}

		// Test if using more installments that allowed.
		if ( $this->gateway->max_installment < $transaction['installments'] || empty( $installments[ $transaction['installments'] ] ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Payment made with more installments than allowed for order ' . $order->get_order_number() );
			}

			return array( 'error' => __( 'Payment made with more installments than allowed.', 'woocommerce-splits' ) );
		}

		$installment = $installments[ $transaction['installments'] ];

		// Test smallest installment amount.
		if ( 1 !== intval( $transaction['installments'] ) && $this->get_smallest_installment() > $installment['installment_amount'] ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Payment divided into a lower amount than permitted for order ' . $order->get_order_number() );
			}

			return array( 'error' => __( 'Payment divided into a lower amount than permitted.', 'woocommerce-splits' ) );
		}

		// Check the transaction amount.
		if ( intval( $transaction['amount'] ) !== intval( $installment['amount'] ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Wrong payment amount total for order ' . $order->get_order_number() );
			}

			return array( 'error' => __( 'Wrong payment amount total.', 'woocommerce-splits' ) );
		}

		$data = array(
			'api_key'  => $this->gateway->api_key,
			'amount'   => $transaction['amount'],
			'metadata' => array(
				'order_number' => $order->get_order_number(),
			),
		);

		return apply_filters( 'wc_splits_checkout_data', $data );
	}

	/**
	 * Do the transaction.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  array    $args  Transaction args.
	 * @param  string   $token Checkout token.
	 *
	 * @return array           Response data.
	 */
	public function do_transaction( $order, $args, $token = '' ) {
		if ( 'yes' === $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Doing a transaction for order ' . $order->get_order_number() . '...' );
		}

		$endpoint = 'transactions';
		if ( ! empty( $token ) ) {
			$endpoint .= '/' . $token . '/capture';
		}

		$response = $this->do_request( $endpoint, 'POST', $args );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the transaction: ' . $response->get_error_message() );
			}

			return array();
		} else {
			$data = json_decode( $response['body'], true );

			if ( isset( $data['errors'] ) ) {
				if ( 'yes' === $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Failed to make the transaction: ' . print_r( $response, true ) );
				}

				return $data;
			}

			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Transaction completed successfully! The transaction response is: ' . print_r( $data, true ) );
			}

			return $data;
		}
	}

	/**
	 * Do the transaction.
	 *
	 * @param  WC_Order $order Order data.
	 * @param  string   $token Checkout token.
	 *
	 * @return array           Response data.
	 */
	public function cancel_transaction( $order, $token ) {
		if ( 'yes' === $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Cancelling transaction for order ' . $order->get_order_number() . '...' );
		}

		$endpoint = 'transactions';
		if ( ! empty( $token ) ) {
			$endpoint .= '/' . $token . '/refund';
		}

		$response = $this->do_request( $endpoint, 'POST', array( 'api_key' => $this->gateway->api_key ) );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in doing the transaction cancellation: ' . $response->get_error_message() );
			}

			return array();
		} else {
			$data = json_decode( $response['body'], true );

			if ( isset( $data['errors'] ) ) {
				if ( 'yes' === $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Failed to cancel the transaction: ' . print_r( $response, true ) );
				}

				return $data;
			}

			if ( 'yes' === $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Transaction canceled successfully! The response is: ' . print_r( $data, true ) );
			}

			return $data;
		}
	}

	/**
	 * Get card brand name.
	 *
	 * @param string $brand Card brand.
	 * @return string
	 */
	protected function get_card_brand_name( $brand ) {
		$names = array(
			'visa'       => __( 'Visa', 'woocommerce-splits' ),
			'mastercard' => __( 'MasterCard', 'woocommerce-splits' ),
			'amex'       => __( 'American Express', 'woocommerce-splits' ),
			'aura'       => __( 'Aura', 'woocommerce-splits' ),
			'jcb'        => __( 'JCB', 'woocommerce-splits' ),
			'diners'     => __( 'Diners', 'woocommerce-splits' ),
			'elo'        => __( 'Elo', 'woocommerce-splits' ),
			'hipercard'  => __( 'Hipercard', 'woocommerce-splits' ),
			'discover'   => __( 'Discover', 'woocommerce-splits' ),
		);

		return isset( $names[ $brand ] ) ? $names[ $brand ] : $brand;
	}

	/**
	 * Save order meta fields.
	 * Save fields as meta data to display on order's admin screen.
	 *
	 * @param int   $id Order ID.
	 * @param array $data Order data.
	 */
	protected function save_order_meta_fields( $id, $data ) {
		if ( 'boleto' === $data['payment_method'] ) {
			if ( ! empty( $data['boleto_url'] ) ) {
				update_post_meta( $id, __( 'Banking Ticket URL', 'woocommerce-splits' ), sanitize_text_field( $data['boleto_url'] ) );
			}
		} else {
			if ( ! empty( $data['card_brand'] ) ) {
				update_post_meta( $id, __( 'Credit Card', 'woocommerce-splits' ), $this->get_card_brand_name( sanitize_text_field( $data['card_brand'] ) ) );
			}
			if ( ! empty( $data['installments'] ) ) {
				update_post_meta( $id, __( 'Installments', 'woocommerce-splits' ), sanitize_text_field( $data['installments'] ) );
			}
			if ( ! empty( $data['amount'] ) ) {
				update_post_meta( $id, __( 'Total paid', 'woocommerce-splits' ), number_format( intval( $data['amount'] ) / 100, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) );
			}
			if ( ! empty( $data['antifraud_score'] ) ) {
				update_post_meta( $id, __( 'Anti Fraud Score', 'woocommerce-splits' ), sanitize_text_field( $data['antifraud_score'] ) );
			}
		}
	}

	/**
	 * Process regular payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 * 
	 * {"query":"mutation create_sale($valor: String!, $descricao: String! $cartao: CartaoInput!){\n\tcreate_sale( valor: $valor, descricao: $descricao, cartao: $cartao){\n\t\tstatus\n\t\tmensagem\n}\n}","variables":{"valor":"","descricao":"","cartao":""},"operationName":"create_sale"}
	 */

	public function process_regular_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$transaction = $this->generate_transaction_data( $order, $_POST );

		if ( $transaction['response_request']['status'] != 200 ) {
			wc_add_notice( $transaction['response_request']['message'], 'error' );
			return array(
				'result' => 'fail',
			);
		} else {
			// Save transaction data.
			update_post_meta( $order->id, '_wc_pagarme_transaction_id', intval( $transaction['transaction_id'] ) );
			$payment_data = array_map(
				'sanitize_text_field',
				array(
					'payment_method'  => $transaction['payment_method'],
					'installments'    => $transaction['installments']
				)
			);
			update_post_meta( $order->id, '_wc_pagarme_transaction_data', $payment_data );
			update_post_meta( $order->id, '_transaction_id', intval( $transaction['id'] ) );
			$this->save_order_meta_fields( $order->id, $transaction );

			// Change the order status.
			$this->process_order_status( $order, 'paid' );

			// Empty the cart.
			WC()->cart->empty_cart();

			// Redirect to thanks page.
			return array(
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $order ),
			);
		}
	}
	/**
	 * Check if Splits Tecnologia response is validity.
	 *
	 * @param  array $ipn_response IPN response data.
	 *
	 * @return bool
	 */
	public function check_fingerprint( $ipn_response ) {
		if ( isset( $ipn_response['id'] ) && isset( $ipn_response['current_status'] ) && isset( $ipn_response['fingerprint'] ) ) {
			$fingerprint = sha1( $ipn_response['id'] . '#' . $this->gateway->api_key );

			if ( $fingerprint === $ipn_response['fingerprint'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	protected function send_email( $subject, $title, $message ) {
		$mailer = WC()->mailer();
		$mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
	}

	/**
	 * IPN handler.
	 */
	public function ipn_handler() {
		@ob_clean();

		$ipn_response = ! empty( $_POST ) ? $_POST : false;

		if ( $ipn_response && $this->check_fingerprint( $ipn_response ) ) {
			header( 'HTTP/1.1 200 OK' );

			$this->process_successful_ipn( $ipn_response );

			// Deprecated action since 2.0.0.
			do_action( 'wc_splits_valid_ipn_request', $ipn_response );

			exit;
		} else {
			wp_die( esc_html__( 'Splits Tecnologia Request Failure', 'woocommerce-splits' ), '', array( 'response' => 401 ) );
		}
	}

	/**
	 * Process successeful IPN requests.
	 *
	 * @param array $posted Posted data.
	 */
	public function process_successful_ipn( $posted ) {
		global $wpdb;

		$posted   = wp_unslash( $posted );
		$order_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wc_splits_transaction_id' AND meta_value = %d", $posted['id'] ) ) );
		$order    = wc_get_order( $order_id );
		$status   = sanitize_text_field( $posted['current_status'] );

		if ( $order && $order->id === $order_id ) {
			$this->process_order_status( $order, $status );
		}

		// Async transactions will only send the boleto_url on IPN.
		if ( ! empty( $posted['transaction']['boleto_url'] ) && 'splits-banking-ticket' === $order->payment_method ) {
			$post_data = get_post_meta( $order->id, '_wc_splits_transaction_data', true );
			$post_data['boleto_url'] = sanitize_text_field( $posted['transaction']['boleto_url'] );
			update_post_meta( $order->id, '_wc_splits_transaction_data', $post_data );
		}
	}

	/**
	 * Process the order status.
	 *
	 * @param WC_Order $order  Order data.
	 * @param string   $status Transaction status.
	 */
	public function process_order_status( $order, $status ) {
		if ( 'yes' === $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Payment status for order ' . $order->get_order_number() . ' is now: ' . $status );
		}

		switch ( $status ) {
			case 'authorized' :
				if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
					$order->update_status( 'on-hold', __( 'Splits Tecnologia: The transaction was authorized.', 'woocommerce-splits' ) );
				}

				break;
			case 'pending_review':
				$transaction_id  = get_post_meta( $order->id, '_wc_splits_transaction_id', true );
				$transaction_url = '<a href="https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '">https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '</a>';

				/* translators: %s transaction details url */
				$order->update_status( 'on-hold', __( 'Splits Tecnologia: You should manually analyze this transaction to continue payment flow, access %s to do it!', 'woocommerce-splits'  ), $transaction_url  );

				break;
			case 'processing' :
				$order->update_status( 'on-hold', __( 'Splits Tecnologia: The transaction is being processed.', 'woocommerce-splits' ) );

				break;
			case 'paid' :
				if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
					$order->add_order_note( __( 'Splits Tecnologia: Transaction paid.', 'woocommerce-splits' ) );
				}

				// Changing the order for processing and reduces the stock.
				$order->payment_complete();

				break;
			case 'waiting_payment' :
				$order->update_status( 'on-hold', __( 'Splits Tecnologia: The banking ticket was issued but not paid yet.', 'woocommerce-splits' ) );

				break;
			case 'refused' :
				$order->update_status( 'failed', __( 'Splits Tecnologia: The transaction was rejected by the card company or by fraud.', 'woocommerce-splits' ) );

				$transaction_id  = get_post_meta( $order->id, '_wc_splits_transaction_id', true );
				$transaction_url = '<a href="https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '">https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '</a>';

				$this->send_email(
					sprintf( esc_html__( 'The transaction for order %s was rejected by the card company or by fraud', 'woocommerce-splits' ), $order->get_order_number() ),
					esc_html__( 'Transaction failed', 'woocommerce-splits' ),
					sprintf( esc_html__( 'Order %1$s has been marked as failed, because the transaction was rejected by the card company or by fraud, for more details, see %2$s.', 'woocommerce-splits' ), $order->get_order_number(), $transaction_url )
				);

				break;
			case 'refunded' :
				$order->update_status( 'refunded', __( 'Splits Tecnologia: The transaction was refunded/canceled.', 'woocommerce-splits' ) );

				$transaction_id  = get_post_meta( $order->id, '_wc_splits_transaction_id', true );
				$transaction_url = '<a href="https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '">https://portal.splits.com.br/#/transactions/' . intval( $transaction_id ) . '</a>';

				$this->send_email(
					sprintf( esc_html__( 'The transaction for order %s refunded', 'woocommerce-splits' ), $order->get_order_number() ),
					esc_html__( 'Transaction refunded', 'woocommerce-splits' ),
					sprintf( esc_html__( 'Order %1$s has been marked as refunded by Splits Tecnologia, for more details, see %2$s.', 'woocommerce-splits' ), $order->get_order_number(), $transaction_url )
				);

				break;

			default :
				break;
		}
	}
}
