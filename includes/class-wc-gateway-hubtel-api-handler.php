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
	public $client_id = null;

	/**
	 * @access public
	 * @var string Client Secret
	 */
	public $client_secret = null;

	/**
	 * @access protected
	 * @var string API URL
	 */
	public $api_url = 'https://api.hubtel.com/v1/merchantaccount/onlinecheckout/invoice/create';

	/**
	 * Constructor for the handler.
	 *
	 * @access public
	 */
	public function __construct( $client_id, $client_secret ) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
	}

	/**
	 * Create invoice request.
	 *
	 * @access public
	 * @param  object $order
	 * @param  array  $store
	 */
	public function create_invoice_request( $order = array(), $store = array() ) {
		$order_items = $order->get_items( 'line_item' );
		$order_total = $order->get_total();

		// Start invoice.
		$invoice = array(
			'invoice' => array(
				'items' => array(
					'total_amount' => $order_total,
					'description'  => sprintf( __( 'Total cost of %s items.', 'wc-hubtel-payment-gateway' ), count( $order_items ) )
),
				//'taxes' => array(),
				'store' => $store,
			)
		);

		// Add Order Items
		foreach ( $order_items as $item_id => $item ) {
			$invoice['items']['item_' . $item_id] = array(
				'name'        => $item->get_name(),
				'quantity'    => $item->get_quantity(),
				'unit_price'  => $order->get_item_total( $item, false, true ),
				'total_price' => $item->get_total(),
				'description' => $item->get_name()
			);
		}

		// Add taxes.
		//$invoice['taxes'] = array();

		$invoice['actions'] = array(
			'cancel_url' => $order->get_cancel_order_url_raw(),
			'return_url' => $order->get_checkout_order_received_url()
		);

		$invoice = apply_filters( 'woocommerce_hubtel_gateway_invoice_request', $invoice );

		return $invoice;
	} // END create_invoice_request()

	/**
	 * Checkout invoice
	 *
	 * @access public
	 * @static
	 * @param  array  $invoice - The invoice data.
	 * @return string $redirect_url - URL to Hubtel Checkout.
	 */
	public static function checkout_invoice( $order_id, $invoice = array() ) {
		$basic_auth_key = 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret );

		$send_invoice = json_encode( $invoice, JSON_UNESCAPED_SLASHES );

		$ch =  curl_init( $this->api_url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $create_invoice );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Authorization: ' . $basic_auth_key,
			'Cache-Control: no-cache',
			'Content-Type: application/json',
		) );

		$result = curl_exec( $ch );
		$error  = curl_error( $ch );

		curl_close( $ch );

		if ( $error ) {
			echo $error;
		} else {
			// Redirect customer to Hubtel checkout.
			$response_param = json_decode( $result );
			$redirect_url = $response_param->response_text;

			// Save the checkout token as transaction ID.
			$token = $response_param->token;
			$order->set_transaction_id( $token );

			return $redirect_url;
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
