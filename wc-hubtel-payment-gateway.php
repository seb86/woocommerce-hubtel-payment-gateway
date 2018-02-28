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
		 * @since  1.0.0
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
			// Checks the WooCommerce enviroment.
			add_action( 'plugins_loaded', array( $this, 'check_enviroment' ) );

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
		 * Check WooCommerce enviroment before activation.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function check_enviroment() {
			// Check we're running the required version of WooCommerce.
			if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $this->required_woo, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );
				return false;
			}

			if ( ! defined( 'IFRAME_REQUEST' ) && ( self::$version !== get_option( 'wc_hubtel_version' ) ) ) {
				$this->install();
			}
		} // END check_enviroment()

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
		public function load_plugin_textdomain() {
			// Load text domain.
			load_plugin_textdomain( 'wc-hubtel-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		} // END load_plugin_textdomain()

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

			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		} // END include()

		/**
		 * Updates the plugin version in database.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static function update_plugin_version() {
			delete_option( 'wc_hubtel_version' );
			update_option( 'wc_hubtel_version', self::$version );
		} // END update_plugin_version()

		/**
		 * Handles upgrade routines.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 */
		public static function install() {
			if ( ! defined( 'WC_HUBTEL_INSTALLING' ) ) {
				define( 'WC_HUBTEL_INSTALLING', true );
			}

			self::update_plugin_version();
		} // END install()

		/**
		 * Adds plugin action links.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @param  array $links
		 * @return array $links
		 */
		public static function plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="admin.php?page=wc-settings&tab=checkout&section=hubtel">' . esc_html__( 'Settings', 'wc-hubtel-payment-gateway' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		} // END plugin_action_links()

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @access public
		 * @static
		 * @since  1.0.0
		 * @param  mixed $links
		 * @param  mixed $file
		 * @return array $links
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

		/**
		 * Add the gateway to WooCommerce.
		 *
		 * @access public
		 * @since  1.0.0
		 * @param  array $methods
		 * @return array $methods
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_Gateway_Hubtel';

			return $methods;
		} // END add_gateways()

	} // END class

} // END if class exists

return WC_Hubtel_Payment_Gateway::instance();
