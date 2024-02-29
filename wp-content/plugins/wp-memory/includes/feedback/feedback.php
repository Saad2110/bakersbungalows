<?php  namespace WPmemory_feedback{
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
	if ( is_multisite() ) {
		return;
	}
	if ( __NAMESPACE__ == 'WPmemory_feedback' ) {
		define( __NAMESPACE__ . '\PRODCLASS', 'wp_memory' );
		define( __NAMESPACE__ . '\VERSION', WPMEMORYVERSION );
		define( __NAMESPACE__ . '\PLUGINHOME', 'https://wpmemory.com' );
		define( __NAMESPACE__ . '\PRODUCTNAME', 'WP Memory Plugin' );
		define( __NAMESPACE__ . '\LANGUAGE', 'wp-memory'  );
		define( __NAMESPACE__ . '\PAGE', 'settings' );
		define( __NAMESPACE__ . '\URL', WPMEMORYURL );
	}
	// somente um, por enquanto
	define( __NAMESPACE__ . '\OPTIN', 'bill-vote' );
	define( __NAMESPACE__ . '\DINSTALL', 'bill_installed' );
	class Bill_mVote {
		protected static $namespace         = __NAMESPACE__;
		protected static $bill_plugin_url   = URL;
		protected static $bill_class        = PRODCLASS;
		protected static $bill_prod_version = VERSION;
		function __construct() {
			add_action( 'load-plugins.php', array( __CLASS__, 'init' ) );
			add_action( 'wp_ajax_vote', array( __CLASS__, 'vote' ) );
		}
		public static function init() {
			$vote = get_option( OPTIN );
            // var_dump($vote);
			$timeinstall = get_option( DINSTALL, '' );
			if ( $timeinstall == '' ) {
				 $w = update_option( DINSTALL, time() );
				if ( ! $w ) {
					add_option( DINSTALL, time() );
				}
				$timeinstall = time();
			}



			$timeout = time() > ( $timeinstall + 60 * 60 * 24 * 3 );

			if ( in_array( $vote, array( 'yes', 'no' ) ) || ! $timeout ) {
				// debug
				return;
			}
            add_action( 'in_admin_footer', array( __CLASS__, 'message' ) );
			add_action( 'admin_head', array( __CLASS__, 'register' ) );
			add_action( 'admin_footer', array( __CLASS__, 'enqueue' ) );
		}
		public static function register() {
			  wp_enqueue_style( PRODCLASS, URL . 'includes/feedback/feedback-plugin.css' );
			  wp_register_script( PRODCLASS, URL . 'includes/feedback/feedback.js', array( 'jquery' ), VERSION, true );
		}
		public static function enqueue() {
			  wp_enqueue_style( PRODCLASS );
			  wp_enqueue_script( PRODCLASS );
		}
		public static function vote() {
			  $vote = sanitize_key( $_GET['vote'] );
			  // http://boatplugin.com/wp-admin/admin-ajax.php?action=vote&vote=no
			if ( ! is_user_logged_in() || ! in_array( $vote, array( 'yes', 'no', 'later' ) ) ) {
				die( 'error' );
			}
			$r = update_option( OPTIN, $vote );
			if ( ! $r ) {
				 add_option( OPTIN, $vote );
			}
			if ( $vote === 'later' ) {
				update_option( DINSTALL, time() );
			}
			wp_die( 'OK: ' . $vote );
		}
		public static function message() {
			?>
		<div class="<?php echo esc_attr( PRODCLASS ); ?>-wrap-vote" style="display:block">
			<!-- <div class="bill-vote-wrap"> -->
				<div class="bill-vote-gravatar"><a href="https://profiles.wordpress.org/sminozzi" target="_blank"><img src="https://en.gravatar.com/userimage/94727241/31b8438335a13018a1f52661de469b60.jpg?size=100" alt="<?php _e( 'Bill Minozzi', 'wp-memory'  ); ?>" width="70" height="70"></a></div>
				<div class="bill-vote-message">
					<p>
				  <?php
					 esc_attr_e( 'Hello, my name is Bill Minozzi', 'wp-memory'  );
					 
					// echo ' ' . esc_attr( PRODUCTNAME );
					 echo '. ';
					// esc_attr_e( 'If you like this product, please write a few words about it. It will help other people find this useful plugin more quickly and help us to keep the plugin live and updated.<br><b>Thank you!</b>', 'wp-memory'  );
					esc_attr_e("Could you please do me a BIG favor?","wp-memory");
					echo '<br />';
					esc_attr_e('If you use & enjoy WP Memory Plugin, please rate it 5 stars on WordPress.org. It only takes a second and help us spread the word and boost our motivation. Thank you!', 'wp-memory' );
					?>

					   </p>
					<p>
						<a href="<?php echo admin_url( 'admin-ajax.php' ); ?>?action=vote&amp;vote=yes" class="bill-vote-action button button-medium button-primary" data-action="<?php echo esc_attr( PLUGINHOME ); ?>/share/"><?php echo esc_attr_e( 'Rate or Share', 'wp-memory'  ); ?></a>
						<a href="<?php echo admin_url( 'admin-ajax.php' ); ?>?action=vote&amp;vote=no" class="bill-vote-action button button-medium"><?php esc_attr_e( 'No, dismiss', 'wp-memory'  ); ?></a>
<span><?php _e( 'or', 'wp-memory'  ); ?></span>
						<a href="<?php echo admin_url( 'admin-ajax.php' ); ?>?action=vote&amp;vote=later" class="bill-vote-action button button-medium"><?php esc_attr_e( 'Remind me later', 'wp-memory'  ); ?></a>
						<input type="hidden" id="billclassvote" name="billclassvote" value="<?php echo esc_attr( PRODCLASS ); ?>" />
						<input type="hidden" id="billclassvote" name="billclassvote" value="<?php echo esc_attr( PRODCLASS ); ?>" />
					</p>
				</div>
				<div class="bill-vote-clear"></div>
		<!-- </div> -->
        </div>
				<?php
		}
	}
	new Bill_mVote();
}
