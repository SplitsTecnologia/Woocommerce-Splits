<?php
/**
 * Splits Tecnologia Credit Card gateway
 *
 * @package WooCommerce_splits/Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_splits_Credit_Card_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_splits_Credit_Card_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                   = 'splits-credit-card';
		$this->icon                 = apply_filters( 'wc_splits_credit_card_icon', false );
		$this->has_fields           = true;
		$this->method_title         = __( 'Splits Pagamento - Cartão de crédito', 'woocommerce-splits' );
		$this->method_description   = __( 'Aceite pagamentos com cartão de crédito usando Splits Pagamento.', 'woocommerce-splits' );
		$this->view_transaction_url = 'https://admin.splits.com.br/#/transactions/%s';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->api_key              = $this->get_option( 'api_key' );
		$this->encryption_key       = $this->get_option( 'api_key' );
		$this->checkout             = 'no';
		$this->max_installment      = $this->get_option( 'max_installment' );
		$this->smallest_installment = $this->get_option( 'smallest_installment' );
		$this->interest_rate        = $this->get_option( 'interest_rate', '0' );
		$this->free_installments    = $this->get_option( 'free_installments', '1' );
		$this->debug                = $this->get_option( 'debug' );

		// Suporte Subscription
		$this->supports = array( 
			'products', 
			'subscriptions',
			'subscription_cancellation', 
			'subscription_suspension', 
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
	   );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			$this->log = new WC_Logger();
		}

		// Set the API.
		$this->api = new WC_splits_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_api_wc_splits_credit_card_gateway', array( $this, 'ipn_handler' ) );

		if($this->get_option( 'api_key' )) {
			echo '
				<script>
					var token = "'.$this->get_option( "api_key" ) .'";
					window.localStorage.setItem("TokenGatewaySplits", token);
				</script>
			';
		}
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		include dirname( __FILE__ ) . '/admin/views/html-admin-page.php';
	}

	/**
	 * Check if the gateway is available to take payments.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && ! empty( $this->api_key ) && $this->api->using_supported_currency();
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Habilitar/Desabilitar', 'woocommerce-splits' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar Splits Pagamento Cartão de crédito', 'woocommerce-splits' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-splits' ),
				'type'        => 'text',
				'description' => __( 'Isso controla o título que o usuário vê durante o checkout.', 'woocommerce-splits' ),
				'desc_tip'    => true,
				'default'     => __( 'Cartão de Crédito', 'woocommerce-splits' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-splits' ),
				'type'        => 'textarea',
				'description' => __( 'Isso controla a descrição que o usuário vê durante o checkout.', 'woocommerce-splits' ),
				'desc_tip'    => true,
				'default'     => __( 'Pagar com cartão de crédito', 'woocommerce-splits' ),
			),
			'integration' => array(
				'title'       => __( 'Configurações de integração', 'woocommerce-splits' ),
				'type'        => 'title',
				'description' => '',
			),
			'api_key' => array(
				'title'             => __( 'Splits Pagamento Secreet Key', 'woocommerce-splits' ),
				'type'              => 'text',
				'description'       => sprintf( __( 'Por favor insira o seu Secreet Key. Isso é necessário para processar o pagamento e as notificações. É possível obter sua chave de API em %s.', 'woocommerce-splits' ), '<a href="https://admin.splits.com.br">' . __( 'Splits Dashboard > Configurações', 'woocommerce-splits' ) . '</a>' ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			// 'encryption_key' => array(
			// 	'title'             => __( 'Splits Pagamento Encryption Key', 'woocommerce-splits' ),
			// 	'type'              => 'text',
			// 	'description'       => sprintf( __( 'Por favor insira o seu Splits Pagamento Encryption key. Isso é necessário para processar o pagamento. É possível obter sua chave de criptografia %s.', 'woocommerce-splits' ), '<a href="https://admin.splits.com.br">' . __( 'Splits Dashboard > Configurações', 'woocommerce-splits' ) . '</a>' ),
			// 	'default'           => '',
			// 	'custom_attributes' => array(
			// 		'required' => 'required',
			// 	),
			// ),
			// 'checkout' => array(
			// 	'title'       => __( 'Checkout Splits Tecnologia', 'woocommerce-splits' ),
			// 	'type'        => 'checkbox',
			// 	'label'       => __( 'Habilitar checkout Splits Pagamento', 'woocommerce-splits' ),
			// 	'default'     => 'no',
			// 	'desc_tip'    => true,
			// 	'description' => __( "Quando habilitado abre uma janela modal da Splits Pagamento para receber as informações do Cartão de Crédito do cliente.", 'woocommerce-splits' ),
			// ),
			'installments' => array(
				'title'       => __( 'Parcelas', 'woocommerce-splits' ),
				'type'        => 'title',
				'description' => '',
			),
			'max_installment' => array(
				'title'       => __( 'Números de parcelas', 'woocommerce-splits' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => '12',
				'description' => __( 'Número máximo de parcelas possíveis com pagamentos por Cartão de Crédito.', 'woocommerce-splits' ),
				'desc_tip'    => true,
				'options'     => array(
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12',
				),
			),
			'smallest_installment' => array(
				'title'       => __( 'Menor Parcela', 'woocommerce-splits' ),
				'type'        => 'text',
				'description' => __( 'PleaPor favor insira com o valor da menor parcela, Nota: não pode ser inferior a 5.', 'woocommerce-splits' ),
				'desc_tip'    => true,
				'default'     => '5',
			),
			// 'interest_rate' => array(
			// 	'title'       => __( 'Taxa de juro', 'woocommerce-splits' ),
			// 	'type'        => 'text',
			// 	'description' => __( 'Por favor, insira com o valor da taxa de juros. Nota: use 0 para não cobrar juros.', 'woocommerce-splits' ),
			// 	'desc_tip'    => true,
			// 	'default'     => '0',
			// ),
			'free_installments' => array(
				'title'       => __( 'Quant. de parcelas sem juros', 'woocommerce-splits' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => '1',
				'description' => __( 'Número de parcelas com juros livres.', 'woocommerce-splits' ),
				'desc_tip'    => true,
				'options'     => array(
					'0'  => _x( 'None', 'sem parcelas gratuitas', 'woocommerce-splits' ),
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12',
				),
			),
			'testing' => array(
				'title'       => __( 'Teste de Gateway', 'woocommerce-splits' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-splits' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar logging', 'woocommerce-splits' ),
				'default'     => 'yes',
				'description' => sprintf( __( 'Log Splits Pagamento eventos, como solicitações de API. Você pode verificar o log in %s', 'woocommerce-splits' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-splits' ) . '</a>' ),
			),
		);
	}

	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		if ( is_checkout() ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			if ( 'yes' === $this->checkout ) {
				$customer = array();

				wp_enqueue_script( 'splits-checkout-library', $this->api->get_checkout_js_url(), array( 'jquery' ), null );
				wp_enqueue_script( 'splits-checkout', plugins_url( 'assets/js/checkout' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'jquery-blockui', 'splits-checkout-library' ), WC_splits::VERSION, true );

				if ( is_checkout_pay_page() ) {
					$customer = $this->api->get_customer_data_from_checkout_pay_page();
				}

				wp_localize_script(
					'splits-checkout',
					'wcsplitsParams',
					array(
						// 'encryptionKey'    => $this->encryption_key,
						// 'interestRate'     => $this->api->get_interest_rate(),
						'freeInstallments' => $this->free_installments,
						'postbackUrl'      => WC()->api_request_url( get_class( $this ) ),
						'customerFields'   => $customer,
						'checkoutPayPage'  => ! empty( $customer ),
						'uiColor'          => apply_filters( 'wc_splits_checkout_ui_color', '#1a6ee1' ),
					)
				);
			} else {
				wp_enqueue_script( 'wc-credit-card-form' );
				wp_enqueue_script( 'splits-library', $this->api->get_js_url(), array( 'jquery' ), null );
				wp_enqueue_script( 'splits-credit-card', plugins_url( 'assets/js/credit-card' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'jquery-blockui', 'splits-library' ), WC_splits::VERSION, true );

				wp_localize_script(
					'splits-credit-card',
					'wcsplitsParams',
					array(
						'encryptionKey' => $this->api_key,
					)
				);
			}
		}
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		$cart_total = $this->get_order_total();

		if ( 'no' === $this->checkout ) {
			$installments = $this->api->get_installments( $cart_total );

			wc_get_template(
				'credit-card/payment-form.php',
				array(
					'cart_total'           => $cart_total,
					'max_installment'      => $this->max_installment,
					'smallest_installment' => $this->api->get_smallest_installment(),
					'installments'         => $installments,
				),
				'woocommerce/splits/',
				WC_splits::get_templates_path()
			);
		} else {
			echo '<div id="splits-checkout-params" ';
			echo 'data-total="' . esc_attr( $cart_total * 100 ) . '" ';
			echo 'data-max_installment="' . esc_attr( apply_filters( 'wc_splits_checkout_credit_card_max_installments', $this->api->get_max_installment( $cart_total ) ) ) . '"';
			echo '></div>';
		}
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 */
	public function process_payment( $order_id ) {
		return $this->api->process_regular_payment( $order_id );
	}

	/**
	 * Thank You page message.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		$data  = get_post_meta( $order_id, '_wc_splits_transaction_data', true );

		if ( isset( $data['installments'] ) && in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) ) {
			wc_get_template(
				'credit-card/payment-instructions.php',
				array(
					'card_brand'   => $data['card_brand'],
					'installments' => $data['installments'],
				),
				'woocommerce/splits/',
				WC_splits::get_templates_path()
			);
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 * @param  bool   $plain_text    Plain text or HTML.
	 *
	 * @return string                Payment instructions.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! in_array( $order->get_status(), array( 'processing', 'on-hold' ), true ) || $this->id !== $order->payment_method ) {
			return;
		}

		$data = get_post_meta( $order->id, '_wc_splits_transaction_data', true );

		if ( isset( $data['installments'] ) ) {
			$email_type = $plain_text ? 'plain' : 'html';

			wc_get_template(
				'credit-card/emails/' . $email_type . '-instructions.php',
				array(
					'card_brand'   => $data['card_brand'],
					'installments' => $data['installments'],
				),
				'woocommerce/splits/',
				WC_splits::get_templates_path()
			);
		}
	}

	/**
	 * IPN handler.
	 */
	public function ipn_handler() {
		$this->api->ipn_handler();
	}
}
