<?php

/**
 * WpBean settings API class
 *
 * @author WpBean
 */

if ( !class_exists('WPB_WIZ_Plugin_Settings' ) ):
class WPB_WIZ_Plugin_Settings {

    private $settings_api;

    function __construct() {
        $this->settings_api = new WPB_WIZ_WeDevs_Settings_API;

        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'admin_menu', array($this, 'admin_menu') );
    }

    function admin_init() {

        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );

        //initialize settings
        $this->settings_api->admin_init();
    }

    function admin_menu() {
        add_options_page( esc_html__( 'WooCommerce Image Zoom Settings', 'woocommerce-image-zoom' ), esc_html__( 'Woo Zoom Settings', 'woocommerce-image-zoom' ), 'delete_posts', 'wpb_woocommerce_image_zoom_settings', array($this, 'plugin_page') );
    }

    function get_settings_sections() {
        $sections = array(
            array(
                'id'    => 'wpb_general_settings',
                'title' => esc_html__( 'General Settings', 'woocommerce-image-zoom' )
            )
        );
        return $sections;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function get_settings_fields() {
        $settings_fields = array(
            'wpb_general_settings' => array(
                array(
                    'name'  => 'wpb_wiz_disable_zoom_mobile',
                    'label' => esc_html__( 'Disable Zooming in Mobile', 'woocommerce-image-zoom' ),
                    'desc'  => esc_html__( 'Yes Please!', 'woocommerce-image-zoom' ),
                    'type'  => 'checkbox',
                ),
                array(
                    'name'  => 'wpb_wiz_gallery_style',
                    'label' => esc_html__( 'Add Gallery Columns Style', 'woocommerce-image-zoom' ),
                    'desc'  => esc_html__( 'Yes Please!', 'woocommerce-image-zoom' ),
                    'type'  => 'checkbox',
                ),
                array(
                    'name'  => 'wpb_wiz_thumb_to_gallery',
                    'label' => esc_html__( 'Add Featured Image to the Gallery', 'woocommerce-image-zoom' ),
                    'desc'  => esc_html__( 'Yes Please!', 'woocommerce-image-zoom' ),
                    'type'  => 'checkbox',
                    'default' => 'on'
                )
            ),
        );

        return $settings_fields;
    }

    function plugin_page() {
        echo '<div class="wrap">';

        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();

        ?>
            <div class="wpb-wiz-pro">
                <h3>Enjoying the Product Image Zoom plugin? Upgrade to the premium version and get a 10% exclusive discount. Use promo code- <b style="color: #dc3232;">10PERCENTOFF</b></h3>

                <h4>Premium Version Features:</h4>
                <ul class="fancy-list">
                    <li><span class="dashicons dashicons-yes-alt"></span> Three different types of zoom.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Product variation images zoom.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> The Product gallery images LightBox popup slider with zoom.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Product gallery images slider ( Updated ). See the <a href="http://demo2.wpbean.com/?product=flying-ninja">demo</a> for details.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Exclude Zoom for specific products and specific categories.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Changeable zoom window and zoom lens size.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> 2 different types of zoom lenses.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Smooth zooming with Easing effect and FadeIn &amp; FadeOut Speed.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Advance setting panel with all necessary settings.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Lifetime free update and free support.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Video Documentation.</li>
                    <li><span class="dashicons dashicons-yes-alt"></span> Free installation and theme customization ( if needed ).</li>
                </ul>
                <div class="wpb-wiz-buttons">
                    <a href="https://wpbean.com/downloads/woocommerce-image-zoom-pro/" target="_blank" class="button wpb-wiz-button-primary">Get the Premium Version</a>
                    <a href="http://demo2.wpbean.com/?product=flying-ninja" target="_blank" class="button wpb-wiz-button-secondary">See the Demo</a>
                </div>
            </div>
            <style type="text/css">
                .wpb-wiz-pro {
                    background: #fff; 
                    padding: 30px; 
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                }
                .wpb-wiz-pro h3 {
                    font-size: 20px; 
                    margin-bottom: 40px;
                }
                .wpb-wiz-pro h4 {    
                    font-size: 17px;
                    margin-bottom: 24px;
                }
                .wpb-wiz-pro li {
                    margin-bottom: 20px;
                    font-size: 15px;
                }
                .wpb-wiz-pro li span{
                    color: #27ae60;
                }
                .wpb-wiz-pro .wpb-wiz-buttons {
                    margin-top: 30px;
                }
                .wpb-wiz-pro .button {
                    padding: 10px 18px;
                    margin-right: 8px;
                    font-size: 16px;
                    font-weight: bold;
                    -webkit-font-smoothing: antialiased;
                    border: 0;
                    color: #fff;
                    transition: all .3s linear;
                }
                .rtl .wpb-wiz-pro .button {
                    margin-right: 0;
                    margin-left: 8px;
                }
                .wpb-wiz-pro .button:hover, .wpb-wiz-pro .button:focus {
                    color: #fff;
                }
                .wp-core-ui .button.wpb-wiz-button-primary {
                    background: #e74c3c;
                }
                .wp-core-ui .button.wpb-wiz-button-secondary {
                    background: #95a5a6;
                }
                .wp-core-ui .button.wpb-wiz-button-primary:hover, .wp-core-ui .button.wpb-wiz-button-primary:focus{
                    background: #c0392b;
                }
                .wp-core-ui .button.wpb-wiz-button-secondary:hover, .wp-core-ui .button.wpb-wiz-button-secondary:focus {
                    background: #7f8c8d;
                }
            </style>
        <?php

        echo '</div>';
    }

    /**
     * Get all the pages
     *
     * @return array page names with key value pairs
     */
    function get_pages() {
        $pages = get_pages();
        $pages_options = array();
        if ( $pages ) {
            foreach ($pages as $page) {
                $pages_options[$page->ID] = $page->post_title;
            }
        }

        return $pages_options;
    }

}
endif;

new WPB_WIZ_Plugin_Settings();


/**
 * Getting the setting options
 */

if ( ! function_exists('wpb_wiz_get_option') ) {

    function wpb_wiz_get_option( $option, $section, $default = '' ) {
     
        $options = get_option( $section );
     
        if ( isset( $options[$option] ) ) {
            return $options[$option];
        }
     
        return $default;
    }

}