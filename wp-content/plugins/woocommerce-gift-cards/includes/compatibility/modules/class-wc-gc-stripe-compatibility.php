<?php
/**
 * WC_GC_Stripe_Compatibility class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.1.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Stripe Gateway Compatibility.
 *
 * @version  1.2.3
 */
class WC_GC_Stripe_Compatibility {

	/**
	 * Initialize integration.
	 */
	public static function init() {
		add_filter( 'wc_stripe_hide_payment_request_on_product_page', array( __CLASS__, 'remove_payment_request_buttons' ), 11, 2 );
		add_filter( 'wc_stripe_show_payment_request_on_checkout', array( __CLASS__, 'remove_payment_request_buttons_on_checkout' ), 11 );

		// CSS fix for lack of cart filters.
		add_action( 'woocommerce_proceed_to_checkout', array( __CLASS__, 'remove_payment_request_buttons_on_cart' ), -10 );
	}

	/**
	 * Hide payment request buttons on cart page.
	 *
	 * @since 1.2.2
	 *
	 * @return bool
	 */
	public static function remove_payment_request_buttons_on_cart() {
		if ( WC_GC()->cart->cart_contains_gift_card() ) {
			echo '<style>#wc-stripe-payment-request-wrapper, #wc-stripe-payment-request-button-separator { display: none !important }</style>';
		}
	}

	/**
	 * Hide payment request buttons on single Product page.
	 *
	 * @since 1.2.2
	 *
	 * @return bool
	 */
	public static function remove_payment_request_buttons_on_checkout( $show ) {

		if ( WC_GC()->cart->cart_contains_gift_card() ) {
			$show = false;
		}

		return $show;
	}

	/**
	 * Hide payment request buttons on single Product page.
	 *
	 * @param  bool     $hide
	 * @param  WP_Post  $post (Optional)
	 * @return bool
	 */
	public static function remove_payment_request_buttons( $hide, $post = null ) {

		if ( is_null( $post ) ) {
			global $post;
		}

		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return $hide;
		}

		$product = wc_get_product( $post->ID );
		if ( $product && is_a( $product, 'WC_Product' ) && WC_GC_Gift_Card_Product::is_gift_card( $product ) ) {
			$hide = true;
		}

		return $hide;
	}
}

WC_GC_Stripe_Compatibility::init();
