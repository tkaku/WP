<?php
/***********************************************************
* Plugins Update checker
***********************************************************/
add_filter( 'http_request_args', 'welcart_http_request_args', 10, 2);
function welcart_http_request_args($a, $b){

	if( false === strpos($b, (USCES_UPDATE_INFO_URL . '/update_info/plugins/info.php')) ){
		return $a;
	}
	$a['body'] = array( 'wpver' => get_bloginfo('version'), 'wcver' => USCES_VERSION, 'prhost' => $_SERVER['SERVER_NAME'] );

	return $a;
}

add_action( 'init', 'welcart_update_check' );
function welcart_update_check(){

	if( !is_admin() ){
		return;
	}

	$current = get_site_transient( 'update_wcex_plugins' );
	if ( ! is_object($current) ){
		$current = new stdClass;
	}

	require( USCES_PLUGIN_DIR . '/update_check/plugin-update-checker.php');

	$timeout = 2 * HOUR_IN_SECONDS;
	$time_not_changed = isset( $current->last_checked ) && $timeout > ( time() - $current->last_checked );
	if( $time_not_changed ){

		$wcproducts = $current->products;

	}else{

		$options = array( 'body' => array( 
			'wpver' => get_bloginfo('version'),
			'wcver' => USCES_VERSION,
			'prhost' => $_SERVER['SERVER_NAME'],
			'checktime' => time()
		) );
		$response = wp_remote_post( USCES_UPDATE_INFO_URL.'/update_info/info_api.php', $options );
		$wcproducts = (array)json_decode($response['body']);

		if( empty($wcproducts) ){
			return;
		}
		$current->last_checked = time();
		$current->products = $wcproducts;
		set_site_transient( 'update_wcex_plugins', $current );
	}

	$wcproducts = (array)$wcproducts;

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	$installed = array();
	foreach( $plugins as $path => $pv){

		if( array_key_exists( $pv['Name'], $wcproducts ) ){

			$slug = $wcproducts[$pv['Name']];
			$fullpath = USCES_WP_PLUGIN_DIR.'/'.$path;
			$installed[$slug] = $fullpath;

		}
	}

	foreach( $installed as $slug => $fullpath ){

		$json_path = USCES_UPDATE_INFO_URL.'/update_info/plugins/' . $slug . '.json';
		$$slug = Puc_v4_Factory::buildUpdateChecker( $json_path, $fullpath, $slug );

	}
}

