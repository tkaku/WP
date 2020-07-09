<?php
/*----------------------------------------------------------------------------
e-SCOTT Smart Main Class
Version: 1.0.0
Author: Collne Inc.
------------------------------------------------------------------------------*/
class ESCOTT_MAIN
{
	protected $paymod_id;			// ex) 'escott'
	protected $acting_name;			// ex) 'e-SCOTT'
	protected $acting_formal_name;	// ex) 'e-SCOTT Smart ソニーペイメントサービス'

	protected $acting_card;			// ex) 'escott_card'
	protected $acting_conv;			// ex) 'escott_conv'
	protected $acting_atodene;		// ex) 'escott_atodene'
	protected $acting_applepay;
	protected $acting_unionpay;

	protected $acting_flg_card;		// ex) 'acting_escott_card'
	protected $acting_flg_conv;		// ex) 'acting_escott_conv'
	protected $acting_flg_atodene;	// ex) 'acting_escott_atodene'
	protected $acting_flg_applepay;
	protected $acting_flg_unionpay;

	protected $pay_method;			// ex) array( 'acting_escott_card', 'acting_escott_conv' )
	protected $unavailable_method;	// ex) array( 'acting_zeus_card', 'acting_zeus_conv' ) ※併用不可決済
	protected $merchantfree3;		// ex) 'wc1collne'
	protected $quick_key_pre;		// ex) 'escott'

	protected $key_aes;
	protected $key_iv;

	protected $error_mes;

	public function __construct( $mode ) {

		$this->paymod_id = $mode;

		$this->key_aes = 'HgmhZ94rN799CD3F';
		$this->key_iv = 'gNqc4zwhNLCSC5cv';

		if( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_action( 'usces_after_cart_instant', array( $this, 'acting_transaction' ), 9 );
			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
			add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement' ), 10, 3 );
			add_action( 'usces_action_revival_order_data', array( $this, 'revival_orderdata' ), 10, 3 );

			if( is_admin() ) {
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
				add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
				add_action( 'usces_action_admin_member_info', array( $this, 'admin_member_info' ), 10, 3 );
				add_action( 'usces_action_post_update_memberdata', array( $this, 'admin_update_memberdata' ), 10, 2 );

			} else {
				add_filter( 'usces_filter_payment_detail', array( $this, 'payment_detail' ), 10, 2 );
				add_filter( 'usces_filter_payments_str', array( $this, 'payments_str' ), 10, 2 );
				add_filter( 'usces_filter_payments_arr', array( $this, 'payments_arr' ), 10, 2 );
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform' ), 10, 5 );
				add_action( 'usces_action_confirm_page_point_inform', array( $this, 'e_point_inform' ), 10, 5 );
				add_filter( 'usces_filter_confirm_point_inform', array( $this, 'point_inform' ), 10, 5 );
				if( defined( 'WCEX_COUPON' ) ) {
					add_filter( 'wccp_filter_coupon_inform', array( $this, 'point_inform' ), 10, 5 );
				}
				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 10, 2 );
				add_filter( 'usces_filter_check_acting_return_results', array( $this, 'acting_return' ) );
				add_filter( 'usces_filter_check_acting_return_duplicate', array( $this, 'check_acting_return_duplicate' ), 10, 2 );
				add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
				add_action( 'usces_post_reg_orderdata', array( $this, 'post_register_orderdata' ), 10, 2 );
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_message' ) );
				add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'order_mail_payment' ), 10, 6 );
			}
		}

		if( $this->is_validity_acting( 'card' ) ) {
			add_action( 'init', array( $this, 'done_3dsecure' ), 10 );
			add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
			add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ), 9 );
			add_filter( 'usces_filter_delivery_check', array( $this, 'delivery_check' ), 15 );
			add_filter( 'usces_filter_delivery_secure_form', array( $this, 'delivery_secure_form' ), 10, 2 );
			add_filter( 'usces_filter_delivery_secure_form_loop', array( $this, 'delivery_secure_form_loop' ), 10, 2 );
			add_filter( 'usces_filter_delete_member_check', array( $this, 'delete_member_check' ), 10, 2 );
			add_action( 'wp_print_styles', array( $this, 'print_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'usces_filter_uscesL10n', array( $this, 'set_uscesL10n' ), 12, 2 );
			add_action( 'usces_front_ajax', array( $this, 'front_ajax' ) );
		}

		if( $this->is_validity_acting( 'conv' ) || $this->is_validity_acting( 'atodene' ) ) {
			add_filter( 'usces_filter_cod_label', array( $this, 'set_fee_label' ) );
			add_filter( 'usces_filter_member_history_cod_label', array( $this, 'set_member_history_fee_label' ), 10, 2 );
			if( is_admin() ) {
			} else {
				add_filter( 'usces_fiter_the_payment_method', array( $this, 'payment_method' ) );
				add_filter( 'usces_filter_set_cart_fees_cod', array( $this, 'add_fee' ), 10, 7 );
				add_filter( 'usces_filter_delivery_check', array( $this, 'check_fee_limit' ) );
				add_filter( 'usces_filter_point_check_last', array( $this, 'check_fee_limit' ) );
			}
		}
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {

	}

	/**
	 * 決済有効判定
	 * 引数が指定されたとき、支払方法で使用している場合に「有効」とする
	 * @param  ($type)
	 * @return boolean
	 */
	public function is_validity_acting( $type = '' ) {

		$acting_opts = $this->get_acting_settings();
		if( empty( $acting_opts ) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method = false;

		switch( $type ) {
		case 'card':
			foreach( $payment_method as $payment ) {
				if( $this->acting_flg_card == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_card() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'conv':
			foreach( $payment_method as $payment ) {
				if( $this->acting_flg_conv == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_conv() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'atodene':
			foreach( $payment_method as $payment ) {
				if( $this->acting_flg_atodene == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_atodene() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'applepay':
			foreach( $payment_method as $payment ) {
				if( $this->acting_flg_applepay == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_applepay() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'unionpay':
			foreach( $payment_method as $payment ) {
				if( $this->acting_flg_unionpay == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_unionpay() ) {
				return true;
			} else {
				return false;
			}
			break;

		default:
			if( 'on' == $acting_opts['activate'] ) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * カード決済有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_card() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['card_activate'] ) && ( 'on' == $acting_opts['card_activate'] || 'link' == $acting_opts['card_activate'] || 'token' == $acting_opts['card_activate'] ) ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * オンライン収納代行有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_conv() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['conv_activate'] ) && 'on' == $acting_opts['conv_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * 後払い決済有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_atodene() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['atodene_activate'] ) && 'on' == $acting_opts['atodene_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * Apple Pay 有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_applepay() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['applepay_activate'] ) && 'on' == $acting_opts['applepay_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * 銀聯有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_unionpay() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['unionpay_activate'] ) && 'on' == $acting_opts['unionpay_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * クレジット決済設定画面タブ
	 * @fook   usces_action_settlement_tab_title
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_title() {

		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_'.$this->paymod_id.'">'.__( $this->acting_name, 'usces' ).'</a></li>';
		}
	}

	/**
	 * 入金通知処理
	 * @fook   usces_after_cart_instant
	 * @param  -
	 * @return -
	 */
	public function acting_transaction() {
		global $usces;

		if( isset( $_REQUEST['MerchantFree1'] ) && isset( $_REQUEST['MerchantId'] ) && isset( $_REQUEST['TransactionId'] ) && isset( $_REQUEST['RecvNum'] ) && isset( $_REQUEST['NyukinDate'] ) && 
			( isset( $_REQUEST['MerchantFree2'] ) && $this->acting_flg_conv == $_REQUEST['MerchantFree2'] ) ) {
			$acting_opts = $this->get_acting_settings();
			if( $acting_opts['merchant_id'] == $_REQUEST['MerchantId'] ) {
				$response_data = $_REQUEST;
				$order_id = usces_get_order_id_by_trans_id( $response_data['MerchantFree1'] );
				if( !empty( $order_id ) ) {

					//オーダーステータス変更
					usces_change_order_receipt( $order_id, 'receipted' );
					//ポイント付与
					usces_action_acting_getpoint( $order_id );

					$response_data['OperateId'] = 'receipted';
					$order_meta = usces_unserialize( $usces->get_order_meta_value( $response_data['MerchantFree2'], $order_id ) );
					$meta_value = array_merge( $order_meta, $response_data );
					$usces->set_order_meta_value( $response_data['MerchantFree2'], usces_serialize( $meta_value ), $order_id );
					usces_log( '['.$this->acting_name.'] conv receipted : '.print_r( $response_data, true ), 'acting_transaction.log' );
				} else {
					usces_log( '['.$this->acting_name.'] conv receipted order_id error : '.print_r( $response_data, true ), 'acting_transaction.log' );
				}
			}
			header( "HTTP/1.0 200 OK" );
			die();
		}
	}

	/**
	 * 管理画面送信メール
	 * @fook   usces_filter_order_confirm_mail_payment
	 * @param  $msg_payment $order_id $payment $cart $data
	 * @return string $msg_payment
	 */
	public function order_confirm_mail_payment( $msg_payment, $order_id, $payment, $cart, $data ) {
		global $usces;

		if( $this->acting_flg_card == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( 1 === (int)$acting_opts['howtopay'] ) {

			} else {
				$acting_data = usces_unserialize( $usces->get_order_meta_value( $this->acting_flg_card, $order_id ) );
				if( isset( $acting_data['PayType'] ) ) {
					$msg_payment = __( '** Payment method **', 'usces' )."\r\n";
					$msg_payment .= usces_mail_line( 1, $data['order_email'] );//********************
					$msg_payment .= $payment['name'];
					switch( $acting_data['PayType'] ) {
					case '01':
						$msg_payment .= ' ('.__( 'One time payment', 'usces' ).')';
						break;
					case '02':
					case '03':
					case '05':
					case '06':
					case '10':
					case '12':
					case '15':
					case '18':
					case '20':
					case '24':
						$times = (int)$acting_data['PayType'];
						$msg_payment .= ' ('.$times.__( '-time payment', 'usces' ).')';
						break;
					case '80':
						$msg_payment .= ' ('.__( 'Bonus lump-sum payment', 'usces' ).')';
						break;
					case '88':
						$msg_payment .= ' ('.__( 'Libor Funding pay', 'usces' ).')';
						break;
					}
					$msg_payment .= "\r\n\r\n";
				}
			}

		} elseif( $this->acting_flg_conv == $payment['settlement'] && ( 'orderConfirmMail' == $_POST['mode'] || 'changeConfirmMail' == $_POST['mode'] ) ) {
			$acting_opts = $this->get_acting_settings();
			$url = $usces->get_order_meta_value( $this->paymod_id.'_conv_url', $order_id );
			$msg_payment .= sprintf( __( "Payment expiration date is %s days.", 'usces' ), $acting_opts['conv_limit'] )."\r\n";
			$msg_payment .= __( "If payment has not yet been completed, please payment procedure from the following URL.", 'usces' )."\r\n\r\n";
			$msg_payment .= __( "[Payment URL]", 'usces' )."\r\n";
			$msg_payment .= $url."\r\n";
		}
		return $msg_payment;
	}

	/**
	 * ポイント即時付与
	 * @fook   usces_filter_is_complete_settlement
	 * @param  $complete $payment_name $status
	 * @return boolean $complete
	 */
	public function is_complete_settlement( $complete, $payment_name, $status ) {

		$payment = usces_get_payments_by_name( $payment_name );
		if( $this->acting_flg_card == $payment['settlement'] ) {
			$complete = true;
		}
		return $complete;
	}

	/**
	 * 受注データ復旧処理
	 * @fook   usces_action_revival_order_data
	 * @param  $order_id $log_key $acting_flg
	 * @return -
	 */
	public function revival_orderdata( $order_id, $log_key, $acting_flg ) {
		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		$usces->set_order_meta_value( 'trans_id', $log_key, $order_id );
		$usces->set_order_meta_value( 'wc_trans_id', $log_key, $order_id );

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$order_meta = array();
		$order_meta['acting'] = substr( $acting_flg, 7 );
		$order_meta['MerchantFree1'] = $log_key;
		$total_full_price = $order_data['order_item_total_price'] - $order_data['order_usedpoint'] + $order_data['order_discount'] + $order_data['order_shipping_charge'] + $order_data['order_cod_fee'] + $order_data['order_tax'];
		if( $total_full_price < 0 ) $total_full_price = 0;
		$order_meta['Amount'] = $total_full_price;
		if( $this->acting_flg_conv == $acting_flg ) {
			$acting_opts = $this->get_acting_settings();
			$paylimit = date_i18n( 'Ymd', strtotime( $order_data['order_date'] )+( 86400*$acting_opts['conv_limit'] ) ).'2359';
			$order_meta['PayLimit'] = $paylimit;
		}
		$usces->set_order_meta_value( $acting_flg, usces_serialize( $order_meta ), $order_id );

		if( $this->acting_flg_conv == $acting_flg ) {
			$usces->set_order_meta_value( $log_key, $acting_flg, $order_id );
		}
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * @fook   usces_filter_settle_info_field_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_keys( $keys ) {

		$field_keys = array_merge( $keys, array( 'MerchantFree1', 'ResponseCd', 'PayType', 'KessaiNumber', 'NyukinDate', 'CvsCd', 'PayLimit', 'SecureResultCode' ) );
		return $field_keys;
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * @fook   usces_filter_settle_info_field_value
	 * @param  $value $key $acting
	 * @return string $value
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {

		if( $this->acting_card != $acting && $this->acting_conv != $acting ) {
			return $value;
		}

		switch( $key ) {
		case 'CvsCd':
			$value = $this->get_cvs_name( $value );
			break;

		case 'PayType':
			switch( $value ) {
			case '01':
				$value = __( 'One time payment', 'usces' );
				break;
			case '02':
			case '03':
			case '05':
			case '06':
			case '10':
			case '12':
			case '15':
			case '18':
			case '20':
			case '24':
				$times = (int)$value;
				$value = $times.__( '-time payment', 'usces' );
				break;
			case '80':
				$value = __( 'Bonus lump-sum payment', 'usces' );
				break;
			case '88':
				$value = __( 'Libor Funding pay', 'usces' );
				break;
			}
		}
		return $value;
	}

	/**
	 * 会員データ編集画面 クイック決済情報
	 * @fook   usces_action_admin_member_info
	 * @param  $data $member_metas $usces_member_history
	 * @return -
	 * @echo   html
	 */
	public function admin_member_info( $data, $member_metas, $usces_member_history ) {

		if( 0 < count( $member_metas ) ):
			$member_id = $data['ID'];
			$KaiinId = $this->get_quick_kaiin_id( $member_id );
			$KaiinPass = $this->get_quick_pass( $member_id );
			if( !empty( $KaiinId ) && !empty( $KaiinPass ) ) :

				//e-SCOTT 会員照会
				$response_member = $this->escott_member_reference( $member_id );
				if( 'OK' == $response_member['ResponseCd'] ):
					$cardlast4 = substr( $response_member['CardNo'], -4 );
					$expyy = substr( date_i18n( 'Y', current_time( 'timestamp' ) ), 0, 2 ).substr( $response_member['CardExp'], 0, 2 );
					$expmm = substr( $response_member['CardExp'], 2, 2 );
?>
		<tr>
			<td class="label"><?php _e( 'Lower 4 digits', 'usces' ); ?></td>
			<td><div class="rod_left shortm"><?php echo $cardlast4; ?></div></td>
		</tr>
		<tr>
			<td class="label"><?php _e( 'Expiration date', 'usces' ); ?></td>
			<td><div class="rod_left shortm"><?php echo $expyy.'/'.$expmm; ?></div></td>
		</tr>
		<tr>
			<td class="label"><?php _e( 'Quick payment', 'usces' ); ?></td>
			<td><div class="rod_left shortm"><?php _e( 'Registered', 'usces' ); ?></div></td>
		</tr>
<?php				if( !usces_have_member_continue_order( $data['ID'] ) && !usces_have_member_regular_order( $data['ID'] ) ): ?>
		<tr>
			<td class="label"><input type="checkbox" name="escott_quickpay" id="escott-quickpay-release" value="release"></td>
			<td><label for="escott-quickpay-release"><?php _e( 'Release quick payment', 'usces' ); ?></label></td>
		</tr>
<?php				endif;
				else:
?>
		</tr>
		<tr>
			<td class="label"><?php _e( 'Quick payment', 'usces' ); ?></td>
			<td><div class="rod_left shortm"><?php _e( 'Unregistered', 'usces' ); ?></div></td>
		</tr>
		<tr>
			<td class="label"><input type="checkbox" name="escott_quickpay" id="escott-quickpay-release" value="forced_release"></td>
			<td><label for="escott-quickpay-release"><?php _e( 'Release unregistered quick payment', 'usces' ); ?></label></td>
		</tr>
<?php			endif;
			endif;
		endif;
	}

	/**
	 * 会員データ編集画面 クイック決済解除
	 * @fook   usces_action_post_update_memberdata
	 * @param  $member_id $res
	 * @return -
	 */
	public function admin_update_memberdata( $member_id, $res ) {

		if( !$this->is_activate_card() || false === $res ) {
			return;
		}

		if( isset( $_POST['escott_quickpay'] ) && ( $_POST['escott_quickpay'] == 'release' || $_POST['escott_quickpay'] == 'forced_release' ) ) {
			$forced_release = ( $_POST['escott_quickpay'] == 'forced_release' ) ? true : false;
			$this->escott_member_delete( $member_id, $forced_release );
		}
	}

	/**
	 * 支払方法説明
	 * @fook   usces_filter_payment_detail
	 * @param  $str $usces_entries
	 * @return string $str
	 */
	public function payment_detail( $str, $usces_entries ) {
		global $usces;

		$payment = $usces->getPayments( $usces_entries['order']['payment_name'] );
		if( $this->acting_flg_card == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( 1 === (int)$acting_opts['howtopay'] ) {

			} else {

				if( isset( $_POST['paytype'] ) && !empty( $_POST['paytype'] ) ) {
					$paytype = $_POST['paytype'];
				}else{
					$paytype = ( isset( $usces_entries['order']['paytype'] ) ) ? $usces_entries['order']['paytype'] : '';
				}

				switch( $paytype ) {
				case '01':
					$str = ' ('.__( 'One time payment', 'usces' ).')';
					break;
				case '02':
				case '03':
				case '05':
				case '06':
				case '10':
				case '12':
				case '15':
				case '18':
				case '20':
				case '24':
					$times = (int)$paytype;
					$str = ' ('.$times.__( '-time payment', 'usces' ).')';
					break;
				case '80':
					$str = ' ('.__( 'Bonus lump-sum payment', 'usces' ).')';
					break;
				case '88':
					$str = ' ('.__( 'Libor Funding pay', 'usces' ).')';
					break;
				default:
					$str = ' ('.__( 'One time payment', 'usces' ).')';
				}
			}
		}
		return $str;
	}

	/**
	 * 支払方法 JavaScript 用決済名追加
	 * @fook   usces_filter_payments_str
	 * @param  $payments_str $payment
	 * @return string $payments_str
	 */
	public function payments_str( $payments_str, $payment ) {

		if( $this->acting_flg_card == $payment['settlement'] ) {
			if( $this->is_activate_card() ) {
				$payments_str .= "'".$payment['name']."': '".$this->paymod_id."', ";
			}
		}
		return $payments_str;
	}

	/**
	 * 支払方法 JavaScript 用決済追加
	 * @fook   usces_filter_payments_arr
	 * @param  $payments_arr $payment
	 * @return array $payments_arr
	 */
	public function payments_arr( $payments_arr, $payment ) {

		if( $this->acting_flg_card == $payment['settlement'] ) {
			if( $this->is_activate_card() ) {
				$payments_arr[] = $this->paymod_id;
			}
		}
		return $payments_arr;
	}

	/**
	 * 内容確認ページ [注文する] ボタン
	 * @fook   usces_filter_confirm_inform
	 * @param  $html $payments $acting_flg $rand $purchase_disabled
	 * @return string $html
	 */
	public function confirm_inform( $html, $payments, $acting_flg, $rand, $purchase_disabled ) {
		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return $html;
		}

		$usces_entries = $usces->cart->get_entry();
		if( !$usces_entries['order']['total_full_price'] ) {
			return $html;
		}

		if( $this->acting_flg_card == $acting_flg ) {
			$acting_opts = $this->get_acting_settings();
			if( 'on' == $acting_opts['card_activate'] ) {
				$cardlast4 = ( isset( $_POST['cardlast4'] ) ) ? $_POST['cardlast4'] : '';
				$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
				$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
					<input type="hidden" name="cardno" value="'.trim( $_POST['cardno'] ).'">
					<input type="hidden" name="cardlast4" value="'.trim( $cardlast4 ).'">';
				if( 'on' == $acting_opts['seccd'] ) {
					$seccd = ( isset( $_POST['seccd'] ) ) ? $_POST['seccd'] : '';
					$html .= '
					<input type="hidden" name="seccd" value="'.trim( $seccd ).'">';
				}
				$html .= '
					<input type="hidden" name="expyy" value="'.trim( $_POST['expyy'] ).'">
					<input type="hidden" name="expmm" value="'.trim( $_POST['expmm'] ).'">
					<input type="hidden" name="paytype" value="'.$usces_entries['order']['paytype'].'">
					<input type="hidden" name="rand" value="'.$rand.'">
					<input type="hidden" name="quick_member" value="'.$quick_member.'">
					<div class="send">
						'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
						<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
						<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
					</div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
				if( isset( $_POST['card_change'] ) && '1' == $_POST['card_change'] ) {
					$html .= '
					<input type="hidden" name="card_change" value="1">';
				}

			} elseif( 'link' == $acting_opts['card_activate'] ) {
				$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
				$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
					<input type="hidden" name="rand" value="'.$rand.'">
					<input type="hidden" name="quick_member" value="'.$quick_member.'">
					<div class="send">
						'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
						<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
						<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
					</div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';

			} elseif( 'token' == $acting_opts['card_activate'] ) {
				$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
				$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
					<input type="hidden" name="token" value="'.trim( $_POST['token'] ).'">
					<input type="hidden" name="paytype" value="'.trim( $_POST['paytype'] ).'">
					<input type="hidden" name="rand" value="'.$rand.'">
					<input type="hidden" name="quick_member" value="'.$quick_member.'">
					<div class="send">
						'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
						<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
						<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
					</div>
					<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
				if( isset( $_POST['card_change'] ) && '1' == $_POST['card_change'] ) {
					$html .= '
					<input type="hidden" name="card_change" value="1">';
				}
			}

		} elseif( $this->acting_flg_conv == $acting_flg ) {
			$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
				<input type="hidden" name="rand" value="'.$rand.'">
				<div class="send">
					'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
					<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
					<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' />
				</div>
				<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
		}
		return $html;
	}

	/**
	 * 内容確認ページ ポイントフォーム
	 * @fook   usces_action_confirm_page_point_inform
	 * @param  -
	 * @echo point_inform()
	 */
	public function e_point_inform() {

		$html = $this->point_inform( '' );
		echo $html;
	}

	/**
	 * 内容確認ページ ポイントフォーム
	 * @fook   usces_filter_confirm_point_inform
	 * @param  $html
	 * @return string $html
	 */
	public function point_inform( $html ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$usces_entries = $usces->cart->get_entry();
		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flg = $payment['settlement'];
		if( $this->acting_flg_card != $acting_flg ) {
			return $html;
		}

		if( 'on' == $acting_opts['card_activate'] ) {
			$cardlast4 = ( isset( $_POST['cardlast4'] ) ) ? $_POST['cardlast4'] : '';
			$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
			$html .= '
			<input type="hidden" name="cardno" value="'.$_POST['cardno'].'">
			<input type="hidden" name="cardlast4" value="'.$cardlast4.'">';
			if( 'on' == $acting_opts['seccd'] ) {
				$seccd = ( isset( $_POST['seccd'] ) ) ? $_POST['seccd'] : '';
				$html .= '
				<input type="hidden" name="seccd" value="'.$seccd.'">';
			}
			$html .= '
			<input type="hidden" name="expyy" value="'.$_POST['expyy'].'">
			<input type="hidden" name="expmm" value="'.$_POST['expmm'].'">
			<input type="hidden" name="offer[paytype]" value="'.$usces_entries['order']['paytype'].'">
			<input type="hidden" name="quick_member" value="'.$quick_member.'">';

		} elseif( 'token' == $acting_opts['card_activate'] ) {
			$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
			$html .= '
			<input type="hidden" name="token" value="'.$_POST['token'].'">
			<input type="hidden" name="paytype" value="'.$_POST['paytype'].'">
			<input type="hidden" name="quick_member" value="'.$quick_member.'">';
		}
		return $html;
	}

	/**
	 * 3Dセキュアリターン
	 * 3Dからのリダイレクトを受けて、もう一度purchaseにリダイレクト
	 * @fook   init
	 * @param  -
	 * @return -
	 */
	public function done_3dsecure() {

		$acting_opts = $this->get_acting_settings();
		if( 'on' != $acting_opts['sec3d_activate'] || 'link' == $acting_opts['card_activate'] ) {
			return;
		}

		//フラグが立っている時のみ通過
		//if( !( isset( $_REQUEST['escott3d'] ) && 'done' == $_REQUEST['escott3d'] ) || !( isset( $_REQUEST['EncryptValue'] ) && !empty( $_REQUEST['EncryptValue'] ) ) ) {
		//	return;
		//}
		if( !( isset( $_REQUEST['EncryptValue'] ) && !empty( $_REQUEST['EncryptValue'] ) ) ) {
			return;
		}

		$algo = 'AES-128-CBC';
		$key = $this->key_aes;
		$iv = $this->key_iv;
		$EncryptValue = base64_decode( $_REQUEST['EncryptValue'] );
		$query = openssl_decrypt( $EncryptValue, $algo, $key, OPENSSL_RAW_DATA, $iv );
		parse_str( $query, $results );
		if( isset( $results['ResponseCd'] ) && isset( $results['SecureResultCode'] ) && ( isset( $results['OperateId'] ) && '3Secure' == $results['OperateId'] ) ) {
		} else {
			return;
		}

		//3Dセキュア認証結果(1)(8)(9)はエラー
		if( 1 == $results['SecureResultCode'] || 8 == $results['SecureResultCode'] || 9 == $results['SecureResultCode'] ) {
			$responsecd = explode( '|', $results['ResponseCd'] );
			foreach( (array)$responsecd as $cd ) {
				$results[$cd] = $this->response_message( $cd );
			}
			$log = array( 'acting'=>$this->acting_card, 'key'=>$results['MerchantFree1'], 'result'=>$results['ResponseCd'], 'data'=>$results );
			usces_save_order_acting_error( $log );
			$result_data = array(
				'MerchantFree2' => $results['MerchantFree2'],
				'ResponseCd' => $results['ResponseCd'],
				'acting' => $this->acting_card,
				'acting_return' => 0,
				'result' => 0
			);
			wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
			exit();
		}

		$post3d = ( isset( $_SESSION['post3d'] ) ) ? $_SESSION['post3d'] : '';
		if( $post3d ) {
			unset( $_SESSION['post3d'] );
?>
			<!DOCTYPE html>
			<html lang="ja">
			<head>
			<title></title>
			</head>
			<body onload="javascript:document.forms['redirectForm'].submit();">
			<form action="<?php echo USCES_CART_URL; ?>" method="post" id="redirectForm">
<?php
			//セッションからPOSTを復帰
			foreach( (array)$post3d as $key => $value ) {
				echo '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
			}

			//3Dセキュアのresを送る
			if( isset( $results['EncodeXId3D'] ) ) {
				echo '<input type="hidden" name="EncodeXId3D" value="' . $results['EncodeXId3D'] . '" />' . "\n";
				echo '<input type="hidden" name="TransactionId3D" value="' . base64_decode( $results['EncodeXId3D'] ) . '" />' . "\n";
			}
			if( isset( $results['MessageVersionNo3D'] ) ) {
				echo '<input type="hidden" name="MessageVersionNo3D" value="' . $results['MessageVersionNo3D'] . '" />' . "\n";
			}
			if( isset( $results['TransactionStatus3D'] ) ) {
				echo '<input type="hidden" name="TransactionStatus3D" value="' . $results['TransactionStatus3D'] . '" />' . "\n";
			}
			if( isset( $results['CAVVAlgorithm3D'] ) ) {
				echo '<input type="hidden" name="CAVVAlgorithm3D" value="' . $results['CAVVAlgorithm3D'] . '" />' . "\n";
			}
			if( isset( $results['ECI3D'] ) ) {
				echo '<input type="hidden" name="ECI3D" value="' . $results['ECI3D'] . '" />' . "\n";
			}
			if( isset( $results['CAVV3D'] ) ) {
				echo '<input type="hidden" name="CAVV3D" value="' . $results['CAVV3D'] . '" />' . "\n";
			}
			if( isset( $results['SecureResultCode'] ) ) {
				echo '<input type="hidden" name="SecureResultCode" value="' . $results['SecureResultCode'] . '" />' . "\n";
			}
?>
			<input type="hidden" name="purchase" value="" />

			<?php //3D完了のフラグを立てる ?>
			<input type="hidden" name="done3d" value="1" />
			<div class="wait_message" style="text-align: center; margin-top: 100px;"><?php esc_html_e( __( 'Hold on for a while, please.', 'usces' ) ); ?></div>
			</form>
			</body>
			</html>
<?php
		}
		exit;
	}

	/**
	 * 3Dセキュア認証画面へリダイレクト
	 * @param  $params, $post_data
	 * @return -
	 */
	public function certification_3dsecure( $params, $post_data, $member_id ) {

		$_SESSION['post3d'] = $post_data;//POSTを保存しておく
		$params['OperateId'] = '3Secure';
		$params['ProcNo'] = usces_rand( 7 );//適当な数値7桁
		$params['RedirectUrl'] = USCES_CART_URL;

		//クイックの時はトークンを入れない
		$acting_opts = $this->get_acting_settings();
		$token = ( isset( $post_data['token'] ) ) ? trim( $post_data['token'] ) : '';
		if( !empty( $token ) ) {
			$params['Token'] = $token;
		} elseif( !empty( $member_id ) && 'on' == $acting_opts['quickpay'] ) {
			$response_member = $this->escott_member_reference( $member_id );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$params['KaiinId'] = $response_member['KaiinId'];
				$params['KaiinPass'] = $response_member['KaiinPass'];
			}
		}
		$query = http_build_query( $params );

		$send_url = $acting_opts['send_url_3dsecure'];
		$algo = 'AES-128-CBC';
		$key = $this->key_aes;
		$iv = $this->key_iv;
		$encrypt_value = openssl_encrypt( $query, $algo, $key, OPENSSL_RAW_DATA, $iv );
		$encrypt_value = base64_encode( $encrypt_value );
?>
		<!DOCTYPE html>
		<html lang="ja">
		<head>
		<title></title>
		</head>
		<body onload="javascript:document.forms['redirectForm'].submit();">
		<form action="<?php echo $send_url; ?>" method="post" id="redirectForm">
		<input type="hidden" name="MerchantId" value="<?php echo $params['MerchantId']; ?>" />
		<input type="hidden" name="EncryptValue" value="<?php echo $encrypt_value; ?>" />
		</form>
		</body>
		</html>
<?php
		exit;
	}

	/**
	 * 決済処理
	 * @fook   usces_action_acting_processing
	 * @param  $acting_flg $post_query
	 * @return -
	 */
	public function acting_processing( $acting_flg, $post_query ) {
		global $usces;

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		$usces_entries = $usces->cart->get_entry();
		$cart = $usces->cart->get_cart();

		if( !$usces_entries || !$cart ) {
			wp_redirect( USCES_CART_URL );
		}

		if( !wp_verify_nonce( $_REQUEST['_nonce'], $acting_flg ) ) {
			wp_redirect( USCES_CART_URL );
		}

		$acting_opts = $this->get_acting_settings();
		parse_str( $post_query, $post_data );
		$TransactionDate = $this->get_transaction_date();
		$rand = $post_data['rand'];
		$member = $usces->get_member();

		if( $this->acting_flg_card == $acting_flg ) {
			if( 'on' == $acting_opts['card_activate'] || 'token' == $acting_opts['card_activate'] ) {

				$param_list = array();
				$params = array();

				//共通部
				$param_list['MerchantId'] = $acting_opts['merchant_id'];
				$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
				$param_list['TransactionDate'] = $TransactionDate;
				$param_list['MerchantFree1'] = $rand;
				$param_list['MerchantFree2'] = $acting_flg;
				$param_list['MerchantFree3'] = $this->merchantfree3;
				$param_list['TenantId'] = $acting_opts['tenant_id'];
				$param_list['Amount'] = $usces_entries['order']['total_full_price'];

				$token = ( isset( $post_data['token'] ) ) ? trim( $post_data['token'] ) : '';
				$quick_member = ( isset( $post_data['quick_member'] ) ) ? $post_data['quick_member'] : '';

				//3Dセキュア認証の結果を受ける
				if( 'on' == $acting_opts['sec3d_activate'] && isset( $post_data['done3d'] ) ) {

					$param_list['Token'] = $token;
					if( isset( $post_data['EncodeXId3D'] ) ) {
						$param_list['EncodeXId3D'] = $post_data['EncodeXId3D'];
					}
					if( isset( $post_data['TransactionId3D'] ) ) {
						$param_list['TransactionId3D'] = $post_data['TransactionId3D'];
					}
					if( isset( $post_data['MessageVersionNo3D'] ) ) {
						$param_list['MessageVersionNo3D'] = $post_data['MessageVersionNo3D'];
					}
					if( isset( $post_data['TransactionStatus3D'] ) ) {
						$param_list['TransactionStatus3D'] = $post_data['TransactionStatus3D'];
					}
					if( isset( $post_data['CAVVAlgorithm3D'] ) ) {
						$param_list['CAVVAlgorithm3D'] = $post_data['CAVVAlgorithm3D'];
					}
					if( isset( $post_data['ECI3D'] ) ) {
						$param_list['ECI3D'] = $post_data['ECI3D'];
					}
					if( isset( $post_data['CAVV3D'] ) ) {
						$param_list['CAVV3D'] = $post_data['CAVV3D'];
					}
					if( isset( $post_data['SecureResultCode'] ) ) {
						$param_list['SecureResultCode'] = $post_data['SecureResultCode'];
					}

				} else {

					//Duplication control
					$this->duplication_control( $acting_flg, $rand );

					if( isset( $post_data['paytype'] ) && '01' != $post_data['paytype'] ) {
						$_SESSION['usces_entry']['order']['paytype'] = $post_data['paytype'];
					}
					usces_save_order_acting_data( $rand );

					if( !empty( $token ) ) {
						//e-SCOTT トークンステータス参照
						$param_list['Token'] = $token;
						$param_list['OperateId'] = '1TokenSearch';
						$params['param_list'] = $param_list;
						$params['send_url'] = $acting_opts['send_url_token'];
						$response_token = $this->connection( $params );
						if( 'OK' != $response_token['ResponseCd'] || 'OK' != $response_token['TokenResponseCd'] ) {
							$tokenresponsecd = '';
							if( isset( $response_token['TokenResponseCd'] ) ) {
								$responsecd = explode( '|', $response_token['ResponseCd'].'|'.$response_token['TokenResponseCd'] );
							} else {
								$responsecd = explode( '|', $response_token['ResponseCd'] );
							}
							foreach( (array)$responsecd as $cd ) {
								if( 'OK' != $cd ) {
									$response_token[$cd] = $this->response_message( $cd );
									$tokenresponsecd .= $cd.'|';
								}
							}
							$tokenresponsecd = rtrim( $tokenresponsecd, '|' );
							$logdata = array_merge( $param_list, $response_token );
							$log = array( 'acting'=>$this->acting_card.'(token_process)', 'key'=>$rand, 'result'=>$tokenresponsecd, 'data'=>$logdata );
							usces_save_order_acting_error( $log );
							$result_data = array(
								'MerchantFree2' => $response_token['MerchantFree2'],
								'ResponseCd' => $tokenresponsecd,
								'acting' => $this->acting_card,
								'acting_return' => 0,
								'result' => 0
							);
							wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
							exit();
						}
					}

					//3Dセキュア認証画面へリダイレクト
					if( 'on' == $acting_opts['sec3d_activate'] ) {
						$this->certification_3dsecure( $param_list, $post_data, $member['ID'] );
					}
				}

				//クイック会員登録
				$quick_member = ( isset( $post_data['quick_member'] ) ) ? $post_data['quick_member'] : '';
				if( !empty( $member['ID'] ) && 'on' == $acting_opts['quickpay'] && 'add' == $quick_member ) {
					$response_member = $this->escott_member_process( $param_list );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$param_list['KaiinId'] = $response_member['KaiinId'];
						$param_list['KaiinPass'] = $response_member['KaiinPass'];
					} else {
						$responsecd = explode( '|', $response_member['ResponseCd'] );
						foreach( (array)$responsecd as $cd ) {
							$response_member[$cd] = $this->response_message( $cd );
						}
						$logdata = array_merge( $param_list, $response_member );
						$log = array( 'acting'=>$acting.'(member_process)', 'key'=>$rand, 'result'=>$response_member['ResponseCd'], 'data'=>$logdata );
						usces_save_order_acting_error( $log );
						$result_data = array(
							'MerchantFree2' => $response_member['MerchantFree2'],
							'ResponseCd' => $response_member['ResponseCd'],
							'acting' => $this->acting_card,
							'acting_return' => 0,
							'result' => 0
						);
						wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
						exit();
					}
					if( true == $response_member['use_token'] ) {
						$param_list['Token'] = '';//トークンクリア
					}
					if( usces_have_continue_charge() ) {
						$chargingday = $usces->getItemChargingDay( $cart[0]['post_id'] );
						if( 99 == $chargingday ) {//受注日課金
							$OperateId = $acting_opts['operateid'];
						} else {
							$OperateId = '1Auth';
						}
						$param_list['PayType'] = '01';
					} else {
						$OperateId = $acting_opts['operateid'];
						$param_list['PayType'] = $post_data['paytype'];
					}
				} else {
					$OperateId = $acting_opts['operateid'];
					$param_list['PayType'] = ( !empty( $post_data['paytype'] ) ) ? $post_data['paytype'] : '01';
					//if( empty( $token ) ) {
					//	$param_list['CardNo'] = trim( $post_data['cardno'] );
					//	$param_list['CardExp'] = substr( $post_data['expyy'], 2 ).$post_data['expmm'];
					//	if( 'on' == $acting_opts['seccd'] ) {
					//		$param_list['SecCd'] = trim( $post_data['seccd'] );
					//	}
					//}
				}

				$param_list['OperateId'] = apply_filters( 'usces_filter_escott_operateid', $OperateId, $cart, $usces_entries['order']['total_full_price'] );
				$params['param_list'] = $param_list;
				$params['send_url'] = $acting_opts['send_url'];

				//e-SCOTT 決済
				$response_data = $this->connection( $params );
				$response_data['acting'] = $this->acting_card;
				$response_data['PayType'] = $param_list['PayType'];
				if( 'on' == $acting_opts['sec3d_activate'] && isset( $post_data['SecureResultCode'] ) ) {
					$response_data['SecureResultCode'] = $post_data['SecureResultCode'];
				}
				if( 'OK' == $response_data['ResponseCd'] ) {
					$res = $usces->order_processing( $response_data );
					if( 'ordercompletion' == $res ) {
						wp_redirect( add_query_arg( array( 'acting'=>$this->acting_card, 'acting_return'=>1, 'result'=>1, 'nonce'=>wp_create_nonce( $this->paymod_id.'_transaction' ) ), USCES_CART_URL ) );
					} else {
						$logdata = array_merge( $usces_entries['order'], $response_data );
						$log = array( 'acting'=>$this->acting_card, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
						usces_save_order_acting_error( $log );
						wp_redirect( add_query_arg( array( 'acting'=>$this->acting_card, 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
					}
				} else {
					$responsecd = explode( '|', $response_data['ResponseCd'] );
					foreach( (array)$responsecd as $cd ) {
						$response_data[$cd] = $this->response_message( $cd );
					}
					$logdata = array_merge( $params, $response_data );
					$log = array( 'acting'=>$this->acting_card, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
					usces_save_order_acting_error( $log );
					$result_data = array(
						'MerchantFree2' => $response_data['MerchantFree2'],
						'ResponseCd' => $response_data['ResponseCd'],
						'acting' => $this->acting_card,
						'acting_return' => 0,
						'result' => 0
					);
					wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
				}
				exit();
			}

		} elseif( $this->acting_flg_conv == $acting_flg ) {

			//Duplication control
			$this->duplication_control( $acting_flg, $rand );

			usces_save_order_acting_data( $rand );

			$param_list = array();
			$params = array();

			$item_name = mb_convert_kana( $usces->getItemName( $cart[0]['post_id'] ), 'ASK', 'UTF-8' );
			if( 1 < count( $cart ) ) {
				if( 16 < mb_strlen( $item_name.__( ' etc.', 'usces' ), 'UTF-8' ) ) {
					$item_name = mb_substr( $item_name, 0, 12, 'UTF-8' ).__( ' etc.', 'usces' );
				}
			} else {
				if( 16 < mb_strlen( $item_name, 'UTF-8' ) ) {
					$item_name = mb_substr( $item_name, 0, 13, 'UTF-8' ).__( '...', 'usces' );
				}
			}
			$paylimit = date_i18n( 'Ymd', current_time( 'timestamp' )+( 86400*$acting_opts['conv_limit'] ) ).'2359';

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree1'] = $rand;
			$param_list['MerchantFree2'] = $acting_flg;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$param_list['Amount'] = $usces_entries['order']['total_full_price'];
			$param_list['OperateId'] = '2Add';
			$param_list['PayLimit'] = $paylimit;
			$param_list['NameKanji'] = $usces_entries['customer']['name1'].$usces_entries['customer']['name2'];
			$param_list['NameKana'] = ( !empty( $usces_entries['customer']['name3'] ) ) ? $usces_entries['customer']['name3'].$usces_entries['customer']['name4'] : $param_list['NameKanji'];
			$param_list['TelNo'] = $usces_entries['customer']['tel'];
			$param_list['ShouhinName'] = $item_name;
			$param_list['Comment'] = __( 'Thank you for using.', 'usces' );
			$param_list['ReturnURL'] = home_url( '/' );
			$params['send_url'] = $acting_opts['send_url_conv'];
			$params['param_list'] = $param_list;
			//e-SCOTT オンライン収納代行データ登録
			$response_data = $this->connection( $params );
			$response_data['acting'] = $this->acting_conv;
			$response_data['PayLimit'] = $paylimit;
			$response_data['Amount'] = $param_list['Amount'];

			if( 'OK' == $response_data['ResponseCd'] ) {
				$FreeArea = trim( $response_data['FreeArea'] );
				$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>1 ), $acting_opts['redirect_url_conv'] );
				$res = $usces->order_processing( $response_data );
				if( 'ordercompletion' == $res ) {
					if( isset( $response_data['MerchantFree1'] ) ) {
						usces_ordered_acting_data( $response_data['MerchantFree1'] );
					}
					$usces->cart->clear_cart();
					header( 'location:'.$url );
					exit();
				} else {
					$logdata = array_merge( $usces_entries['order'], $response_data );
					$log = array( 'acting'=>$this->acting_conv, 'key'=>$rand, 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$logdata );
					usces_save_order_acting_error( $log );
					wp_redirect( add_query_arg( array( 'acting'=>$this->acting_conv, 'acting_return'=>0, 'result'=>0 ), USCES_CART_URL ) );
				}
			} else {
				$responsecd = explode( '|', $response_data['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$response_data[$cd] = $this->response_message( $cd );
				}
				$logdata = array_merge( $params, $response_data );
				$log = array( 'acting'=>$this->acting_conv, 'key'=>$rand, 'result'=>$response_data['ResponseCd'], 'data'=>$logdata );
				usces_save_order_acting_error( $log );
				$result_data = array(
					'MerchantFree2' => $response_data['MerchantFree2'],
					'ResponseCd' => $response_data['ResponseCd'],
					'acting' => $this->acting_conv,
					'acting_return' => 0,
					'result' => 0
				);
				wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
			}
			exit();
		}
	}

	/**
	 * 決済完了ページ制御
	 * @fook   usces_filter_check_acting_return_results
	 * @param  $results
	 * @return array $results
	 */
	public function acting_return( $results ) {

		if( !in_array( 'acting_'.$results['acting'], $this->pay_method ) ) {
			return $results;
		}

		if( isset( $results['acting_return'] ) && $results['acting_return'] != 1 ) {
			return $results;
		}

		$results['reg_order'] = false;

		usces_log( '['.$this->acting_name.'] results : '.print_r( $results, true ), 'acting_transaction.log' );
		if( !isset( $_REQUEST['nonce'] ) || !wp_verify_nonce( $_REQUEST['nonce'], $this->paymod_id.'_transaction' ) ) {
			wp_redirect( home_url() );
			exit();
		}

		return $results;
	}

	/**
	 * 重複オーダー禁止処理
	 * @fook   usces_filter_check_acting_return_duplicate
	 * @param  $trans_id $results
	 * @return string RandId
	 */
	public function check_acting_return_duplicate( $trans_id, $results ) {
		global $usces;

		$entry = $usces->cart->get_entry();
		if( !$entry['order']['total_full_price'] ) {
			return 'not_credit';
		} elseif( isset( $results['MerchantFree1'] ) && isset( $results['acting'] ) && ( $this->acting_card == $results['acting'] || $this->acting_conv == $results['acting'] ) ) {
			$_REQUEST['absolute_trans_id'] = $results['MerchantFree1'];
			return $results['MerchantFree1'];
		} else {
			$_REQUEST['absolute_trans_id'] = $trans_id;
			return $trans_id;
		}
	}

	/**
	 * 受注データ登録
	 * Call from usces_reg_orderdata() and usces_new_orderdata().
	 * @fook   usces_action_reg_orderdata
	 * @param  $args = array(
	 *						'cart'=>$cart, 'entry'=>$entry, 'order_id'=>$order_id, 
	 *						'member_id'=>$member['ID'], 'payments'=>$set, 'charging_type'=>$charging_type, 
	 *						'results'=>$results
	 *						);
	 * @return -
	 */
	public function register_orderdata( $args ) {
		global $usces;
		extract( $args );

		$acting_flg = $payments['settlement'];
		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		if( !$entry['order']['total_full_price'] ) {
			return;
		}

		if( isset( $results['MerchantFree1'] ) ) {
			$usces->set_order_meta_value( 'trans_id', $results['MerchantFree1'], $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $results['MerchantFree1'], $order_id );
			$usces->set_order_meta_value( $acting_flg, usces_serialize( $results ), $order_id );
		}

		if( $this->acting_flg_conv == $acting_flg ) {
			$usces->set_order_meta_value( $results['MerchantFree1'], $acting_flg, $order_id );
		}
	}

	/**
	 * 受注データ登録
	 * Call after usces_reg_orderdata().
	 * @fook   usces_post_reg_orderdata
	 * @param  $order_id $results
	 * @return -
	 */
	public function post_register_orderdata( $order_id, $results ) {
		global $usces;

		if( isset( $results['acting'] ) && $this->acting_conv == $results['acting'] ) {
			$acting_opts = $this->get_acting_settings();
			$FreeArea = trim( $results['FreeArea'] );
			$url = add_query_arg( array( 'code'=>$FreeArea, 'rkbn'=>2 ), $acting_opts['redirect_url_conv'] );
			$usces->set_order_meta_value( $this->paymod_id.'_conv_url', $url, $order_id );
		}
	}

	/**
	 * 決済エラーメッセージ
	 * @fook   usces_filter_get_error_settlement
	 * @param  $html
	 * @return string $html
	 */
	public function error_page_message( $html ) {

		$acting_flg = ( isset( $_REQUEST['MerchantFree2'] ) ) ? $_REQUEST['MerchantFree2'] : '';
		if( $this->acting_flg_card == $acting_flg ) {
			if( isset( $_REQUEST['MerchantFree1'] ) && usces_get_order_id_by_trans_id( (int)$_REQUEST['MerchantFree1'] ) ) {
				$html .= '<div class="error_page_mesage">
				<p>'.__( 'Your order has already we complete.', 'usces' ).'</p>
				<p>'.__( 'Please do not re-display this page.', 'usces' ).'</p>
				</div>';
			} else {
				$error_message = array();
				$responsecd = explode( '|', $_REQUEST['ResponseCd'] );
				foreach( (array)$responsecd as $cd ) {
					$error_message[] = $this->error_message( $cd );
				}
				$error_message = array_unique( $error_message );
				if( 0 < count( $error_message ) ) {
					$html .= '<div class="error_page_mesage">
					<p>'.__( 'Error code', 'usces' ).'：'.$_REQUEST['ResponseCd'].'</p>';
					foreach( $error_message as $message ) {
						$html .= '<p>'.$message.'</p>';
					}
					$html .= '
					<p class="return_settlement"><a href="'.add_query_arg( array( 'backDelivery'=>$this->acting_card, 're-enter'=>1 ), USCES_CART_URL ).'">'.__( 'Card number re-enter', 'usces' ).'</a></p>
					</div>';
				}
			}

		} elseif( $this->acting_flg_conv == $acting_flg ) {
			$error_message = array();
			$responsecd = explode( '|', $_REQUEST['ResponseCd'] );
			foreach( (array)$responsecd as $cd ) {
				$error_message[] = $this->error_message( $cd );
			}
			$error_message = array_unique( $error_message );
			if( 0 < count( $error_message ) ) {
				$html .= '<div class="error_page_mesage">
				<p>'.__( 'Error code', 'usces' ).'：'.$_REQUEST['ResponseCd'].'</p>';
				foreach( $error_message as $message ) {
					$html .= '<p>'.$message.'</p>';
				}
			}
			$html .= '</div>';
		}
		return $html;
	}

	/**
	 * オンライン収納代行決済用サンキューメール
	 * @fook   usces_filter_send_order_mail_payment
	 * @param  $msg_payment $order_id $payment $cart $entry $data
	 * @return string $msg_payment
	 */
	public function order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ) {
		global $usces;

		if( $this->acting_flg_conv != $payment['settlement'] ) {
			return $msg_payment;
		}

		$acting_opts = $this->get_acting_settings();
		$url = $usces->get_order_meta_value( $this->paymod_id.'_conv_url', $order_id );
		$msg_conv = sprintf( __( "Payment expiration date is %s days.", 'usces' ), $acting_opts['conv_limit'] )."\r\n";
		$msg_conv .= __( "If payment has not yet been completed, please payment procedure from the following URL.", 'usces' )."\r\n\r\n";
		$msg_conv .= __( "[Payment URL]", 'usces' )."\r\n";
		$msg_conv .= $url."\r\n";
		$msg_payment .= apply_filters( 'usces_filter_escott_send_order_mail_payment_conv', $msg_conv, $url, $acting_opts['conv_limit'] );
		return $msg_payment;
	}

	/**
	 * 管理画面メッセージ表示
	 * @fook   admin_notices
	 * @param  -
	 * @return -
	 */
	public function display_admin_notices() {

		$acting_opts = $this->get_acting_settings();
		if( 'on' == $acting_opts['card_activate'] ) {
			if( empty( $acting_opts['token_code'] ) ) {
				echo '<div class="update-nag">'.$this->acting_name.sprintf( __( "Please enter the <a href=\"admin.php?page=usces_settlement#uscestabs_%s\">'Token auth code'</a>.", 'usces' ), $this->paymod_id ).'</div>';
			}
		}
	}

	/**
	 * @fook   wp_print_footer_scripts
	 * @param  -
	 * @return -
	 */
	public function footer_scripts() {
		global $usces;

		if( !$this->is_validity_acting( 'card' ) ) {
			return;
		}

		//発送・支払方法ページ
		if( 'delivery' == $usces->page ):
			$acting_opts = $this->get_acting_settings();
			//埋込み型
			if( isset( $acting_opts['card_activate'] ) && 'on' == $acting_opts['card_activate'] ):
?>
<script type="text/javascript">
( function( $ ) {
	$( "#cardno" ).change( function( e ) {
		var first_c = $( this ).val().substr( 0, 1 );
		var second_c = $( this ).val().substr( 1, 1 );
		if( '4' == first_c || '5' == first_c || ( '3' == first_c && '5' == second_c ) ) {
			$( "#paytype_default" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype4535" ).removeAttr( "disabled" ).css( "display", "inline" );
			$( "#paytype37" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype36" ).attr( "disabled", "disabled" ).css( "display", "none" );
		} else if( '3' == first_c && '6' == second_c ) {
			$( "#paytype_default" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype4535" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype37" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype36" ).removeAttr( "disabled" ).css( "display", "inline" );
		} else if( '3' == first_c && '7' == second_c ) {
			$( "#paytype_default" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype4535" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype37" ).removeAttr( "disabled" ).css( "display", "inline" );
			$( "#paytype36" ).attr( "disabled", "disabled" ).css( "display", "none" );
		} else {
			$( "#paytype_default" ).removeAttr( "disabled" ).css( "display", "inline" );
			$( "#paytype4535" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype37" ).attr( "disabled", "disabled" ).css( "display", "none" );
			$( "#paytype36" ).attr( "disabled", "disabled" ).css( "display", "none" );
		}
	});
	$( "#cardno" ).trigger( "change" );
		<?php if( isset( $_REQUEST['backDelivery'] ) && $this->acting_card == substr( $_REQUEST['backDelivery'], 0, 12 ) ):
			$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );
			$id = $payment_method[$this->acting_flg_card]['sort']; ?>
	$( "#payment_name_<?php echo $id; ?>" ).prop( "checked", true );
		<?php endif; ?>
})( jQuery );
</script>
<?php
			//トークン決済
			elseif( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ):
				wp_register_style( 'jquery-ui-style', USCES_FRONT_PLUGIN_URL.'/css/jquery/jquery-ui-1.10.3.custom.min.css' );
				wp_enqueue_style( 'jquery-ui-style' );
				wp_enqueue_script( 'jquery-ui-dialog' );
				wp_enqueue_script( 'usces_cart_escott', USCES_FRONT_PLUGIN_URL.'/js/cart_escott.js', array( 'jquery' ), USCES_VERSION, true );
			endif;

		//マイページ
		elseif( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ):
			$member = $usces->get_member();
			$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
			if( !empty( $KaiinId ) ):
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	$( "input[name='deletemember']" ).css( "display", "none" );
});
</script>
<?php
			endif;
		endif;
	}

	/**
	 * カード情報入力チェック
	 * @fook   usces_filter_delivery_check
	 * @param  $mes
	 * @return string $mes
	 */
	public function delivery_check( $mes ) {
		global $usces;

		if( !isset( $_POST['offer']['payment_name'] ) ) {
			return $mes;
		}

		$payment = $usces->getPayments( $_POST['offer']['payment_name'] );
		if( $this->acting_flg_card == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( isset( $acting_opts['card_activate'] ) && 'on' == $acting_opts['card_activate'] ) {
				if( 'on' == $acting_opts['seccd'] ) {
					if( ( isset( $_POST['acting'] ) && $this->paymod_id == $_POST['acting'] ) && 
						( isset( $_POST['cardno'] ) && empty( $_POST['cardno'] ) ) || 
						( isset( $_POST['seccd'] ) && empty( $_POST['seccd'] ) ) || 
						( isset( $_POST['expyy'] ) && empty( $_POST['expyy'] ) ) || 
						( isset( $_POST['expmm'] ) && empty( $_POST['expmm'] ) ) ) {
						$mes .= __( 'Please enter the card information correctly.', 'usces' ).'<br />';
					}
				} else {
					if( ( isset( $_POST['acting'] ) && $this->paymod_id == $_POST['acting'] ) && 
						( isset( $_POST['cardno'] ) && empty( $_POST['cardno'] ) ) || 
						( isset( $_POST['expyy'] ) && empty( $_POST['expyy'] ) ) || 
						( isset( $_POST['expmm'] ) && empty( $_POST['expmm'] ) ) ) {
						$mes .= __( 'Please enter the card information correctly.', 'usces' ).'<br />';
					}
				}
			} elseif( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ) {
				if( isset( $_POST['token'] ) && empty( $_POST['token'] ) ) {
					if( $usces->is_member_logged_in() && 'on' == $acting_opts['quickpay'] ) {
						$quick_member = ( isset( $_POST['quick_member'] ) ) ? $_POST['quick_member'] : '';
						if( 'add' != $quick_member ) {
							$mes .= __( 'Please enter the card information correctly.', 'usces' ).'<br />';
						}
					} else {
						$mes .= __( 'Please enter the card information correctly.', 'usces' ).'<br />';
					}
				}
			}
		}
		return $mes;
	}

	/**
	 * 支払方法ページ用入力フォーム
	 * @fook   usces_filter_delivery_secure_form
	 * @param  $html $payment
	 * @return string $html
	 */
	public function delivery_secure_form( $html, $payment ) {
		global $usces;

		if( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'delivery' == $usces->page ) {
			$acting_opts = $this->get_acting_settings();
			if( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ) {
				$html .= '
					<input type="hidden" name="acting" value="'.$this->paymod_id.'" />
					<input type="hidden" name="confirm" value="confirm" />
					<input type="hidden" name="token" id="token" value="" />
					<input type="hidden" name="paytype" value="" />
					<input type="hidden" name="quick_member" value="" />
					<input type="hidden" name="card_change" value="" />';
			}
		}
		return $html;
	}

	/**
	 * 支払方法ページ用入力フォーム
	 * @fook   usces_filter_delivery_secure_form_loop
	 * @param  $nouse $payment
	 * @return string $html
	 */
	public function delivery_secure_form_loop( $nouse, $payment ) {
		global $usces;

		$html = '';
		if( $this->acting_flg_card == $payment['settlement'] ) {
			$acting_opts = $this->get_acting_settings();
			if( ( !isset( $acting_opts['activate'] ) || 'on' != $acting_opts['activate'] ) || 
				( !isset( $acting_opts['card_activate'] ) || 'on' != $acting_opts['card_activate'] ) ||
				'activate' != $payment['use'] ) {
				return $html;
			}

			$backDelivery = ( isset( $_REQUEST['backDelivery'] ) && $this->acting_card == substr( $_REQUEST['backDelivery'], 0, 12 ) ) ? true : false;
			$card_change = ( isset( $_REQUEST['card_change'] ) ) ? true : false;
			if( $card_change ) {
				if( 'on' == $acting_opts['seccd'] ) {
					if( ( isset( $_POST['acting'] ) && $this->paymod_id == $_POST['acting'] ) && 
						( isset( $_POST['cardno'] ) && empty( $_POST['cardno'] ) ) || 
						( isset( $_POST['seccd'] ) && empty( $_POST['seccd'] ) ) || 
						( isset( $_POST['expyy'] ) && empty( $_POST['expyy'] ) ) || 
						( isset( $_POST['expmm'] ) && empty( $_POST['expmm'] ) ) ) {
						$backDelivery = true;
					}
				} else {
					if( ( isset( $_POST['acting'] ) && $this->paymod_id == $_POST['acting'] ) && 
						( isset( $_POST['cardno'] ) && empty( $_POST['cardno'] ) ) || 
						( isset( $_POST['expyy'] ) && empty( $_POST['expyy'] ) ) || 
						( isset( $_POST['expmm'] ) && empty( $_POST['expmm'] ) ) ) {
						$backDelivery = true;
					}
				}
			}

			$cardno = ( isset( $_POST['cardno'] ) ) ? esc_html( $_POST['cardno'] ) : '';
			$expyy = ( isset( $_POST['expyy'] ) ) ? esc_html( $_POST['expyy'] ) : '';
			$expmm = ( isset( $_POST['expmm'] ) ) ? esc_html( $_POST['expmm'] ) : '';
			$paytype = ( isset( $usces_entries['order']['paytype'] ) ) ? esc_html( $usces_entries['order']['paytype'] ) : '01';

			$html .= '<input type="hidden" name="acting" value="'.$this->paymod_id.'">';
			$html .= '
			<table class="customer_form" id="'.$this->paymod_id.'">';

			if( usces_is_login() ) {
				$member = $usces->get_member();
				$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
				$KaiinPass = $this->get_quick_pass( $member['ID'] );
			}

			$response_member = array( 'ResponseCd'=>'' );

			if( 'on' == $acting_opts['quickpay'] && !empty( $KaiinId ) && !empty( $KaiinPass ) && !$card_change ) {
				//e-SCOTT 会員照会
				$response_member = $this->escott_member_reference( $member['ID'], $KaiinId, $KaiinPass );
			}
			if( 'OK' == $response_member['ResponseCd'] && !$backDelivery ) {
				$cardlast4 = substr( $response_member['CardNo'], -4 );
				$expyy = substr( date_i18n( 'Y', current_time( 'timestamp' ) ), 0, 2 ).substr( $response_member['CardExp'], 0, 2 );
				$expmm = substr( $response_member['CardExp'], 2, 2 );
				$html .= '
				<input name="cardno" type="hidden" value="8888888888888888" />
				<input name="cardlast4" type="hidden" value="'.$cardlast4.'" />
				<input name="expyy" type="hidden" value="'.$expyy.'" />
				<input name="expmm" type="hidden" value="'.$expmm.'" />
				<input name="quick_member" type="hidden" value="add">
				<tr>
					<th scope="row">'.__( 'The last four digits of your card number', 'usces' ).'</th>
					<td colspan="2"><p>'.$cardlast4.' (<a href="'.add_query_arg( array( 'backDelivery'=>$this->acting_card, 'card_change'=>1 ), USCES_CART_URL ).'">'.__( 'Change of card information, click here', 'usces' ).'</a>)</p></td>
				</tr>';

			} else {
				$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __( '(Single-byte numbers only)', 'usces' ).'<div class="attention">'.__( '* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.', 'usces' ).'</div>' );
				$change = ( $card_change ) ? '<input type="hidden" name="card_change" value="1">' : '';
				$quickpay = '';
				if( usces_is_login() && 'on' == $acting_opts['quickpay'] ) {
					if( usces_have_regular_order() || usces_have_continue_charge() ) {
						$quickpay = '<input type="hidden" name="quick_member" value="add">';
					} elseif( 'on' != $acting_opts['chooseable_quickpay'] ) {
						$quickpay = '<input type="hidden" name="quick_member" value="add">';
					} else {
						$quickpay = '<p class="escott_quick_member"><label type="add"><input type="checkbox" name="quick_member" value="add"><span>'.__( 'Register and purchase a credit card', 'usces' ).'</span></label></p>';
					}
				} else {
					$quickpay = '<input type="hidden" name="quick_member" value="no">';
				}
				$html .= '
				<tr>
					<th scope="row">'.__( 'card number', 'usces' ).'</th>
					<td colspan="2"><input name="cardno" id="cardno" type="tel" value="'.$cardno.'" />'.$cardno_attention.$change.$quickpay.'</td>
				</tr>';
				if( 'on' == $acting_opts['seccd'] ) {
					$seccd = ( isset( $_POST['seccd'] ) ) ? esc_html( $_POST['seccd'] ) : '';
					$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __( '(Single-byte numbers only)', 'usces' ) );
					$html .= '
				<tr>
					<th scope="row">'.__( 'security code', 'usces' ).'</th>
					<td colspan="2"><input name="seccd" type="tel" value="'.$seccd.'" />'.$seccd_attention.'</td>
				</tr>';
				}
				$html .= '
				<tr>
					<th scope="row">'.__( 'Card expiration', 'usces' ).'</th>
					<td colspan="2">
						<select name="expmm">
							<option value="">--</option>';
				for( $i = 1; $i <= 12; $i++ ) {
					$html .= '
							<option value="'.sprintf( '%02d', $i ).'"'.( ( $i == (int)$expmm ) ? ' selected="selected"' : '' ).'>'.sprintf( '%2d', $i ).'</option>';
				}
				$html .= '
						</select>'.__( 'month', 'usces' ).'&nbsp;
						<select name="expyy">
							<option value="">----</option>';
				for( $i = 0; $i < 15; $i++ ) {
					$year = date_i18n( 'Y' ) + $i;
					$selected = ( $year == $expyy ) ? ' selected="selected"' : '';
					$html .= '
							<option value="'.$year.'"'.$selected.'>'.$year.'</option>';
				}
				$html .= '
						</select>'.__( 'year', 'usces' ).'
					</td>
				</tr>';
			}

			$html_paytype = '';
			if( ( usces_have_regular_order() || usces_have_continue_charge() ) && usces_is_login() ) {
				$html_paytype .= '<input type="hidden" name="offer[paytype]" value="01" />';

			} else {
				if( 1 === (int)$acting_opts['howtopay'] ) {
				//	$html_paytype .= '
				//<tr>
				//	<th scope="row">'.__( 'Number of payments', 'usces' ).'</th>
				//	<td colspan="2">'.__( 'Single payment only', 'usces' ).'
				//		<input type="hidden" name="offer[paytype]" value="01" />
				//	</td>
				//</tr>';
					$html_paytype .= '<input type="hidden" name="offer[paytype]" value="01" />';

				} elseif( 2 <= $acting_opts['howtopay'] ) {
					$cardfirst4 = ( 'OK' == $response_member['ResponseCd'] && !$backDelivery ) ? '<input type="hidden" id="cardno" value="'.substr( $response_member['CardNo'], 0, 4 ).'" />' : '';//先頭4桁
					$html_paytype .= '
				<tr>
					<th scope="row">'.__( 'Number of payments', 'usces' ).'</th>
					<td colspan="2">'.$cardfirst4.'<div class="paytype">';

					$html_paytype .= '
						<select name="offer[paytype]" id="paytype_default" >
							<option value="01"'.( ( '01' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'One time payment', 'usces' ).'</option>
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="paytype4535" style="display:none;" disabled="disabled" >
							<option value="01"'.( ( '01' == $paytype ) ? ' selected="selected"' : '' ).'>1'.__( '-time payment', 'usces' ).'</option>
							<option value="02"'.( ( '02' == $paytype ) ? ' selected="selected"' : '' ).'>2'.__( '-time payment', 'usces' ).'</option>
							<option value="03"'.( ( '03' == $paytype ) ? ' selected="selected"' : '' ).'>3'.__( '-time payment', 'usces' ).'</option>
							<option value="05"'.( ( '05' == $paytype ) ? ' selected="selected"' : '' ).'>5'.__( '-time payment', 'usces' ).'</option>
							<option value="06"'.( ( '06' == $paytype ) ? ' selected="selected"' : '' ).'>6'.__( '-time payment', 'usces' ).'</option>
							<option value="10"'.( ( '10' == $paytype ) ? ' selected="selected"' : '' ).'>10'.__( '-time payment', 'usces' ).'</option>
							<option value="12"'.( ( '12' == $paytype ) ? ' selected="selected"' : '' ).'>12'.__( '-time payment', 'usces' ).'</option>
							<option value="15"'.( ( '15' == $paytype ) ? ' selected="selected"' : '' ).'>15'.__( '-time payment', 'usces' ).'</option>
							<option value="18"'.( ( '18' == $paytype ) ? ' selected="selected"' : '' ).'>18'.__( '-time payment', 'usces' ).'</option>
							<option value="20"'.( ( '20' == $paytype ) ? ' selected="selected"' : '' ).'>20'.__( '-time payment', 'usces' ).'</option>
							<option value="24"'.( ( '24' == $paytype ) ? ' selected="selected"' : '' ).'>24'.__( '-time payment', 'usces' ).'</option>
							<option value="88"'.( ( '88' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'Libor Funding pay', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.( ( '80' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="paytype37" style="display:none;" disabled="disabled" >
							<option value="01"'.( ( '01' == $paytype ) ? ' selected="selected"' : '' ).'>1'.__( '-time payment', 'usces' ).'</option>
							<option value="03"'.( ( '03' == $paytype ) ? ' selected="selected"' : '' ).'>3'.__( '-time payment', 'usces' ).'</option>
							<option value="05"'.( ( '05' == $paytype ) ? ' selected="selected"' : '' ).'>5'.__( '-time payment', 'usces' ).'</option>
							<option value="06"'.( ( '06' == $paytype ) ? ' selected="selected"' : '' ).'>6'.__( '-time payment', 'usces' ).'</option>
							<option value="10"'.( ( '10' == $paytype ) ? ' selected="selected"' : '' ).'>10'.__( '-time payment', 'usces' ).'</option>
							<option value="12"'.( ( '12' == $paytype ) ? ' selected="selected"' : '' ).'>12'.__( '-time payment', 'usces' ).'</option>
							<option value="15"'.( ( '15' == $paytype ) ? ' selected="selected"' : '' ).'>15'.__( '-time payment', 'usces' ).'</option>
							<option value="18"'.( ( '18' == $paytype ) ? ' selected="selected"' : '' ).'>18'.__( '-time payment', 'usces' ).'</option>
							<option value="20"'.( ( '20' == $paytype ) ? ' selected="selected"' : '' ).'>20'.__( '-time payment', 'usces' ).'</option>
							<option value="24"'.( ( '24' == $paytype ) ? ' selected="selected"' : '' ).'>24'.__( '-time payment', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.( ( '80' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select name="offer[paytype]" id="paytype36" style="display:none;" disabled="disabled" >
							<option value="01"'.( ( '01' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'One time payment', 'usces' ).'</option>
							<option value="88"'.( ( '88' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'Libor Funding pay', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80"'.( ( '80' == $paytype ) ? ' selected="selected"' : '' ).'>'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '</div>
					</td>
				</tr>';
				}
			}
			$html .= apply_filters( 'usces_filter_escott_secure_form_paytype', $html_paytype );
			$html .= '
			</table><table>';
		}
		return $html;
	}

	/**
	 * 会員データ削除チェック
	 * @fook   usces_filter_delete_member_check
	 * @param  $del $member_id
	 * @return boolean $del
	 */
	public function delete_member_check( $del, $member_id ) {
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		if( !empty( $KaiinId ) ) {
			$del = false;
		}
		return $del;
	}

	/**
	 * @fook   wp_print_styles
	 * @param  -
	 * @return -
	 */
	public function print_styles() {
		global $usces;

		//発送・支払方法ページ
		if( !is_admin() && 'delivery' == $usces->page && $this->is_validity_acting( 'card' ) ):
			$acting_opts = $this->get_acting_settings();
			if( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ):
?>
<style type="text/css">
#escott-dialog {
	left: 50% !important;
	transform: translateY(-50%) translateX(-50%);
	-webkit- transform: translateY(-50%) translateX(-50%);
	width: 90% !important;
	max-width: 700px;
}
</style>
<?php
			endif;
		endif;
	}

	/**
	 * @fook   wp_enqueue_scripts
	 * @param  -
	 * @return -
	 */
	public function enqueue_scripts() {
		global $usces;

		//発送・支払方法ページ
		if( !is_admin() && 'delivery' == $usces->page && $this->is_validity_acting( 'card' ) ):
			$acting_opts = $this->get_acting_settings();
			if( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ):
?>
<script type="text/javascript"
src="<?php esc_html_e( $acting_opts['api_token'] ); ?>?k_TokenNinsyoCode=<?php esc_html_e( $acting_opts['token_code'] ); ?>" callBackFunc="setToken" class="spsvToken"></script>
<?php
			endif;
		endif;
	}

	/**
	 * @fook   usces_filter_uscesL10n
	 * @param  $l10n $post_id
	 * @return string $l10n
	 */
	public function set_uscesL10n( $l10n, $post_id ) {
		global $usces;

		if( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'delivery' == $usces->page ) {
			$acting_opts = $this->get_acting_settings();
			if( isset( $acting_opts['card_activate'] ) && 'token' == $acting_opts['card_activate'] ) {
				$front_ajaxurl = USCES_SSL_URL;
				if( '/' != substr( $front_ajaxurl, -1 ) ) {
					$front_ajaxurl .= '/';
				}
				$front_ajaxurl .= 'index.php';
				$l10n .= "'front_ajaxurl': '".$front_ajaxurl."',\n";
				$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
				$payment_method = apply_filters( 'usces_fiter_the_payment_method', $payment_method, '' );
				foreach( (array)$payment_method as $id => $payment ) {
					if( $payment['settlement'] == $this->acting_flg_card ) {
						$l10n .= "'escott_token_payment_id': '".$id."',\n";
						break;
					}
				}
				$l10n .= "'escott_token_dialog_title': '".__( 'Credit card information', 'usces' )."',\n";
				$l10n .= "'escott_token_btn_next': '".__( 'Next', 'usces' )."',\n";
				$l10n .= "'escott_token_btn_cancel': '".__( 'Cancel', 'usces' )."',\n";
				$l10n .= "'escott_token_error_message': '".__( 'Credit card information is not appropriate.', 'usces' )."',\n";
			}
		}
		return $l10n;
	}

	/**
	 * @fook   usces_front_ajax
	 * @param  -
	 * @return -
	 */
	public function front_ajax() {
		global $usces;

		switch( $_POST['usces_ajax_action'] ) {
		case 'escott_token_dialog':
			if( !wp_verify_nonce( $_POST['wc_nonce'], 'wc_delivery_secure_nonce' ) ) {
				wp_redirect( USCES_CART_URL );
			}

			$data = array();
			$acting_opts = $this->get_acting_settings();
			$card_change = ( isset( $_POST['card_change'] ) ) ? true : false;

			$html = '';
			$html .= '
			<table class="customer_form settlement_form" id="'.$this->paymod_id.'">';

			if( usces_is_login() ) {
				$member = $usces->get_member();
				$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
				$KaiinPass = $this->get_quick_pass( $member['ID'] );
			}

			$response_member = array( 'ResponseCd'=>'' );

			if( 'on' == $acting_opts['quickpay'] && !empty( $KaiinId ) && !empty( $KaiinPass ) && !$card_change ) {
				//e-SCOTT 会員照会
				$response_member = $this->escott_member_reference( $member['ID'], $KaiinId, $KaiinPass );
			}

			if( 'OK' == $response_member['ResponseCd'] ) {
				$cardlast4 = substr( $response_member['CardNo'], -4 );
				$html .= '
				<tr>
					<th scope="row">'.__( 'The last four digits of your card number', 'usces' ).'</th>
					<td colspan="2"><p>'.$cardlast4.' (<a href="#" id="escott_card_change">'.__( 'Change of card information, click here', 'usces' ).'</a>)</p></td>
				</tr>';

			} else {
				$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __( '(Single-byte numbers only)', 'usces' ).'<div class="attention">'.__( '* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.', 'usces' ).'</div>' );
				$change = ( $card_change ) ? '<input type="hidden" id="card_change" value="1">' : '';
				$quickpay = '';
				if( usces_is_login() && 'on' == $acting_opts['quickpay'] ) {
					if( usces_have_regular_order() || usces_have_continue_charge() ) {
						$quickpay = '<input type="hidden" id="quick_member" value="add">';
					} elseif( 'on' != $acting_opts['chooseable_quickpay'] ) {
						$quickpay = '<input type="hidden" id="quick_member" value="add">';
					} else {
						$quickpay = '<p class="escott_quick_member"><label type="add"><input type="checkbox" id="quick_member" value="add"><span>'.__( 'Register and purchase a credit card', 'usces' ).'</span></label></p>';
					}
				} else {
					$quickpay = '<input type="hidden" id="quick_member" value="no">';
				}
				$html .= '
				<tr>
					<th scope="row">'.__( 'card number', 'usces' ).'</th>
					<td colspan="2"><input id="cardno" type="tel" value="" />'.$cardno_attention.$change.$quickpay.'</td>
				</tr>';
				if( 'on' == $acting_opts['seccd'] ) {
					$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __( '(Single-byte numbers only)', 'usces' ) );
					$html .= '
				<tr>
					<th scope="row">'.__( 'security code', 'usces' ).'</th>
					<td colspan="2"><input id="seccd" type="tel" value="" />'.$seccd_attention.'</td>
				</tr>';
				}
				$html .= '
				<tr>
					<th scope="row">'.__( 'Card expiration', 'usces' ).'</th>
					<td colspan="2">
						<select id="expmm">
							<option value="">--</option>';
				for( $i = 1; $i <= 12; $i++ ) {
					$html .= '
							<option value="'.sprintf( '%02d', $i ).'">'.sprintf( '%2d', $i ).'</option>';
				}
				$html .= '
						</select>'.__( 'month', 'usces' ).'&nbsp;
						<select id="expyy">
							<option value="">----</option>';
				for( $i = 0; $i < 15; $i++ ) {
					$year = date_i18n( 'Y' ) + $i;
					$html .= '
							<option value="'.$year.'">'.$year.'</option>';
				}
				$html .= '
						</select>'.__( 'year', 'usces' ).'
					</td>
				</tr>';
			}

			$html_paytype = '';
			if( ( usces_have_regular_order() || usces_have_continue_charge() ) && usces_is_login() ) {
				$html_paytype .= '<input type="hidden" id="paytype" value="01" />';

			} else {
				if( 1 === (int)$acting_opts['howtopay'] ) {
				//	$html_paytype .= '
				//<tr>
				//	<th scope="row">'.__( 'Number of payments', 'usces' ).'</th>
				//	<td colspan="2">'.__( 'Single payment only', 'usces' ).'
				//		<input type="hidden" id="paytype" value="01" />
				//	</td>
				//</tr>';
					$html_paytype .= '<input type="hidden" id="paytype" value="01" />';

				} elseif( 2 <= $acting_opts['howtopay'] ) {
					$cardfirst4 = ( 'OK' == $response_member['ResponseCd'] ) ? '<input type="hidden" id="cardno" value="'.substr( $response_member['CardNo'], 0, 4 ).'" />' : '';//先頭4桁
					$html_paytype .= '
				<tr>
					<th scope="row">'.__( 'Number of payments', 'usces' ).'</th>
					<td colspan="2">'.$cardfirst4.'<div class="paytype">';

					$html_paytype .= '
						<select id="paytype_default" >
							<option value="01">'.__( 'One time payment', 'usces' ).'</option>
						</select>';

					$html_paytype .= '
						<select id="paytype4535" style="display:none;" disabled="disabled" >
							<option value="01">1'.__( '-time payment', 'usces' ).'</option>
							<option value="02">2'.__( '-time payment', 'usces' ).'</option>
							<option value="03">3'.__( '-time payment', 'usces' ).'</option>
							<option value="05">5'.__( '-time payment', 'usces' ).'</option>
							<option value="06">6'.__( '-time payment', 'usces' ).'</option>
							<option value="10">10'.__( '-time payment', 'usces' ).'</option>
							<option value="12">12'.__( '-time payment', 'usces' ).'</option>
							<option value="15">15'.__( '-time payment', 'usces' ).'</option>
							<option value="18">18'.__( '-time payment', 'usces' ).'</option>
							<option value="20">20'.__( '-time payment', 'usces' ).'</option>
							<option value="24">24'.__( '-time payment', 'usces' ).'</option>
							<option value="88">'.__( 'Libor Funding pay', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80">'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select id="paytype37" style="display:none;" disabled="disabled" >
							<option value="01">1'.__( '-time payment', 'usces' ).'</option>
							<option value="03">3'.__( '-time payment', 'usces' ).'</option>
							<option value="05">5'.__( '-time payment', 'usces' ).'</option>
							<option value="06">6'.__( '-time payment', 'usces' ).'</option>
							<option value="10">10'.__( '-time payment', 'usces' ).'</option>
							<option value="12">12'.__( '-time payment', 'usces' ).'</option>
							<option value="15">15'.__( '-time payment', 'usces' ).'</option>
							<option value="18">18'.__( '-time payment', 'usces' ).'</option>
							<option value="20">20'.__( '-time payment', 'usces' ).'</option>
							<option value="24">24'.__( '-time payment', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80">'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '
						<select id="paytype36" style="display:none;" disabled="disabled" >
							<option value="01">'.__( 'One time payment', 'usces' ).'</option>
							<option value="88">'.__( 'Libor Funding pay', 'usces' ).'</option>';
					if( 3 == $acting_opts['howtopay'] ) {
						$html_paytype .= '
							<option value="80">'.__( 'Bonus lump-sum payment', 'usces' ).'</option>';
					}
					$html_paytype .= '
						</select>';

					$html_paytype .= '</div>
					</td>
				</tr>';
				}
			}
			$html .= apply_filters( 'usces_filter_escott_secure_form_paytype_token', $html_paytype );
			$html .= '
			</table>';
			$quick = ( 'OK' == $response_member['ResponseCd'] ) ? 'quick' : '';
			$data['status'] = 'OK';
			$data['result'] = $html;
			$data['member'] = $quick;
			wp_send_json( $data );
			break;

		case 'escott_set_token':
			if( !wp_verify_nonce( $_POST['wc_nonce'], 'wc_delivery_secure_nonce' ) ) {
				wp_redirect( USCES_CART_URL );
			}

			$data = array();
			$data['status'] = 'OK';
			$data['result'] = '';
			wp_send_json( $data );
			break;
		}
	}

	/**
	 * 手数料ラベル
	 * @fook   usces_filter_cod_label
	 * @param  $label
	 * @return string $label
	 */
	public function set_fee_label( $label ) {
		global $usces;

		if( is_admin() ) {
			$order_id = ( isset( $_REQUEST['order_id'] ) ) ? $_REQUEST['order_id'] : '';
			if( !empty( $order_id ) ) {
				$order_data = $usces->get_order_data( $order_id, 'direct' );
				$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
				if( $this->acting_flg_conv == $payment['settlement'] || $this->acting_flg_atodene == $payment['settlement'] ) {
					$label = $payment['name'].__( 'Fee', 'usces' );
				}
			}
		} else {
			$usces_entries = $usces->cart->get_entry();
			$payment = $usces->getPayments( $usces_entries['order']['payment_name'] );
			if( $this->acting_flg_conv == $payment['settlement'] || $this->acting_flg_atodene == $payment['settlement'] ) {
				$label = $payment['name'].__( 'Fee', 'usces' );
			}
		}
		return $label;
	}

	/**
	 * 手数料ラベル
	 * @fook   usces_filter_member_history_cod_label
	 * @param  $label $order_id
	 * @return string $label
	 */
	public function set_member_history_fee_label( $label, $order_id ) {
		global $usces;

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$payment = usces_get_payments_by_name( $order_data['order_payment_name'] );
		if( $this->acting_flg_conv == $payment['settlement'] || $this->acting_flg_atodene == $payment['settlement'] ) {
			$label = $payment['name'].__( 'Fee', 'usces' );
		}
		return $label;
	}

	/**
	 * 支払方法
	 * @fook   usces_fiter_the_payment_method
	 * @param  $payments
	 * @return array $payments
	 */
	public function payment_method( $payments ) {

		$conv_exclusion = false;

		if( usces_have_regular_order() ) {
			$conv_exclusion = true;

		} elseif( usces_have_continue_charge() ) {
			$conv_exclusion = true;
		}

		if( $conv_exclusion ) {
			foreach( $payments as $key => $payment ) {
				if( $this->acting_flg_conv == $payment['settlement'] ) {
					unset( $payments[$key] );
				}
			}
		}

		return $payments;
	}

	/**
	 * 決済手数料
	 * @fook   usces_filter_set_cart_fees_cod
	 * @param  $cod_fee $usces_entries $total_items_price $use_point $discount $shipping_charge $amount_by_cod
	 * @return float $cod_fee
	 */
	public function add_fee( $cod_fee, $usces_entries, $total_items_price, $use_point, $discount, $shipping_charge, $amount_by_cod ) {
		global $usces;

		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		if( $this->acting_flg_conv != $payment['settlement'] && $this->acting_flg_atodene != $payment['settlement'] ) {
			return $cod_fee;
		}

		$acting_opts = $this->get_acting_settings();
		$acting = explode( '_', $payment['settlement'] );
		$fee = 0;
		if( !empty( $acting_opts[$acting[2].'_fee_type'] ) ) {
			if( 'fix' == $acting_opts[$acting[2].'_fee_type'] ) {
				$fee = (int)$acting_opts[$acting[2].'_fee'];
			} else {
				$materials = array(
					'total_items_price' => $total_items_price,
					'discount' => $discount,
					'shipping_charge' => $shipping_charge,
					'cod_fee' => $cod_fee,
					'use_point' => $use_point,
				);
				$items_price = $total_items_price - $discount;
				$price = $items_price + $usces->getTax( $items_price, $materials );
				if( $price <= (int)$acting_opts[$acting[2].'_fee_first_amount'] ) {
					$fee = $acting_opts[$acting[2].'_fee_first_fee'];
				} elseif( isset( $acting_opts[$acting[2].'_fee_amounts'] ) && !empty( $acting_opts[$acting[2].'_fee_amounts'] ) ) {
					$last = count( $acting_opts[$acting[2].'_fee_amounts'] ) - 1;
					if( $price > $acting_opts[$acting[2].'_fee_amounts'][$last] ) {
						$fee = $acting_opts[$acting[2].'_fee_end_fee'];
					} else {
						foreach( $acting_opts[$acting[2].'_fee_amounts'] as $key => $value ) {
							if( $price <= $value ) {
								$fee = $acting_opts[$acting[2].'_fee_fees'][$key];
								break;
							}
						}
					}
				} else {
					$fee = $acting_opts[$acting[2].'_fee_end_fee'];
				}
			}
		}
		return $cod_fee + $fee;
	}

	/**
	 * 決済手数料チェック
	 * @fook   usces_filter_delivery_check usces_filter_point_check_last
	 * @param  $mes
	 * @return string $mes
	 */
	public function check_fee_limit( $mes ) {
		global $usces;

		$member = $usces->get_member();
		$usces->set_cart_fees( $member, array() );
		$usces_entries = $usces->cart->get_entry();
		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		if( $this->acting_flg_conv != $payment['settlement'] && $this->acting_flg_atodene != $payment['settlement'] ) {
			return $mes;
		}

		if( 2 == $usces_entries['delivery']['delivery_flag'] ) {
			$mes .= sprintf( __( "If you specify multiple shipping address, you cannot use '%s' payment method.", 'usces' ), $usces_entries['order']['payment_name'] );
			return $mes;
		}

		$acting_opts = $this->get_acting_settings();
		$fee_limit_amount = 0;
		switch( $payment['settlement'] ) {
		case $this->acting_flg_conv:
			if( !empty( $acting_opts['conv_fee_limit_amount'] ) ) {
				$fee_limit_amount = (int)$acting_opts['conv_fee_limit_amount'];
			}
			break;

		case $this->acting_flg_atodene:
			if( !empty( $acting_opts['atodene_fee_limit_amount'] ) ) {
				$fee_limit_amount = (int)$acting_opts['atodene_fee_limit_amount'];
			}
			break;
		}

		if( 0 < $fee_limit_amount && $usces_entries['order']['total_full_price'] > $fee_limit_amount ) {
			$mes .= sprintf( __( 'It exceeds the maximum amount of "%1$s" (total amount %2$s).', 'usces' ), $usces_entries['order']['payment_name'], usces_crform( $fee_limit_amount, true, false, 'return', true ) );
		}

		return $mes;
	}

	/**
	 * 決済オプション取得
	 * @param  -
	 * @return array $acting_settings
	 */
	protected function get_acting_settings() {
		global $usces;

		$acting_settings = ( isset( $usces->options['acting_settings'][$this->paymod_id] ) ) ? $usces->options['acting_settings'][$this->paymod_id] : array();
		return $acting_settings;
	}

	/**
	 * 処理日付生成
	 * @param  -
	 * @return date 'YYYYMMDD'
	 */
	protected function get_transaction_date() {

		$transactiondate = date_i18n( 'Ymd', current_time( 'timestamp' ) );
		return $transactiondate;
	}

	/**
	 * e-SCOTT 会員ID取得
	 * @param  $member_id ($type)
	 * @return string $escott_member_id
	 */
	public function get_quick_kaiin_id( $member_id, $type = '' ) {
		global $usces;

		if( empty( $member_id ) ) {
			return false;
		}

		if( '' == $type ) {
			$escott_member_id = $usces->get_member_meta_value( $this->quick_key_pre.'_member_id', $member_id );
		} else {
			$escott_member_id = $usces->get_member_meta_value( $this->quick_key_pre.'_'.$type.'_member_id', $member_id );
		}
		return $escott_member_id;
	}

	/**
	 * e-SCOTT 会員パスワード取得
	 * @param  $member_id ($type)
	 * @return string $escott_member_passwd
	 */
	public function get_quick_pass( $member_id, $type = '' ) {
		global $usces;

		if( empty( $member_id ) ) {
			return false;
		}

		if( '' == $type ) {
			$escott_member_passwd = $usces->get_member_meta_value( $this->quick_key_pre.'_member_passwd', $member_id );
		} else {
			$escott_member_passwd = $usces->get_member_meta_value( $this->quick_key_pre.'_'.$type.'_member_passwd', $member_id );
		}
		return $escott_member_passwd;
	}

	/**
	 * e-SCOTT 会員ID生成
	 * @param  $member_id
	 * @return string KaiinId
	 */
	public function make_kaiin_id( $member_id ) {

		$digit = 11 - strlen( $member_id );
		$num = str_repeat( "9", $digit );
		$id = sprintf( '%0'.$digit.'d', mt_rand( 1, (int)$num ) );
		return 'w'.$member_id.'i'.$id;
	}

	/**
	 * e-SCOTT 会員パスワード生成
	 * @param  -
	 * @return string KaiinPass
	 */
	public function make_kaiin_pass() {

		$passwd = sprintf( '%012d', mt_rand() );
		return $passwd;
	}

	/**
	 * e-SCOTT 会員情報登録・更新
	 * @param  ($param_list)
	 * @return array $response_member
	 */
	public function escott_member_process( $param_list = array() ) {
		global $usces;

		$member = $usces->get_member();
		$acting_opts = $this->get_acting_settings();
		$params = array();
		$params['send_url'] = $acting_opts['send_url_member'];
		$params['param_list'] = $param_list;

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member['ID'] );
		$KaiinPass = $this->get_quick_pass( $member['ID'] );

		if( empty( $KaiinId ) || empty( $KaiinPass ) ) {
			$KaiinId = $this->make_kaiin_id( $member['ID'] );
			$KaiinPass = $this->make_kaiin_pass();
			$params['param_list']['OperateId'] = '4MemAdd';
			$params['param_list']['KaiinId'] = $KaiinId;
			$params['param_list']['KaiinPass'] = $KaiinPass;
			if( !isset( $param_list['Token'] ) && isset( $_POST['cardno'] ) && isset( $_POST['expyy'] ) && isset( $_POST['expmm'] ) ) {
				$params['param_list']['CardNo'] = trim( $_POST['cardno'] );
				$params['param_list']['CardExp'] = substr( $_POST['expyy'], 2 ).$_POST['expmm'];
				if( 'on' == $acting_opts['seccd'] && isset( $_POST['seccd'] ) ) {
					$params['param_list']['SecCd'] = trim( $_POST['seccd'] );
				}
			}
			//e-SCOTT 新規会員登録
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$usces->set_member_meta_value( $this->quick_key_pre.'_member_id', $KaiinId, $member['ID'] );
				$usces->set_member_meta_value( $this->quick_key_pre.'_member_passwd', $KaiinPass, $member['ID'] );
				$response_member['KaiinId'] = $KaiinId;
				$response_member['KaiinPass'] = $KaiinPass;
				$response_member['use_token'] = true;
			}

		} else {
			$params['param_list']['OperateId'] = '4MemRefM';
			$params['param_list']['KaiinId'] = $KaiinId;
			$params['param_list']['KaiinPass'] = $KaiinPass;
			//e-SCOTT 会員照会
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				if( isset( $_POST['card_change'] ) && '1' == $_POST['card_change'] ) {
					$params['param_list']['OperateId'] = '4MemChg';
					if( !isset( $param_list['Token'] ) && isset( $_POST['cardno'] ) && '8888888888888888' != $_POST['cardno'] && isset( $_POST['expyy'] ) && isset( $_POST['expmm'] ) ) {
						$params['param_list']['CardNo'] = trim( $_POST['cardno'] );
						$params['param_list']['CardExp'] = substr( $_POST['expyy'], 2 ).$_POST['expmm'];
						if( 'on' == $acting_opts['seccd'] && isset( $_POST['seccd'] ) ) {
							$params['param_list']['SecCd'] = trim( $_POST['seccd'] );
						}
					}
					//e-SCOTT 会員更新
					$response_member = $this->connection( $params );
					$response_member['KaiinId'] = $KaiinId;
					$response_member['KaiinPass'] = $KaiinPass;
					$response_member['use_token'] = true;
				} else {
					$response_member['KaiinId'] = $KaiinId;
					$response_member['KaiinPass'] = $KaiinPass;
					$response_member['use_token'] = false;
				}
			}
		}
		return $response_member;
	}

	/**
	 * e-SCOTT 会員情報登録
	 * @param  $member_id
	 * @return array $response_member
	 */
	public function escott_member_register( $member_id ) {
		global $usces;

		$response_member = array( 'ResponseCd'=>'' );
		$acting_opts = $this->get_acting_settings();
		$TransactionDate = $this->get_transaction_date();
		$param_list = array();
		$params = array();

		$KaiinId = $this->make_kaiin_id( $member_id );
		$KaiinPass = $this->make_kaiin_pass();

		//共通部
		$param_list['MerchantId'] = $acting_opts['merchant_id'];
		$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
		$param_list['TransactionDate'] = $TransactionDate;
		$param_list['MerchantFree3'] = $this->merchantfree3;
		$param_list['TenantId'] = $acting_opts['tenant_id'];

		$token = ( isset( $_POST['token'] ) ) ? trim( $_POST['token'] ) : '';
		if( !empty( $token ) ) {
			$param_list['Token'] = $token;
			$param_list['OperateId'] = '1TokenSearch';
			$params['send_url'] = $acting_opts['send_url_token'];
			$params['param_list'] = $param_list;
			//e-SCOTT トークンステータス参照
			$response_token = $this->connection( $params );
			if( 'OK' != $response_token['ResponseCd'] || 'OK' != $response_token['TokenResponseCd'] ) {
				$tokenresponsecd = '';
				if( isset( $response_token['TokenResponseCd'] ) ) {
					$responsecd = explode( '|', $response_token['ResponseCd'].'|'.$response_token['TokenResponseCd'] );
				} else {
					$responsecd = explode( '|', $response_token['ResponseCd'] );
				}
				foreach( (array)$responsecd as $cd ) {
					if( 'OK' != $cd ) {
						$response_token[$cd] = $this->response_message( $cd );
						$tokenresponsecd .= $cd.'|';
					}
				}
				$response_token['ResponseCd'] = rtrim( $tokenresponsecd, '|' );
				return $response_token;
			}
			unset( $params['param_list'] );

		} else {
			$param_list['CardNo'] = trim( $_POST['cardno'] );
			$param_list['CardExp'] = substr( $_POST['expyy'], 2 ).$_POST['expmm'];
			if( 'on' == $acting_opts['seccd'] && !empty( $_POST['seccd'] ) ) {
				$param_list['SecCd'] = trim( $_POST['seccd'] );
			}
		}

		$params['send_url'] = $acting_opts['send_url_member'];
		$params['param_list'] = array_merge( $param_list,
			array(
				'OperateId' => '4MemAdd',
				'KaiinId' => $KaiinId,
				'KaiinPass' => $KaiinPass
			)
		);
		//e-SCOTT 新規会員登録
		$response_member = $this->connection( $params );
		if( 'OK' == $response_member['ResponseCd'] ) {
			$usces->set_member_meta_value( $this->quick_key_pre.'_member_id', $KaiinId, $member_id );
			$usces->set_member_meta_value( $this->quick_key_pre.'_member_passwd', $KaiinPass, $member_id );
			$response_member['KaiinId'] = $KaiinId;
			$response_member['KaiinPass'] = $KaiinPass;
		}
		return $response_member;
	}

	/**
	 * e-SCOTT 会員情報更新
	 * @param  $member_id
	 * @return array $response_member
	 */
	public function escott_member_update( $member_id ) {

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );

		if( $KaiinId && $KaiinPass ) {
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];

			$token = ( isset( $_POST['token'] ) ) ? trim( $_POST['token'] ) : '';
			if( !empty( $token ) ) {
				$param_list['Token'] = $token;
			} else {
				if( !empty( $_POST['cardno'] ) ) {
					$param_list['CardNo'] = trim( $_POST['cardno'] );
				}
				if( 'on' == $acting_opts['seccd'] && !empty( $_POST['seccd'] ) ) {
					$param_list['SecCd'] = trim( $_POST['seccd'] );
				}
				if( !empty( $_POST['expyy'] ) && !empty( $_POST['expmm'] ) ) {
					$param_list['CardExp'] = substr( $_POST['expyy'], 2 ).$_POST['expmm'];
				}
			}

			$params['send_url'] = $acting_opts['send_url_member'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemChg',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員更新
			$response_member = $this->connection( $params );
			if( 'OK' != $response_member['ResponseCd'] ) {
				usces_log( '['.$this->acting_name.'] 4MemChg NG : '.print_r( $response_member, true ), 'acting_transaction.log' );
			}
		}
		return $response_member;
	}

	/**
	 * e-SCOTT 会員情報削除
	 * @param  $member_id
	 * @return array $response_member
	 */
	public function escott_member_delete( $member_id, $forced = false ) {
		global $usces;

		$response_member = array( 'ResponseCd'=>'' );
		$KaiinId = $this->get_quick_kaiin_id( $member_id );
		$KaiinPass = $this->get_quick_pass( $member_id );

		if( $KaiinId && $KaiinPass ) {

			if( $forced ) {//強制削除
				$usces->del_member_meta( $this->quick_key_pre.'_member_id', $member_id );
				$usces->del_member_meta( $this->quick_key_pre.'_member_passwd', $member_id );

			} else {
				$acting_opts = $this->get_acting_settings();
				$TransactionDate = $this->get_transaction_date();
				$param_list = array();
				$params = array();

				//共通部
				$param_list['MerchantId'] = $acting_opts['merchant_id'];
				$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
				$param_list['TransactionDate'] = $TransactionDate;
				$param_list['MerchantFree3'] = $this->merchantfree3;
				$param_list['TenantId'] = $acting_opts['tenant_id'];
				$params['send_url'] = $acting_opts['send_url_member'];
				$params['param_list'] = array_merge( $param_list,
					array(
						'OperateId' => '4MemInval',
						'KaiinId' => $KaiinId,
						'KaiinPass' => $KaiinPass
					)
				);
				//e-SCOTT 会員無効
				$response_member = $this->connection( $params );
				if( 'OK' == $response_member['ResponseCd'] ) {
					$params['param_list'] = array_merge( $param_list,
						array(
							'OperateId' => '4MemDel',
							'KaiinId' => $KaiinId,
							'KaiinPass' => $KaiinPass
						)
					);
					//e-SCOTT 会員削除
					$response_member = array( 'ResponseCd'=>'' );
					$response_member = $this->connection( $params );
					if( 'OK' == $response_member['ResponseCd'] ) {
						$usces->del_member_meta( $this->quick_key_pre.'_member_id', $member_id );
						$usces->del_member_meta( $this->quick_key_pre.'_member_passwd', $member_id );
					} else {
						usces_log( '['.$this->acting_name.'] 4MemDel NG : '.print_r( $response_member, true ), 'acting_transaction.log' );
					}
				} else {
					usces_log( '['.$this->acting_name.'] 4MemInval NG : '.print_r( $response_member, true ), 'acting_transaction.log' );
				}
			}
		}
		return $response_member;
	}

	/**
	 * e-SCOTT 会員情報照会
	 * @param  $member_id ($KaiinId) ($KaiinPass)
	 * @return array $response_member
	 */
	public function escott_member_reference( $member_id, $KaiinId = '', $KaiinPass = '' ) {

		$response_member = array( 'ResponseCd'=>'' );
		if( empty( $KaiinId ) ) {
			$KaiinId = $this->get_quick_kaiin_id( $member_id );
		}
		if( empty( $KaiinPass ) ) {
			$KaiinPass = $this->get_quick_pass( $member_id );
		}

		if( $KaiinId && $KaiinPass ) {
			$acting_opts = $this->get_acting_settings();
			$TransactionDate = $this->get_transaction_date();
			$param_list = array();
			$params = array();

			//共通部
			$param_list['MerchantId'] = $acting_opts['merchant_id'];
			$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
			$param_list['TransactionDate'] = $TransactionDate;
			$param_list['MerchantFree3'] = $this->merchantfree3;
			$param_list['TenantId'] = $acting_opts['tenant_id'];
			$params['send_url'] = $acting_opts['send_url_member'];
			$params['param_list'] = array_merge( $param_list,
				array(
					'OperateId' => '4MemRefM',
					'KaiinId' => $KaiinId,
					'KaiinPass' => $KaiinPass
				)
			);
			//e-SCOTT 会員照会
			$response_member = $this->connection( $params );
			if( 'OK' == $response_member['ResponseCd'] ) {
				$response_member['KaiinId'] = $KaiinId;
				$response_member['KaiinPass'] = $KaiinPass;
			}
		}
		return $response_member;
	}

	/**
	 * e-SCOTT トークン検索
	 * @param  $token
	 * @return array $response_token
	 */
	public function escott_token_search( $token ) {

		$acting_opts = $this->get_acting_settings();
		$TransactionDate = $this->get_transaction_date();
		$param_list = array();
		$params = array();

		//共通部
		$param_list['MerchantId'] = $acting_opts['merchant_id'];
		$param_list['MerchantPass'] = $acting_opts['merchant_pass'];
		$param_list['TransactionDate'] = $TransactionDate;
		$param_list['MerchantFree3'] = $this->merchantfree3;
		$param_list['TenantId'] = $acting_opts['tenant_id'];
		$params['send_url'] = $acting_opts['send_url_token'];
		$params['param_list'] = array_merge( $param_list,
			array(
				'OperateId' => '1TokenSearch',
				'Token' => $token
			)
		);

		//e-SCOTT トークンステータス参照
		$response_token = $this->connection( $params );
		if( 'OK' != $response_token['ResponseCd'] || 'OK' != $response_token['TokenResponseCd'] ) {
			$tokenresponsecd = '';
			if( isset( $response_token['TokenResponseCd'] ) ) {
				$responsecd = explode( '|', $response_token['ResponseCd'].'|'.$response_token['TokenResponseCd'] );
			} else {
				$responsecd = explode( '|', $response_token['ResponseCd'] );
			}
			foreach( (array)$responsecd as $cd ) {
				if( 'OK' != $cd ) {
					$response_token[$cd] = $this->response_message( $cd );
					$tokenresponsecd .= $cd.'|';
				}
			}
			$tokenresponsecd = rtrim( $tokenresponsecd, '|' );
		}
		return $response_token;
	}

	/**
	 * 処理区分名称
	 * @param  $OperateId
	 * @return $operate_name
	 */
	public function get_operate_name( $OperateId ) {

		$operate_name = '';
		switch( $OperateId ) {
		case '1Check'://カードチェック
			$operate_name = __( 'Card check', 'usces' );
			break;
		case '1Auth'://与信
			$operate_name = __( 'Credit', 'usces' );
			break;
		case '1Capture'://売上計上
			$operate_name = __( 'Sales recorded', 'usces' );
			break;
		case '1Gathering'://与信売上計上
		case '11Gathering':
			$operate_name = __( 'Credit sales', 'usces' );
			break;
		case '1Change'://利用額変更
			$operate_name = __( 'Change spending amount', 'usces' );
			break;
		case '1Delete'://取消
		case '11Delete':
			$operate_name = __( 'Unregister', 'usces' );
			break;
		case '1Search'://取引参照
		case '11Search':
			$operate_name = __( 'Transaction reference', 'usces' );
			break;
		case '1ReAuth'://再オーソリ
			$operate_name = __( 'Re-authorization', 'usces' );
			break;
		case '2Add'://登録
			$operate_name = __( 'Register' );
			break;
		case '2Chg'://変更
			$operate_name = __( 'Change' );
			break;
		case '2Del'://削除
			$operate_name = __( 'Unregister', 'usces' );
			break;
		case '4MemAdd'://会員新規
			$operate_name = __( 'New member', 'usces' );
			break;
		case '4MemChg'://会員更新
			$operate_name = __( 'Update member', 'usces' );
			break;
		case '4MemInval'://会員無効
			$operate_name = __( 'Invalid member', 'usces' );
			break;
		case '4MemRef'://会員参照
		case '4MemRefM'://会員参照(マスキング)
		case '4MemRefMulti'://会員参照(同一カード番号返戻)
		case '4MemRefToken'://トークン会員参照
			$operate_name = __( 'Member reference', 'usces' );
			break;
		case '4MemUnInval'://会員無効解除
			$operate_name = __( 'Cancellation of member', 'usces' );
			break;
		case '4MemDel'://会員削除
			$operate_name = __( 'Delete member', 'usces' );
			break;
		case '5Auth'://外貨与信
			$operate_name = __( 'Foreign currency credit', 'usces' );
			break;
		case '5Gathering'://外貨与信売上確定
			$operate_name = __( 'Foreign currency credit sales confirmed', 'usces' );
			break;
		case '5Capture'://外貨売上確定
			$operate_name = __( 'Foreign currency sales fixed', 'usces' );
			break;
		case '5Delete'://外貨取消
			$operate_name = __( 'Foreign currency cancellation', 'usces' );
			break;
		case '5OpeUnInval'://外貨取引保留解除
			$operate_name = __( 'Withdrawal of foreign currency transactions', 'usces' );
			break;
		case 'receipted'://入金
			$operate_name = __( 'Payment', 'usces' );
			break;
		case 'expiration'://期限切れ
			$operate_name = __( 'Expired', 'usces' );
			break;
		}
		return $operate_name;
	}

	/**
	 * 収納機関名称
	 * @param  $CvsCd
	 * @return $cvs_name
	 */
	protected function get_cvs_name( $CvsCd ) {

		$cvs_name = '';
		switch( trim( $CvsCd ) ) {
		case 'LSN':
			$cvs_name = 'ローソン';
			break;
		case 'FAM':
			$cvs_name = 'ファミリーマート';
			break;
		case 'SAK':
			$cvs_name = 'サンクス';
			break;
		case 'CCK':
			$cvs_name = 'サークルK';
			break;
		case 'ATM':
			$cvs_name = 'Pay-easy（ATM）';
			break;
		case 'ONL':
			$cvs_name = 'Pay-easy（オンライン）';
			break;
		case 'LNK':
			$cvs_name = 'Pay-easy（情報リンク）';
			break;
		case 'SEV':
			$cvs_name = 'セブンイレブン';
			break;
		case 'MNS':
			$cvs_name = 'ミニストップ';
			break;
		case 'DAY':
			$cvs_name = 'デイリーヤマザキ';
			break;
		case 'EBK':
			$cvs_name = '楽天銀行';
			break;
		case 'JNB':
			$cvs_name = 'ジャパンネット銀行';
			break;
		case 'EDY':
			$cvs_name = 'Edy';
			break;
		case 'SUI':
			$cvs_name = 'Suica';
			break;
		case 'FFF':
			$cvs_name = 'スリーエフ';
			break;
		case 'JIB':
			$cvs_name = 'じぶん銀行';
			break;
		case 'SNB':
			$cvs_name = '住信SBIネット銀行';
			break;
		case 'SCM':
			$cvs_name = 'セイコーマート';
			break;
		case 'JPM':
			$cvs_name = 'JCBプレモ';
			break;
		}
		return $cvs_name;
	}

	/**
	 * 手数料名称
	 * @param  $fee_type
	 * @return string $fee_name
	 */
	protected function get_fee_name( $fee_type ) {

		$fee_name = '';
		if( 'fix' == $fee_type ) {
			$fee_name = __( 'Fixation', 'usces' );
		} elseif( 'change' == $fee_type ) {
			$fee_name = __( 'Variable', 'usces' );
		}
		return $fee_name;
	}

	/**
	 * エラーコード対応メッセージ
	 * @param  $code
	 * @return string $message
	 */
	public function response_message( $code ) {

		switch( $code ) {
		case 'K01'://当該 OperateId の設定値を網羅しておりません。（送信項目不足、または項目エラー）設定値をご確認の上、再処理行ってください。（オンライン取引電文精査エラー）
			$message = __( 'Online trading message scrutiny error', 'usces' );
			break;
		case 'K02'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「MerchantId」精査エラー）
			$message = __( '\'MerchantId\' scrutiny error', 'usces' );
			break;
		case 'K03'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「MerchantPass」精査エラー）
			$message = __( '\'MerchantPass\' scrutiny error', 'usces' );
			break;
		case 'K04'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「TenantId」精査エラー）
			$message = __( '\'TenantId\' scrutiny error', 'usces' );
			break;
		case 'K05'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「TransactionDate」精査エラー）
			$message = __( '\'TransactionDate\' scrutiny error', 'usces' );
			break;
		case 'K06'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「OperateId」精査エラー）
			$message = __( '\'OperateId\' scrutiny error', 'usces' );
			break;
		case 'K07'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「MerchantFree1」精査エラー）
			$message = __( '\'MerchantFree1\' scrutiny error', 'usces' );
			break;
		case 'K08'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「MerchantFree2」精査エラー）
			$message = __( '\'MerchantFree2\' scrutiny error', 'usces' );
			break;
		case 'K09'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「MerchantFree3」精査エラー）
			$message = __( '\'MerchantFree3\' scrutiny error', 'usces' );
			break;
		case 'K10'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「ProcessId」精査エラー）
			$message = __( '\'ProcessId\' scrutiny error', 'usces' );
			break;
		case 'K11'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「ProcessPass」精査エラー）
			$message = __( '\'ProcessPass\' scrutiny error', 'usces' );
			break;
		case 'K12'://Master 電文で発行された「ProcessId」または「ProcessPass」では無いことを意味します。設定値をご確認の上、再処理行ってください。（項目「ProcessId」または「ProcessPass」不整合エラー）
			$message = __( '\'ProcessId\' or \'ProcessPass\' inconsistency error', 'usces' );
			break;
		case 'K14'://要求された Process 電文の「OperateId」が要求対象外です。例：「1Delete：取消」に対して再度「1Delete：取消」を送信したなど。（OperateId のステータス遷移不整合）
			$message = __( 'Transition inconsistency of OperateId status', 'usces' );
			break;
		case 'K15'://返戻対象となる会員の数が、最大件（30 件）を超えました。（会員参照（同一カード番号返戻）時の返戻対象会員数エラー）
			$message = __( 'Error of the numbers of members subject to return on membership reference (same card number return)', 'usces' );
			break;
		case 'K20'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「CardNo」精査エラー）
			$message = __( '\'CardNo\' scrutiny error', 'usces' );
			break;
		case 'K21'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「CardExp」精査エラー）
			$message = __( '\'CardExp\' scrutiny error', 'usces' );
			break;
		case 'K22'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「PayType」精査エラー）
			$message = __( '\'PayType\' scrutiny error', 'usces' );
			break;
		case 'K23'://半角数字ではないこと、または、利用額変更で元取引と金額が同一となっていることを意味します。 8桁以下(0 以外)の半角数字であること、利用額変更で元取引と金額が同一でないことをご確認の上、再処理を行ってください。（項目「Amount」精査エラー）
			$message = __( '\'Amount\' scrutiny error', 'usces' );
			break;
		case 'K24'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「SecCd」精査エラー）
			$message = __( '\'SecCd\' scrutiny error', 'usces' );
			break;
		case 'K28'://オンライン収納で「半角数字ハイフン≦13桁では無い」設定値を確認の上、再処理を行ってください。（項目「TelNo」精査エラー）
			$message = __( '\'TelNo\' scrutiny error', 'usces' );
			break;
		case 'K30'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「MessageVersionNo3D」精査エラー）
			$message = __( '\'MessageVersionNo3D\' scrutiny error', 'usces' );
			break;
		case 'K31'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「TransactionId3D」精査エラー）
			$message = __( '\'TransactionId3D\' scrutiny error', 'usces' );
			break;
		case 'K32'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「EncordXId3D」精査エラー）
			$message = __( '\'EncordXId3D\' scrutiny error', 'usces' );
			break;
		case 'K33'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「TransactionStatus3D」精査エラー）
			$message = __( '\'TransactionStatus3D\' scrutiny error', 'usces' );
			break;
		case 'K34'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「CAVVAlgorithm3D」精査エラー）
			$message = __( '\'CAVVAlgorithm3D\' scrutiny error', 'usces' );
			break;
		case 'K35'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「CAVV3D」精査エラー）
			$message = __( '\'CAVV3D\' scrutiny error', 'usces' );
			break;
		case 'K36'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「ECI3D」精査エラー）
			$message = __( '\'ECI3D\' scrutiny error', 'usces' );
			break;
		case 'K37'://形式エラーです。設定値をご確認の上、再処理を行ってください。（項目「PANCard3D」精査エラー）
			$message = __( '\'PANCard3D\' scrutiny error', 'usces' );
			break;
		case 'K39'://YYYMMDD形式では無い、または未来日付あることを意味します。設定値をご確認の上、再処理を行ってください。（項目「SalesDate」精査エラー）
			$message = __( '\'SalesDate\' scrutiny error', 'usces' );
			break;
		case 'K40'://取引は OK 判定でしたが McSecCd が「1」アンマッチです。（セキュリティコードアンマッチ検証 NG）
			$message = __( 'Security code error', 'usces' );
			break;
		case 'K45'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「KaiinId」精査エラー）
			$message = __( '\'KaiinId\' scrutiny error', 'usces' );
			break;
		case 'K46'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「KaiinPass」精査エラー）
			$message = __( '\'KaiinPass\' scrutiny error', 'usces' );
			break;
		case 'K47'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「NewKaiinPass」精査エラー）
			$message = __( '\'NewKaiinPass\' scrutiny error', 'usces' );
			break;
		case 'K48'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「EnableCheckUseKbn」精査エラー）
			$message = __( '\'EnableCheckUseKbn\' scrutiny error', 'usces' );
			break;
		case 'K50'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「PayLimit」精査エラー）
			$message = __( '\'PayLimit\' scrutiny error', 'usces' );
			break;
		case 'K51'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「NameKanji」精査エラー）
			$message = __( '\'NameKanji\' scrutiny error', 'usces' );
			break;
		case 'K52'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「NameKana」精査エラー）
			$message = __( '\'NameKana\' scrutiny error', 'usces' );
			break;
		case 'K53'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「ShouhinName」精査エラー）
			$message = __( '\'ShouhinName\' scrutiny error', 'usces' );
			break;
		case 'K54'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「フリーエリア：Free1～7」精査エラー）
			$message = __( '\'Free1 to 7\' scrutiny error', 'usces' );
			break;
		case 'K55'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「Comment」精査エラー）
			$message = __( '\'Comment\' scrutiny error', 'usces' );
			break;
		case 'K60'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「戻り先 URL：ReturnURL」精査エラー）
			$message = __( '\'Return URL: ReturnURL\' scrutiny error', 'usces' );
			break;
		case 'K68'://会員登録機能が未設定となっております。（会員の登録機能は利用できません）
			$message = __( 'Member registration function is unavailabe.', 'usces' );
			break;
		case 'K69'://この会員ID はすでに使用されています。（会員ID の重複エラー）
			$message = __( 'Duplicate error of member ID', 'usces' );
			break;
		case 'K70'://会員削除電文に対して会員が無効状態ではありません。（会員が無効状態ではありません）
			$message = __( 'Member is not in invalid state.', 'usces' );
			break;
		case 'K71'://会員ID・パスワードが一致しません。（会員ID の認証エラー）
			$message = __( 'Member ID authentication error', 'usces' );
			break;
		case 'K73'://会員無効解除電文に対して会員が既に有効となっています。（会員が既に有効となっています）
			$message = __( 'The member is already active.', 'usces' );
			break;
		case 'K74'://会員認証に連続して失敗し、ロックアウトされました。（会員認証に連続して失敗し、ロックアウトされました）
			$message = __( 'Member authentication failed consecutively and was locked out.', 'usces' );
			break;
		case 'K75'://会員は有効ではありません。
			$message = __( 'The member is not valid.', 'usces' );
			break;
		case 'K79'://現在は Login 無効または会員無効状態です。（会員判定エラー（Login 無効または会員無効））
			$message = __( 'Member determination error (Login invalid or Member invalid)', 'usces' );
			break;
		case 'K80'://Master 電文は会員ID が設定されています。Process 電文も会員ID を設定してください。（会員ID 設定不一致（設定が必要））
			$message = __( 'Mismatch of Member ID setting (required the setting)', 'usces' );
			break;
		case 'K81'://Master 電文は会員 ID が未設定です。Process 電文の会員ID も未設定としてください。（会員ID 設定不一致（設定が必要））
			$message = __( 'Mismatch of Member ID setting (required the setting)', 'usces' );
			break;
		case 'K82'://カード番号が適切ではありません。（カード番号の入力内容不正）
			$message = __( 'Invalid input contents of card number', 'usces' );
			break;
		case 'K83'://カード有効期限が適切ではありません。（カード有効期限の入力内容不正）
			$message = __( 'Invalid input contents of card expiration', 'usces' );
			break;
		case 'K84'://会員ID が適切ではありません。（会員ID の入力内容不正）
			$message = __( 'Invalid input contents of member ID', 'usces' );
			break;
		case 'K85'://会員パスワードが適切ではありません。（会員パスワードの入力内容不正）
			$message = __( 'Invalid input contents of member password', 'usces' );
			break;
		case 'K86'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「処理番号：ProcNo」精査エラー）
			$message = __( '\'Processing number: ProcNo\' scrutiny error', 'usces' );
			break;
		case 'K87'://形式エラーです。 設定値をご確認の上、再処理を行ってください。（項目「POST用URL：PostUrl」精査エラー）
			$message = __( '\'POST URL: PostUrl\' scrutiny error', 'usces' );
			break;
		case 'K88'://取引の対象が複数件存在します。弊社までお問い合わせください。（元取引重複エラー）
			$message = __( 'Original deal duplication error', 'usces' );
			break;
		case 'K89'://この処理番号は既に使用されています。（処理番号の重複エラー）
			$message = __( 'Duplicate error of processing number', 'usces' );
			break;
		case 'K96'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。（本システム通信障害発生（タイムアウト））
			$message = __( 'Communication failure occurred in the system (timeout)', 'usces' );
			break;
		case 'K98'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。（本システム内部で軽度障害が発生）
			$message = __( 'Minor failure occurred in the system inside', 'usces' );
			break;
		case 'K99'://弊社までお問い合わせください。（その他例外エラー）
			$message = __( 'Other exception error', 'usces' );
			break;
		case 'KA1'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「通貨コード：CurrencyId」精査エラー）
			$message = __( '\'Currency code: CurrencyId\' scrutiny error', 'usces' );
			break;
		case 'KBX'://重複した電文を受信しました。利用者によりブラウザバックや二重押下が行われた可能性があります。（二重取引エラー）
			$message = __( 'Double transaction error', 'usces' );
			break;
		case 'KBY'://元取引が完了していません。取引状態をご確認ください。（処理未完了エラー）
			$message = __( 'Processing incomplete error', 'usces' );
			break;
		case 'KBZ'://取引対象が取得できません。設定値をご確認の上、再処理を行ってください。（元取引なしエラー）
			$message = __( 'No original transaction error', 'usces' );
			break;
		case 'KG8'://マーチャント ID、マーチャントパスワード認証に連続して失敗し、ロックアウトされました。（事業者認証に連続して失敗し、ロックアウトされました）
			$message = __( 'Failed operator authentication consecutively and was locked out.', 'usces' );
			break;
		case 'KGH'://会員参照の利用は制限されています。（会員参照電文利用設定エラー）
			$message = __( 'Member reference message usage setting error', 'usces' );
			break;
		case 'KH0'://取引が銀聯側でどのような状態か不明。（レスポンス不明）
			$message = __( 'Unknown response', 'usces' );
			break;
		case 'KH4'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「PageLanguage」精査エラー）
			$message = __( '\'PageLanguage\' scrutiny error', 'usces' );
			break;
		case 'KHS'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「SuccessURL」精査エラー）
			$message = __( '\'SuccessURL\' scrutiny error', 'usces' );
			break;
		case 'KHT'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「ErrorURL」精査エラー）
			$message = __( '\'ErrorURL\' scrutiny error', 'usces' );
			break;
		case 'KHU'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「StatusNoticeURL」精査エラー）
			$message = __( '\'StatusNoticeURL\' scrutiny error', 'usces' );
			break;
		case 'KHV'://対象の取引について、再処理実施有無を確認していいただき、必要に応じて再処理を行ってください。（自動取消実施済、再処理を行ってください）
			$message = __( 'Auto-cancel has been executed, please try again.', 'usces' );
			break;
		case 'KHX'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「Token」精査エラー）
			$message = __( '\'Token\' scrutiny error', 'usces' );
			break;
		case 'KHZ'://利用可能なトークンがありません。（利用可能トークンなしエラー）
			$message = __( 'No token available error', 'usces' );
			break;
		case 'KI0'://取引がエンドユーザにより中止されました。（与信売上計上中止（詳細不明））
			$message = __( 'Credit sales canceled (details unknown)', 'usces' );
			break;
		case 'KI1'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「k_TokenNinsyoCode」精査エラー）
			$message = __( '\'k_TokenNinsyoCode\' scrutiny error', 'usces' );
			break;
		case 'KI2'://すでに利用されたトークンです。（使用済みトークンエラー）
			$message = __( 'Used token error', 'usces' );
			break;
		case 'KI3'://トークンの有効期限が切れています。（トークン有効期限切れエラー）
			$message = __( 'Token expiration error', 'usces' );
			break;
		case 'KI4'://形式エラーです。設定値を確認の上、再処理を行ってください。（項目「端末情報」精査エラー）
			$message = __( '\'Terminal information\' scrutiny error', 'usces' );
			break;
		case 'KI5'://同一カード番号の連続入力によりロックされています。
			$message = __( 'It is locked by continuous input of the same card number.', 'usces' );
			break;
		case 'KI6'://同一端末からの連続入力により端末がロックされています。
			$message = __( 'The terminal is locked by continuous input from the same terminal.', 'usces' );
			break;
		case 'KI8'://取引の対象が複数件存在します。
			$message = __( 'There are two or more transaction targets.', 'usces' );
			break;
		case 'KIE'://ApplePay 決済データの値が Null または桁数、データ型不正となっております。（項目「signature」精査エラー）
			$message = __( '\'signature\' scrutiny error', 'usces' );
			break;
		case 'KIF'://Apple Pay 暗号化済み決済データについて signature エラー、オブジェクト識別子が含まれていません。
			$message = __( 'signature Error, object identifier isn\'t included.', 'usces' );
			break;
		case 'KIH'://Apple Pay 暗号化済み決済データについて signature エラー、署名時間が許容時間を超えています。
			$message = __( 'signature Error, signature time exceeds the allowable time.', 'usces' );
			break;
		case 'KIJ'://トークンとプロセスID が設定されています、いずれかを設定ください。（トークン決済、ApplePay 取引許可エラー）
			$message = __( 'Token payment, ApplePay transaction authorization error', 'usces' );
			break;
		case 'KIK'://元取引が ApplePay 取引ではありません。ApplePay 会員登録・会員更新時にクレジットカード決済のプロセス ID で更新した場合に発生いたします。（Apple Pay 会員元取引不整合エラー）
			$message = __( 'Apple Pay membership transaction mismatch error', 'usces' );
			break;
		case 'KIL'://元取引が通常取引ではありません。クレジットカード決済の会員登録・会員更新時に Apple Pay 決済のプロセス ID で更新した場合、または、Apple Pay 会員に対して、会員更新時にカード番号、カード有効期限を設定して更新した場合に発生いたします。（通常会員元取引不整合エラー）
			$message = __( 'Normal member transaction mismatch error', 'usces' );
			break;
		case 'KIW'://形式エラー。設定値を確認の上、再処理を行ってください。（項目「KaiinIdAutoRiyoFlg」精査エラー）
			$message = __( '\'KaiinIdAutoRiyoFlg\' scrutiny error', 'usces' );
			break;
		case 'C01'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。（弊社設定関連エラー）
			$message = __( 'Error related to e-SCOTT setting', 'usces' );
			break;
		case 'C02'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。（e-SCOTT システムエラー）
			$message = __( 'e-SCOTT system error', 'usces' );
			break;
		case 'C03'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は、弊社までお問い合わせください。（e-SCOTT 通信エラー）
			$message = __( 'e-SCOTT communication error', 'usces' );
			break;
		case 'C10'://ご契約のある支払回数（区分）をセットし再処理行ってください。（支払区分エラー）
			$message = __( 'Payment indicator error', 'usces' );
			break;
		case 'C11'://ボーナス払いご利用対象外期間のため、支払区分を変更して再処理を行ってください。（ボーナス期間外エラー）
			$message = __( 'Bonus overtime period error', 'usces' );
			break;
		case 'C12'://ご契約のある分割回数（区分）をセットし再処理行ってください。（分割回数エラー）
			$message = __( 'Number of installments error', 'usces' );
			break;
		case 'C13'://カード有効期限の年月入力間違え。または、有効期限切れカードです。（有効期限切れエラー）
			$message = __( 'Expired error', 'usces' );
			break;
		case 'C14'://取消処理が既に行われております。管理画面で処理状況をご確認ください。（取消済みエラー）
			$message = __( 'Canceled error', 'usces' );
			break;
		case 'C15'://ボーナス払いの下限金額未満によるエラーのため、支払方法を変更して再処理を行ってください。（ボーナス金額下限エラー）
			$message = __( 'Bonus amount lower limit error', 'usces' );
			break;
		case 'C16'://該当のカード会員番号は存在しない。（カード番号エラー）
			$message = __( 'Card number error', 'usces' );
			break;
		case 'C17'://ご契約範囲外のカード番号。もしくは存在しないカード番号体系。（カード番号体系エラー）
			$message = __( 'Card number system error', 'usces' );
			break;
		case 'C18'://オーソリ除外となるカード番号。本エラーを発生するには個別に設定が必要になります。弊社までお問い合わせください。（オーソリ除外対象のカード番号体系エラー）
			$message = __( 'Card number system error subject to authorization exclusion', 'usces' );
			break;
		case 'C70'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。（弊社設定情報エラー）
			$message = __( 'Our company setting information error', 'usces' );
			break;
		case 'C71'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。（弊社設定情報エラー）
			$message = __( 'Our company setting information error', 'usces' );
			break;
		case 'C80'://カード会社システムの停止を意味します。（カード会社センター閉局）
			$message = __( 'Credit card company center closed', 'usces' );
			break;
		case 'C98'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。（その他例外エラー）
			$message = __( 'Other exception error', 'usces' );
			break;
		case 'C99'://貴社送信内容が仕様に沿っているかご確認の上、弊社までお問い合わせください。（その他例外エラー）
			$message = __( 'Other exception error', 'usces' );
			break;
		case 'G12'://クレジットカードが使用不可能です。（カード使用不可）
			$message = __( 'Card unavailable', 'usces' );
			break;
		case 'G22'://支払永久禁止を意味します。（支払永久禁止）
			$message = __( 'Prohibit payment permanently', 'usces' );
			break;
		case 'G30'://取引の判断保留を意味します。（取引判定保留）
			$message = __( 'Transaction decision pending', 'usces' );
			break;
		case 'G42'://暗証番号が正しくありません。※デビットカードの場合、発生する場合があります。（暗証番号エラー）
			$message = __( 'PIN error', 'usces' );
			break;
		case 'G44'://入力されたセキュリティコードが正しくありません。（セキュリティコード誤り）
			$message = __( 'Security code error', 'usces' );
			break;
		case 'G45'://セキュリティコードが入力されていません。（セキュリティコード入力無）
			$message = __( 'Security code input is none', 'usces' );
			break;
		case 'G54'://1日利用回数または金額オーバーです。（利用回数エラー）
			$message = __( 'Error of the number of available use', 'usces' );
			break;
		case 'G55'://1日利用限度額オーバーです。（限度額オーバー）
			$message = __( 'Over limit amount', 'usces' );
			break;
		case 'G56'://クレジットカードが無効です。（無効カード）
			$message = __( 'Invalid card', 'usces' );
			break;
		case 'G60'://事故カードが入力されたことを意味します。（事故カード）
			$message = __( 'Accident card', 'usces' );
			break;
		case 'G61'://無効カードが入力されたことを意味します。（無効カード）
			$message = __( 'Invalid card', 'usces' );
			break;
		case 'G65'://カード番号の入力が誤っていることを意味します。（カード番号エラー）
			$message = __( 'Card number error', 'usces' );
			break;
		case 'G68'://金額の入力が誤っていることを意味します。（金額エラー）
			$message = __( 'Amount error', 'usces' );
			break;
		case 'G72'://ボーナス金額の入力が誤っていることを意味します。（ボーナス額エラー）
			$message = __( 'Bonus amount error', 'usces' );
			break;
		case 'G74'://分割回数の入力が誤っていることを意味します。（分割回数エラー）
			$message = __( 'Number of installments error', 'usces' );
			break;
		case 'G75'://分割払いの下限金額を回ってること意味します。（分割金額エラー）
			$message = __( 'Amount of installments error', 'usces' );
			break;
		case 'G78'://支払方法の入力が誤っていることを意味します。（支払区分エラー）
			$message = __( 'Payment indicator error', 'usces' );
			break;
		case 'G83'://有効期限の入力が誤っていることを意味します。（有効期限エラー）
			$message = __( 'Expiration date error', 'usces' );
			break;
		case 'G84'://承認番号の入力が誤っていることを意味します。（承認番号エラー）
			$message = __( 'Authorization number error', 'usces' );
			break;
		case 'G85'://CAFIS 代行中にエラーが発生したことを意味します。（CAFIS 代行エラー）
			$message = __( 'CAFIS substitution error', 'usces' );
			break;
		case 'G92'://カード会社側で任意にエラーとしたい場合に発生します。（カード会社任意エラー）
			$message = __( 'Card company arbitrary error', 'usces' );
			break;
		case 'G94'://サイクル通番が規定以上または数字でないことを意味します。（サイクル通番エラー）
			$message = __( 'Cycle sequence number error', 'usces' );
			break;
		case 'G95'://カード会社の当該運用業務が終了していることを意味します。（当該業務オンライン終了）
			$message = __( 'The business online termination', 'usces' );
			break;
		case 'G96'://取扱不可のクレジットカードが入力されたことを意味します。（事故カードデータエラー）
			$message = __( 'Accident card data error', 'usces' );
			break;
		case 'G97'://当該要求が拒否され、取扱不能を意味します。（当該要求拒否）
			$message = __( 'The request rejection', 'usces' );
			break;
		case 'G98'://接続されたクレジットカード会社の対象業務ではないことを意味します。（当該自社対象業務エラー）
			$message = __( 'The company-specific task error', 'usces' );
			break;
		case 'G99'://接続要求自社受付拒否を意味します。（接続要求自社受付拒否）
			$message = __( 'Connection request refused acceptance of company', 'usces' );
			break;
		case 'R01'://再処理を行ってください。（システムが起動していないか、または一時的にダウンしています。しばらくしてからもう一度試してください。）
			$message = __( 'The system is not booting or is temporarily down. Please try again later.', 'usces' );
			break;
		case 'R02'://設定値をご確認の上、再処理を行ってください。（メッセージ形式のエラー）
			$message = __( 'Message format error', 'usces' );
			break;
		case 'R03'://弊社までお問い合わせください。（署名エラーを確認してください。）
			$message = __( 'Check for signature error.', 'usces' );
			break;
		case 'R04'://メッセージ取引キー要素がありません。
			$message = __( 'Missing message transaction key element.', 'usces' );
			break;
		case 'R05'://取引不成立。他の銀聯決済カードを使用してください。
			$message = __( 'Transaction unsuccessful. Please use another UnionPay card.', 'usces' );
			break;
		case 'R06'://弊社までお問い合わせください。（加盟店の状態が正しくありません。）
			$message = __( 'Incorrect merchant status.', 'usces' );
			break;
		case 'R07'://この取引の権利はありません。
			$message = __( 'You are not entitled to this transaction.', 'usces' );
			break;
		case 'R08'://取引額が限度額を超過しています。
			$message = __( 'Transaction amount exceeds the limit.', 'usces' );
			break;
		case 'R09'://元取引が存在していないか、または状態が正しくありません。
			$message = __( 'Original transaction does not exist or is in an incorrect state.', 'usces' );
			break;
		case 'R10'://元取引情報と一致しません。
			$message = __( 'Not match with original transaction information.', 'usces' );
			break;
		case 'R11'://照会の回数が限度を超えるか、オペレーションの頻度が過剰です。
			$message = __( 'The number of queries exceeds the limit or the frequency of operations is excessive.', 'usces' );
			break;
		case 'R12'://銀聯リスク制限
			$message = __( 'UnionPay risk limit', 'usces' );
			break;
		case 'R13'://エンドユーザの操作が許容時間内に完了しませんでした。（取引の受付時間外です。）
			$message = __( 'Out of transaction available hours.', 'usces' );
			break;
		case 'R14'://残高からの差し引きはできますが、決済時間を過ぎています。
			$message = __( 'You can deduct from your balance, but the payment available time has passed.', 'usces' );
			break;
		case 'R15'://入力したカード番号が無効です。再度確認のうえ入力してください。
			$message = __( 'The card number is invalid. Please check and input again.', 'usces' );
			break;
		case 'R16'://取引不成立。発行会社はこの加盟店に対応していません。別の銀行カードに変更してください。
			$message = __( 'Transaction unsuccessful. The issuing company does not support this merchant. Please change to a different bank card.', 'usces' );
			break;
		case 'R17'://カードの状態が正しくありません。
			$message = __( 'Incorrect card status', 'usces' );
			break;
		case 'R18'://カードの残高不足
			$message = __( 'Insufficient card balance', 'usces' );
			break;
		case 'R19'://入力した PIN、有効期限または CVN2 のエラー、取引不成立。
			$message = __( 'Eroor of PIN or expiration date or CVN2, transaction failed.', 'usces' );
			break;
		case 'R20'://入力したカード保有者 ID 情報または携帯電話番号が正しくない、検証不成立。
			$message = __( 'The entered cardholder ID information or mobile phone number is incorrect, verification failed.', 'usces' );
			break;
		case 'R21'://PIN 入力回数の限度を超えています。
			$message = __( 'The number of input limit of PIN exceeded.', 'usces' );
			break;
		case 'R22'://あなたの銀行カードは現在この取引には対応していません。
			$message = __( 'Your bank card is currently unavailable this transaction.', 'usces' );
			break;
		case 'R23'://試行時間の限度超過、取引不成立。
			$message = __( 'Trial time limit exceeded, transaction failed.', 'usces' );
			break;
		case 'R24'://取引はリダイレクトされました。カード保有者入力画面になるまでお待ちください。
			$message = __( 'The transaction has been redirected. Please wait until the cardholder entry screen appears.', 'usces' );
			break;
		case 'R25'://動的パスワードまたは SMS 認証コードの確認ができません。
			$message = __( 'The dynamic password or SMS authentication code cannot be verified.', 'usces' );
			break;
		case 'R26'://銀行窓口またはオンライン銀行で銀聯決済サービスにサインアップしていません。
			$message = __( 'You have not signed up for UnionPay payment service at a bank counter or online bank.', 'usces' );
			break;
		case 'R27'://決済カードの有効期限が過ぎています。
			$message = __( 'Payment card has expired.', 'usces' );
			break;
		case 'R28'://有効化には暗号検証が必要です。
			$message = __( 'Validation require an encryption verification.', 'usces' );
			break;
		case 'R29'://この銀行カードは認証済の支払いに有効化されていません。
			$message = __( 'This bank card is not activated for verified payments.', 'usces' );
			break;
		case 'R30'://発行会社の取引権が制限されています。発行会社にお問い合わせください。
			$message = __( 'An issuing company\'s trading rights are restricted. Please contact the issuing company.', 'usces' );
			break;
		case 'R31'://銀行カードは有効ですが、発行会社が SMS認証に対応していません。
			$message = __( 'The bank card is valid, but the issuing company does not support SMS authentication.', 'usces' );
			break;
		case 'R32'://ファイルが存在しません。
			$message = __( 'File doesn\'t exist.', 'usces' );
			break;
		case 'R33'://一般的エラー
			$message = __( 'General error', 'usces' );
			break;
		case 'R34'://弊社までお問い合わせください。
			$message = __( 'Please contact SLN.', 'usces' );
			break;
		case 'R35'://弊社までお問い合わせください。
			$message = __( 'Please contact SLN.', 'usces' );
			break;
		case 'R36'://この取引はありませんでした。
			$message = __( 'This transaction doesn\'t exist.', 'usces' );
			break;
		case 'R37'://取引不成立。詳細は発行会社に問い合わせてください。
			$message = __( 'Transaction failed. Please contact the issuing company for details.', 'usces' );
			break;
		case 'U01'://エンドユーザーの 3D セキュアパスワードが未設定です。
			$message = __( 'The end user\'s 3D secure password is not set.', 'usces' );
			break;
		case 'U02'://3D セキュア未対応カード発行会社です。
			$message = __( 'This card company doesn\'t conpliant with 3D Secure.', 'usces' );
			break;
		case 'U03'://ブランドサーバー、または、イシュアサーバーに障害が発生し認証できませんでした。
			$message = __( 'The brand server or issuer server failed and could not be authenticated.', 'usces' );
			break;
		case 'U04'://3D セキュア認証ができませんでした。各カード会社の基準により 3D セキュア認証画面を表示せずに NGとなる場合がございます。
			$message = __( '3D secure authentication couldn\'t be executed. Depending on the standards of card companies, it may be NG without displaying the 3D secure authentication screen.', 'usces' );
			break;
		case 'U05'://認証システムで改ざんをチェックし 3D セキュア認証ができませんでした。
			$message = __( 'The authentication system checked for tampering and 3D secure authentication failed.', 'usces' );
			break;
		case 'U06'://エンドユーザーの操作でタイムアウト（40 分以上経過）が発生しました。
			$message = __( 'A timeout (over 40 minutes) occurred by end-user operation.', 'usces' );
			break;
		case 'U07'://認証システムにて電文処理中に同じ電文を受信しました。
			$message = __( 'The same message was received during message processing in the authentication system.', 'usces' );
			break;
		case 'U08'://セッション情報が削除された後に重複した電文を受信しました。
			$message = __( 'A duplicate message was received after the session information was deleted.', 'usces' );
			break;
		case 'U09'://3D セキュア未対応ブランドです。
			$message = __( 'This brand doesn\'t conpliant with 3D Secure.', 'usces' );
			break;
		case 'U10'://ブランドのサーバーが停止、または接続不可により 3D セキュア認証ができませんでした。
			$message = __( 'The 3D secure authentication couldn\'t be executed because the branded server stopped or failed connection.', 'usces' );
			break;
		case 'U11'://弊社までお問い合わせください。（認証システム精査エラー）
			$message = __( 'Authentication system scrutiny error', 'usces' );
			break;
		case 'U12'://弊社までお問い合わせください。（加盟店認証エラー）
			$message = __( 'Merchant authentication error', 'usces' );
			break;
		case 'U13'://弊社までお問い合わせください。（認証システム項目エラー）
			$message = __( 'Authentication system item error', 'usces' );
			break;
		case 'U14'://計画停止案内が通知されている場合は、計画停止終了を待って再処理を行ってください。その他は、弊社までお問い合わせください。（メンテナンスによるシステムエラー）
			$message = __( 'If a planned stoppage guidance has been notified, wait for the planned stoppage to end and reprocess.', 'usces' );
			break;
		case 'U95'://一時的に処理できませんでした。リトライしてください。（システムエラー）
			$message = __( 'Failed the process temporarily. Please retry.', 'usces' );
			break;
		case 'U96'://障害報が通知されている場合は、回復報を待って再処理を行ってください。その他は弊社までお問い合せください。（タイムアウト）
			$message = __( 'If a failure report has been notified, wait for the recovery report and reprocess.', 'usces' );
			break;
		case 'U99'://弊社までお問い合せください。（想定外エラー）
			$message = __( 'Unexpected error', 'usces' );
			break;
		case 'W01'://弊社までお問い合わせください。（オンライン収納代行サービス設定エラー）
			$message = __( 'Online Payment Collection Agency Service setting error', 'usces' );
			break;
		case 'W02'://弊社までお問い合わせください。（設定値エラー）
			$message = __( 'Setting value error', 'usces' );
			break;
		case 'W03'://弊社までお問い合わせください。（オンライン収納代行サービス内部エラー（Web系））
			$message = __( 'Online Payment Collection Agency Service internal error (Web type)', 'usces' );
			break;
		case 'W04'://弊社までお問い合わせください。（システム設定エラー）
			$message = __( 'System setting error', 'usces' );
			break;
		case 'W05'://送信内容をご確認の上、再処理を行ってください。エラーが解消しない場合は、弊社までお問い合わせください。（項目設定エラー）
			$message = __( 'Item setting error', 'usces' );
			break;
		case 'W06'://弊社までお問い合わせください。（オンライン収納代行サービス内部エラー（DB系））
			$message = __( 'Online Payment Collection Agency Service internal error (DB type)', 'usces' );
			break;
		case 'W99'://弊社までお問い合わせください。（その他例外エラー）
			$message = __( 'Other exception error', 'usces' );
			break;
		default:
			$message = $code;
		}
		return $message;
	}

	/**
	 * エラーコード対応メッセージ
	 * @param  $code
	 * @return string $message
	 */
	protected function error_message( $code ) {

		switch( $code ) {
		case 'K01'://オンライン取引電文精査エラー
		case 'K02'://項目「MerchantId」精査エラー
		case 'K03'://項目「MerchantPass」精査エラー
		case 'K04'://項目「TenantId」精査エラー
		case 'K05'://項目「TransactionDate」精査エラー
		case 'K06'://項目「OperateId」精査エラー
		case 'K07'://項目「MerchantFree1」精査エラー
		case 'K08'://項目「MerchantFree2」精査エラー
		case 'K09'://項目「MerchantFree3」精査エラー
		case 'K10'://項目「ProcessId」精査エラー
		case 'K11'://項目「ProcessPass」精査エラー
		case 'K12'://項目「ProcessId」または「ProcessPass」不整合エラー
		case 'K14'://OperateId のステータス遷移不整合
		case 'K15'://会員参照（同一カード番号返戻）時の返戻対象会員数エラー
		case 'K22'://項目「PayType」精査エラー
		case 'K23'://項目「Amount」精査エラー
		case 'K39'://項目「SalesDate」精査エラー
		case 'K50'://項目「PayLimit」精査エラー
		case 'K53'://項目「ShouhinName」精査エラー
		case 'K54'://項目「フリーエリア：Free1～7」精査エラー
		case 'K55'://項目「Comment」精査エラー
		case 'K60'://項目「戻り先 URL：ReturnURL」精査エラー
		case 'K68'://会員の登録機能は利用できません
		case 'K69'://会員ID の重複エラー
		case 'K70'://会員が無効状態ではありません
		case 'K71'://会員ID の認証エラー
		case 'K73'://会員が既に有効となっています
		case 'K74'://会員認証に連続して失敗し、ロックアウトされました
		case 'K75'://会員は有効ではありません
		case 'K79'://会員判定エラー（Login 無効または会員無効）
		case 'K80'://会員ID 設定不一致（設定が必要）
		case 'K81'://会員ID 設定不一致（設定が不要）
		case 'K84'://会員ID の入力内容不正
		case 'K85'://会員パスワードの入力内容不正
		case 'K86'://項目「処理番号：ProcNo」精査エラー
		case 'K87'://項目「POST用URL：PostUrl」精査エラー
		case 'K88'://元取引重複エラー
		case 'K89'://処理番号の重複エラー
		case 'K96'://本システム通信障害発生（タイムアウト）
		case 'K98'://本システム内部で軽度障害が発生
		case 'K99'://その他例外エラー
		case 'KA1'://項目「通貨コード：CurrencyId」精査エラー
		case 'KBX'://二重取引エラー
		case 'KBY'://処理未完了エラー
		case 'KBZ'://元取引なしエラー
		case 'KG8'://事業者認証に連続して失敗し、ロックアウトされました
		case 'KGH'://会員参照電文利用設定エラー
		case 'KH0'://レスポンス不明
		case 'KH4'://項目「PageLanguage」精査エラー
		case 'KHS'://項目「SuccessURL」精査エラー
		case 'KHT'://項目「ErrorURL」精査エラー
		case 'KHU'://項目「StatusNoticeURL」精査エラー
		case 'KHV'://自動取消実施済、再処理を行ってください
		case 'KHX'://項目「Token」精査エラー
		case 'KHZ'://利用可能なトークンがありません
		case 'KI0'://与信売上計上中止（詳細不明）
		case 'KI1'://項目「k_TokenNinsyoCode」精査エラー
		case 'KI2'://使用済みトークンエラー
		case 'KI3'://トークン有効期限切れエラー
		case 'KI4'://項目「端末情報」精査エラー
		case 'KI5'://同一カード番号の連続入力によりロックされています。
		case 'KI6'://同一端末からの連続入力により端末がロックされています。
		case 'KI8'://取引の対象が複数件存在します
		case 'KIE'://項目「signature」精査エラー
		case 'KIF'://signature エラー、オブジェクト識別子が含まれていません。
		case 'KIH'://signature エラー、署名時間が許容時間を超えています。
		case 'KIJ'://トークン決済、ApplePay 取引許可エラー
		case 'KIK'://Apple Pay 会員元取引不整合エラー
		case 'KIL'://通常会員元取引不整合エラー
		case 'KIW'://項目「KaiinIdAutoRiyoFlg」精査エラー
		case 'C01'://弊社設定関連エラー
		case 'C02'://e-SCOTT システムエラー
		case 'C03'://e-SCOTT 通信エラー
		case 'C10'://支払区分エラー
		case 'C11'://ボーナス期間外エラー
		case 'C12'://分割回数エラー
		case 'C14'://取消済みエラー
		case 'C18'://オーソリ除外対象のカード番号体系エラー
		case 'C70'://弊社設定情報エラー
		case 'C71'://弊社設定情報エラー
		case 'C80'://カード会社センター閉局
		case 'C98'://その他例外エラー
		case 'C99'://その他例外エラー
		case 'G74'://分割回数エラー
		case 'G78'://支払区分エラー
		case 'G85'://CAFIS 代行エラー
		case 'G92'://カード会社任意エラー
		case 'G94'://サイクル通番エラー
		case 'G95'://当該業務オンライン終了
		case 'G98'://当該自社対象業務エラー
		case 'G99'://接続要求自社受付拒否
		case 'R01'://システムが起動していないか、または一時的にダウンしています。しばらくしてからもう一度試してください。
		case 'R02'://メッセージ形式のエラー。
		case 'R03'://署名エラーを確認してください。
		case 'R04'://メッセージ取引キー要素がありません。
		case 'R05'://取引不成立。他の銀聯決済カードを使用してください。
		case 'R06'://加盟店の状態が正しくありません。
		case 'R07'://この取引の権利はありません。
		case 'R08'://取引額が限度額を超過しています。
		case 'R09'://元取引が存在していないか、または状態が正しくありません。
		case 'R10'://元取引情報と一致しません。
		case 'R11'://照会の回数が限度を超えるか、オペレーションの頻度が過剰です。
		case 'R12'://銀聯リスク制限
		case 'R13'://取引の受付時間外です。
		case 'R14'://残高からの差し引きはできますが、決済時間を過ぎています。
		case 'R15'://入力したカード番号が無効です。再度確認のうえ入力してください。
		case 'R16'://取引不成立。発行会社はこの加盟店に対応していません。別の銀行カードに変更してください。
		case 'R17'://カードの状態が正しくありません。
		case 'R18'://カードの残高不足。
		case 'R19'://入力した PIN、有効期限または CVN2 のエラー、取引不成立。
		case 'R20'://入力したカード保有者ID情報または携帯電話番号が正しくない、検証不成立。
		case 'R21'://PIN 入力回数の限度を超えています。
		case 'R22'://あなたの銀行カードは現在この取引には対応していません。
		case 'R23'://試行時間の限度超過、取引不成立。
		case 'R24'://取引はリダイレクトされました。カード保有者入力画面になるまでお待ちください。
		case 'R25'://動的パスワードまたは SMS 認証コードの確認ができません。
		case 'R26'://銀行窓口またはオンライン銀行で銀聯決済サービスにサインアップしていません。
		case 'R27'://決済カードの有効期限が過ぎています。
		case 'R28'://有効化には暗号検証が必要です。
		case 'R29'://この銀行カードは認証済の支払いに有効化されていません。
		case 'R30'://発行会社の取引権が制限されています。発行会社にお問い合わせください。
		case 'R31'://銀行カードは有効ですが、発行会社が SMS認証に対応していません。
		case 'R32'://ファイルが存在しません。
		case 'R33'://一般的エラー
		case 'R34'://SLN にお問い合わせください。
		case 'R35'://SLN にお問い合わせください。
		case 'R36'://この取引はありませんでした。
		case 'R37'://取引不成立。詳細は発行会社に問い合わせてください。
		case 'W01'://オンライン収納代行サービス設定エラー
		case 'W02'://設定値エラー
		case 'W03'://オンライン収納代行サービス内部エラー（Web系）
		case 'W04'://システム設定エラー
		case 'W05'://項目設定エラー
		case 'W06'://オンライン収納代行サービス内部エラー（DB系）
		case 'W99'://その他例外エラー
			$message = __( 'Sorry, please contact the administrator from the inquiry form.', 'usces' );//恐れ入りますが、お問い合わせフォームより管理者にお問い合わせください。
			break;
		case 'K20'://項目「CardNo」精査エラー
		case 'K82'://カード番号の入力内容不正
		case 'C16'://カード番号エラー
		case 'C17'://カード番号体系エラー
		case 'G65'://カード番号エラー
			$message = __( 'Credit card number is not appropriate.', 'usces' );//指定のカード番号が適切ではありません。
			break;
		case 'K21'://項目「CardExp」精査エラー
		case 'K83'://カード有効期限の入力内容不正
		case 'C13'://有効期限切れエラー
		case 'G83'://有効期限エラー
			$message = __( 'Card expiration date is not appropriate.', 'usces' );//カード有効期限が適切ではありません。
			break;
		case 'K24'://項目「SecCd」精査エラー
		case 'G44'://セキュリティコード誤り
		case 'G45'://セキュリティコード入力無
			$message = __( 'Security code is not appropriate.', 'usces' );//セキュリティコードが適切ではありません。
			break;
		case 'K40'://セキュリティコードアンマッチ検証 NG
		case 'K45'://項目「KaiinId」精査エラー
		case 'K46'://項目「KaiinPass」精査エラー
		case 'K47'://項目「NewKaiinPass」精査エラー
		case 'K48'://項目「EnableCheckUseKbn」精査エラー
		case 'KHX'://項目「Token」精査エラー
		case 'G42'://暗証番号エラー
		case 'G84'://承認番号エラー
			$message = __( 'Credit card information is not appropriate.', 'usces' );//カード情報が適切ではありません。
			break;
		case 'C15'://ボーナス金額下限エラー
			$message = __( 'Please change the payment method and error due to less than the minimum amount of bonus payment.', 'usces' );//ボーナス払いの下限金額未満によるエラーのため、支払方法を変更して再処理を行ってください。
			break;
		case 'G12'://カード使用不可
		case 'G22'://支払永久禁止
		case 'G30'://取引判定保留
		case 'G56'://無効カード
		case 'G60'://事故カード
		case 'G61'://無効カード
		case 'G96'://事故カードデータエラー
		case 'G97'://当該要求拒否
			$message = __( 'Credit card is unusable.', 'usces' );//クレジットカードが使用不可能です。
			break;
		case 'G54'://利用回数エラー
			$message = __( 'It is over 1 day usage or over amount.', 'usces' );//1日利用回数または金額オーバーです。
			break;
		case 'G55'://限度額オーバー
			$message = __( 'It is over limit for 1 day use.', 'usces' );//1日利用限度額オーバーです。
			break;
		case 'G68'://金額エラー
		case 'G72'://ボーナス額エラー
			$message = __( 'Amount is not appropriate.', 'usces' );//金額が適切ではありません。
			break;
		case 'G75'://分割金額エラー
			$message = __( 'It is lower than the lower limit of installment payment.', 'usces' );//分割払いの下限金額を下回っています。
			break;
		case 'K28'://項目「TelNo」精査エラー
			$message = __( 'Customer telephone number is not appropriate.', 'usces' );//お客様電話番号が適切ではありません。
			break;
		case 'K51'://項目「NameKanji」精査エラー
			$message = __( 'Customer name is not entered properly.', 'usces' );//お客様氏名が適切に入力されていません。
			break;
		case 'K52'://項目「NameKana」精査エラー
			$message = __( 'Customer kana name is not entered properly.', 'usces' );//お客様氏名カナが適切に入力されていません。
			break;
		case 'K30'://項目「MessageVersionNo3D」精査エラー
		case 'K31'://項目「TransactionId3D」精査エラー
		case 'K32'://項目「EncordXId3D」精査エラー
		case 'K33'://項目「TransactionStatus3D」精査エラー
		case 'K34'://項目「CAVVAlgorithm3D」精査エラー
		case 'K35'://項目「CAVV3D」精査エラー
		case 'K36'://項目「ECI3D」精査エラー
		case 'K37'://項目「PANCard3D」精査エラー
		case 'U01'://カード発行会社での ID 登録なし
		case 'U02'://3D セキュア未対応カード発行会社
		case 'U03'://ネットワーク等でのエラー発生
		case 'U04'://3D セキュア本人認証 NG
		case 'U05'://3D セキュア改ざんチェック NG
		case 'U06'://セッションの有効期限切れ
		case 'U07'://重複した受付
		case 'U08'://退避したセッション情報が削除されていた
		case 'U09'://3D セキュア対象外ブランド
		case 'U10'://ブランドサーバーが停止、または接続不可
		case 'U11'://認証システム精査エラー
		case 'U12'://加盟店認証エラー
		case 'U13'://認証システム項目エラー
		case 'U14'://メンテナンスによるシステムエラー
		case 'U95'://システムエラー
		case 'U96'://タイムアウト
		case 'U99'://想定外エラー
			$message = __( '3D secure authentication failed.', 'usces' );//3D セキュア認証ができませんでした。
			break;
		default:
			$message = __( 'Sorry, please contact the administrator from the inquiry form.', 'usces' );//恐れ入りますが、お問い合わせフォームより管理者にお問い合わせください。
		}
		return $message;
	}

	/**
	 * 重複送信不可
	 * @param  $acting_flg, $rand
	 * @return -
	 */
	public function duplication_control( $acting_flg, $rand ) {
		global $wpdb;
		$key = 'wc_trans_id';

		if( !usces_check_trans_id( $rand ) ) {
			exit();
		}
		usces_save_trans_id( $rand, $acting_flg );

		$order_meta_table_name = $wpdb->prefix.'usces_order_meta';
		$query = $wpdb->prepare( "SELECT order_id FROM $order_meta_table_name WHERE meta_value = %d AND meta_key = %s", $rand, $key );
		$order_id = $wpdb->get_var( $query );
		if( !$order_id ) {
			return;
		}

		if( $this->acting_flg_card == $acting_flg ) {
			$result_data = array(
				'acting' => $this->acting_card,
				'acting_return' => 1,
				'result' => 1,
				'nonce' => wp_create_nonce( $this->paymod_id.'_transaction' )
			);
			wp_redirect( add_query_arg( $result_data, USCES_CART_URL ) );
			exit();

		} elseif( $this->acting_flg_conv == $acting_flg ) {

		}
	}

	/**
	 * ソケット通信接続
	 * @param  $params
	 * @return array $response_data
	 */
	public function connection( $params ) {

		$gc = new SLNConnection();
		$gc->set_connection_url( $params['send_url'] );
		$gc->set_connection_timeout( 60 );
		$response_value = $gc->send_request( $params['param_list'] );

		if( !empty( $response_value ) ) {
			$response = explode( "\r\n\r\n", $response_value );
			parse_str( $response[1], $response_data );
			if( !array_key_exists( 'ResponseCd', $response_data ) ) {
				$response_data['ResponseCd'] = 'NG';
			}

		} else {
			$response_data['ResponseCd'] = 'NG';
		}
		return $response_data;
	}
}

/**************************************************************************************/
//クラス定義 : SLNConnection
if( !class_exists( 'SLNConnection' ) ) {
	class SLNConnection
	{
		// プロパティ定義
		// 接続先URLアドレス
		private $connection_url;

		// 通信タイムアウト
		private $connection_timeout;

		// メソッド定義
		// コンストラクタ
		// 引数：なし
		// 戻り値：なし
		function __construct()
		{
			// プロパティ初期化
			$this->connection_url = "";
			$this->connection_timeout = 600;
		}

		// 接続先URLアドレスの設定
		// 引数：接続先URLアドレス
		// 戻り値：なし
		function set_connection_url( $connection_url = "" )
		{
			$this->connection_url = $connection_url;
		}

		// 接続先URLアドレスの取得
		// 引数：なし
		// 戻り値：接続先URLアドレス
		function get_connection_url()
		{
			return $this->connection_url;
		}

		// 通信タイムアウト時間（s）の設定
		// 引数：通信タイムアウト時間（s）
		// 戻り値：なし
		function set_connection_timeout( $connection_timeout = 0 )
		{
			$this->connection_timeout = $connection_timeout;
		}

		// 通信タイムアウト時間（s）の取得
		// 引数：なし
		// 戻り値：通信タイムアウト時間（s）
		function get_connection_timeout()
		{
			return $this->connection_timeout;
		}

		// リクエスト送信クラス
		// 引数：リクエストパラメータ（要求電文）配列
		// 戻り値：レスポンスパラメータ（応答電文）配列
		function send_request( &$param_list = array() )
		{
			$rValue = array();

			// パラメータチェック
			if( empty( $param_list ) === false ) {
				// 送信先情報の準備
				$url = parse_url( $this->connection_url );

				// HTTPデータ生成
				$http_data = http_build_query( $param_list );

				// HTTPヘッダ生成
				$http_header = "POST ".$url['path']." HTTP/1.1"."\r\n".
				"Host: ".$url['host']."\r\n".
				"User-Agent: SLN_PAYMENT_CLIENT_PG_PHP_VERSION_1_0"."\r\n".
				"Content-Type: application/x-www-form-urlencoded"."\r\n".
				"Content-Length: ".strlen( $http_data )."\r\n".
				"Connection: close";

				// POSTデータ生成
				$http_post = $http_header."\r\n\r\n".$http_data;

				// 送信処理
				$errno = 0;
				$errstr = "";
				$hm = array();
				$context = stream_context_create(
					array(
						'ssl' => array( 'capture_session_meta' => true )
					)
				);

				// ソケット通信接続
				$fp = @stream_socket_client( 'tlsv1.2://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
				if( $fp === false ) {
					usces_log( 'e-SCOTT send error : '.__( 'TLS 1.2 connection failed.', 'usces' ), 'acting_transaction.log' );//TLS1.2接続に失敗しました
					$fp = @stream_socket_client( 'ssl://'.$url['host'].':443', $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $context );
					if( $fp === false ) {
						usces_log( 'e-SCOTT send error : '.__( 'SSL connection failed.', 'usces' ), 'acting_transaction.log' );//SSL接続に失敗しました
						return $rValue;
					}
				}

				if( $fp !== false ) {
					// 接続後タイムアウト設定
					$result = socket_set_timeout( $fp, $this->connection_timeout );
					if( $result === true ) {
						// データ送信
						fwrite( $fp, $http_post );
						// 応答受信
						$response_data = "";
						while( !feof( $fp ) ) {
							$response_data .= fgets( $fp, 4096 );
						}

						// ソケット通信情報を取得
						$hm = stream_get_meta_data( $fp );
						// ソケット通信切断
						$result = fclose( $fp );
						if( $result === true ) {
							if( $hm['timed_out'] !== true ) {
								// レスポンスデータ生成
								$rValue = $response_data;
							} else {
								// エラー：タイムアウト発生
								usces_log( 'e-SCOTT send error : '.__( 'Timeout occurred during communication.', 'usces' ), 'acting_transaction.log' );//通信中にタイムアウトが発生しました
							}
						} else {
							// エラー：ソケット通信切断失敗
							usces_log( 'e-SCOTT send error : '.__( 'Failed to disconnect from SLN.', 'usces' ), 'acting_transaction.log' );//SLNとの切断に失敗しました
						}
					} else {
						// エラー：タイムアウト設定失敗
						usces_log( 'e-SCOTT send error : '.__( 'Timeout setting failed.', 'usces' ), 'acting_transaction.log' );//タイムアウト設定に失敗しました
					}
				}
			} else {
				// エラー：パラメータ不整合
				usces_log( 'e-SCOTT send error : '.__( 'Invalid request parameter specification.', 'usces' ), 'acting_transaction.log' );//リクエストパラメータの指定が正しくありません
			}
			return $rValue;
		}

		function send_request_unionpay( $url, &$param_list = array() ) {

			if( empty( $param_list ) === false ) {

				$headers = array(
					"Content-Type" => "application/x-www-form-urlencoded;charset=UTF-8",
				);
				$args = array(
					"headers" => $headers,
					"body" => $param_list,
				);

				$response = wp_remote_post( $url, $args );
				$response_body = wp_remote_retrieve_body( $response );
				echo $response_body;

			} else {
				// エラー：パラメータ不整合
				usces_log( 'e-SCOTT send error : '.__( 'Invalid request parameter specification.', 'usces' ), 'acting_transaction.log' );//リクエストパラメータの指定が正しくありません
			}
		}
	}
}
