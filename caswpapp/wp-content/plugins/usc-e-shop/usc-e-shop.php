<?php
/*
Plugin Name: Welcart e-Commerce
Plugin URI: http://www.welcart.com/
Description: Welcart builds the management system with a net shop on WordPress.
Version: 1.9.31
Author: Collne Inc.
Author URI: http://www.collne.com/
Text Domain: usces
Domain Path: /languages/
*/
define('USCES_VERSION', '1.9.31.2007081');
define('USCES_DB_ACCESS', '1.5');
define('USCES_DB_MEMBER', '1.1');
define('USCES_DB_MEMBER_META', '1.1');
define('USCES_DB_ORDER', '2.0');
define('USCES_DB_ORDER_META', '1.2');
define('USCES_DB_ORDERCART', '1.0');
define('USCES_DB_ORDERCART_META', '1.0');
define('USCES_DB_LOG', '1.1');

define('USCES_UP07', 1);
define('USCES_UP11', 2);
define('USCES_UP14', 3);
define('USCES_UP141', 5);
define('USCES_UP143', 1);

define('USCES_WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('USCES_WP_CONTENT_URL', site_url() . '/wp-content');
define('USCES_WP_PLUGIN_DIR', USCES_WP_CONTENT_DIR . '/plugins');
define('USCES_WP_PLUGIN_URL', USCES_WP_CONTENT_URL . '/plugins');

define('USCES_PLUGIN_DIR', USCES_WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)));
define('USCES_PLUGIN_URL', USCES_WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)));
define('USCES_PLUGIN_FOLDER', dirname(plugin_basename(__FILE__)));
define('USCES_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('USCES_CART_FOLDER', 'usces-cart');
define('USCES_MEMBER_FOLDER', 'usces-member');
define('USCES_ADMIN_SSL_BASE_NAME', 'admin-ssl.php');
define('USCES_ADMIN_URL', site_url() . '/wp-admin/admin.php');
define('USCES_EXTENSIONS_DIR', USCES_PLUGIN_DIR . '/extensions');
define('USCES_EXTENSIONS_URL', USCES_PLUGIN_URL . '/extensions');
define('USCES_UPDATE_INFO_URL', 'https://endpoint.welcart.org');

global $usces_settings, $usces_states, $usces_p, $usces_essential_mark;
require_once(USCES_PLUGIN_DIR."/functions/included_first.php");
add_filter( 'locale', 'usces_filter_locale' );
load_plugin_textdomain('usces', false, USCES_PLUGIN_FOLDER.'/languages');

require_once(USCES_PLUGIN_DIR."/classes/utilities.class.php");
require_once(USCES_PLUGIN_DIR."/functions/filters.php");
require_once(USCES_PLUGIN_DIR."/functions/redirect.php");
require_once(USCES_PLUGIN_DIR."/includes/initial.php");
require_once(USCES_PLUGIN_DIR.'/functions/define_function.php');
require_once(USCES_PLUGIN_DIR."/functions/calendar-com.php");
require_once(USCES_PLUGIN_DIR."/functions/utility.php");
require_once(USCES_PLUGIN_DIR."/functions/datalist.php");
require_once(USCES_PLUGIN_DIR."/functions/item_post.php");
require_once(USCES_PLUGIN_DIR."/functions/function.php");
require_once(USCES_PLUGIN_DIR."/functions/shortcode.php");
require_once(USCES_PLUGIN_DIR."/classes/usceshop.class.php");
require_once(USCES_PLUGIN_DIR."/functions/hoock_func.php");
require_once(USCES_PLUGIN_DIR."/classes/httpRequest.class.php");
require_once(USCES_PLUGIN_DIR."/functions/admin_func.php");
require_once(USCES_PLUGIN_DIR."/functions/system_post.php");
if( is_admin() ){
	require_once(USCES_PLUGIN_DIR."/functions/admin_page.php");
	require_once(USCES_PLUGIN_DIR."/includes/update_check.php");
}
require_once(USCES_PLUGIN_DIR."/functions/settlement_func.php");
require_once(USCES_PLUGIN_DIR."/classes/PaymentYahooWallet.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentEpsilon.class.php");
require_once(USCES_PLUGIN_DIR.'/classes/paymentESCOTT.main.class.php');
require_once(USCES_PLUGIN_DIR."/classes/PaymentESCOTT.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentWelcart.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentZeus.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentRemise.class.php");
require_once(USCES_PLUGIN_DIR.'/classes/paymentSBPS.main.class.php');
require_once(USCES_PLUGIN_DIR."/classes/paymentSBPS.class.php");
require_once(USCES_PLUGIN_DIR."/classes/PaymentDSK.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentPayPalEC.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentPayPalWPP.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentJPayment.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentTelecom.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentDigitalcheck.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentMizuho.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentAnotherlane.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentVeritrans.class.php");
require_once(USCES_PLUGIN_DIR."/classes/paymentPaygent.class.php");
require_once(USCES_PLUGIN_DIR."/classes/tax.class.php");

global $usces;
$usces = new usc_e_shop();
$usces->regist_action();

require_once(USCES_PLUGIN_DIR."/functions/template_func.php");

register_activation_hook( __FILE__, array($usces, 'set_initial') );
register_deactivation_hook( __FILE__, array($usces, 'deactivate') );

require_once(USCES_PLUGIN_DIR."/includes/default_filters.php");
