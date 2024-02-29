<?php
/**
 * WC_GC_Core_Compatibility class
 *
 * @package  WooCommerce Gift Cards
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Functions related to core back-compatibility.
 *
 * @class    WC_GC_Core_Compatibility
 * @version  1.15.0
 */
class WC_GC_Core_Compatibility {

	/**
	 * Cache 'gte' comparison results.
	 *
	 * @var array
	 */
	private static $is_wc_version_gte = array();

	/**
	 * Cache 'gt' comparison results.
	 *
	 * @var array
	 */
	private static $is_wc_version_gt = array();

	/**
	 * Cache 'gt' comparison results for WP version.
	 *
	 * @var array
	 */
	private static $is_wp_version_gt = array();

	/**
	 * Cache 'gte' comparison results for WP version.
	 *
	 * @var array
	 */
	private static $is_wp_version_gte = array();

	/**
	 * Cache wc admin status result.
	 *
	 * @var bool
	 */
	private static $is_wc_admin_enabled = null;

	/**
	 * Current REST request.
	 *
	 * @since  1.2.0
	 *
	 * @var WP_REST_Request
	 */
	private static $request;

	/**
	 * Cache HPOS status.
	 *
	 * @since  1.15.0
	 *
	 * @var bool
	 */
	private static $is_hpos_enabled = null;

	/**
	 * Initialization and hooks.
	 */
	public static function init() {
		// Save current rest request. Is there a better way to get it?
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'save_rest_request' ), 10, 3 );
	}

	/*
	|--------------------------------------------------------------------------
	| Callbacks.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Saves the current rest request.
	 *
	 * @since  1.2.0
	 *
	 * @param  mixed            $result
	 * @param  WP_REST_Server   $server
	 * @param  WP_REST_Request  $request
	 * @return mixed
	 */
	public static function save_rest_request( $result, $server, $request ) {
		self::$request = $request;
		return $result;
	}

	/*
	|--------------------------------------------------------------------------
	| WC version handling.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Helper method to get the version of the currently installed WooCommerce.
	 *
	 * @return string
	 */
	public static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	/**
	 * Helper method to get the class string for all versions.
	 *
	 * @return string
	 */
	public static function get_versions_class() {

		$classes = array();

		if ( self::is_wc_version_gte( '3.3' ) ) {
			$classes[] = 'wc_gte_33';
		}
		if ( self::is_wc_version_gte( '3.4' ) ) {
			$classes[] = 'wc_gte_34';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than or equal to $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gte( $version ) {
		if ( ! isset( self::$is_wc_version_gte[ $version ] ) ) {
			self::$is_wc_version_gte[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>=' );
		}
		return self::$is_wc_version_gte[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gt( $version ) {
		if ( ! isset( self::$is_wc_version_gt[ $version ] ) ) {
			self::$is_wc_version_gt[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>' );
		}
		return self::$is_wc_version_gt[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is lower than or equal $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_lte( $version ) {
		if ( ! isset( self::$is_wc_version_gt[ $version ] ) ) {
			self::$is_wc_version_gt[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '<=' );
		}
		return self::$is_wc_version_gt[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is lower than $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_lt( $version ) {
		if ( ! isset( self::$is_wc_version_gt[ $version ] ) ) {
			self::$is_wc_version_gt[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '<' );
		}
		return self::$is_wc_version_gt[ $version ];
	}

	/*
	|--------------------------------------------------------------------------
	| WP version handling.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns true if the installed version of WooCommerce is greater than or equal to $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wp_version_gt( $version ) {
		if ( ! isset( self::$is_wp_version_gt[ $version ] ) ) {
			global $wp_version;
			self::$is_wp_version_gt[ $version ] = $wp_version && version_compare( WC_GC()->get_plugin_version( true, $wp_version ), $version, '>' );
		}
		return self::$is_wp_version_gt[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than or equal to $version.
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wp_version_gte( $version ) {
		if ( ! isset( self::$is_wp_version_gte[ $version ] ) ) {
			global $wp_version;
			self::$is_wp_version_gte[ $version ] = $wp_version && version_compare( WC_GC()->get_plugin_version( true, $wp_version ), $version, '>=' );
		}
		return self::$is_wp_version_gte[ $version ];
	}

	/**
	 * Returns true if the WC Admin feature is installed and enabled.
	 *
	 * @return boolean
	 */
	public static function is_wc_admin_enabled() {

		if ( ! isset( self::$is_wc_admin_enabled ) ) {
			$enabled = self::is_wc_version_gte( '4.0' ) && defined( 'WC_ADMIN_VERSION_NUMBER' ) && version_compare( WC_ADMIN_VERSION_NUMBER, '1.0.0', '>=' );
			if ( $enabled && version_compare( WC_ADMIN_VERSION_NUMBER, '2.3.0', '>=' ) && true === apply_filters( 'woocommerce_admin_disabled', false ) ) {
				$enabled = false;
			}

			self::$is_wc_admin_enabled = $enabled;
		}

		return self::$is_wc_admin_enabled;
	}

	/**
	 * Returns true if is a react based admin page.
	 *
	 * @since 1.10.3
	 *
	 * @return boolean
	 */
	public static function is_admin_or_embed_page() {

		if ( class_exists( '\Automattic\WooCommerce\Admin\PageController' ) && method_exists( '\Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page' ) ) {

			return \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page();

		} elseif ( class_exists( '\Automattic\WooCommerce\Admin\Loader' ) && method_exists( '\Automattic\WooCommerce\Admin\Loader', 'is_admin_or_embed_page' ) ) {

			return \Automattic\WooCommerce\Admin\Loader::is_admin_or_embed_page();
		}

		return false;
	}

	/**
	 * Compatibility wrapper for invalidating cache groups.
	 *
	 * @param  string  $group
	 * @return void
	 */
	public static function invalidate_cache_group( $group ) {
		if ( self::is_wc_version_gte( '3.9' ) ) {
			WC_Cache_Helper::invalidate_cache_group( $group );
		} else {
			WC_Cache_Helper::incr_cache_prefix( $group );
		}
	}

	/**
	 * Compatibility wrapper for scheduling single actions.
	 *
	 * @since 1.3.2
	 *
	 * @param  string  $group
	 * @return void
	 */
	public static function schedule_single_action( $timestamp, $hook, $args = array(), $group = '' ) {
		if ( self::is_wc_version_gte( '3.5' ) ) {
			return WC()->queue()->schedule_single( $timestamp, $hook, $args, $group );
		} else {
			return wp_schedule_single_event( $timestamp, $hook, $args );
		}
	}

	/**
	 * Compatibility wrapper for unscheduling actions.
	 *
	 * @since 1.3.2
	 *
	 * @param  string  $group
	 * @return void
	 */
	public static function unschedule_action( $hook, $args = array(), $group = '' ) {
		if ( self::is_wc_version_gte( '3.5' ) ) {
			return WC()->queue()->cancel( $hook, $args, $group );
		} else {
			return wp_clear_scheduled_hook( $hook, $args );
		}
	}

	/**
	 * Compatibility wrapper for getting the date and time for the next scheduled occurence of an action with a given hook.
	 *
	 * @since 1.3.2
	 *
	 * @param  string  $group
	 * @return void
	 */
	public static function next_scheduled_action( $hook, $args = null, $group = '' ) {
		if ( self::is_wc_version_gte( '3.5' ) ) {
			return WC()->queue()->get_next( $hook, $args, $group );
		} else {
			return wp_next_scheduled( $hook, $args );
		}
	}

	/**
	 * Compatibility wrapper for getting the date of a AS action.
	 *
	 * @since 1.5.2
	 *
	 * @param  ActionScheduler_Action  $action
	 * @return DateTime
	 */
	public static function get_schedule_date( $action ) {

		if ( self::is_wc_version_gte( '4.0' ) ) {
			return $action->get_schedule()->get_date();
		} else {
			return $action->get_schedule()->next();
		}
	}

	/**
	 * Compatibility wrapper wp_date.
	 *
	 * @since 1.10.3
	 *
	 * @param  string       $format
	 * @param  int          $timestamp
	 * @param  DateTimeZone $timezone
	 * @return string|false
	 */
	public static function wp_date( $format, $timestamp = null, $timezone = null ) {

		if ( self::is_wp_version_gte( '5.3.0' ) ) {
			$date = wp_date( $format, $timestamp, $timezone );
		} else {
			global $wp_locale;

			if ( null === $timestamp ) {
				$timestamp = time();
			} elseif ( ! is_numeric( $timestamp ) ) {
				return false;
			}

			if ( ! $timezone ) {
				$timezone = wp_timezone();
			}

			$datetime = date_create( '@' . $timestamp );
			$datetime->setTimezone( $timezone );

			if ( empty( $wp_locale->month ) || empty( $wp_locale->weekday ) ) {
				$date = $datetime->format( $format );
			} else {
				// We need to unpack shorthand `r` format because it has parts that might be localized.
				$format = preg_replace( '/(?<!\\\\)r/', DATE_RFC2822, $format );

				$new_format    = '';
				$format_length = strlen( $format );
				$month         = $wp_locale->get_month( $datetime->format( 'm' ) );
				$weekday       = $wp_locale->get_weekday( $datetime->format( 'w' ) );

				for ( $i = 0; $i < $format_length; $i ++ ) {
					switch ( $format[ $i ] ) {
						case 'D':
							$new_format .= addcslashes( $wp_locale->get_weekday_abbrev( $weekday ), '\\A..Za..z' );
							break;
						case 'F':
							$new_format .= addcslashes( $month, '\\A..Za..z' );
							break;
						case 'l':
							$new_format .= addcslashes( $weekday, '\\A..Za..z' );
							break;
						case 'M':
							$new_format .= addcslashes( $wp_locale->get_month_abbrev( $month ), '\\A..Za..z' );
							break;
						case 'a':
							$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'a' ) ), '\\A..Za..z' );
							break;
						case 'A':
							$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'A' ) ), '\\A..Za..z' );
							break;
						case '\\':
							$new_format .= $format[ $i ];

							// If character follows a slash, we add it without translating.
							if ( $i < $format_length ) {
								$new_format .= $format[ ++$i ];
							}
							break;
						default:
							$new_format .= $format[ $i ];
							break;
					}
				}

				$date = $datetime->format( $new_format );
				$date = wp_maybe_decline_date( $date, $format );
			}
		}

		return $date;
	}

	/**
	 * Converts an error to a response object.
	 *
	 * This iterates over all error codes and messages to change it into a flat
	 * array. This enables simpler client behaviour, as it is represented as a
	 * list in JSON rather than an object/map.
	 *
	 * @since 1.8.0
	 *
	 * @param  WP_Error  $error WP_Error instance.
	 * @return WP_REST_Response List of associative arrays with code and message keys.
	 */
	public static function rest_convert_error_to_response( $error ) {

		if ( self::is_wp_version_gte( '5.7' ) ) {
			return rest_convert_error_to_response( $error );
		}

		/*
		 * The following code is a copy paste of `error_to_response`.
		 * https://github.com/WordPress/WordPress/blob/5.5-branch/wp-includes/rest-api/class-wp-rest-server.php#L158
		 *
		 * `rest_convert_error_to_response` became a wrapper of `error_to_response` in WP 5.6 and then in 5.7 a standalone function.
		 */
		$error_data = $error->get_error_data();

		if ( is_array( $error_data ) && isset( $error_data[ 'status' ] ) ) {
			$status = $error_data[ 'status' ];
		} else {
			$status = 500;
		}

		$errors = array();

		foreach ( (array) $error->errors as $code => $messages ) {
			foreach ( (array) $messages as $message ) {
				$errors[] = array(
					'code'    => $code,
					'message' => $message,
					'data'    => $error->get_error_data( $code ),
				);
			}
		}

		$data = $errors[ 0 ];
		if ( count( $errors ) > 1 ) {
			// Remove the primary error.
			array_shift( $errors );
			$data[ 'additional_errors' ] = $errors;
		}

		$response = new WP_REST_Response( $data, $status );

		return $response;
	}

	/**
	 * Back-compat wrapper for 'is_rest_api_request'.
	 *
	 * @since  1.2.0
	 *
	 * @return boolean
	 */
	public static function is_rest_api_request() {

		if ( false !== self::get_api_request() ) {
			return true;
		}

		return method_exists( WC(), 'is_rest_api_request' ) ? WC()->is_rest_api_request() : defined( 'REST_REQUEST' );
	}

	/*
	|--------------------------------------------------------------------------
	| Utilities.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether this is a Store/REST API request.
	 *
	 * @since  1.2.0
	 *
	 * @return boolean
	 */
	public static function is_api_request() {
		return self::is_store_api_request() || self::is_rest_api_request();
	}

	/**
	 * Returns the current Store/REST API request or false.
	 *
	 * @since  1.2.0
	 *
	 * @return WP_REST_Request|false
	 */
	public static function get_api_request() {
		return self::$request instanceof WP_REST_Request ? self::$request : false;
	}

	/**
	 * Whether this is a Store API request.
	 *
	 * @since  1.2.0
	 *
	 * @param  string  $route
	 * @return boolean
	 */
	public static function is_store_api_request( $route = '', $method = '' ) {

		$request = self::get_api_request();

		if ( false !== $request && strpos( $request->get_route(), 'wc/store' ) !== false ) {

			$check_route  = ! empty( $route );
			$check_method = ! empty( $method );

			if ( ! $check_route && ! $check_method ) {
				// Generic store api question.
				return true;
			}

			$route_result  = ! $check_route || strpos( $request->get_route(), $route ) !== false;
			$method_result = ! $check_method || strtolower( $request->get_method() ) === strtolower( $method );

			return $route_result && $method_result;
		}

		return false;
	}

	/**
	 * Check if the usage of the custom orders table is enabled.
	 *
	 * @since  1.15.0
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled() {

		if ( ! isset( self::$is_hpos_enabled ) ) {
			self::$is_hpos_enabled = class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return self::$is_hpos_enabled;
	}
}

WC_GC_Core_Compatibility::init();
