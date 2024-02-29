<?php
/**
 * WC_GC_Modules class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.9.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GC Modules Loader
 *
 * @version  1.9.0
 */
class WC_GC_Modules {

	/**
	 * The single instance of the class.
	 * @var WC_GC_Modules
	 */
	protected static $_instance = null;

	/**
	 * Modules to instantiate.
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Main WC_GC_Modules instance. Ensures only one instance of WC_GC_Modules is loaded or can be loaded.
	 *
	 * @static
	 * @return WC_GC_Modules
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-gift-cards' ), '1.9.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!', 'woocommerce-gift-cards' ), '1.9.0' );
	}

	/**
	 * Handles module initialization.
	 *
	 * @return void
	 */
	public function __construct() {

		// Abstract modules container class.
		require_once( 'abstract/class-wc-gc-abstract-module.php' );

		// Send As Gift module.
		require_once( 'send-as-gift/class-wc-gc-sag-module.php' );

		$module_names = apply_filters( 'woocommerce_gift_cards_modules', array(
			'WC_GC_SAG_Module'
		) );

		foreach ( $module_names as $module_name ) {
			$this->modules[] = new $module_name();
		}
	}

	/**
	 * Loads module functionality associated with a named component.
	 *
	 * @param  string  $name
	 */
	public function load_components( $name ) {

		foreach ( $this->modules as $module ) {
			$module->load_component( $name );
		}
	}
}
