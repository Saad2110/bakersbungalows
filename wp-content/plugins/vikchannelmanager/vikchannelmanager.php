<?php
/*
Plugin Name:  VikChannelManager
Plugin URI:   https://vikwp.com
Description:  Hotels Channel Manager complementary plugin of Vik Booking.
Version:      1.8.11
Author:       E4J s.r.l.
Author URI:   https://vikwp.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  vikchannelmanager
Domain Path:  /languages
*/

// No direct access
defined('ABSPATH') or die('No script kiddies please!');

// autoload dependencies
try
{
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'autoload.php';
}
catch (RuntimeException $e)
{
	// VikBooking is not installed!
	?>
	<div class="notice is-dismissible notice-warning">
		<p>
			<?php echo $e->getMessage(); ?>
		</p>
	</div>
	<?php

	// return to avoid breaking the website
	return;
}

// handle install/uninstall
register_activation_hook(__FILE__, array('VikChannelManagerInstaller', 'activate'));
register_deactivation_hook(__FILE__, array('VikChannelManagerInstaller', 'deactivate'));
register_uninstall_hook(__FILE__, array('VikChannelManagerInstaller', 'uninstall'));

// init Installer
add_action('init', array('VikChannelManagerInstaller', 'onInit'));
add_action('plugins_loaded', array('VikChannelManagerInstaller', 'update'));

// init language
VikChannelManagerBuilder::loadLanguage();
// init pagination layout
VikChannelManagerBuilder::setupPaginationLayout();
// init html helpers
VikChannelManagerBuilder::setupHtmlHelpers();

/**
 * Added support for screen options.
 * Parameters such as the list limit can be changed from there.
 * 
 * Due to WordPress 5.4.2 changes, we need to attach
 * VikChannelManager to a dedicated hook in order to 
 * allow the update of the list limit.
 *
 * @since 1.7.5
 */
add_action('current_screen', array('VikChannelManagerScreen', 'options'));
add_filter('set-screen-option', array('VikChannelManagerScreen', 'saveOption'), 10, 3);
add_filter('set_screen_option_vikchannelmanager_list_limit', array('VikChannelManagerScreen', 'saveOption'), 10, 3);

// init Session
add_action('init', array('JSessionHandler', 'start'), 1);
add_action('wp_logout', array('JSessionHandler', 'destroy'));

// filter page link to rewrite URI
add_action('plugins_loaded', function()
{
	global $pagenow;

	$app   = JFactory::getApplication(); 
	$input = $app->input;

	// check if the URI contains option=com_vikchannelmanager
	if ($input->get('option') == 'com_vikchannelmanager')
	{
		// make sure we are not contacting the AJAX and POST end-points
		if (!wp_doing_ajax() && $pagenow != 'admin-post.php')
		{
			/**
			 * Include page in query string only if we are in the back-end,
			 * because WordPress 5.5 seems to break the page loading in case
			 * that argument has been included in query string.
			 *
			 * It is not needed to include this argument in the front-end
			 * as the page should lean on the reached shortcode only.
			 *
			 * @since 1.7.6
			 */
			if ($app->isAdmin())
			{
				// inject page=vikchannelmanager in GET superglobal
				$input->get->set('page', 'vikchannelmanager');
			}
		}
		else
		{
			// inject action=vikchannelmanager in GET superglobal for AJAX and POST requests
			$_GET['action'] = 'vikchannelmanager';
		}
	}
	elseif ($input->get('page') == 'vikchannelmanager' || $input->get('action') == 'vikchannelmanager')
	{
		// inject option=com_vikchannelmanager in GET superglobal
		$_GET['option'] = 'com_vikchannelmanager';
	}
});

// resolve possible conflicts with malcoded Themes/Plugins like "betheme"
add_action('plugins_loaded', function()
{
    $app = JFactory::getApplication();

    if ($app->input->get->get('page') === 'vikchannelmanager' && $app->input->get->getBool('forcecheck'))
    {
        $app->input->get->delete('forcecheck');
    }
});

// process the request and obtain the response
add_action('init', function()
{
	$app 	= JFactory::getApplication();
	$input 	= $app->input;

	// process VikChannelManager only if it has been requested via GET or POST
	if ($input->get('option') == 'com_vikchannelmanager' || $input->get('page') == 'vikchannelmanager')
	{
		VikChannelManagerBody::process();
	}
});

// handle AJAX requests
add_action('wp_ajax_vikchannelmanager', 'handle_vikchannelmanager_ajax');
add_action('wp_ajax_nopriv_vikchannelmanager', 'handle_vikchannelmanager_ajax');

function handle_vikchannelmanager_ajax()
{
	VikChannelManagerBody::getHtml();

	// die to get a valid response
	wp_die();
}

// setup admin menu
add_action('admin_menu', array('VikChannelManagerBuilder', 'setupAdminMenu'));

// register widgets
add_action('widgets_init', array('VikChannelManagerBuilder', 'setupWidgets'));

// the callback is fired before the VCM controller is dispatched
add_action('vikchannelmanager_before_dispatch', function()
{
	$app 	= JFactory::getApplication();
	$user 	= Jfactory::getUser();

	// initialize timezone handler
	JDate::getDefaultTimezone();
	date_default_timezone_set($app->get('offset', 'UTC'));

	// check if the user is authorised to access the back-end (only if the client is 'admin')
	if ($app->isAdmin() && !$user->authorise('core.manage', 'com_vikchannelmanager'))
	{
		if ($user->guest)
		{
			// if the user is not logged, redirect to login page
			$app->redirect('index.php');
			exit;
		}
		else
		{
			// otherwise raise an exception
			wp_die(
				'<h1>' . JText::_('FATAL_ERROR') . '</h1>' .
				'<p>' . JText::_('RESOURCE_AUTH_ERROR') . '</p>',
				403
			);
		}
	}

	// main library
	require_once VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php';
	// configuration
	require_once VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vcm_config.php';

	/**
	 * Normalize db driver and script declarations (if necessary).
	 * 
	 * @since 	1.7.5
	 */
	VikChannelManager::normalizeExecution();

	if ($app->isAdmin())
	{
		require_once VCM_ADMIN_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'helper.php';
		
		new OrderingManager('com_vikchannelmanager', 'vcmordcolumn', 'vcmordtype');

		// Trigger reports
		VikChannelManager::notifyReportsData();

		// Trigger reminders
		VikChannelManager::checkSubscriptionReminder();

		// Trigger auto bulk actions
		VikChannelManager::autoBulkActions();
	}
});

// the callback is fired once the VCM controller has been dispatched
add_action('vikchannelmanager_after_dispatch', function()
{
	// load assets after dispatching the controller to avoid
	// including JS and CSS when an AJAX function exits or dies
	VikChannelManagerAssets::load();

	/**
	 * Load javascript core.
	 *
	 * @since 1.1.8
	 */
	JHtml::_('behavior.core');

	// restore standard timezone
	date_default_timezone_set(JDate::getDefaultTimezone());

	/**
	 * @note 	when the headers have been sent or when 
	 * 			the request is AJAX, the assets (CSS and JS) are
	 * 			appended to the document after the 
	 * 			response dispatched by the controller.
	 */
});

/**
 * Action triggered before loading the text domain.
 * For Vik Booking, VCM needs to attach both hanlders.
 *
 * @param 	string 	$domain    The plugin text domain to look for.
 * @param 	string 	$basePath  The base path containing the languages.
 * @param 	mixed   $langtag   An optional language tag to use.
 * 
 * @since   1.8.1
 */
add_action('vik_plugin_before_load_language', function($domain, $basePath, $langtag)
{
	if ($domain != 'vikbooking')
	{
		// do not proceed, as no language handlers for VCM are needed
		return;
	}

	$app 	= JFactory::getApplication();
	$input 	= $app->input;
	if ($input->get('option') != 'com_vikchannelmanager' && $input->get('page') == 'vikchannelmanager')
	{
		// do not proceed, it is not Vik Channel Manager that is loading the VBO language
		return;
	}

	// VBO base libraries path for language handlers
	$handler_base = str_replace('vikchannelmanager', $domain, VIKCHANNELMANAGER_LIBRARIES) . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;

	$lang = JFactory::getLanguage();

	// load back-end language handler
	$lang->attachHandler($handler_base . 'admin.php', $domain);
	// load front-end language handler
	$lang->attachHandler($handler_base . 'site.php', $domain);
}, 10, 3);

/**
 * Action triggered before loading the text domain.
 * Loads the language handlers when needed from a 
 * different application client.
 *
 * @param 	string 	$domain    The plugin text domain to look for.
 * @param 	string 	$basePath  The base path containing the languages.
 * @param 	mixed   $langtag   An optional language tag to use.
 *
 * @since 	1.8.11
 */
add_action('vik_plugin_before_load_language', function($domain, $basePath, $langtag)
{
	if ($domain != 'vikchannelmanager')
	{
		// do not go ahead
		return;
	}

	$app  = JFactory::getApplication();
	$lang = JFactory::getLanguage();

	$handler = VIKCHANNELMANAGER_LIBRARIES . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR;

	// check if we are in the site client and the system
	// needs to load the language used in the back-end
	if ($app->isSite() && $basePath == VIKCHANNELMANAGER_ADMIN_LANG)
	{
		// load back-end language handler
		$lang->attachHandler($handler . 'admin.php', $domain);
	}
	// check if we are in the admin client and the system
	// needs to load the language used in the front-end
	else if ($app->isAdmin() && $basePath == VIKCHANNELMANAGER_SITE_LANG)
	{
		// load front-end language handler
		$lang->attachHandler($handler . 'site.php', $domain);
	}
}, 10, 3);

/**
 * Added support for Loco Translate.
 * In case some translations have been edited by using this plugin,
 * we should look within the Loco Translate folder to check whether
 * the requested translation is available.
 *
 * @param 	boolean  $loaded  True if the translation has been already loaded.
 * @param 	string 	 $domain  The plugin text domain to load.
 *
 * @return 	boolean  True if a new translation is loaded.
 *
 * @since 	1.8.11
 */
add_filter('vik_plugin_load_language', function($loaded, $domain)
{
	// proceed only in case the translation hasn't been loaded
	// and Loco Translate plugin is installed
	if (!$loaded && is_dir(WP_LANG_DIR . DIRECTORY_SEPARATOR . 'loco'))
	{
		// Build LOCO path.
		// Since load_plugin_textdomain accepts only relative paths, 
		// we should go back to the /wp-contents/ folder first.
		$loco = implode(DIRECTORY_SEPARATOR, array('..', 'languages', 'loco', 'plugins'));

		// try to load the plugin translation from Loco folder
		$loaded = load_plugin_textdomain($domain, false, $loco);
	}

	return $loaded;
}, 10, 2);

// End-point for front-end post actions.
// The end-point URL must be built as .../wp-admin/admin-post.php
// and requires $_POST['action'] == 'vikchannelmanager' to be submitted through a form or GET.
add_action('admin_post_vikchannelmanager', 'handle_vikchannelmanager_endpoint'); 			// if the user is logged in
add_action('admin_post_nopriv_vikchannelmanager', 'handle_vikchannelmanager_endpoint'); 	// if the user in not logged in

// handle POST end-point
function handle_vikchannelmanager_endpoint()
{
	// get PLAIN response
	echo VikChannelManagerBody::getResponse();
}
