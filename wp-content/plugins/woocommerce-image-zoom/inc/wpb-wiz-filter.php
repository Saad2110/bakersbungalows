<?php 

/**
 * Woocommerce Image Zoom
 * By WPbean
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Custom array unshift function 
 */

if( !function_exists('wpb_wiz_unshift') ){
	function wpb_wiz_unshift($array, $var) {
	  array_unshift($array, $var);
	  return $array;
	}
}


/**
 * Apply the modifired images area for Zoom plugin
 */


function wpb_wiz_apply_modified_images() {

	global $woocommerce;

	if ( $woocommerce->version >= '3.0' ){
		remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
		add_action( 'woocommerce_before_single_product_summary', 'wpb_wiz_modified_single_images', 20 );

		// electro theme support
		remove_action( 'woocommerce_before_single_product_summary', 'electro_show_product_images', 20 );
	}else{
		add_filter( 'woocommerce_single_product_image_html', 'wpb_aiz_get_single_images' );
	}
}
add_action('template_redirect', 'wpb_wiz_apply_modified_images', 20);


/**
 * Modified product images for Woo 3.0
 *
 * File Location : templates/product-image.php
 *
 * This is the core WooCommerce 3.0 new product image template file, we are modifying this template for adding the zoom
 */

function wpb_wiz_modified_single_images(){
	$templates = new WPB_WIZ_Template_Loader;
	$templates->get_template_part( 'product', 'image' );
}

/**
 * Output the product thumbnails ( Woo 3.0 ).
 *
 * @subpackage	Product
 */

function wpb_wiz_woocommerce_show_product_thumbnails() {
	$templates = new WPB_WIZ_Template_Loader;
	$templates->get_template_part( 'product', 'thumbnails' );
}


/**
 * Get Modified images for zoom
 */

function wpb_aiz_get_single_images(){
	global $post, $woocommerce, $product;

	if( has_post_thumbnail() ){

		$image_title = esc_attr( get_the_title( get_post_thumbnail_id() ) );
		$image_link  = wp_get_attachment_url( get_post_thumbnail_id() );
		$image       = get_the_post_thumbnail( $post->ID, apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ), array(
			'title' 			=> $image_title,
			'data-zoom-image' 	=> $image_link,
			'class' 			=> 'wpb-wiz-main-image',
			) );

		if ( $woocommerce->version >= '3.0' ){
			$attachment_count = count( $product->get_gallery_image_ids() );
		}else{
			$attachment_count = count( $product->get_gallery_attachment_ids() );
		}

		if ( $attachment_count > 0 ) {
			$gallery = '[product-gallery]';
		} else {
			$gallery = '';
		}

		if ( $woocommerce->version >= '3.0' ){
			return sprintf( '<div class="images"><a href="%s" itemprop="image" class="woocommerce-main-image zoom" title="%s" data-rel="prettyPhoto' . $gallery . '">%s</a>%s</div>', $image_link, $image_title, $image, wpb_wiz_product_gallery_images() );

		}else{
			return sprintf( '<a href="%s" itemprop="image" class="woocommerce-main-image zoom" title="%s" data-rel="prettyPhoto' . $gallery . '">%s</a>', $image_link, $image_title, $image );
		}
	
	} else {

		if ( $woocommerce->version >= '3.0' ){
			return sprintf( '<div class="images"><img src="%s" alt="%s" /></div>', wc_placeholder_img_src(), esc_html__( 'Placeholder', 'woocommerce-image-zoom' ) );
		}else{
			return sprintf( '<img src="%s" alt="%s" />', wc_placeholder_img_src(), esc_html__( 'Placeholder', 'woocommerce-image-zoom' ) );
		}

	}
}


/**
 * echo Get Modified images for zoom
 */

function wpb_aiz_single_images(){
	echo wpb_aiz_get_single_images();
}


/**
 * Product gallery images
 */

function wpb_wiz_product_gallery_images(){
	global $woocommerce, $product, $post;

	if ( $woocommerce->version >= '3.0' ){
		$attachments = $product->get_gallery_image_ids();
	}else{
		$attachments = $product->get_gallery_attachment_ids();
	}

	if ( $attachments ) {
		$loop 		= 0;
		$columns 	= apply_filters( 'woocommerce_product_thumbnails_columns', 3 );
	}

	$add_thumb_to_gallery 	= wpb_wiz_get_option( 'wpb_wiz_thumb_to_gallery', 'wpb_general_settings', 'on' );
	$gallery_style 			= wpb_wiz_get_option( 'wpb_wiz_gallery_style', 'wpb_general_settings', 'off' );

	if ( isset($attachments) && !empty($attachments) ){

		if( $add_thumb_to_gallery == 'on' && has_post_thumbnail( get_the_id() ) ){
			array_unshift($attachments, get_post_thumbnail_id());
		}
		
		ob_start();
		?>
			<div id="wpb_wiz_gallery" class="wpb_wiz_gallery_style_<?php echo esc_attr( $gallery_style ); ?> thumbnails <?php echo esc_attr( 'columns-' . $columns ) ; ?>">

				<?php 

		    		foreach ( $attachments as $attachment ) {

		    			$classes = array( 'wpb-woo-zoom' );
		    			$classes = apply_filters('wpb_wim_gallery_image_link_class', $classes);

						if ( $loop == 0 || $loop % $columns == 0 )
							$classes[] = 'first';

						if ( ( $loop + 1 ) % $columns == 0 )
							$classes[] = 'last';

		    			$attachment_resized = wp_get_attachment_image_src( $attachment, apply_filters( 'single_product_large_thumbnail_size', 'shop_single' ) );
		    			$attachment_full = wp_get_attachment_image_src( $attachment, 'full' );
		    			?>
		    				<a class="<?php echo esc_attr( implode( ' ', $classes ) ) ; ?>" href="#" data-image="<?php echo esc_url( $attachment_resized[0] ); ?>" data-zoom-image="<?php echo esc_url( $attachment_full[0] ) ; ?>"> 
		    					<img src="<?php echo esc_url( $attachment_resized[0] ); ?>" /> 
		    				</a> 
		    			<?php

		    			$loop++;
		    		}
			    ?>

			</div>
		<?php

		return ob_get_clean();
	}
}


/**
 * Template loader for Image Zoom PRO
 *
 * Only need to specify class properties here.
 *
 * @package Woo Image Zoom
 * @author  Gary Jones
 */
class WPB_WIZ_Template_Loader extends Gamajo_Template_Loader {
  /**
   * Prefix for filter names.
   *
   * @since 1.0.0
   *
   * @var string
   */
  protected $filter_prefix = 'wpb_wiz';

  /**
   * Directory name where custom templates for this plugin should be found in the theme.
   *
   * @since 1.0.0
   *
   * @var string
   */
  protected $theme_template_directory = 'wpb-wiz-template';

  /**
   * Reference to the root directory path of this plugin.
   *
   * Can either be a defined constant, or a relative reference from where the subclass lives.
   *
   * In this case, `WPB_WIZ_PLUGIN_DIR` would be defined in the root plugin file as:
   *
   * ~~~
   * define( 'WPB_WIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
   * ~~~
   *
   * @since 1.0.0
   *
   * @var string
   */
  protected $plugin_directory = WPB_WIZ_FREE_PLUGIN_DIR;

  /**
   * Directory name where templates are found in this plugin.
   *
   * Can either be a defined constant, or a relative reference from where the subclass lives.
   *
   * e.g. 'templates' or 'includes/templates', etc.
   *
   * @since 1.1.0
   *
   * @var string
   */
  protected $plugin_template_directory = 'templates';
}


/**
 * Disable Default Zoom and LightBox
 */

function wpb_wiz_remove_image_zoom_support() {
	remove_theme_support( 'wc-product-gallery-zoom' );
}
add_action( 'wp', 'wpb_wiz_remove_image_zoom_support', 100 );

function wpb_wiz_after_theme_setup(){
	remove_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'wpb_wiz_after_theme_setup', 99 );


// Add shortcode for layout builder

add_shortcode( 'wpb_wiz_product_image', 'wpb_wiz_product_image_shortcode' );
function wpb_wiz_product_image_shortcode( $atts ){

	extract(shortcode_atts(array(
		'x_class'		=> '',
    ), $atts));

    ob_start();

    if( is_product() ){
    	echo '<div class="wpb-wz-images-shortcode'. esc_attr( $x_class ? ' ' . $x_class : '' ) .'">';
	   		wpb_wiz_modified_single_images();
	    echo '</div>';
    }

	return ob_get_clean();
}