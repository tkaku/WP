<?php

function usces_ajax_send_mail() {
	global $wpdb, $usces;
	
	$_POST = $usces->stripslashes_deep_post($_POST);
	$order_para = array(
			'to_name' => sprintf(_x('%s', 'honorific', 'usces'), trim(urldecode($_POST['name']))),
			'to_address' => trim($_POST['mailaddress']),
			'from_name' => get_option('blogname'),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => trim(urldecode($_POST['subject'])),
			'message' => trim($_POST['message'])
			);
	
	$order_para = apply_filters( 'usces_ajax_send_mail_para_to_customer', $order_para);
	$res = usces_send_mail( $order_para );

	do_action('usces_action_ajax_after_send_mail_to_customer', $res, $order_para);

	if($res){
		if( isset($_POST['order_id']) && $_POST['order_id'] != '' ) {
			$tableName = $wpdb->prefix . "usces_order";
			$order_id = $_POST['order_id'];
			$checked = $_POST['checked'];

			$query = $wpdb->prepare("SELECT `order_check` FROM $tableName WHERE ID = %d", $order_id);
			$res = $wpdb->get_var( $query );

			$checkfield = unserialize($res);
			if( !isset($checkfield[$checked]) ) $checkfield[$checked] = $checked;
			$query = $wpdb->prepare("UPDATE $tableName SET `order_check`=%s WHERE ID = %d", serialize($checkfield), $order_id);
			$wpdb->query( $query );
		}

		if( $usces->options['system']['front_lang'] != 'ja' ){
			$bcc_subject = trim(urldecode($_POST['subject'])) . ' to ' . sprintf(_x('%s', 'usces'), trim(urldecode($_POST['name'])));
		} else {
			$bcc_subject = trim(urldecode($_POST['subject'])) . ' to ' . sprintf(_x('%s', 'honorific', 'usces'), trim(urldecode($_POST['name'])));
		}

		$bcc_para = array(
				'to_name' => apply_filters( 'usces_filter_bccmail_to_admin_name', 'Shop Admin' ), 
				'to_address' => $usces->options['order_mail'], 
				'from_name' => apply_filters( 'usces_filter_bccmail_from_admin_name', 'Welcart Auto BCC' ), 
				'from_address' => $usces->options['sender_mail'], 
				'return_path' => $usces->options['sender_mail'],
				'subject' => $bcc_subject,
				'message' => trim($_POST['message'])
				);

		$bcc_para = apply_filters( 'usces_ajax_send_mail_para_to_manager', $bcc_para);
//		sleep(1);
		$res2 = usces_send_mail( $bcc_para );

		do_action('usces_action_ajax_after_send_mail_to_manager', $res2, $bcc_para);

		return 'success';
	}else{
		return 'error';
	}
}

function usces_order_confirm_message($order_id) {
	global $usces;

	$data = $usces->get_order_data( $order_id, 'direct' );
	$deli = unserialize( $data['order_delivery'] );
	$cart = usces_get_ordercartdata( $order_id );

	$country = $usces->get_order_meta_value('customer_country', $order_id);
	$customer = array(
					'name1' => $data['order_name1'],
					'name2' => $data['order_name2'],
					'name3' => $data['order_name3'],
					'name4' => $data['order_name4'],
					'zipcode' => $data['order_zip'],
					'country' => $country,
					'pref' => $data['order_pref'],
					'address1' => $data['order_address1'],
					'address2' => $data['order_address2'],
					'address3' => $data['order_address3'],
					'tel' => $data['order_tel'],
					'fax' => $data['order_fax'],
				);
	$condition = unserialize($data['order_condition']);
	$tax_display = ( isset( $condition['tax_display'] ) ) ? $condition['tax_display'] : usces_get_tax_display();
	//$reduced_taxrate = usces_is_reduced_taxrate( $order_id );
	$reduced_taxrate = ( isset( $condition['applicable_taxrate'] ) && 'reduced' == $condition['applicable_taxrate'] ) ? true : false;
	if( 'activate' == $tax_display && $reduced_taxrate ) {
		$usces_tax = Welcart_Tax::get_instance();
	}
	$member_system = ( isset( $condition['membersystem_state'] ) ) ? $condition['membersystem_state'] : $usces->options['membersystem_state'];
	$member_system_point = ( isset( $condition['membersystem_point'] ) ) ? $condition['membersystem_point'] : $usces->options['membersystem_point'];
	$tax_mode = ( isset( $condition['tax_mode'] ) ) ? $condition['tax_mode'] : usces_get_tax_mode();
	$tax_target = ( isset( $condition['tax_target'] ) ) ? $condition['tax_target'] : usces_get_tax_target();
	$point_coverage = ( isset( $condition['point_coverage'] ) ) ? $condition['point_coverage'] : usces_point_coverage();

	$total_full_price = $data['order_item_total_price'] - $data['order_usedpoint'] + $data['order_discount'] + $data['order_shipping_charge'] + $data['order_cod_fee'] + $data['order_tax'];
	if( $total_full_price < 0 ) $total_full_price = 0;

	$mail_data = $usces->options['mail_data'];
	$payment = $usces->getPayments( $data['order_payment_name'] );
	$msg_body = '';
	$hook_args = compact('order_id', 'data', 'deli', 'cart', 'country', 'customer', 'condition', 'total_full_price', 'mail_data', 'payment' );

	$msg_body .= apply_filters( 'usces_filter_order_confirm_body_top', $msg_body, $hook_args );

	if($_POST['mode'] == 'mitumoriConfirmMail'){
		$msg_body .= "\r\n\r\n\r\n" . __('Estimate','usces') . "\r\n";
		$msg_body .= usces_mail_line( 1, $data['order_email'] );//********************
		$msg_body .= apply_filters('usces_filter_order_confirm_mail_first', NULL, $data);
		$msg_body .= uesces_get_mail_addressform( 'admin_mail_customer', $customer, $order_id );
		$msg_body .= __('estimate number','usces') . " : " . $order_id . "\r\n";
	}else{
		$msg_body .= "\r\n\r\n\r\n" . __('** Article order contents **','usces') . "\r\n";
		$msg_body .= usces_mail_line( 1, $data['order_email'] );//********************
		$msg_body .= apply_filters('usces_filter_order_confirm_mail_first', NULL, $data);
		$msg_body .= uesces_get_mail_addressform( 'admin_mail_customer', $customer, $order_id );
		$msg_body .= __('Order number','usces') . " : " . usces_get_deco_order_id( $order_id ) . "\r\n";
		$msg_body .= __( 'order date','usces' ) . " : " . $data['order_date'] . "\r\n";
	}

	$meisai = __('Items','usces') . " :\r\n";
	foreach ( (array)$cart as $cart_row ) {
		$post_id = $cart_row['post_id'];
		$sku = urldecode($cart_row['sku']);
		$quantity = $cart_row['quantity'];
		$options = ( !empty( $cart_row['options'] ) ) ? $cart_row['options'] : array();
		$itemCode = $usces->getItemCode($post_id);
		$itemName = $usces->getItemName($post_id);
		$cartItemName = $usces->getCartItemName_byOrder($cart_row);
		if( 'activate' == $tax_display && $reduced_taxrate ) {
			$applicable_taxrate = $usces_tax->get_ordercart_applicable_taxrate( $cart_row['cart_id'], $post_id, $sku );
			if( 'reduced' == $applicable_taxrate ) {
				$cartItemName .= $usces_tax->reduced_taxrate_mark;
			}
		}
		$skuPrice = $cart_row['price'];
		$args = compact('cart', 'cart_row', 'post_id', 'sku' );
		$meisai .= usces_mail_line( 2, $data['order_email'] );//--------------------
		$meisai .= apply_filters('usces_filter_cart_item_name_nl', $cartItemName, $args)."\r\n";
		if( is_array($options) && count($options) > 0 ){
			$optstr = '';
			foreach($options as $key => $value){
				if( !empty($key) ) {
					$key = urldecode($key);
					$value = maybe_unserialize($value);
					if(is_array($value)) {
						$c = '';
						$optstr .= $key . ' : ';
						foreach($value as $v) {
							$optstr .= $c.rawurldecode($v);
							$c = ', ';
						}
						$optstr .= "\r\n";
					} else {
						$optstr .= $key . ' : ' . rawurldecode($value) . "\r\n";
					}
				}
			}
			$meisai .= apply_filters( 'usces_filter_option_adminmail', $optstr, $options, $cart_row );
		}
		$meisai .= apply_filters( 'usces_filter_advance_adminmail', '', $cart_row, $data );
		$meisai .= __('Unit price','usces') . " ".usces_crform( $skuPrice, true, false, 'return' ) . __(' * ','usces') . $cart_row['quantity'] . "\r\n";
	}
	
	$meisai .= usces_mail_line( 3, $data['order_email'] );//====================
	$meisai .= __('total items','usces') . " : " . usces_crform( $data['order_item_total_price'], true, false, 'return' ) . "\r\n";

	if ( $data['order_discount'] != 0 )
		$meisai .= apply_filters('usces_confirm_discount_label', __('Campaign discount', 'usces'), $order_id) . " : " . usces_crform( $data['order_discount'], true, false, 'return' ) . "\r\n";

	if ( 'activate' == $tax_display && 'products' == $tax_target ) {
		$meisai .= usces_tax_label($data, 'return');
		if( 'exclude' == $tax_mode ) $meisai .= " : " . usces_tax($data, 'return');
		$meisai .= "\r\n";
	}

	if( 'activate' == $member_system && 'activate' == $member_system_point && 0 == $point_coverage && $data['order_usedpoint'] != 0 )
		$meisai .= __('use of points','usces') . " : " . number_format($data['order_usedpoint']) . __('Points','usces') . "\r\n";

	$meisai .= __('Shipping','usces') . " : " . usces_crform( $data['order_shipping_charge'], true, false, 'return' ) . "\r\n";

	if ( 0 < $data['order_cod_fee'] )
		$meisai .= apply_filters('usces_filter_cod_label', __('COD fee', 'usces'), $order_id ) . " : " . usces_crform( $data['order_cod_fee'], true, false, 'return' ) . "\r\n";

	if ( 'activate' == $tax_display && 'all' == $tax_target ) {
		$meisai .= usces_tax_label($data, 'return');
		if( 'exclude' == $tax_mode ) $meisai .= " : " . usces_tax($data, 'return');
		$meisai .= "\r\n";
	}

	if( 'activate' == $member_system && 'activate' == $member_system_point && 1 == $point_coverage && $data['order_usedpoint'] != 0 )
		$meisai .= __('use of points','usces') . " : " . number_format($data['order_usedpoint']) . __('Points','usces') . "\r\n";

	$meisai .= usces_mail_line( 2, $data['order_email'] );//--------------------
	$meisai .= __('Payment amount','usces') . " : " . usces_crform( $total_full_price, true, false, 'return' ) . "\r\n";
	$meisai .= usces_mail_line( 2, $data['order_email'] );//--------------------
	if( 'activate' == $tax_display && $reduced_taxrate ) {
		$materials = array(
			'total_items_price' => $data['order_item_total_price'],
			'discount' => $data['order_discount'],
			'shipping_charge' => $data['order_shipping_charge'],
			'cod_fee' => $data['order_cod_fee'],
			'use_point' => $data['order_usedpoint'],
			'carts' => $cart,
			'condition' => $condition,
			'order_id' => $order_id,
		);
		$usces_tax->get_order_tax( $materials );
		if( 'include' == $condition['tax_mode'] ) {
			$po = '(';
			$pc = ')';
		} else {
			$po = '';
			$pc = '';
		}
		$meisai .= sprintf( __( "Applies to %s%%", 'usces' ), $usces_tax->tax_rate_standard ) . " : " . usces_crform( $usces_tax->subtotal_standard + $usces_tax->discount_standard, true, false, 'return' ) . "\r\n";
		$meisai .= sprintf( __( "%s%% consumption tax", 'usces' ), $usces_tax->tax_rate_standard ) . " : " . $po . usces_crform( $usces_tax->tax_standard, true, false, 'return' ) . $pc . "\r\n";
		$meisai .= sprintf( __( "Applies to %s%%", 'usces' ), $usces_tax->tax_rate_reduced ) . " : " . usces_crform( $usces_tax->subtotal_reduced + $usces_tax->discount_reduced, true, false, 'return' ) . "\r\n";
		$meisai .= sprintf( __( "%s%% consumption tax", 'usces' ), $usces_tax->tax_rate_reduced ) . " : " . $po . usces_crform( $usces_tax->tax_reduced, true, false, 'return' ) . $pc . "\r\n";
		$meisai .= usces_mail_line( 2, $data['order_email'] );//--------------------
		$meisai .= $usces_tax->reduced_taxrate_mark . __( ' is reduced tax rate', 'usces' ) . "\r\n";
	}
	$meisai .= "(" . __('Currency', 'usces') . ' : ' . __(usces_crcode( 'return' ), 'usces') . ")\r\n\r\n";
	$msg_body .= apply_filters('usces_filter_order_confirm_mail_meisai', $meisai, $data, $cart);
	
	$msg_shipping = __('** A shipping address **','usces') . "\r\n";
	$msg_shipping .= usces_mail_line( 1, $data['order_email'] );//********************
	
	$msg_shipping .= uesces_get_mail_addressform( 'admin_mail', $deli, $order_id );

	if ( $data['order_delidue_date'] == NULL || $data['order_delidue_date'] == '#none#' ) {
		$msg_shipping .= "\r\n";
	}else{
		$msg_shipping .= __('Shipping date', 'usces') . " : " . $data['order_delidue_date'] . "\r\n";
		$msg_shipping .= __("* A shipment due date is a day to ship an article, and it's not the arrival day.", 'usces') . "\r\n";
		$msg_shipping .= "\r\n";
	}
	$deli_meth = (int)$data['order_delivery_method'];
	if( 0 <= $deli_meth ){
		$deli_index = $usces->get_delivery_method_index($deli_meth);
		if( 0 <= $deli_index ) $msg_shipping .= __('Delivery Method','usces') . " : " . $usces->options['delivery_method'][$deli_index]['name'] . "\r\n";
	}
	$msg_shipping .= __('Delivery date','usces') . " : " . $data['order_delivery_date'] . "\r\n";
	$msg_shipping .= __('Delivery Time','usces') . " : " . $data['order_delivery_time'] . "\r\n";
	
	if( $_POST['mode'] == 'completionMail' ){
		$tracking_number = $usces->get_order_meta_value( apply_filters( 'usces_filter_tracking_meta_key', 'tracking_number'), $order_id );
		$delivery_company = $usces->get_order_meta_value( 'delivery_company', $order_id );
		if( !empty($delivery_company) ){
			$msg_shipping .= __('Delivery company', 'usces') . " : " . $delivery_company . "\r\n";
			if( !empty($tracking_number) ){
				$msg_shipping .= __('Tracking number', 'usces') . " : " . $tracking_number . "\r\n\r\n";
				$msg_shipping .= __( "[*]", 'usces' ) . __('You can check delivery situation from the following URL.', 'usces') . "\r\n";
				$msg_shipping .= usces_get_delivery_company_url($delivery_company, $tracking_number) . " \r\n\r\n";
			}
		}
	}
	$msg_shipping .= "\r\n";
	$msg_body .= apply_filters('usces_filter_order_confirm_mail_shipping', $msg_shipping, $data);

	$msg_payment = __('** Payment method **','usces') . "\r\n";
	$msg_payment .= usces_mail_line( 1, $data['order_email'] );//********************
	$msg_payment .= $payment['name'] . usces_payment_detail_confirm( $data ) . "\r\n\r\n";
	if( 'orderConfirmMail' == $_POST['mode'] || 'changeConfirmMail' == $_POST['mode'] || 'mitumoriConfirmMail' == $_POST['mode'] || 'otherConfirmMail' == $_POST['mode'] ) {
		if ( $payment['settlement'] == 'transferAdvance' ) {
			$transferee = __('Transfer','usces') . " :\r\n";
			$transferee .= $usces->options['transferee'] . "\r\n";
			$msg_payment .= apply_filters('usces_filter_mail_transferee', $transferee, $payment, $order_id);
			$msg_payment .= "\r\n".usces_mail_line( 2, $data['order_email'] )."\r\n";//--------------------
		}
	}
	$msg_body .= apply_filters('usces_filter_order_confirm_mail_payment', $msg_payment, $order_id, $payment, $cart, $data);
	$msg_body .= usces_mail_custom_field_info( 'order', '', $order_id, $data['order_email'] );

	$msg_others = "\r\n";
	$msg_others .= __('** Others / a demand **','usces') . "\r\n";
	$msg_others .= usces_mail_line( 1, $data['order_email'] );//********************
	$msg_others .= $data['order_note'] . "\r\n\r\n";
	$msg_body .= apply_filters( 'usces_filter_order_confirm_mail_others', $msg_others, $data );

	$msg_body .= apply_filters('usces_filter_order_confirm_mail_body', NULL, $data);
	$msg_body = apply_filters('usces_filter_order_confirm_mail_bodyall', $msg_body, $data);

	switch ( $_POST['mode'] ) {
		case 'completionMail':
			$message = do_shortcode($mail_data['header']['completionmail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['completionmail']);
			break;
		case 'orderConfirmMail':
			$message = do_shortcode($mail_data['header']['ordermail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['ordermail']);
			break;
		case 'changeConfirmMail':
			$message = do_shortcode($mail_data['header']['changemail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['changemail']);
			break;
		case 'receiptConfirmMail':
			$message = do_shortcode($mail_data['header']['receiptmail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['receiptmail']);
			break;
		case 'mitumoriConfirmMail':
			$message = do_shortcode($mail_data['header']['mitumorimail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['mitumorimail']);
			break;
		case 'cancelConfirmMail':
			$message = do_shortcode($mail_data['header']['cancelmail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['cancelmail']);
			break;
		case 'otherConfirmMail':
			$message = do_shortcode($mail_data['header']['othermail']) . apply_filters('usces_filter_order_confirm_mail_body_after', $msg_body, $data) . do_shortcode($mail_data['footer']['othermail']);
			break;
		default:
			$message = apply_filters( 'usces_filter_order_confirm_mail_body_after', $msg_body, $data );
	}

	if( $usces->options['put_customer_name'] == 1 ) {
		$name = sprintf( __('Dear %s', 'usces'), usces_localized_name(trim($data['order_name1']), trim($data['order_name2']), 'return') );
		$message = $name."\r\n\r\n".$message;
	}

	return apply_filters('usces_filter_order_confirm_mail_message', $message, $data);

}

function usces_send_ordermail($order_id) {
	global $usces;
	$reduced_taxrate = usces_is_reduced_taxrate();
	if( usces_is_tax_display() && $reduced_taxrate ) {
		$usces_tax = Welcart_Tax::get_instance();
	}

	$data = $usces->get_order_data( $order_id, 'direct' );

	$newcart = usces_get_ordercartdata($order_id);
	$cart = $usces->cart->get_cart();

	$entry = $usces->cart->get_entry();
	$mail_data = $usces->options['mail_data'];
	$payment = $usces->getPayments( $entry['order']['payment_name'] );

	$msg_body = "\r\n\r\n\r\n" . __('** content of ordered items **','usces') . "\r\n";
	$msg_body .= usces_mail_line( 1, $entry['customer']['mailaddress1'] );//********************
	$msg_body .= apply_filters('usces_filter_send_order_mail_first', NULL, $data);
	$msg_body .= uesces_get_mail_addressform( 'order_mail_customer', $entry, $order_id );
	$msg_body .= __('Order number','usces') . " : " . usces_get_deco_order_id( $order_id ) . "\r\n";
	$msg_body .= __( 'order date','usces' ) . " : " . $data['order_date'] . "\r\n";

	$meisai = __('Items','usces') . " :\r\n";
	foreach ( $cart as $cart_key => $cart_row ) {
		$cart_row['cart_id'] = $newcart[$cart_key]['cart_id'];
		$post_id = $cart_row['post_id'];
		$sku = urldecode($cart_row['sku']);
		$quantity = $cart_row['quantity'];
		$options = ( !empty( $cart_row['options'] ) ) ? $cart_row['options'] : array();
		$itemCode = $usces->getItemCode($post_id);
		$itemName = $usces->getItemName($post_id);
		$cartItemName = $usces->getCartItemName($post_id, $sku);
		if( usces_is_tax_display() && $reduced_taxrate ) {
			$applicable_taxrate = $usces_tax->get_sku_applicable_taxrate( $post_id, $sku );
			if( 'reduced' == $applicable_taxrate ) {
				$cartItemName .= $usces_tax->reduced_taxrate_mark;
			}
		}
		$skuPrice = $cart_row['price'];
		$args = compact('cart', 'cart_row', 'post_id', 'sku' );
		$meisai .= usces_mail_line( 2, $entry['customer']['mailaddress1'] );//--------------------
		$meisai .= apply_filters('usces_filter_cart_item_name_nl', $cartItemName, $args)."\r\n";
		if( is_array($options) && count($options) > 0 ){
			$optstr = '';
			foreach($options as $key => $value){
				if( !empty($key) ) {
					$key = urldecode($key);
					if(is_array($value)) {
						$c = '';
						$optstr .= $key. ' : ';
						foreach($value as $v) {
							$optstr .= $c.urldecode($v);
							$c = ', ';
						}
						$optstr .= "\r\n";
					} else {
						$optstr .= $key . ' : ' . urldecode($value) . "\r\n";
					}
				}
			}
			$meisai .= apply_filters( 'usces_filter_option_ordermail', $optstr, $options, $newcart[$cart_key] );
		}
		$meisai .= apply_filters( 'usces_filter_advance_ordermail', '', $newcart[$cart_key], $data );
		$meisai .= __('Unit price','usces') . " ".usces_crform( $skuPrice, true, false, 'return' ) . __(' * ','usces') . $cart_row['quantity'] . "\r\n";
	}
	$meisai .= usces_mail_line( 3, $entry['customer']['mailaddress1'] );//====================
	$meisai .= __('total items','usces') . " : " . usces_crform( $entry['order']['total_items_price'], true, false, 'return' ) . "\r\n";

	if ( $entry['order']['discount'] != 0 )
		$meisai .= apply_filters('usces_confirm_discount_label', __('Campaign discount', 'usces'), $order_id) . " : " . usces_crform( $entry['order']['discount'], true, false, 'return' ) . "\r\n";

	if ( usces_is_tax_display() && 'products' == usces_get_tax_target() ) {
		$meisai .= usces_tax_label($data, 'return');
		if( 'exclude' == usces_get_tax_mode() ) $meisai .= " : " . usces_tax($entry, 'return');
		$meisai .= "\r\n";
	}

	if( usces_is_member_system() && usces_is_member_system_point() && 0 == usces_point_coverage() && $entry['order']['usedpoint'] != 0 )
		$meisai .= __('use of points','usces') . " : " . number_format($entry['order']['usedpoint']) . __('Points','usces') . "\r\n";

	$meisai .= __('Shipping','usces') . " : " . usces_crform( $entry['order']['shipping_charge'], true, false, 'return' ) . "\r\n";

	if ( 0 < $entry['order']['cod_fee'] )
		$meisai .= apply_filters('usces_filter_cod_label', __('COD fee', 'usces'), $order_id ) . " : " . usces_crform( $entry['order']['cod_fee'], true, false, 'return' ) . "\r\n";

	if ( usces_is_tax_display() && 'all' == usces_get_tax_target() ) {
		$meisai .= usces_tax_label($data, 'return');
		if( 'exclude' == usces_get_tax_mode() ) $meisai .= " : " . usces_tax($entry, 'return');
		$meisai .= "\r\n";
	}

	if( usces_is_member_system() && usces_is_member_system_point() && 1 == usces_point_coverage() && $entry['order']['usedpoint'] != 0 )
		$meisai .= __('use of points','usces') . " : " . number_format($entry['order']['usedpoint']) . __('Points','usces') . "\r\n";

	$meisai .= usces_mail_line( 2, $entry['customer']['mailaddress1'] );//--------------------
	$meisai .= __('Payment amount','usces') . " : " . usces_crform( $entry['order']['total_full_price'], true, false, 'return' ) . "\r\n";
	$meisai .= usces_mail_line( 2, $entry['customer']['mailaddress1'] );//--------------------
	if( usces_is_tax_display() && $reduced_taxrate ) {
		$condition = $usces->get_condition();
		if( 'include' == $condition['tax_mode'] ) {
			$po = '(';
			$pc = ')';
		} else {
			$po = '';
			$pc = '';
		}
		$meisai .= sprintf( __( "Applies to %s%%", 'usces' ), $usces_tax->tax_rate_standard ) . " : " . usces_crform( $usces_tax->subtotal_standard + $usces_tax->discount_standard, true, false, 'return' ) . "\r\n";
		$meisai .= sprintf( __( "%s%% consumption tax", 'usces' ), $usces_tax->tax_rate_standard ) . " : " . $po . usces_crform( $usces_tax->tax_standard, true, false, 'return' ) . $pc . "\r\n";
		$meisai .= sprintf( __( "Applies to %s%%", 'usces' ), $usces_tax->tax_rate_reduced ) . " : " . usces_crform( $usces_tax->subtotal_reduced + $usces_tax->discount_reduced, true, false, 'return' ) . "\r\n";
		$meisai .= sprintf( __( "%s%% consumption tax", 'usces' ), $usces_tax->tax_rate_reduced ) . " : " . $po . usces_crform( $usces_tax->tax_reduced, true, false, 'return' ) . $pc . "\r\n";
		$meisai .= usces_mail_line( 2, $data['order_email'] );//--------------------
		$meisai .= $usces_tax->reduced_taxrate_mark . __( ' is reduced tax rate', 'usces' ) . "\r\n";
	}
	$meisai .= "(" . __('Currency', 'usces') . ' : ' . __(usces_crcode( 'return' ), 'usces') . ")\r\n\r\n";

	$msg_body .= apply_filters('usces_filter_send_order_mail_meisai', $meisai, $data, $cart, $entry);

	$msg_shipping = __('** A shipping address **','usces') . "\r\n";
	$msg_shipping .= usces_mail_line( 1, $entry['customer']['mailaddress1'] );//********************
	
	$msg_shipping .= uesces_get_mail_addressform( 'order_mail', $entry, $order_id );

	$deli_meth = (int)$entry['order']['delivery_method'];
	if( 0 <= $deli_meth ){
		$deli_index = $usces->get_delivery_method_index($deli_meth);
		if( 0 <= $deli_index ) $msg_shipping .= __('Delivery Method','usces') . " : " . $usces->options['delivery_method'][$deli_index]['name'] . "\r\n";
	}
	$msg_shipping .= __('Delivery date','usces') . " : " . $entry['order']['delivery_date'] . "\r\n";
	$msg_shipping .= __('Delivery Time','usces') . " : " . $entry['order']['delivery_time'] . "\r\n";
	$msg_shipping .= "\r\n";
	$msg_body .= apply_filters('usces_filter_send_order_mail_shipping', $msg_shipping, $data, $entry );

	$msg_payment = __('** Payment method **','usces') . "\r\n";
	$msg_payment .= usces_mail_line( 1, $entry['customer']['mailaddress1'] );//********************
	$msg_payment .= $payment['name'] . usces_payment_detail($entry) . "\r\n\r\n";
	if ( $payment['settlement'] == 'transferAdvance' ) {
		$transferee = __('Transfer','usces') . " : \r\n";
		$transferee .= $usces->options['transferee'] . "\r\n";
		$msg_payment .= apply_filters('usces_filter_mail_transferee', $transferee, $payment, $order_id);
		$msg_payment .= "\r\n".usces_mail_line( 2, $entry['customer']['mailaddress1'] )."\r\n";//--------------------
	}
	
	$msg_body .= apply_filters('usces_filter_send_order_mail_payment', $msg_payment, $order_id, $payment, $cart, $entry, $data);

	$msg_body .= usces_mail_custom_field_info( 'order', '', $order_id );

	$msg_others = "\r\n";
	$msg_others .= __('** Others / a demand **','usces') . "\r\n";
	$msg_others .= usces_mail_line( 1, $entry['customer']['mailaddress1'] );//********************
	$msg_others .= $entry['order']['note'] . "\r\n\r\n";
	$msg_body .= apply_filters( 'usces_filter_send_order_mail_others', $msg_others, $data );

	$msg_body .= apply_filters('usces_filter_send_order_mail_body', NULL, $data);
	$msg_body = apply_filters('usces_filter_send_order_mail_bodyall', $msg_body, $data);

	$name = sprintf( _x('%s', 'honorific', 'usces'), usces_localized_name(trim($entry["customer"]["name1"]), trim($entry["customer"]["name2"]), 'return') );
	$subject = apply_filters( 'usces_filter_send_order_mail_subject_thankyou', $mail_data['title']['thankyou'], $data );
	$header = apply_filters( 'usces_filter_send_order_mail_header_thankyou', do_shortcode($mail_data['header']['thankyou']), $data );
	$footer = apply_filters( 'usces_filter_send_order_mail_footer_thankyou', do_shortcode($mail_data['footer']['thankyou']), $data );
	$message = $header . $msg_body . $footer;
	if( $usces->options['put_customer_name'] == 1 ) {
		$dear_name = sprintf( __('Dear %s', 'usces'), usces_localized_name(trim($entry["customer"]["name1"]), trim($entry["customer"]["name2"]), 'return') );
		$message = $dear_name."\r\n\r\n".$message;
	}
	$confirm_para = array(
			'to_name' => $name,
			'to_address' => $entry['customer']['mailaddress1'], 
			'from_name' => get_option('blogname'),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
			);
	$confirm_para = apply_filters( 'usces_send_ordermail_para_to_customer', $confirm_para, $entry, $data);

	$res1 = usces_send_mail( $confirm_para );

	do_action('usces_action_after_send_ordermail_to_customer', $res1, $confirm_para, $entry, $data);

	$subject = apply_filters('usces_filter_send_order_mail_subject_order', $mail_data['title']['order'].' ['.$order_id.'] '.$name, $data);
	$message = do_shortcode($mail_data['header']['order']) . $msg_body
	 . $mail_data['footer']['order']
	 . "\r\n----------------------------------------------------\r\n"
	 . "REMOTE_ADDR : " . $_SERVER['REMOTE_ADDR']
	 . "\r\n----------------------------------------------------\r\n";
	$order_para = array(
			'to_name' => __('An order email','usces'),
			'to_address' => $usces->options['order_mail'],
			'from_name' => get_option('blogname'), 
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
			);
	
	$order_para = apply_filters( 'usces_send_ordermail_para_to_manager', $order_para, $entry, $data);
//	sleep(1);
	$res2 = usces_send_mail( $order_para );

	do_action('usces_action_after_send_ordermail_to_manager', $res2, $order_para, $entry, $data);

	return $res2;
}

function usces_send_inquirymail() {
	global $usces;
	$_POST = $usces->stripslashes_deep_post($_POST);
	$res = false;
	$mail_data = $usces->options['mail_data'];
	$inq_name = trim($_POST["inq_name"]);
	$inq_contents = trim($_POST["inq_contents"]);
	$inq_mailaddress = trim($_POST["inq_mailaddress"]);
	$reserve = '';
	if(isset($_POST['reserve'])){
		foreach($_POST['reserve'] as $key => $value){
			$reserve .= $key . " : " . $value . "\r\n";
		}
	}
	$mats = compact('inq_name','inq_contents','inq_mailaddress','reserve','mail_data');
	$subject =  apply_filters( 'usces_filter_inquiry_subject_to_customer', $mail_data['title']['inquiry'],$mats);
	$message  = apply_filters( 'usces_filter_inquiry_header', $mail_data['header']['inquiry'], $inq_name, $inq_mailaddress ) . "\r\n\r\n";
	$message .= apply_filters( 'usces_filter_inquiry_reserve', $reserve, $inq_name, $inq_mailaddress );
	$message .= apply_filters( 'usces_filter_inq_contents', $inq_contents, $inq_name, $inq_mailaddress ) . "\r\n\r\n";
	$message .= apply_filters( 'usces_filter_inq_footer', $mail_data['footer']['inquiry'], $inq_name, $inq_mailaddress );
	if( $usces->options['put_customer_name'] == 1 ) {
		$message = sprintf(__('Dear %s', 'usces'), $inq_name)."\r\n\r\n".$message;
	}
	do_action( 'usces_action_presend_inquiry_mail', $message, $inq_name, $inq_mailaddress );
	$para1 = array(
			'to_name' => sprintf(_x('%s', 'honorific', 'usces'), $inq_name),
			'to_address' => $inq_mailaddress, 
			'from_name' => get_option('blogname'),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => do_shortcode($message),
			);
	$para1 = apply_filters( 'usces_send_inquirymail_para_to_customer', $para1, $mats);
	$res0 = usces_send_mail( $para1 );

	if ( $res0 ) {
		$subject = apply_filters( 'usces_filter_inquiry_subject_to_manager', __('** An inquiry **','usces').'('.sprintf(_x('%s', 'honorific', 'usces'), $inq_name).')',$mats);
		$message = $reserve . $_POST['inq_contents'] . "\r\n"
		 . "\r\n" . __("Sender's e-mail address",'usces') . " : " . $inq_mailaddress . "\r\n"
		 . "\r\n----------------------------------------------------\r\n"
		 . "REMOTE_ADDR : " . $_SERVER['REMOTE_ADDR']
		 . "\r\n----------------------------------------------------\r\n";
		$para2 = array(
				'to_name' => __('An inquiry email','usces'),
				'to_address' => $usces->options['inquiry_mail'],
				'from_name' => get_option('blogname'), 
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para2 = apply_filters( 'usces_send_inquirymail_para_to_manager', $para2, $mats);
//		sleep(1);
		$res = usces_send_mail( $para2 );
		do_action( 'usces_action_aftersend_inquiry_mail', $message, $inq_name, $inq_mailaddress );
	}
	
	return $res;
}

function usces_send_regmembermail($user) {
	global $usces;
	$res = false;
	$mail_data = $usces->options['mail_data'];
	$newmem_admin_mail = $usces->options['newmem_admin_mail'];
	$name = sprintf( _x('%s', 'honorific', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
	$mailaddress1 = trim($user['mailaddress1']);

	$subject = $mail_data['title']['membercomp'];
	$message  = $mail_data['header']['membercomp'];
	$message .= __('Registration contents', 'usces')."\r\n";
	$message .= '--------------------------------'."\r\n";
	$message .= __('Member ID', 'usces') . ' : ' . $user['ID'] . "\r\n";
	$message .= __('Name', 'usces') . ' : ' . $name . "\r\n";
	$message .= __('e-mail adress', 'usces') . ' : ' . $mailaddress1."\r\n";
	$message .= '--------------------------------'."\r\n\r\n";
	$message .= $mail_data['footer']['membercomp'];
	if( $usces->options['put_customer_name'] == 1 ) {
		$dear_name = sprintf( __('Dear %s', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
		$message = $dear_name."\r\n\r\n".$message;
	}
	$message = apply_filters('usces_filter_send_regmembermail_message', $message, $user);

	$para1 = array(
			'to_name' => $name,
			'to_address' => $mailaddress1, 
			'from_name' => get_option('blogname'),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => do_shortcode($message),
			);
	$para1 = apply_filters( 'usces_filter_send_regmembermail_para1', $para1 );
	$res = usces_send_mail( $para1 );

	if($newmem_admin_mail){
		
		$subject = __('New sign-in processing was completed.', 'usces');
		$message  = __('New sign-in processing was completed.', 'usces') . "\r\n\r\n";
		$message .= __('Registration contents', 'usces')."\r\n";
		$message .= '--------------------------------'."\r\n";
		$message .= __('Member ID', 'usces') . ' : ' . $user['ID'] . "\r\n";
		$message .= __('Name', 'usces') . ' : ' . $name . "\r\n";
		$message .= __('e-mail adress', 'usces') . ' : ' . $mailaddress1."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message = apply_filters('usces_filter_send_regmembermail_notice', $message, $user);
		
		$para2 = array(
				'to_name' => __('Notice of new sign-in', 'usces'),
				'to_address' => $usces->options['order_mail'], 
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para2 = apply_filters( 'usces_filter_send_regmembermail_para2', $para2 );
		usces_send_mail( $para2 );
	}

	return $res;
}

function usces_send_updmembermail( $user ) {
	global $usces;
	$updmem_admin_mail = $usces->options['updmem_admin_mail'];
	$updmem_customer_mail = $usces->options['updmem_customer_mail'];
	if( !$updmem_admin_mail && !$updmem_customer_mail ) return;

	$mail_data = $usces->options['mail_data'];
	$name = sprintf( _x('%s', 'honorific', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
	$mailaddress1 = trim($user['mailaddress1']);
	$subject = apply_filters( 'usces_filter_send_updmembermail_subject', __('Member update processing was completed.', 'usces'), $user );

	if( $updmem_customer_mail ) {
		$message  = $subject."\r\n\r\n";
		$message .= __('Registration contents', 'usces')."\r\n";
		$message .= '--------------------------------'."\r\n";
		$message .= __('Member ID', 'usces').' : '.$user['ID']."\r\n";
		$message .= __('Name', 'usces').' : '.$name."\r\n";
		$message .= __('e-mail adress', 'usces').' : '.$mailaddress1."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message .= $mail_data['footer']['membercomp'];
		if( $usces->options['put_customer_name'] == 1 ) {
			$dear_name = sprintf( __('Dear %s', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
			$message = $dear_name."\r\n\r\n".$message;
		}
		$message = apply_filters( 'usces_filter_send_updmembermail_message', $message, $user );

		$para1 = array(
				'to_name' => $name,
				'to_address' => $mailaddress1,
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para1 = apply_filters( 'usces_filter_send_updmembermail_para1', $para1 );
		usces_send_mail( $para1 );
	}

	if( $updmem_admin_mail ) {
		$message  = $subject."\r\n\r\n";
		$message .= __('Registration contents', 'usces')."\r\n";
		$message .= '--------------------------------'."\r\n";
		$message .= __('Member ID', 'usces').' : '.$user['ID']."\r\n";
		$message .= __('Name', 'usces').' : '.$name."\r\n";
		$message .= __('e-mail adress', 'usces') . ' : '.$mailaddress1."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message = apply_filters( 'usces_filter_send_updmembermail_notice', $message, $user );

		$para2 = array(
				'to_name' => __('Notice of new sign-in', 'usces'),
				'to_address' => $usces->options['order_mail'],
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para2 = apply_filters( 'usces_filter_send_updmembermail_para2', $para2 );
		usces_send_mail( $para2 );
	}
}

function usces_send_delmembermail( $user ) {
	global $usces;
	$delmem_admin_mail = $usces->options['delmem_admin_mail'];
	$delmem_customer_mail = $usces->options['delmem_customer_mail'];
	if( !$delmem_admin_mail && !$delmem_customer_mail ) return;

	$mail_data = $usces->options['mail_data'];
	$name = sprintf( _x('%s', 'honorific', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
	$mailaddress1 = trim($user['mailaddress1']);
	$subject = apply_filters( 'usces_filter_send_delmembermail_subject', __('Member removal processing was completed.', 'usces'), $user );

	if( $delmem_customer_mail ) {
		$message  = $subject."\r\n\r\n";
		$message .= __('Registration contents', 'usces')."\r\n";
		$message .= '--------------------------------'."\r\n";
		$message .= __('Member ID', 'usces').' : '.$user['ID']."\r\n";
		$message .= __('Name', 'usces').' : '.$name."\r\n";
		$message .= __('e-mail adress', 'usces').' : '.$mailaddress1."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message .= $mail_data['footer']['membercomp'];
		if( $usces->options['put_customer_name'] == 1 ) {
			$dear_name = sprintf( __('Dear %s', 'usces'), usces_localized_name(trim($user['name1']), trim($user['name2']), 'return') );
			$message = $dear_name."\r\n\r\n".$message;
		}
		$message = apply_filters( 'usces_filter_send_delmembermail_message', $message, $user );

		$para1 = array(
				'to_name' => $name,
				'to_address' => $mailaddress1,
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para1 = apply_filters( 'usces_filter_send_delmembermail_para1', $para1 );
		usces_send_mail( $para1 );
	}

	if( $delmem_admin_mail ) {
		$message  = $subject."\r\n\r\n";
		$message .= __('Registration contents', 'usces')."\r\n";
		$message .= '--------------------------------'."\r\n";
		$message .= __('Member ID', 'usces').' : '.$user['ID']."\r\n";
		$message .= __('Name', 'usces').' : '.$name."\r\n";
		$message .= __('e-mail adress', 'usces') . ' : '.$mailaddress1."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message = apply_filters( 'usces_filter_send_delmembermail_notice', $message, $user );

		$para2 = array(
				'to_name' => __('Notice of new sign-in', 'usces'),
				'to_address' => $usces->options['order_mail'],
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
		$para2 = apply_filters( 'usces_filter_send_delmembermail_para2', $para2 );
		usces_send_mail( $para2 );
	}
}

function usces_lostmail($url) {
	global $usces;
	$res = false;
	
	if( isset($_REQUEST['loginmail']) && !empty($_REQUEST['loginmail']) ){
		
		$usces_lostmail = $_REQUEST['loginmail'];
		$mail_data = $usces->options['mail_data'];
		$subject = apply_filters( 'usces_filter_lostmail_subject', __('Change password','usces') );
		$message = __('Please, click the following URL, and please change a password.','usces') . "\r\n\r\n\r\n"
				. $url . "\r\n\r\n\r\n"
				. "-----------------------------------------------------\r\n"
				. __('If you have not requested this email please kindly ignore and delete it.','usces') . "\r\n"
				. "-----------------------------------------------------\r\n\r\n\r\n";
		$message = apply_filters( 'usces_filter_lostmail_message', $message, $url );
		$message .= apply_filters( 'usces_filter_lostmail_footer', $mail_data['footer']['othermail'] );

		$para1 = array(
				'to_name' => sprintf(_x('%s', 'honorific', 'usces'), $usces_lostmail),
				'to_address' => $usces_lostmail, 
				'from_name' => get_option('blogname'),
				'from_address' => $usces->options['sender_mail'],
				'return_path' => $usces->options['sender_mail'],
				'subject' => $subject,
				'message' => do_shortcode($message),
				);
	
		$para1 = apply_filters( 'usces_filter_send_lostmail_para1', $para1 );
		$res = usces_send_mail( $para1 );
	}
	
	if($res === false) {
		$usces->error_message = __('Error: I was not able to transmit an email.','usces');
		$page = 'lostmemberpassword';
	} else {
		$page = 'lostcompletion';
	}

	return $page;

}

function usces_mail_custom_field_info( $custom_field, $position, $id, $mailaddress = '' ) {
	global $usces;

	$msg_body = '';
	switch($custom_field) {
	case 'order':
		$field = 'usces_custom_order_field';
		$cs = 'csod_';
		break;
	case 'customer':
		$field = 'usces_custom_customer_field';
		$cs = 'cscs_';
		break;
	case 'delivery':
		$field = 'usces_custom_delivery_field';
		$cs = 'csde_';
		break;
	case 'member':
		$field = 'usces_custom_member_field';
		$cs = 'csmb_';
		break;
	default:
		return $msg_body;
	}

	$meta = usces_has_custom_field_meta($custom_field);

	if(!empty($meta) and is_array($meta)) {
		$keys = array_keys($meta);
		switch($custom_field) {
		case 'order':
			$msg_body .= "\r\n";
			$msg_body .= usces_mail_line( 1, $mailaddress );
			foreach($keys as $key) {
				$value = maybe_unserialize($usces->get_order_meta_value($cs.$key, $id));
				if(is_array($value)) {
					$concatval = '';
					$c = '';
					foreach($value as $v) {
						$concatval .= $c.$v;
						$c = ', ';
					}
					$value = $concatval;
				}
				$msg_body .= $meta[$key]['name']." : ".$value."\r\n";
			}
			$msg_body .= usces_mail_line( 1, $mailaddress );
			break;

		case 'customer':
		case 'delivery':
			foreach($keys as $key) {
				if($meta[$key]['position'] == $position) {
					$value = maybe_unserialize($usces->get_order_meta_value($cs.$key, $id));
					if(is_array($value)) {
						$concatval = '';
						$c = '';
						foreach($value as $v) {
							$concatval .= $c.$v;
							$c = ', ';
						}
						$value = $concatval;
					}
					$msg_body .= $meta[$key]['name']." : ".$value."\r\n";
				}
			}
			break;

		case 'member':
			foreach($keys as $key) {
				if($meta[$key]['position'] == $position) {
					$value = maybe_unserialize($usces->get_member_meta_value($cs.$key, $id));
					if(is_array($value)) {
						$concatval = '';
						$c = '';
						foreach($value as $v) {
							$concatval .= $c.$v;
							$c = ', ';
						}
						$value = $concatval;
					}
					$msg_body .= $meta[$key]['name']." : ".$value."\r\n";
				}
			}
			break;
		}
	}
	$msg_body = apply_filters('usces_filter_mail_custom_field_info', $msg_body, $custom_field, $position, $id, $mailaddress);
	return $msg_body;
}

function _usces_send_mail( $para ) {
	global $usces;

	$from_name = $para['from_name'];
	$from_address = $para['from_address'];
	if (strpos($para['from_address'], '..') !== false || strpos($para['from_address'], '.@') !== false) {
		$fname = str_replace(strstr($para['from_address'], '@'), '', $para['from_address']);
		if( '"' != substr($fname, 0, 1) && '"' != substr($fname, -1) ){
			$para['from_address'] = str_replace($fname, '"RFC_violation"', $para['from_address']);
			$from_name = $para['from_name'] . '(' . $from_address . ')';
		}
	}
	$from = htmlspecialchars(html_entity_decode($from_name, ENT_QUOTES)) . " <{$para['from_address']}>";
	$header = "From: " . apply_filters('usces_filter_send_mail_from', $from, $para) . "\r\n";
	$header .= "Return-Path: {$para['return_path']}\r\n";

	$subject = html_entity_decode($para['subject'], ENT_QUOTES);
	$message = $para['message'];
	
	ini_set( "SMTP", "{$usces->options['smtp_hostname']}" );
	if( !ini_get( "smtp_port" ) ){
		ini_set( "smtp_port", apply_filters('usces_filter_send_mail_port', 25, $para) );
	}
	ini_set( "sendmail_from", "" );
	
	$mails = explode( ',', $para['to_address'] );
	$to_mailes = array();
	foreach( $mails as $mail ){
		if (strpos($mail, '..') !== false || strpos($mail, '.@') !== false) {
			$name = str_replace(strstr($mail, '@'), '', $mail);
			if( '"' != substr($name, 0, 1) && '"' != substr($name, -1) ){
				$to_mailes[] = str_replace($name, '"'.$name.'"', $mail);
			}else{
				$to_mailes[] = $mail;
			}
		}elseif( is_email( trim($mail) ) ){
			$to_mailes[] = $mail;
		}else{
			$to_mailes[] = NULL;
		}
	}
				
	if( !empty( $to_mailes ) ){
		$res = @wp_mail( $to_mailes , $subject , $message, $header );
	}else{
		$res = false;
	}
	
	return $res;

}

function usces_send_mail( $para ) {
	global $usces;
	
	$from_name = $para['from_name'];
	$from_address = $para['from_address'];
	if (strpos($para['from_address'], '..') !== false || strpos($para['from_address'], '.@') !== false) {
		$fname = str_replace(strstr($para['from_address'], '@'), '', $para['from_address']);
		if( '"' != substr($fname, 0, 1) && '"' != substr($fname, -1) ){
			$para['from_address'] = str_replace($fname, '"RFC_violation"', $para['from_address']);
			$from_name = $para['from_name'] . '(' . $from_address . ')';
		}
	}
	$from_name = html_entity_decode( $from_name, ENT_QUOTES );
	$from_name = mb_encode_mimeheader($from_name);
	//$from = htmlspecialchars(html_entity_decode($from_name, ENT_QUOTES));
	//$para['from_name'] = $from;
	$para['from_name'] = $from_name;

	$usces->mail_para = $para;
	add_action('phpmailer_init','usces_send_mail_init', 5);

	$subject = html_entity_decode($para['subject'], ENT_QUOTES);
	$message = $para['message'];

	$attachments = isset($para['attachments']) ? $para['attachments']: array();

	$mails = explode( ',', $para['to_address'] );
	$to_mailes = array();
	foreach( $mails as $mail ){
		if( is_email( trim($mail) ) ){
			$to_mailes[] = $mail;
		}
	}
	$res = false;
	foreach($to_mailes as $to_maile){
		if (strpos($to_maile, '..') !== false || strpos($to_maile, '.@') !== false){
			$headers = "From: " . $from_address . "\r\n";
			$res = @mb_send_mail($to_maile, $subject, $message, $headers );
		}elseif( !empty( $to_maile ) ){
			$headers = ( !empty($para['headers']) ) ? $para['headers'] : '';
			$res = @wp_mail( $to_maile , $subject , $message, $headers, $attachments );
		}else{
			$res = false;
		}
	}
	
	remove_action('phpmailer_init','usces_send_mail_init', 5);
	$usces->mail_para = array();
	return $res;

}

function usces_send_mail_init($phpmailer){
	global $usces;

	$phpmailer->Mailer = 'mail';
	$phpmailer->From = $usces->mail_para['from_address'];
	$phpmailer->FromName = apply_filters('usces_filter_send_mail_from', $usces->mail_para['from_name'], $usces->mail_para);
	$phpmailer->Sender = $usces->mail_para['from_address'];
	
	do_action('usces_filter_phpmailer_init', array( &$phpmailer ));
}

function usces_recovery_session_data(){
	global $usces;

	$absolute_trans_id = isset($_REQUEST['absolute_trans_id']) ? $_REQUEST['absolute_trans_id'] : 0;

	if( !$absolute_trans_id ){
		return;
	}

	$order_data = usces_get_acting_data( $absolute_trans_id );

	if( !empty($order_data['usces_entry']) ){
		$_SESSION['usces_member'] = $order_data['usces_member'];
		$_SESSION['usces_cart'] = $order_data['usces_cart'];
		$_SESSION['usces_entry'] = $order_data['usces_entry'];
	}
}

function usces_reg_orderdata( $results = array() ) {
	global $wpdb, $usces;

	$options = get_option('usces');
	$cart = $usces->cart->get_cart();
	$entry = $usces->cart->get_entry();

	if( empty($cart) ){

		//Trying to get back the entry data
		usces_recovery_session_data();
		$cart = $usces->cart->get_cart();
		$entry = $usces->cart->get_entry();
		
		if( empty($cart) ){
			$linkkey = usces_get_link_key( $results );
			$log = array( 'acting'=>'reg_orderdata', 'key'=>$linkkey, 'result'=>'SESSION EMPTY', 'data'=>$_REQUEST );
			usces_save_order_acting_error( $log );
			return 0;
		}else{
			$log = array( 'acting'=>'reg_orderdata', 'key'=>$_REQUEST['absolute_trans_id'], 'result'=>'OK SESSION RECOVERY', 'data'=>$_REQUEST );
			usces_save_order_acting_error( $log );

		}
	}

	//Anti-spam
	if( (empty($entry['customer']['name1']) && empty($entry['customer']['name2'])) || empty($entry['customer']['mailaddress1']) || empty($entry) || empty($cart) ) {
		usces_log('reg_orderdata : Anti-spam', 'acting_transaction.log');
		return '1';
	}

	$charging_type = $usces->getItemChargingType($cart[0]['post_id']);

	$item_total_price = $usces->get_total_price( $cart );
	$member = $usces->get_member();
	$order_table_name = $wpdb->prefix . "usces_order";
	$order_table_meta_name = $wpdb->prefix . "usces_order_meta";
	$member_table_name = usces_get_tablename( 'usces_member' );
	$set = $usces->getPayments( $entry['order']['payment_name'] );
	$status = '';
	$gmtdate = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()));
	if( 'continue' == $charging_type ){
		$order_modified = substr($gmtdate, 0, 10);
	}else{
		$noreceipt_status_table = apply_filters( 'usces_filter_noreceipt_status', get_option( 'usces_noreceipt_status' ) );

		$status = ( in_array( $set['settlement'], $noreceipt_status_table ) ) ? 'noreceipt' : '';
		$order_modified = NULL;
	}

	if( $set['module'] == 'paypal.php' || $set['settlement'] == 'acting_paypal_ec' ) {
		if( ( isset($results['payment_status']) && $results['payment_status'] != 'Completed' ) || ( isset($results['profile_status']) && $results['profile_status'] != 'ActiveProfile' ) ) {
			$status = 'pending';
		}
	}

	$status = apply_filters( 'usces_filter_reg_orderdata_status', $status, $entry );
	$order_date = ( isset($results['order_date']) ) ? $results['order_date'] : $gmtdate;
	$delidue_date = ( isset($entry['order']['delidue_date']) ) ? $entry['order']['delidue_date'] : NULL;
	$query = $wpdb->prepare(
				"INSERT INTO $order_table_name (
					`mem_id`, `order_email`, `order_name1`, `order_name2`, `order_name3`, `order_name4`, 
					`order_zip`, `order_pref`, `order_address1`, `order_address2`, `order_address3`, 
					`order_tel`, `order_fax`, `order_delivery`, `order_cart`, `order_note`, `order_delivery_method`, `order_delivery_date`, `order_delivery_time`, 
					`order_payment_name`, `order_condition`, `order_item_total_price`, `order_getpoint`, `order_usedpoint`, `order_discount`, 
					`order_shipping_charge`, `order_cod_fee`, `order_tax`, `order_date`, `order_modified`, `order_status`, `order_delidue_date` ) 
				VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %f, %d, %d, %f, %f, %f, %f, %s, %s, %s, %s)", 
					$member['ID'], 
					$entry['customer']['mailaddress1'], 
					$entry['customer']['name1'], 
					$entry['customer']['name2'], 
					$entry['customer']['name3'], 
					$entry['customer']['name4'], 
					$entry['customer']['zipcode'], 
					$entry['customer']['pref'], 
					$entry['customer']['address1'], 
					$entry['customer']['address2'], 
					$entry['customer']['address3'], 
					$entry['customer']['tel'], 
					$entry['customer']['fax'], 
					serialize($entry['delivery']), 
					serialize($cart), 
					$entry['order']['note'], 
					$entry['order']['delivery_method'], 
					$entry['order']['delivery_date'], 
					$entry['order']['delivery_time'], 
					$entry['order']['payment_name'], 
					serialize($entry['condition']), 
					$item_total_price, 
					$entry['order']['getpoint'], 
					$entry['order']['usedpoint'], 
					$entry['order']['discount'], 
					$entry['order']['shipping_charge'], 
					$entry['order']['cod_fee'], 
					$entry['order']['tax'], 
					$order_date, 
					$order_modified, 
					$status, 
					$delidue_date 
				);

	$res = $wpdb->query( $query );

	if( $res === false){
		$order_id = false;
		usces_log('reg_orderdata : ' . $wpdb->last_error, 'database_error.log');
	}else{
		$order_id = $wpdb->insert_id;
	}

	if ( !$order_id ) {
		usces_log('reg_error_entry : '.print_r($entry, true), 'acting_transaction.log');
		return false;
	}

	$usces->cart->set_order_entry( array('ID' => $order_id) );
	$usces->set_order_meta_value('customer_country', $entry['customer']['country'], $order_id);

	if( usces_is_tax_display() && usces_is_reduced_taxrate() ) {
		$usces_tax = Welcart_Tax::get_instance();
		$materials = array(
			'total_items_price' => $entry['order']['total_items_price'],
			'discount' => ( isset( $entry['order']['discount'] ) ) ? $entry['order']['discount'] : 0,
			'shipping_charge' => ( isset( $entry['order']['shipping_charge'] ) ) ? $entry['order']['shipping_charge'] : 0,
			'cod_fee' => ( isset( $entry['order']['cod_fee'] ) ) ? $entry['order']['cod_fee'] : 0,
			'use_point' => ( isset( $entry['order']['usedpoint'] ) ) ? $entry['order']['usedpoint'] : 0,
			'carts' => $cart,
			'condition' => $entry['condition'],
		);
		$usces_tax->get_order_tax( $materials );
		$usces->set_order_meta_value( 'subtotal_standard', $usces_tax->subtotal_standard, $order_id );
		$usces->set_order_meta_value( 'subtotal_reduced', $usces_tax->subtotal_reduced, $order_id );
		$usces->set_order_meta_value( 'discount_standard', $usces_tax->discount_standard, $order_id );
		$usces->set_order_meta_value( 'discount_reduced', $usces_tax->discount_reduced, $order_id );
		if( 'include' == $usces->options['tax_mode'] ) {
			$usces->set_order_meta_value( 'tax_standard', 0, $order_id );
			$usces->set_order_meta_value( 'tax_reduced', 0, $order_id );
		} else {
			$usces->set_order_meta_value( 'tax_standard', $usces_tax->tax_standard, $order_id );
			$usces->set_order_meta_value( 'tax_reduced', $usces_tax->tax_reduced, $order_id );
		}
	}

	if ( $member['ID'] && 'activate' == $options['membersystem_state'] && 'activate' == $options['membersystem_point'] ) {

		$mquery = '';
		if( usces_is_complete_settlement( $entry['order']['payment_name'], $status ) ) {
			if( apply_filters( 'usces_action_acting_getpoint_switch', true, $order_id, true) ){
				$mquery = $wpdb->prepare(
							"UPDATE $member_table_name SET mem_point = (mem_point + %d - %d) WHERE ID = %d", 
							$entry['order']['getpoint'], $entry['order']['usedpoint'], $member['ID']);
			}else{
				$mquery = $wpdb->prepare(
							"UPDATE $member_table_name SET mem_point = (mem_point - %d) WHERE ID = %d", 
							$entry['order']['usedpoint'], $member['ID']);
			}
		} elseif( 0 < $entry['order']['usedpoint'] ) {
			$mquery = $wpdb->prepare(
						"UPDATE $member_table_name SET mem_point = (mem_point - %d) WHERE ID = %d", 
						$entry['order']['usedpoint'], $member['ID']);
		}
		if( $mquery ) {
			$wpdb->query( $mquery );
			$mquery = $wpdb->prepare("SELECT mem_point FROM $member_table_name WHERE ID = %d", $member['ID']);
			$point = $wpdb->get_var( $mquery );
			$_SESSION['usces_member']['point'] = $point;
		}

	}

	if ( !empty($entry['reserve']) ) {
		foreach ( $entry['reserve'] as $key => $value ) {
			if ( is_array($value) )
				 $value = serialize($value);
			$usces->set_order_meta_value($key, $value, $order_id);
		}
	}

	if ( !preg_match('/pending|noreceipt/', $status) ) {
		$usces->set_order_meta_value('receipted_date', $gmtdate, $order_id);
	}

	if( !empty($entry['custom_order']) ) {
		foreach( $entry['custom_order'] as $key => $value ) {
			$csod_key = 'csod_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($csod_key, $value, $order_id);
		}
	}
	if( !empty($entry['custom_customer']) ) {
		foreach( $entry['custom_customer'] as $key => $value ) {
			$cscs_key = 'cscs_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($cscs_key, $value, $order_id);
		}
	}
	if( !empty($entry['custom_delivery']) ) {
		foreach( $entry['custom_delivery'] as $key => $value ) {
			$csde_key = 'csde_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($csde_key, $value, $order_id);
		}
	}

	$args = array('cart'=>$cart, 'entry'=>$entry, 'order_id'=>$order_id, 'member_id'=>$member['ID'], 'payments'=>$set, 'charging_type'=>$charging_type, 'results'=>$results);
	do_action('usces_action_reg_orderdata', $args);

	return $order_id;
}

function usces_new_orderdata() {
	global $wpdb, $usces;
	$_POST = $usces->stripslashes_deep_post($_POST);

	$usces->cart->crear_cart();
	$cart = $usces->cart->get_cart();
	$item_total_price = $usces->get_total_price( $cart );
	$entry = $usces->cart->get_entry();
	$member_id = $usces->get_memberid_by_email($_POST['customer']['mailaddress']);
	$order_table_name = $wpdb->prefix . "usces_order";
	$order_table_meta_name = $wpdb->prefix . "usces_order_meta";
	$member_table_name = usces_get_tablename( 'usces_member' );
	$set = $usces->getPayments( $_POST['offer']['payment_name'] );
	if( isset($_POST['offer']['receipt']) ) {
		$status = $_POST['offer']['receipt'].',';
	} else {
		$noreceipt_status_table = apply_filters( 'usces_filter_noreceipt_status', get_option( 'usces_noreceipt_status' ) );
		$status = ( in_array( $set['settlement'], $noreceipt_status_table ) ) ? 'noreceipt' : '';
	}
	$status .= ( !WCUtils::is_blank($_POST['offer']['taio']) && $_POST['offer']['taio'] != '#none#' ) ? $_POST['offer']['taio'].',' : '';
	$status .= $_POST['offer']['admin'];
	$status = apply_filters( 'usces_filter_new_orderdata_status', $status, $entry );
	$order_conditions = $usces->get_condition();
	$gmtdate = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()));

	$delivery_date = $_POST['offer']['delivery_date'];
	if( !usces_is_date( $delivery_date ) ) {
		$delivery_date = apply_filters( 'usces_filter_label_delivery_date_no_preference', __( 'Non-request', 'usces_dual' ) );
	}

	$query = $wpdb->prepare(
				"INSERT INTO $order_table_name (
					`mem_id`, `order_email`, `order_name1`, `order_name2`, `order_name3`, `order_name4`, 
					`order_zip`, `order_pref`, `order_address1`, `order_address2`, `order_address3`, 
					`order_tel`, `order_fax`, `order_delivery`, `order_cart`, `order_note`, `order_delivery_method`, `order_delivery_date`, `order_delivery_time`, 
					`order_payment_name`, `order_condition`, `order_item_total_price`, `order_getpoint`, `order_usedpoint`, `order_discount`, 
					`order_shipping_charge`, `order_cod_fee`, `order_tax`, `order_date`, `order_modified`, `order_status`, `order_delidue_date` ) 
				VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %f, %d, %d, %f, %f, %f, %f, %s, %s, %s, %s)", 
					$member_id, 
					$_POST['customer']['mailaddress'], 
					$_POST['customer']['name1'], 
					$_POST['customer']['name2'], 
					$_POST['customer']['name3'], 
					$_POST['customer']['name4'], 
					$_POST['customer']['zipcode'], 
					$_POST['customer']['pref'], 
					$_POST['customer']['address1'], 
					$_POST['customer']['address2'], 
					$_POST['customer']['address3'], 
					$_POST['customer']['tel'], 
					$_POST['customer']['fax'], 
					serialize($_POST['delivery']), 
					serialize($cart), 
					$_POST['offer']['note'], 
					$_POST['offer']['delivery_method'], 
					$delivery_date, 
					$_POST['offer']['delivery_time'], 
					$_POST['offer']['payment_name'], 
					serialize($order_conditions), 
					$item_total_price, 
					$_POST['offer']['getpoint'], 
					$_POST['offer']['usedpoint'], 
					$_POST['offer']['discount'], 
					$_POST['offer']['shipping_charge'], 
					$_POST['offer']['cod_fee'], 
					$_POST['offer']['tax'], 
					$gmtdate, 
					null, 
					$status, 
					$_POST['offer']['delidue_date']
				);

	$res = $wpdb->query( $query );
	$order_id = $wpdb->insert_id;
	$_REQUEST['order_id'] = $wpdb->insert_id;

	if( !$order_id ) {
		return false;
	}

	$usces->set_order_meta_value('customer_country', $_POST['customer']['country'], $order_id);

	if( usces_is_tax_display() && usces_is_reduced_taxrate() ) {
		$usces_tax = Welcart_Tax::get_instance();
		$materials = array(
			'total_items_price' => $item_total_price,
			'discount' => $_POST['offer']['discount'],
			'shipping_charge' => $_POST['offer']['shipping_charge'],
			'cod_fee' => $_POST['offer']['cod_fee'],
			'use_point' => $_POST['offer']['usedpoint'],
			'carts' => $cart,
			'condition' => $order_conditions,
		);
		$usces_tax->get_order_tax( $materials );
		$usces->set_order_meta_value( 'subtotal_standard', $usces_tax->subtotal_standard, $order_id );
		$usces->set_order_meta_value( 'subtotal_reduced', $usces_tax->subtotal_reduced, $order_id );
		$usces->set_order_meta_value( 'discount_standard', $usces_tax->discount_standard, $order_id );
		$usces->set_order_meta_value( 'discount_reduced', $usces_tax->discount_reduced, $order_id );
		if( 'include' == $usces->options['tax_mode'] ) {
			$usces->set_order_meta_value( 'tax_standard', 0, $order_id );
			$usces->set_order_meta_value( 'tax_reduced', 0, $order_id );
		} else {
			$usces->set_order_meta_value( 'tax_standard', $usces_tax->tax_standard, $order_id );
			$usces->set_order_meta_value( 'tax_reduced', $usces_tax->tax_reduced, $order_id );
		}
	}

	if( !empty($_POST['custom_order']) ) {
		foreach( $_POST['custom_order'] as $key => $value ) {
			$csod_key = 'csod_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($csod_key, $value, $order_id);
		}
	}
	if( !empty($_POST['custom_customer']) ) {
		foreach( $_POST['custom_customer'] as $key => $value ) {
			$cscs_key = 'cscs_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($cscs_key, $value, $order_id);
		}
	}
	if( !empty($_POST['custom_delivery']) ) {
		foreach( $_POST['custom_delivery'] as $key => $value ) {
			$csde_key = 'csde_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$usces->set_order_meta_value($csde_key, $value, $order_id);
		}
	}
	if ( !preg_match('/pending|noreceipt/', $status) ) {
		$usces->set_order_meta_value('receipted_date', $gmtdate, $order_id);
	}
	$args = array('cart'=>$cart, 'entry'=>$entry, 'order_id'=>$order_id, 'member_id'=>$member_id, 'payments'=>$set);
	do_action('usces_action_reg_orderdata', $args);

	$usces->cart->crear_cart();
	return $res;
}

function usces_new_memberdata(){
	global $wpdb, $usces;

	$_POST = $usces->stripslashes_deep_post($_POST);
	//$pass = usces_get_hash(trim($_POST['member']['password']));
	$salt = usces_get_salt( '', 1 );
	$pass = usces_get_hash( trim($_POST['member']['password']), $salt, 1 );
	$member_table_name = usces_get_tablename( 'usces_member' );
	$member_table_meta_name = usces_get_tablename( 'usces_member_meta' );
	$gmtdate = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()));
	$query = $wpdb->prepare("INSERT INTO $member_table_name
			(`mem_email`, `mem_pass`, `mem_status`, `mem_cookie`, `mem_point`, 
			`mem_name1`, `mem_name2`, `mem_name3`, `mem_name4`, `mem_zip`, `mem_pref`, 
			`mem_address1`, `mem_address2`, `mem_address3`, `mem_tel`, `mem_fax`, 
			`mem_delivery_flag`, `mem_delivery`, `mem_registered`, `mem_nicename`) 
			VALUES (%s, %s, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s)", 
			trim($_POST['member']['email']),
			$pass, 
			trim($_POST['member']['status']),
			"",
			trim($_POST['member']['point']),
			trim($_POST['member']['name1']),
			trim($_POST['member']['name2']),
			trim($_POST['member']['name3']),
			trim($_POST['member']['name4']),
			trim($_POST['member']['zipcode']),
			trim($_POST['member']['pref']),
			trim($_POST['member']['address1']),
			trim($_POST['member']['address2']),
			trim($_POST['member']['address3']),
			trim($_POST['member']['tel']),
			trim($_POST['member']['fax']),
			'',
			'',
			$gmtdate,
			'');
	$res[0] = $wpdb->query( $query );

	if(false === $res[0]) {
		return false;
	}

	$member_id = $wpdb->insert_id;
	$_REQUEST['member_id'] = $wpdb->insert_id;
	if( !$member_id ) {
		return false;
	}

	$usces->set_member_meta_value('customer_country', $_POST['member']['country'], $member_id);
	$csmb_meta = usces_has_custom_field_meta( 'member' );
	if( is_array($csmb_meta) ) {
		foreach( $csmb_meta as $key => $entry ) {
			if( '4' == $entry['means'] ) {
				$usces->del_member_meta( 'csmb_'.$key, $member_id);
			}
		}
	}
	$i = 1;
	if( !empty($_POST['custom_member']) ) {
		foreach( $_POST['custom_member'] as $key => $value ) {
			$csmb_key = 'csmb_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$res[$i] = $usces->set_member_meta_value($csmb_key, $value, $member_id);
			if(false === $res[$i]) 
				return false;
			$i++;
		}
	}
	if( !empty($salt) ) {
		$res[$i] = $usces->set_member_meta_value( 'mem_salt', $salt, $member_id );
		$i++;
	}
	$result = ( 0 < array_sum($res) ) ? 1 : 0;

	do_action( 'usces_action_post_new_memberdata', $member_id, $res[0] );

	return $result;
}

function usces_delete_memberdata( $ID = 0 ) {
	global $wpdb, $usces;
	
	if( 0 === $ID ){
		if(!isset($_REQUEST['member_id']) || WCUtils::is_blank($_REQUEST['member_id']) )
			return 0;
		$ID = $_REQUEST['member_id'];
	}
	do_action('usces_action_pre_delete_memberdata', $ID);

	$member_table_name = usces_get_tablename( 'usces_member' );
	$member_table_meta_name = usces_get_tablename( 'usces_member_meta' );

	$query = $wpdb->prepare("DELETE FROM $member_table_name WHERE ID = %d", $ID);
	$res = $wpdb->query( $query );
	if($res) {
		$query = $wpdb->prepare("DELETE FROM $member_table_meta_name WHERE member_id = %d", $ID);
		$wpdb->query( $query );
	}
	do_action('usces_action_post_delete_memberdata', $res, $ID);
	
	return $res;
}

function usces_update_memberdata() {
	global $wpdb, $usces;
	$_POST = $usces->stripslashes_deep_post($_POST);
	
	$member_table_name = usces_get_tablename( 'usces_member' );
	$member_table_meta_name = usces_get_tablename( 'usces_member_meta' );

	$ID = (int)$_REQUEST['member_id'];
	$name3 = ( isset( $_POST['member']['name3'] ) ) ? $_POST['member']['name3'] : '';
	$name4 = ( isset( $_POST['member']['name4'] ) ) ? $_POST['member']['name4'] : '';

	$query = $wpdb->prepare(
				"UPDATE $member_table_name SET 
					`mem_email`=%s, `mem_status`=%s, `mem_point`=%d, `mem_name1`=%s, `mem_name2`=%s, 
					`mem_name3`=%s, `mem_name4`=%s, `mem_zip`=%s, `mem_pref`=%s, `mem_address1`=%s, 
					`mem_address2`=%s, `mem_address3`=%s, `mem_tel`=%s, `mem_fax`=%s 
				WHERE ID = %d", 
					$_POST['member']['email'], 
					$_POST['member']['status'], 
					$_POST['member']['point'], 
					$_POST['member']['name1'], 
					$_POST['member']['name2'], 
					$name3, 
					$name4, 
					$_POST['member']['zipcode'], 
					$_POST['member']['pref'], 
					$_POST['member']['address1'], 
					$_POST['member']['address2'], 
					$_POST['member']['address3'], 
					$_POST['member']['tel'], 
					$_POST['member']['fax'], 
					$ID
				);

	do_action('usces_action_pre_update_memberdata', $ID);
	$res[0] = $wpdb->query( $query );
	
	do_action( 'usces_action_post_update_memberdata', $ID, $res[0]);
	
	if(false === $res[0]) 
		return false;
		
	$usces->set_member_meta_value('customer_country', $_POST['member']['country'], $ID);
	$csmb_meta = usces_has_custom_field_meta( 'member' );
	if( is_array($csmb_meta) ) {
		foreach( $csmb_meta as $key => $entry ) {
			if( '4' == $entry['means'] ) {
				$usces->del_member_meta( 'csmb_'.$key, $ID );
			}
		}
	}
	$i = 1;
	if( !empty($_POST['custom_member']) ) {
		foreach( $_POST['custom_member'] as $key => $value ) {
			$csmb_key = 'csmb_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$res[$i] = $usces->set_member_meta_value($csmb_key, $value, $ID);
			if(false === $res[$i]) 
				return false;
			$i++;
		}
	}

	$result = ( 0 < array_sum($res) ) ? 1 : 0;
	//return $res;
	return $result;
}

function usces_delete_orderdata() {
	global $wpdb, $usces;
	if(!isset($_REQUEST['order_id']) || WCUtils::is_blank($_REQUEST['order_id']) ) return 0;
	$order_table = $wpdb->prefix . "usces_order";
	$order_meta_table = $wpdb->prefix . "usces_order_meta";
	$ID = $_REQUEST['order_id'];

	$del = usces_delete_order_check( $ID );
	if( $del ) {

	$query = $wpdb->prepare("SELECT * FROM $order_table WHERE ID = %d", $ID);
	$order_data = $wpdb->get_row( $query, OBJECT );
	$point = 0;
	if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] && !empty($order_data->mem_id) && !$usces->is_status('cancel', $order_data->order_status) ) {
		if( 0 < $order_data->order_getpoint ) {
			if( usces_is_complete_settlement( $order_data->order_payment_name, $order_data->order_status ) || $usces->is_status('receipted', $order_data->order_status) ) {
				$point += $order_data->order_getpoint;
			}
		}
		if( 0 < $order_data->order_usedpoint ) {
			$point -= $order_data->order_usedpoint;
		}
	}

	$query = $wpdb->prepare("DELETE FROM $order_table WHERE ID = %d", $ID);
	$res = $wpdb->query( $query );
	
	$args = compact( 'ID', 'point', 'res' );
	
	if($res){
		
		do_action('usces_action_del_orderdata', $order_data, $args);

		$query = $wpdb->prepare("DELETE FROM $order_meta_table WHERE order_id = %d", $ID);
		$wpdb->query( $query );
		
		usces_delete_ordercartdata( NULL, $ID );
		
		if( 0 != $point ) usces_restore_point( $order_data->mem_id, $point );
	}

	} else {
		$res = true;
	}

	return $res;
}

function usces_update_serialized_cart(){
	global $wpdb, $usces;
	if(!isset($_REQUEST['order_id']) || WCUtils::is_blank($_REQUEST['order_id']) ) return 0;

	$order_table_name = $wpdb->prefix . "usces_order";
	$ID = $_REQUEST['order_id'];
	$usces->cart->crear_cart();
	$usces->cart->upCart();
	$cart = $usces->cart->get_cart();
	if( $cart && is_array( $cart ) && 0 < count( $cart ) ) {
		$idx = count($cart)-1;
		$post_id = $cart[$idx]['post_id'];
		$sku = $cart[$idx]['sku'];
		$sku_code = esc_attr(urldecode($sku));
		$cartItemName = $usces->getCartItemName($post_id, $sku_code);
		$skuPrice = $cart[$idx]['price'];

		$query = $wpdb->prepare("UPDATE $order_table_name SET `order_cart`=%s WHERE ID = %d", serialize($cart), $ID);
		$res = $wpdb->query( $query );

		$usces->cart->crear_cart();
	}
}

function usces_delete_serialized_cart(){
	global $wpdb, $usces;
	if(!isset($_REQUEST['order_id']) || WCUtils::is_blank($_REQUEST['order_id']) ) return 0;

		$indexs = array_keys($_POST['delButton']);
		$index = $indexs[0];
		$ids = array_keys($_POST['delButton'][$index]);
		$post_id = $ids[0];
		$skus = array_keys($_POST['delButton'][$index][$post_id]);
		$sku = $skus[0];
		
		$usces->up_serialize($index, $post_id, $sku);
		do_action('usces_cart_del_row', $index);
		
		if(isset($_SESSION['usces_cart'][$usces->serial]))
			unset($_SESSION['usces_cart'][$usces->serial]);
			
		unset( $_SESSION['usces_entry']['order']['usedpoint'] );
}

function usces_get_serialized_cart($order_id){
	global $wpdb, $usces;

	$order_table_name = $wpdb->prefix . "usces_order";
	$query = $wpdb->prepare("SELECT order_cart FROM $order_table_name WHERE ID = %d", $order_id);
	$order_cart = $wpdb->get_var( $query );
	$cart = unserialize($order_cart);
	foreach( $cart as $cart_index => $cart_row ){
		$options = array();
		if( !empty( $cart_row['options'] ) ){
			foreach( $cart_row['options'] as $key => $value ){
				$key = urldecode($key);
				if( is_array($value) ){
					foreach( $value as $vk => $vv ){
						$value[$vk] = urldecode($vv);
					}
					$options[$key] = $value;
				}else{
					$options[$key] = urldecode($value);
				}
			}
			$cart_row['options'] = $options;
		}
		$cart[$cart_index] = $cart_row;
	}
	return $cart;
}

function usces_update_ordercart() {
	$ordercart_table_name = $wpdb->prefix . "usces_ordercart";
	foreach( $_POST['skuPrice'] as $cart_id => $price ){
		$quantity = $_POST['quant'][$cart_id];
		$query = $wpdb->prepare("
			UPDATE $ordercart_table_name SET price = %f, quantity = %f WHERE cart_id = %d 
			", $price, $quantity, $cart_id );
		$wpdb->query( $query );
	}
	
	$ordercart_meta_table_name = $wpdb->prefix . "usces_ordercart_meta";
	foreach( $_POST['itemOption'] as $cartmeta_id => $value ){
		if(is_array($value)) {
			foreach($value as $v){
				$opval[$v] = $v;
			}
			$value = serialize($opval);
		}
		$query = $wpdb->prepare("
			UPDATE $ordercart_meta_table_name SET meta_value = %s WHERE cartmeta_id = %d 
			", $value, $cartmeta_id );
		$wpdb->query( $query );
	}
	if( $res === false ) {
		$res = "-1#usces#";
	} else {
		$res = $skuPrice."#usces#".$cartItemName;
	}
	//return $res;
}

function usces_update_ordercartdata( $order_id ) {
	global $wpdb, $usces;

	if( !isset($_POST['skuPrice']) )
		return;
		
	$ordercart_table_name = $wpdb->prefix . "usces_ordercart";
	$ordercart_meta_table_name = $wpdb->prefix . "usces_ordercart_meta";
	foreach( (array)$_POST['skuPrice'] as $cart_id => $price ){
		$quantity = $_POST['quant'][$cart_id];
		$query = $wpdb->prepare("
			UPDATE $ordercart_table_name SET price = %f, quantity = %f WHERE cart_id = %d 
			", $price, $quantity, $cart_id );
		$wpdb->query( $query );
		
		//itemOptionチェックボックスとラジオのチェック
		$cart = usces_get_ordercartdata_row( $cart_id );
		$item_opts = usces_get_opts( $cart['post_id'], 'name' );
		foreach( $item_opts as $key => $iopts ){
			if( 3 == $iopts['means'] || 4 == $iopts['means'] ){
				$cart_meta = usces_get_ordercart_meta( 'option', $cart_id, $iopts['name'] );
				$cart_meta_id = $cart_meta[0]['cartmeta_id'];
				//POSTが無ければmeta_valueをNULLに変更
				//$cart_meta['meta_value']にデータがある場合は変更せず
				if( !isset($_POST['itemOption'][$cart_meta_id]) && WCUtils::is_blank( $cart_meta[0]['meta_value'] ) ){
					$query = $wpdb->prepare(
						"UPDATE $ordercart_meta_table_name SET meta_value = %s WHERE cartmeta_id = %d"
						, NULL, $cart_meta_id );
					$wpdb->query( $query );
				}
			}
		}
	}
	
	if( !isset($_POST['itemOption']) )
		return;

	$ordercart_meta_table_name = $wpdb->prefix . "usces_ordercart_meta";
	foreach( (array)$_POST['itemOption'] as $cartmeta_id => $value ){
		$post_id = usces_get_post_id_by_cartmeta_id( $cartmeta_id );
		$option_fields = usces_get_opts( $post_id, 'name' );
		$ordercart_metas = usces_get_ordercart_meta_by_id( $cartmeta_id ); 
		$means = $option_fields[$ordercart_metas['meta_key']]['means'];
		if(is_array($value)) {
			$opval =array();
			if( 4 == $means ){
				foreach($value as $v){
					$opval[] = urldecode($v);
				}
			}else{
				foreach($value as $v){
					$opval[$v] = urldecode($v);
				}
			}
			$value = serialize($opval);
		}else{
			if( 3 == $means ){
				$value = urldecode($value);
			}
		}
		$query = $wpdb->prepare("
			UPDATE $ordercart_meta_table_name SET meta_value = %s WHERE cartmeta_id = %d 
			", $value, $cartmeta_id );
		$wpdb->query( $query );
	}
}

function usces_delete_ordercartdata( $cart_id, $order_id = NULL ){
	global $wpdb, $usces;
		
	$ordercart_table_name = $wpdb->prefix . "usces_ordercart";
	$ordercart_meta_table_name = $wpdb->prefix . "usces_ordercart_meta";
	
	if( NULL != $cart_id ){
		
		$query = $wpdb->prepare("DELETE FROM $ordercart_table_name WHERE cart_id = %d", $cart_id );
		$wpdb->query( $query );
		
		$mquery = $wpdb->prepare("
			DELETE FROM $ordercart_meta_table_name WHERE cart_id = %d", $cart_id );
		$wpdb->query( $mquery );
		
	}elseif( NULL != $order_id ){
		
		$query = $wpdb->prepare("SELECT cart_id FROM $ordercart_table_name WHERE order_id = %d", $order_id );
		$cat_ids = $wpdb->get_col( $query );
		
		$query = $wpdb->prepare("DELETE FROM $ordercart_table_name WHERE order_id = %d", $order_id );
		$wpdb->query( $query );
		
		foreach($cat_ids as $id){
			$mquery = $wpdb->prepare("
				DELETE FROM $ordercart_meta_table_name WHERE cart_id = %d", $id );
			$wpdb->query( $mquery );
		}
		
	}
}
function usces_get_ordercartdata_row( $cart_id ){
	global $usces, $wpdb;
	
	$cart_table = $wpdb->prefix . "usces_ordercart";
	
	$query = $wpdb->prepare("SELECT * FROM $cart_table WHERE cart_id = %d", $cart_id );
	$cart = $wpdb->get_row( $query, ARRAY_A );
	return $cart;
}

function usces_get_ordercartdata( $order_id ){
	global $usces, $wpdb;
	
	$cart_table = $wpdb->prefix . "usces_ordercart";
	$cart_meta_table = $wpdb->prefix . "usces_ordercart_meta";
	
	$query = $wpdb->prepare("SELECT * FROM $cart_table WHERE order_id = %d ORDER BY cart_id", $order_id );
	$cart = $wpdb->get_results( $query, ARRAY_A );
	
	foreach( $cart as $key => $value ){
		$cart[$key]['sku'] = $value['sku_code'];
		$query = $wpdb->prepare("SELECT * FROM $cart_meta_table WHERE cart_id = %d ORDER BY cartmeta_id", $value['cart_id'] );
		$results = $wpdb->get_results( $query, ARRAY_A );
		foreach((array)$results as $value ){
			switch( $value['meta_type'] ){
				case 'option':
					$cart[$key]['options'][$value['meta_key']] = $value['meta_value'];
					break;
				case 'advance':
					$cart[$key]['advance'][$value['meta_key']] = $value['meta_value'];
					break;
			}
		}
		if( !isset($cart[$key]['options']) )
			$cart[$key]['options'] = array();
		if( !isset($cart[$key]['advance']) )
			$cart[$key]['advance'] = array();
	}
	
	return $cart;
}


function usces_update_ordercheck() {
	global $wpdb, $usces;

	$tableName = $wpdb->prefix . "usces_order";
	$order_id = $_POST['order_id'];
	$checked = $_POST['checked'];

	$query = $wpdb->prepare("SELECT `order_check` FROM $tableName WHERE ID = %d", $order_id);
	$res = $wpdb->get_var( $query );

	$checkfield = unserialize($res);
	if( !isset($checkfield[$checked]) ) $checkfield[$checked] = $checked;
	$query = $wpdb->prepare("UPDATE $tableName SET `order_check` = %s WHERE ID = %d", serialize($checkfield), $order_id);
	$res = $wpdb->query( $query );
	
	if($res)
		return $checked;
	else
		return 'error';
}
function usces_update_orderdata() {
	global $wpdb, $usces;
	$_POST = $usces->stripslashes_deep_post($_POST);
	
	$order_table_name = $wpdb->prefix . "usces_order";
	$order_table_meta_name = $wpdb->prefix . "usces_order_meta";
	$member_table_name = usces_get_tablename( 'usces_member' );

	$ID = $_REQUEST['order_id'];
	$old_cart = usces_get_ordercartdata( $ID );
	
	$query = $wpdb->prepare("SELECT * FROM $order_table_name WHERE ID = %d", $ID);
	$old_orderdata = $wpdb->get_row( $query );
	$old_status = $old_orderdata->order_status;
	
	if(isset($_POST['delButtonAdmin'])) {
		foreach( $_POST['delButtonAdmin'] as $del_cart_id => $delvalue ){
			do_action('usces_admin_delete_orderrow', $del_cart_id, $ID, $old_cart );
			usces_delete_ordercartdata( $del_cart_id, NULL );
		}
	}else{
		usces_update_ordercartdata( $ID );
	}
	
	$new_cart = usces_get_ordercartdata( $ID );
	$gmtdate = get_date_from_gmt(gmdate('Y-m-d H:i:s', time()));

	$item_total_price = $usces->get_total_price( $new_cart );
	$taio = isset($_POST['offer']['taio']) ? $_POST['offer']['taio'] : '';
	$receipt = isset($_POST['offer']['receipt']) ? $_POST['offer']['receipt'] : '';
	$admin = isset($_POST['offer']['admin']) ? $_POST['offer']['admin'] : '';
	$status = $usces->make_status( $taio, $receipt, $admin );
	if( $taio == 'completion' || $taio == 'continuation' ){
		if( 'update' == $_POST['up_modified'] ){
			$order_modified =  substr($gmtdate, 0, 10);
		}else{
			$order_modified =  $_POST['modified'];
		}
	}else{
		$order_modified = '';
	}
	$ordercheck = isset($_POST['check']) ? serialize($_POST['check']) : '';
	$member_id = isset($_POST['member_id']) ? $_POST['member_id'] : 0;

	if( 'cancel' == $taio ){
		$query = $wpdb->prepare(
				"UPDATE $order_table_name SET `order_modified` = %s, `order_status` = %s WHERE ID = %d", 
					$order_modified, $status, $ID
				);
		$res[0] = $wpdb->query( $query );

		$query = $wpdb->prepare("SELECT * FROM $order_table_name WHERE ID = %d", $ID);
		$new_orderdata = $wpdb->get_row( $query );

		if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] && !empty($member_id) && !$usces->is_status('cancel', $old_status) ) {
			$point = 0;
			if( 0 < $old_orderdata->order_getpoint ) {
				if( usces_is_complete_settlement( $old_orderdata->order_payment_name, $old_orderdata->order_status ) || $usces->is_status('receipted', $old_orderdata->order_status) ) {
					$point += $old_orderdata->order_getpoint;
				}
			}
			if( 0 < $old_orderdata->order_usedpoint ) {
				$point -= $old_orderdata->order_usedpoint;
			}
			if( 0 != $point ) usces_restore_point( $member_id, $point );
		}

		do_action('usces_action_update_orderdata', $new_orderdata, $old_status, $old_orderdata, $new_cart, $old_cart);

		return 1;
	}
	
	$old_deli = unserialize($old_orderdata->order_delivery);
	foreach($_POST['delivery'] as $dk => $dv ){
		$old_deli[$dk] = $dv;
	}
	$delivery = serialize($old_deli);

	$delivery_date = $_POST['offer']['delivery_date'];
	if( !usces_is_date( $delivery_date ) ) {
		$delivery_date = apply_filters( 'usces_filter_label_delivery_date_no_preference', __( 'Non-request', 'usces_dual' ) );
	}

	$query = $wpdb->prepare(
				"UPDATE $order_table_name SET 
					`mem_id`=%d, `order_email`=%s, `order_name1`=%s, `order_name2`=%s, `order_name3`=%s, `order_name4`=%s, 
					`order_zip`=%s, `order_pref`=%s, `order_address1`=%s, `order_address2`=%s, `order_address3`=%s, 
					`order_tel`=%s, `order_fax`=%s, `order_delivery`=%s, `order_note`=%s, 
					`order_delivery_method`=%d, `order_delivery_date`=%s, `order_delivery_time`=%s, `order_payment_name`=%s, `order_item_total_price`=%f, `order_getpoint`=%d, `order_usedpoint`=%d, 
					`order_discount`=%f, `order_shipping_charge`=%f, `order_cod_fee`=%f, `order_tax`=%f, `order_modified`=%s, 
					`order_status`=%s, `order_delidue_date`=%s, `order_check`=%s 
				WHERE ID = %d", 
					$member_id, 
					$_POST['customer']['mailaddress'], 
					$_POST['customer']['name1'], 
					$_POST['customer']['name2'], 
					(isset($_POST['customer']['name3']) ? $_POST['customer']['name3'] : ''), 
					(isset($_POST['customer']['name4']) ? $_POST['customer']['name4'] : ''), 
					$_POST['customer']['zipcode'], 
					$_POST['customer']['pref'], 
					$_POST['customer']['address1'], 
					$_POST['customer']['address2'], 
					$_POST['customer']['address3'], 
					$_POST['customer']['tel'], 
					$_POST['customer']['fax'], 
					$delivery, 
					$_POST['offer']['note'], 
					$_POST['offer']['delivery_method'], 
					$delivery_date, 
					$_POST['offer']['delivery_time'], 
					$_POST['offer']['payment_name'], 
					$item_total_price, 
					$_POST['offer']['getpoint'], 
					$_POST['offer']['usedpoint'], 
					$_POST['offer']['discount'], 
					$_POST['offer']['shipping_charge'], 
					$_POST['offer']['cod_fee'], 
					$_POST['offer']['tax'], 
					$order_modified, 
					$status,
					$_POST['offer']['delidue_date'], 
					$ordercheck,
					$ID
				);

	$res[0] = $wpdb->query( $query );
	if(false === $res[0]) {
		return false;
	}

	$usces->set_order_meta_value('customer_country', $_POST['customer']['country'], $ID);

	if( usces_is_tax_display() && usces_is_reduced_taxrate( $ID ) ) {
		$usces->del_order_meta( 'subtotal_standard', $ID );
		$usces->del_order_meta( 'subtotal_reduced', $ID );
		$usces->del_order_meta( 'discount_standard', $ID );
		$usces->del_order_meta( 'discount_reduced', $ID );
		$usces->del_order_meta( 'tax_standard', $ID );
		$usces->del_order_meta( 'tax_reduced', $ID );
		if( isset( $_POST['order_subtotal_standard'] ) && isset( $_POST['order_subtotal_reduced'] ) && isset( $_POST['order_discount_standard'] ) && isset( $_POST['order_discount_reduced'] ) && isset( $_POST['order_tax_standard'] ) && isset( $_POST['order_tax_reduced'] ) ) {
			$usces->set_order_meta_value( 'subtotal_standard', $_POST['order_subtotal_standard'], $ID );
			$usces->set_order_meta_value( 'subtotal_reduced', $_POST['order_subtotal_reduced'], $ID );
			$usces->set_order_meta_value( 'discount_standard', $_POST['order_discount_standard'], $ID );
			$usces->set_order_meta_value( 'discount_reduced', $_POST['order_discount_reduced'], $ID );
			if( 'include' == $usces->options['tax_mode'] ) {
				$usces->set_order_meta_value( 'tax_standard', 0, $ID );
				$usces->set_order_meta_value( 'tax_reduced', 0, $ID );
			} else {
				$usces->set_order_meta_value( 'tax_standard', $_POST['order_tax_standard'], $ID );
				$usces->set_order_meta_value( 'tax_reduced', $_POST['order_tax_reduced'], $ID );
			}
		} else {
			$usces_tax = Welcart_Tax::get_instance();
			$materials = array(
				'total_items_price' => $item_total_price,
				'discount' => $_POST['offer']['discount'],
				'shipping_charge' => $_POST['offer']['shipping_charge'],
				'cod_fee' => $_POST['offer']['cod_fee'],
				'use_point' => $_POST['offer']['usedpoint'],
				'carts' => $new_cart,
				'condition' => $old_orderdata->order_condition,
			);
			$usces_tax->get_order_tax( $materials );
			$usces->set_order_meta_value( 'subtotal_standard', $usces_tax->subtotal_standard, $ID );
			$usces->set_order_meta_value( 'subtotal_reduced', $usces_tax->subtotal_reduced, $ID );
			$usces->set_order_meta_value( 'discount_standard', $usces_tax->discount_standard, $ID );
			$usces->set_order_meta_value( 'discount_reduced', $usces_tax->discount_reduced, $ID );
			if( 'include' == $usces->options['tax_mode'] ) {
				$usces->set_order_meta_value( 'tax_standard', 0, $ID );
				$usces->set_order_meta_value( 'tax_reduced', 0, $ID );
			} else {
				$usces->set_order_meta_value( 'tax_standard', $usces_tax->tax_standard, $ID );
				$usces->set_order_meta_value( 'tax_reduced', $usces_tax->tax_reduced, $ID );
			}
		}
	}

	$csod_meta = usces_has_custom_field_meta( 'order' );
	if( is_array($csod_meta) ) {
		foreach( $csod_meta as $key => $entry ) {
			if( '4' == $entry['means'] ) {
				$usces->del_order_meta( 'csod_'.$key, $ID );
			}
		}
	}
	$cscs_meta = usces_has_custom_field_meta( 'customer' );
	if( is_array($cscs_meta) ) {
		foreach( $cscs_meta as $key => $entry ) {
			if( '4' == $entry['means'] ) {
				$usces->del_order_meta( 'cscs_'.$key, $ID );
			}
		}
	}
	$csde_meta = usces_has_custom_field_meta( 'delivery' );
	if( is_array($csde_meta) ) {
		foreach( $csde_meta as $key => $entry ) {
			if( '4' == $entry['means'] ) {
				$usces->del_order_meta( 'csde_'.$key, $ID );
			}
		}
	}
	$i = 1;
	if( !empty($_POST['custom_order']) ) {
		foreach( $_POST['custom_order'] as $key => $value ) {
			$csod_key = 'csod_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$res[$i] = $usces->set_order_meta_value($csod_key, $value, $ID);
			if(false === $res[$i]) 
				return false;
			$i++;
		}
	}
	if( !empty($_POST['custom_customer']) ) {
		foreach( $_POST['custom_customer'] as $key => $value ) {
			$cscs_key = 'cscs_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$res[$i] = $usces->set_order_meta_value($cscs_key, $value, $ID);
			if(false === $res[$i]) 
				return false;
			$i++;
		}
	}
	if( !empty($_POST['custom_delivery']) ) {
		foreach( $_POST['custom_delivery'] as $key => $value ) {
			$csde_key = 'csde_'.$key;
			if( is_array($value) ) 
				 $value = serialize($value);
			$res[$i] = $usces->set_order_meta_value($csde_key, $value, $ID);
			if(false === $res[$i]) 
				return false;
			$i++;
		}
	}

	if ( !preg_match('/pending|noreceipt/', $old_status) && preg_match('/pending|noreceipt/', $status) ) {
		$rquery = $wpdb->prepare("DELETE FROM $order_table_meta_name WHERE order_id = %d AND meta_key = %s", $ID, 'receipted_date');
		$wpdb->query( $rquery );
	}else if ( !preg_match('/pending|noreceipt/', $old_status) && !preg_match('/pending|noreceipt/', $status) ) {
		$query = $wpdb->prepare("SELECT order_id FROM $order_table_meta_name WHERE order_id = %d AND meta_key = %s", $ID, 'receipted_date');
		$varres = $wpdb->get_var( $query );
		if( empty($varres) ){
			$usces->set_order_meta_value('receipted_date', $gmtdate, $ID);
		}
	}else if ( preg_match('/pending|noreceipt/', $old_status) && !preg_match('/pending|noreceipt/', $status) ) {
		$usces->set_order_meta_value('receipted_date', $gmtdate, $ID);
	}

	if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] && !empty($member_id) ) {
		$point = 0;
		$getpoint = $_POST['offer']['getpoint'];
		$usedpoint = $_POST['offer']['usedpoint'];
		if( $usces->is_status('cancel', $old_status) && !$usces->is_status('cancel', $status) ) {//キャンセル→新規受付・取り寄せ中・発送済み
			if( 0 < $getpoint ) {
				if( usces_is_complete_settlement( $_POST['offer']['payment_name'], $status ) || $usces->is_status('receipted', $status) ) {
					$point -= $getpoint;
				}
			}
			if( 0 < $usedpoint ) {
				$point += $usedpoint;
			}
		} else {
			if( !usces_is_complete_settlement( $_POST['offer']['payment_name'] ) ) {
				if( !preg_match('/pending|noreceipt/', $old_status) && preg_match('/pending|noreceipt/', $status) ) {//入金→未入金
					$point += $getpoint;//ポイント取消
				} else if( preg_match('/pending|noreceipt/', $old_status) && !preg_match('/pending|noreceipt/', $status) ) {//未入金→入金
					$point -= $getpoint;//ポイント追加
				} else if( preg_match('/pending|noreceipt/', $old_status) && preg_match('/pending|noreceipt/', $status) ) {//未入金→未入金
					$point = 0;//ポイント追加
				} else {
					if( $old_orderdata->order_getpoint != $getpoint ) {
						$point += $old_orderdata->order_getpoint - $getpoint;
					}
				}
			} else {
				if( $old_orderdata->order_getpoint != $getpoint ) {
					$point += $old_orderdata->order_getpoint - $getpoint;
				}
			}
			if( $old_orderdata->order_usedpoint != $usedpoint ) {
				$point -= $old_orderdata->order_usedpoint - $usedpoint;
			}
		}
		if( 0 != $point ) usces_restore_point( $member_id, $point );
	}

	$result = ( 0 < array_sum($res) ) ? 1 : 0;

	$query = $wpdb->prepare("SELECT * FROM $order_table_name WHERE ID = %d", $ID);
	$new_orderdata = $wpdb->get_row( $query );
	do_action( 'usces_action_update_orderdata', $new_orderdata, $old_status, $old_orderdata, $new_cart, $old_cart );
	$usces->cart->crear_cart();
	
	return $result;
}



function usces_export_xml() {
	$options = get_option('usces');
	echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . ">\n";
?>
	<usces><?php echo serialize($options); ?></usces>
	<usces_management_status><?php echo serialize(get_option('usces_management_status')); ?></usces_management_status>
	<usces_zaiko_status><?php echo serialize(get_option('usces_zaiko_status')); ?></usces_zaiko_status>
	<usces_customer_status><?php echo serialize(get_option('usces_customer_status')); ?></usces_customer_status>
	<usces_payment_structure><?php echo serialize(get_option('usces_payment_structure')); ?></usces_payment_structure>
	<usces_display_mode><?php echo serialize(get_option('usces_display_mode')); ?></usces_display_mode>
<!--	<usces_pref><?php //echo serialize(get_option('usces_pref')); ?></usces_pref>-->
	<usces_shipping_rule><?php echo serialize(get_option('usces_shipping_rule')); ?></usces_shipping_rule>

<?php
}

function usces_all_change_zaiko(&$obj) {
	global $wpdb, $usces;

	$ids = $_POST['listcheck'];
	$status = true;
	foreach ( (array)$ids as $post_id ){
		$skus = $usces->get_skus($post_id);
		foreach ( (array)$skus as $sku ){
			$res = usces_update_sku( $post_id, $sku['code'], 'stock', (int)$_POST['change']['word']['zaiko'] );
			if( !$res ){
				$status = false;
			}
		}
	}
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
}

function usces_all_change_itemdisplay(&$obj){
	global $wpdb;

	$ids = $_POST['listcheck'];
	$post_status = $_POST['change']['word']['display_status'];
	$status = true;
	foreach ( (array)$ids as $post_id ):
		$post_data = array('ID'=>$post_id, 'post_status'=>$post_status);
		$res = wp_update_post( $post_data );

		if( $res == 0 ) {
			$status = false;
		}
	endforeach;
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
}

function usces_all_change_order_reciept(&$obj){
	global $wpdb, $usces;

	$tableName = $wpdb->prefix . "usces_order";
	$ids = $_POST['listcheck'];
	if( isset($_REQUEST['change']['word']) && !is_array($_REQUEST['change']['word']) ){
		$change_word = $_REQUEST['change']['word'];
	}elseif( isset($_REQUEST['change']['word']['order_reciept']) ){
		$change_word = $_REQUEST['change']['word']['order_reciept'];
	}else{
		$change_word = '';
	}
	$status = true;
	foreach ( (array)$ids as $id ):
		$query = $wpdb->prepare("SELECT order_status, mem_id, order_getpoint, order_payment_name FROM $tableName WHERE ID = %d", $id);
		$order_res = $wpdb->get_row( $query, ARRAY_A );
		$statusstr = $order_res['order_status'];
		$restore_point = false;
		$getpoint = $order_res['order_getpoint'];
		if( strpos($statusstr, 'cancel') !== false ) continue;
		if(strpos($statusstr, 'noreceipt') === false && strpos($statusstr, 'receipted') === false) continue;
		$old_status = $statusstr;
		if($change_word == 'receipted') { 
			if(strpos($statusstr, 'noreceipt') !== false) { //未入金→入金
				$statusstr = str_replace('noreceipt', 'receipted', $statusstr);
				if( !usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
					$restore_point = true;
					$getpoint = $getpoint * -1;//add point
				}
			}
		}elseif($change_word == 'noreceipt') {
			if(strpos($statusstr, 'receipted') !== false) {	//入金済み→未入金
				$statusstr = str_replace('receipted', 'noreceipt', $statusstr);
				if( !usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
					$restore_point = true;
				}
			}
		}
		$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
		$res = $wpdb->query( $query );
		if( $res === false ) {
			$status = false;
		}
		if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] ) {
			if( !empty($order_res['mem_id']) && 0 < $order_res['order_getpoint'] ) {
				if( $res && $restore_point ) usces_restore_point( $order_res['mem_id'], $getpoint );
			}
		}
		if( $status ) {
			do_action( 'usces_action_collective_order_reciept_each', $id, $statusstr, $old_status );
		}
	endforeach;
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_order_reciept', array(&$obj));
}

function usces_all_change_order_status(&$obj){
	global $wpdb, $usces;

	$tableName = $wpdb->prefix . "usces_order";
	$ids = $_POST['listcheck'];
	foreach ( (array)$ids as $id ):
		$status = true;
		$query = $wpdb->prepare("SELECT order_status, mem_id, order_getpoint, order_usedpoint, order_payment_name FROM $tableName WHERE ID = %d", $id);
		$order_res = $wpdb->get_row( $query, ARRAY_A );
		$statusstr = $order_res['order_status'];
		$restore_point = false;
		$getpoint = $order_res['order_getpoint'];
		$usedpoint = $order_res['order_usedpoint'];
		$old_status = $statusstr;
		if( isset($_REQUEST['change']['word']) && !is_array($_REQUEST['change']['word']) ){
			$change_word = $_REQUEST['change']['word'];
		}elseif( isset($_REQUEST['change']['word']['order_reciept']) ){
			$change_word = $_REQUEST['change']['word']['order_reciept'];
		}elseif( isset($_REQUEST['change']['word']['order_status']) ){
			$change_word = $_REQUEST['change']['word']['order_status'];
		}else{
			$change_word = '';
		}

		switch ($change_word) {
			case 'estimate':
				if(strpos($statusstr, 'adminorder') !== false) {
					$statusstr = str_replace('adminorder', 'estimate', $statusstr);
				}else if(strpos($statusstr, 'estimate') === false) {
					if( ',' !== substr( $statusstr, -1 ) ) $statusstr .= ',';
					$statusstr .= 'estimate,';
				}
				if( strpos($statusstr, 'cancel') === false ){
					if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
						$restore_point = true;
						$usedpoint = $usedpoint * -1;//add point
					} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
						$restore_point = true;
						$usedpoint = $usedpoint * -1;//add point
					}
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
			case 'adminorder':
				if( strpos($statusstr, 'estimate') !== false ) {
					$statusstr = str_replace('estimate', 'adminorder', $statusstr);
					if( strpos($statusstr, 'cancel') === false ){
						if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
							$restore_point = true;
							$usedpoint = $usedpoint * -1;//add point
						} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
							$restore_point = true;
							$usedpoint = $usedpoint * -1;//add point
						}
					}
				}else if(strpos($statusstr, 'adminorder') === false) {
					if( ',' !== substr( $statusstr, -1 ) ) $statusstr .= ',';
					$statusstr .= 'adminorder,';
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
			case 'frontorder':
				if(strpos($statusstr, 'estimate') !== false) {
					$statusstr = str_replace('estimate,', '', $statusstr);
					$statusstr = trim($statusstr, ',');
					if( strpos($statusstr, 'cancel') === false ){
						if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
							$restore_point = true;
							$usedpoint = $usedpoint * -1;//add point
						} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
							$restore_point = true;
							$usedpoint = $usedpoint * -1;//add point
						}
					}
				}else if(strpos($statusstr, 'adminorder') !== false) {
					$statusstr = str_replace('adminorder,', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
			case 'duringorder':
				if( strpos($statusstr, 'cancel') !== false ) {
					$statusstr = str_replace('cancel', '', $statusstr);
					$statusstr = trim($statusstr, ',');
					if( strpos($statusstr, 'estimate') === false ){
						if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						}
					}
				}
				if(strpos($statusstr, 'completion') !== false) {
					$statusstr = str_replace('completion', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'new') !== false) {
					$statusstr = str_replace('new', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'duringorder') === false) {
					if( ',' !== substr( $statusstr, -1 ) ) $statusstr .= ',';
					$statusstr .= 'duringorder,';
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
			case 'cancel':
				if(strpos($statusstr, 'completion') !== false) {
					$statusstr = str_replace('completion', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'new') !== false) {
					$statusstr = str_replace('new', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'duringorder') !== false) {
					$statusstr = str_replace('duringorder', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'cancel') === false) {
					if( ',' !== substr( $statusstr, -1 ) ) $statusstr .= ',';
					$statusstr .= 'cancel,';
				}
				if( strpos($statusstr, 'estimate') === false ){
					if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
						$restore_point = true;
						$usedpoint = $usedpoint * -1;//add point
					} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
						$restore_point = true;
						$usedpoint = $usedpoint * -1;//add point
					}
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
			case 'completion':
				if(strpos($statusstr, 'new') !== false) {
					$statusstr = str_replace('new', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'duringorder') !== false) {
					$statusstr = str_replace('duringorder', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'cancel') !== false) {
					$statusstr = str_replace('cancel', '', $statusstr);
					$statusstr = trim($statusstr, ',');
					if( strpos($statusstr, 'estimate') === false ){
						if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						}
					}
				}else if(strpos($statusstr, 'completion') === false) {
					if( ',' !== substr( $statusstr, -1 ) ) $statusstr .= ',';
					$statusstr .= 'completion,';
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s, order_modified = %s WHERE ID = %d", $statusstr, substr(current_time('mysql'), 0, 10), $id);
				break;
			case 'new':
			case 'neworder':
				if(strpos($statusstr, 'duringorder') !== false) {
					$statusstr = str_replace('duringorder,', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'completion') !== false) {
					$statusstr = str_replace('completion,', '', $statusstr);
					$statusstr = trim($statusstr, ',');
				}
				if(strpos($statusstr, 'cancel') !== false) {
					$statusstr = str_replace('cancel,', '', $statusstr);
					$statusstr = trim($statusstr, ',');
					if( strpos($statusstr, 'estimate') === false ){
						if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						} elseif( $usces->is_status('receipted', $order_res['order_status']) ) {
							$restore_point = true;
							$getpoint = $getpoint * -1;//add point
						}
					}
				}
				$query = $wpdb->prepare("UPDATE $tableName SET order_status = %s WHERE ID = %d", $statusstr, $id);
				break;
		}

		$res = $wpdb->query( $query );
		if( $res === false ) {
			$status = false;
		}
		if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] ) {
			if( !empty($order_res['mem_id']) && ( 0 < $order_res['order_getpoint'] || 0 < $order_res['order_usedpoint'] ) ) {
				if( $res && $restore_point ) usces_restore_point( $order_res['mem_id'], $getpoint + $usedpoint );
			}
		}
		if( $status ) {
			do_action('usces_action_collective_order_status_each', $id, $statusstr, $old_status);
		}
	endforeach;
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_order_status', array(&$obj));
}

function usces_all_delete_order_data(&$obj){
	global $wpdb, $usces;

	$tableName = $wpdb->prefix . "usces_order";
	$tableMetaName = $wpdb->prefix . "usces_order_meta";
	$ids = $_POST['listcheck'];
	$status = true;
	foreach ( (array)$ids as $id ):
		$del = usces_delete_order_check( $id );
		if( $del === false ) continue;

		$point = 0;
		$query = $wpdb->prepare("SELECT * FROM $tableName WHERE ID = %d", $id);
		$order_res = $wpdb->get_row( $query, ARRAY_A );
		if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] && !empty($order_res['mem_id']) && !$usces->is_status('cancel', $order_res['order_status']) ) {
			if( 0 < $order_res['order_getpoint'] ) {
                if( usces_is_complete_settlement( $order_res['order_payment_name'], $order_res['order_status'] ) || $usces->is_status('receipted', $order_res['order_status']) )
                    $point += $order_res['order_getpoint'];
			}
			if( 0 < $order_res['order_usedpoint'] ) {
				$point -= $order_res['order_usedpoint'];
			}
		}
		$query = $wpdb->prepare("DELETE FROM $tableName WHERE ID = %d", $id);
		$res = $wpdb->query( $query );
		if( $res === false ) {
			$status = false;
		}else{

			if( 0 != $point ) usces_restore_point( $order_res['mem_id'], $point );

			do_action('usces_action_collective_order_delete_each', $id, $order_res);
			
			usces_delete_ordercartdata( NULL, $id );
			
			$metaquery = $wpdb->prepare("DELETE FROM $tableMetaName WHERE order_id = %d", $id);
			$metares = $wpdb->query( $metaquery );
		}
	endforeach;
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_order_delete', array(&$obj));
}

function usces_all_change_member_rank(&$obj){
	global $wpdb, $usces;

	$tableName = usces_get_tablename( 'usces_member' );
	$ids = $_POST['listcheck'];
	$status = true;
	
	foreach ( (array)$ids as $id ){

		$value = isset($_REQUEST['change']['word']) ? $_REQUEST['change']['word'] : '';
		$query = $wpdb->prepare("UPDATE $tableName SET mem_status = %d WHERE ID = %d", $value, $id);
		$res = $wpdb->query( $query );
		if( $res === false ) {
			$status = false;
		}
		if( $status ) {
			do_action( 'usces_action_collective_member_rank_each', $id, $value );
		}
	}
	
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_member_rank', array(&$obj));
}

function usces_all_change_member_point(&$obj){
	global $wpdb, $usces;

	$tableName = usces_get_tablename( 'usces_member' );
	$ids = $_POST['listcheck'];
	$status = true;
	
	foreach ( (array)$ids as $id ){

		$value = isset($_REQUEST['change']['word']) ? $_REQUEST['change']['word'] : '';
		$query = $wpdb->prepare("UPDATE $tableName SET mem_point = %d WHERE ID = %d", $value, $id);
		$res = $wpdb->query( $query );
		if( $res === false ) {
			$status = false;
		}
		if( $status ) {
			do_action( 'usces_action_collective_member_point_each', $id, $value );
		}
	}
	
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_member_point', array(&$obj));
}

function usces_all_delete_member_data(&$obj){
	global $wpdb, $usces;

	$ids = $_POST['listcheck'];
	$status = true;
	
	foreach ( (array)$ids as $id ){
		$del = usces_delete_member_check( $id );

		if( $del === false )
			continue;

		$old_data = $usces->get_member_info( $id );
		$res = usces_delete_memberdata( $id );
		
		if( $res === false ) {
			$status = false;
			do_action('usces_action_collective_member_delete_each', $id, $old_data);
		}
	}
	
	if ( true === $status ) {
		$obj->set_action_status('success', __('I completed collective operation.','usces'));
	} elseif ( false === $status ) {
		$obj->set_action_status('error', __('ERROR: I was not able to complete collective operation','usces'));
	} else {
		$obj->set_action_status('none', '');
	}
	do_action('usces_action_collective_member_delete', array(&$obj));
}

function usces_check_acting_return() {
	global $usces;
	$entry = $usces->cart->get_entry();

	$acting = ( isset($_GET['acting']) ) ? $_GET['acting'] : '';
	$results = array();
	switch ( $acting ) {
		case 'epsilon':
			if(isset($_GET['duplicate']) && $_GET['duplicate'] == 1){
				$results[0] = 'duplicate';
			}else if(isset($_GET['result'])){
				$results[0] = (int)$_GET['result'];
				$results['reg_order'] = true;
			}else{
				$str = explode('?', $_GET['acting_return']);
				if(!isset($str[1])) {
					$results[0] = 0;
				}else{
				    parse_str($str[1], $para);
					$results[0] = isset($para['result']) ? (int)$para['result'] : 0;
					foreach($para as $key => $value){
						$results[$key] = $value;
					}
				}
			
			}
			$results['reg_order'] = true;
			break;
			
		case 'paypal':
			usces_log('paypal in ', 'acting_transaction.log');
			require_once($usces->options['settlement_path'] . "paypal.php");
			$results = paypal_check($usces_paypal_url);
			remove_action( 'wp_footer', array(&$usces, 'lastprocessing'));
			$results['reg_order'] = true;
			break;
			
		case 'remise_card':
			$results = $_POST;
			if( $_REQUEST['acting_return'] && ( isset($_REQUEST['X-ERRCODE']) && '   ' === $_REQUEST['X-ERRCODE'] ) ){
				usces_log('remise card entry data : '.print_r($entry, true), 'acting_transaction.log');
				$results[0] = 1;
			}else{
				$results[0] = 0;
			}
			if( isset($_REQUEST['dlseller_update']) ){
				$results['reg_order'] = false;
			}else{
				$results['reg_order'] = true;
			}
			break;
			
		case 'remise_conv':
			$results = $_GET;
			if( $_REQUEST['acting_return'] && isset($_REQUEST['X-JOB_ID']) && '0:0000' === $_REQUEST['X-R_CODE']){
				$results[0] = 1;
			}else{
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;
			
		case 'jpayment_card':
			$results = $_GET;
			if($_GET['rst'] == 2) {
				usces_log('jpayment card error : '.print_r($entry, true), 'acting_transaction.log');
				$log = array( 'acting'=>$acting, 'key'=>$_GET['cod'], 'result'=>$_GET['rst'], 'data'=>$_GET );
				usces_save_order_acting_error( $log );
			}
			$results[0] = ($_GET['rst'] == 1) ? 1 : 0;
			$results['reg_order'] = true;
			break;

		case 'jpayment_conv':
			$results = $_GET;
			if($_GET['rst'] == 2) {
				usces_log('jpayment conv error : '.print_r($entry, true), 'acting_transaction.log');
				$log = array( 'acting'=>$acting, 'key'=>$_GET['cod'], 'result'=>$_GET['rst'], 'data'=>$_GET );
				usces_save_order_acting_error( $log );
			}
			$results[0] = ($_GET['rst'] == 1 and $_GET['ap'] == 'CPL_PRE') ? 1 : 0;
			$results['reg_order'] = true;
			break;

		case 'jpayment_bank':
			$results = $_GET;
			if($_GET['rst'] == 2) {
				usces_log('jpayment bank error : '.print_r($entry, true), 'acting_transaction.log');
				$log = array( 'acting'=>$acting, 'key'=>$_GET['cod'], 'result'=>$_GET['rst'], 'data'=>$_GET );
				usces_save_order_acting_error( $log );
			}
			$results[0] = ($_GET['rst'] == 1) ? 1 : 0;
			$results['reg_order'] = true;
			break;

		case 'paypal_ec':
			$results = $_GET;

			//Build a second API request to PayPal, using the token as the ID to get the details on the payment authorization
		    $req_token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '0';
			$nvpstr = "&TOKEN=".urlencode($req_token);

			$usces->paypal->setMethod('GetExpressCheckoutDetails');
			$usces->paypal->setData($nvpstr);
			$res = $usces->paypal->doExpressCheckout();
			$resArray = $usces->paypal->getResponse();
			$ack = strtoupper($resArray["ACK"]);
			if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
				if( usces_have_continue_charge() ) {
					$results[0] = 1;
				} else {
					$amt = floor( $resArray["AMT"] * 100 );
					$total_full_price = usces_crform( $entry['order']['total_full_price'], false, false, 'return', false );
					$total_full_price = floor( $total_full_price * 100 );
					if( $amt != $total_full_price ) {
						usces_log('PayPal : AMT Error. AMT='.$resArray["AMT"].', total_full_price='.$entry['order']['total_full_price'], 'acting_transaction.log');
						$results[0] = 0;
					} else {
						$results[0] = 1;
					}
				}

			} else {
				//Display a user friendly Error on the page using any of the following error information returned by PayPal
				$ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
				$ErrorShortMsg = urldecode($resArray["L_SHORTMESSAGE0"]);
				$ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
				$ErrorSeverityCode = urldecode($resArray["L_SEVERITYCODE0"]);
				usces_log('PayPal : GetExpressCheckoutDetails API call failed. Error Code:['.$ErrorCode.'] Error Severity Code:['.$ErrorSeverityCode.'] Short Error Message:'.$ErrorShortMsg.' Detailed Error Message:'.$ErrorLongMsg, 'acting_transaction.log');
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;

		case 'telecom_card':
			$results = $_GET;
			if( $_REQUEST['result'] ){
				usces_log($acting.' entry data : '.print_r($entry, true), 'acting_transaction.log');
				$results[0] = 1;
			}else{
				usces_log($acting.' error : '.print_r($_REQUEST,true), 'acting_transaction.log');
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;

		case 'digitalcheck_card':
			$results = $_REQUEST;
			if( isset($_REQUEST['SID']) ) {
				$results[0] = 1;
			} else {
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;
		case 'digitalcheck_conv':
			$results = $_REQUEST;
			$results[0] = 1;
			$results['reg_order'] = false;
			break;

		case 'mizuho_card':
		case 'mizuho_conv':
			$results = $_GET;
			if( isset($_GET['rsltcd']) ) {
				if( '000' == substr($_GET['rsltcd'], 0, 3) ) {
					$results[0] = 1;
					$results['reg_order'] = true;
				} else {
					$results[0] = 0;
					$results['reg_order'] = false;
				}
			} else {
				$results[0] = 0;
				$results['reg_order'] = false;
			}
			break;

		case 'anotherlane_card':
			$results[0] = 1;
			$results['reg_order'] = false;
			break;

		case 'veritrans_card':
		case 'veritrans_conv':
			$results[0] = ( isset($_GET['result']) ) ? $_GET['result'] : 0;
			$results['reg_order'] = false;
			break;

		case 'paypal_wpp':
			$results[0] = 1;
			$results['reg_order'] = false;
			break;

		case 'paygent_card':
		case 'paygent_conv':
			$results[0] = $_REQUEST['acting_return'];
			$results['reg_order'] = false;
			break;

		default:
			do_action( 'usces_action_check_acting_return_default' );
			$results = $_REQUEST;
			if( isset($_REQUEST['result']) and true == $_REQUEST['result'] ) {
				usces_log($acting.' entry data : '.print_r($entry, true), 'acting_transaction.log');
				$results[0] = 1;
			}else{
				usces_log($acting.' error : '.print_r($_REQUEST, true), 'acting_transaction.log');
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			$results = apply_filters( 'usces_filter_check_acting_return_results', $results );
			break;
	}
	
	return $results;
}

function usces_check_acting_return_duplicate( $results = array() ) {
	global $wpdb;
	$acting = isset($_GET['acting']) ? $_GET['acting'] : '';

	switch($acting) {
	case 'epsilon':
		$trans_id = isset($_REQUEST['trans_code']) ? $_REQUEST['trans_code'] : '';
		$_REQUEST['absolute_trans_id'] = $_REQUEST['order_number'];
		break;
	case 'paypal':
		$trans_id = isset($results['txn_id']) ? $results['txn_id'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	case 'paypal_ipn':
		$trans_id = isset($_REQUEST['txn_id']) ? $_REQUEST['txn_id'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	case 'paypal_ec':
		$trans_id = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	case 'remise_card':
		$trans_id = isset($_REQUEST['X-TRANID']) ? $_REQUEST['X-TRANID'] : '';
		$_REQUEST['absolute_trans_id'] = $_REQUEST['X-S_TORIHIKI_NO'];
		break;
	case 'remise_conv':
		$trans_id = isset($_REQUEST['X-JOB_ID']) ? $_REQUEST['X-JOB_ID'] : '';
		$_REQUEST['absolute_trans_id'] = $_REQUEST['X-S_TORIHIKI_NO'];
		break;
	case 'jpayment_card':
	case 'jpayment_conv':
	case 'jpayment_bank':
		$trans_id = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	case 'telecom_card':
		$trans_id = isset($_REQUEST['option']) ? $_REQUEST['option'] : '';
		$_REQUEST['absolute_trans_id'] = isset($_REQUEST['option']) ? $_REQUEST['option'] : $trans_id;
		break;
	case 'digitalcheck_card':
	case 'digitalcheck_conv':
		$trans_id = isset($_REQUEST['SID']) ? $_REQUEST['SID'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	case 'mizuho_card':
	case 'mizuho_conv':
		$trans_id = isset($_REQUEST['stran']) ? $_REQUEST['stran'] : '';
		$_REQUEST['absolute_trans_id'] = $trans_id;
		break;
	default:
		$trans_id = apply_filters( 'usces_filter_check_acting_return_duplicate', '', $results );
		$_REQUEST['absolute_trans_id'] = $trans_id;
	}
	$order_id = usces_get_order_id_by_trans_id( $trans_id );
	$_REQUEST['remote_addr'] = $_SERVER['REMOTE_ADDR'];
	$_REQUEST['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

	return $order_id;
}

function usces_get_order_id_by_trans_id( $trans_id ){
	global $wpdb;
	
	if( empty($trans_id) )
		return false;
		
	$table_meta_name = $wpdb->prefix.'usces_order_meta';
	$query = $wpdb->prepare("SELECT order_id FROM $table_meta_name WHERE meta_key = %s AND meta_value = %s", 'trans_id', $trans_id);
	$order_id = $wpdb->get_var($query);
	return $order_id;
}

function usces_dates_interconv( $date_str ) {
	$base_struc = preg_split('[/.-]', 'd/m/Y' );
	$date_str_parts = preg_split('[/.-]', $date_str );
	$date_elements = array();

	$p_keys = array_keys( $base_struc );
	foreach( $p_keys as $p_key ) {
		if( !empty( $date_str_parts[$p_key] )) {
			$date_elements[$base_struc[$p_key]] = $date_str_parts[$p_key];
		} else {
			return false;
		}
	}
	$dummy_ts = mktime( 0,0,0, $date_elements['m'],$date_elements['d'],$date_elements['Y']);
	return date( 'Y-m-d', $dummy_ts );
}

function usces_register_action($handle, $type, $key, $value, $function){
	global $usces_action;
	$usces_action[$handle] = array('type'=>$type, 'key'=>$key, 'value'=>$value, 'function'=>$function);
}

function usces_deregister_action($handle){
	global $usces_action;
	unset($usces_action[$handle]);
}

function usces_get_wcexp($post_id, $name=''){
	$wcexp = get_post_meta($post_id, '_wcexp', true);
	if( empty($name) ){
		$value = ( $wcexp !== false ) ? $wcexp : '';
	}else{
		$value = ($wcexp !== false && isset($wcexp[$name])) ? $wcexp[$name] : '';
	}
	return $value;
}

function usces_update_check_admin($result){
	$result = true;
	return $result;
}

function usces_setup_cod_ajax(){
	global $usces;
	$usces->options = get_option('usces');
	$message = '';
	$_POST = $usces->stripslashes_deep_post($_POST);
	
	$usces->options['cod_type'] = isset($_POST['cod_type']) ? $_POST['cod_type'] : 'fix';
	if( isset($_POST['cod_fee']) )
		$usces->options['cod_fee'] = (int)$_POST['cod_fee'];
			
	if( 'fix' == $usces->options['cod_type'] ){
		if( isset($_POST['cod_limit_amount']) ){
			$usces->options['cod_limit_amount'] = (int)$_POST['cod_limit_amount'];
			if( !WCUtils::is_blank($_POST['cod_limit_amount']) && 0 === (int)$_POST['cod_limit_amount'] )
				$message = __('There is the item where a value is dirty.', 'usces');
		}
		
	}elseif( 'change' == $usces->options['cod_type'] ){
		if( isset($_POST['cod_first_amount']) ){
			$usces->options['cod_first_amount'] = (int)$_POST['cod_first_amount'];
			if( 0 === (int)$_POST['cod_first_amount'] )
				$message = __('There is the item where a value is dirty.', 'usces');
		}
		if( isset($_POST['cod_limit_amount']) ){
			$usces->options['cod_limit_amount'] = (int)$_POST['cod_limit_amount'];
			if( !WCUtils::is_blank($_POST['cod_limit_amount']) && 0 === (int)$_POST['cod_limit_amount'] )
				$message = __('There is the item where a value is dirty.', 'usces');
		}
		if( isset($_POST['cod_first_fee']) ){
			$usces->options['cod_first_fee'] = (int)$_POST['cod_first_fee'];
			if( 0 === (int)$_POST['cod_first_fee'] && '0' !== $_POST['cod_first_fee'])
				$message = __('There is the item where a value is dirty.', 'usces');
		}
		if( isset($_POST['cod_end_fee']) ){
			$usces->options['cod_end_fee'] = (int)$_POST['cod_end_fee'];
			if( 0 === (int)$_POST['cod_end_fee'] && '0' !== $_POST['cod_end_fee'] )
				$message = __('There is the item where a value is dirty.', 'usces');
		}
		
		unset($usces->options['cod_amounts'], $usces->options['cod_fees']);
		if( isset($_POST['cod_amounts']) ){
			$cod_amounts_count = ( is_array( $_POST['cod_amounts'] ) ) ? count( $_POST['cod_amounts'] ) : 0;
			for($i=0; $i<$cod_amounts_count; $i++){
				$usces->options['cod_amounts'][$i] = (int)$_POST['cod_amounts'][$i];
				$usces->options['cod_fees'][$i] = (int)$_POST['cod_fees'][$i];
				if( 0 === (int)$_POST['cod_amounts'][$i] || (0 === (int)$_POST['cod_fees'][$i] && '0' !== $_POST['cod_fees'][$i]) )
					$message = __('There is the item where a value is dirty.', 'usces');
			}
		}
	}

	
	if( '' == $message ){
		$r = 'success';
		update_option('usces', $usces->options);
	}else{
		$r = '<span style="color:red;">' . $message . '</span>';
	}
	die( $r );
}

function usces_get_order_acting_data($rand){
	global $wpdb;

	$table_name = $wpdb->prefix . "usces_access";
	$query = $wpdb->prepare("SELECT acc_str1 AS `sesid`, acc_value AS `order_data` FROM $table_name WHERE acc_key = %s", $rand);
	$res = $wpdb->get_row($query, ARRAY_A);
	if($res == NULL){
		return false;
	}else{
		return $res;
	}
}

function usces_ordered_acting_data( $rand, $key = 'order_received' ) {
	global $usces, $wpdb;
	$datas = usces_get_order_acting_data($rand);
	$data = unserialize($datas['order_data']);
	usces_log('usces_ordered_acting_data', 'acting_transaction.log');
	$data[$key] = 1;

	$data = serialize($data);
	$table_name = $wpdb->prefix . "usces_access";
	$query = $wpdb->prepare("UPDATE $table_name SET acc_value = %s WHERE acc_type = %s AND acc_key = %s", 
							$data, 
							'acting_data', 
							$rand
							);
	$res = $wpdb->query($query);
}

function usces_is_trusted_acting_data($rand){
	global $usces, $wpdb;
	$datas = usces_get_order_acting_data($rand);
	$data = unserialize($datas['order_data']);
	usces_log('usces_is_trusted_acting_data', 'acting_transaction.log');
	if( isset($data['propriety']) && $data['propriety'] && !isset($data['order_received']) )
		return true;
	else
		return false;
}

function usces_get_filename( $path ){
	$res = array();
	if ( $handle = opendir($path) ) {
		while (false !== ($file = readdir($handle))) {
			if( '.' != $file && '..' != $file )
				$res[] = $file;
		}
		closedir($handle);
	}
	return $res;	
}

function usces_locales(){
	$res = array();
	if ( $handle = opendir(USCES_PLUGIN_DIR.'/languages/') ) {
		while (false !== ($file = readdir($handle))) {
			if( '.' != $file && '..' != $file && preg_match('/^usces-(.+)\.mo$/', $file, $matches) ){
				$res[] = $matches[1];
			}
		}
		closedir($handle);
	}
	return $res;	
}

function usces_get_local_language(){
	$locale = get_locale();
	switch( $locale ){
		case '':
		case 'en':
		case 'en_US':
			$front_lang =  'en';
			break;
		case 'ja':
		case 'ja_JP':
			$front_lang =  'ja';
			break;
		default:
			$front_lang =  'others';
	}
	return $front_lang;
}

function usces_get_base_country(){
	global $usces_settings;
	$wplang = defined('WPLANG') ? WPLANG : get_locale();
	$locale = empty( $wplang ) ? 'en' : $wplang;
	$base_country = ( array_key_exists($locale, $usces_settings['lungage2country']) ) ? $usces_settings['lungage2country'][$locale] : 'US';
	$base_country = apply_filters( 'usces_filter_base_country', $base_country );
	return $base_country;
}

function usces_get_local_addressform(){
	global $usces_settings;
	$base = usces_get_base_country();
	$addressform = ( array_key_exists($base, $usces_settings['addressform']) ) ? $usces_settings['addressform'][$base] : 'US';
	$addressform = apply_filters( 'usces_filter_addressform', $addressform );
	return $addressform;
}

function usces_get_local_target_market(){
	$base = usces_get_base_country();
	return (array)$base;
}

function usces_get_apply_addressform($country){
	global $usces_settings;
	return $usces_settings['addressform'][$country];
}

function usces_remove_filter(){
	global $usces, $post;

	if( is_single() && 'item' == $post->post_mime_type ) {
		remove_filter('the_content', array(&$usces, 'filter_itemPage'));

	}else if( $usces->is_cart_page($_SERVER['REQUEST_URI']) || $usces->is_inquiry_page($_SERVER['REQUEST_URI']) ){
		remove_action('the_post', array(&$usces, 'action_cartFilter'));
		remove_filter('the_title', array(&$usces, 'filter_cartTitle'),20);
		remove_filter('the_content', array(&$usces, 'filter_cartContent'),20);
		
	}else if( $usces->is_member_page($_SERVER['REQUEST_URI']) ){
		remove_action('the_post', array(&$usces, 'action_memberFilter'));
		remove_filter('the_title', array(&$usces, 'filter_memberTitle'),20);
		remove_filter('the_content', array(&$usces, 'filter_memberContent'),20);
	
	}else{
		remove_action('the_post', array(&$usces, 'goDefaultPage'));
	}
}

function usces_reset_filter(){
	global $usces, $post;

	if( is_single() && 'item' == $post->post_mime_type ) {
		add_filter('the_content', array(&$usces, 'filter_itemPage'));
		
	}else if( $usces->is_cart_page($_SERVER['REQUEST_URI']) || $usces->is_inquiry_page($_SERVER['REQUEST_URI']) ){
		add_action('the_post', array(&$usces, 'action_cartFilter'));
		add_filter('the_title', array(&$usces, 'filter_cartTitle'),20);
		add_filter('the_content', array(&$usces, 'filter_cartContent'),20);
		
	}else if( $usces->is_member_page($_SERVER['REQUEST_URI']) ){
		add_action('the_post', array(&$usces, 'action_memberFilter'));
		add_filter('the_title', array(&$usces, 'filter_memberTitle'),20);
		add_filter('the_content', array(&$usces, 'filter_memberContent'),20);
	
	}else{
		add_action('the_post', array(&$usces, 'goDefaultPage'));
	}
}


function usces_get_wcex(){
	$wcex = array();
	if( defined('WCEX_DLSELLER_VERSION'))
		$wcex['DLSELLER'] = array('name'=>'Dl Seller', 'version'=>WCEX_DLSELLER_VERSION);
	if( defined('WCEX_ITEM_LIST_LAYOUT_VERSION'))
		$wcex['ITEM_LIST_LAYOUT'] = array('name'=>'Item List Layout', 'version'=>WCEX_ITEM_LIST_LAYOUT_VERSION);
	if( defined('WCEX_MOBILE_VERSION'))
		$wcex['MOBILE'] = array('name'=>'Mobile', 'version'=>WCEX_MOBILE_VERSION);
	if( defined('WCEX_MULTIPRICE_VERSION'))
		$wcex['MULTIPRICE'] = array('name'=>'Multi Price', 'version'=>WCEX_MULTIPRICE_VERSION);
	if( defined('WCEX_SLIDE_SHOWCASE_VERSION'))
		$wcex['SLIDE_SHOWCASE'] = array('name'=>'Slide Showcase', 'version'=>WCEX_SLIDE_SHOWCASE_VERSION);
	if( defined('WCEX_WIDGET_CART_VERSION'))
		$wcex['WIDGET_CART'] = array('name'=>'Widget Cart', 'version'=>WCEX_WIDGET_CART_VERSION);
		
	return $wcex;
}

function usces_trackPageview_cart($push){
	$push = array();
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_cart'";
	}else{
		$push[] = "'_trackPageview','/wc_cart'";
	}
	return $push;
}

function usces_trackPageview_customer($push){
	$push = array();
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_customer'";
	}else{
		$push[] = "'_trackPageview','/wc_customer'";
	}
	return $push;
}

function usces_trackPageview_delivery($push){
	$push = array();
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_delivery'";
	}else{
		$push[] = "'_trackPageview','/wc_delivery'";
	}
	return $push;
}

function usces_trackPageview_confirm($push){
	$push = array();
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_confirm'";
	}else{
		$push[] = "'_trackPageview','/wc_confirm'";
	}
	return $push;
}

function usces_trackPageview_ordercompletion($push){
	global $usces;

	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_ordercompletion'";
	}else{
		$push[] = "'_trackPageview','/wc_ordercompletion'";
	}

	$sesdata = $usces->cart->get_entry();
	if( isset($sesdata['order']['ID']) && !empty($sesdata['order']['ID']) ){
		$order_id = $sesdata['order']['ID'];
		$data = $usces->get_order_data($order_id, 'direct');
		$cart = unserialize($data['order_cart']);
		$total_price = $usces->get_total_price( $cart ) + $data['order_discount'] - $data['order_usedpoint'];
		if( $total_price < 0 ) $total_price = 0;
		$push[] = "'_addTrans', '" . $order_id . "', '" . get_option('blogname') . "', '" . $total_price . "', '" . $data['order_tax'] . "', '" . $data['order_shipping_charge'] . "', '" . $data['order_address1'].$data['order_address2'] . "', '" . $data['order_pref'] . "', '" . get_locale() . "'";
		$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
		for($i=0; $i<$cart_count; $i++) { 
			$cart_row = $cart[$i];
			$post_id = $cart_row['post_id'];
			$sku = urldecode($cart_row['sku']);
			$quantity = $cart_row['quantity'];
			$itemName = $usces->getItemName($post_id);
			$skuPrice = $cart_row['price'];
			$cats = $usces->get_item_cat_genre_ids( $post_id );
			if( is_array($cats) )
				sort($cats);
			$category = ( isset($cats[0]) ) ? get_cat_name($cats[0]): '';
			$push[] = "'_addItem', '" . $order_id . "', '" . $sku . "', '" . $itemName . "', '" . $category . "', '" . $skuPrice . "', '" . $quantity . "'";
		}
		$push[] = "'_trackTrans'";
	}

	return $push;
}

function usces_trackPageview_error($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_error'";
	}else{
		$push[] = "'_trackPageview','/wc_error'";
	}
	return $push;
}

function usces_trackPageview_member($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_member'";
	}else{
		$push[] = "'_trackPageview','/wc_member'";
	}
	return $push;
}

function usces_trackPageview_login($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_login'";
	}else{
		$push[] = "'_trackPageview','/wc_login'";
	}
	return $push;
}

function usces_trackPageview_editmemberform($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_editmemberform'";
	}else{
		$push[] = "'_trackPageview','/wc_editmemberform'";
	}
	return $push;
}

function usces_trackPageview_newcompletion($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_newcompletion'";
	}else{
		$push[] = "'_trackPageview','/wc_newcompletion'";
	}
	return $push;
}

function usces_trackPageview_newmemberform($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_newmemberform'";
	}else{
		$push[] = "'_trackPageview','/wc_newmemberform'";
	}
	return $push;
}

function usces_trackPageview_deletemember($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_deletemember'";
	}else{
		$push[] = "'_trackPageview','/wc_deletemember'";
	}
	return $push;
}

function usces_trackPageview_search_item($push){
	if(defined('USCES_KEY') && defined('USCES_MULTI') && true == USCES_MULTI){
		$push[] = "'_trackPageview','/" . USCES_KEY . "wc_search_item'";
	}else{
		$push[] = "'_trackPageview','/wc_search_item'";
	}
	return $push;
}

function usces_get_essential_mark( $fielde, $data = NULL ){
	global $usces_essential_mark;
	do_action('usces_action_essential_mark', $data, $fielde);
	return $usces_essential_mark[$fielde];
}

function uesces_get_admin_addressform( $type, $data, $customdata, $out = 'return' ){
	global $usces, $usces_settings;
	$options = get_option('usces');
	$applyform = usces_get_apply_addressform($options['system']['addressform']);
	$formtag = '';
	switch( $type ){
	case 'member':
		$values = array('name1'=>$data['mem_name1'], 'name2'=>$data['mem_name2'], 'name3'=>$data['mem_name3'], 'name4'=>$data['mem_name4'], 'zipcode'=>$data['mem_zip'], 'address1'=>$data['mem_address1'], 'address2'=>$data['mem_address2'], 'address3'=>$data['mem_address3'], 'tel'=>$data['mem_tel'], 'fax'=>$data['mem_fax'], 'pref'=>$data['mem_pref']);
		$country = $usces->get_member_meta_value('customer_country', $data['ID']);
		$values['country'] = !empty($country) ? $country : usces_get_local_addressform();
		break;
	case 'customer':
		$values = array('name1'=>$data['order_name1'], 'name2'=>$data['order_name2'], 'name3'=>$data['order_name3'], 'name4'=>$data['order_name4'], 'zipcode'=>$data['order_zip'], 'address1'=>$data['order_address1'], 'address2'=>$data['order_address2'], 'address3'=>$data['order_address3'], 'tel'=>$data['order_tel'], 'fax'=>$data['order_fax'], 'pref'=>$data['order_pref']);
		$country = $usces->get_order_meta_value('customer_country', $data['ID']);
		$values['country'] = !empty($country) ? $country : usces_get_local_addressform();
		break;
	case 'delivery':
		$values = $data;
		break;
	}
	
	switch ($applyform){
	
	case 'JP': 
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_pre', 'return');
		$formtag .= '
		<tr id="' . $type . '_name_row">
			<td class="label">' . __('name', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[name1]" type="text" class="text short" value="' . esc_attr($values['name1']) . '" /><input name="' . $type . '[name2]" type="text" class="text short" value="' . esc_attr($values['name2']) . '" /></td>
		</tr>
		<tr id="' . $type . 'furikana_row">
			<td class="label">' . __('furigana', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[name3]" type="text" class="text short" value="' . esc_attr($values['name3']) . '" /><input name="' . $type . '[name4]" type="text" class="text short" value="' . esc_attr($values['name4']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_after', 'return');
		$formtag .= '
		<tr id="' . $type . 'zipcode_row">
			<td class="label">' . __('Zip/Postal Code', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[zipcode]" type="text" class="text short" value="' . esc_attr($values['zipcode']) . '" />'.apply_filters('usces_filter_admin_addressform_zipcode', NULL, $type).'</td>
		</tr>
		<tr id="' . $type . '_country_row">
			<td class="label">' . __('Country', 'usces') . '</td>
			<td class="col2 datafield">' . uesces_get_target_market_form( $type, $values['country'] ) . '</td>
		</tr>
		<tr id="' . $type . '_states_row">
			<td class="label">' . __('Province', 'usces') . '</td>
			<td class="col2 datafield">';
			
		$formtag .= usces_pref_select( $type, $values );
			
		$formtag .= '</td>
		</tr>';
		$formtag .= '
		<tr id="' . $type . '_address1_row">
			<td class="label">' . __('city', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address1]" type="text" class="text long" value="' . esc_attr($values['address1']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address2_row">
			<td class="label">' . __('numbers', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address2]" type="text" class="text long" value="' . esc_attr($values['address2']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address3_row">
			<td class="label">' . __('building name', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address3]" type="text" class="text long" value="' . esc_attr($values['address3']) . '" /></td>
		</tr>
		<tr id="' . $type . '_tel_row">
			<td class="label">' . __('Phone number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[tel]" type="text" class="text long" value="' . esc_attr($values['tel']) . '" /></td>
		</tr>
		<tr id="' . $type . '_fax_row">
			<td class="label">' . __('FAX number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[fax]" type="text" class="text long" value="' . esc_attr($values['fax']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'fax_after', 'return');
		break;
			
	case 'CN':
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_pre', 'return');
		$formtag .= '
		<tr>
			<td id="' . $type . 'name_row" class="label">' . __('name', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[name1]" type="text" class="text short" value="' . esc_attr($values['name1']) . '" /><input name="' . $type . '[name2]" type="text" class="text short" value="' . esc_attr($values['name2']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_after', 'return');
		$formtag .= '
		<tr id="' . $type . '_country_row">
			<td class="label">' . __('Country', 'usces') . '</td>
			<td class="col2 datafield">' . uesces_get_target_market_form( $type, $values['country'] ) . '</td>
		</tr>
		<tr id="' . $type . '_states_row">
			<td class="label">' . __('State', 'usces') . '</td>
			<td class="col2 datafield">';
			
		$formtag .= usces_pref_select( $type, $values );
			
		$formtag .= '</td>
		</tr id="' . $type . '_address1_row">';
		$formtag .= '<tr>
			<td class="label">' . __('city', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address1]" type="text" class="text long" value="' . esc_attr($values['address1']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address2_row">
			<td class="label">' . __('Address Line1', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address2]" type="text" class="text long" value="' . esc_attr($values['address2']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address3_row">
			<td class="label">' . __('Address Line2', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address3]" type="text" class="text long" value="' . esc_attr($values['address3']) . '" /></td>
		</tr>
		<tr id="' . $type . 'zipcode_row">
			<td class="label">' . __('Zip', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[zipcode]" type="text" class="text short" value="' . esc_attr($values['zipcode']) . '" /></td>
		</tr>
		<tr id="' . $type . '_tel_row">
			<td class="label">' . __('Phone number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[tel]" type="text" class="text long" value="' . esc_attr($values['tel']) . '" /></td>
		</tr>
		<tr id="' . $type . '_fax_row">
			<td class="label">' . __('FAX number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[fax]" type="text" class="text long" value="' . esc_attr($values['fax']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'fax_after', 'return');
		break;
		
	case 'US':
	default:
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_pre', 'return');
		$formtag .= '
		<tr id="' . $type . 'name_row">
			<td class="label">' . __('name', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[name2]" type="text" class="text short" value="' . esc_attr($values['name2']) . '" /><input name="' . $type . '[name1]" type="text" class="text short" value="' . esc_attr($values['name1']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'name_after', 'return');
		$formtag .= '
		<tr id="' . $type . '_address2_row">
			<td class="label">' . __('Address Line1', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address2]" type="text" class="text long" value="' . esc_attr($values['address2']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address3_row">
			<td class="label">' . __('Address Line2', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address3]" type="text" class="text long" value="' . esc_attr($values['address3']) . '" /></td>
		</tr>
		<tr id="' . $type . '_address1_row">
			<td class="label">' . __('city', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[address1]" type="text" class="text long" value="' . esc_attr($values['address1']) . '" /></td>
		</tr>
		<tr id="' . $type . '_states_row">
			<td class="label">' . __('State', 'usces') . '</td>
			<td class="col2 datafield">';
			
		$formtag .= usces_pref_select( $type, $values );
			
		$formtag .= '</td>
		</tr>';
		$formtag .= '
		<tr id="' . $type . '_country_row">
			<td class="label">' . __('Country', 'usces') . '</td>
			<td class="col2 datafield">' . uesces_get_target_market_form( $type, $values['country'] ) . '</td>
		</tr>
		<tr id="' . $type . 'zipcode_row">
			<td class="label">' . __('Zip', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[zipcode]" type="text" class="text short" value="' . esc_attr($values['zipcode']) . '" /></td>
		</tr>
		<tr id="' . $type . '_tel_row">
			<td class="label">' . __('Phone number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[tel]" type="text" class="text long" value="' . esc_attr($values['tel']) . '" /></td>
		</tr>
		<tr id="' . $type . '_fax_row">
			<td class="label">' . __('FAX number', 'usces') . '</td>
			<td class="col2 datafield"><input name="' . $type . '[fax]" type="text" class="text long" value="' . esc_attr($values['fax']) . '" /></td>
		</tr>';
		$formtag .= usces_admin_custom_field_input($customdata, $type, 'fax_after', 'return');
		break;
	}
	$res = apply_filters('usces_filter_apply_admin_addressform', $formtag, $type, $data, $customdata);
	

	if($out == 'return') {
		return $res;
	} else {
		echo $res;
	}
}

function uesces_get_mail_addressform( $type, $data, $order_id, $out = 'return' ){
	global $wpdb, $usces, $usces_settings;
	$options = get_option('usces');
	$applyform = usces_get_apply_addressform($options['system']['addressform']);

	$formtag = '';
	switch( $type ){
	case 'admin_mail_customer':
		$values = $data;
		$values['country'] = !empty($values['country']) ? $values['country'] : usces_get_local_addressform();
		$mode = 'customer';
		$name_label = __('Buyer','usces');
		break;
	case 'admin_mail':
		$values = $data;
		$values['country'] = !empty($values['country']) ? $values['country'] : usces_get_local_addressform();
		$mode = 'delivery';
		$name_label = __('A destination name','usces');
		break;
	case 'order_mail_customer':
		$values = $data['customer'];
		$values['country'] = !empty($values['country']) ? $values['country'] : usces_get_local_addressform();
		$mode = 'customer';
		$name_label = __('Buyer','usces');
		break;
	case 'order_mail':
		$values = $data['delivery'];
		$values['country'] = !empty($values['country']) ? $values['country'] : usces_get_local_addressform();
		$mode = 'delivery';
		$name_label = __('A destination name','usces');
		break;
	}
	$pref = ( $values['pref'] == __('-- Select --','usces') || $values['pref'] == '-- Select --' ) ? '' : $values['pref'];
	$target_market_count = ( isset( $options['system']['target_market'] ) && is_array( $options['system']['target_market'] ) ) ? count( $options['system']['target_market'] ) : 1;

	switch ($applyform){
	case 'JP': 
		$formtag .= usces_mail_custom_field_info( $mode, 'name_pre', $order_id );
		if( $type == 'order_mail_customer' or $type == 'admin_mail_customer' ){
			$usces_order_table = $wpdb->prefix . "usces_order";
			$order_data = $wpdb->get_results( $wpdb->prepare("SELECT mem_id,order_email FROM $usces_order_table WHERE ID = %d LIMIT 1", $order_id ) );	
			$mem_id = $order_data[0]->mem_id;
			$order_email = $order_data[0]->order_email;

			$formtag .= ( !empty( $mem_id ) ) ? __( 'membership number', 'usces' ) . " : " . $mem_id . "\r\n" : '';
			$formtag .= ( !empty( $order_email ) ) ? __( 'e-mail adress', 'usces' ) . " : " . $order_email . "\r\n" : '';
		}
		$formtag .= $name_label . " : " . sprintf(_x('%s', 'honorific', 'usces'), ($values['name1'] . ' ' . $values['name2'])) . "\r\n";
		if( !empty($values['name3']) || !empty($values['name4']) ) {
			$formtag .= __('furigana','usces') . " : " . $values['name3'] . ' ' . $values['name4'] . "\r\n";
		}
		$formtag .= usces_mail_custom_field_info( $mode, 'name_after', $order_id );
		if( 1 < $target_market_count ){
			$formtag .= __('Country','usces') . " : " . $usces_settings['country'][$values['country']] . "\r\n";
		}
		$formtag .= __('Zip/Postal Code','usces') . " : " . $values['zipcode'] . "\r\n";
		$formtag .= __('Address','usces') . " : " . $pref . $values['address1'] . $values['address2'] . " " . $values['address3'] . "\r\n";
		$formtag .= __('Phone number','usces') . " : " . $values['tel'] . "\r\n";
		$formtag .= __('FAX number','usces') . " : " . $values['fax'] . "\r\n";
		$formtag .= usces_mail_custom_field_info( $mode, 'fax_after', $order_id );
		break;
		
	case 'CN':
		$formtag .= usces_mail_custom_field_info( $mode, 'name_pre', $order_id );
		if( $type == 'order_mail_customer' or $type == 'admin_mail_customer' ){
			$usces_order_table = $wpdb->prefix . "usces_order";
			$order_data = $wpdb->get_results( $wpdb->prepare("SELECT mem_id,order_email FROM $usces_order_table WHERE ID = %d LIMIT 1", $order_id ) );
			$mem_id = $order_data[0]->mem_id;
			$order_email = $order_data[0]->order_email;

			$formtag .= ( !empty( $mem_id ) ) ? __( 'membership number', 'usces' ) . " : " . $mem_id . "\r\n" : '';
			$formtag .= ( !empty( $order_email ) ) ? __( 'e-mail adress', 'usces' ) . " : " . $order_email . "\r\n" : '';
		}
		$formtag .= $name_label . " : " . sprintf(_x('%s', 'honorific', 'usces'), ($values['name1'] . ' ' . $values['name2'])) . "\r\n";
		$formtag .= usces_mail_custom_field_info( $mode, 'name_after', $order_id );
		if( 1 < $target_market_count ){
			$formtag .= __('Country','usces') . " : " . $usces_settings['country'][$values['country']] . "\r\n";
		}
		$formtag .= __('State','usces') . " : " . $pref . "\r\n";
		$formtag .= __('City','usces') . " : " . $values['address1'] . "\r\n";
		$formtag .= __('Address','usces') . " : " . $values['address2'] . " " . $values['address3'] . "\r\n";
		$formtag .= __('Zip/Postal Code','usces') . " : " . $values['zipcode'] . "\r\n";
		$formtag .= __('Phone number','usces') . " : " . $values['tel'] . "\r\n";
		$formtag .= __('FAX number','usces') . " : " . $values['fax'] . "\r\n";
		$formtag .= usces_mail_custom_field_info( $mode, 'fax_after', $order_id );
		break;
		
	case 'US':
	default:
		$formtag .= usces_mail_custom_field_info( $mode, 'name_pre', $order_id );
		if( $type == 'order_mail_customer' or $type == 'admin_mail_customer' ){
			$usces_order_table = $wpdb->prefix . "usces_order";
			$order_data = $wpdb->get_results( $wpdb->prepare("SELECT mem_id,order_email FROM $usces_order_table WHERE ID = %d LIMIT 1", $order_id ) );
			$mem_id = $order_data[0]->mem_id;
			$order_email = $order_data[0]->order_email;

			$formtag .= ( !empty( $mem_id ) ) ? __( 'membership number', 'usces' ) . " : " . $mem_id . "\r\n" : '';
			$formtag .= ( !empty( $order_email ) ) ? __( 'e-mail adress', 'usces' ) . " : " . $order_email . "\r\n" : '';
		}
		$formtag .= $name_label . " : " . sprintf(_x('%s', 'honorific', 'usces'), ($values['name2'] . ' ' . $values['name1'])) . "\r\n";
		$formtag .= usces_mail_custom_field_info( $mode, 'name_after', $order_id );
		$formtag .= __('Address','usces') . " : " . $values['address2'] . " " . $values['address3'] . "\r\n";
		$formtag .= __('City','usces') . " : " . $values['address1'] . "\r\n";
		$formtag .= __('State','usces') . " : " . $pref . "\r\n";
		if( 1 < $target_market_count ){
			$formtag .= __('Country','usces') . " : " . $usces_settings['country'][$values['country']] . "\r\n";
		}
		$formtag .= __('Zip/Postal Code','usces') . " : " . $values['zipcode'] . "\r\n";
		$formtag .= __('Phone number','usces') . " : " . $values['tel'] . "\r\n";
		$formtag .= __('FAX number','usces') . " : " . $values['fax'] . "\r\n";
		$formtag .= usces_mail_custom_field_info( $mode, 'fax_after', $order_id );
		break;
	}
	$res = apply_filters('usces_filter_apply_mail_addressform', $formtag, $type, $data, $order_id);
	

	if($out == 'return') {
		return $res;
	} else {
		echo $res;
	}
}

function uesces_get_target_market_form( $type, $selected, $out = 'return' ){
	global $usces_settings;
	$options = get_option('usces');
	$res = '<select name="' . $type . '[country]" id="' . $type . '_country">'."\n";
	foreach ( $usces_settings['country'] as $key => $value ){
		if( in_array($key, $options['system']['target_market']) )
			$res .= '<option value="' . $key . '"' . ($selected == $key ? ' selected="selected"' : '') . '>' . $value . "</option>\n";
	}
	$res .= '</select>'."\n";
	
	if($out == 'return') {
		return $res;
	} else {
		echo $res;
	}
}

function usces_pref_select( $type, $values, $out = 'return' ){
	global $usces, $usces_states;

	$country = empty($values['country']) ? usces_get_base_country() : $values['country'];
	$options = get_option('usces');
	if( !in_array($country, $options['system']['target_market']) )
		$country = $options['system']['target_market'][0];
	$prefs = get_usces_states($country);
	$html = '<select name="' . esc_attr($type . '[pref]') . '" id="' . esc_attr($type) . '_pref" class="pref">';
	$prefs_count = count($prefs);
	if($prefs_count > 0) {
		$select = __('-- Select --', 'usces_dual');
		$html .= "\t".'<option value="' . esc_attr($select) . '">' . esc_html($select) . "</option>\n";
		for($i = 1; $i < $prefs_count; $i++) 
			$html .= "\t".'<option value="' . esc_attr($prefs[$i]) . '"' . ($prefs[$i] == $values['pref'] ? ' selected="selected"' : '') . '>' . esc_html($prefs[$i]) . "</option>\n";
	}
	$html .= "</select>\n";
	$html = apply_filters( 'usces_filter_pref_field', $html, $type, $values, $country, $prefs );

	if( $out == 'return' ){
		return $html;
	}else{
		echo $html;
	}
}

function usces_is_tax_display() {
	global $usces;
	if( isset($usces->options['tax_display']) and 'deactivate' == $usces->options['tax_display'] )
		return false;
	else
		return true;
}

function usces_get_tax_display() {
	global $usces;
	$tax_display = ( isset($usces->options['tax_display']) ) ? $usces->options['tax_display'] : 'activate';
	return $tax_display;
}

function usces_get_tax_mode(){
	global $usces;
	return $usces->options['tax_mode'];
}

function usces_get_tax_target(){
	global $usces;
	return $usces->options['tax_target'];
}

function usces_is_member_system(){
	global $usces;
	if($usces->options['membersystem_state'] == 'activate')
		return true;
	else
		return false;
}

function usces_is_member_system_point(){
	global $usces;
	if($usces->options['membersystem_point'] == 'activate')
		return true;
	else
		return false;
}

function usces_point_coverage() {
	global $usces;
	return $usces->options['point_coverage'];
}

function usces_shipping_country_option( $selected, $out = '' ){
	global $usces_settings;
	$options = get_option('usces');
	$res = '';
	foreach ( $usces_settings['country'] as $key => $value ){
		if( in_array($key, $options['system']['target_market']) )
			$res .= '<option value="' . $key . '"' . ($selected == $key ? ' selected="selected"' : '') . '>' . $value . "</option>\n";
	}
	echo $res;
}

function usces_get_cart_button( $out = '' ) {
	global $usces;
	$res = '';
	
	if($usces->use_js){
		$res .= '<input name="previous" type="button" id="previouscart" class="continue_shopping_button" value="' . __('continue shopping','usces') . '"' . apply_filters('usces_filter_cart_prebutton', ' onclick="uscesCart.previousCart();"') . ' />&nbsp;&nbsp;';
		if( usces_is_cart() ) {
			$res .= '<input name="customerinfo" type="submit" class="to_customerinfo_button" value="' . __(' Next ','usces') . '"' . apply_filters('usces_filter_cart_nextbutton', ' onclick="return uscesCart.cartNext();"') . ' />';
		}
	}else{
		$res .= '<a href="' . get_home_url() . '" class="continue_shopping_button">' . __('continue shopping','usces') . '</a>&nbsp;&nbsp;';
		if( usces_is_cart() ) {
			$res .= '<input name="customerinfo" type="submit" class="to_customerinfo_button" value="' . __(' Next ','usces') . '"' . apply_filters('usces_filter_cart_nextbutton', NULL) . ' />';
		}
	}
	$res = apply_filters('usces_filter_get_cart_button', $res);

	if($out == 'return'){
		return $res;
	}else{
		echo $res;
	}
}

function usces_get_customer_button( $out = '' ) {
	global $usces, $member_regmode;
	$res = '';
	
	$res = '<input name="backCart" type="submit" class="back_cart_button" value="'.__('Back', 'usces').'" />&nbsp;&nbsp;';
	
	$button = '<input name="deliveryinfo" type="submit" class="to_deliveryinfo_button" value="'.__(' Next ', 'usces').'" />&nbsp;&nbsp;';
	$res .= apply_filters('usces_filter_customer_button', $button);
	
	if(usces_is_membersystem_state() && $member_regmode != 'editmemberfromcart' && usces_is_login() == false ){
		$res .= '<input name="reganddeliveryinfo" type="submit" class="to_reganddeliveryinfo_button" value="'.__('To the next while enrolling', 'usces').'"' . apply_filters('usces_filter_customerinfo_prebutton', NULL) . ' />';
	}elseif(usces_is_membersystem_state() && $member_regmode == 'editmemberfromcart' ){
		$res .= '<input name="reganddeliveryinfo" type="submit" class="to_reganddeliveryinfo_button" value="'.__('Revise member information, and to next', 'usces').'"' . apply_filters('usces_filter_customerinfo_nextbutton', NULL) . ' />';
	}
	
	$res = apply_filters('usces_filter_get_customer_button', $res);

	if($out == 'return'){
		return $res;
	}else{
		echo $res;
	}
}

function usces_delivery_secure_form( $out = '' ) {
	global $usces, $usces_entries, $usces_carts;
	$html = '';
	include( USCES_PLUGIN_DIR . "/includes/delivery_secure_form.php");

	if($out == 'return'){
		return $html;
	}else{
		echo $html;
	}
}

function usces_delivery_info_script( $out ='' ){

}

function usces_delivery_info_scripts() {
	global $usces, $usces_entries, $usces_carts;
	$html = '';
	if( 'delivery' == $usces->page ) {
		include( USCES_PLUGIN_DIR . "/includes/delivery_info_script.php" );
	} elseif( usces_is_cart_page() && usces_is_login() ) {
		$paypal_ec = false;
		$payments = usces_get_system_option( 'usces_payment_method', 'sort' );
		foreach( (array)$payments as $id => $payment ) {
			if( 'acting_paypal_ec' == $payment['settlement'] && 'activate' == $payment['use'] ) { $paypal_ec = true; break; }
		}
		if( $paypal_ec ) {
			include( USCES_PLUGIN_DIR . "/includes/delivery_info_script.php" );
		}
	}
	echo $html;
}

function usces_get_entries(){
	global $usces, $usces_entries;
	$usces_entries = $usces->cart->get_entry();
}

function usces_get_carts(){
	global $usces, $usces_carts;
	$usces_carts = $usces->cart->get_cart();
}

function usces_get_members(){
	global $usces, $usces_members;
	$usces_members = $usces->get_member();
}

function usces_error_message( $out = ''){
	global $usces;
	
	if($out == 'return'){
		return $usces->error_message;
	}else{
		echo $usces->error_message;
	}
}

function usces_url( $type, $out = ''){
	global $usces;
	
	switch ( $type ){
		case 'cart':
			$url = USCES_CART_URL;
			break;
		case 'login':
			$url = USCES_LOGIN_URL;
			break;
		case 'member':
			$url = USCES_MEMBER_URL;
			break;
		case 'newmember':
			$url = USCES_NEWMEMBER_URL;
			break;
		case 'lostmemberpassword':
			$url = USCES_LOSTMEMBERPASSWORD_URL;
			break;
		case 'cartnonsession':
			$url = USCES_CART_NONSESSION_URL;
			break;
	}
	
	if($out == 'return'){
		return $url;
	}else{
		echo $url;
	}
}

function usces_total_price( $out = ''){
	global $usces;
	
	if($out == 'return'){
		return $usces->get_total_price();
	}else{
		echo $usces->get_total_price();
	}
}

function usces_completion_settlement( $out ='' ){
	global $usces, $usces_entries;
	$html = '';
	
	$template = apply_filters( 'usces_filter_completion_settlement', USCES_PLUGIN_DIR . "/includes/completion_settlement.php", $usces_entries );
	require( $template );
	
	if($out == 'return'){
		return $html;
	}else{
		echo $html;
	}
}

function usces_purchase_button( $out ='' ){
	global $usces, $usces_entries;
	$html = '';
	include( USCES_PLUGIN_DIR . "/includes/purchase_button.php");

	if($out == 'return'){
		return $html;
	}else{
		echo $html;
	}
}

function usces_get_member_regmode(){
	global $member_regmode;
	$member_regmode = isset( $_SESSION['usces_entry']['member_regmode'] ) ? $_SESSION['usces_entry']['member_regmode'] : 'none'; 
}

function uesces_get_error_settlement( $out = '' ) {
	$res = '';
	$res = apply_filters( 'usces_filter_get_error_settlement', $res );
	if( empty( $res ) ) {
		$res = __( 'Sorry, If you have any questions, please contact the administrator of the site.', 'usces' );
	}

	if($out == 'return'){
		return $res;
	}else{
		echo $res;
	}
}

function usces_page_name( $out = '') {
	global $usces;
	$page = '';
	
	if( empty($usces->page) ){
		if( $usces->is_cart_page($_SERVER['REQUEST_URI']) ){
			$page = 'cart';
		}elseif( $usces->is_member_page($_SERVER['REQUEST_URI']) ){
			$page = 'member';
		}
	}else{
			$page = $usces->page;
	}
	
	if($out == 'return'){
		return esc_js($page);
	}else{
		echo esc_html($page);
	}
}

function usces_post_reg_orderdata($order_id, $results){
	global $usces, $wpdb;
	$acting = isset($_GET['acting']) ? $_GET['acting'] : '';
	$data = array();

	if( $order_id ){

		switch ( $acting ) {
			case 'epsilon':
				$trans_id = isset($_REQUEST['trans_code']) ? $_REQUEST['trans_code'] : '';
				break;
			case 'paypal_ipn':
				$trans_id = isset($_REQUEST['txn_id']) ? $_REQUEST['txn_id'] : '';
				break;
			case 'paypal':
				$trans_id = isset($results['txn_id']) ? $results['txn_id'] : '';
				break;
			case 'paypal_ec':
				$trans_id = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';
				if(isset($results['settlement_id'])) 
					$usces->set_order_meta_value('settlement_id', $results['settlement_id'], $order_id);
				if(isset($results['profile_id'])) 
					$usces->set_order_meta_value('profile_id', $results['profile_id'], $order_id);
				break;
			case 'remise_card':
				$trans_id = isset($_REQUEST['X-TRANID']) ? $_REQUEST['X-TRANID'] : '';
				break;
			case 'remise_conv':
				$trans_id = isset($_REQUEST['X-JOB_ID']) ? $_REQUEST['X-JOB_ID'] : '';
				break;
			case 'jpayment_card':
			case 'jpayment_conv':
			case 'jpayment_bank':
				$trans_id = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : '';
				break;
			case 'telecom_card':
				$trans_id = isset($_REQUEST['sendid']) ? $_REQUEST['sendid'] : '';
				break;
			case 'digitalcheck_card':
			case 'digitalcheck_conv':
				$trans_id = isset($_REQUEST['SID']) ? $_REQUEST['SID'] : '';
				break;
			case 'mizuho_card':
			case 'mizuho_conv':
				$trans_id = isset($_REQUEST['stran']) ? $_REQUEST['stran'] : '';
				break;
			default:
				if( isset($_REQUEST['FUKA']) && strstr($_REQUEST['FUKA'], "digitalcheck_card") ) {
					$trans_id = isset($_REQUEST['SID']) ? $_REQUEST['SID'] : '';
				} else {
					$trans_id = '';
				}
		}
	
		if(!empty($trans_id)) {
			$usces->set_order_meta_value('trans_id', $trans_id, $order_id);
		}
	}
	
}

function usces_paypal_doecp( &$results ) {
	global $usces;
	$entry = $usces->cart->get_entry();
	$cart = $usces->cart->get_cart();

	$post_id = $cart[0]['post_id'];
	$charging_type = $usces->getItemChargingType( $post_id );
	if( 'continue' != $charging_type ) {
		//通常購入
		//Format the other parameters that were stored in the session from the previous calls
		$token = urlencode($_REQUEST['token']);
		$paymentType = apply_filters( 'usces_filter_paypal_ec_paymentaction', 'Sale' );
		$currencyCodeType = urlencode($usces->get_currency_code());
		$payerID = urlencode($_REQUEST['PayerID']);
		$serverName = urlencode($_SERVER['SERVER_NAME']);

		$nvpstr = '&TOKEN='.$token.'&PAYERID='.$payerID.'&PAYMENTREQUEST_0_PAYMENTACTION='.$paymentType.'&PAYMENTREQUEST_0_CURRENCYCODE='.$currencyCodeType.'&IPADDRESS='.$serverName;
		$item_total_price = 0;
		$i = 0;
		foreach( $cart as $cart_row ) {
			$nvpstr .= 
				'&L_PAYMENTREQUEST_0_NAME'.$i.'='.urlencode($usces->getItemName($cart_row['post_id'])).
				'&L_PAYMENTREQUEST_0_AMT'.$i.'='.usces_crform($cart_row['price'], false, false, 'return', false).
				'&L_PAYMENTREQUEST_0_NUMBER'.$i.'='.urlencode($usces->getItemCode($cart_row['post_id'])).' '.$cart_row['sku'].
				'&L_PAYMENTREQUEST_0_QTY'.$i.'='.$cart_row['quantity'];
			$options = ( !empty( $cart_row['options'] ) ) ? $cart_row['options'] : array();
			if( is_array($options) && count($options) > 0 ) {
				$optstr = '';
				foreach( $options as $key => $value ) {
					if( !empty($key) ) {
						$key = urldecode($key);
						$value = maybe_unserialize($value);
						if( is_array($value) ) {
							$c = '';
							$optstr .= $key . ' : ';
							foreach( $value as $v ) {
								$optstr .= $c.urldecode($v);
								$c = ', ';
							}
							$optstr .= "\r\n";
						} else {
							$optstr .= $key . ' : ' . urldecode($value) . "\r\n";
						}
					}
				}
				if( $optstr != '' ) {
					if( 60 < mb_strlen($optstr, 'UTF-8') ) $optstr = mb_substr($optstr, 0, 60, 'UTF-8').'...';
					$nvpstr .= '&L_PAYMENTREQUEST_0_DESC'.$i.'='.urlencode($optstr);
				}
			}
			$item_total_price += ( $cart_row['price'] * $cart_row['quantity'] );
			$i++;
		}
		if( !empty($entry['order']['discount']) ) {
			$nvpstr .= 
				'&L_PAYMENTREQUEST_0_NAME'.$i.'='.urlencode(__('Campaign discount', 'usces')).
				'&L_PAYMENTREQUEST_0_AMT'.$i.'='.usces_crform($entry['order']['discount'], false, false, 'return', false);
			$item_total_price += $entry['order']['discount'];
			$i++;
		}
		if( !empty($entry['order']['usedpoint']) ) {
			$nvpstr .= 
				'&L_PAYMENTREQUEST_0_NAME'.$i.'='.urlencode(__('Used points', 'usces')).
				'&L_PAYMENTREQUEST_0_AMT'.$i.'='.usces_crform($entry['order']['usedpoint']*(-1), false, false, 'return', false);
			$item_total_price -= $entry['order']['usedpoint'];
			$i++;
		}
		$nvpstr .= 
			'&PAYMENTREQUEST_0_ITEMAMT='.usces_crform($item_total_price, false, false, 'return', false).
			'&PAYMENTREQUEST_0_SHIPPINGAMT='.usces_crform($entry['order']['shipping_charge'], false, false, 'return', false).
			'&PAYMENTREQUEST_0_AMT='.usces_crform($entry['order']['total_full_price'], false, false, 'return', false);
		if( !empty($entry['order']['cod_fee']) ) $nvpstr .= '&PAYMENTREQUEST_0_HANDLINGAMT='.usces_crform($entry['order']['cod_fee'], false, false, 'return', false);
		if( !empty($entry['order']['tax']) ) $nvpstr .= '&PAYMENTREQUEST_0_TAXAMT='.usces_crform($entry['order']['tax'], false, false, 'return', false);
		$nvpstr .= '&PAYMENTREQUEST_0_AMT='.usces_crform($entry['order']['total_full_price'], false, false, 'return', false);
		$nvpstr = apply_filters( 'usces_filter_usces_paypal_doecp', $nvpstr, $charging_type );
		if( 'shipped' == $usces->getItemDivision( $post_id ) ) {
			$name = urlencode($entry['delivery']['name2'].' '.$entry['delivery']['name1']);
			$address2 = urlencode($entry['delivery']['address2']);
			$address3 = urlencode($entry['delivery']['address3']);
			$address1 = urlencode($entry['delivery']['address1']);
			$pref = urlencode($entry['delivery']['pref']);
			$country = ( !empty($entry['delivery']['country']) ) ? $entry['delivery']['country'] : usces_get_base_country();
			$country_code = apply_filters( 'usces_filter_paypalec_shiptocountrycode', $country );
			if( $country_code == 'TW' ) {
				$city = $address1;
				$address1 = $pref;
				$pref = $city;
			}
			$zip = $entry['delivery']['zipcode'];
			$tel = ltrim( str_replace( '-', '', $entry['delivery']['tel'] ), '0' );
			if( 'US' != $country_code and 'CA' != $country_code ) {
				$nvpstr .= 
				'&PAYMENTREQUEST_0_SHIPTONAME='.$name.
				'&PAYMENTREQUEST_0_SHIPTOSTREET='.$address2.
				'&PAYMENTREQUEST_0_SHIPTOSTREET2='.$address3.
				'&PAYMENTREQUEST_0_SHIPTOCITY='.$address1.
				'&PAYMENTREQUEST_0_SHIPTOSTATE='.$pref.
				'&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE='.$country_code.
				'&PAYMENTREQUEST_0_SHIPTOZIP='.$zip.
				'&PAYMENTREQUEST_0_SHIPTOPHONENUM='.$tel;
			}
		}
		$usces->paypal->setMethod('DoExpressCheckoutPayment');
		$usces->paypal->setData($nvpstr);
		$res = $usces->paypal->doExpressCheckout();
		$resArray = $usces->paypal->getResponse();
		$ack = strtoupper($resArray["ACK"]);
		if( $ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING" ) {
			$transactionId = $resArray["PAYMENTINFO_0_TRANSACTIONID"]; // ' Unique transaction ID of the payment. Note:  If the PaymentAction of the request was Authorization or Order, this value is your AuthorizationID for use with the Authorization & Capture APIs. 
			$results['settlement_id'] = $transactionId;
			$results['payment_status'] = isset( $resArray["PAYMENTINFO_0_PAYMENTSTATUS"] ) ? $resArray["PAYMENTINFO_0_PAYMENTSTATUS"] : '';
			$results['pending_reason'] = isset( $resArray["PAYMENTINFO_0_PENDINGREASON"] ) ? $resArray["PAYMENTINFO_0_PENDINGREASON"] : '';

		} else {
			//Display a user friendly Error on the page using any of the following error information returned by PayPal
			$ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
			$ErrorShortMsg = urldecode($resArray["L_SHORTMESSAGE0"]);
			$ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
			$ErrorSeverityCode = urldecode($resArray["L_SEVERITYCODE0"]);
			usces_log('PayPal : DoExpressCheckoutPayment API call failed. Error Code:['.$ErrorCode.'] Error Severity Code:['.$ErrorSeverityCode.'] Short Error Message:'.$ErrorShortMsg.' Detailed Error Message:'.$ErrorLongMsg, 'acting_transaction.log');
			if( '10486' == $ErrorCode ) {
				$options = get_option('usces');
				$acting_opts = $options['acting_settings']['paypal'];
				$query = http_build_query($_REQUEST);
				$payPalURL = $acting_opts['paypal_url'].'?cmd=_express-checkout&'.$query;
				header("Location: ".$payPalURL);
				exit;
			}
			$log = array( 'acting'=>'paypal_ec', 'key'=>'(empty key)', 'result'=>$ack, 'data'=>$resArray );
			usces_save_order_acting_error( $log );
			return false;
		}

	} else {
		if( !apply_filters( 'usces_pre_create_recurring_payments_profile', true ) ) return false;

		//定期支払い
		$paymentAmount = usces_crform($entry['order']['total_full_price'], false, false, 'return', false);
		$token = urlencode($_REQUEST['token']);
		$currencyCodeType = urlencode($usces->get_currency_code());
		$profileStartDate = date('Y-m-d', dlseller_first_charging($post_id, 'time')).'T01:01:01Z';
		$billingPeriod = "Month";// or "Day", "Week", "SemiMonth", "Year"
		$billingFreq = $usces->getItemFrequency($post_id);
		$desc = urlencode(usces_make_agreement_description($cart, $entry['order']['total_full_price']));
		$totalBillingCycles = (empty($dlitem['dlseller_interval'])) ? '' : '&TOTALBILLINGCYCLES='.urlencode($dlitem['dlseller_interval']);

		$nvpstr = '&TOKEN='.$token.'&AMT='.$paymentAmount.'&CURRENCYCODE='.$currencyCodeType.'&PROFILESTARTDATE='.$profileStartDate.'&BILLINGPERIOD='.$billingPeriod.'&BILLINGFREQUENCY='.$billingFreq.'&DESC='.$desc.$totalBillingCycles;
		$nvpstr = apply_filters( 'usces_filter_usces_paypal_doecp', $nvpstr, $charging_type );

		$usces->paypal->setMethod('CreateRecurringPaymentsProfile');
		$usces->paypal->setData($nvpstr);
		$res = $usces->paypal->doExpressCheckout();
		$resArray = $usces->paypal->getResponse();
		$ack = strtoupper($resArray["ACK"]);
		if( $ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING" ) {
			$profileid = $resArray["PROFILEID"];
			$results['settlement_id'] = $profileid;
			$results['profile_id'] = $profileid;
			$results['profile_status'] = isset( $resArray["PROFILESTATUS"] ) ? $resArray["PROFILESTATUS"] : '';

		} else {
			//Display a user friendly Error on the page using any of the following error information returned by PayPal
			$ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
			$ErrorShortMsg = urldecode($resArray["L_SHORTMESSAGE0"]);
			$ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
			$ErrorSeverityCode = urldecode($resArray["L_SEVERITYCODE0"]);
			usces_log('PayPal : CreateRecurringPaymentsProfile API call failed. Error Code:['.$ErrorCode.'] Error Severity Code:['.$ErrorSeverityCode.'] Short Error Message:'.$ErrorShortMsg.' Detailed Error Message:'.$ErrorLongMsg, 'acting_transaction.log');
			$log = array( 'acting'=>'paypal_ec', 'key'=>'(empty key)', 'result'=>$ack, 'data'=>$resArray );
			usces_save_order_acting_error( $log );
			return false;
		}
	}

	return true;
}

function usces_make_agreement_description($cart, $amt) {
	global $usces;

	$cart_row = $cart[0];
	$quantity = $cart_row['quantity'];
	$itemName = $usces->getItemName($cart_row['post_id']);
	if(50 < mb_strlen($itemName, 'UTF-8')) $itemName = mb_substr($itemName, 0, 50, 'UTF-8').'...';
	$amt = usces_crform($amt, true, false, 'return', true);
	$desc = $itemName.' '.__('Quantity','usces').':'.$quantity.' '.$amt;
	return($desc);
}

function usces_get_send_out_date(){
	global $usces;

	$bus_day_arr = (isset($usces->options['business_days'])) ? $usces->options['business_days'] : false;
	list( $today_year, $today_month, $today_day, $hour, $minute, $second ) = preg_split( '([^0-9])', current_time('mysql') );
	if( !is_array($bus_day_arr) ){
		$today_bus_flag = 1;
	}else{
		$today_bus_flag = isset($bus_day_arr[(int)$today_year][(int)$today_month][(int)$today_day]) ? (int)$bus_day_arr[(int)$today_year][(int)$today_month][(int)$today_day] : 1;
	}
	// get the time limit addition
	$limit_hour = (!empty($usces->options['delivery_time_limit']['hour'])) ? $usces->options['delivery_time_limit']['hour'] : false;
	$limit_min = (!empty($usces->options['delivery_time_limit']['min'])) ? $usces->options['delivery_time_limit']['min'] : false;

	if( false === $hour || false === $minute ){
		$time_limit_addition = false;
	}elseif( ($hour.':'.$minute.':'.$second) > ($limit_hour.':'.$limit_min.':00') ){
		$time_limit_addition = 1;
	}else{
		$time_limit_addition = 0;
	}
	// get the shipping indication in cart
	$cart = $usces->cart->get_cart();
	$shipping_indication = apply_filters('usces_filter_shipping_indication', $usces->options['usces_shipping_indication']);
	$shipping = 0;
	$indication_flag = true;
	$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
	for($i = 0; $i < $cart_count; $i++) {
		$cart_row = $cart[$i];
		$post_id = $cart_row['post_id'];
		$itemShipping = (int)$usces->getItemShipping($post_id);
		if($itemShipping === 0 or $itemShipping === 9) {
			$indication_flag = false;
			break;
		}
		if($shipping < $itemShipping) $shipping = $itemShipping;
	}
	$indication_incart = ( $indication_flag ) ? $shipping_indication[$shipping] : false;
	$indication_incart = apply_filters( 'usces_filter_indication_incart', $indication_incart, $shipping_indication, $shipping, $cart );
	// get the send out date
	$sendout_num = 0;
	if( $today_bus_flag ){
		if( $time_limit_addition ){
			$sendout_num += 1;
		}
		if( false !== $indication_incart ){
			$sendout_num += $indication_incart;
		}
	}else{
		if( false !== $indication_incart ){
			$sendout_num += $indication_incart;
		}
	}
	$holiday = 0;
	for( $i=0; $i<=$sendout_num; $i++ ){
		list($yyyy, $mm, $dd) = explode('-', date('Y-m-d', mktime(0,0,0,(int)$today_month,($today_day + $i),(int)$today_year)));
		if( isset($bus_day_arr[(int)$yyyy][(int)$mm][(int)$dd]) && !$bus_day_arr[(int)$yyyy][(int)$mm][(int)$dd] ){
			$holiday++;
			$sendout_num++;	
		}
		if( 100 < $sendout_num ) break;
	}
	list($send_y, $send_m, $send_d) = explode('-', date('Y-m-d', mktime(0,0,0,(int)$today_month,($today_day + $sendout_num),(int)$today_year)));
	
	$res = array(
			'today_bus_flag'	=> $today_bus_flag, 
			'time_limit_addition'=> $time_limit_addition, 
			'indication_incart'	=> $indication_incart, 
			'holiday'			=> $holiday, 
			'sendout_num'		=> $sendout_num, 
			'sendout_date'		=> array('year' => $send_y, 'month' => $send_m, 'day' => $send_d)
			);
	return $res;
}

function usces_action_footer_comment(){
	echo "<!-- Welcart version : v".USCES_VERSION." -->\n";
}

function usces_set_acting_notification_time( $key ){
	global $wpdb;
	$tableName = $wpdb->prefix . "usces_access";
	$query = $wpdb->prepare("SELECT ID FROM $tableName WHERE acc_type = %s AND acc_key = %s", 'notification_time', $key);
	$res = $wpdb->get_var( $query );
	if( $res )
		return;
		
	$query = $wpdb->prepare("INSERT INTO $tableName (acc_key, acc_type, acc_num1) VALUES (%s, %s, %d)", 
							$key, 'notification_time', time());
	$res = $wpdb->query( $query );
}

function usces_check_notification_time( $key, $time ){
	global $wpdb;
	$tableName = $wpdb->prefix . "usces_access";
	$query = $wpdb->prepare("SELECT acc_num1 FROM $tableName WHERE acc_type = %s AND acc_key = %s", 'notification_time', $key);
	$res = $wpdb->get_var( $query );
	if( !$res )
		return false;
		
	$past = time() - $res;
	if( $time > $past )
		return true;
	else
		return false;
}

function usces_is_same_itemcode( $post_id, $item_code ){
	global $wpdb, $usces;

	$query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE post_id <> %d AND meta_key = %s AND meta_value = %s", $post_id, '_itemCode', $item_code);
	$ids = $wpdb->get_col( $query );
	if( !$ids ){
		return false;
	}
	$id_str = implode(',', $ids);
	$query = "SELECT ID FROM $wpdb->posts WHERE ID IN({$id_str}) AND post_status = 'publish'";
	$res = $wpdb->get_col( $query );
	if( !$res ){
		return false;
	}
	
	return $res;
}

function usces_update_sku( $post_id, $skucode, $fieldname, $value ){
		global $wpdb;
		$metas = usces_get_post_meta($post_id, '_isku_');
		if( ! $metas )
			return false;
		
		$meta_id = '';
		foreach( $metas as $meta ){
			$sku = unserialize($meta['meta_value']);
			if( $skucode == $sku['code'] ){
				$meta_id = $meta['meta_id'];
				$sku[$fieldname] = $value;
				break;
			}
		}
		$serialized_values = serialize($sku);
		if( !empty($meta_id) ) $res = $wpdb->query( $wpdb->prepare("UPDATE $wpdb->postmeta SET meta_value = %s WHERE meta_id = %d", $serialized_values, $meta_id) );
		if( !$res ){
			return false;
		}else{
			return true;
		}
}

function usces_get_items_skus() {
	global $wpdb, $usces;
	$res = array();
	$item_codes = array();
	$item_names = array();
	$status = array();

	$query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} 
							WHERE post_mime_type = %s AND post_status = %s", 'item', 'publish');
	$IDs = $wpdb->get_col($query);
	if( !$IDs )
		return $res;

	
	$key = 0;
	foreach((array)$IDs as $post_id){
		$item_codes[$post_id] = $usces->getItemCode($post_id);
		$item_names[$post_id] = $usces->getItemName($post_id);

		$metas = usces_get_post_meta($post_id, '_isku_');
		foreach( (array)$metas as $mkey => $rows ){
			$metas[$mkey]['meta_value'] = unserialize($rows['meta_value']);
			$allmetas[] = $metas;
			$status[] = $metas[$mkey]['meta_value']['stock'];
			$key++;
		}
	}

}
function usces_get_non_zerostoc_items() {
	global $wpdb, $usces;

	$query = $wpdb->prepare("SELECT ID, code.meta_value AS `code`, name.meta_value AS `name` FROM {$wpdb->posts} 
							LEFT JOIN {$wpdb->postmeta} AS `code` ON ID = code.post_id AND '_itemCode' = code.meta_key 
							LEFT JOIN {$wpdb->postmeta} AS `name` ON ID = name.post_id AND '_itemName' = name.meta_key 
							LEFT JOIN {$wpdb->postmeta} AS `sku` ON ID = sku.post_id AND '_isku_' = sku.meta_key
							WHERE post_mime_type = %s AND post_type = %s AND post_status <> %s AND (sku.meta_value LIKE %s OR sku.meta_value LIKE %s) 
							GROUP BY ID",
							'item', 'post', 'trash', '%"stocknum";i:0%', '%"stocknum";s:1:"0"%');
	$res = $wpdb->get_results($query, ARRAY_A);

	return $res;

}

function usces_get_stocs() {
	global $wpdb, $usces;
	$status = array();

	$query = $wpdb->prepare("SELECT m.meta_value FROM {$wpdb->posts} AS `p` 
							LEFT JOIN {$wpdb->postmeta} AS `m` ON p.ID = m.post_id AND m.meta_key = %s 
							WHERE post_mime_type = %s AND post_type = %s AND post_status <> %s"
							, '_isku_', 'item', 'post', 'trash');
	$values = $wpdb->get_col($query);
	if( !$values )
		return $status;
		
	foreach((array)$values as $value){
			$meta_value = unserialize($value);
			$status[] = (string)$meta_value['stock'];
	}
	return array_count_values($status);
}

function usces_get_deco_order_id( $order_id ) {
	global $usces;
	
	$dec_order_id = $usces->get_order_meta_value( 'dec_order_id', $order_id);
	if( !$dec_order_id ){
		$dec_order_id = str_pad($order_id, $usces->options['system']['dec_orderID_digit'], "0", STR_PAD_LEFT);
	}
	return $dec_order_id;
}

function usces_mail_line( $type, $email = '' ) {
	$line = '';

	switch( $type ) {
	case 1:
		$line = "******************************************************";
		break;
	case 2:
		$line = "------------------------------------------------------------------";
		break;
	case 3:
		$line = "=============================================";
		break;
	}

	$line = apply_filters( 'usces_filter_mail_line', $line, $type, $email );

	return $line."\r\n";
}

function usces_get_gp_price($post_id, $p, $quant){
	global $usces;
	$GpN1 = $usces->getItemGpNum1($post_id);
	$GpN2 = $usces->getItemGpNum2($post_id);
	$GpN3 = $usces->getItemGpNum3($post_id);
	$GpD1 = $usces->getItemGpDis1($post_id);
	$GpD2 = $usces->getItemGpDis2($post_id);
	$GpD3 = $usces->getItemGpDis3($post_id);

	if( empty($GpN1) || empty($GpD1) ) {
	
			$realprice = $p;
			
	}else if( (!empty($GpN1) && !empty($GpD1)) && (empty($GpN2) || empty($GpD2)) ) {
	
		if( $quant >= $GpN1 ) {
			$realprice = round($p * (100 - $GpD1) / 100);
		}else{
			$realprice = $p;
		}
		
	}else if( (!empty($GpN1) && !empty($GpD1)) && (!empty($GpN2) && !empty($GpD2)) && (empty($GpN3) || empty($GpD3)) ) {
	
		if( $quant >= $GpN2 ) {
			$realprice = round($p * (100 - $GpD2) / 100);
		}else if( $quant >= $GpN1 && $quant < $GpN2 ) {
			$realprice = round($p * (100 - $GpD1) / 100);
		}else{
			$realprice = $p;
		}
		
	}else if( (!empty($GpN1) && !empty($GpD1)) && (!empty($GpN2) && !empty($GpD2)) && (!empty($GpN3) && !empty($GpD3)) ) {
	
		if( $quant >= $GpN3 ) {
			$realprice = round($p * (100 - $GpD3) / 100);
		}else if( $quant >= $GpN2 && $quant < $GpN3 ) {
			$realprice = round($p * (100 - $GpD2) / 100);
		}else if( $quant >= $GpN1 && $quant < $GpN2 ) {
			$realprice = round($p * (100 - $GpD1) / 100);
		}else{
			$realprice = $p;
		}
		
	}else{
		$realprice = $p;
	}
	
	$realprice = apply_filters('usces_filter_get_gp_price', $realprice, $post_id, $p, $quant);
	return $realprice;
}

function usces_is_complete_settlement( $payment_name, $status = '' ) {
	$complete = false;
	$options = get_option('usces');
	if( $options['point_assign'] == 0 ) {
		$complete = true;
	} else {
		$payments = usces_get_system_option( 'usces_payment_method', 'name' );
		if( isset($payments[$payment_name]['settlement']) ) {
			switch( $payments[$payment_name]['settlement'] ) {
			case 'acting':
				if( false !== strpos( $status, 'pending' ) ) break;
			case 'acting_remise_card':
			case 'acting_jpayment_card':
			case 'acting_paypal_ec':
			case 'acting_telecom_card':
			case 'acting_digitalcheck_card':
			case 'acting_mizuho_card':
			case 'acting_anotherlane_card':
			case 'acting_veritrans_card':
			case 'COD':
				$complete = true;
			}
		}
	}
	$complete = apply_filters( 'usces_filter_is_complete_settlement', $complete, $payment_name, $status );
	return $complete;
}

function usces_action_acting_getpoint( $order_id, $add = true ) {
	global $usces, $wpdb;
	
	if( !apply_filters( 'usces_action_acting_getpoint_switch', true, $order_id, $add) )
		return;
	
	$options = get_option('usces');
	if( $options['point_assign'] != 0 ) {
		if( 'activate' == $usces->options['membersystem_state'] && 'activate' == $usces->options['membersystem_point'] ) {
			$table_name = $wpdb->prefix . "usces_order";
			$mquery = $wpdb->prepare("SELECT mem_id, order_getpoint FROM $table_name WHERE ID = %d", $order_id);
			$values = $wpdb->get_row( $mquery, ARRAY_A );
			$mem_id = $values['mem_id'];
			$getpoint = $values['order_getpoint'];

			if( !empty($mem_id) && 0 < $getpoint ) {
				$calc = ($add) ? '+' : '-';
				$member_table_name = usces_get_tablename( 'usces_member' );
				$mquery = $wpdb->prepare("UPDATE $member_table_name SET mem_point = (mem_point ".$calc." %d) WHERE ID = %d", $getpoint, $mem_id);
				$wpdb->query( $mquery );

				if( !empty($_SESSION['usces_member']['point']) ) {
					$mquery = $wpdb->prepare("SELECT mem_point FROM $member_table_name WHERE ID = %d", $mem_id);
					$point = $wpdb->get_var( $mquery );
					$_SESSION['usces_member']['point'] = $point;
				}
			}
		}
	}
	do_action( 'usces_action_acting_getpoint', $order_id, $add );
}

function usces_restore_point( $mem_id, $point ) {
	global $wpdb;

	if( !apply_filters( 'usces_action_restore_point_switch', true, $mem_id, $point) )
		return;

	$member_table_name = usces_get_tablename( 'usces_member' );
	$query = $wpdb->prepare( "UPDATE $member_table_name SET mem_point = (mem_point - %d) WHERE ID = %d", $point, $mem_id );
	$wpdb->query( $query );
}

function usces_set_free_csv( $customer ){
	$free_csv = "";
	if( !WCUtils::is_blank($customer['name1']) ) {
		$free_csv = "LAST_NAME=".mb_convert_encoding($customer['name1'],'SJIS','UTF-8').",FIRST_NAME=".mb_convert_encoding($customer['name2'],'SJIS','UTF-8').",TEL=".str_replace("-","",$customer['tel']).",MAIL=".$customer['mailaddress1'];
		$free_csv = base64_encode( $free_csv );
	}
	return $free_csv;
}

function usces_get_itemopt_filed($post_id, $sku, $opt){
	global $usces;
	$sku = urlencode($sku);
	$optcode = urlencode($opt['name']);
	$name = $opt['name'];
	$means = (int)$opt['means'];
	$essential = (int)$opt['essential'];
	$session_value = isset( $_SESSION['usces_singleitem']['itemOption'][$post_id][$sku][$optcode] ) ? $_SESSION['usces_singleitem']['itemOption'][$post_id][$sku][$optcode] : NULL;
	$opt['value'] = usces_change_line_break( $opt['value'] );

	$html = '';
	switch($means) {
	case 0://Single-select
	case 1://Multi-select
		$selects = explode("\n", $opt['value']);
		$multiple = ($means === 0) ? '' : ' multiple';
		$multiple_array = ($means == 0) ? '' : '[]';
		$html .= "\n<select name='itemOption[{$post_id}][{$sku}][{$optcode}]{$multiple_array}' id='itemOption[{$post_id}][{$sku}][{$optcode}]' class='iopt_select'{$multiple} onKeyDown=\"if (event.keyCode == 13) {return false;}\">\n";
		if($essential == 1){
			if(  '#NONE#' == $session_value || NULL == $session_value ) 
				$selected = ' selected="selected"';
			else
				$selected = '';
			$html .= "\t<option value='#NONE#'{$selected}>" . __('Choose','usces') . "</option>\n";
		}
		$i=0;
		foreach($selects as $v) {
			if( ($i == 0 && $essential == 0 && NULL == $session_value) || esc_attr($v) == $session_value ) 
				$selected = ' selected="selected"';
			else
				$selected = '';
			$html .= "\t<option value='" . esc_attr($v) . "'{$selected}>" . esc_html($v) . "</option>\n";
			$i++;
		}
		$html .= "</select>\n";
		break;
	case 2://Text
		$html .= "\n<input name='itemOption[{$post_id}][{$sku}][{$optcode}]' type='text' id='itemOption[{$post_id}][{$sku}][{$optcode}]' class='iopt_text' onKeyDown=\"if (event.keyCode == 13) {return false;}\" value=\"" . esc_attr($session_value) . "\" />\n";
		break;
	case 5://Text-area
		$html .= "\n<textarea name='itemOption[{$post_id}][{$sku}][{$optcode}]' id='itemOption[{$post_id}][{$sku}][{$optcode}]' class='iopt_textarea'>" . esc_attr($session_value) . "</textarea>\n";
		break;
	}
	
	$html = apply_filters('usces_get_itemopt_filed', $html, $post_id, $sku, $opt);
	
	return $html;
}

function usces_delete_order_check( $order_id ) {
	$res = apply_filters( 'usces_filter_delete_order_check', true, $order_id );
	return $res;
}

function usces_delete_member_check( $member_id ) {
	$res = apply_filters( 'usces_filter_delete_member_check', true, $member_id );
	return $res;
}

function usces_delete_member_check_front( $member_id ) {
	$res = apply_filters( 'usces_filter_delete_member_check_front', true, $member_id );
	return $res;
}

function usces_itempage_admin_bar() {
    global $wp_admin_bar, $post;
	if( is_single() && usces_is_item() ){
		$wp_admin_bar->remove_menu('edit');
		$ref = urlencode(site_url() . '/wp-admin/admin.php?page=usces_itemedit');
		$wp_admin_bar->add_menu( array(
			'id' => 'edit',
			'title' => __('Edit item', 'usces'),
			'href' => site_url() . '/wp-admin/admin.php?page=usces_itemedit&action=edit&post=' . $post->ID . '&usces_referer=' . $ref
		) );
	}
}

function usces_rand( $digit = 10 ) {
	$num = str_repeat( "9", $digit );
	$rand = apply_filters( 'usces_filter_rand_value', sprintf( '%0'.$digit.'d', mt_rand( 1, (int)$num ) ), $num );
	return $rand;
}

function usces_get_cr_symbol() {
	global $usces, $usces_settings;
	$cr = $usces->options['system']['currency'];
	list( $code, $decimal, $point, $seperator, $symbol ) = $usces_settings['currency'][$cr];
	return $symbol;
}

function usces_make_option_field( $materials, $cart ){
	extract( $materials );
	/*	$materials = compact( 'i', 'cart_row', 'post_id', 'sku', 'sku_code', 'quantity', 
					'options', 'advance', 'itemCode', 'itemName', 'cartItemName', 
					'skuPrice', 'stock', 'red', 'pictid', 'order_id' );
	*/
	$option_fields = usces_get_opts( $post_id, 'sort' );
	$field = '<div>' . "\n";
	$field .= '<ul>' . "\n";
//usces_p($options);
//usces_p($option_fields);
	
	foreach((array)$option_fields as $field_data){
		
		$field .= '<li>' . usces_get_itemOption( $field_data, $materials, $label = '#default#' ) . '</li>' . "\n";
		foreach((array)$options as $op_key => $option){

//usces_p($op_key);
//usces_p($option['meta_key'].$field_data['name']);
			if( $option['meta_key'] == $field_data['name'] ){
				$options[$op_key]['flag'] = 1;
			}
		}
	}

	foreach((array)$options as $option){
		if( !isset( $option['flag'] ) ){
			$meta_value = maybe_unserialize($option['meta_value']);
			if( !is_array( $meta_value ) ){
				$meta_value = (array)$meta_value;
			}
			$value_str = '';
			foreach( $meta_value as $mv ){
				$value_str .= $mv . ' & ';
			}
			$value_str = trim( $value_str, ' & ' );
			$field .= '<li>' . esc_html( $option['meta_key'] ) . ' : ' . esc_html( $value_str ) . '</li>' . "\n";
		}
	}

	$field .= '</ul>' . "\n";
	$field .= '</div>' . "\n";

	echo apply_filters( 'usces_filter_order_edit_form_row', $field, $cart, $materials );
}

function usces_get_itemOption( $field_data, $materials, $label = '#default#' ) {
	global $usces;
	extract( $materials );
	/*	$materials = compact( 'i', 'cart_row', 'post_id', 'sku', 'sku_code', 'quantity', 
					'options', 'advance', 'itemCode', 'itemName', 'cartItemName', 
					'skuPrice', 'stock', 'red', 'pictid', 'order_id' );
	*/
	$opt_value = array();
	foreach( $options as $opt_value ){
		if( $field_data['name'] == $opt_value['meta_key'] ){
			$cartmeta_id = $opt_value['cartmeta_id'];
			$name = $opt_value['meta_key'];
			$value = $opt_value['meta_value'];
			$means = (int)$field_data['means'];
			$essential = (int)$field_data['essential'];
			break;
		}
	}
	if(!$opt_value){
		return '';
	}
	if( empty($name) ){
		return '';
	}
	
	if($label == '#default#')
		$label = $name;



	$field_data['value'] = usces_change_line_break( $field_data['value'] );

	$html = '';
	$name = esc_attr($name);
	$label = esc_attr($label);
	$html .= '<label for="itemOption[' . $cartmeta_id . ']" class="iopt_label">' . $label . '</label>' . "\n";
	switch($means) {
		case 0://Single-select
			$selects = explode("\n", $field_data['value']);
			$value = maybe_unserialize($value);
			$check_value = array();
			if( !is_array( $value ) ){
				$check_value = (array)$value;
			}
			$diff = array_diff( $check_value, $selects );

			if( !empty( $diff ) ){

				$value_str = '';
				foreach( $check_value as $mv ){
					$value_str .= $mv . ' & ';
				}
				$value_str = trim( $value_str, ' & ' );
				$html .= '<span>：' . esc_html( $value_str ) . '</span>' . "\n";

			}else{
				$html .= '<select name="itemOption[' . $cartmeta_id . ']" id="itemOption[' . $cartmeta_id . ']" class="iopt_select" onKeyDown="if (event.keyCode == 13) {return false;}">' . "\n";
				if($essential == 1){
					if(  '#NONE#' == $value || NULL == $value ) 
						$selected = ' selected="selected"';
					else
						$selected = '';
					$html .= '<option value="#NONE#"' . $selected . '>' . __('Choose','usces') . '</option>' . "\n";
				}
				$i=0;
				foreach($selects as $v) {
					$v = trim($v);
					if( ($i == 0 && $essential == 0 && NULL == $value) || esc_attr($v) == esc_attr($value) ) 
						$selected = ' selected="selected"';
					else
						$selected = '';
					$html .= '<option value="' . esc_attr($v) . '"' . $selected . '>' . esc_attr($v) . '</option>' . "\n";
					$i++;
				}
				$html .= '</select>' . "\n";
			}
			break;
		case 1://Multi-select
			$selects = explode("\n", $field_data['value']);
			$value = maybe_unserialize($value);
			if( !is_array( $value ) ){
				$value = (array)$value;
			}
			$diff = array_diff( (array)$value, $selects );

			if( !empty( $diff ) ){

				$value_str = '';
				foreach( $value as $mv ){
					$value_str .= $mv . ' & ';
				}
				$value_str = trim( $value_str, ' & ' );
				$html .= '<span>：' . esc_html( $value_str ) . '</span>' . "\n";

			}else{
				$html .= '<select name="itemOption[' . $cartmeta_id . '][]" id="itemOption[' . $cartmeta_id . ']" class="iopt_select" multiple onKeyDown="if (event.keyCode == 13) {return false;}">' . "\n";
				if($essential == 1){
					if(  '#NONE#' == $value || NULL == $value ) 
						$selected = ' selected="selected"';
					else
						$selected = '';
					$html .= '<option value="#NONE#"' . $selected . '>' . __('Choose','usces') . '</option>' . "\n";
				}
				$i=0;
				
				$value_arr = maybe_unserialize($value);

				foreach($selects as $v) {
					$v = trim($v);
					$opval = urlencode($v);
					$val_str = isset($value_arr[$opval]) ? $value_arr[$opval] : '';
					if( $v == $val_str ) 
						$selected = ' selected="selected"';
					else
						$selected = '';
					$html .= '<option value="' . esc_attr($opval) . '"' . $selected . '>' . esc_attr($v) . '</option>' . "\n";
					$i++;
				}
				$html .= '</select>' . "\n";
			}
			break;
		case 2://Text
			$html .= '<input name="itemOption[' . $cartmeta_id . ']" type="text" id="itemOption[' . $cartmeta_id . ']" class="iopt_text" onKeyDown="if (event.keyCode == 13) {return false;}" value="' . esc_attr($value) . '" />' . "\n";
			break;
		case 3://Radio-button
			$selects = explode("\n", $field_data['value']);
			$value = maybe_unserialize($value);
			if( !is_array( $value ) ){
				$value = (array)$value;
			}
			$diff = array_diff( $value, $selects );

			if( !empty( $diff ) && !empty( $diff[0] ) ){

				$value_str = '';
				foreach( $value as $mv ){
					$value_str .= $mv . ' & ';
				}
				$value_str = trim( $value_str, ' & ' );
				$html .= '<span>：' . esc_html( $value_str ) . '</span>' . "\n";

			}else{
				$i=0;
				foreach($selects as $v) {
					$v = trim($v);
					$checked = '';
					foreach( $value as $ov ){
						if( $v == $ov ){ 
							$checked = ' checked="checked"';
							break;
						}
					}

					$html .= '<label for="itemOption[' . $cartmeta_id . ']'.$i.'" class="iopt_radio_label"><input name="itemOption[' . $cartmeta_id . ']" id="itemOption[' . $cartmeta_id . ']'.$i.'" class="iopt_radio" type="radio" value="' . urlencode($v) . '"' . $checked . ' onKeyDown="if (event.keyCode == 13) {return false;}">' . esc_html($v) . "</label>\n";
					$i++;
				}
			}
			break;
		case 4://Check-box
			$selects = explode("\n", $field_data['value']);
			$value = maybe_unserialize($value);
			if( !is_array( $value ) ){
				$value = (array)$value;
			}
			$diff = array_diff( (array)$value, $selects );

			if( !empty( $diff ) ){

				$value_str = '';
				foreach( $value as $mv ){
					$value_str .= $mv . ' & ';
				}
				$value_str = trim( $value_str, ' & ' );
				$html .= '<span>：' . esc_html( $value_str ) . '</span>' . "\n";

			}else{
				$i=0;
				foreach($selects as $v) {
					$v = trim($v);
					$opval = urlencode($v);
					$val_str = isset($value_arr[$opval]) ? $value_arr[$opval] : '';
					if( in_array($v, (array)$value) ) 
						$checked = ' checked="checked"';
					else
						$checked = '';
					$html .= '<label for="itemOption[' . $cartmeta_id . ']'.$i.'" class="iopt_checkbox_label"><input name="itemOption[' . $cartmeta_id . '][]" id="itemOption[' . $cartmeta_id . ']'.$i.'" class="iopt_checkbox" type="checkbox" value="' . urlencode($v) . '"' . $checked . ' onKeyDown="if (event.keyCode == 13) {return false;}">' . esc_html($v) . "</label>\n";
					$i++;
				}
			}
			break;
		case 5://Text-area
			$html .= '<textarea name="itemOption[' . $cartmeta_id . ']" id="itemOption[' . $cartmeta_id . ']" class="iopt_textarea">' . esc_attr($value) . '</textarea>' . "\n";
			break;
	}
	
	$html = apply_filters('usces_filter_get_itemOption', $html, $opt_value, $post_id );
	
	return $html;
}

function usces_update_ordercart_meta_value( $cartmeta_id, $value ){
	global $wpdb;
	
	if( !$cartmeta_id )
		return;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	$query = $wpdb->prepare( "
			UPDATE $ordercart_meta_table SET meta_value = %s WHERE cartmeta_id = %d", $value, $cartmeta_id );
	$res = $wpdb->query($query);
	return $res;
}
function usces_set_ordercart_meta_value( $type, $cart_id, $key, $value ){
	global $wpdb;
	
	if( !$type || !$cart_id || !$key || !$value )
		return;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	$query = $wpdb->prepare( "SELECT cartmeta_id FROM $ordercart_meta_table 
		WHERE cart_id = %d AND meta_type = %s AND meta_key = %s ", $cart_id, $type, $key );
	$res1 = $wpdb->get_var($query);

	if( $res1 ){
		$query = $wpdb->prepare( "UPDATE $ordercart_meta_table SET meta_value = %s 
			WHERE cart_id = %d AND meta_type = %s AND meta_key = %s", $value, $cart_id, $type, $key );
		$res2 = $wpdb->query($query);
	}else{
		$query = $wpdb->prepare( "INSERT INTO $ordercart_meta_table 
				( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, %s, %s, %s )", $cart_id, $type, $key, $value );
		$res2 = $wpdb->query( $query );
	}
	return $res2;
}

function usces_get_ordercart_meta( $type, $cart_id, $key = '' ){
	global $wpdb;
	
	if( !$cart_id )
		return;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	if( '' != $key ) {
		$query = $wpdb->prepare( "
			SELECT cartmeta_id, meta_key, meta_value 
			FROM $ordercart_meta_table 
			WHERE cart_id = %d AND meta_type = %s AND meta_key = %s 
			ORDER BY cartmeta_id 
			", $cart_id, $type, $key );
	} else {
		$query = $wpdb->prepare( "
			SELECT cartmeta_id, meta_key, meta_value 
			FROM $ordercart_meta_table 
			WHERE cart_id = %d AND meta_type = %s 
			ORDER BY cartmeta_id 
			", $cart_id, $type );
	}
	$res = $wpdb->get_results($query, ARRAY_A);
	return $res;
}

function usces_add_ordercart_meta_value( $type, $cart_id, $key, $value ){
	global $wpdb;

	if( !$type || !$cart_id || !$key || !$value )
		return;

	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	$query = $wpdb->prepare( "INSERT INTO $ordercart_meta_table 
			( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, %s, %s, %s )", $cart_id, $type, $key, $value );
	$res = $wpdb->query( $query );
	return $res;
}

function usces_get_ordercart_meta_value( $type, $cart_id, $key = '' ){
	global $wpdb;

	if( !$cart_id )
		return;

	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	if( '' != $key ) {
		$query = $wpdb->prepare( "
			SELECT meta_value 
			FROM $ordercart_meta_table 
			WHERE cart_id = %d AND meta_type = %s AND meta_key = %s 
			ORDER BY cartmeta_id 
			", $cart_id, $type, $key );
	} else {
		$query = $wpdb->prepare( "
			SELECT meta_value 
			FROM $ordercart_meta_table 
			WHERE cart_id = %d AND meta_type = %s 
			ORDER BY cartmeta_id
			", $cart_id, $type );
	}
	$res = $wpdb->get_var( $query );
	return $res;
}

function usces_get_ordercart_meta_by_id( $cartmeta_id ){
	global $wpdb;
	
	if( !$cartmeta_id )
		return;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	$query = $wpdb->prepare( "SELECT * FROM $ordercart_meta_table WHERE cartmeta_id = %d", $cartmeta_id );
	$res = $wpdb->get_row($query, ARRAY_A);
	return $res;
}

function usces_get_post_id_by_cartmeta_id( $cartmeta_id ){
	global $wpdb;
	
	if( !$cartmeta_id )
		return 0;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";
	$ordercart_table = $wpdb->prefix . "usces_ordercart";

	$query = $wpdb->prepare( "SELECT cart_id FROM $ordercart_meta_table WHERE cartmeta_id = %d", $cartmeta_id );
	$cart_id = $wpdb->get_var($query);
	if( !$cartmeta_id )
		return 0;

	$query = $wpdb->prepare( "SELECT post_id FROM $ordercart_table WHERE cart_id = %d", $cart_id );
	$post_id = $wpdb->get_var($query);

	return $post_id;
}

function usces_delete_ordercart_meta_by_id( $cartmeta_id ){
	global $wpdb;
	
	if( !$cartmeta_id )
		return;
	
	$ordercart_meta_table = $wpdb->prefix . "usces_ordercart_meta";

	$query = $wpdb->prepare( "DELETE FROM $ordercart_meta_table WHERE cartmeta_id = %d", $cartmeta_id );
	$res = $wpdb->query( $query );
	return $res;
}

function usces_make_advance_value( $advance, $cart_row ) {
	$value = '';
	$advance_value = array();

	foreach( $advance as $row ) {
		$advance_value[] = array( $row['meta_key'] => $row['meta_value'] );
	}
	$value = apply_filters( 'usces_filter_order_edit_form_row_advance_value', serialize($advance_value), $advance, $cart_row );
	return $value;
}

function usces_get_ordercart_row( $order_id, $cart = array() ){
	global $usces;

	if( empty( $cart ) ) {
		$cart = usces_get_ordercartdata( $order_id );
	}
	$reduced_taxrate = usces_is_reduced_taxrate( $order_id );
	if( $reduced_taxrate ) {
		$usces_tax = Welcart_Tax::get_instance();
	}

	ob_start();
	foreach( $cart as $i => $cart_row ) { 
		$ordercart_id = $cart_row['cart_id'];
		$post_id = $cart_row['post_id'];
		$sku = $cart_row['sku'];
		$sku_code = $cart_row['sku_code'];
		$quantity = $cart_row['quantity'];
		$options = usces_get_ordercart_meta( 'option', $ordercart_id );
		$advance = usces_get_ordercart_meta( 'advance', $ordercart_id );
		$applicable_taxrate = ( $reduced_taxrate ) ? $usces_tax->get_ordercart_applicable_taxrate( $ordercart_id, $post_id, $sku_code ) : '';
		if( 'reduced' == $applicable_taxrate ) {
			$reduced_taxrate_mark = '<span class="reduced_taxrate_mark">'.$usces_tax->reduced_taxrate_mark.'</span>';
		} else {
			$reduced_taxrate_mark = '';
		}
		$itemCode = $cart_row['item_code'];
		$itemName = $cart_row['item_name'];
		$cartItemName = $usces->getCartItemName_byOrder($cart_row);
		$skuPrice = $cart_row['price'];
		$stock = $usces->getItemZaiko($post_id, $sku_code);
		$stockid = $usces->getItemZaikoStatusId($post_id, $sku_code);
		$red = ( 1 < $stockid ) ? 'class="signal_red"' : '';
		$pictid = (int)$usces->get_mainpictid($itemCode);
		$materials = compact( 'i', 'cart_row', 'post_id', 'sku', 'sku_code', 'quantity', 'options', 'advance', 
			'itemCode', 'itemName', 'cartItemName', 'skuPrice', 'stock', 'red', 'pictid', 'order_id' );
		$advance_value = usces_make_advance_value( $advance, $cart_row );
		$cart_thumbnail = ( !empty($pictid) ) ? wp_get_attachment_image( $pictid, array(80, 80), true ) : usces_get_attachment_noimage( array(80, 80), $itemCode );
		$cart_thumbnail = apply_filters( 'usces_filter_cart_thumbnail', $cart_thumbnail, $post_id, $pictid, $i, $cart_row );
?>
	<tr>
		<td><?php echo $i + 1; ?></td>
		<td><?php echo $cart_thumbnail; ?></td>
		<td class="aleft"><?php echo apply_filters('usces_filter_admin_cart_item_name', esc_html($cartItemName), $materials ); ?><?php echo $reduced_taxrate_mark; ?><?php do_action('usces_admin_order_item_name', $order_id, $i); ?><?php usces_make_option_field( $materials, $cart ); ?></td>
		<td><input name="skuPrice[<?php echo $ordercart_id; ?>]" class="text price" type="text" value="<?php echo esc_attr( usces_crform($skuPrice, false, false, 'return', false) ); ?>" /><?php do_action('usces_admin_order_cart_after_price', $materials); ?></td>
		<td><input name="quant[<?php echo $ordercart_id; ?>]" class="text quantity" type="text" value="<?php echo esc_attr($cart_row['quantity']); ?>" /></td>
		<td><p id="sub_total[<?php echo $ordercart_id; ?>]" class="aright">&nbsp;</p><?php do_action('usces_admin_order_cart_after_sub_total', $materials); ?></td>
		<td <?php echo $red; ?>><?php echo esc_html($stock); ?></td>
		<td>
		<input name="postId[<?php echo $ordercart_id; ?>]" type="hidden" value="<?php echo esc_attr($post_id); ?>" />
		<input name="advance[<?php echo $ordercart_id; ?>]" type="hidden" value="<?php echo esc_attr($advance_value); ?>" />
<?php if( $reduced_taxrate ): ?>
		<input name="applicable_taxrate[<?php echo $ordercart_id; ?>]" type="hidden" value="<?php echo esc_attr( $applicable_taxrate ); ?>" />
<?php endif; ?>
		<input name="delButtonAdmin[<?php echo $ordercart_id; ?>]" class="button delCartButton" type="submit" value="<?php _e('Delete', 'usces'); ?>" />
		<?php do_action('usces_admin_order_cart_button', $order_id, $i); ?>
		</td>
	</tr>
<?php 
	}
	$row = ob_get_contents();
	ob_end_clean();
	
	return apply_filters( 'usces_filter_get_ordercart_row', $row, $order_id, $cart );
}

function usces_add_role(){
	
	if ( ! get_role( 'wc_author' ) ) {
		$capabilities = array(
			'moderate_comments' => 1, 
			'manage_categories' => 1, 
			'manage_links' => 1, 
			'upload_files' => 1, 
			'unfiltered_html' => 1, 
			'edit_posts' => 1, 
			'edit_others_posts' => 1, 
			'edit_published_posts' => 1, 
			'publish_posts' => 1, 
			'edit_pages' => 1, 
			'read' => 1, 
			'level_4' => 1, 
			'level_3' => 1, 
			'level_2' => 1, 
			'level_1' => 1, 
			'level_0' => 1, 
			'edit_others_pages' => 1, 
			'edit_published_pages' => 1, 
			'publish_pages' => 1, 
			'delete_pages' => 1, 
			'delete_others_pages' => 1, 
			'delete_published_pages' => 1, 
			'delete_posts' => 1, 
			'delete_others_posts' => 1, 
			'delete_published_posts' => 1, 
			'delete_private_posts' => 1, 
			'edit_private_posts' => 1, 
			'read_private_posts' => 1, 
			'delete_private_pages' => 1, 
			'edit_private_pages' => 1, 
			'read_private_pages' => 1
		);
		add_role( 'wc_author', '編集者（マネジメント権限無し）', $capabilities );
	}

	if ( ! get_role( 'wc_management' ) ) {
		$capabilities = array(
			'moderate_comments' => 1, 
			'manage_categories' => 1, 
			'manage_links' => 1, 
			'upload_files' => 1, 
			'unfiltered_html' => 1, 
			'edit_posts' => 1, 
			'edit_others_posts' => 1, 
			'edit_published_posts' => 1, 
			'publish_posts' => 1, 
			'edit_pages' => 1, 
			'read' => 1, 
			'level_5' => 1, 
			'level_4' => 1, 
			'level_3' => 1, 
			'level_2' => 1, 
			'level_1' => 1, 
			'level_0' => 1, 
			'edit_others_pages' => 1, 
			'edit_published_pages' => 1, 
			'publish_pages' => 1, 
			'delete_pages' => 1, 
			'delete_others_pages' => 1, 
			'delete_published_pages' => 1, 
			'delete_posts' => 1, 
			'delete_others_posts' => 1, 
			'delete_published_posts' => 1, 
			'delete_private_posts' => 1, 
			'edit_private_posts' => 1, 
			'read_private_posts' => 1, 
			'delete_private_pages' => 1, 
			'edit_private_pages' => 1, 
			'read_private_pages' => 1
		);
		add_role( 'wc_management', '編集者（設定権限無し）', $capabilities );
	}
		
	remove_role( 'wpsc_anonymous' );
}

function usces_get_admin_user_level(){
	global $current_user;
	wp_get_current_user();
	$levels = array();
	foreach($current_user->allcaps as $key => $value){
		$parts = explode( '_', $key );
		if( 'level' == $parts[0] )
			$levels[] = $parts[1];
	}
	if( empty($levels) ){
		return 0;
	}else{
		rsort($levels);
		return $levels[0];
	}
}

function usces_make_lost_key(){
	return uniqid ("wc" , true );
}

function usces_store_lostmail_key( $lost_mail, $lost_key ){
	global $wpdb;
	$date = substr(current_time('mysql'), 0, 10);
	$table = $wpdb->prefix . 'usces_access';
	$query = $wpdb->prepare("INSERT INTO {$table} (acc_key, acc_type, acc_value, acc_date)  VALUES (%s, %s, %s, %s)", $lost_mail, 'lostkey', $lost_key, $date );
	$res = $wpdb->query($query);
	return $res;
}

function usces_remove_lostmail_key( $lost_mail, $lost_key ){
	global $wpdb;
	$table = $wpdb->prefix . 'usces_access';
	$query = $wpdb->prepare("DELETE FROM {$table} WHERE acc_key = %s AND acc_type = %s AND acc_value = %s", $lost_mail, 'lostkey', $lost_key );
	$res = $wpdb->query($query);
	return $res;
}

function usces_check_lostkey($lost_mail, $lost_key){
	global $wpdb;
	$table = $wpdb->prefix . 'usces_access';
	$query = $wpdb->prepare("SELECT ID FROM {$table} WHERE acc_key = %s AND acc_type = %s AND acc_value = %s", $lost_mail, 'lostkey', $lost_key );
	$res = $wpdb->get_col($query);
	return $res;
}

function usces_clearup_lostkey(){
	global $wpdb;
	$table = $wpdb->prefix . 'usces_access';
	$date = date( 'Y-m-d', current_time('timestamp') );
	$query = $wpdb->prepare("DELETE FROM {$table} WHERE acc_type = %s AND acc_date < %s", 'lostkey', $date );
	$res = $wpdb->query($query);
	do_action( 'usces_clearup_lostkey', $res );
	return $res;
}

function usces_clearup_acting_data(){
	global $wpdb;
	$table = $wpdb->prefix . 'usces_access';
	$date = date( 'Y-m-d', (current_time('timestamp') - 86400 * 30) );
	$query = $wpdb->prepare("DELETE FROM {$table} WHERE acc_type = %s AND acc_date < %s", 'acting_data', $date );
	$res = $wpdb->query($query);
	do_action( 'usces_clearup_acting_data', $res );
	return $res;
}

function usces_clearup_acting_log() {
	global $usces, $wpdb;
	$table = $wpdb->prefix.'usces_log';
	$date = date( 'Y-m-d 00:00:00', (current_time('timestamp') - 86400 * 30) );
	$query = $wpdb->prepare("DELETE FROM {$table} WHERE ( log_type = %s OR log_type = %s ) AND datetime < %s", 'acting_data', 'acting_error', $date );
	$res = $wpdb->query($query);
	do_action( 'usces_clearup_acting_log', $res );
}

function usces_acting_key() {
	global $usces;
	$acting_key = usces_rand();
	return $acting_key;
}

function usces_change_order_receipt( $order_id, $flag ){
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'usces_order';
	
	if( 'receipted' == $flag ){
	
		$mquery = $wpdb->prepare("
		UPDATE $table_name SET order_status = 
		CASE 
			WHEN LOCATE('noreceipt', order_status) > 0 THEN REPLACE(order_status, 'noreceipt', 'receipted') 
			WHEN LOCATE('receipted', order_status) > 0 THEN order_status 
			ELSE CONCAT('receipted,', order_status ) 
		END 
		WHERE ID = %d", $order_id);
		$res = $wpdb->query( $mquery );
		
	}elseif( 'noreceipt' == $flag ){
	
		$mquery = $wpdb->prepare("
		UPDATE $table_name SET order_status = 
		CASE 
			WHEN LOCATE('receipted', order_status) > 0 THEN REPLACE(order_status, 'receipted', 'noreceipt') 
			WHEN LOCATE('noreceipt', order_status) > 0 THEN order_status 
			ELSE CONCAT('noreceipt,', order_status ) 
		END 
		WHERE ID = %d", $order_id);
		$res = $wpdb->query( $mquery );
		
	}
	return $res;
}

function usces_localized_name( $Familly_name, $Given_name, $out = '' ){
	global $usces_settings, $usces;
	
	$options = get_option('usces');
	$form = $options['system']['addressform'];
	if( $usces_settings['nameform'][$form] ){
		$res = $Given_name . ' ' . $Familly_name;
	}else{
		$res = $Familly_name . ' ' . $Given_name;
	}
	
	if($out == 'return'){
		return $res;
	}else{
		echo esc_html($res);
	}
}

function usces_get_hash( $str, $salt = NULL, $type = 0 ){
	//$hash = md5(trim($str));
	$hash_algos = ( defined('USCES_HASH_ALGOS') ) ? USCES_HASH_ALGOS : 'md5';
	$secret_key = ( defined('USCES_SECRET_KEY') ) ? USCES_SECRET_KEY : '';
	if( $secret_key ) {
		$hash = hash_hmac( $hash_algos, $str.$salt, $secret_key );
	} else {
		$hash = hash( $hash_algos, $str.$salt );
	}
	$args = compact( 'str', 'salt', 'type' );
	return apply_filters( 'usces_filter_get_hash', $hash, $args );
}

function usces_get_salt( $mem_key, $type = 0 ) {
	global $wpdb;

	$salt = '';
	if( defined('USCES_SALT') && USCES_SALT == '1' ) {
		if( $type == 1 ) {
			$salt = uniqid();
		} else {
			if( !empty($mem_key) ) {
				$member_table = usces_get_tablename( 'usces_member' );
				$member_meta_table = usces_get_tablename( 'usces_member_meta' );
				if( is_numeric($mem_key) ) {
					$salt = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $member_meta_table WHERE member_id = %d AND meta_key = %s", $mem_key, 'mem_salt' ) );
				} else {
					$salt = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $member_table AS `member` INNER JOIN $member_meta_table AS `meta` ON member.ID = meta.member_id AND meta.meta_key = %s WHERE mem_email = %s", 'mem_salt', $mem_key ) );
				}
			}
		}
	}
	$args = compact( 'mem_key', 'type' );
	return apply_filters( 'usces_filter_get_salt', $salt, $args );
}

function usces_get_member_reinforcement() {
	if( defined('WCEX_DLSELLER') ) {
		$dlseller_options = get_option( 'dlseller' );
		if( isset($dlseller_options['dlseller_member_reinforcement']) && 'on' == $dlseller_options['dlseller_member_reinforcement'] )
			return true;
	}
	return false;
}

function usces_have_shipped( $cart = NULL ) {
	global $usces;
	if( empty($cart) )
		$cart = $usces->cart->get_cart();

	$division = '';
	foreach( (array)$cart as $cart_row ) {
		if( 'shipped' == $usces->getItemDivision( $cart_row['post_id'] ) ) {
			$division = 'shipped';
			break;
		}
	}
	if( 'shipped' == $division )
		return true;
	else
		return false;
}

function usces_have_continue_charge( $cart = NULL ) {
	global $usces;
	if( empty($cart) )
		$cart = $usces->cart->get_cart();

	$charging_type = 'once';
	foreach( (array)$cart as $cart_row ) {
		if( 'continue' == $usces->getItemChargingType( $cart_row['post_id'] ) ) {
			$charging_type = 'continue';
			break;
		}
	}
	if( 'continue' == $charging_type )
		return true;
	else
		return false;
}

function usces_have_regular_order() {
	if( defined('WCEX_AUTO_DELIVERY') ) {
		return wcad_have_regular_order();
	}
	return false;
}

function usces_have_member_regular_order( $member_id ) {
	if( defined('WCEX_AUTO_DELIVERY') ) {
		return wcad_have_member_regular_order( $member_id );
	}
	return false;
}

function usces_have_member_continue_order( $member_id ) {
	if( defined('WCEX_DLSELLER') && function_exists('dlseller_have_member_continue_order') ) {
		return dlseller_have_member_continue_order( $member_id );
	}
	return false;
}

function usces_is_available_point(){
	global $usces;
	$res = true;
	
	
	if( defined('WCEX_DLSELLER') && function_exists('dlseller_have_continue_charge') ){
		if( dlseller_have_continue_charge() ){
			$res = false;
		}
	}
	
	return $res;
}

function usces_get_link_key( $results ) {
	global $usces;
	$linkkey = '(empty key)';
	if( isset($_REQUEST['X-S_TORIHIKI_NO']) ) {
		$linkkey = $_REQUEST['S_TORIHIKI_NO'];//remise
	} elseif( isset($_REQUEST['acting']) && ('jpayment_card' == $_REQUEST['acting'] || 'jpayment_conv' == $_REQUEST['acting'] || 'jpayment_bank' == $_REQUEST['acting']) && isset($_REQUEST['cod']) ) {
		$linkkey = $_REQUEST['cod'];//ROBOT PAYMENT (Cloud Payment,J-Payment)
	} elseif( isset($_REQUEST['txn_type']) && 'pro_hosted' == $_REQUEST['txn_type'] && isset($_REQUEST['custom']) ) {
		$linkkey = $_REQUEST['custom'];//PayPal Webpayment Plus
	} elseif( isset($_REQUEST['SID']) && isset($_REQUEST['FUKA']) ) {
		$linkkey = $_REQUEST['SID'];//MetapsPayment (paydesign,digitalcheck)
	} elseif( isset($_REQUEST['acting']) && ('mizuho_card' == $_REQUEST['acting'] || 'mizuho_conv' == $_REQUEST['acting']) && isset($_REQUEST['stran']) ) {
		$linkkey = $_REQUEST['stran'];//mizuho
	} elseif( isset($_REQUEST['SiteId']) && $usces->options['acting_settings']['anotherlane']['siteid'] == $_REQUEST['SiteId'] && isset($_REQUEST['TransactionId']) ) {
		$linkkey = $_REQUEST['TransactionId'];//AnotherLane
	} elseif( isset($_REQUEST['acting']) && ('veritrans_card' == $_REQUEST['acting'] || 'veritrans_conv' == $_REQUEST['acting']) && isset($_REQUEST['orderId']) ) {
		$linkkey = $_REQUEST['orderId'];//Veritrans
	} elseif( isset($_REQUEST['acting']) && 'telecom_card' == $_REQUEST['acting'] && isset($_REQUEST['acting_return']) && isset($_REQUEST['option']) ) {
		$linkkey = $_REQUEST['option'];//telecom credit
	} elseif( isset($_REQUEST['acting']) && 'epsilon' == $_REQUEST['acting'] && isset($_REQUEST['acting_return']) && isset($_REQUEST['order_number']) ) {
		$linkkey = $_REQUEST['order_number'];//epsilon
	}
	$linkkey = apply_filters( 'usces_filter_get_link_key', $linkkey, $results );
	return $linkkey;
}

function usces_erase_emoji( $str ) {
	if( $str ){
		$newstr = preg_replace('/[\xF0-\xF7][\x80-\xBF][\x80-\xBF][\x80-\xBF]/', '', $str);
		$str = apply_filters('usces_filter_erase_emoji', $newstr, $str );
	}
	return $str;
}

function usces_is_reduced_taxrate( $order_id = '' ) {
	global $usces;

	if( empty( $order_id ) ) {
		$reduced = $usces->is_reduced_taxrate();
	} else {
		$condition = usces_get_order_condition( $order_id );
		$reduced = ( isset( $condition['applicable_taxrate'] ) && 'reduced' == $condition['applicable_taxrate'] ) ? true : false;
	}
	return $reduced;
}

function usces_get_order_condition( $order_id ) {
	global $wpdb;
	$order_condition = $wpdb->get_var( $wpdb->prepare( "SELECT order_condition FROM {$wpdb->prefix}usces_order WHERE ID = %d", $order_id ) );
	return maybe_unserialize( $order_condition );
}

function usces_tax_rounding_off( $tax, $tax_method = '' ) {
	global $usces;

	if( empty( $tax_method ) ) {
		$tax_method = $usces->options['tax_method'];
	}
	$decimal = $usces->get_currency_decimal();
	$decipad = (int)str_pad( '1', $decimal + 1, '0', STR_PAD_RIGHT );
	switch( $tax_method ) {
	case 'cutting':
		if( 0 < $tax ) {
			$tax = floor( (float)$tax * $decipad ) / $decipad;
		}
		break;
	case 'bring':
		if( 0 < $tax ) {
			$tax = ceil( (float)$tax * $decipad ) / $decipad;
		}
		break;
	case 'rounding':
		if( 0 < $decimal ) {
			if( 0 < $tax ) {
				$tax = round( (float)$tax, $decimal );
			}
		} else {
			if( 0 < $tax ) {
				$tax = round( (float)$tax );
			}
		}
		break;
	}
	return $tax;
}

function usces_is_required_field( $name ){
	global $usces_essential_mark;

	if( empty( $usces_essential_mark[$name] ) ){
		return false;
	}else{
		return true;
	}
}

function usces_get_tablename( $target ){
	global $wpdb;

	$prefix = '';
	if( defined( 'WELCART_DB_PREFIX' ) ){
		$prefix = WELCART_DB_PREFIX;
	}
	if( !$prefix ){
		$prefix = $wpdb->prefix;
	}
	$tablename = $prefix . $target;

	return $tablename;
}
