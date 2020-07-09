<?php
/**
 * イプシロン
 *
 * Version: 1.0.0
 * Author: Collne Inc.
 */
class EPSILON_SETTLEMENT
{
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	protected $paymod_id;			//決済代行会社ID
	protected $pay_method;			//決済種別
	protected $acting_name;			//決済代行会社略称
	protected $acting_formal_name;	//決済代行会社正式名称
	protected $acting_company_url;	//決済代行会社URL

	protected $error_mes;

	public function __construct() {

		$this->paymod_id = 'epsilon';
		$this->pay_method = array(
			'acting_epsilon_card',
			'acting_epsilon_conv'
		);
		$this->acting_name = 'イプシロン';
		$this->acting_formal_name = 'イプシロン';
		$this->acting_company_url = 'http://www.epsilon.jp/';

		$this->initialize_data();

		self::set_noreceipt_status();
		self::set_available_settlement();

		add_action( 'usces_after_cart_instant', array( $this, 'acting_transaction' ) );
		add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement' ), 10, 3 );

		if( is_admin() ) {
			add_action( 'usces_action_settlement_tab_title', array( $this, 'tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'tab_body' ) );
			add_action( 'usces_action_settlement_script', array( $this, 'settlement_script' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'data_update' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_action( 'usces_action_reg_orderdata', array( $this, 'register_order_data' ) );
			if( is_admin() ) {
				add_action( 'usces_action_revival_order_data', array( $this, 'revival_order_data' ), 10, 3 );
				add_action( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
			} else {
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform' ), 10, 5 );
				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 10, 2 );
				add_filter( 'usces_filter_completion_settlement_message', array( $this, 'completion_settlement_message' ), 10, 2 );
			}
		}

		if( $this->is_validity_acting( 'card' ) ) {
			if( is_admin() ) {
				add_action( 'usces_action_admin_member_info', array( $this, 'member_settlement_info' ), 10, 3 );
				add_action( 'usces_action_post_update_memberdata', array( $this, 'member_edit_post' ), 10, 2 );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {

		$options = get_option( 'usces' );
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['epsilon'] ) ) {
			$options['acting_settings']['epsilon']['contract_code'] = '';
			$options['acting_settings']['epsilon']['ope'] = '';
			$options['acting_settings']['epsilon']['card_activate'] = '';
			$options['acting_settings']['epsilon']['multi_currency'] = '';
			$options['acting_settings']['epsilon']['3dsecure'] = '';
			$options['acting_settings']['epsilon']['process_code'] = '';
			$options['acting_settings']['epsilon']['conv_activate'] = '';
			update_option( 'usces', $options );
		}
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
				if( 'acting_epsilon_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
				if( 'acting_epsilon_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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

		default:
			if( 'on' == $acting_opts['activate'] ) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * クレジットカード決済有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_card() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['card_activate'] ) && ( 'on' == $acting_opts['card_activate'] ) ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * コンビニ決済有効判定
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
	 * 未入金ステータス
	 * @param  -
	 * @return -
	 */
	private function set_noreceipt_status() {
		$noreceipt_status = get_option( 'usces_noreceipt_status' );
		if( !in_array( 'acting_epsilon_conv', $noreceipt_status ) ) {
			$noreceipt_status[] = 'acting_epsilon_conv';
			update_option( 'usces_noreceipt_status', $noreceipt_status );
		}
	}

	/**
	 * 利用可能な決済モジュール
	 * @param  -
	 * @return -
	 */
	private function set_available_settlement() {
		$available_settlement = get_option( 'usces_available_settlement' );
		if( !in_array( 'epsilon', $available_settlement ) ) {
			$available_settlement['epsilon'] = $this->acting_name;
			update_option( 'usces_available_settlement', $available_settlement );
		}
	}

	/**
	 * ポイント即時付与
	 * @fook   usces_filter_is_complete_settlement
	 * @param  $complete, $payment_name, $status
	 * @return bool
	 * @echo   -
	 */
	public function is_complete_settlement( $complete, $payment_name, $status ) {
		$payments = usces_get_system_option( 'usces_payment_method', 'name' );
		if( isset( $payments[$payment_name]['settlement'] ) && 'acting_epsilon_card' == $payments[$payment_name]['settlement'] ) {
			$complete = true;
		}
		return $complete;
	}

	/**
	 * 購入完了画面メッセージ
	 * @fook   usces_filter_completion_settlement_message
	 * @param  $html, $usces_entries
	 * @return string $html
	 */
	public function completion_settlement_message( $html, $usces_entries ) {
		if( isset( $_REQUEST['acting'] ) && ( 'epsilon' == $_REQUEST['acting'] ) ) {
			$payments = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
			if( $payments['settlement'] == 'acting_epsilon_conv' ) {
				$html .= '<div id="status_table"><h5>イプシロン・コンビニ決済</h5>
					<p>「お支払いのご案内」は、'.esc_html( $usces_entries['customer']['mailaddress1'] ).'　宛にメールさせていただいております。</p>
					</div>'."\n";
			}
		}
		return $html;
	}

	/**
	 * 内容確認ページ [注文する] ボタン
	 * @fook   usces_filter_confirm_inform
	 * @param  $html, $payments, $acting_flg, $rand, $purchase_disabled
	 * @return form str
	 */
	public function confirm_inform( $html, $payments, $acting_flg, $rand, $purchase_disabled ) {
		if( in_array( $acting_flg, $this->pay_method ) ) {
			$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
				<div class="send">
				'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
				<input name="backDelivery" type="submit" id="back_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
				<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.$purchase_disabled.' /></div>
				<input type="hidden" name="rand" value="'.$rand.'">
				<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">'."\n";
		}
		return $html;
	}

	/**
	 * 決済処理
	 * @fook   usces_action_acting_processing
	 * @param  $acting_flg, $post_query
	 * @return form str
	 */
	public function acting_processing( $acting_flg, $post_query ) {

		if( !in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}

		if( !wp_verify_nonce( $_REQUEST['_nonce'], $acting_flg ) ) {
			wp_redirect( USCES_CART_URL );
		}

		global $usces;
		$usces_entries = $usces->cart->get_entry();
		$cart = $usces->cart->get_cart();
		if( !$usces_entries || !$cart ) {
			wp_redirect( USCES_CART_URL );
		}

		$delim = apply_filters( 'usces_filter_delim', $usces->delim );
		$acting = substr( $acting_flg, 7 );
		$acting_opts = $usces->options['acting_settings']['epsilon'];
		$rand = $_REQUEST['rand'];
		usces_save_order_acting_data( $rand );
		$user_name = mb_strimwidth( $usces_entries['customer']['name1'].$usces_entries['customer']['name2'], 0, 64, '', 'UTF-8' );
		$item_code = mb_convert_kana( $usces->getItemCode( $cart[0]['post_id'] ), 'a', 'UTF-8' );
		$item_name = $usces->getItemName( $cart[0]['post_id'] );
		if( 1 < count( $cart ) ) $item_name .= ' '.__( 'Others', 'usces' );
		if( 32 < mb_strlen( $item_name, 'UTF-8' ) ) $item_name = mb_strimwidth( $item_name, 0, 28, '...', 'UTF-8' );

		switch( $acting_flg ) {
		case 'acting_epsilon_card'://クレジットカード決済
			if( 'on' == $acting_opts['multi_currency'] ) {
				$st_code = '10000-0000-00000-00001-00000-00000-00000';
				$currency_id = $usces->get_currency_code();
				$user_id = '-';
				$process_code = '1';
			} else {
				$st_code = '10000-0000-00000-00000-00000-00000-00000';
				$currency_id = '';
				if( 'on' == $acting_opts['process_code'] ) {
					$member = $usces->get_member();
					$release = ( !empty( $member['ID'] ) ) ? $usces->get_member_meta_value( 'epsilon_process_code_release', $member['ID'] ) : '';
					$user_id = ( !empty( $member['ID'] ) && empty( $release ) ) ? $member['ID'] : '-';
					$process_code = ( $user_id == '-' ) ? '1' : '2';
				} else {
					$user_id = '-';
					$process_code = '1';
				}
			}
			break;
		case 'acting_epsilon_conv'://コンビニ決済
			$st_code = '00100-0000-00000-00000-00000-00000-00000';
			$user_id = '-';
			$currency_id = '';
			$process_code = '1';
			break;
		}

		$send_data = array(
			'version' => '2',
			'contract_code' => $acting_opts['contract_code'],
			'user_id' => $user_id,
			'user_name' => $user_name,
			'user_mail_add' => $usces_entries['customer']['mailaddress1'],
			'item_code' => $item_code,
			'item_name' => $item_name,
			'order_number' => $rand,
			'st_code' => $st_code,
			'mission_code' => '1',
			'item_price' => $usces_entries['order']['total_full_price'],
			'process_code' => $process_code,
			'memo1' => '',
			'memo2' => 'wc1collne',
			'xml' => '1',
			'character_code' => 'UTF8',
			'currency_id' => $currency_id
		);
		if( $acting_flg == 'acting_epsilon_conv' ) {
			$send_data['user_tel'] = str_replace( '-', '', mb_convert_kana( $usces_entries['customer']['tel'], 'a', 'UTF-8' ) );
			$send_data['user_name_kana'] = $usces_entries['customer']['name3'].$usces_entries['customer']['name4'];
		}
		$vars = http_build_query( $send_data );
		$host = parse_url( USCES_CART_URL );
		$interface = parse_url( $acting_opts['send_url'] );

		$request  = "POST ".$acting_opts['send_url']." HTTP/1.1\r\n";
		$request .= "Host: ".$host['host']."\r\n";
		$request .= "User-Agent: PHP Script\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$request .= "Content-Length: ".strlen( $vars )."\r\n";
		$request .= "Connection: close\r\n\r\n";
		$request .= $vars;

		$fp = @stream_socket_client( 'tlsv1.2://'.$interface['host'].':443', $errno, $errstr, 30 );
		if( !$fp ) {
			usces_log( 'Epsilon : TLS(v1.2) Socket Error', 'acting_transaction.log' );
			$fp = fsockopen( 'ssl://'.$interface['host'], 443, $errno, $errstr, 30 );
			if( !$fp ) {
				usces_log( 'Epsilon : SSL Socket Error', 'acting_transaction.log' );
				$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>'SSL/TLS ERROR ('.$errno.')', 'data'=>array( $errstr ) );
				usces_save_order_acting_error( $log );
				header( "location: ".USCES_CART_URL.$delim."acting=epsilon&acting_return=0" );
				exit;
			}
		}

		fwrite( $fp, $request );
		while( !feof( $fp ) ) {
			$scr = fgets( $fp, 1024 );
			preg_match_all( "/<result\s(.*)\s\/>/", $scr, $match, PREG_SET_ORDER );
			if( !empty( $match[0][1] ) ) {
				list( $key, $value ) = explode( '=', $match[0][1] );
				$datas[$key] = urldecode( trim( $value, '"' ) );
			}
		}
		fclose( $fp );
		if( (int)$datas['result'] === 1 ) {
			header( "location: ".$datas['redirect'] );
		} else {
			usces_log( 'Epsilon : Certification Error'.print_r( $datas, true ), 'acting_transaction.log' );
			$err_code = ( isset( $datas['err_code'] ) ) ? urlencode( $datas['err_code'] ) : '';
			$err_detail = ( isset( $datas['err_detail'] ) ) ? urlencode( $datas['err_detail'] ) : '';
			$log = array( 'acting'=>$acting, 'key'=>$rand, 'result'=>$err_code, 'data'=>$datas );
			usces_save_order_acting_error( $log );
			header( "location: ".USCES_CART_URL.$delim."acting=epsilon&acting_return=0&err_code=".$err_code."&err_detail=".$err_detail );
		}
		exit;
	}

	/**
	 * 結果通知処理
	 * @fook   usces_after_cart_instant
	 * @param  -
	 * @return -
	 */
	function acting_transaction() {
		global $wpdb;

		if( isset( $_POST['trans_code'] ) && isset( $_POST['user_id'] ) && isset( $_POST['order_number'] ) ) {
			foreach( $_POST as $key => $value ) {
				$data[$key] = mb_convert_encoding( $value, 'UTF-8', 'SJIS' );
			}

			if( $data['paid'] == '1' ) {
				$table_name = $wpdb->prefix."usces_order";
				$table_meta_name = $wpdb->prefix."usces_order_meta";
				$mquery = $wpdb->prepare( "SELECT order_id FROM $table_meta_name WHERE meta_key = %s AND meta_value = %s", 'settlement_id', $data['order_number'] );
				$order_id = $wpdb->get_var( $mquery );
				if( $order_id == NULL ) {
					usces_log( 'Epsilon conv error1 : '.print_r( $data, true ), 'acting_transaction.log' );
					exit( "0 999 ERROR1" );
				}

				$res = usces_change_order_receipt( $order_id, 'receipted' );
				if( $res === false ) {
					usces_log( 'Epsilon conv error2 : '.print_r( $data, true ), 'acting_transaction.log' );
					exit( "0 999 ERROR2" );
				}

				$datastr = serialize( $data );
				$mquery = $wpdb->prepare( "UPDATE $table_meta_name SET meta_value = %s WHERE meta_key = %s AND order_id = %d", $datastr, 'settlement_id', $order_id );
				$res = $wpdb->query( $mquery );
				if( $res === false ) {
					usces_log( 'Epsilon conv error3 : '.print_r( $data, true ), 'acting_transaction.log' );
					exit( "0 999 ERROR3" );
				}

				usces_action_acting_getpoint( $order_id );

				usces_log( 'Epsilon conv transaction : '.$data['settlement_id'], 'acting_transaction.log' );
				exit( "1" );
			}
		}
	}

	/**
	 * Settlement setting data update
	 * @fook   usces_action_admin_settlement_update
	 * @param  -
	 * @return -
	 */
	public function data_update() {
		global $usces;

		if( 'epsilon' != $_POST['acting'] )
			return;

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['epsilon'] );
		$options['acting_settings']['epsilon']['contract_code'] = ( isset( $_POST['contract_code'] ) ) ? trim( $_POST['contract_code'] ) : '';
		$options['acting_settings']['epsilon']['ope'] = ( isset( $_POST['ope'] ) ) ? $_POST['ope'] : '';
		$options['acting_settings']['epsilon']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['epsilon']['multi_currency'] = ( isset( $_POST['multi_currency'] ) ) ? $_POST['multi_currency'] : '';
		$options['acting_settings']['epsilon']['3dsecure'] = ( isset( $_POST['3dsecure'] ) ) ? $_POST['3dsecure'] : '';
		$options['acting_settings']['epsilon']['process_code'] = ( isset( $_POST['process_code'] ) ) ? $_POST['process_code'] : '';
		$options['acting_settings']['epsilon']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : '';

		if( 'on' == $options['acting_settings']['epsilon']['card_activate'] || 'on' == $options['acting_settings']['epsilon']['conv_activate'] ) {
			if( '' == $options['acting_settings']['epsilon']['contract_code'] ) {
				$this->error_mes .= '※契約番号を入力してください<br />';
			}
			if( '' == $options['acting_settings']['epsilon']['ope'] ) {
				$this->error_mes .= '※稼働環境を選択してください<br />';
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['epsilon']['card_activate'] || 'on' == $options['acting_settings']['epsilon']['conv_activate'] ) {
				$options['acting_settings']['epsilon']['activate'] = 'on';
				if( 'public' == $options['acting_settings']['epsilon']['ope'] ) {
					$options['acting_settings']['epsilon']['send_url'] = 'https://secure.epsilon.jp/cgi-bin/order/receive_order3.cgi';
				} elseif( 'test' == $options['acting_settings']['epsilon']['ope'] ) {
					$options['acting_settings']['epsilon']['send_url'] = 'https://beta.epsilon.jp/cgi-bin/order/receive_order3.cgi';
				}
				$toactive = array();
				if( 'on' == $options['acting_settings']['epsilon']['card_activate'] ) {
					$usces->payment_structure['acting_epsilon_card'] = 'カード決済（イプシロン）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_epsilon_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_epsilon_card'] );
				}
				if( 'on' == $options['acting_settings']['epsilon']['conv_activate'] ) {
					$usces->payment_structure['acting_epsilon_conv'] = 'コンビニ決済（イプシロン）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_epsilon_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_epsilon_conv'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['epsilon']['activate'] = 'off';
				unset( $usces->payment_structure['acting_epsilon_card'] );
				unset( $usces->payment_structure['acting_epsilon_conv'] );
			}
			$deactivate = array();
			foreach( $payment_method as $settlement => $payment ) {
				if( !array_key_exists( $settlement, $usces->payment_structure ) ) {
					if( 'deactivate' != $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[] = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if( 0 < count( $deactivate ) ) {
				$deactivate_message = sprintf( __( "\"Deactivate\" %s of payment method.", 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message;
			}
		} else {
			$usces->action_status = 'error';
			$usces->action_message = __( 'Data have deficiency.', 'usces' );
			$options['acting_settings']['epsilon']['activate'] = 'off';
			unset( $usces->payment_structure['acting_epsilon_card'] );
			unset( $usces->payment_structure['acting_epsilon_conv'] );
			$deactivate = array();
			foreach( $payment_method as $settlement => $payment ) {
				if( in_array( $settlement, $this->pay_method ) ) {
					if( 'deactivate' != $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[] = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if( 0 < count( $deactivate ) ) {
				$deactivate_message = sprintf( __( "\"Deactivate\" %s of payment method.", 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message.__( "Please complete the setup and update the payment method to \"Activate\".", 'usces' );
			}
		}
		ksort( $usces->payment_structure );
		update_option( 'usces', $options );
		update_option( 'usces_payment_structure', $usces->payment_structure );
	}

	/**
	 * Settlement setting page tab title
	 * @fook   usces_action_settlement_tab_title
	 * @param  -
	 * @return -
	 * @echo   str
	 */
	public function tab_title() {
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_'.$this->paymod_id.'">'.$this->acting_name.'</a></li>';
		}
	}

	/**
	 * Settlement setting page tab body
	 * @fook   usces_action_settlement_tab_body
	 * @param  -
	 * @return -
	 * @echo   str
	 */
	public function tab_body() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ):
?>
	<div id="uscestabs_epsilon">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'epsilon' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="epsilon_form" id="epsilon_form">
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_contract_code_epsilon">契約番号</a></th>
				<td><input name="contract_code" type="text" id="contract_code_epsilon" value="<?php echo ( isset( $acting_opts['contract_code'] ) ? $acting_opts['contract_code'] : '' ); ?>" class="regular-text" maxlength="8" /></td>
			</tr>
			<tr id="ex_contract_code_epsilon" class="explanation"><td colspan="2">契約時にイプシロンから発行される契約番号（半角数字8桁）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_ope_epsilon"><?php _e( 'Operation Environment', 'usces' ); ?></a></th>
				<td><label><input name="ope" type="radio" id="ope_epsilon_test" value="test"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="ope" type="radio" id="ope_epsilon_public" value="public"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_ope_epsilon" class="explanation"><td colspan="2">動作環境を切り替えます。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_epsilon_on" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_epsilon_off" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="card_form_epsilon">
				<th><a class="explanation-label" id="label_ex_multi_currency_epsilon">多通貨決済</a></th>
				<td><label><input name="multi_currency" type="radio" class="multi_currency_epsilon" id="multi_currency_epsilon_on" value="on"<?php if( isset( $acting_opts['multi_currency'] ) && $acting_opts['multi_currency'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="multi_currency" type="radio" class="multi_currency_epsilon" id="multi_currency_epsilon_off" value="off"<?php if( isset( $acting_opts['multi_currency'] ) && $acting_opts['multi_currency'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_multi_currency_epsilon" class="explanation card_form_epsilon"><td colspan="2">イプシロンとの契約時にクレジットカード決済（多通貨）の契約をした場合、「利用する」にしてください。</td></tr>
			<tr class="card_form_epsilon">
				<th><a class="explanation-label" id="label_ex_3dsecure_epsilon">3Dセキュア</a></th>
				<td><label><input name="3dsecure" type="radio" class="3dsecure_epsilon" id="3dsecure_epsilon_on" value="on"<?php if( isset( $acting_opts['3dsecure'] ) && $acting_opts['3dsecure'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="3dsecure" type="radio" class="3dsecure_epsilon" id="3dsecure_epsilon_off" value="off"<?php if( isset( $acting_opts['3dsecure'] ) && $acting_opts['3dsecure'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_3dsecure_epsilon" class="explanation card_form_epsilon"><td colspan="2">イプシロンとの契約時に3Dセキュアの契約をした場合、「利用する」にしてください。<br />「多通貨決済」では必須です。「登録済み課金」は併用できません。</td></tr>
			<tr class="card_form_epsilon">
				<th><a class="explanation-label" id="label_ex_process_code_epsilon">登録済み課金</th>
				<td><label><input name="process_code" type="radio" class="process_code_epsilon" id="process_code_epsilon_on" value="on"<?php if( isset( $acting_opts['process_code'] ) && $acting_opts['process_code'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="process_code" type="radio" class="process_code_epsilon" id="process_code_epsilon_off" value="off"<?php if( isset( $acting_opts['process_code'] ) && $acting_opts['process_code'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_process_code_epsilon" class="explanation card_form_epsilon"><td colspan="2">Welcart の会員システムを利用している場合、1度クレジットカード決済を実施すると会員番号で紐付けてクレジットカード番号をイプシロンで保持し、2回目以降のクレジット決済において、クレジットカード番号の入力を不要にします。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ決済</th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_epsilon_on" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_epsilon_off" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
		</table>
		<input name="acting" type="hidden" value="epsilon" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?>の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<!--<p><strong><?php echo $this->acting_formal_name; ?></strong></p>-->
		<!--<a href="http://www.epsilon.jp/" target="_blank"><?php echo $this->acting_name; ?>の詳細はこちら 》</a>-->
		<!--<p>　</p>-->
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへ遷移してカード情報を入力する決済システムです。</p>
	</div>
	</div><!--uscestabs_epsilon-->
<?php
		endif;
	}

	/**
	 * Settlement setting page script
	 * @fook   usces_action_settlement_script
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function settlement_script() {
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'epsilon', (array)$settlement_selected ) ):
?>
	if( 'on' == $( "input[name='card_activate']:checked" ).val() ) {
		$( ".card_form_epsilon" ).css( "display", "" );
	} else {
		$( ".card_form_epsilon" ).css( "display", "none" );
	}
	$( document ).on( "change", "input[name='card_activate']", function() {
		if( 'on' == $( "input[name='card_activate']:checked" ).val() ) {
			$( ".card_form_epsilon" ).css( "display", "" );
		} else {
			$( ".card_form_epsilon" ).css( "display", "none" );
		}
	});
	$( document ).on( "change", ".multi_currency_epsilon", function() {
		if( "on" == $( this ).val() ) {
			$( "#3dsecure_epsilon_on" ).prop( "checked", true );
			$( "#process_code_epsilon_off" ).prop( "checked", true );
		}
	});
	$( document ).on( "change", ".3dsecure_epsilon", function() {
		if( "on" == $( this ).val() ) {
			$( "#process_code_epsilon_off" ).prop( "checked", true );
		} else if( "off" == $( this ).val() ) {
			if( $( "#multi_currency_epsilon_on" ).prop( "checked" ) ) {
				$( "#3dsecure_epsilon_on" ).prop( "checked", true );
			}
		}
	});
	$( document ).on( "change", ".process_code_epsilon", function() {
		if( "on" == $( this ).val() ) {
			$( "#multi_currency_epsilon_off" ).prop( "checked", true );
			$( "#3dsecure_epsilon_off" ).prop( "checked", true );
		}
	});
<?php
		endif;
	}

	/**
	 * Settlement information key
	 * @fook   usces_filter_settle_info_field_keys
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function settlement_info_field_keys( $keys ) {
		array_push( $keys, 'conveni_name', 'conveni_date' );
		return $keys;
	}

	/**
	 * 受注データ復旧処理
	 * @fook   usces_action_revival_order_data
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function revival_order_data( $order_id, $log_key, $acting ) {
		global $usces;
		if( in_array( $acting, $this->pay_method ) ) {
			$usces->set_order_meta_value( 'settlement_id', $log_key, $order_id );
		}
	}

	/**
	 * 会員データ編集画面 登録済み課金情報
	 * @fook   usces_action_admin_member_info
	 * @param  $member_data $member_meta_data $member_history
	 * @return -
	 * @echo   -
	 */
	public function member_settlement_info( $member_data, $member_meta_data, $member_history ) {

		$epsilon_card = false;
		foreach( $member_history as $history ) {
			$payments = usces_get_payments_by_name( $history['payment_name'] );
			$settlement = ( 'acting' == $payments['settlement'] ) ? $payments['module'] : $payments['settlement'];
			if( 'acting_epsilon_card' == $settlement ) {
				$epsilon_card = true;
				break;
			}
		}

		$checked = '';
		foreach( $member_meta_data as $meta ) {
			if( $meta['meta_key'] == 'epsilon_process_code_release' ) {
				$checked = ' checked="checked"';
				break;
			}
		}

		if( 0 < count( $member_history ) && $epsilon_card ): ?>
		<tr>
			<td class="label"><input type="checkbox" name="epsilon_process_code_release" id="epsilon_process_code_release" value="release"<?php echo $checked; ?>></td>
			<td><label for="epsilon_process_code_release">登録済み課金を解除する</label></td>
		</tr>
<?php	endif;
	}

	/**
	 * 会員データ編集画面 登録済み課金解除
	 * @fook   usces_action_post_update_memberdata
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function member_edit_post( $member_id, $res ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( 'on' == $acting_opts['process_code'] ) {
			if( isset( $_POST['epsilon_process_code_release'] ) ) {
				$usces->set_member_meta_value( 'epsilon_process_code_release', 'release', $member_id );
			} else {
				$usces->del_member_meta( 'epsilon_process_code_release', $member_id );
			}
		}
	}

	/**
	 * Register order data.
	 * @fook   usces_action_reg_orderdata
	 * @param  @array $cart, $entry, $order_id, $member_id, $payments, $charging_type, $results
	 * @return -
	 * @echo   -
	 */
	public function register_order_data( $args ) {
		global $usces;
		extract( $args );

		if( isset( $_REQUEST['acting'] ) && isset( $_REQUEST['acting_return'] ) && isset( $_REQUEST['trans_code'] ) && 'epsilon' == $_REQUEST['acting'] ) {
			$usces->set_order_meta_value( 'settlement_id', $_GET['order_number'], $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $_GET['order_number'], $order_id );
		}
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
}
