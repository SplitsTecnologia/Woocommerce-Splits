<?php
/**
 * Plugin Name: WooCommerce Splits Tecnologia
 * Plugin URI: http://splits.com.br
 * Description: Gateway de pagamento Splits Tecnologia para WooCommerce.
 * Author: Splits Tecnologia, Vinícius Pereira
 * Author URI: https://Splits Tecnologia/
 * Version: 2.0.12
 * License: GPLv2 or later
 * Text Domain: woocommerce-splits
 * Domain Path: /languages/
 *
 * @package WooCommerce_splits
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_splits' ) ) :

	/**
	 * WooCommerce WC_splits main class.
	 */
	class WC_splits {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '2.0.12';

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin public actions.
		 */
		private function __construct() {
			// Load plugin text domain.
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$this->upgrade();
				$this->includes();

				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Includes.
		 */
		private function includes() {
			include_once dirname( __FILE__ ) . '/includes/class-wc-splits-api.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-splits-my-account.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-splits-banking-ticket-gateway.php';
			include_once dirname( __FILE__ ) . '/includes/class-wc-splits-credit-card-gateway.php';
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'woocommerce-splits', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Get templates path.
		 *
		 * @return string
		 */
		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @param  array $methods WooCommerce payment methods.
		 *
		 * @return array
		 */
		public function add_gateway( $methods ) {
			$methods[] = 'WC_splits_Banking_Ticket_Gateway';
			$methods[] = 'WC_splits_Credit_Card_Gateway';

			return $methods;
		}

		/**
		 * Action links.
		 *
		 * @param  array $links Plugin links.
		 *
		 * @return array
		 */
		public function plugin_action_links( $links ) {
			$plugin_links = array();

			$banking_ticket = 'wc_splits_banking_ticket_gateway';
			$credit_card    = 'wc_splits_credit_card_gateway';

			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $banking_ticket ) ) . '">' . __( 'Bank Slip Settings', 'woocommerce-splits' ) . '</a>';

			$plugin_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $credit_card ) ) . '">' . __( 'Credit Card Settings', 'woocommerce-splits' ) . '</a>';

			return array_merge( $plugin_links, $links );
		}

		/**
		 * WooCommerce fallback notice.
		 */
		public function woocommerce_missing_notice() {
			include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-woocommerce.php';
		}

		/**
		 * Upgrade.
		 *
		 * @since 2.0.0
		 */
		private function upgrade() {
			if ( is_admin() ) {
				if ( $old_options = get_option( 'woocommerce_splits_settings' ) ) {
					// Banking ticket options.
					$banking_ticket = array(
						'enabled'        => $old_options['enabled'],
						'title'          => 'Boleto bancário',
						'description'    => '',
						'api_key'        => $old_options['api_key'],
						'encryption_key' => $old_options['encryption_key'],
						'debug'          => $old_options['debug'],
					);

					// Credit card options.
					$credit_card = array(
						'enabled'              => $old_options['enabled'],
						'title'                => 'Cartão de crédito',
						'description'          => '',
						'api_key'              => $old_options['api_key'],
						'encryption_key'       => $old_options['encryption_key'],
						'checkout'             => 'no',
						'max_installment'      => $old_options['max_installment'],
						'smallest_installment' => $old_options['smallest_installment'],
						'interest_rate'        => $old_options['interest_rate'],
						'free_installments'    => $old_options['free_installments'],
						'debug'                => $old_options['debug'],
					);

					update_option( 'woocommerce_splits-banking-ticket_settings', $banking_ticket );
					update_option( 'woocommerce_splits-credit-card_settings', $credit_card );

					delete_option( 'woocommerce_splits_settings' );
				}
			}
		}
	}

	add_action( 'plugins_loaded', array( 'WC_splits', 'get_instance' ) );

endif;
