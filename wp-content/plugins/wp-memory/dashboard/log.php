<?php
/**
 * @ Author: Bill Minozzi
 * @ Copyright: 2020 www.BillMinozzi.com
 * @ Modified time: 2024-01-24 09:03:33
 */
echo '<div class="wrap-wpmemory ">' . "\n";
echo '<h2 class="title">Memory Usage by Page (Last 200)</h2>' . "\n";
echo esc_attr__("We suggest keeping the usage percentage below 70%.","wp-memory");

echo '<br>';

echo esc_attr__('Spammers and hackers may try loading non-existent pages, seeking vulnerabilities, and potentially overloading your server. Enhance your security with our Anti Hacker and Stop Bad Bots plugins. Click the "More Tools" tab above.',"wp-memory");
echo '<br>';
echo '<br>';
?>
<style>
.widefat tbody tr.even {
    background-color: #f5f5f5;
}
.widefat tbody tr.high {
    background-color: yellow;
}
.widefat thead th {
    background-color: #333;
    color: #fff !important; /* Ensure text is white */
    padding: 10px;
}
</style>
<?php
if(!function_exists("wpmemory_convert_to_bytes")) {
    function wpmemory_convert_to_bytes($value) {
        $value = trim($value);
        $unit = strtoupper(substr($value, -1));
        $value = (int)$value;
        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }
        return $value;
    }
}
wpmemory_display_custom_table_page();
// Function to display the table
function wpmemory_display_custom_table_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpmemory_log';
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    // Verify nonce only on GET request when form is submitted
    if (isset($_GET['paged'])) {
        // Add nonce field
        $nonce_field = wp_nonce_field('wpmemory_pagination', 'wpmemory_nonce', true, false);
        // Retrieve nonce for verification
        $nonce = isset($_REQUEST['wpmemory_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['wpmemory_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'wpmemory_pagination')) {
            die('Security check failed.');
        }
    }
    // Get items from the table
    $data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date DESC LIMIT $offset, $per_page", ARRAY_A);
    // Total items for pagination
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    // Configure table headers
    $columns = array(
        'date'         => 'Date',
        'memory_usage' => 'Memory Usage',
        'php_memory'   => 'WP Memory Limit',
        'usage perc'   => 'Usage Perc',
        'page'         => 'Page'
    );
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    // Configure pagination
    $total_pages = ceil($total_items / $per_page);
    // Pagination args
    $pagination_args = array(
        'base'      => add_query_arg(array('paged' => '%#%', 'wpmemory_nonce' => wp_create_nonce('wpmemory_pagination')), admin_url('tools.php?page=wp_memory_admin_page&tab=log')),
        'format'    => '',
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
        'total'     => $total_pages,
        'current'   => $current_page,
    );
    // Add nonce to pagination links
    $pagination = paginate_links($pagination_args);
    // Display the table
    $nonce = wp_create_nonce('wpmemory_pagination');
    echo '<form method="get" action="' . esc_url(admin_url('tools.php?page=wp_memory_admin_page&tab=log&wpmemory_nonce=$nonce')) . '">';
    wp_nonce_field('wpmemory_pagination', 'wpmemory_nonce');
    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr>';
    foreach ($columns as $column_key => $column_name) {
        echo '<th>' . esc_html($column_name) . '</th>';
    }
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    $row_class = ''; // Initialize row class
    if (defined('WP_MEMORY_LIMIT')) {
        $memory_limit = WP_MEMORY_LIMIT;
    } else {
        $memory_limit = "40M";
    }

    $memory_limit = wpmemory_convert_to_bytes($memory_limit);
    $php_memory = wpmemory_convert_to_bytes(ini_get('memory_limit'));
    foreach ($data as $item) {
       
        $memory_usage = $item['memory_usage'];
 
        if (is_numeric($php_memory) && is_numeric($memory_usage)) {
            $perc = sprintf('%.2f%%', ($memory_usage / $memory_limit) * 100);
        } else {
            $perc = 0;
        }
       
        // Toggle row class to create alternating gray background
        if($perc > 70)
           $row_class = 'high';
        else
           $row_class = ($row_class == 'even') ? 'odd' : 'even';
        echo '<tr class="' . esc_attr($row_class) . '">';
        echo '<td>' . esc_html($item['date']) . '</td>';
        echo '<td>' . esc_html(wpmemory_sizeFilter($item['memory_usage'])) . '</td>';
        echo '<td>' . esc_html(wpmemory_sizeFilter( $memory_limit)) . '</td>';
       

        if (is_numeric($php_memory) && is_numeric($memory_usage)) {
            $percentage = sprintf('%.2f%%', ($memory_usage / $memory_limit) * 100);
        } else {
            $percentage = '?';
        }
 
        echo '<td>' . esc_html($percentage) . '</td>'; 
        echo '<td>' . esc_html($item['page']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    // Display pagination
    if ($total_pages > 1) {
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)) . '</span>';
        echo '<span class="pagination-links">' . $pagination . '</span>';
        echo '</div>';
        echo '</div>';
    }
    echo '</form>';
}
echo '</div>';
?>
