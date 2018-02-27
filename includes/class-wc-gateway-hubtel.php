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

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	/** @var string */
	public static $logout_url = '';

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
		$this->method_description = __( 'Hubtel sends customers to Hubtel to enter their payment information.', 'wc-hubtel-payment-gateway' );
		$this->supports           = array(
			'products',
			'refunds',
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

		// Actions
		$this->cancel_url     = $this->get_option( 'cancel_url', $this->website_url );

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		//add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
		//add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );

		add_filter( 'woocommerce_currencies', array( $this, 'add_hubtel_supported_currencies' ) );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'add_hubtel_supported_currency_symbols' ), 10, 2 );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		} else {
			if ( $this->client_id && $this->client_secret ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-hubtel-handler.php' );
				new WC_Gateway_Hubtel_Handler( $this->client_id, $this->client_secret );
			}
		}
	}

	/**
	 * Adds Ghana Cedi and Kenya Shilling Currency.
	 *
	 * @access public
	 * @param  array $currencies
	 * @return array $currencies
	 */
	public function add_hubtel_supported_currencies( $currencies ) {
		$currencies['GHS'] = __( 'Ghana Cedi', 'wc-hubtel-payment-gateway' );
		$currencies['KES'] = __( 'Kenya Shilling', 'wc-hubtel-payment-gateway' );

		return $currencies;
	} // END add_hubtel_supported_currencies()

	/**
	 * Adds Ghana Cedi and Kenya Shilling Currency Symbols.
	 *
	 * @access public
	 * @param  string $currency_symbol
	 * @param  array  $currency
	 * @return array  $currency_symbol
	 */
	public function add_hubtel_supported_currency_symbols( $currency_symbol, $currency ) {
		switch( $currency ) {
			case 'GHS':
				$currency_symbol = 'GH¢';
				break;

			case 'KES':
				$currency_symbol = 'KSh';
				break;
		}

		return $currency_symbol;
	} // END add_hubtel_supported_currency_symbols()

	/**
	 * Logging method.
	 *
	 * @access public
	 * @static
	 * @param  string $message Log message.
	 * @param  string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'hubtel' ) );
		}
	} // END log()

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '<img src="' . esc_attr( self::$logo_url ) . '" alt="' . esc_attr__( 'Hubtel acceptance mark', 'wc-hubtel-payment-gateway' ) . '" />';

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
				'default'     => _x( 'Check payments', 'Check payment method', 'wc-hubtel-payment-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'wc-hubtel-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-hubtel-payment-gateway' ),
				'default'     => __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'wc-hubtel-payment-gateway' ),
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
			'image_url'             => array(
				'title'       => __( 'Image url', 'wc-hubtel-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'Optionally enter the URL to a 150x50px image displayed as your logo in the upper left corner of the Hubtel checkout pages.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'wc-hubtel-payment-gateway' ),
			),
			/*'instructions' => array(
				'title'       => __( 'Instructions', 'wc-hubtel-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-hubtel-payment-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),*/
		);
	} // END init_form_fields()

	/**
	 * Create invoice and redirect to Hubtel to checkout and make payment.
	 *
	 * @access public
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-hubtel-request.php' );

		$order = wc_get_order( $order_id );

		$this->log( 'Invoice Created: ' . wc_print_r( $order ) );

		return array(
			'result'   => 'success',
			'redirect' => WC_Gateway_Hubtel_API_Handler::get_checkout_url( $this->client_id, $this->client_secret, $order ),
		);
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
	} // END process_refund()

	/**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @access public
	 * @param  int $order_id
	 */
	public function capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'hubtel' === $order->get_payment_method() && 'pending' === get_post_meta( $order->get_id(), '_hubtel_status', true ) && $order->get_transaction_id() ) {
			$this->init_api();
			$result = WC_Gateway_Hubtel_API_Handler::do_capture( $order );

			if ( is_wp_error( $result ) ) {
				$this->log( 'Capture Failed: ' . $result->get_error_message(), 'error' );
				$order->add_order_note( sprintf( __( 'Payment could not captured: %s', 'wc-hubtel-payment-gateway' ), $result->get_error_message() ) );
				return;
			}

			$this->log( 'Capture Result: ' . wc_print_r( $result, true ) );

			if ( ! empty( $result->PAYMENTSTATUS ) ) {
				switch ( $result->PAYMENTSTATUS ) {
					case 'Completed' :
						$order->add_order_note( sprintf( __( 'Payment of %1$s was captured - Auth ID: %2$s, Transaction ID: %3$s', 'wc-hubtel-payment-gateway' ), $result->AMT, $result->AUTHORIZATIONID, $result->TRANSACTIONID ) );
						update_post_meta( $order->get_id(), '_hubtel_status', $result->PAYMENTSTATUS );
						update_post_meta( $order->get_id(), '_checkout_id', $result->TRANSACTIONID );
					break;
					default :
						$order->add_order_note( sprintf( __( 'Payment could not captured - Auth ID: %1$s, Status: %2$s', 'wc-hubtel-payment-gateway' ), $result->AUTHORIZATIONID, $result->PAYMENTSTATUS ) );
					break;
				}
			}
		}
	} // END capture_payment()

} // END class
