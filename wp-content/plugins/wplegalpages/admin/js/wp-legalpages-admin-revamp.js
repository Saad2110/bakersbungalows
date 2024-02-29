var jQuery = jQuery.noConflict();

var jQuery = jQuery.noConflict();

jQuery(document).ready(function () {
    // localised variable
    const isProActivated = wplp_localize_data.is_pro_activated;
    const adminUrl = wplp_localize_data.admin_url;
	const lpTerms  = wplp_localize_data.lp_terms;

    if (isProActivated) {
        jQuery('.wp-legalpages-admin-tabs-section').addClass('pro-is-activated');
        jQuery('.wp-legalpages-admin-tab').addClass('pro-is-activated');
    }

	if ( lpTerms != '1') {
		jQuery('.wp-legalpages-admin-tab .wp-legalpages-admin-tab-name').addClass('lp-terms-not-acpt');
		jQuery('.wplegal-help-card').addClass('lp-terms-not-acpt');
		jQuery('.wplegal-container .wplegal-container-features .wplegal-features-section .wplegal-section-content .wplegal-feature').addClass('lp-terms-not-acpt');
		jQuery('.wp-legalpages-admin-help-and-support .wp-legalpages-admin-help-text').addClass('lp-terms-not-acpt');
		jQuery('.wp-legalpages-admin-help-and-support .wp-legalpages-admin-support-text').addClass('lp-terms-not-acpt');
	}


    // Hide all tab contents initially except the first one
    jQuery('.wp-legalpages-admin-tab-content').not(':first').hide();
    jQuery('.wp-legalpages-admin-getting-started-tab').addClass('active-tab');
    jQuery('#getting_started').show();

    // On tab click, redirect to the specified URL for create_legalpages tab
    jQuery('.wp-legalpages-admin-create_legalpages-tab').on('click', function () {
        // Redirect to the specified URL when the tab is clicked
        window.location.href =  adminUrl + 'index.php?page=wplegal-wizard';
    });

    // On tab click, show the corresponding content and update URL hash for other tabs
    jQuery('.wp-legalpages-admin-tabs').on('click', '.wp-legalpages-admin-tab:not(.wp-legalpages-admin-create_legalpages-tab)', function (event) {
        event.preventDefault();
        var tabId = jQuery(this).data('tab');

        // Remove active class from all tabs
        jQuery('.wp-legalpages-admin-tab').removeClass('active-tab');

        // Hide all tab contents
        jQuery('.wp-legalpages-admin-tab-content').hide();

        // Show the selected tab content
        jQuery('#' + tabId).show();
        jQuery(this).addClass('active-tab');

        // Update URL hash with the tab ID
        history.pushState({}, '', '#' + tabId);
    });

    // Retrieve the active tab from URL hash on page load
    var hash = window.location.hash;

    if (hash) {
        var tabId = hash.substring(1); // Remove '#' from the hash

        const substr = 'settings#';

        if (tabId.includes(substr)) {
            tabId = 'settings'
        }
        // Remove active class from all tabs
        jQuery('.wp-legalpages-admin-tab').removeClass('active-tab');

        // Hide all tab contents
        jQuery('.wp-legalpages-admin-tab-content').hide();

        // Show the stored active tab content
        jQuery('#' + tabId).show();
        jQuery('[data-tab="' + tabId + '"]').addClass('active-tab');
    }

    // load the clicked link
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual'
    }

});
