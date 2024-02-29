<?php
/**
 * WC_GC_Tracker class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.12.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gift Cards Helper Functions.
 *
 * @class    WC_GC_Tracker
 * @version  1.15.0
 */
class WC_GC_Tracker {

	/**
	 * Initialize the Tracker.
	 */
	public static function init() {
		if ( 'yes' === get_option( 'woocommerce_allow_tracking', 'no' ) ) {

			add_filter( 'woocommerce_tracker_data', array( __CLASS__, 'add_tracking_data' ), 10 );
			// Async tasks.
			add_action( 'wc_gc_daily', array( __CLASS__, 'maybe_calculate_tracking_data' ) );
		}

	}

	/**
	 * Adds GC data to the tracked data.
	 *
	 * @param array $data
	 * @return array all the tracking data.
	 */
	public static function add_tracking_data( $data ) {
		$data[ 'extensions' ][ 'wc_gc' ] = self::get_tracking_data();
		return $data;
	}

	/**
	 * Get all tracking data from cache.
	 *
	 * @return array All the tracking data.
	 */
	protected static function get_tracking_data() {
		$tracking_data = get_option( self::get_cache_key() );
		if ( empty( $tracking_data ) ) {
			$tracking_data = array();
		}

		return $tracking_data;
	}

	/**
	 * Calculates all tracking-related data for the previous month and year.
	 * Runs indepedently in a background task. @see ::maybe_calculate_tracking_data().
	 *
	 * @return array All the tracking data.
	 */
	protected static function calculate_tracking_data() {
		$tracking_data                = array();
		$tracking_data[ 'settings' ]  = self::get_settings();
		$tracking_data[ 'giftcards' ] = self::get_giftcards_data();
		$tracking_data[ 'orders' ]    = self::get_orders_data();
		return $tracking_data;
	}

	/**
	 * Maybe calculate orders data. Also, handles the caching strategy.
	 *
	 * @return bool Returns true if the data are re-calculated, false otherwise.
	 */
	public static function maybe_calculate_tracking_data() {
		$dates     = self::get_dates( 'previous_month' );
		$cache_key = self::get_cache_key();
		$data      = get_option( $cache_key );
		if ( empty( $data ) ) {

			$data = self::calculate_tracking_data();

			// Cache.
			update_option( $cache_key, $data );

			// Auto-clear previous cache key, once a new one is generated.
			$prev_dates     = self::get_dates( 'previous_month', $dates[ 'start' ] );
			$prev_cache_key = self::get_cache_key( $prev_dates[ 'start' ] );
			delete_option( $prev_cache_key );

			return true;
		}

		return false;
	}

	/**
	 * Orders data cache key based on time.
	 *
	 * @param  DateTime $reference_date (Optional) A reference date to calculate on the previous month and year cache key. Defaults to 'now'.
	 * @return string The cache key string.
	 */
	private static function get_cache_key( $reference_date = null ) {
		$dates = self::get_dates( 'previous_month', $reference_date );
		return sprintf(
			'woocommerce_gc_tracking_data_%s_%s',
			$dates[ 'start' ]->format( 'n' ),
			$dates[ 'start' ]->format( 'Y' )
		);
	}

	/**
	 * Gets settings data.
	 *
	 * @return array
	 */
	private static function get_settings() {

		$bcc_recipients = get_option( 'wc_gc_bcc_recipients' );

		return array(
			'account_features'                => 'yes' === get_option( 'wc_gc_is_redeeming_enabled', 'yes' ) ? 'on' : 'off',
			'cart_features'                   => 'yes' === get_option( 'wc_gc_disable_cart_ui', 'yes' ) ? 'off' : 'on',
			'allow_coupons_with_gift_cards'   => 'yes' === get_option( 'wc_gc_disable_coupons_with_gift_cards', 'no' ) ? 'off' : 'on',
			'allow_multiple_recipients'       => 'yes' !== get_option( 'wc_gc_allow_multiple_recipients', 'no' ) ? 'off' : 'on',
			'bcc_recipients' => ! empty( $bcc_recipients ) ? 'on' : 'off',
			'unmask_codes_for_shop_managers'  => 'yes' !== get_option( 'wc_gc_unmask_codes_for_shop_managers', 'no' ) ? 'off' : 'on',
			'send_as_gift_status'             => wc_gc_sag_get_send_as_gift_status(),
		);
	}

	/**
	 * Gets giftcards data.
	 *
	 * @return array
	 */
	private static function get_giftcards_data() {
		global $wpdb;

		$previous_month_dates  = self::get_dates( 'previous_month' );
		$previous_year_dates   = self::get_dates( 'previous_year' );

		$issued_previous_month = WC_GC()->db->activity->query( array(
			'type'       => 'issued',
			'start_date' => $previous_month_dates[ 'start' ]->getTimestamp(),
			'end_date'   => $previous_month_dates[ 'end' ]->getTimestamp()
		) );

		$issued_previous_year = WC_GC()->db->activity->query( array(
			'type'       => 'issued',
			'start_date' => $previous_year_dates[ 'start' ]->getTimestamp(),
			'end_date'   => $previous_year_dates[ 'end' ]->getTimestamp()
		) );

		$issued_revenue_previous_month = (float) array_sum( wp_list_pluck( $issued_previous_month, 'amount' ) );
		$issued_revenue_previous_year  = (float) array_sum( wp_list_pluck( $issued_previous_year, 'amount' ) );

		// Orders - Date of the first order in store that used gift cards for payment.
		$first_date = (int) $wpdb->get_var( "
			SELECT min(`date`) as `min_date`
			FROM `{$wpdb->prefix}woocommerce_gc_activity` as `activities`
			WHERE `type` = 'issued'
			" );

		$data = array(

			// Dates.
			'first_issued_date'             => $first_date ? gmdate( 'Y-m-d H:i:s', $first_date ) : null,

			// Total gift cards issued previous month.
			'issued_previous_month'         => count( $issued_previous_month ),

			// Total gift cards issued previous year.
			'issued_previous_year'          => count( $issued_previous_year ),

			// Total gift cards balance issued previous month.
			'issued_revenue_previous_month' => $issued_revenue_previous_month,

			// Total gift cards balance issued previous year.
			'issued_revenue_previous_year'  => $issued_revenue_previous_year
		);

		return $data;
	}

	/**
	 * Get orders data for tracking.
	 *
	 * @return array The orders data from cache, empty array if no cache.
	 */
	private static function get_orders_data() {
		global $wpdb;

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$hpos_orders_table = Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
		}

		$previous_month_dates = self::get_dates( 'previous_month' );
		$previous_year_dates  = self::get_dates( 'previous_year' );

		// Revenue - Previous month.
		$balance_revenue_prev_month = WC_GC()->db->activity->get_gift_card_captured_amount( array(
			'date_start'       => $previous_month_dates[ 'start' ]->getTimestamp(),
			'date_end'         => $previous_month_dates[ 'end' ]->getTimestamp(),
			'exclude_statuses' => array( 'cancelled', 'pending', 'failed', 'on-hold', 'checkout-draft', 'trash' )
		) );

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_revenue_prev_month = (float) $wpdb->get_var( "
				SELECT SUM( `orders`.`total_amount` )
				FROM `{$hpos_orders_table}` AS `orders`
				WHERE `orders`.`date_created_gmt` >= '{$previous_month_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`date_created_gmt` <= '{$previous_month_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`type` = 'shop_order'
			" );
		} else {
			$total_revenue_prev_month = (float) $wpdb->get_var( "
				SELECT SUM( `order_total`.`meta_value` )
				FROM `{$wpdb->posts}` AS `orders`
				LEFT JOIN `{$wpdb->prefix}postmeta` AS `order_total` ON `order_total`.`post_id` = `orders`.`ID`
				WHERE `orders`.`post_date_gmt` >= '{$previous_month_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_date_gmt` <= '{$previous_month_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `order_total`.`meta_key` = '_order_total'
				AND `orders`.`post_type` = 'shop_order'
			" );
		}

		$balance_revenue_monthly_percentage = $total_revenue_prev_month + $balance_revenue_prev_month > 0 ? round( $balance_revenue_prev_month / ( $total_revenue_prev_month + $balance_revenue_prev_month ) * 100, 4 ) : 0;

		// Revenue - Previous year.
		$balance_revenue_prev_year = WC_GC()->db->activity->get_gift_card_captured_amount( array(
			'date_start'       => $previous_year_dates[ 'start' ]->getTimestamp(),
			'date_end'         => $previous_year_dates[ 'end' ]->getTimestamp(),
			'exclude_statuses' => array( 'cancelled', 'pending', 'failed', 'on-hold', 'checkout-draft', 'trash' )
		) );

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_revenue_prev_year = (float) $wpdb->get_var( "
				SELECT SUM( `orders`.`total_amount` )
				FROM `{$hpos_orders_table}` AS `orders`
				WHERE `orders`.`date_created_gmt` >= '{$previous_year_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`date_created_gmt` <= '{$previous_year_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`type` = 'shop_order'
			" );
		} else {
			$total_revenue_prev_year = (float) $wpdb->get_var( "
				SELECT SUM( `order_total`.`meta_value` )
				FROM `{$wpdb->posts}` AS `orders`
				LEFT JOIN `{$wpdb->prefix}postmeta` AS `order_total` ON `order_total`.`post_id` = `orders`.`ID`
				WHERE `orders`.`post_date_gmt` >= '{$previous_year_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_date_gmt` <= '{$previous_year_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `order_total`.`meta_key` = '_order_total'
				AND `orders`.`post_type` = 'shop_order'
			" );
		}

		$balance_revenue_yearly_percentage = $total_revenue_prev_year + $balance_revenue_prev_year > 0 ? round( $balance_revenue_prev_year / ( $total_revenue_prev_year + $balance_revenue_prev_year ) * 100, 4 ) : 0;

		// Orders - Previous month.
		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_orders_prev_month = (int) $wpdb->get_var( "
				SELECT COUNT( `orders`.`id` )
				FROM `{$hpos_orders_table}` AS `orders`
				WHERE `orders`.`date_created_gmt` >= '{$previous_month_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`date_created_gmt` <= '{$previous_month_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`type` = 'shop_order'
			" );
		}else {
			$total_orders_prev_month = (int) $wpdb->get_var( "
				SELECT COUNT( `ID` )
				FROM `{$wpdb->posts}` AS `orders`
				WHERE `orders`.`post_date_gmt` >= '{$previous_month_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_date_gmt` <= '{$previous_month_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`post_type` = 'shop_order'
			" );
		}

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_orders_with_balance_prev_month = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `object_id` ) ) as `num_orders`
				FROM `{$wpdb->prefix}woocommerce_gc_activity` as `activities`
				INNER JOIN `{$hpos_orders_table}` as `orders` ON `activities`.`object_id` = `orders`.`id`
				WHERE `activities`.`date` >= '{$previous_month_dates[ 'start' ]->getTimestamp()}'
				AND `activities`.`date` <= '{$previous_month_dates[ 'end' ]->getTimestamp()}'
				AND `activities`.`type` = 'used'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
			" );
		} else {
			$total_orders_with_balance_prev_month = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `object_id` ) ) as `num_orders`
				FROM `{$wpdb->prefix}woocommerce_gc_activity` as `activities`
				INNER JOIN `{$wpdb->posts}` as `orders` ON `activities`.`object_id` = `orders`.`ID`
				WHERE `activities`.`date` >= '{$previous_month_dates[ 'start' ]->getTimestamp()}'
				AND `activities`.`date` <= '{$previous_month_dates[ 'end' ]->getTimestamp()}'
				AND `activities`.`type` = 'used'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
			" );
		}

		$orders_usage_monthly_percentage = ! empty( $total_orders_prev_month ) ? round( $total_orders_with_balance_prev_month / $total_orders_prev_month * 100, 4 ) : 0;

		// Orders - Previous year.
		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_orders_prev_year = (int) $wpdb->get_var( "
				SELECT COUNT( `orders`.`id` )
				FROM `{$hpos_orders_table}` AS `orders`
				WHERE `orders`.`date_created_gmt` >= '{$previous_year_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`date_created_gmt` <= '{$previous_year_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`type` = 'shop_order'
			" );
		}else {
			$total_orders_prev_year = (int) $wpdb->get_var( "
				SELECT COUNT( `ID` )
				FROM `{$wpdb->posts}` AS `orders`
				WHERE `orders`.`post_date_gmt` >= '{$previous_year_dates[ 'start' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_date_gmt` <= '{$previous_year_dates[ 'end' ]->format( 'Y-m-d H:i:s' )}'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
				AND `orders`.`post_type` = 'shop_order'
			" );
		}

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$total_orders_with_balance_prev_year = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `object_id` ) ) as `num_orders`
				FROM `{$wpdb->prefix}woocommerce_gc_activity` as `activities`
				INNER JOIN `{$hpos_orders_table}` as `orders` ON `activities`.`object_id` = `orders`.`id`
				WHERE `activities`.`date` >= '{$previous_year_dates[ 'start' ]->getTimestamp()}'
				AND `activities`.`date` <= '{$previous_year_dates[ 'end' ]->getTimestamp()}'
				AND `activities`.`type` = 'used'
				AND `orders`.`status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
			" );
		} else {
			$total_orders_with_balance_prev_year = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `object_id` ) ) as `num_orders`
				FROM `{$wpdb->prefix}woocommerce_gc_activity` as `activities`
				INNER JOIN `{$wpdb->posts}` as `orders` ON `activities`.`object_id` = `orders`.`ID`
				WHERE `activities`.`date` >= '{$previous_year_dates[ 'start' ]->getTimestamp()}'
				AND `activities`.`date` <= '{$previous_year_dates[ 'end' ]->getTimestamp()}'
				AND `activities`.`type` = 'used'
				AND `orders`.`post_status` NOT IN ( 'wc-cancelled', 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-checkout-draft', 'wc-trash' )
			" );
		}

		$orders_usage_yearly_percentage = ! empty( $total_orders_prev_year ) ? round( $total_orders_with_balance_prev_year / $total_orders_prev_year * 100, 4 ) : 0;

		if ( WC_GC_Core_Compatibility::is_hpos_enabled() ) {
			$orders_currencies_count = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `currency` ) )
				FROM `{$hpos_orders_table}` AS `orders`
			" );
		} else {
			$orders_currencies_count = (int) $wpdb->get_var( "
				SELECT COUNT( DISTINCT( `meta_value` ) )
				FROM `{$wpdb->postmeta}` AS `orders_meta`
				WHERE `orders_meta`.`meta_key` = '_order_currency'
			" );
		}

		$data = array(

			// Monthly balance usage.
			'balance_revenue_previous_month'         => $balance_revenue_prev_month,
			'revenue_previous_month'                 => $total_revenue_prev_month + $balance_revenue_prev_month,
			'balance_revenue_previous_month_percent' => $balance_revenue_monthly_percentage,

			// Yearly balance usage.
			'balance_revenue_previous_year'          => $balance_revenue_prev_year,
			'revenue_previous_year'                  => $total_revenue_prev_year + $balance_revenue_prev_year,
			'balance_revenue_previous_year_percent'  => $balance_revenue_yearly_percentage,

			// Monthly usage.
			'count_with_balance_previous_month'      => $total_orders_with_balance_prev_month,
			'count_previous_month'                   => $total_orders_prev_month,
			'usage_previous_month_percent'           => $orders_usage_monthly_percentage,

			// Yearly usage.
			'count_with_balance_previous_year'       => $total_orders_with_balance_prev_year,
			'count_previous_year'                    => $total_orders_prev_year,
			'usage_previous_year_percent'            => $orders_usage_yearly_percentage,

			// Multi-currency data
			'orders_in_multiple_currencies'          => ( $orders_currencies_count > 1 ) ? true : false,
		);

		return $data;
	}

	/**
	 * Get dates.
	 *
	 * @param string $time_period
	 * @param DateTime $reference_date (Optional) Date to act as reference for calculating the previous month, year. Defaults to 'now'.
	 * @return array Array of DateTime objects.
	 */
	protected static function get_dates( $time_period, $reference_date = null ) {

		if ( ! in_array( $time_period, array( 'previous_month', 'previous_year' ) ) ) {
			return array();
		}

		$today = is_a( $reference_date, 'DateTime' ) ? $reference_date : new DateTime();
		$today->setTime( 0,0,0 );

		switch ( $time_period ) {

			case 'previous_month':

				$ref       = $today->setDate( $today->format( 'Y' ), $today->format( 'm' ), 1 );
				$last_day  = $ref->sub( new DateInterval( 'P1D' ) );
				$first_day = clone $last_day;
				$first_day->setDate( $last_day->format( 'Y' ), $last_day->format( 'm' ), 1 );
				$last_day->setTime( 23, 59, 59 );

				break;

			case 'previous_year':

				$ref       = $today->setDate( $today->format( 'Y' ), 1, 1 );
				$last_day  = $ref->sub( new DateInterval( 'P1D' ) );
				$first_day = clone $last_day;
				$first_day->setDate( $last_day->format( 'Y' ), 1, 1 );
				$last_day->setTime( 23, 59, 59 );

				break;
		}

		return array(
			'start' => $first_day,
			'end'   => $last_day
		);
	}
}

WC_GC_Tracker::init();
