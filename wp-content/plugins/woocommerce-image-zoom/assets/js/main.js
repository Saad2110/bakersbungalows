(function($) { 
  'use strict';

  	var image = $('.woocommerce-product-gallery__image').find('.wp-post-image');

	/**
	 * Remove srcset & size attr
	 */
	
	$("#wpb_wiz_gallery a").on("click", function(){ 
		$('.wpb-wiz-woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image > a img').removeAttr('srcset');
		$('.wpb-wiz-woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image > a img').removeAttr('sizes');
	});


	/**
	 * Init Zoom
	 */
	
	$(image).ezPlus({
        gallery: 'wpb_wiz_gallery',
        cursor: 'pointer',
        galleryActiveClass: "active",
        imageCrossfade: true,
        loadingIcon: wpb_wiz_free.loading_icon,
        zoomType: 'inner',
        responsive: true,
        scrollZoom: false,
        zoomWindowWidth: 400,
        zoomWindowHeight: 400,
        zoomWindowOffsetY: -8,
        zoomWindowOffsetX: 30,
        easing: true,
        zoomWindowFadeIn: true,
        zoomWindowFadeOut: true,
        borderSize: 0,
    });

    $(image).bind("click", function (e) {
        var ez = $(image).data('ezPlus');
        ez.closeAll(); //NEW: This function force hides the lens, tint and window
        $.fancybox.open(ez.getGalleryListFancyboxThree());
        return false;
    });


	/**
	 * Zoom image change with variation
	 */
  	
  	$(document).on( 'found_variation', 'form.variations_form', function( event, variation ) {
  		var ez 	= image.data('ezPlus');
		if ( variation && variation.image && variation.image.src && variation.image.src.length > 1 ) {
			ez.swaptheimage( variation.image.src, variation.image.full_src );
		}
	});


  	/**
  	 * Back to the main image for zoom after reseting the variation
  	 */
  	
	$(document).on( 'click', '.reset_variations', function( event ) {
		var featureImage      = $(".wpb-wiz-woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image > a img").attr("src"),
			featureImageLarge = $(".wpb-wiz-woocommerce-product-gallery__wrapper > .woocommerce-product-gallery__image > a").attr("href");
			
		var ez 	= image.data('ezPlus');
		ez.swaptheimage( featureImage, featureImageLarge );
	});
	
})(jQuery);