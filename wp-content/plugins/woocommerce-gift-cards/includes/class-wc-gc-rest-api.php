<?php
/**
 * WC_GC_REST_API class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Endpoints.
 * Similar to Automattic\WooCommerce\RestApi\Server.php.
 *
 * @class    WC_GC_REST_API
 * @version  1.8.0
 */
class WC_GC_REST_API {

	/**
	 * Load required files, setups hooks and rest api fields.
	 */
	public function __construct() {
		$this->includes();
		$this->register_hooks();
		$this->register_fields();
	}

	/**
	 * Load REST API related files.
	 */
	private function includes() {

		// Routes Controllers.
		require_once WC_GC_ABSPATH . 'includes/rest-api/class-wc-gc-rest-api-gift-cards-v2-controller.php';
		require_once WC_GC_ABSPATH . 'includes/rest-api/class-wc-gc-rest-api-gift-cards-controller.php';

		// Fields.
		require_once WC_GC_ABSPATH . 'includes/rest-api/class-wc-gc-rest-api-order-controller.php';
	}

	/**
	 * Sets up hooks.
	 */
	private function register_hooks() {
		add_filter( 'woocommerce_rest_api_get_rest_namespaces', array( $this, 'register_rest_namespaces' ), 10 );
	}

	/**
	 * Register Gift Cards REST namespace.
	 *
	 * @param  array  $namespaces List of registered namespaces.
	 * @return array
	 */
	public function register_rest_namespaces( $namespaces ) {

		// Bail out early.
		if ( isset( $namespaces[ 'wc/v2' ][ 'gift-cards' ] )
		     || isset( $namespaces[ 'wc/v3' ][ 'gift-cards' ] ) ) {
			return $namespaces;
		}

		$namespaces[ 'wc/v2' ][ 'gift-cards' ] = 'WC_GC_REST_API_Gift_Cards_V2_Controller';
		$namespaces[ 'wc/v3' ][ 'gift-cards' ] = 'WC_GC_REST_API_Gift_Cards_Controller';

		return $namespaces;
	}

	/**
	 * Instantiate classes that register new fields in existing routes.
	 */
	private function register_fields() {
		$load_fields = array(
			'WC_GC_REST_API_Order_Controller',
		);

		foreach ( $load_fields as $field_class ) {
			if ( ! class_exists( $field_class ) ) {
				continue;
			}
			$field_class = new $field_class();
		}
	}
}

new WC_GC_REST_API();
