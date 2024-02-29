<?php
/**
 * @ Author: Bill Minozzi
 * @ Copyright: 2020 www.BillMinozzi.com
 * @ Modified time: 2021-03-03 08:58:41
 */
if (!defined('ABSPATH')) {
    die('We\'re sorry, but you can not directly access this file.');
}


// Activation...
if (is_admin()) {



// debug($wpmemory_activated_notice);

	$BILLCLASS = 'ACTIVATED_WPMEMORY';
	if(!isset($_COOKIE[$BILLCLASS]) and $wpmemory_activated_notice == '1'){
		add_action('wp_loaded', 'wpmemory_load_feedback3');
		add_action( 'admin_notices', 'wpmemory_include_file_more_plugins' );
	}

	
	// 2023
	function wpmemory_load_feedback3() {
		global $wpmemory_request_url;
		if (strpos($wpmemory_request_url, 'plugins.php') !== false) 
		{
			wp_register_style( 'bill-feedback-wpmemory-css', WPMEMORYURL. 'includes/feedback/feedback-plugin.css' );
			wp_enqueue_style( 'bill-feedback-wpmemory-css' );

			wp_register_script( 'bill-feedback-wpmemory-js', WPMEMORYURL.'includes/feedback/activated-manager.js' , array( 'jquery' ), WPMEMORYVERSION, true );
			wp_enqueue_script( 'bill-feedback-wpmemory-js' );
		}
	}
	

	function wpmemory_include_file_more_plugins() {
		global $wpmemory_request_url;
		if (strpos($wpmemory_request_url, 'plugins.php') !== false) 
			require_once (WPMEMORYPATH . 'includes/feedback/activated-manager.php');
	}

	function wpmemory_activated()
	{
		$r = update_option('wpmemory_was_activated', '1');
		if (!$r) {
			add_option('wpmemory_was_activated', '1');
		}
		$r = update_option('wpmemory_activated_notice', '1');
		if (!$r) {
			add_option('wpmemory_activated_notice', '1');
		}
		$r = update_option('wpmemory_activated_pointer', '1');
		if (!$r) {
			add_option('wpmemory_activated_pointer', '1');
		}
	}
	// end activation
}










$wpmemory_now = strtotime("now");
$wpmemory_after = strtotime("now") + (3600);
function wpmemory_enqueue_scripts()
{
   // wp_register_script('wpmemory-fix-config-manager', WPMEMORYURL . 'dashboard/fixconfig/wp-memory-fix-config-manager.js', array('jquery'), WPMEMORYVERSION, true);
   // wp_enqueue_script('wpmemory-fix-config-manager');
   // wp_enqueue_style('bill-help-wpmemory', WPMEMORYURL . 'dashboard/fixconfig/help.css');
}
//add_action('admin_init', 'wpmemory_enqueue_scripts');
if (!function_exists('ini_get')) {
	function wpmemory_general_admin_notice2()
	{
		if (is_admin()) {
			echo '<div class="notice notice-warning is-dismissible">
				 <p>Your server doesn\'t have a PHP function ini_get.</p>
				 <p>Please, talk with your hosting company.</p>
			 </div>';
		}
	}
	add_action('admin_notices', 'wpmemory_general_admin_notice');
}
function wpmemory_memory_test()
{
    global $wpmemory_memory, $wpmemory_usage_content, $wpmemory_label, $wpmemory_status, $wpmemory_description, $wpmemory_actions;
    $result = array(
        'badge' => array(
            'label' => $wpmemory_label,
            'color' => $wpmemory_memory['color'],
        ),
        'test' => 'wpmemory_test',
        // status: Section the result should be displayed in. Possible values are good, recommended, or critical.
        'status' => $wpmemory_status,
        'label' => __('Memory Usage', 'wp-memory' ),
        'description' => $wpmemory_description . '  ' . $wpmemory_usage_content,
        'actions' => $wpmemory_actions
    );
    return $result;
}
function wpmemory_add_debug_info($debug_info)
{
    global $wpmemory_usage_content;
    $debug_info['wpmemory'] = array(
        'label' => __('Memory Usage', 'wp-memory' ),
        'fields' => array(
            'memory' => array(
                'label' => __('Memory Usage information', 'wp-memory' ),
                'value' => strip_tags($wpmemory_usage_content),
                'private' => true
            )
        )
    );
    return $debug_info;
}
function wpmemory_activation()
{
    global $wp_version;
    if (version_compare(PHP_VERSION, '5.3', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        load_plugin_textdomain('wpmemory', false, dirname(plugin_basename(__FILE__)) . '/language/');
        $plugin_data    = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];
        $plugin_name    = $plugin_data['Name'];
        wp_die('<h1>' . __('Could not activate plugin: PHP version error', 'wp-memory' ) . '</h1><h2>PLUGIN: <i>' . $plugin_name . ' ' . $plugin_version . '</i></h2><p><strong>' . __('You are using PHP version', 'wp-memory' ) . ' ' . PHP_VERSION . '</strong>. ' . __('This plugin has been tested with PHP versions 5.3 and greater.', 'wp-memory' ) . '</p><p>' . __('WordPress itself <a href="https://wordpress.org/about/requirements/" target="_blank">recommends using PHP version 7 or greater</a>. Please upgrade your PHP version or contact your Server administrator.', 'wp-memory' ) . '</p>', __('Could not activate plugin: PHP version error', 'wp-memory' ), array(
            'back_link' => true
        ));
    }
    if (version_compare($wp_version, '5.2') < 0) {
        deactivate_plugins(plugin_basename(__FILE__));
        load_plugin_textdomain('wpmemory', false, dirname(plugin_basename(__FILE__)) . '/language/');
        $plugin_data    = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];
        $plugin_name    = $plugin_data['Name'];
        wp_die('<h1>' . __('Could not activate plugin: WordPress need be 5.2 or bigger.', 'wp-memory' ) . '</h1><h2>PLUGIN: <i>' . $plugin_name . ' ' . $plugin_version . '</i></h2><p><strong>' . __('Please, Update WordPress to Version 5.2 or bigger to use this plugin.', 'wp-memory' ) . '</strong>', array(
            'back_link' => true
        ));
    }
}
function wp_memory_activ_message()
{
    if (get_transient('wpmemory-admin-notice')) {
        echo '<div class="updated"><p>';
        $bd_msg = '<h2>';
        $bd_msg .= __('WP Memory  Plugin was activated!', "wp-memory");
        $bd_msg .= '</h2>';
        $bd_msg .= '<h3>';
        $bd_msg .= __('For details and help, take a look at WP Memory  at your left menu, Tools', "wp-memory");
        $bd_msg .= '<br />';
        $bd_msg .= ' <a class="button button-primary" href="admin.php?page=wp_memory_admin_page">';
        $bd_msg .= __('or click here', "wp-memory");
        $bd_msg .= '</a>';

		$allowed_atts = array(
			'align'      => array(),
			'class'      => array(),
			'type'       => array(),
			'id'         => array(),
			'dir'        => array(),
			'lang'       => array(),
			'style'      => array(),
			'xml:lang'   => array(),
			'src'        => array(),
			'alt'        => array(),
			'href'       => array(),
			'rel'        => array(),
			'rev'        => array(),
			'target'     => array(),
			'novalidate' => array(),
			'type'       => array(),
			'value'      => array(),
			'name'       => array(),
			'tabindex'   => array(),
			'action'     => array(),
			'method'     => array(),
			'for'        => array(),
			'width'      => array(),
			'height'     => array(),
			'data'       => array(),
			'title'      => array(),

			'checked' => array(),
			'selected' => array(),


		);

		$my_allowed['form'] = $allowed_atts;
		$my_allowed['select'] = $allowed_atts;
		// select options
		$my_allowed['option'] = $allowed_atts;
		$my_allowed['style'] = $allowed_atts;
		$my_allowed['label'] = $allowed_atts;
		$my_allowed['input'] = $allowed_atts;
		$my_allowed['textarea'] = $allowed_atts;

        //more...future...
		$my_allowed['form']     = $allowed_atts;
		$my_allowed['label']    = $allowed_atts;
		$my_allowed['input']    = $allowed_atts;
		$my_allowed['textarea'] = $allowed_atts;
		$my_allowed['iframe']   = $allowed_atts;
		$my_allowed['script']   = $allowed_atts;
		$my_allowed['style']    = $allowed_atts;
		$my_allowed['strong']   = $allowed_atts;
		$my_allowed['small']    = $allowed_atts;
		$my_allowed['table']    = $allowed_atts;
		$my_allowed['span']     = $allowed_atts;
		$my_allowed['abbr']     = $allowed_atts;
		$my_allowed['code']     = $allowed_atts;
		$my_allowed['pre']      = $allowed_atts;
		$my_allowed['div']      = $allowed_atts;
		$my_allowed['img']      = $allowed_atts;
		$my_allowed['h1']       = $allowed_atts;
		$my_allowed['h2']       = $allowed_atts;
		$my_allowed['h3']       = $allowed_atts;
		$my_allowed['h4']       = $allowed_atts;
		$my_allowed['h5']       = $allowed_atts;
		$my_allowed['h6']       = $allowed_atts;
		$my_allowed['ol']       = $allowed_atts;
		$my_allowed['ul']       = $allowed_atts;
		$my_allowed['li']       = $allowed_atts;
		$my_allowed['em']       = $allowed_atts;
		$my_allowed['hr']       = $allowed_atts;
		$my_allowed['br']       = $allowed_atts;
		$my_allowed['tr']       = $allowed_atts;
		$my_allowed['td']       = $allowed_atts;
		$my_allowed['p']        = $allowed_atts;
		$my_allowed['a']        = $allowed_atts;
		$my_allowed['b']        = $allowed_atts;
		$my_allowed['i']        = $allowed_atts;
     	

		echo wp_kses($bd_msg, $my_allowed);


       // echo $bd_msg;





        echo "</p></h3></div>";
        delete_transient('wpmemory-admin-notice');
    }
}
function wpmemory_admin_notice_activation_hook()
{
    /* Create transient data */
    set_transient('wpmemory-admin-notice', true, 5);
}
function wp_memory_init()
{
    add_management_page(
        'WP Memory',
        'WP Memory',
        'manage_options',
        'wp_memory_admin_page', // slug
        'wp_memory_admin_page'
    );
}
function wpmemory_check_memory()
{
    global $wpmemory_memory;
    $wpmemory_memory['limit'] = (int) ini_get('memory_limit');
    $wpmemory_memory['usage'] = function_exists('memory_get_usage') ? round(memory_get_usage() / 1024 / 1024, 0) : 0;
    
    /*
    //if (!defined("WP_MEMORY_LIMIT")) {
        $wpmemory_memory['msg_type'] = 'notok';
        return;
    }
    */


    // $wpmemory_memory['wp_limit'] =  trim(WP_MEMORY_LIMIT);

    if (!defined("WP_MEMORY_LIMIT")) {
        $wpmemory_memory['wp_limit'] = 40;
    } else {

        $wpmemory_memory['wp_limit'] = (int) rtrim(WP_MEMORY_LIMIT, 'M');
    }


    if ($wpmemory_memory['wp_limit'] > 9999999)
        $wpmemory_memory['wp_limit'] = ($wpmemory_memory['wp_limit'] / 1024) / 1024;


    if (!is_numeric($wpmemory_memory['usage'])) {
        $wpmemory_memory['msg_type'] = 'notok';
        return;
    }
    if (!is_numeric($wpmemory_memory['limit'])) {
        $wpmemory_memory['msg_type'] = 'notok';
        return;
    }
    
    if ($wpmemory_memory['limit'] > 9999999)
       $wpmemory_memory['limit'] = ($wpmemory_memory['limit'] / 1024) / 1024;	


    if ($wpmemory_memory['usage'] < 1) {
        $wpmemory_memory['msg_type'] = 'notok';
        return;
    }

    /*
    $wplimit = $wpmemory_memory['wp_limit'];
    $wplimit = substr($wplimit, 0, strlen($wplimit) - 1);
    $wpmemory_memory['wp_limit'] = $wplimit;
    */


    $wpmemory_memory['percent'] = $wpmemory_memory['usage'] / $wpmemory_memory['wp_limit'];
    $wpmemory_memory['color'] = 'font-weight:normal;';
    if ($wpmemory_memory['percent'] > .7) $wpmemory_memory['color'] = 'font-weight:bold;color:#E66F00';
    if ($wpmemory_memory['percent'] > .85) $wpmemory_memory['color'] = 'font-weight:bold;color:red';
    $wpmemory_memory['msg_type'] = 'ok';
    return $wpmemory_memory;
}


function wp_memory_admin_page()
{
            require_once WPMEMORYPATH . "/dashboard/dashboard_container.php";
}
function wp_memory_plugin_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=wp_memory_admin_page">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function wpmemory_add_memory_test($tests)
{
    $tests['direct']['wpmemory_plugin'] = array(
        'label' => __('WP Memory Test', 'wp-memory' ),
        'test' => 'wpmemory_memory_test'
    );
    return $tests;
}
function wpmemory_check($code)
{
  $code = trim($code);
  if(empty($code))
    return false;
  $code = stripNonAlphaNumeric($code);
  $size = strlen($code);
  if (($size != 17) and ($size != 6)  and ($size != 7)  and ($size != 8))
    return false;
  if ($size == 6 or $size == 7 or $size == 8) {
    if (!is_numeric($code))
      return false;
    if ($code < 290000)
      return false;
  }
  /*
  if (($size == 17)) {
    if (is_numeric($code))
      return false;
    if (!ctype_alnum($code))
      return false;
    if (!preg_match('#[0-9]#', $code)) {
      return false;
    }
    if ($code != strtoupper($code))
      return false;
  }
  */
  return true;
}
function wpmemory_updated_message()
{
    echo '<div class="notice notice-success is-dismissible">';
    echo '<br /><b>';
    echo esc_attr__('Database Updated!', 'wp-memory' );
    echo '<br /><br /></div>';
}

function wpmemory_strip_strong($htmlString)
{
    // return $htmlString;
    // Use preg_replace para remover as tags <strong>
    $textWithoutStrongTags = preg_replace(
        "/<strong>(.*?)<\/strong>/i",
        '$1',
        $htmlString
    );

    return $textWithoutStrongTags;
}

function wpmemory_javascript_errors_today($onlytoday)
{
    $wpmemory_count = 0;

    //define('WPMEMORYPATH', plugin_dir_path(__file__));
    //WPMEMORYPATH
    $wpmemory_themePath = get_theme_root();
    $error_log_path = trim(ini_get("error_log"));
    if (
        !is_null($error_log_path) and
        $error_log_path != trim(ABSPATH . "error_log")
    ) {
        $wpmemory_folders = [
            $error_log_path,
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPATH . "/error_log",
            WPMEMORYPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    } else {
        $wpmemory_folders = [
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPATH . "/error_log",
            WPMEMORYPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    }
    $wpmemory_admin_path = str_replace(
        get_bloginfo("url") . "/",
        ABSPATH,
        get_admin_url()
    );
    array_push($wpmemory_folders, $wpmemory_admin_path . "/error_log");
    array_push($wpmemory_folders, $wpmemory_admin_path . "/php_errorlog");
    $wpmemory_plugins = array_slice(scandir(WPMEMORYPATH), 2);
    foreach ($wpmemory_plugins as $wpmemory_plugin) {
        if (is_dir(WPMEMORYPATH . "/" . $wpmemory_plugin)) {
            array_push(
                $wpmemory_folders,
                WPMEMORYPATH . "/" . $wpmemory_plugin . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                WPMEMORYPATH . "/" . $wpmemory_plugin . "/php_errorlog"
            );
        }
    }
    $wpmemory_themes = array_slice(scandir($wpmemory_themePath), 2);
    foreach ($wpmemory_themes as $wpmemory_theme) {
        if (is_dir($wpmemory_themePath . "/" . $wpmemory_theme)) {
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/php_errorlog"
            );
        }
    }

    foreach ($wpmemory_folders as $wpmemory_folder) {
        //// if (gettype($wpmemory_folder) != 'array')
        //	continue;

        if (trim(empty($wpmemory_folder))) {
            continue;
        }

        foreach (glob($wpmemory_folder) as $wpmemory_filename) {
            if (strpos($wpmemory_filename, "backup") != true) {
                $wpmemory_count++;
                $marray = wpmemory_read_file($wpmemory_filename, 20);

                if (gettype($marray) != "array" or count($marray) < 1) {
                    continue;
                }

                if (count($marray) > 0) {
                    for ($i = 0; $i < count($marray); $i++) {
                        // [05-Aug-2021 08:31:45 UTC]

                        //if (substr($marray[$i], 0, 1) != '[' or empty($marray[$i]))
                        if (
                            substr($marray[$i], 0, 1) != "[" ||
                            stripos($marray[$i], "javascript") === false ||
                            empty($marray[$i])
                        ) {
                            continue;
                        }

                        $pos = strpos($marray[$i], " ");
                        $string = trim(substr($marray[$i], 1, $pos));
                        if (empty($string)) {
                            continue;
                        }
                        // $data_array = explode('-',$string,);
                        $last_date = strtotime($string);
                        // var_dump($last_date);

                        //if ($onlytoday == 1) {
                            if (time() - $last_date < (60 * 60 * ($onlytoday * 24))) {
                                return true;
                            }
                        //} else {
                        //    return true;
                        //}
                    }
                }
            }
        }
    }
    return false;
}
function wpmemory_errors_today($onlytoday)
{
    $wpmemory_count = 0;

    //define('WPMEMORYPATH', plugin_dir_path(__file__));
    //WPMEMORYPATH
    $wpmemory_themePath = get_theme_root();
    $error_log_path = trim(ini_get("error_log"));
    if (
        !is_null($error_log_path) and
        $error_log_path != trim(ABSPATH . "error_log")
    ) {
        $wpmemory_folders = [
            $error_log_path,
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPATH . "/error_log",
            WPMEMORYPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    } else {
        $wpmemory_folders = [
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPATH . "/error_log",
            WPMEMORYPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    }
    $wpmemory_admin_path = str_replace(
        get_bloginfo("url") . "/",
        ABSPATH,
        get_admin_url()
    );
    array_push($wpmemory_folders, $wpmemory_admin_path . "/error_log");
    array_push($wpmemory_folders, $wpmemory_admin_path . "/php_errorlog");
    $wpmemory_plugins = array_slice(scandir(WPMEMORYPATH), 2);
    foreach ($wpmemory_plugins as $wpmemory_plugin) {
        if (is_dir(WPMEMORYPATH . "/" . $wpmemory_plugin)) {
            array_push(
                $wpmemory_folders,
                WPMEMORYPATH . "/" . $wpmemory_plugin . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                WPMEMORYPATH . "/" . $wpmemory_plugin . "/php_errorlog"
            );
        }
    }
    $wpmemory_themes = array_slice(scandir($wpmemory_themePath), 2);
    foreach ($wpmemory_themes as $wpmemory_theme) {
        if (is_dir($wpmemory_themePath . "/" . $wpmemory_theme)) {
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/php_errorlog"
            );
        }
    }

    foreach ($wpmemory_folders as $wpmemory_folder) {
        //// if (gettype($wpmemory_folder) != 'array')
        //	continue;

        if (trim(empty($wpmemory_folder))) {
            continue;
        }

        foreach (glob($wpmemory_folder) as $wpmemory_filename) {
            if (strpos($wpmemory_filename, "backup") != true) {
                $wpmemory_count++;
                $marray = wpmemory_read_file($wpmemory_filename, 20);

                if (gettype($marray) != "array" or count($marray) < 1) {
                    continue;
                }

                if (count($marray) > 0) {
                    for ($i = 0; $i < count($marray); $i++) {
                        // [05-Aug-2021 08:31:45 UTC]

                        if (
                            substr($marray[$i], 0, 1) != "[" or
                            empty($marray[$i])
                        ) {
                            continue;
                        }
                        $pos = strpos($marray[$i], " ");
                        $string = trim(substr($marray[$i], 1, $pos));
                        if (empty($string)) {
                            continue;
                        }
                        // $data_array = explode('-',$string,);
                        $last_date = strtotime($string);


                        // 
                      //  die(var_dump($marray[$i]));
                     // die(var_export(time() - $last_date < 60 * 60 * 24));
                        
                        //var_dump($last_date);

                      //  if ($onlytoday == 2) {
                            if (time() - $last_date < (60 * 60 * ($onlytoday * 24))) {
                                //die(var_export(time() - $last_date < 60 * 60 * 24));
                                return true;
                            }
                       // } else {
                           // return true;
                       // }
                    }
                }
            }
        }
    }
    return false;
}

function wpmemory_errors()
{
    if (isset($_GET["page"])) {
        $page = sanitize_text_field($_GET["page"]);
		if ($page !== 'wp_memory_admin_page')
	    	 return;
    }
    $wpmemory_count = 0;
    define("WPMEMORYPLUGINPATH", plugin_dir_path(__FILE__));
    $wpmemory_themePath = get_theme_root();
    $error_log_path = trim(ini_get("error_log"));
    if (
        !is_null($error_log_path) and
        $error_log_path != trim(ABSPATH . "error_log")
    ) {
        $wpmemory_folders = [
            $error_log_path,
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPLUGINPATH . "/error_log",
            WPMEMORYPLUGINPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    } else {
        $wpmemory_folders = [
            ABSPATH . "error_log",
            ABSPATH . "php_errorlog",
            WPMEMORYPLUGINPATH . "/error_log",
            WPMEMORYPLUGINPATH . "/php_errorlog",
            $wpmemory_themePath . "/error_log",
            $wpmemory_themePath . "/php_errorlog",
        ];
    }
    $wpmemory_admin_path = str_replace(
        get_bloginfo("url") . "/",
        ABSPATH,
        get_admin_url()
    );
    array_push($wpmemory_folders, $wpmemory_admin_path . "/error_log");
    array_push($wpmemory_folders, $wpmemory_admin_path . "/php_errorlog");
    $wpmemory_plugins = array_slice(scandir(WPMEMORYPLUGINPATH), 2);
    foreach ($wpmemory_plugins as $wpmemory_plugin) {
        if (is_dir(WPMEMORYPLUGINPATH . "/" . $wpmemory_plugin)) {
            array_push(
                $wpmemory_folders,
                WPMEMORYPLUGINPATH . "/" . $wpmemory_plugin . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                WPMEMORYPLUGINPATH . "/" . $wpmemory_plugin . "/php_errorlog"
            );
        }
    }
    $wpmemory_themes = array_slice(scandir($wpmemory_themePath), 2);
    foreach ($wpmemory_themes as $wpmemory_theme) {
        if (is_dir($wpmemory_themePath . "/" . $wpmemory_theme)) {
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/error_log"
            );
            array_push(
                $wpmemory_folders,
                $wpmemory_themePath . "/" . $wpmemory_theme . "/php_errorlog"
            );
        }
    }
    // echo WPMEMORYURL.'images/logo.png';
    //echo "<br />";
    //echo '<img src="' . esc_url(WPMEMORYURL) . 'images/logo.png" alt="logo">';
    echo "<h1>" . esc_attr__("Errors", "wpmemory") . "</h1>";
    echo "<center>";

    echo "<h2>";
    echo esc_attr__(
        "Your site has errors. Here are the last lines of the error log files.",
        "wpmemory"
    );
    echo "</h2>";

    //2023
    //die(var_export(wpmemory_errors_today(2)));

    
    if (wpmemory_javascript_errors_today(2) or wpmemory_errors_today(2)) {
        echo '<h3 style="color: red;">';
        echo esc_attr__(
            "Our plugin can't function as intended. Errors, including JavaScript errors, may lead to visual problems or disrupt functionality, from minor glitches to critical site failures. Promptly address these issues before continuing because these problems will persist even if you deactivate our plugin.Notice that the PHP error system does not capture JavaScript errors. Only our plugin captures them.",
            "wpmemory"
        );
        echo "</h3>";
    }
    //end 2023

    echo "</center>";
    echo "<h4>";
    echo esc_attr__(
        "For bigger files, download and open them in your local computer.",
        "wpmemory"
    );

    echo "<br />";

    echo '<a href="https://wptoolsplugin.com/site-language-error-can-crash-your-site/" >';
    echo esc_attr(__("Learn more about errors and warnings...", "wpmemory")) .
        ".";
    echo "</a>";

    echo "<br />";

    echo "</h4>";

    //var_export($wpmemory_folders);

    foreach ($wpmemory_folders as $wpmemory_folder) {
        foreach (glob($wpmemory_folder) as $wpmemory_filename) {
            if (strpos($wpmemory_filename, "backup") != true) {

                echo "<hr>";
                echo "<strong>";
                echo esc_attr(wpmemory_sizeFilter(filesize($wpmemory_filename)));
                echo " - ";
                echo esc_attr($wpmemory_filename);
                echo "</strong>";
                $wpmemory_count++;

                $marray = wpmemory_read_file($wpmemory_filename, 3000);

                // die(var_export($marray));


                if (gettype($marray) != "array" or count($marray) < 1) {
                    continue;
                }

                //die(var_export($marray[0]));


                $total = count($marray);


                // die(var_export($total));


                if (count($marray) > 0) {
                    echo '<textarea style="width:99%;" id="anti_hacker" rows="12">';

                    if ($total > 1000) {
                        $total = 1000;
                    }


                    for ($i = 0; $i < $total; $i++) {
                        if (strpos(trim($marray[$i]), "[") !== 0) {
                            continue; // Skip lines without correct date format
                        }


                        $logs = [];

                        $line = trim($marray[$i]);
                        if(empty($line))
                           continue;
                        


                        //  stack trace
                        //[30-Sep-2023 11:28:52 UTC] PHP Stack trace:
                        $pattern = "/PHP Stack trace:/";
                        if (preg_match($pattern, $line, $matches)) {
                            continue;
                        }
                        $pattern =
                            "/\d{4}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} UTC\] PHP \d+\./";
                        if (preg_match($pattern, $line, $matches)) {
                            continue;
                        }
                        //  end stack trace

                        // Javascript ?
                        if (strpos($line, "Javascript") !== false) {
                            $is_javascript = true;
                        } else {
                            $is_javascript = false;
                        }

                        if ($is_javascript) {
                            $matches = [];

                            // die($line);

                            $apattern = [];
                            $apattern[] =
                                "/(Error|Syntax|Type|TypeError|Reference|ReferenceError|Range|Eval|URI|Error .*?): (.*?) - URL: (https?:\/\/\S+).*?Line: (\d+).*?Column: (\d+).*?Error object: ({.*?})/";

                            //$apattern[] =
                            //    "/(Error|Syntax|Type|TypeError|Reference|ReferenceError|Range|Eval|URI|Error .*?): (.*?) - URL: (https?:\/\/\S+).*?Line: (\d+)/";


                            $apattern[] =
                                "/(SyntaxError|Error|Syntax|Type|TypeError|Reference|ReferenceError|Range|Eval|URI|Error .*?): (.*?) - URL: (https?:\/\/\S+).*?Line: (\d+)/";
            
                            // Google Maps !
                            //$apattern[] = "/Script error(?:\. - URL: (https?:\/\/\S+))?/i";

                            $pattern = $apattern[0];

                            for ($j = 0; $j < count($apattern); $j++) {
                                if (
                                    preg_match($apattern[$j], $line, $matches)
                                ) {
                                    $pattern = $apattern[$j];
                                    break;
                                }
                            }

                            /*
                                //$pattern = "/Line: (\d+)/";
                                 preg_match($pattern, $line, $matches);
                                print_r($matches);
                                die('------------xxx---------------');
                                die($line);
                                */

                            if (preg_match($pattern, $line, $matches)) {
                                $matches[1] = str_replace(
                                    "Javascript ",
                                    "",
                                    $matches[1]
                                );

                                if (count($matches) == 2) {
                                    $log_entry = [
                                        "Date" => substr($line, 1, 20),
                                        "Message Type" => "Script error",
                                        "Problem Description" => "N/A",
                                        "Script URL" => $matches[1],
                                        "Line" => "N/A",
                                    ];
                                } else {
                                    $log_entry = [
                                        "Date" => substr($line, 1, 20),
                                        "Message Type" => $matches[1],
                                        "Problem Description" => $matches[2],
                                        "Script URL" => $matches[3],
                                        "Line" => $matches[4],
                                    ];
                                }




                                $script_path = $matches[3];
                                $script_info = pathinfo($script_path);
        
        
                                // Dividir o nome do script com base em ":"
                                $parts = explode(":", $script_info["basename"]);
        
                                // O nome do script agora está na primeira parte
                                $scriptName = $parts[0];
        
                                $log_entry["Script Name"] = $scriptName; // Get the script name
        
                                $log_entry["Script Location"] =
                                    $script_info["dirname"]; // Get the script location
        
                                if($log_entry["Script Location"] == 'http:' or $log_entry["Script Location"] == 'https:' )
                                  $log_entry["Script Location"] = $matches[3];


                                if (
                                    strpos(
                                        $log_entry["Script URL"],
                                        "/wp-content/plugins/"
                                    ) !== false
                                ) {
                                    // O erro ocorreu em um plugin
                                    $parts = explode(
                                        "/wp-content/plugins/",
                                        $log_entry["Script URL"]
                                    );
                                    if (count($parts) > 1) {
                                        $plugin_parts = explode("/", $parts[1]);
                                        $log_entry["File Type"] = "Plugin";
                                        $log_entry["Plugin Name"] =
                                            $plugin_parts[0];
                                     //   $log_entry["Script Location"] =
                                      //      "/wp-content/plugins/" .
                                     //       $plugin_parts[0];
                                    }
                                } elseif (
                                    strpos(
                                        $log_entry["Script URL"],
                                        "/wp-content/themes/"
                                    ) !== false
                                ) {
                                    // O erro ocorreu em um tema
                                    $parts = explode(
                                        "/wp-content/themes/",
                                        $log_entry["Script URL"]
                                    );
                                    if (count($parts) > 1) {
                                        $theme_parts = explode("/", $parts[1]);
                                        $log_entry["File Type"] = "Theme";
                                        $log_entry["Theme Name"] =
                                            $theme_parts[0];
                                       // $log_entry["Script Location"] =
                                       //     "/wp-content/themes/" .
                                       //     $theme_parts[0];
                                    }
                                } else {
                                    // Caso não seja um tema nem um plugin, pode ser necessário ajustar o comportamento aqui.
                                    //$log_entry["Script Location"] = $matches[1];
                                }

                                // Extrair o nome do script do URL
                                $script_name = basename(
                                    parse_url(
                                        $log_entry["Script URL"],
                                        PHP_URL_PATH
                                    )
                                );
                                $log_entry["Script Name"] = $script_name;

                                //echo $line."\n";

                                // Exemplo de saída:
                                if (isset($log_entry["Date"])) {
                                    echo "DATE: {$log_entry["Date"]}\n";
                                }
                                if (isset($log_entry["Message Type"])) {
                                    echo "MESSAGE TYPE: (Javascript) {$log_entry["Message Type"]}\n";
                                }
                                if (isset($log_entry["Problem Description"])) {
                                    echo "PROBLEM DESCRIPTION: {$log_entry["Problem Description"]}\n";
                                }

                                if (isset($log_entry["Script Name"])) {
                                    echo "SCRIPT NAME: {$log_entry["Script Name"]}\n";
                                }
                                if (isset($log_entry["Line"])) {
                                    echo "LINE: {$log_entry["Line"]}\n";
                                }
                                if (isset($log_entry["Column"])) {
                                    //	echo "COLUMN: {$log_entry['Column']}\n";
                                }
                                if (isset($log_entry["Error Object"])) {
                                    //	echo "ERROR OBJECT: {$log_entry['Error Object']}\n";
                                }
                                if (isset($log_entry["Script Location"])) {
                                    echo "SCRIPT LOCATION: {$log_entry["Script Location"]}\n";
                                }
                                if (isset($log_entry["Plugin Name"])) {
                                    echo "PLUGIN NAME: {$log_entry["Plugin Name"]}\n";
                                }
                                if (isset($log_entry["Theme Name"])) {
                                    echo "THEME NAME: {$log_entry["Theme Name"]}\n";
                                }

                                echo "------------------------\n";
                                continue;
                            } else {
                                // echo "-----------x-------------\n";
                                echo $line;
                                echo "\n-----------x------------\n";
                            }
                            continue;
                            // END JAVASCRIPT
                        } else {
                            /* ----- PHP // */


                            // continue;


                            $apattern = [];
                            $apattern[] =
                                "/^\[.*\] PHP (Warning|Error|Notice|Fatal error|Parse error): (.*) in \/([^ ]+) on line (\d+)/";
                            $apattern[] =
                                "/^\[.*\] PHP (Warning|Error|Notice|Fatal error|Parse error): (.*) in \/([^ ]+):(\d+)$/";

                            $pattern = $apattern[0];

                            for ($j = 0; $j < count($apattern); $j++) {
                                if (
                                    preg_match($apattern[$j], $line, $matches)
                                ) {
                                    $pattern = $apattern[$j];
                                    break;
                                }
                            }

                            if (preg_match($pattern, $line, $matches)) {
                                //die(var_export($matches));

                                /*              
                                    0 => '[29-Sep-2023 11:44:22 UTC] PHP Parse error:  syntax error, unexpected \'preg_match\' (T_STRING) in /home/realesta/public_html/wp-content/plugins/wptools/functions/functions.php on line 2066',
                                    1 => 'Parse error',
                                    2 => ' syntax error, unexpected \'preg_match\' (T_STRING)',
                                    3 => 'home/realesta/public_html/wp-content/plugins/wptools/functions/functions.php',
                                    4 => '2066',
                                    */

                                $log_entry = [
                                    "Date" => substr($line, 1, 20), // Extract date from line
                                    "News Type" => $matches[1],
                                    "Problem Description" => wpmemory_strip_strong(
                                        $matches[2]
                                    ),
                                ];



                                $script_path = $matches[3];
                                $script_info = pathinfo($script_path);

                                // Dividir o nome do script com base em ":"
                                $parts = explode(":", $script_info["basename"]);

                                // O nome do script agora está na primeira parte
                                $scriptName = $parts[0];

                                $log_entry["Script Name"] = $scriptName; // Get the script name

                                $log_entry["Script Location"] =
                                    $script_info["dirname"]; // Get the script location

                                $log_entry["Line"] = $matches[4];



                                // Check if the "Script Location" contains "/plugins/" or "/themes/"
                                if (
                                    strpos(
                                        $log_entry["Script Location"],
                                        "/plugins/"
                                    ) !== false
                                ) {
                                    // Extract the plugin name
                                    $parts = explode(
                                        "/plugins/",
                                        $log_entry["Script Location"]
                                    );
                                    if (count($parts) > 1) {
                                        $plugin_parts = explode("/", $parts[1]);
                                        $log_entry["File Type"] = "Plugin";
                                        $log_entry["Plugin Name"] =
                                            $plugin_parts[0];
                                    }
                                } elseif (
                                    strpos(
                                        $log_entry["Script Location"],
                                        "/themes/"
                                    ) !== false
                                ) {
                                    // Extract the theme name
                                    $parts = explode(
                                        "/themes/",
                                        $log_entry["Script Location"]
                                    );
                                    if (count($parts) > 1) {
                                        $theme_parts = explode("/", $parts[1]);
                                        $log_entry["File Type"] = "Theme";
                                        $log_entry["Theme Name"] =
                                            $theme_parts[0];
                                    }
                                }
                            } else {
                                // stack trace...
                                $pattern = "/\[.*?\] PHP\s+\d+\.\s+(.*)/";
                                preg_match($pattern, $line, $matches);

                                if (!preg_match($pattern, $line)) {
                                    echo "-----------y-------------\n";
                                    echo $line;
                                    echo "\n-----------y------------\n";
                                }
                                continue;
                            }

                            //$in_error_block = false; // End the error block
                            $logs[] = $log_entry; // Add this log entry to the array of logs

                            foreach ($logs as $log) {
                                if (isset($log["Date"])) {
                                    echo "DATE: {$log["Date"]}\n";
                                }
                                if (isset($log["News Type"])) {
                                    echo "MESSAGE TYPE: {$log["News Type"]}\n";
                                }
                                if (isset($log["Problem Description"])) {
                                    echo "PROBLEM DESCRIPTION: {$log["Problem Description"]}\n";
                                }

                                // Check if the 'Script Name' key exists before printing
                                if (
                                    isset($log["Script Name"]) and
                                    !empty(trim($log["Script Name"]))
                                ) {
                                    echo "SCRIPT NAME: {$log["Script Name"]}\n";
                                }

                                // Check if the 'Line' key exists before printing
                                if (isset($log["Line"])) {
                                    echo "LINE: {$log["Line"]}\n";
                                }

                                // Check if the 'Script Location' key exists before printing
                                if (isset($log["Script Location"])) {
                                    echo "SCRIPT LOCATION: {$log["Script Location"]}\n";
                                }

                                // Check if the 'File Type' key exists before printing
                                if (isset($log["File Type"])) {
                                    // echo "FILE TYPE: {$log['File Type']}\n";
                                }

                                // Check if the 'Plugin Name' key exists before printing
                                if (
                                    isset($log["Plugin Name"]) and
                                    !empty(trim($log["Plugin Name"]))
                                ) {
                                    echo "PLUGIN NAME: {$log["Plugin Name"]}\n";
                                }

                                // Check if the 'Theme Name' key exists before printing
                                if (isset($log["Theme Name"])) {
                                    echo "THEME NAME: {$log["Theme Name"]}\n";
                                }

                                echo "------------------------\n";
                            }
                        }
                        // end if PHP ...
                    } // end for...

                    echo "</textarea>";

                        
  
                }
                echo "<br />";
            }
        }
    }
    echo "<p>" .
        esc_attr(__("Log Files found", "wpmemory")) .
        ": " .
        esc_attr($wpmemory_count) .
        "</p>";
}


// Bill202309

function wpmemory_read_file($file, $lines)
{

    // Precisa o so uma linha?
    // remover stack trace ? 


    try {
        $handle = fopen($file, "r");
    } catch (Exception $e) {
        return "";
    }
    if (!$handle) {
        return "";
    }

    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];

    while ($linecounter > 0) {
        $t = " ";
        // acha ultima quebra de linha indo para traz... 
        // partindo da ultima posicao menos 1.
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                // chegou no inicio?
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }

        $linecounter--;

        // chegou no inicio?
        if ($beginning) {
            rewind($handle);
        }

        $line = fgets($handle);
        if ($line === false) {
            break; // Não há mais linhas para ler
        }
        $text[] = $line;

        if ($beginning) {
            break;
        }
    }

    fclose($handle);

    // Inverte o array para que as linhas sejam na ordem correta
    // $text = array_reverse($text);

    //die(var_export(count($text)));

    return $text;

    return implode("", $text);
}

function wpmemory_sizeFilter($bytes)
{
	$label = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB');
	for ($i = 0; $bytes >= 1024 && $i < (count($label) - 1); $bytes /= 1024, $i++);
	return (round($bytes, 2) . " " . $label[$i]);
}

/*
function wpmemory_read_file($file, $lines)
{
    try {
        $handle = fopen($file, "r");
    } catch (Exception $e) {
        return "";
    }
    if (!$handle) {
        return "";
    }
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];
    while ($linecounter > 0) {
        $t = " ";
        // acha ultima quebra de linha indo para traz... 
        // partindo da ultima posicao menos 1.
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                // chegou no inicio?
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        // chegou no inicio?
        if ($beginning) {
            rewind($handle);
        }
        $line = fgets($handle);
        if ($line === false) {
            break; // Não há mais linhas para ler
        }
        $text[] = $line;
        if ($beginning) {
            break;
        }
    }
    fclose($handle);
    // Inverte o array para que as linhas sejam na ordem correta
    // $text = array_reverse($text);
    //die(var_export(count($text)));
    return $text;
    return implode("", $text);
}
*/



if(is_admin()){
	$wpmemory_skip = false;
	$wpmemory_definedFunctions = get_defined_functions();

	foreach ($wpmemory_definedFunctions['user'] as $functionName) {
		if (strpos($functionName, '_alert_errors') !== false) {
			$wpmemory_skip = true;
		}
	}

	if(!$wpmemory_skip)
		{

	// errors ////
	function wpmemory_alert_errors2()
	{
		global $wp_admin_bar;
		//global $wpmemory_radio_server_load;
		$site = WPMEMORYHOMEURL . "admin.php?page=wp_memory_admin_page&tab=errors";
		$args = array(
			'id' => 'wpmemory-alert',
			'title' => '<div class="wpmemory-alert-logo"></div><span id="wpmemory_alert_errors" class="text">'. esc_attr__("Site Errors","wpmemory").'</td>',
			'href' => $site,
			'meta' => array(
				'class' => 'wpmemory-alert',
				'title' => ''
			)
		);
		$wp_admin_bar->add_node($args);
		echo '<style>';
		if (wpmemory_errors_today(1)) {
			echo '#wpadminbar .wpmemory-alert  {
			 background: red !important; 
				color: white !important;
				width: 110px;
				}';
		} 
		$logourl = WPMEMORYIMAGES . "/bell.png";
		echo '#wpadminbar .wpmemory-alert-logo  {
			background-image: url("' . esc_url($logourl) . '");
			float: left;
			width: 26px;
			height: 30px;
			background-repeat: no-repeat;
			background-position: 0 6px;
			background-size: 20px;
			}';
		echo '</style>';
	}

			function wpmemory_alert_errors3()
			{
				global $wp_admin_bar;
				$site = WPMEMORYHOMEURL . "admin.php?page=wp_memory_admin_page&tab=dashboard";
				$args = array(
					'id' => 'wpmemory-alert-memory',
					'title' => '<div class="wpmemory-alert-logo"></div><span id="wpmemory_alert_memory" class="text">'. esc_attr__("Memory Issue","wpmemory").'</td>',
					'href' => $site,
					'meta' => array(
						'class' => 'wpmemory-alert-memory',
						'title' => ''
					)
				);
				$wp_admin_bar->add_node($args);
				echo '<style>';
					echo '#wpadminbar .wpmemory-alert-memory  {
					background: red !important; 
						color: white !important;
						width: 110px;
						}';
				//} 
				$logourl = WPMEMORYIMAGES . "/bell.png";
				echo '#wpadminbar .wpmemory-alert-logo  {
					background-image: url("' . esc_url($logourl) . '");
					float: left;
					width: 26px;
					height: 30px;
					background-repeat: no-repeat;
					background-position: 0 6px;
					background-size: 20px;
					}';
				echo '</style>';
			}
		if(wpmemory_javascript_errors_today(1) or wpmemory_errors_today(1)){
		    add_action('admin_bar_menu', 'wpmemory_alert_errors2', 999);
			add_action('admin_notices', 'wpmemory_show_dismissible_notification');
		}
		$sbb_memory = wpmemory_check_memory();
		if ( $sbb_memory['msg_type'] == 'notok' ) {
			return;
		}
		else{
			$sbb_memory_free = $sbb_memory['wp_limit']  - $sbb_memory['usage']; 
			if ( $sbb_memory['percent'] > .7  or $sbb_memory_free < 30 ) {
				add_action('admin_bar_menu', 'wpmemory_alert_errors3', 999);
				add_action('admin_notices', 'wpmemory_show_dismissible_notification2');
			}
		}
		function wpmemory_show_dismissible_notification() {
			// Check if the notification was already shown today
			$last_notification_date = get_option('wpmemory_last_notification_date');
			$today = date('Y-m-d');
			if ($last_notification_date === $today) {
			  return; // Notification already shown today
			}
			$message = __('Errors have been detected on this site. ', 'wpmemory') . '<a href="' . esc_url(WPMEMORYHOMEURL . "admin.php?page=wp_memory_admin_page&tab=errors") . '">' . __('Learn more', 'wpmemory') . '</a>';
			// Display the notification HTML
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p style="color: red;">' . wp_kses_post($message) . '</p>';
			echo '</div>';
			// Update the last notification date
			update_option('wpmemory_last_notification_date', $today);
		}
		add_action('admin_notices', 'wpmemory_show_dismissible_notification');
		function wpmemory_show_dismissible_notification2() {
			// Check if the notification was already shown today
			$last_notification_date = get_option('wpmemory_last_notification_date2');
			$today = date('Y-m-d');
			if ($last_notification_date === $today) {
				return; // Notification already shown today
			}
			$message = __('Memory issues have been detected on this site. ', 'wpmemory') . '<a href="' . esc_url(WPMEMORYHOMEURL . "admin.php?page=wp_memory_admin_page") . '">' . __('Learn more', 'wpmemory') . '</a>';
			// Display the notification HTML
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p style="color: red;">' . wp_kses_post($message) . '</p>';
			echo '</div>';
			// Update the last notification date
			update_option('wpmemory_last_notification_date2', $today);
		}
	}
}
//error_log('Test');