<?php
/**
 * Admin refunds controller class.
 *
 * @package  WooCommerce Gift Cards
 * @since    1.10.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_GC_Admin_Refunds Class.
 *
 * @version 1.15.1
 */
class WC_GC_Admin_Refunds {

	/**
	 * Filter UI runtime flag.
	 *
	 * @deprecated This prop will be removed once we drop support for WC lt 6.4.
	 */
	private static $should_force_render_refunds_form;

	/**
	 * Setup refunds in admin.
	 */
	public static function init() {

		// Display.
		add_action( 'woocommerce_admin_order_totals_after_total', array( __CLASS__, 'add_admin_refund_totals' ), 9 );
		add_action( 'woocommerce_after_order_refund_item_name', array( __CLASS__, 'add_admin_refund_line_description' ) );

		// Fix admin-order refunds UI.
		if ( ! WC_GC_Core_Compatibility::is_wc_version_gte( '6.4' ) ) {
			add_action( 'woocommerce_admin_order_totals_after_tax', array( __CLASS__, 'maybe_force_render_refunds_interface' ) );
		} else {
			add_filter( 'woocommerce_admin_order_should_render_refunds', array( __CLASS__, 'should_render_refunds' ), 10, 2 );
		}
	}

	/**
	 * Whether or not to render the refund button.
	 *
	 * @since 1.12.2
	 *
	 * @param  boolean  $should
	 * @param  int      $order_id
	 * @return boolean
	 */
	public static function should_render_refunds( $should, $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		if ( 0 >= WC_GC()->order->get_order_total_gift_cards( $order ) ) {
			return $should;
		}

		return $should || 0 < WC_GC()->order->get_order_total_captured( $order );
	}

	/*---------------------------------------------------*/
	/*  Refunded UI fix for WC lt 6.4.
	/*---------------------------------------------------*/

	/**
	 * Maybe setup filters for refunds UI, if the order is fully refunded.
	 *
	 * @deprecated This method will be removed once we drop support for WC lt 6.7.
	 *
	 * @param  int  $order_id
	 * @return void
	 */
	public static function maybe_force_render_refunds_interface( $order_id ) {

		if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		if ( $order->is_editable() ) {
			return;
		}

		if ( 0 >= WC_GC()->order->get_order_total_gift_cards( $order ) ) {
			return;
		}

		// Hint: Is (a) fully refunded and (b) has balance captured? Then, filter the refunds UI.
		if ( ( 0 >= $order->get_total() - $order->get_total_refunded() || 0 >= absint( $order->get_item_count() - $order->get_item_count_refunded() ) ) && 0 < WC_GC()->order->get_order_total_captured( $order ) ) {

			// Render the "Refund" button.
			add_action( 'woocommerce_admin_order_totals_after_total', array( __CLASS__, 'enable_display_filters' ), 99999 );
			add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'disable_display_filters'), -99999 );

			// Render the "Refund Summary" details.
			add_action( 'woocommerce_order_item_add_line_buttons', array( __CLASS__, 'enable_display_filters' ), 99999 );
			add_action( 'pre_option_woocommerce_manage_stock', array( __CLASS__, 'disable_display_filters'), -99999 );
		}
	}

	/**
	 * Turn on filters for refunds UI.
	 *
	 * @deprecated This method will be removed once we drop support for WC lt 6.4.
	 */
	public static function enable_display_filters() {
		self::$should_force_render_refunds_form = true;
		add_action( 'woocommerce_order_get_total', array( __CLASS__, 'modify_order_total_for_rendering' ), 10, 2 );
	}

	/**
	 * Turn off filters for refunds UI.
	 *
	 * @deprecated This method will be removed once we drop support for WC lt 6.4.
	 */
	public static function disable_display_filters() {
		if ( true === self::$should_force_render_refunds_form ) {
			self::$should_force_render_refunds_form = null;
			remove_action( 'woocommerce_order_get_total', array( __CLASS__, 'modify_order_total_for_rendering' ), 10, 2 );
		}

		// This return is set for the `get_option()` to work properly.
		return false;
	}

	/**
	 * Filter the order total to make sure refunds UI gets rendered.
	 *
	 * @deprecated This method will be removed once we drop support for WC lt 6.4.
	 *
	 * @param  float     $value
	 * @param  WC_Order  $order
	 * @return float
	 */
	public static function modify_order_total_for_rendering( $value, $order ) {
		if ( true !== self::$should_force_render_refunds_form ) {
			return $value;
		}

		// Hint: Adding one more is enough for `get_total()` to create a diff from `get_total_refunded()` in a fully refunded order.
		return $value + 1;
	}

	/*---------------------------------------------------*/
	/*  Refunds admin UI.
	/*---------------------------------------------------*/

	/**
	 * Adds Gift Cards refund data in admin order totals.
	 *
	 * @param  int  $order_id
	 * @return void
	 */
	public static function add_admin_refund_totals( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$total_gift_cards = WC_GC()->order->get_order_total_gift_cards( $order );
		$total_captured   = WC_GC()->order->get_order_total_captured( $order );

		if ( $total_gift_cards > $total_captured ) {
			?>
			<tr>
				<td class="label refunded-total gift-cards-refunded-total"><?php esc_html_e( 'Refunded', 'woocommerce' ); ?> <small><?php esc_html_e( '(to gift cards)', 'woocommerce-gift-cards' ); ?></small>:</td>
				<td width="1%"></td>
				<td class="total refunded-total">-<?php echo wc_price( $total_gift_cards - $total_captured, array( 'currency' => $order->get_currency() ) ); ?></td>
			</tr>
			<tr>
				<td class="label label-highlight"><?php esc_html_e( 'Net payment', 'woocommerce-gift-cards' ); ?> <small><?php esc_html_e( '(via gift cards)', 'woocommerce-gift-cards' ); ?></small>:</td>
				<td width="1%"></td>
				<td class="total"><?php echo wc_price( $total_captured, array( 'currency' => $order->get_currency() ) ); ?></td>
			</tr>
			<?php
		}

		// Hint: jQuery will move these elements below into the summary totals table.
		?>
		<tr class="wc_gc_move_row_to_refund_summary" style="display: none;">
			<td class="label"><?php esc_html_e( 'Total available to refund to gift cards', 'woocommerce-gift-cards' ); ?>:</td>
			<td class="total"><?php echo wc_price( $total_captured, array( 'currency' => $order->get_currency() ) ); ?></td>
		</tr>
		<tr class="wc_gc_move_row_to_refund_summary" style="display: none;">
			<td class="label"><?php esc_html_e( 'Amount already refunded to gift cards', 'woocommerce-gift-cards' ); ?>:</td>
			<td class="total">
				-<?php echo wc_price( $total_gift_cards - $total_captured, array( 'currency' => $order->get_currency() ) ); ?>
				<input type="hidden" id="gift_card_refunded_amount" name="gift_card_refunded_amount" value="<?php echo esc_attr( number_format( $total_gift_cards - $total_captured, wc_get_price_decimals() ) ); ?>" />
			</td>
		</tr>
		<?php
	}

	/**
	 * Adds Gift Cards refund description in admin order totals.
	 *
	 * @param  WC_Order_Refund  $refund
	 * @return void
	 */
	public static function add_admin_refund_line_description( $refund ) {

		if ( ! is_a( $refund, 'WC_Order_Refund' ) ) {
			return;
		}

		$activities = $refund->get_meta( '_wc_gc_refund_activities', true );
		if ( empty( $activities ) ) {
			return;
		}

		$mask  = wc_gc_mask_codes( 'admin' );
		$text  = _n( 'Refunded to gift card code:', 'Refunded to gift card codes:', count( $activities ), 'woocommerce-gift-cards' );
		$codes = array();

		foreach ( $activities as $id ) {
			$activity = WC_GC()->db->activity->get( $id );
			if ( ! $activity ) {
				continue;
			}
			$codes[] = $mask ? wc_gc_mask_code( $activity->get_gc_code() ) : $activity->get_gc_code();
		}
		$text .= ' ' . implode( ', ', $codes );
		?>
		<p class="description">
			<?php echo esc_html( $text ); ?>
		</p>
		<?php
	}
}

WC_GC_Admin_Refunds::init();
