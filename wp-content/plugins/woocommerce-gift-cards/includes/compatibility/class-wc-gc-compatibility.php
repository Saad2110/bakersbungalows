<?php
/**
 * WC_GC_Compatibility class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles compatibility with other WC extensions.
 *
 * @class    WC_GC_Compatibility
 * @version  1.15.0
 */
class WC_GC_Compatibility {

	/**
	 * Min required plugin versions to check.
	 *
	 * @var array
	 */
	private static $required = array(
		'wc_stripe' => '4.0.0',
		'blocks'    => '7.0.0'
	);

	/**
	 * Keep track of cart totals recursive loops.
	 *
	 * @var int
	 */
	protected static $recursive_counter;

	/**
	 * Setup compatibility class.
	 */
	public static function init() {
		// Initialize.
		self::load_modules();
		self::$recursive_counter = 0;

		// Manage cart recursive loops.
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'increase_cart_totals_recursive_counter' ), -999999, 1 );
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'decrease_cart_totals_recursive_counter' ), 999999, 1 );
	}

	/**
	 * When starting the cart totals calculation increase the counter.
	 *
	 * @since  1.5.0
	 *
	 * @return void
	 */
	public static function increase_cart_totals_recursive_counter( $cart ) {
		self::$recursive_counter++;
	}

	/**
	 * When ending the cart totals calculation decrease the counter.
	 *
	 * @since  1.5.0
	 *
	 * @return void
	 */
	public static function decrease_cart_totals_recursive_counter( $cart ) {
		self::$recursive_counter--;
	}

	/**
	 * Detect if it's a cart totals recursive loop.
	 *
	 * @since  1.5.0
	 *
	 * @return void
	 */
	public static function has_cart_totals_loop() {
		if ( self::$recursive_counter > 1 ) {
			return true;
		}

		return false;
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	protected static function load_modules() {

		if ( is_admin() ) {
			// Check plugin min versions.
			add_action( 'admin_init', array( __CLASS__, 'add_compatibility_notices' ) );
		}

		// Include core compatibility class.
		self::core_includes();

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );

		// Load modules.
		add_action( 'plugins_loaded', array( __CLASS__, 'module_includes' ), 100 );

		// Prevent initialization of deprecated mini-extensions usually loaded on 'plugins_loaded' -- 10.
		self::unload_modules();
	}

	/**
	 * Core compatibility functions.
	 *
	 * @return void
	 */
	public static function core_includes() {
		require_once  WC_GC_ABSPATH . 'includes/compatibility/core/class-wc-gc-core-compatibility.php' ;
	}

	/**
	 * Declare HPOS( Custom Order tables) compatibility.
	 *
	 * @since 1.13.1
	 */
	public static function declare_hpos_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		$compatibility = WC_GC_Core_Compatibility::is_wc_version_gte( '7.1.1' );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_GC()->get_plugin_basename(), $compatibility );
	}

	/**
	 * Prevent deprecated mini-extensions from initializing.
	 *
	 * @return void
	 */
	protected static function unload_modules() {
		// Silence.
	}

	/**
	 * Load compatibility classes.
	 *
	 * @return void
	 */
	public static function module_includes() {

		$module_paths = array();

		// WooCommerce Cart/Checkout Blocks support.
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) && version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), self::$required[ 'blocks' ] ) > 0 ) {
			$module_paths[ 'blocks' ] = WC_GC_ABSPATH . 'includes/compatibility/modules/class-wc-gc-blocks-compatibility.php';
		}

		// WC Subscriptions compatibility.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$module_paths[ 'wc_subscriptions' ] = 'modules/class-wc-gc-subscriptions-compatibility.php';
		}

		// WooCommerce PDF Invoices & Packing Slips by Ewout Fernhout support.
		if ( class_exists( 'WPO_WCPDF' ) ) {
			$module_paths[ 'wpowcpdf' ] = 'modules/class-wc-gc-wpowcpdf-compatibility.php';
		}

		// WooCommerce PIP.
		if ( class_exists( 'WC_PIP' ) ) {
			$module_paths[ 'pip' ] = 'modules/class-wc-gc-pip-compatibility.php';
		}

		// WC Stripe support.
		if ( class_exists( 'WC_Stripe' ) && defined( 'WC_STRIPE_VERSION' ) && version_compare( WC_STRIPE_VERSION, self::$required[ 'wc_stripe' ] ) >= 0 ) {
			$module_paths[ 'wc_stripe' ] = 'modules/class-wc-gc-stripe-compatibility.php';
		}

		// WooCommerce Avatax.
		if ( class_exists( 'WC_AvaTax' ) ) {
			$module_paths[ 'avatax' ] = 'modules/class-wc-gc-avatax-compatibility.php';
		}

		// Elementor Pro compatibility.
		if ( class_exists('\ElementorPro\Plugin') ) {
			$module_paths[ 'elementor_pro' ] = 'modules/class-wc-gc-elementor-compatibility.php';
		}

		// Flatsome compatibility.
		if ( function_exists( 'wc_is_active_theme' ) && wc_is_active_theme( 'flatsome' ) ) {
			$module_paths[ 'flatsome' ] = 'modules/class-wc-gc-fs-compatibility.php';
		}

		// WooCommerce Payments compatibility.
		if ( class_exists( 'WC_Payments' ) ) {
			$module_paths[ 'wcpay' ] = 'modules/class-wc-gc-wc-payments-compatibility.php';
		}

		/**
		 * 'woocommerce_gc_compatibility_modules' filter.
		 *
		 * Use this to filter the required compatibility modules.
		 *
		 * @since  1.0.0
		 * @param  array $module_paths
		 */
		$module_paths = apply_filters( 'woocommerce_gc_compatibility_modules', $module_paths );

		foreach ( $module_paths as $name => $path ) {
			require_once $path ;
		}
	}

	/**
	 * Checks versions of compatible/integrated/deprecated extensions.
	 *
	 * @return void
	 */
	public static function add_compatibility_notices() {

		// WC Stripe version check.
		if ( class_exists( 'WC_Stripe' ) ) {

			$required_version = self::$required[ 'wc_stripe' ];

			if ( ! defined( 'WC_STRIPE_VERSION' ) || version_compare( WC_STRIPE_VERSION, $required_version ) < 0 ) {

				$extension      = __( 'Stripe Gateway', 'woocommerce-gift-cards' );
				$extension_full = __( 'WooCommerce Stripe Gateway', 'woocommerce-gift-cards' );
				$extension_url  = 'https://woocommerce.com/products/stripe/';
				/* translators: %1$s: extension %2$s: extension url %3$s: extension %4$s: version */
				$notice         = sprintf( __( 'The installed version of <strong>%1$s</strong> is not supported by <strong>Gift Cards</strong>. Please update <a href="%2$s" target="_blank">%3$s</a> to version <strong>%4$s</strong> or higher.', 'woocommerce-gift-cards' ), $extension, $extension_url, $extension_full, $required_version );

				WC_GC_Admin_Notices::add_dismissible_notice( $notice, array( 'dismiss_class' => 'wc_stripe_lt_' . $required_version, 'type' => 'warning' ) );
			}
		}
	}
}

WC_GC_Compatibility::init();
