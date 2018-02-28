<?php
/**
 * Hubtel API Handler.
 *
 * Creates the invoice for the order and redirects the customer to Hubtel for checkout.
 *
 * @class   WC_Gateway_Hubtel_API_Handler
 * @version 1.0.0
 * @package WooCommerce Hubtel Payment Gateway/Classes/Payment
 * @author  Sébastien Dumont
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Hubtel_API_Handler {

	/**
	 * @access public
	 * @var string Client ID
	 */
	public static $client_id = null;

	/**
	 * @access public
	 * @var string Client Secret
	 */
	public static $client_secret = null;

	/**
	 * @access protected
	 * @var string API URL
	 */
	public static $api_url = 'https://api.hubtel.com/v1/merchantaccount/onlinecheckout/invoice/create';

	/**
	 * Constructor for the handler.
	 *
	 * @access public
	 */
	public function __construct( $client_id, $client_secret ) {
		self::$client_id = $client_id;
		self::$client_secret = $client_secret;
	}

	/**
	 * Create invoice request.
	 *
	 * @access public
	 * @param  object $order
	 * @param  array  $store
	 */
	public function create_invoice_request( $order, $store = array() ) {
		$order_items = $order->get_items( 'line_item' );
		$order_total = $order->get_total();

		// Start invoice.
		$invoice = array(
			'invoice' => array(
				'items' => array(
					'total_amount' => $order_total,
					'description'  => sprintf( __( 'Total cost of $1%s item(s) bought on $2%s.', 'wc-hubtel-payment-gateway' ), count( $order_items ), get_bloginfo('name') )
),
				//'taxes' => array(),
				'store' => $store,
			)
		);

		// Add Order Items
		foreach ( $order_items as $item_id => $item ) {
			$invoice['invoice']['items']['item_' . $item_id] = array(
				'name'        => $item->get_name(),
				'quantity'    => $item->get_quantity(),
				'unit_price'  => $order->get_item_total( $item, false, true ),
				'total_price' => $item->get_total(),
				'description' => $item->get_name()
			);
		}

		// Add taxes.
		//$invoice['invoice']['taxes'] = array();

		$invoice['actions'] = array(
			'cancel_url' => $order->get_cancel_order_url_raw(),
			'return_url' => $order->get_checkout_order_received_url()
		);

		$invoice = apply_filters( 'woocommerce_hubtel_gateway_invoice_request', $invoice );

		return $invoice;
	} // END create_invoice_request()

	/**
	 * Generates the headers to pass to API.
	 *
	 * @access public
	 * @static
	 * @return array
	 */
	public static function get_headers() {
		return apply_filters( 'woocommerce_hubtel_request_headers', array(
			'User-Agent'    => 'WooCommerce Hubtel Checkout',
			'Authorization' => 'Basic ' . base64_encode( self::$client_id . ':' . self::$client_secret ),
			'Cache-Control' => 'no-cache',
			'Content-Type'  => 'application/json',
		) );
	} // END get_headers()

	/**
	 * Checkout invoice
	 *
	 * @access public
	 * @static
	 * @param  int    $order_id
	 * @param  array  $invoice - The invoice data.
	 * @return string $return_response - URL to Hubtel Checkout and Token.
	 */
	public static function checkout_invoice( $order_id, $invoice = array() ) {
		// Get headers.
		$headers = self::get_headers();

		// Encode invoice to JSON format.
		$send_invoice = json_encode( $invoice, JSON_UNESCAPED_SLASHES );

		$response = wp_safe_remote_post(
			self::$api_url,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => apply_filters( 'woocommerce_hubtel_request_body', $send_invoice, self::$api_url ),
				'timeout' => 70,
			)
		);

		// Log $error_returned if response failed.
		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Gateway_Hubtel::log( 'Error Response: ' . wc_print_r( $response, true ) );

			return new WP_Error( sprintf( __( 'Error! - %s', 'wc-hubtel-payment-gateway' ), $response->get_error_message() ) );
		} else {
			// Return response.
			$parsed_response = json_decode( $response['body'] );

			// Check the response was good.
			if ( 200 !== $response['response']['code'] ) {
				WC_Gateway_Hubtel::log( 'Returned Response: ' . wc_print_r( $parsed_response, true ) );
			}

			$error_returned = false;

			// Find response code and text.
			if ( isset( $parsed_response->ResponseCode ) || isset( $parsed_response->response_code ) ) {
				if ( empty( $parsed_response->ResponseCode ) ) {
					$response_code = $parsed_response->response_code;
					$response_text = $parsed_response->response_text;
				}

				if ( empty( $parsed_response->response_code ) ) {
					$response_code = $parsed_response->ResponseCode;
					$response_text = $parsed_response->Message;
				}

				// Log Hubtel response.
				WC_Gateway_Hubtel::log( 'Hubtel Response Code: ' . $response_code );
				WC_Gateway_Hubtel::log( 'Hubtel Response Message: ' . $response_text );

				// If invoice was not created.
				if ( $response_code !== '00' ) {
					$error_returned = true;
				}
			}

			// Redirect if failed.
			if ( $error_returned ) {
				$order_pay_url = wc_get_endpoint_url( 'order-pay', '', wc_get_page_permalink( 'checkout' ) ) . '/' . $order_id . '/';

				wp_safe_redirect( $order_pay_url );
			} else {
				// Get Token
				$token = $parsed_response->token;

				if ( empty( $token ) ) {
					WC_Gateway_Hubtel::log( 'Token is missing!' );
				}

				// Return response if all is good.
				$return_response = array(
					'checkout_url' => $parsed_response->response_text,
					'token'        => $parsed_response->token
				);

				return $return_response;
			}
		}
	} // END checkout_invoice()

	/**
	 * Gets the URL to Hubtel Checkout should the order be incomplete.
	 *
	 * @access public
	 * @static
	 * @param  object $order
	 * @return string $url
	 */
	public static function get_checkout_url( $order ) {
		$token = $order->get_transaction_id();
		$url = 'https://checkout.hubtel.com/checkout/invoice/' . $token;

		return $url;
	} // END get_checkout_url()

} // END class
