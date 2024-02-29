<?php

/**
 * Provide a admin area view for the WP Legal Pages plugin
 *
 * This file is used to markup the admin-facing aspects of the WP Legal Pages plugin.
 *
 * @link       https://wplegalpages.com/
 * @since      2.10.0
 *
 * @package Wplegalpages
 */

$pro_is_activated = get_option( '_lp_pro_active' );
$popup = get_option( 'lp_popup_enabled' );
$lp_pro_key_activated    = get_option( 'wc_am_client_wplegalpages_pro_activated' );
$if_terms_are_accepted = get_option( 'lp_accept_terms' );

?>

<div id="wp-legalpages-main-admin-structure" class="wp-legalpages-main-admin-structure">
	<div id="wp-legalpages-main-admin-header" class="wp-legalpages-main-admin-header">
		<!-- Main top banner  -->
		<div class="wp-legalpages-admin-fixed-banner">
				<div class="wp-legalpages-admin-logo-and-label">
					<div class="wp-legalpages-admin-logo">
						<!-- //image  -->
						<img src="<?php echo esc_url( WPL_LITE_PLUGIN_URL ) . 'admin/images/Wp_Legal_pages_logo_icon.png'; ?>" alt="WP Cookie Consent Logo">
					</div>
					<div class="wp-legalpages-admin-label">
						<!-- //label  -->
						<div class="wp-legalpages-admin-label_wp_label"><img src="<?php echo esc_url( WPL_LITE_PLUGIN_URL ) . 'admin/images/Wp_Legal_pages_text_logo.png'; ?>" alt="WP Cookie Consent Label"></div>
					</div>
				</div>
				<div class="wp-legalpages-admin-help-and-support">
				<div class="wp-legalpages-admin-help">
						<div class="wp-legalpages-admin-help-icon">
							<!-- //image  -->
							<a href="https://club.wpeka.com/docs/wp-legal-pages/" target="_blank">
								<img src="<?php echo esc_url( WPL_LITE_PLUGIN_URL ) . 'admin/images/wp_cookie_help.png'; ?>" alt="WP Cookie Consent Help">
							</a>
						</div>
						<div class="wp-legalpages-admin-help-text"><a href="https://club.wpeka.com/docs/wp-legal-pages/" target="_blank">
							Help Guide</a>
						</div>
					</div>
					<div class="wp-legalpages-admin-support">
						<!-- //support  -->
						<div class="wp-legalpages-admin-support-icon">
							<!-- //image  -->
							<a href="https://club.wpeka.com/my-account/" target="_blank">
							<img src="<?php echo esc_url( WPL_LITE_PLUGIN_URL ) . 'admin/images/wp_cookie_support.png'; ?>" alt="WP Cookie Consent Support">
							</a>
						</div>
						<div class="wp-legalpages-admin-support-text"><a href="https://club.wpeka.com/my-account/" target="_blank">
							Support</a>
						</div>
					</div>
				</div>
		</div>
		<!-- promotional banner  -->
		<?php

		if ( ! $pro_is_activated ) {

		?>
			<div class="wp-legalpages-admin-promotional-banner">
				<a href="https://club.wpeka.com/product/wplegalpages/?utm_source=plugin&utm_medium=banner&utm_campaign=upgrade-to-pro" target="_blank">
				<img src="<?php echo esc_url( WPL_LITE_PLUGIN_URL ) . 'admin/images/wp_legalpages_upgrade_to_pro.png'; ?>" alt="WP Cookie Consent Promotional Banner"></a>
			</div>
		<?php

		};

		?>
		<!-- tabs -->
		<div class="wp-legalpages-admin-tabs-section">
			<div class="wp-legalpages-admin-tabs">
				<!-- Gettins Started tab  -->
				<div class="wp-legalpages-admin-tab wp-legalpages-admin-getting-started-tab" data-tab="getting_started">
					<p class="wp-legalpages-admin-tab-name">Getting&nbsp;Started</p>
				</div>

				<?php
				// if terms are accepted only then show rest of the tabs
				if ( $if_terms_are_accepted ) {

				?>

					<!-- Create Legal Pages tab  -->
					<div class="wp-legalpages-admin-tab wp-legalpages-admin-create_legalpages-tab" data-tab="create_legal_page">
						<p class="wp-legalpages-admin-tab-name">Create&nbsp;Legal&nbsp;Pages</p>
					</div>
					<!-- Settings tab  -->
					<div class="wp-legalpages-admin-tab wp-legalpages-admin-settings-tab" data-tab="settings">
						<p class="wp-legalpages-admin-tab-name">Settings</p>
					</div>
					<!-- All Legal Pages data tab  -->
					<div class="wp-legalpages-admin-tab wp-legalpages-admin-all_legalpages-tab" data-tab="all_legal_pages">
					<p class="wp-legalpages-admin-tab-name">All Legal Pages</p>
					</div>
				<?php

				};

				?>
				<!-- tab for create popup  -->
				<?php
				// first check if popup is activated
				if ( $popup ) {
					// do not show tab if pro isn't activated
					if ( $lp_pro_key_activated == 'Activated' && $pro_is_activated ) {
					?>
						<div class="wp-legalpages-admin-tab wp-legalpages-admin-create-popups-tab" data-tab="create_popup">
						<p class="wp-legalpages-admin-tab-name">Create&nbsp;Popups</p>
						</div>

					<?php
					}
				}

				?>

			</div>
		</div>

		<!-- tab content  -->

		<div class="wp-legalpages-admin-tabs-content">
			<div class="wp-legalpages-admin-tabs-inner-content">
				<!-- Getting Started content  -->
				<div class="wp-legalpages-admin-getting-started-content wp-legalpages-admin-tab-content" id="getting_started">

				<?php require_once plugin_dir_path( __FILE__ ) . 'wp-legal-pages-getting-started-template.php'; ?>

				</div>
				<!-- create cookie content  -->
				<div class="wp-legalpages-admin-create-cookie-content wp-legalpages-admin-tab-content" id="create_legal_page">

				</div>
				<!-- settings content -->
				<div class="wp-legalpages-admin-cookie-settings-content wp-legalpages-admin-tab-content" id="settings">

				<?php require_once plugin_dir_path( __FILE__ ) . 'wp-legal-pages-settings-template.php'; ?>

				</div>
				<!-- all legalpages data content  -->
				<div class="wp-legalpages-admin-all-legalpages-data-content wp-legalpages-admin-tab-content" id="all_legal_pages">

				<?php require_once plugin_dir_path( __FILE__ ) . 'wp-legal-pages-all-legalpages-template.php'; ?>

				</div>
				<!-- create popup  -->
				<div class="wp-legalpages-admin-legal-pages-content wp-legalpages-admin-tab-content" id="create_popup">

				<?php

					// do not show tab if pro key isn't activated
					if ( $lp_pro_key_activated == 'Activated' && $pro_is_activated ) {
					?>

					<?php require_once plugin_dir_path( __FILE__ ) . 'wp-legal-pages-create-popups-template.php'; ?>

					<?php
					}


				?>

				</div>
			</div>
		</div>

	</div>
</div>
