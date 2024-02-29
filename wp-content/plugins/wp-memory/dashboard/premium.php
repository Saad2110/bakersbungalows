<?php
/**
 * @ Author: Bill Minozzi
 * @ Copyright: 2020 www.BillMinozzi.com
 * @ Modified time: 2021-03-02 17:19:27
 */
if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}
global $wpmemory_memory;

if (!function_exists('ini_set')) {
    function wpmemory_general_admin_notice1()
    {
        if (is_admin()) {
            echo '<div class="notice notice-warning is-dismissible">
				 <p>Your server doesn\'t have a PHP function ini_set.</p>
				 <p>Please, talk with your hosting company before to buy premium.</p>
			 </div>';
        }
    }
    add_action('admin_notices', 'wpmemory_general_admin_notice');
}



    echo '<div class="wrap-wpmemory ">' . "\n";
    echo '<h2 class="title">Premium</h2>' . "\n";
    echo '<p class="description">';
    echo esc_attr__("Our Premium version can modify quickly the necessary files to increase your WP Memory Limit and PHP Memory. Don't take the risk of making manual changes that can harm your site.","wp-memory");
    echo '</p>' . "\n";
    echo '<br />';

    
    
        echo '<a href="http://siterightaway.net/wp-memory-premium-plugin/" class="button-primary">';
        esc_attr_e("Go Premium","wp-memory");
        echo '</a>';



        echo '<br />';
        echo '<br />';




echo '<div class="main-notice">';
echo '</div>' . "\n";
echo '</div>';
function stripNonAlphaNumeric($string)
{
    return preg_replace("/[^a-z0-9]/i", "", $string);
}