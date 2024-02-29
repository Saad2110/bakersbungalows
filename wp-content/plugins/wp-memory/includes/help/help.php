<?php
/**
 * @author Bill Minozzi
 * @copyright 2021 - 2023
 */
if (!defined('ABSPATH')) {
    exit;
}
if (is_admin()) {
    // Pointer...
    function wpmemory_load_pointer() {
        $pointers = get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true);
        $pointers = ''; // str_replace( 'plugins', '', $pointers );
        update_user_meta(get_current_user_id(), 'dismissed_wp_pointers', $pointers);
    }
    function wpmemory_load_pointer_css() {
        wp_enqueue_style('wpm-pointer', WPMEMORYURL . 'includes/help/bill-wp-pointer.css');
    }
    function wpmemory_adm_enqueue_scripts2() {
        global $bill_current_screen;
        // wp_enqueue_style( 'wp-pointer' );
        wp_enqueue_script('wp-pointer');
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        $myscreen = get_current_screen();
        $bill_current_screen = $myscreen->id;
        // Check if the pointer was displayed before
        $dismissed = get_user_option('wpmemory_pointer_displayed', get_current_user_id());
       // if (!$dismissed) {
            add_action('admin_print_footer_scripts', 'wpmemory_admin_print_footer_scripts');
            update_user_option(get_current_user_id(), 'wpmemory_pointer_displayed', true);
       // }
    }
    $wpmemory_activated_pointer = trim(sanitize_text_field(get_option('wpmemory_activated_pointer', '0')));
    if ($wpmemory_activated_pointer == '1') {
        $BILLCLASS = 'ACTIVATED_WPMEMORY';
        if (isset($_COOKIE[$BILLCLASS])) {
            $bill_tempo_criacao = $_COOKIE[$BILLCLASS];
            $bill_intervalo = time() - $bill_tempo_criacao;
            // wait 30 sec...
            if ($bill_intervalo > 10) {
                // add_action('wp_enqueue_scripts', 'wpmemory_load_pointer_css');
                add_action('wp_loaded', 'wpmemory_load_pointer_css');
                add_action('admin_enqueue_scripts', 'wpmemory_adm_enqueue_scripts2');
                wpmemory_load_pointer();
                $r = update_option('wpmemory_activated_pointer', '0');
                if (!$r) {
                    add_option('wpmemory_activated_pointer', '0');
                }
            }
        }
    }
    // end pointer
}
function wpmemory_admin_print_footer_scripts() {
    global $bill_current_screen;
    $pointer_content = esc_attr__("Open WP Memory Plugin Here!", "wpmemory");
    $pointer_content2 = $pointer_content . ' ' . esc_attr__("Just Click Over Tools, then Go To WP Memory.", "wpmemory");
    ?>


    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        // Check if the pointer already exists
        if (jQuery('#bill-pointer-wpm').length === 0) {

            jQuery('.wp-menu-name:contains("Tools")').pointer({
                content: '<?php echo '<h3>' . esc_attr($pointer_content) . '</h3>' . '<div id="bill-pointer-wpm-body">' . esc_attr($pointer_content2) . '</div>'; ?>',
                position: {
                    edge: 'left',
                    align: 'right'
                },
                close: function () {
                    // Once the close button is hit
                    jQuery.post(ajaxurl, {
                        pointer: '<?php echo esc_attr($bill_current_screen); ?>',
                        action: 'dismiss-wp-pointer'
                    });
                }
            }).pointer('open');

            // Add an ID to the pointer
            jQuery('#wp-pointer-0').attr('id', 'bill-pointer-wpm');
            jQuery('#wp-pointer-1').hide();
        }
    });
    </script>

    <?php
}
?>
