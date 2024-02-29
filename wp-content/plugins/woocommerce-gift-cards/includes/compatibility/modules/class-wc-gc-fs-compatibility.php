<?php
/**
 * WC_GC_FS_Compatibility class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.5.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flatsome integration.
 *
 * @version  1.5.2
 */
class WC_GC_FS_Compatibility {

	public static function init() {
		// Add hooks if the active parent theme is Flatsome.
		add_action( 'after_setup_theme', array( __CLASS__, 'maybe_add_hooks' ) );
	}

	/**
	 * Add hooks if the active parent theme is Flatsome.
	 */
	public static function maybe_add_hooks() {

		if ( function_exists( 'flatsome_quickview' ) ) {
			// Initialize gift cards in quick view modals.
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'add_quickview_integration' ), 999 );
		}
	}

	/**
	 * Initializes bundles in quick view modals.
	 *
	 * @return array
	 */
	public static function add_quickview_integration() {

		wp_enqueue_style( 'wc-gc-css' );
		WC_GC()->templates->enqueue_scripts();
		wp_add_inline_script( 'wc-gc-main',
		'
			jQuery( document ).on( "mfpOpen", function( e ) {

				// Init datepickers.
				jQuery( ".woocommerce_gc_giftcard_form" ).wc_gc_datepickers();

			} );
		' );
	}
}

WC_GC_FS_Compatibility::init();
