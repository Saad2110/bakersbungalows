<?php
/**
 * Template Functions
 *
 * @package  WooCommerce Gift Cards
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wc_gc_get_activity_description' ) ) {

	function wc_gc_get_activity_description( $activity_data ) {

		$description = $activity_data->get_gc_code();
		$mask        = wc_gc_mask_codes();

		switch ( $activity_data->get_type() ) {
			case 'redeemed':
				/* translators: %1$s: giftcard code */
				$description = sprintf( __( 'Added gift card code %1$s to your account', 'woocommerce-gift-cards' ), $mask ? wc_gc_mask_code( $activity_data->get_gc_code() ) : $activity_data->get_gc_code() );
				break;
			case 'refunded':
				/* translators: %1$s: giftcard code, %2$s: order link */
				$description = sprintf( __( 'Refunded to gift card code %1$s via order %2$s', 'woocommerce-gift-cards' ), $mask ? wc_gc_mask_code( $activity_data->get_gc_code() ) : $activity_data->get_gc_code(), '<a href="' . wc_get_endpoint_url( 'view-order', $activity_data->get_object_id(), wc_get_page_permalink( 'myaccount' ) ) . '">#' . $activity_data->get_object_id() . '</a>' );
				break;
			case 'used':
				/* translators: %1$s: giftcard code, %2$s: order link */
				$description = sprintf( __( 'Used gift card code %1$s to pay for order %2$s', 'woocommerce-gift-cards' ), $mask ? wc_gc_mask_code( $activity_data->get_gc_code() ) : $activity_data->get_gc_code(), '<a href="' . wc_get_endpoint_url( 'view-order', $activity_data->get_object_id(), wc_get_page_permalink( 'myaccount' ) ) . '">#' . $activity_data->get_object_id() . '</a>' );
				break;
			case 'manually_refunded':
				/* translators: %1$s: giftcard code, %2$s: order link */
				$description = sprintf( __( 'Manually refunded to gift card code %1$s via order %2$s', 'woocommerce-gift-cards' ), $mask ? wc_gc_mask_code( $activity_data->get_gc_code() ) : $activity_data->get_gc_code(), '<a href="' . wc_get_endpoint_url( 'view-order', $activity_data->get_object_id(), wc_get_page_permalink( 'myaccount' ) ) . '">#' . $activity_data->get_object_id() . '</a>' );
				break;
			case 'refund_reversed':
				/* translators: %1$s: giftcard code, %2$s: order link */
				$description = sprintf( __( 'Refund to gift card code %1$s reversed via order %2$s', 'woocommerce-gift-cards' ), $mask ? wc_gc_mask_code( $activity_data->get_gc_code() ) : $activity_data->get_gc_code(), '<a href="' . wc_get_endpoint_url( 'view-order', $activity_data->get_object_id(), wc_get_page_permalink( 'myaccount' ) ) . '">#' . $activity_data->get_object_id() . '</a>' );
				break;
		}

		return $description;
	}
}


if ( ! function_exists( 'wc_gc_get_emails_formatted' ) ) {

	function wc_gc_get_emails_formatted( $emails, $show = 2 ) {
		$total    = count( $emails );
		$remaning = $total - $show;

		if ( $total > $show ) {
			$value = implode( ', ', array_slice( $emails, 0, $show ) );
			if ( $remaning > 0 ) {
				$more = '';
				if ( 1 === $remaning ) {
					$more = esc_html__( 'and 1 more', 'woocommerce-gift-cards' );
				} else {
					/* translators: %s: number of emails used */
					$more = _n( 'and %s more', 'and %s others', $remaning, 'woocommerce-gift-cards' );
				}
				$value .= ' ' . sprintf( $more, $remaning );
			}
		} else {
			$value = implode( ', ', $emails );
		}

		return $value;
	}
}

if ( ! function_exists( 'wc_gc_mask_code' ) ) {

	function wc_gc_mask_code( $code ) {
		return 'XXXX-XXXX-XXXX-' . substr( $code, -4 );
	}

}

if ( ! function_exists( 'wc_gc_get_pending_balance_resolution' ) ) {

	/**
	 * Builds the resolution to be shown for pending balances.
	 *
	 * @since  1.6.0
	 *
	 * @param  WC_GC_Gift_Card_Data  $giftcard
	 * @param  string                $context
	 * @return string
	 */
	function wc_gc_get_pending_balance_resolution( $giftcard, $context = 'view' ) {
		$resolution = '';

		if ( $giftcard->is_redeemed() && is_user_logged_in() ) {

			$link = add_query_arg( array( 'wc_gc_show_pending_orders' => 'yes' ), wc_get_account_endpoint_url( 'orders' ) );

			if ( 'notice' === $context ) {

				/* translators: Gift Card balance */
				$notice_text = sprintf( esc_html__( 'Failed to apply gift card. %s on hold in pending orders.', 'woocommerce-gift-cards' ), wc_price( $giftcard->get_pending_balance() ) );

				/* translators: %1$s pay link, %2$s cta text, %3$s notice text html */
				$resolution  = sprintf( '<a href="%1$s" class="button wc-forward">%2$s</a> %s', $link, esc_html__( 'View orders', 'woocommerce-gift-cards' ), $notice_text );

			} else {

				/* translators: %1$s pending balance, %2$s account order link */
				$resolution  = sprintf( '%1$s on hold in <a href="%2$s">pending orders</a>', wc_price( $giftcard->get_pending_balance() ), $link );
			}


		} else {

			$pending_orders = $giftcard->get_pending_balance( true );
			$total_orders   = count( $pending_orders );

			if ( 1 === $total_orders ) {

				$order_id = array_keys( $pending_orders )[ 0 ];
				$order    = wc_get_order( $order_id );

				if ( ! is_a( $order, 'WC_Order' ) ) {
					return __( 'Gift card has funds on hold in pending orders. Please contact us for assistance.', 'woocommerce-gift-cards' );
				}

				/* translators: %1$s pay link, %2$s order id */
				$link = sprintf( '<a href="%s">#%d</a>', add_query_arg( array( 'wc_gc_pay_order_pending_status' => 'notice' ), $order->get_checkout_payment_url() ), $order->get_id() );

				if ( 'notice' === $context ) {

					/* translators: %1$s pending balance, %2$s pay link html */
					$resolution = sprintf( esc_html__( 'Failed to apply gift card. %1$s on hold in order %2$s.', 'woocommerce-gift-cards' ), wc_price( $giftcard->get_pending_balance() ), $link );

				} else {

					/* translators: %1$s pending balance, %2$s pay link html */
					$resolution = sprintf( esc_html__( '%1$s on hold in order %2$s', 'woocommerce-gift-cards' ), wc_price( $giftcard->get_pending_balance() ), $link );
				}

			} else {

				// Build a list of pay pages.
				$pay_links = array();
				$counter   = 0;

				foreach ( $pending_orders as $pending_order_id => $balance ) {
					$order = wc_get_order( $pending_order_id );
					if ( ! is_a( $order, 'WC_Order' ) ) {
						continue;
					}

					/* translators: %1$s pay link, %2$s order id */
					$pay_links[] = sprintf( '<a href="%1$s">#%2$d</a>', add_query_arg( array( 'wc_gc_pay_order_pending_status' => 'notice' ), $order->get_checkout_payment_url() ), $order->get_id() );
					$counter     = $counter + 1;

					if ( $counter >= 2 ) {
						break;
					}
				}

				/* translators: amount of orders */
				$show_more_string = $total_orders > 2 ? sprintf( __( ' and %d more', 'woocommerce-gift-cards' ), $total_orders - 2 ) : '';

				if ( 'notice' === $context ) {

					/* translators: %1$s pending balance, %2$s paylinks comma separated, %3$s more string */
					$resolution = sprintf( esc_html__( 'Failed to apply gift card. %1$s on hold in orders %2$s%3$s.', 'woocommerce-gift-cards' ), wc_price( $giftcard->get_pending_balance() ), implode( ', ', $pay_links ), $show_more_string );

				} else {

					/* translators: %1$s pending balance, %2$s paylinks comma separated, %3$s more string */
					$resolution = sprintf( esc_html__( '%1$s on hold in orders %2$s%3$s', 'woocommerce-gift-cards' ), wc_price( $giftcard->get_pending_balance() ), implode( ', ', $pay_links ), $show_more_string );
				}
			}
		}

		return $resolution;
	}
}

if ( ! function_exists( 'wc_gc_form_field_recipient_html' ) ) {

	/**
	 * Prints the recipient form field html.
	 *
	 * @since  1.6.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	function wc_gc_form_field_recipient_html( $product ) {

		// Re-fill form.
		$to = isset( $_REQUEST[ 'wc_gc_giftcard_to' ] ) ? sanitize_text_field( $_REQUEST[ 'wc_gc_giftcard_to' ] ) : '';
		$to = empty( $to ) && isset( $_REQUEST[ 'wc_gc_giftcard_to_multiple' ] ) ? sanitize_text_field( $_REQUEST[ 'wc_gc_giftcard_to_multiple' ] ) : $to;

		if ( $product->is_sold_individually() ) { ?>
			<div class="wc_gc_field wc_gc_giftcard_to">
				<label for="wc_gc_giftcard_to"><?php esc_html_e( 'Recipients email', 'woocommerce-gift-cards' ); ?></label>
				<input type="text" name="wc_gc_giftcard_to" placeholder="<?php esc_attr_e( 'Enter gift card recipient email', 'woocommerce-gift-cards' ); ?>" value="<?php echo esc_attr( $to ); ?>" />
			</div>
		<?php } else { ?>
			<div class="wc_gc_field wc_gc_giftcard_to_multiple">
				<label for="wc_gc_giftcard_to_multiple"><?php esc_html_e( 'Recipients email', 'woocommerce-gift-cards' ); ?></label><?php
					/* translators: delimiter */
					$placeholder = sprintf( esc_attr__( 'Enter gift card recipient emails, separated by comma (%s)', 'woocommerce-gift-cards' ), wc_gc_get_emails_delimiter() );
				?><input type="text" name="wc_gc_giftcard_to_multiple" placeholder="<?php echo $placeholder ?>" value="<?php echo esc_attr( $to ); ?>"/>
			</div>
		<?php
		}
	}
}

if ( ! function_exists( 'wc_gc_form_field_cc_html' ) ) {

	/**
	 * Prints the CC form field html.
	 *
	 * @since  1.9.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	function wc_gc_form_field_cc_html( $product ) {

		if ( apply_filters( 'wooocommerce_gc_hide_cc_field', 'yes' !== get_option( 'wc_gc_allow_multiple_recipients', 'no' ), $product ) ) {
			return;
		}

		$value = isset( $_REQUEST[ 'wc_gc_giftcard_cc' ] ) ? sanitize_text_field( $_REQUEST[ 'wc_gc_giftcard_cc' ] ) : '';

		?>
		<div class="wc_gc_field wc_gc_giftcard_cc">
			<label for="wc_gc_giftcard_cc"><?php esc_html_e( 'CC', 'woocommerce-gift-cards' ); ?></label><?php
			/* translators: delimiter */
			?><input type="text" name="wc_gc_giftcard_cc" placeholder="<?php echo sprintf( esc_attr__( 'Enter additional emails to receive a gift card copy, separated by comma (%s)', 'woocommerce-gift-cards' ), wc_gc_get_emails_delimiter() ); ?>" value="<?php echo esc_attr( $value ); ?>"/>

		</div>
		<?php
	}
}

if ( ! function_exists( 'wc_gc_form_field_sender_html' ) ) {

	/**
	 * Prints the sender form field html.
	 *
	 * @since  1.6.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	function wc_gc_form_field_sender_html( $product ) {

		// Re-fill form.
		$from = isset( $_REQUEST[ 'wc_gc_giftcard_from' ] ) ? sanitize_text_field( wp_unslash( urldecode( $_REQUEST[ 'wc_gc_giftcard_from' ] ) ) ) : ''; // @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $from ) && get_current_user_id() ) {
			$customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );
			$customer    = new WC_Customer( $customer_id );
			if ( is_a( $customer, 'WC_Customer' ) ) {

				if ( is_email( $customer->get_display_name() ) || $customer->get_display_name() === $customer->get_username() ) {
					$customer->set_display_name( $customer->get_first_name() . ' ' . $customer->get_last_name() );
				}

				$from = ! empty( trim( $customer->get_display_name() ) ) ? $customer->get_display_name() : '';
			}
		}

		?>
		<div class="wc_gc_field wc_gc_giftcard_from">
			<label for="wc_gc_giftcard_from"><?php esc_html_e( 'Senders email', 'woocommerce-gift-cards' ); ?></label>
			<input type="text" name="wc_gc_giftcard_from" placeholder="<?php esc_attr_e( 'Enter your name', 'woocommerce-gift-cards' ); ?>" value="<?php echo esc_attr( $from ); ?>" />
		</div>
		<?php
	}
}

if ( ! function_exists( 'wc_gc_form_field_message_html' ) ) {

	/**
	 * Prints the message form field html.
	 *
	 * @since  1.6.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	function wc_gc_form_field_message_html( $product ) {

		// Re-fill form.
		$message = isset( $_REQUEST[ 'wc_gc_giftcard_message' ] ) ? sanitize_textarea_field( str_replace( '<br />', "\n", wp_unslash( urldecode( $_REQUEST[ 'wc_gc_giftcard_message' ] ) ) ) ) : ''; // @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		?>
		<div class="wc_gc_field wc_gc_giftcard_message">
			<label for="wc_gc_giftcard_message"><?php esc_html_e( 'Message', 'woocommerce-gift-cards' ); ?></label>
			<textarea rows="3" name="wc_gc_giftcard_message" placeholder="<?php esc_attr_e( 'Add your message (optional)', 'woocommerce-gift-cards' ); ?>"><?php echo esc_html( $message ); ?></textarea>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wc_gc_form_field_delivery_html' ) ) {

	/**
	 * Prints the delivery form field html.
	 *
	 * @since  1.6.0
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	function wc_gc_form_field_delivery_html( $product ) {

		// Re-fill form.
		$deliver_date = isset( $_REQUEST[ 'wc_gc_giftcard_delivery' ] ) ? absint( $_REQUEST[ 'wc_gc_giftcard_delivery' ] ) : '';

		// Check for valid date and reset.
		if ( $deliver_date < strtotime( 'tomorrow' ) ) {
			$deliver_date = '';
		}

		if ( $deliver_date && isset( $_REQUEST[ '_wc_gc_giftcard_delivery_gmt_offset' ] ) ) {
			$deliver_date = wc_gc_convert_timestamp_to_gmt_offset( $deliver_date, -1 * (float) $_REQUEST[ '_wc_gc_giftcard_delivery_gmt_offset' ] );
		}

		?>
		<div class="wc_gc_field wc_gc_giftcard_delivery">
			<label for="wc_gc_giftcard_delivery"><?php esc_html_e( 'Delivery Date', 'woocommerce-gift-cards' ); ?></label>
			<input autocomplete="off" readonly type="text" class="datepicker" placeholder="<?php esc_attr_e( 'Now', 'woocommerce-gift-cards' ); ?>" value="<?php echo $deliver_date ? esc_attr( date_i18n( get_option( 'date_format' ), $deliver_date ) ) : ''; ?>" />
			<input autocomplete="off" type="hidden" name="wc_gc_giftcard_delivery" />
			<input autocomplete="off" type="hidden" name="_wc_gc_giftcard_delivery_gmt_offset" />
			<?php echo wp_kses_post( apply_filters( 'woocommerce_gc_reset_delivery_date_link', '<a class="reset_delivery_date" href="#">' . esc_html__( 'Clear', 'woocommerce-gift-cards' ) . '</a>' ) ); ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'wc_gc_get_status_labels_html' ) ) {

	/**
	 * Get the status labels html.
	 *
	 * @since  1.7.0
	 *
	 * @param  mixed  $giftcard
	 * @param  bool   $single
	 * @return string
	 */
	function wc_gc_get_status_labels_html( $giftcard, $single = false ) {

		if ( ! is_a( $giftcard, 'WC_GC_Gift_Card_Data' ) ) {
			$giftcard = new WC_GC_Gift_Card_Data( $giftcard );
		}

		$labels = array();

		if ( $giftcard->has_expired() ) {

			$labels[] = array(
				'class'   => 'cancelled',
				'label'   => __( 'Expired', 'woocommerce-gift-cards' ),
			);
		}

		if ( false === $giftcard->is_delivered() && $giftcard->get_deliver_date() > 0 ) {
			$labels[] = array(
				'class'   => 'on-hold',
				'label'   => __( 'Scheduled', 'woocommerce-gift-cards' ),
				'tooltip' => wc_sanitize_tooltip( sprintf( 'Scheduled for %s', date_i18n( get_option( 'date_format' ), $giftcard->get_deliver_date() ) ) )
			);

		}

		if ( false !== $giftcard->is_delivered() && ! $giftcard->is_active() ) {

			$labels[] = array(
				'class'   => 'cancelled',
				'label'   => __( 'Deactivated', 'woocommerce-gift-cards' ),
			);
		}

		if ( false !== $giftcard->is_delivered() ) {

			$labels[] = array(
				'class'   => 'completed',
				'label'   => __( 'Delivered', 'woocommerce-gift-cards' ),
				'tooltip' => wc_sanitize_tooltip( sprintf( 'Delivered on %s', date_i18n( get_option( 'date_format' ), $giftcard->get_deliver_date() ? $giftcard->get_deliver_date() : $giftcard->get_date_created() ) ) )
			);

		} elseif ( $giftcard->is_active() ) {

			$labels[] = array(
				'class'   => 'completed',
				'label'   => __( 'Active', 'woocommerce-gift-cards' ),
			);
		}

		if ( false === $giftcard->is_delivered() && ! $giftcard->is_active() ) {

			$labels[] = array(
				'class'   => 'cancelled',
				'label'   => __( 'Inactive', 'woocommerce-gift-cards' ),
			);
		}

		if ( $giftcard->is_redeemed() ) {

			$labels[] = array(
				'class'   => 'redeemed',
				'label'   => __( 'Redeemed', 'woocommerce-gift-cards' ),
			);
		}

		// Build HTML labels with `mark` element.
		$html = '';
		foreach ( $labels as $label ) {

			if ( ! empty( $label[ 'tooltip' ] ) ) {

				$html .= sprintf( '<mark class="order-status %s tips" data-tip="%s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $label[ 'class' ] ) ), wp_kses_post( $label[ 'tooltip' ] ), esc_html( $label[ 'label' ] ) );
			} else {

				$html .= sprintf( '<mark class="order-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $label[ 'class' ] ) ), esc_html( $label[ 'label' ] ) );
			}

			if ( $single ) {
				break;
			}
		}

		return $html;
	}
}

if ( ! function_exists( 'wc_gc_display_message_text' ) ) {

	/**
	 * Get the status labels html.
	 *
	 * @since  1.8.1
	 *
	 * @param  string $message
	 * @param  string $context
	 * @param  string $separator
	 * @return string
	 */
	function wc_gc_display_message_text( $message, $context = 'all', $separator = ' ' ) {

		// Default settings to strip new lines based on context.
		$strip = false;
		switch ( $context ) {
			case 'order':
				// Remove new lines in order to disable wpautop. @see WC_Order_Item_Meta::display()
				$strip = true;
				break;
			case 'cart':
				$strip = false;
				// Strip while in mini-cart.
				if ( did_action( 'woocommerce_before_mini_cart' ) > did_action( 'woocommerce_after_mini_cart' ) ) {
					$strip = true;
				}
				break;
			case 'all':
				$strip = false;
				break;
		}

		/**
		 * Whether or not to strip new lines from gift card message in various display contexts.
		 *
		 * @since  1.8.1
		 *
		 * @param  mixed  $strip
		 * @param  string $context
		 * @return bool|string
		 */
		$strip = apply_filters( 'woocommerce_gc_strip_new_lines_in_message', $strip, $context );
		if ( ! is_bool( $strip ) ) {
			$separator = (string) $strip;
			$strip     = true;
		}

		$separator = ! empty( $separator ) ? ' ' . $separator . ' ' : ' ';
		$formatted = $strip ? trim( preg_replace( '/\s\s+/', esc_html( $separator ), wptexturize( $message ) ) ) : wptexturize( $message );

		return $formatted;
	}
}