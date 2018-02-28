<?php
/**
 * Hubtel Payment Gateway.
 *
 * Provides a Hubtel Payment Gateway.
 *
 * @class   WC_Gateway_Hubtel
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package WooCommerce Hubtel Payment Gateway/Classes/Payment
 * @author  Sébastien Dumont
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Hubtel Class.
 */
class WC_Gateway_Hubtel extends WC_Payment_Gateway {

	/**
	 * @access public
	 * @static
	 * @var bool Whether or not logging is enabled
	 */
	public static $log_enabled = false;

	/**
	 * @access public
	 * @static
	 * @var WC_Logger Logger instance
	 */
	public static $logger;

	/**
	 * @access public
	 * @var string Hubtel Client ID
	 */
	public $client_id;

	/**
	 * @access public
	 * @var string Hubtel Client Secret
	 */
	public $client_secret;

	/**
	 * @access public
	 * @var string Store Name
	 */
	public $store_name;

	/**
	 * @access public
	 * @var string Store Tagline
	 */
	public $store_tagline;

	/**
	 * @access public
	 * @var string Store Phone Number
	 */
	public $store_phone;

	/**
	 * @access public
	 * @var string Website URL
	 */
	public $website_url;

	/**
	 * @access public
	 * @var WC_Gateway_Hubtel_API_Handler
	 */
	public $hubtel_handler;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->id                 = 'hubtel';
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Proceed to Hubtel', 'wc-hubtel-payment-gateway' );
		$this->method_title       = __( 'Hubtel', 'wc-hubtel-payment-gateway' );
		$this->method_description = __( 'Hubtel sends customers to Hubtel Checkout to enter their payment information.', 'wc-hubtel-payment-gateway' );
		$this->supports           = array(
			'products',
			//'refunds',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );

		$this->client_id      = $this->get_option( 'client_id' );
		$this->client_secret  = $this->get_option( 'client_secret' );

		// Store details.
		$this->store_name     = $this->get_option( 'store_name' );
		$this->store_tagline  = $this->get_option( 'store_tagline' );
		$this->store_phone    = $this->get_option( 'store_phone' );
		$this->website_url    = $this->get_option( 'website_url' );

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			if ( $this->client_id && $this->client_secret ) {
				include_once( 'class-wc-gateway-hubtel-api-handler.php' );
				$this->hubtel_handler = new WC_Gateway_Hubtel_API_Handler( $this->client_id, $this->client_secret );
			}
		}
	}

	/**
	 * Utilize WC logger class
	 *
	 * @access public
	 * @static
	 * @param string $message
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
		if ( ! self::$log_enabled ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = new WC_Logger();
		}

		$settings = get_option( 'woocommerce_hubtel_settings' );

		if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
			return;
		}

		if ( ! is_null( $start_time ) ) {

			$formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
			$end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
			$formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
			$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );
			$log_entry  = '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
			$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

		} else {

			$log_entry = '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

		}

		self::$logger->add( 'woocommerce-gateway-hubtel', $log_entry );
	} // END log()

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @static
	 * @return string
	 */
	public function get_icon() {
		$logo_url = WC_Hubtel_Payment_Gateway::plugin_url() . '/assets/images/hubtel.png';

		$icon_html = '<img src="' . esc_attr( $logo_url ) . '" alt="' . esc_attr__( 'Hubtel acceptance mark', 'wc-hubtel-payment-gateway' ) . '" />';

		return $icon_html;
	} // END get_icon()

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @access public
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_gateway_hubtel_available_countries', array( 'GHS', 'KES' ) ) );
	} // END is_valid_for_use()

	/**
	 * Admin Panel Options.
	 * - Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @access public
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway disabled', 'wc-hubtel-payment-gateway' ); ?></strong>: <?php _e( 'Hubtel does not support your store currency.', 'wc-hubtel-payment-gateway' ); ?></p></div>
			<?php
		}
	} // END admin_options()

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @access public
	 * @return array  $form_fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'wc-hubtel-payment-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Hubtel', 'wc-hubtel-payment-gateway' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-hubtel-payment-gateway' ),
				'default'     => _x( 'Hubtel', 'Hubtel payment method', 'wc-hubtel-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'wc-hubtel-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-hubtel-payment-gateway' ),
				'default'     => __( 'Hubtel sends customers to Hubtel Checkout to enter their payment information.', 'wc-hubtel-payment-gateway' ),
				'desc_tip'    => true,
			),
			'debug'                 => array(
				'title'       => __( 'Debug log', 'wc-hubtel-payment-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'wc-hubtel-payment-gateway' ),
				'default'     => 'no',
				/* translators: %s: URL */
				'description' => sprintf( __( 'Log Hubtel events, such as payment requests, inside %s', 'wc-hubtel-payment-gateway' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'hubtel' ) . '</code>' ),
			),
			'store_details' => array(
				'title'       => __( 'Store Details', 'wc-hubtel-payment-gateway' ),
				'type'        => 'title',
				'description' => __( 'Enter your Hubtel Store information.', 'wc-hubtel-payment-gateway' ),
			),
			'store_name' => array(
				'title'       => __( 'Store Name', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Store Name for the Invoice.', 'wc-hubtel-payment-gateway' ),
				'default'     => get_option( 'blogname' ),
				'desc_tip'    => true,
				'placeholder' => __( 'Required', 'wc-hubtel-payment-gateway' ),
			),
			'store_tagline' => array(
				'title'       => __( 'Store Tagline', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Store Tagline for the Invoice.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'wc-hubtel-payment-gateway' ),
			),
			'store_phone' => array(
				'title'       => __( 'Store Phone Number', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Store Phone Number for the Invoice.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'wc-hubtel-payment-gateway' ),
			),
			'website_url' => array(
				'title'       => __( 'Website URL', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Store Website URL for the Invoice.', 'wc-hubtel-payment-gateway' ),
				'default'     => get_option( 'siteurl' ),
				'desc_tip'    => true,
				'placeholder' => get_option( 'siteurl' ),
			),
			'api_details' => array(
				'title'       => __( 'API Credentials', 'wc-hubtel-payment-gateway' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Enter your Hubtel API credentials to process payments via Hubtel. You can access your API credentials via <a href="%s" target="_blank">Hubtel Applications</a>.', 'wc-hubtel-payment-gateway' ), 'https://unity.hubtel.com/account/api-accounts' ),
			),
			'client_id' => array(
				'title'       => __( 'Client ID', 'wc-hubtel-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API credentials from Hubtel Applications.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Required', 'wc-hubtel-payment-gateway' ),
			),
			'client_secret' => array(
				'title'       => __( 'Client Secret', 'wc-hubtel-payment-gateway' ),
				'type'        => 'password',
				'description' => __( 'Get your API credentials from Hubtel Applications.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Required', 'wc-hubtel-payment-gateway' ),
			),
		);
	} // END init_form_fields()

	/**
	 * Get the invoice status.
	 *
	 * @access public
	 * @param  WC_Order $order
	 * @return string
	 */
	public function get_invoice_status( $order ) {
		$token              = $order->get_transaction_id();
		$get_invoice_status = 'https://api.hubtel.com/v1/merchantaccount/onlinecheckout/invoice/status/' . $token;

		return $get_invoice_status;
	} // END get_invoice_status()

	/**
	 * Get the transaction URL.
	 *
	 * @access public
	 * @param  WC_Order $order
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = $this->hubtel_handler->get_checkout_url( $order );

		return parent::get_transaction_url( $order );
	} // END get_transaction_url()

	/**
	 * Create invoice and redirect to Hubtel to checkout and make payment.
	 *
	 * @access public
	 * @param  int $order_id
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			$store = array(
				'name'        => $this->store_name,
				'tagline'     => $this->store_tagline,
				'phone'       => $this->store_phone,
				'website_url' => $this->website_url
			);

			$invoice = $this->hubtel_handler->create_invoice_request( $order, $store );

			$this->log( 'Invoice Created: ' . wc_print_r( $invoice, true ) );

			$response = $this->hubtel_handler->checkout_invoice( $order_id, $invoice );

			if ( isset( $response->checkout_url ) ) {
				$this->log( 'Checkout Response: ' . wc_print_r( $response, true ) );

				// Save the checkout token as transaction ID.
				$token = $response->token;
				$this->log( 'Checkout Token: ' . $token );
				$order->set_transaction_id( $token );

				// Redirect customer to Hubtel checkout.
				return array(
					'result'   => 'success',
					'redirect' => esc_url_raw( $response->checkout_url ),
				);
			} else {
				$this->log( 'Hubtel Checkout Failed: ' . wc_print_r( $response, true ) );

				// Set order status as failed.
				$order->update_status( 'failed' );

				// Return result failed.
				return array(
					'result' => 'failed',
				);
			}
		} else {
			$order->payment_complete();

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	} // END process_payment()

	/**
	 * Can the order be refunded via Hubtel?
	 *
	 * @access public
	 * @param  WC_Order $order
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	} // END can_refund_order()

	/**
	 * Process a refund if supported.
	 *
	 * @access public
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log( 'Refund Failed: No transaction ID', 'error' );
			return new WP_Error( 'error', __( 'Refund failed: No transaction ID', 'wc-hubtel-payment-gateway' ) );
		}

		$this->init_api();

		$result = WC_Gateway_Hubtel_API_Handler::refund_transaction( $order, $amount, $reason );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Refund Failed: ' . $result->get_error_message(), 'error' );
			return new WP_Error( 'error', $result->get_error_message() );
		}

		$this->log( 'Refund Result: ' . wc_print_r( $result, true ) );

		$order->add_order_note( sprintf( __( 'Refunded %1$s - Refund ID: %2$s', 'wc-hubtel-payment-gateway' ), $result->GROSSREFUNDAMT, $result->REFUNDTRANSACTIONID ) );

		return isset( $result->L_LONGMESSAGE0 ) ? new WP_Error( 'error', $result->L_LONGMESSAGE0 ) : false;
	} // END process_refund()*/

} // END class
