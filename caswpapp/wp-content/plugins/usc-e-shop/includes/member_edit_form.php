<?php

if($member_action == 'new'){
	$page = 'usces_membernew';
	$oa = 'newpost';
	$ID = NULL;
	$member_metas = array();
	$data = array(
			'ID' =>'',
			'mem_email' => ( isset($_POST['member']['email']) ) ? $_POST['member']['email'] : '',
			'mem_pass' => ( isset($_POST['member']['password']) ) ? $_POST['member']['password'] : '',
			'mem_status' => ( isset($_POST['member']['status']) ) ? $_POST['member']['status'] : 0,
			'mem_cookie' => '',
			'mem_point' => ( isset($_POST['member']['point']) ) ? $_POST['member']['point'] : 0,
			'mem_name1' => ( isset($_POST['member']['name1']) ) ? $_POST['member']['name1'] : '',
			'mem_name2' => ( isset($_POST['member']['name2']) ) ? $_POST['member']['name2'] : '',
			'mem_name3' => ( isset($_POST['member']['name3']) ) ? $_POST['member']['name3'] : '',
			'mem_name4' => ( isset($_POST['member']['name4']) ) ? $_POST['member']['name4'] : '',
			'mem_zip' => ( isset($_POST['member']['zipcode']) ) ? $_POST['member']['zipcode'] : '',
			'mem_pref' => ( isset($_POST['member']['pref']) ) ? $_POST['member']['pref'] : '',
			'mem_address1' => ( isset($_POST['member']['address1']) ) ? $_POST['member']['address1'] : '',
			'mem_address2' => ( isset($_POST['member']['address2']) ) ? $_POST['member']['address2'] : '',
			'mem_address3' => ( isset($_POST['member']['address3']) ) ? $_POST['member']['address3'] : '',
			'mem_tel' => ( isset($_POST['member']['tel']) ) ? $_POST['member']['tel'] : '',
			'mem_fax' => ( isset($_POST['member']['fax']) ) ? $_POST['member']['fax'] : '',
			'mem_delivery_flag' => '',
			'mem_delivery' => '',
			'mem_registered' => '',
			'mem_nicename' => ''
			);

	$usces_member_history = array();

	$csmb_meta = usces_has_custom_field_meta('member');
	if(is_array($csmb_meta)) {
		$keys = array_keys($csmb_meta);
		foreach($keys as $key) {
			if( isset($_POST['custom_member'][$key]) ) {
				$csmb_meta[$key]['data'] = $_POST['custom_member'][$key];
			}
		}
	}
	$navibutton = '';
	
}else{
	$page = 'usces_memberlist';
	$oa = 'editpost';
	$ID = $_REQUEST['member_id'];
	$member_metas = $this->get_member_meta($ID);
	if( !$member_metas ) {
		$member_metas = array();
	}
	ksort($member_metas);
	global $wpdb;

	$tableName = usces_get_tablename( 'usces_member' );
	$query = $wpdb->prepare("SELECT * FROM $tableName WHERE ID = %d", $ID);
	$data = $wpdb->get_row( $query, ARRAY_A );

	$usces_member_history = $this->get_member_history($ID);
	if( !$usces_member_history ) {
		$usces_member_history = array();
	}
	$csmb_meta = usces_has_custom_field_meta('member');
	if(is_array($csmb_meta)) {
		$keys = array_keys($csmb_meta);
		foreach($keys as $key) {
			$csmb_meta[$key]['data'] = maybe_unserialize($this->get_member_meta_value('csmb_'.$key, $ID));
		}
	}
	
	
	$exopt = get_option('usces_ex');
	if( isset($exopt['system']['datalistup']['memberlist_flag']) && $exopt['system']['datalistup']['memberlist_flag'] ){
		$prev_uri = $this->get_prev_page_uri( 'member', $ID );
		$next_uri = $this->get_next_page_uri( 'member', $ID );
		$navibutton = '';
		if( !empty($prev_uri) ){
			$navibutton .= '<a href="' . $prev_uri . '" class="prev-page"><span class="dashicons dashicons-arrow-left-alt2"></span>' . __('to prev page', 'usces') . '</a>';
		}
		$navibutton .= '<a href="' . admin_url('admin.php?page=usces_memberlist&returnList=1') . '" class="back-list"><span class="dashicons dashicons-list-view"></span>' . __('to member list', 'usces') . '</a>';
		if( !empty($next_uri) ){
			$navibutton .= '<a href="' . $next_uri . '" class="next-page">' . __('to next page', 'usces') . '<span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		}
	}else{
		$navibutton = '<a href="' . admin_url('admin.php?page=usces_memberlist&returnList=1') . '" class="back-list"><span class="dashicons dashicons-list-view"></span>' . __('to member list', 'usces') . '</a>';
	}
	
}

if( usces_is_member_system() ){
	$colspan = 8;
}else{
	$colspan = 6;
}

$mem_registered = ( !empty($data['mem_registered']) ) ? sprintf(__('%2$s %3$s, %1$s', 'usces'),substr($data['mem_registered'],0,4),substr($data['mem_registered'],5,2),substr($data['mem_registered'],8,2)) : '';

$curent_url = urlencode(esc_url(USCES_ADMIN_URL . '?' . $_SERVER['QUERY_STRING']));
?>
<script type="text/javascript">
function addComma(str)
{
	cnt = 0;
	n   = "";
	for (i=str.length-1; i>=0; i--)
	{
		n = str.charAt(i) + n;
		cnt++;
		if (((cnt % 3) == 0) && (i != 0)) n = ","+n;
	}
	return n;
};
</script>
<div class="wrap">
<div class="usces_admin">
<form action="<?php echo USCES_ADMIN_URL.'?page='.$page.'&member_action='.$oa; ?>" method="post" name="editpost">

<?php if( $member_action == 'new' ) : ?>
<h1>Welcart Management <?php _e('New Membership Registration','usces'); ?></h1>
<?php else : ?>
<h1>Welcart Management <?php _e('Edit membership data','usces'); ?></h1>
<?php endif;?>

<p class="version_info">Version <?php echo USCES_VERSION; ?></p>
<?php usces_admin_action_status(); ?>
<?php if( $navibutton ) echo '<div class="edit_pagenav">' . $navibutton . '</div>'; ?>
<div class="usces_tablenav usces_tablenav_top">
<div class="ordernavi"><input name="upButton" class="button button-primary" type="submit" value="<?php _e('change decision', 'usces'); ?>" /><?php _e("When you change amount, please click 'Edit' before you finish your process.", 'usces'); ?></div>
</div>

<div class="info_head">
<table class="mem_wrap">
<tr>
<td class="label"><?php _e('membership number', 'usces'); ?></td><td class="col1"><div class="rod large short"><?php echo esc_html($data['ID']); ?></div></td>
<td colspan="2" rowspan="5" class="mem_col2">
<table class="mem_info">
		<tr>
			<td class="label">e-mail</td>
			<td><input name="member[email]" type="text" class="text long" value="<?php echo esc_attr($data['mem_email']); ?>" /></td>
		</tr>
<?php if( $member_action == 'new' ) : ?>
		<tr>
			<td class="label"><?php _e('password', 'usces'); ?></td>
			<td><input name="member[password]" type="text" class="text" value="<?php echo esc_attr($data['mem_pass']); ?>" autocomplete="off" /></td>
		</tr>
<?php endif; ?>
<?php echo uesces_get_admin_addressform( 'member', $data, $csmb_meta ); ?>
</table>
</td>
<td colspan="2" rowspan="5" class="mem_col3">
<table class="mem_info">
<?php do_action( 'usces_action_admin_member_info', $data, $member_metas, $usces_member_history ); ?>
</table>


</td>
	</tr>
<tr>
<td class="label"><?php _e('Rank', 'usces'); ?></td><td class="col1"><select name="member[status]">
<?php foreach ((array)$this->member_status as $rk => $rv) :
		$selected = ($rk == $data['mem_status']) ? ' selected="selected"' : ''; ?>
    <option value="<?php echo esc_attr($rk); ?>"<?php echo $selected; ?>><?php echo esc_html($rv); ?></option>
<?php endforeach; ?>
</select></td>
</tr>
<?php if( usces_is_membersystem_point() ) : ?>
<tr>
<td class="label"><?php _e('current point', 'usces'); ?></td><td class="col1"><input name="member[point]" type="text" class="text right short num" value="<?php echo $data['mem_point']; ?>" /></td>
</tr>
<?php endif; ?>
<tr>
<td class="label"><?php _e('Strated date', 'usces'); ?></td><td class="col1"><div class="rod shortm"><?php echo esc_html($mem_registered); ?></div></td>
</tr>
<tr>
<td colspan="2"><?php do_action( 'usces_action_member_edit_form_left_blank', $ID ); ?></td>
</tr>
</table>
<?php if( !usces_is_membersystem_point() ) : ?>
<input name="member[point]" type="hidden" value="<?php echo $data['mem_point']; ?>" />
<?php endif; ?>
</div>
<div id="member_history">
<table>
<?php if ( 0 == count( $usces_member_history ) ) : ?>
<tr>
<td><?php _e('There is no purchase history for this moment.', 'usces'); ?></td>
</tr>
<?php endif; ?>


<?php 
$colspan = ( usces_is_tax_display() ) ? 10 : 9;
ob_start();
foreach ( (array)$usces_member_history as $umhs ) :
	$cart = $umhs['cart'];
	$order_id = $umhs['ID'];
	$total_price = $this->get_total_price($cart)-$umhs['usedpoint']+$umhs['discount']+$umhs['shipping_charge']+$umhs['cod_fee']+$umhs['tax'];
	if( $total_price < 0 ) $total_price = 0;
?>
<tr>
<th class="historyrow"><?php _e('Purchase date', 'usces'); ?></th>
<th class="historyrow"><?php _e('Order number', 'usces'); ?></th>
<th class="historyrow"><?php _e('Processing status', 'usces'); ?></th>
<th class="historyrow"><?php _e('Purchase price', 'usces'); ?></th>
<th class="historyrow"><?php echo apply_filters( 'usces_member_discount_label', __('Special Price', 'usces'), $umhs['ID'] ); ?></th>
<?php if( usces_is_tax_display() && 'products' == usces_get_tax_target() ) : ?>
<th class="historyrow"><?php usces_tax_label(); ?></th>
<?php endif; ?>
<?php if( usces_is_membersystem_point() && 0 == usces_point_coverage() ) : ?>
<th class="historyrow"><?php _e('Used points','usces'); ?></th>
<?php endif; ?>
<th class="historyrow"><?php _e('Shipping', 'usces'); ?></th>
<th class="historyrow"><?php echo apply_filters( 'usces_filter_member_history_cod_label', __('C.O.D', 'usces'), $umhs['ID'] ); ?></th>
<?php if( usces_is_tax_display() && 'all' == usces_get_tax_target() ) : ?>
<th class="historyrow"><?php usces_tax_label(); ?></th>
<?php endif; ?>
<?php if( usces_is_membersystem_point() && 1 == usces_point_coverage() ) : ?>
<th class="historyrow"><?php _e('Used points','usces'); ?></th>
<?php endif; ?>
<?php if( usces_is_membersystem_point() ) : ?>
<th class="historyrow"><?php _e('Acquired points', 'usces'); ?></th>
<?php endif; ?>
</tr>
<tr>
<td><?php echo $umhs['date']; ?></td>
<td><a href="<?php echo USCES_ADMIN_URL; ?>?page=usces_orderlist&order_action=edit&order_id=<?php echo $order_id; ?>&usces_referer=<?php echo $curent_url; ?>"><?php echo usces_get_deco_order_id( $order_id ); ?></a></td>
<?php 
$management_status = apply_filters( 'usces_filter_management_status', get_option( 'usces_management_status' ) );
$value = $umhs['order_status'];
$p_status = '';
if( $this->is_status('duringorder', $value) ){
	$p_status = esc_html($management_status['duringorder']);
}elseif( $this->is_status('cancel', $value) ){
	$p_status = esc_html($management_status['cancel']);
}elseif( $this->is_status('completion', $value) ){
	$p_status = esc_html($management_status['completion']);
}else{
	$p_status = esc_html(__('new order', 'usces'));
}
$p_status = apply_filters( 'usces_filter_orderlist_process_status', $p_status, $value, $management_status, $order_id );
 ?>
<td><?php echo $p_status; ?></td>
<td class="rightnum"><?php usces_crform( $total_price, true, false ); ?></td>
<td class="rightnum"><?php usces_crform( $umhs['discount'], true, false ); ?></td>
<?php if( usces_is_tax_display() && 'products' == usces_get_tax_target() ) : ?>
<td class="rightnum"><?php usces_tax($umhs); ?></td>
<?php endif; ?>
<?php if( usces_is_membersystem_point() && 0 == usces_point_coverage() ) : ?>
<td class="rightnum"><?php echo number_format($umhs['usedpoint']); ?></td>
<?php endif; ?>
<td class="rightnum"><?php usces_crform( $umhs['shipping_charge'], true, false ); ?></td>
<td class="rightnum"><?php usces_crform( $umhs['cod_fee'], true, false ); ?></td>
<?php if( usces_is_tax_display() && 'all' == usces_get_tax_target() ) : ?>
<td class="rightnum"><?php usces_tax($umhs); ?></td>
<?php endif; ?>
<?php if( usces_is_membersystem_point() && 1 == usces_point_coverage() ) : ?>
<td class="rightnum"><?php echo number_format($umhs['usedpoint']); ?></td>
<?php endif; ?>
<?php if( usces_is_membersystem_point() ) : ?>
<td class="rightnum"><?php echo number_format($umhs['getpoint']); ?></td>
<?php endif; ?>
</tr>
<tr>
<td class="retail" colspan="<?php echo $colspan; ?>">
	<table id="retail_table">
	<tr>
	<th scope="row" class="num"><?php echo __('No.','usces'); ?></th>
	<th class="thumbnail">&nbsp;</th>
	<th><?php _e('Items','usces'); ?></th>
	<th class="price "><?php _e('Unit price','usces'); ?>(<?php usces_crcode(); ?>)</th>
	<th class="quantity"><?php _e('Quantity','usces'); ?></th>
	<th class="subtotal"><?php _e('Amount','usces'); ?>(<?php usces_crcode(); ?>)</th>
	</tr>
	<?php
	$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
	for($i=0; $i<$cart_count; $i++) { 
		$cart_row = $cart[$i];
		$ordercart_id = $cart_row['cart_id'];
		$post_id = $cart_row['post_id'];
		$sku = urldecode($cart_row['sku']);
		$quantity = $cart_row['quantity'];
		$options = $cart_row['options'];
		$advance = usces_get_ordercart_meta( 'advance', $ordercart_id );
		$itemCode = $cart_row['item_code'];
		$itemName = $cart_row['item_name'];
		$cartItemName = $this->getCartItemName_byOrder($cart_row);
		$skuPrice = $cart_row['price'];
		$pictid = (int)$this->get_mainpictid($itemCode);
		$optstr =  '';
		foreach((array)$options as $key => $value){
			if( !empty($key) ) {
				$key = urldecode($key);
				$value = maybe_unserialize($value);
				if(is_array($value)) {
					$c = '';
					$optstr .= esc_html($key) . ' : '; 
					foreach($value as $v) {
						$optstr .= $c.nl2br(esc_html(urldecode($v)));
						$c = ', ';
					}
					$optstr .= "<br />\n"; 
				} else {
					$optstr .= esc_html($key) . ' : ' . nl2br(esc_html(urldecode($value))) . "<br />\n"; 
				}
			}
		}
		$materials = compact( 'i', 'cart_row', 'post_id', 'sku', 'quantity', 'options', 'advance', 
						'itemCode', 'itemName', 'cartItemName', 'skuPrice', 'pictid', 'order_id' );
		$optstr = apply_filters( 'usces_filter_member_edit_form_row', $optstr, $cart, $materials );

		$cart_item_name = apply_filters('usces_filter_admin_cart_item_name', esc_html($cartItemName), $materials ) . '<br />' . $optstr;
		$cart_item_name = apply_filters( 'usces_filter_admin_history_cart_item_name', $cart_item_name, $cartItemName, $optstr, $cart_row, $i );

		$cart_thumbnail = ( !empty($pictid) ) ? wp_get_attachment_image( $pictid, array(60, 60), true ) : usces_get_attachment_noimage( array(60, 60), $itemCode );
		$cart_thumbnail = apply_filters( 'usces_filter_cart_thumbnail', $cart_thumbnail, $post_id, $pictid, $i, $cart_row );
	?>
	<tr>
	<td><?php echo $i + 1; ?></td>
	<td><?php echo $cart_thumbnail; ?></td>
	<td class="aleft"><?php echo $cart_item_name; ?></td>
	<td class="rightnum"><?php usces_crform( $skuPrice, true, false ); ?></td>
	<td class="rightnum"><?php echo number_format($cart_row['quantity']); ?></td>
	<td class="rightnum"><?php usces_crform( $skuPrice * $cart_row['quantity'], true, false ); ?></td>
	</tr>
	<?php 
	}
	?>
	<?php do_action( 'usces_action_admin_member_history_row', $ID, $umhs ); ?>
	</table>
</td>
</tr>
<?php
endforeach;
$admin_history = ob_get_contents();
ob_end_clean();
echo apply_filters( 'usces_filter_admin_history', $admin_history, $usces_member_history );
?>

</table>
</div>
<input name="member_action" type="hidden" value="<?php echo $oa; ?>" />
<input name="member_id" id="member_id" type="hidden" value="<?php echo $data['ID']; ?>" />


<div id="mailSendAlert" title="">
	<div id="order-response"></div>
	<fieldset>
	</fieldset>
</div>
<?php wp_nonce_field( 'post_member', 'wc_nonce' ); ?>
</form>

</div><!--usces_admin-->
</div><!--wrap-->
