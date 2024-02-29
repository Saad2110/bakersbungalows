<?php
/**
 * Plugin Name:       Product Image Zoom for WooCommerce
 * Plugin URI:        https://wpbean.com/downloads/woocommerce-image-zoom-pro/
 * Description:       Highly customizable product image zoom plugin for Woocommerce Store. 
 * Version:           1.05.4
 * Author:            wpbean
 * Author URI:        https://wpbean.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-image-zoom
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
    require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
}


/**
 * Define constants
 */

if ( ! defined( 'WPB_WIZ_FREE_INIT' ) ) {
    define( 'WPB_WIZ_FREE_INIT', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'WPB_WIZ_FREE_PLUGIN_DIR' ) ) {
    define( 'WPB_WIZ_FREE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * This version can't be activate if premium version is active
 */

if ( defined( 'WPB_WIZ_PLUGIN_DIR' ) ) {
    function wpb_wiz_install_free_admin_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e( 'You can\'t activate the free version of Product Image Zoom for WooCommerce while you are using the premium one.', 'woocommerce-image-zoom' ); ?></p>
        </div>
    <?php
    }

    add_action( 'admin_notices', 'wpb_wiz_install_free_admin_notice' );
    deactivate_plugins( plugin_basename( __FILE__ ) );
    return;
}


/**
 * Add plugin action links
 */

function wpb_wiz_free_plugin_actions_links( $links ) {

	$links[] = '<a href="https://wpbean.com/support/" target="_blank">'. esc_html__('Support', 'woocommerce-image-zoom') .'</a>';
	$links[] = '<a href="options-general.php?page=wpb_woocommerce_image_zoom_settings">'. esc_html__('Settings', 'woocommerce-image-zoom') .'</a>';

	return $links;
}



/**
 * Enqueue Scripts
 */

if( !function_exists('wpb_wiz_adding_scripts') ){
	function wpb_wiz_adding_scripts() {
		if( is_singular( 'product' ) ){
			wp_enqueue_style( 'wpb-wiz-fancybox-css',  plugins_url( '/assets/css/jquery.fancybox.min.css', __FILE__ ), array(), '3.0' );
			wp_enqueue_style( 'wpb-wiz-main-css',  plugins_url( '/assets/css/main.css', __FILE__ ), array(), '1.0' );
			wp_enqueue_script('jquery');
			wp_enqueue_script('wpb-wiz-fancybox', plugins_url( '/assets/js/jquery.fancybox.min.js', __FILE__ ), array('jquery'), '3.0', false);
			wp_enqueue_script('wpb-wiz-elevatezoom', plugins_url('assets/js/jquery.ez-plus.js', __FILE__), array('jquery'), '3.0.8', false);
			wp_enqueue_script('wpb-wiz-plugin-main', plugins_url('assets/js/main.js', __FILE__), array('jquery'), '1.0', true);
			wp_localize_script('wpb-wiz-plugin-main', 'wpb_wiz_free', array( 
	            'loading_icon'  => plugins_url( '/assets/images/spinner.gif', __FILE__ ),
	        ));
		}
	}
}



/**
 * Disable zoom in Mobile
 */

if( !function_exists('wpb_wiz_disable_in_mobile') ){
	function wpb_wiz_disable_in_mobile(){
		if( wp_is_mobile() && wpb_wiz_get_option( 'wpb_wiz_disable_zoom_mobile', 'wpb_general_settings', 'off' ) == 'on' ){
			remove_action( 'wp_enqueue_scripts', 'wpb_wiz_adding_scripts' );
		}
	}
}


/**
 * Woocommerce not installed error message
 */

if( !function_exists('wpb_wiz_free_admin_notice')){
	function wpb_wiz_free_admin_notice() {
	    ?>
	    <div class="error">
	    	<p>
	    		<?php printf( '<strong>%s</strong> %s <strong>%s</strong> %s <strong>%s</strong> %s', esc_html( 'Product Image Zoom for WooCommerce', 'woocommerce-image-zoom' ), esc_html( 'plugin need', 'woocommerce-image-zoom' ), esc_html( 'WooCommerce', 'woocommerce-image-zoom' ), esc_html( 'plugin installed. Please install &amp; active the', 'woocommerce-image-zoom' ), esc_html( 'WooCommerce', 'woocommerce-image-zoom' ), esc_html( 'plugin.', 'woocommerce-image-zoom' ) ); ?>
	    	</p>
	    </div>
	    <?php
	}
}


/**
 * Pro version discount
 */

function wpb_wiz_pro_discount_admin_notice() {
    $user_id = get_current_user_id();
    if ( !get_user_meta( $user_id, 'wpb_wiz_pro_discount_dismissed' ) ){
        printf('<div class="wpb-wiz-discount-notice updated" style="padding: 30px 20px;border-left-color: #27ae60;border-left-width: 5px;margin-top: 20px;"><p style="font-size: 18px;line-height: 32px">%s <a target="_blank" href="%s">%s</a>! %s <b>%s</b></p><a href="%s">%s</a></div>', esc_html__( 'Get a 10% exclusive discount on the premium version of the', 'woocommerce-image-zoom' ), 'https://wpbean.com/downloads/woocommerce-image-zoom-pro/', esc_html__( 'Product Image Zoom for WooCommerce', 'woocommerce-image-zoom' ), esc_html__( 'Use discount code - ', 'woocommerce-image-zoom' ), '10PERCENTOFF', esc_url( add_query_arg( 'wpb-wiz-pro-discount-admin-notice-dismissed', 'true' ) ), esc_html__( 'Dismiss', 'woocommerce-image-zoom' ));
    }
}


function wpb_wiz_pro_discount_admin_notice_dismissed() {
    $user_id = get_current_user_id();
    if ( isset( $_GET['wpb-wiz-pro-discount-admin-notice-dismissed'] ) ){
        add_user_meta( $user_id, 'wpb_wiz_pro_discount_dismissed', 'true', true );
    }
}

/**
 * Plugin Deactivation
 */

function wpb_wiz_lite_plugin_deactivation() {
  $user_id = get_current_user_id();
  if ( get_user_meta( $user_id, 'wpb_wiz_pro_discount_dismissed' ) ){
  	delete_user_meta( $user_id, 'wpb_wiz_pro_discount_dismissed' );
  }
}


/**
 * Plugin Init
 */

function wpb_wiz_free_init(){
	load_plugin_textdomain( 'woocommerce-image-zoom', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	register_deactivation_hook( plugin_basename( __FILE__ ), 'wpb_wiz_lite_plugin_deactivation' );
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wpb_wiz_free_plugin_actions_links' );
	add_action( 'admin_notices', 'wpb_wiz_pro_discount_admin_notice' );
	add_action( 'admin_init', 'wpb_wiz_pro_discount_admin_notice_dismissed' );

	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {

		add_action( 'wp_enqueue_scripts', 'wpb_wiz_adding_scripts' );
		add_action( 'init', 'wpb_wiz_disable_in_mobile' );

		/**
		 * Require Files
		 */
		if ( ! class_exists( 'Gamajo_Template_Loader' ) ) {
			require_once dirname( __FILE__ ) . '/inc/class-gamajo-template-loader.php';
		}
		require_once dirname( __FILE__ ) . '/inc/wpb-wiz-filter.php';
		require_once dirname( __FILE__ ) . '/admin/class.settings-api.php';
		require_once dirname( __FILE__ ) . '/admin/plugin-settings.php';

	}else{
		add_action( 'admin_notices', 'wpb_wiz_free_admin_notice' );
	}
}
add_action( 'plugins_loaded', 'wpb_wiz_free_init' );


/**
 * jQuery Migrate Helper
 */

if ( ! class_exists( 'jQuery_Migrate_Helper' ) ) {
	include_once __DIR__ . '/inc/jquery-migrate-helper/class-jquery-migrate-helper.php';
	add_action( 'plugins_loaded', array( 'jQuery_Migrate_Helper', 'init_actions' ) );
}