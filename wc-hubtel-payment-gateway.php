<?php
/*
 * Plugin Name: Hubtel Payment Gateway for WooCommerce
 * Plugin URI:  https://github.com/seb86/woocommerce-hubtel-payment-gateway
 * Version:     1.0.0
 * Description: Provides a payment gateway for Hubtel.
 * Author:      Sébastien Dumont
 * Author URI:  https://sebastiendumont.com
 *
 * Text Domain: wc-hubtel-payment-gateway
 * Domain Path: /languages/
 *
 * Requires at least: 4.4
 * Tested up to: 4.9.4
 * WC requires at least: 3.0.0
 * WC tested up to: 3.3.3
 *
 * Copyright: © 2018 Sébastien Dumont
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! class_exists( 'WC_Hubtel_Payment_Gateway' ) ) {
	class WC_Hubtel_Payment_Gateway {

		/**
		 * @var WC_Hubtel_Payment_Gateway - the single instance of the class.
		 *
		 * @access protected
		 * @static
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Plugin Version
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static $version = '1.0.0';

		/**
		 * Required WooCommerce Version
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $required_woo = '3.0.0';

		/**
		 * Main WC_Hubtel_Payment_Gateway Instance.
		 *
		 * Ensures only one instance of WC_Hubtel_Payment_Gateway is loaded or can be loaded.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @see    WC_Hubtel_Payment_Gateway()
		 * @return WC_Hubtel_Payment_Gateway - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cloning this object is forbidden.', 'wc-hubtel-payment-gateway' ) );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'wc-hubtel-payment-gateway' ) );
		}

		/**
		 * Load the plugin.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function __construct() {
			// Checks for WooCommerce dependency.
			add_action( 'plugins_loaded', array( $this, 'check_woocommerce_dependency' ) );

			// Load translation files.
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

			// Add link to documentation.
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

			// Include required files.
			add_action( 'woocommerce_loaded', array( $this, 'includes' ) );
		}

		/**
		 * Get the Plugin Path.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @return string
		 */
		public static function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		} // END plugin_path()

		/**
		 * Check WooCommerce dependency on activation.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function check_woocommerce_dependency() {
			// Check we're running the required version of WooCommerce.
			if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $this->required_woo, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );
				return false;
			}
		} // END check_woocommerce_dependency()

		/**
		 * Display a warning message if minimum version of WooCommerce check fails.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function admin_notice() {
			echo '<div class="error"><p>' . sprintf( __( '%1$s requires at least %2$s v%3$s or higher.', 'wc-hubtel-payment-gateway' ), 'Hubtel Payment Gateway for WooCommerce', 'WooCommerce', $this->required_woo ) . '</p></div>';
		} // END admin_notice()

		/*-----------------------------------------------------------------------------------*/
		/*  Localization                                                                     */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Make the plugin translation ready.
		 *
		 * Translations should be added in the WordPress language directory:
		 *      - WP_LANG_DIR/plugins/wc-hubtel-payment-gateway-LOCALE.mo
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function load_plugin_textdomain()() {
			// Load text domain.
			load_plugin_textdomain( 'wc-hubtel-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		} // END load_plugin_textdomain()

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @access public
		 * @static
		 * @param  mixed $links
		 * @param  mixed $file
		 * @return array
		 */
		public static function plugin_row_meta( $links, $file ) {
			if ( $file == plugin_basename( __FILE__ ) ) {
				$row_meta = array(
					'docs'    => '<a href="https://github.com/seb86/woocommerce-hubtel-payment-gateway/wiki/" target="_blank">' . __( 'Documentation', 'wc-hubtel-payment-gateway' ) . '</a>',
				);

				$links = array_merge( $links, $row_meta );
			}

			return $links;
		} // END plugin_row_meta()

		/*-----------------------------------------------------------------------------------*/
		/*  Load Files                                                                       */
		/*-----------------------------------------------------------------------------------*/

		/**
		 * Includes Hubtel Payment Gateway.
		 *
		 * @access public
		 * @since  1.0.0
		 * @return void
		 */
		public function includes() {
			include_once( $this->plugin_path() .'/includes/class-wc-gateway-hubtel.php' );
		} // END include()

	} // END class

} // END if class exists

return WC_Hubtel_Payment_Gateway::instance();
