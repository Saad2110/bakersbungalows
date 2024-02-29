<?php
/**
 * @ Author: Bill Minozzi
 * @ Copyright: 2020 www.BillMinozzi.com
 * @ Modified time: 2021-03-02 17:19:27
 */
if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}

if (isset($_GET['page']) && $_GET['page'] == 'wp_memory_admin_page') {
    if (isset($_POST['wp_memory_update']) && $_POST['process'] == 'wp_memory_admin_page') {
        // Check nonce
        $nonce = sanitize_text_field($_POST['_wpnonce'] ?? '');

        if (wp_verify_nonce($nonce, 'update_wp_memory')) {
            $wp_memory_update = sanitize_text_field($_POST['wp_memory_update']);
            
            // Update option
            if (!update_option('wp_memory_update', $wp_memory_update))
                add_option('wp_memory_update', $wp_memory_update);
            
            echo '<br>';
            wpmemory_updated_message();
        } else {
            // Nonce verification failed
            echo 'Nonce verification failed.';
        }
    }
}

// Display form
echo '<div class="wrap-wpmemory ">' . "\n";
echo '<h2 class="title">Settings</h2>' . "\n";

$wp_memory_update = trim(sanitize_text_field(get_option('wp_memory_update', '')));

esc_attr_e("Enable Auto Update Plugin? (default Yes)","wp-memory");
echo '<br />';
esc_attr_e("After that, click UPDATE Button.","wp-memory");
echo '<br />';
echo '<br />';

?>
<form class="wpmemory -form" method="post" action="admin.php?page=wp_memory_admin_page&tab=settings">

    <input type="hidden" name="process" value="wp_memory_admin_page" />

    <!-- Add the hidden input for nonce value -->
    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('update_wp_memory')); ?>" />

    <label>
        <input type="radio" name="wp_memory_update" value="yes" <?php echo checked('yes', $wp_memory_update, false); ?>>
        <?php esc_attr_e("Yes, enable WP Memory Auto Update","wp-memory");?>
    </label>
    <br>
    <label>
        <input type="radio" name="wp_memory_update" value="no" <?php echo checked('no', $wp_memory_update, false); ?>>
        <?php esc_attr_e("No (unsafe)","wp-memory");?>
    </label>

    <?php
    echo '<br />';
    echo '<br />';
    echo '<input class="wpmemory -submit button-primary" type="submit" value="Update" />';
    echo '</form>' . "\n";

echo '<div class="main-notice">';
echo '</div>' . "\n";
echo '</div>';
?>
