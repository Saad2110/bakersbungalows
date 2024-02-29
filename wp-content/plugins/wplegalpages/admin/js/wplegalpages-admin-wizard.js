/**
 * Admin Wizard JavaScript.
 *
 * @package    Wplegalpages
 * @subpackage Wplegalpages/admin
 * @author     wpeka <https://club.wpeka.com>
 */

(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	$( document ).ready(
		function() {
			$( '#tps_advertising' ).select2(
				{
					multiple: true,
					width: '100%',
					allowClear: true
				}
			);
			$( '#tps_analytics' ).select2(
				{
					multiple: true,
					width: '100%',
					allowClear: true
				}
			);
			$( '[data-toggle="popover"]' ).popover(
				{
					container: 'body',
					title: 'Do you need help?<span class="dashicons dashicons-no-alt"></span>',
					html: 'true',
					trigger: 'hover',
				}
			);

			$( document ).on(
				'click',
				'.field-radio-selector',
				function(){
					var parent = $( this ).parent();
					var child  = parent.children( '.subfield' );
					child.show();
					var subfield = parent.siblings().children( '.subfield' );
					subfield.hide();
				}
			);

			$( document ).on(
				'click',
				'.field-checkbox-selector',
				function(){
					var parent = $( this ).parent();
					var child  = parent.children( '.subfield' );
					if ($( this ).is( ":checked" )) {
						child.show();
					} else {
						child.hide()
					}
				}
			);

			$( document ).on(
				'click',
				'.section-label.collapsible',
				function(){
					if ($( this ).next( '.section' ).hasClass( 'collapsible-hide' )) {
						$( this ).next( '.section' ).removeClass( 'collapsible-hide' );
						$( this ).next( '.section' ).addClass( 'collapsible-show' );
						$( this ).children( '.dashicons' ).removeClass( 'dashicons-arrow-down-alt2' );
						$( this ).children( '.dashicons' ).addClass( 'dashicons-arrow-up-alt2' );
					} else if ($( this ).next( '.section' ).hasClass( 'collapsible-show' )) {
						$( this ).next( '.section' ).removeClass( 'collapsible-show' );
						$( this ).next( '.section' ).addClass( 'collapsible-hide' );
						$( this ).children( '.dashicons' ).removeClass( 'dashicons-arrow-up-alt2' );
						$( this ).children( '.dashicons' ).addClass( 'dashicons-arrow-down-alt2' );
					}
				}
			);

			$( document ).on(
				'click',
				'.clause.collapsible',
				function(){
					var parent           = $( this ).parent( '.section-clause' );
					var settings_section = parent.children( '.section-clause-settings' );
					var dashicon         = parent.find( '.dashicons' );
					var clause_checkbox  = parent.children( 'input[type="checkbox"]' );
					if (settings_section.hasClass( 'collapsible-hide' )) {
						settings_section.removeClass( 'collapsible-hide' );
						if ( ! clause_checkbox.prop( "checked" )) {
							clause_checkbox.prop( "checked", true );
						}
						settings_section.addClass( 'collapsible-show' );
						dashicon.removeClass( 'dashicons-arrow-down-alt2' );
						dashicon.addClass( 'dashicons-arrow-up-alt2' );
					} else if (settings_section.hasClass( 'collapsible-show' )) {
						settings_section.removeClass( 'collapsible-show' );
						settings_section.addClass( 'collapsible-hide' );
						dashicon.removeClass( 'dashicons-arrow-up-alt2' );
						dashicon.addClass( 'dashicons-arrow-down-alt2' );
					}
				}
			);

			$( document ).on(
				'click',
				'.section-clause > input[type="checkbox"]',
				function(){
					var parent   = $( this ).parent( '.section-clause' );
					var child    = parent.children( '.section-clause-settings' );
					var dashicon = parent.find( '.dashicons' );
					if ($( this ).is( ":checked" )) {
						child.removeClass( 'collapsible-hide' );
						child.addClass( 'collapsible-show' );
						dashicon.removeClass( 'dashicons-arrow-down-alt2' );
						dashicon.addClass( 'dashicons-arrow-up-alt2' );
					} else {
						child.removeClass( 'collapsible-show' );
						child.addClass( 'collapsible-hide' );
						dashicon.removeClass( 'dashicons-arrow-up-alt2' );
						dashicon.addClass( 'dashicons-arrow-down-alt2' );
					}
				}
			);
		}
	);

})( jQuery );
