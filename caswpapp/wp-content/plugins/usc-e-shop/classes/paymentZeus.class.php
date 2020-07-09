<?php
/**
 * ゼウス
 *
 * Version: 1.0.0
 * Author: Collne Inc.
 */
class ZEUS_SETTLEMENT
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
	protected $unavailable_method;	//併用不可決済モジュール

	protected $error_mes;

	public function __construct() {

		$this->paymod_id = 'zeus';
		$this->pay_method = array(
			'acting_zeus_card',
			'acting_zeus_bank',
			'acting_zeus_conv'
		);
		$this->acting_name = 'ゼウス';
		$this->acting_formal_name = __( 'ZEUS Japanese Settlement', 'usces' );

		$this->initialize_data();

		if( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_bank() || $this->is_activate_conv() ) {
			add_action( 'plugins_loaded', array( $this, 'acting_construct' ), 11 );
			add_action( 'usces_after_cart_instant', array( $this, 'acting_transaction' ), 11 );
			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
			add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement' ), 10, 3 );
			add_action( 'usces_filter_completion_settlement_message', array( $this, 'completion_settlement_message' ), 10, 2 );
			add_filter( 'usces_filter_get_link_key', array( $this, 'get_link_key' ), 10, 2 );
			add_action( 'usces_action_revival_order_data', array( $this, 'revival_orderdata' ), 10, 3 );
			if( is_admin() ) {
				add_filter( 'usces_filter_settle_info_field_meta_keys', array( $this, 'settlement_info_field_meta_keys' ) );
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
				add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
			} else {
				add_filter( 'usces_filter_payment_detail', array( $this, 'payment_detail' ), 10, 2 );
				add_filter( 'usces_filter_payments_str', array( $this, 'payments_str' ), 10, 2 );
				add_filter( 'usces_filter_payments_arr', array( $this, 'payments_arr' ), 10, 2 );
				add_filter( 'usces_filter_delivery_check', array( $this, 'delivery_check' ), 15 );
				add_filter( 'usces_filter_delivery_secure_form_loop', array( $this, 'delivery_secure_form_loop' ), 10, 2 );
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
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_message' ) );
			}
		}

		if( $this->is_validity_acting( 'card' ) ) {
			add_action( 'usces_action_admin_member_info', array( $this, 'admin_member_info' ), 10, 3 );
			add_action( 'usces_action_post_update_memberdata', array( $this, 'admin_update_memberdata' ), 10, 2 );
			add_filter( 'usces_filter_uscesL10n', array( $this, 'set_uscesL10n' ), 12, 2 );
			add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ), 9 );
			add_filter( 'usces_filter_available_payment_method', array( $this, 'set_available_payment_method' ) );
			add_filter( 'usces_filter_template_redirect', array( $this, 'member_update_settlement' ), 1 );
			add_action( 'usces_action_member_submenu_list', array( $this, 'e_update_settlement' ) );
			add_filter( 'usces_filter_member_submenu_list', array( $this, 'update_settlement' ), 10, 2 );
			add_filter( 'usces_filter_delete_member_check', array( $this, 'delete_member_check' ), 10, 2 );
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['zeus'] ) ) {
			$options['acting_settings']['zeus']['ipaddrs'] = ( isset( $options['acting_settings']['zeus']['ipaddrs'] ) ) ? $options['acting_settings']['zeus']['ipaddrs'] : array();
			$options['acting_settings']['zeus']['card_url'] = ( isset( $options['acting_settings']['zeus']['card_url'] ) ) ? $options['acting_settings']['zeus']['card_url'] : '';
			$options['acting_settings']['zeus']['card_secureurl'] = ( isset( $options['acting_settings']['zeus']['card_secureurl'] ) ) ? $options['acting_settings']['zeus']['card_secureurl'] : '';
			$options['acting_settings']['zeus']['card_tokenurl'] = ( isset( $options['acting_settings']['zeus']['card_tokenurl'] ) ) ? $options['acting_settings']['zeus']['card_tokenurl'] : '';
			$options['acting_settings']['zeus']['bank_url'] = ( isset( $options['acting_settings']['zeus']['bank_url'] ) ) ? $options['acting_settings']['zeus']['bank_url'] : '';
			$options['acting_settings']['zeus']['conv_url'] = ( isset( $options['acting_settings']['zeus']['conv_url'] ) ) ? $options['acting_settings']['zeus']['conv_url'] : '';
			$options['acting_settings']['zeus']['card_activate'] = ( isset( $options['acting_settings']['zeus']['card_activate'] ) ) ? $options['acting_settings']['zeus']['card_activate'] : 'off';
			$options['acting_settings']['zeus']['clientip'] = ( isset( $options['acting_settings']['zeus']['clientip'] ) ) ? $options['acting_settings']['zeus']['clientip'] : '';
			$options['acting_settings']['zeus']['connection'] = ( isset( $options['acting_settings']['zeus']['connection'] ) ) ? $options['acting_settings']['zeus']['connection'] : '1';
			$options['acting_settings']['zeus']['3dsecur'] = ( isset( $options['acting_settings']['zeus']['3dsecur'] ) ) ? $options['acting_settings']['zeus']['3dsecur'] : '2';
			$options['acting_settings']['zeus']['security'] = ( isset( $options['acting_settings']['zeus']['security'] ) ) ? $options['acting_settings']['zeus']['security'] : '2';
			$options['acting_settings']['zeus']['authkey'] = ( isset( $options['acting_settings']['zeus']['authkey'] ) ) ? $options['acting_settings']['zeus']['authkey'] : '';
			$options['acting_settings']['zeus']['quickcharge'] = ( isset( $options['acting_settings']['zeus']['quickcharge'] ) ) ? $options['acting_settings']['zeus']['quickcharge'] : '';
			$options['acting_settings']['zeus']['batch'] = ( isset( $options['acting_settings']['zeus']['batch'] ) ) ? $options['acting_settings']['zeus']['batch'] : '';
			$options['acting_settings']['zeus']['howpay'] = ( isset( $options['acting_settings']['zeus']['howpay'] ) ) ? $options['acting_settings']['zeus']['howpay'] : '';
			$options['acting_settings']['zeus']['bank_activate'] = ( isset( $options['acting_settings']['zeus']['bank_activate'] ) ) ? $options['acting_settings']['zeus']['bank_activate'] : 'off';
			$options['acting_settings']['zeus']['bank_ope'] = ( isset( $options['acting_settings']['zeus']['bank_ope'] ) ) ? $options['acting_settings']['zeus']['bank_ope'] : '';
			$options['acting_settings']['zeus']['clientip_bank'] = ( isset( $options['acting_settings']['zeus']['clientip_bank'] ) ) ? $options['acting_settings']['zeus']['clientip_bank'] : '';
			$options['acting_settings']['zeus']['testid_bank'] = ( isset( $options['acting_settings']['zeus']['testid_bank'] ) ) ? $options['acting_settings']['zeus']['testid_bank'] : '';
			$options['acting_settings']['zeus']['conv_activate'] = ( isset( $options['acting_settings']['zeus']['conv_activate'] ) ) ? $options['acting_settings']['zeus']['conv_activate'] : 'off';
			$options['acting_settings']['zeus']['conv_ope'] = ( isset( $options['acting_settings']['zeus']['conv_ope'] ) ) ? $options['acting_settings']['zeus']['conv_ope'] : '';
			$options['acting_settings']['zeus']['clientip_conv'] = ( isset( $options['acting_settings']['zeus']['clientip_conv'] ) ) ? $options['acting_settings']['zeus']['clientip_conv'] : '';
			$options['acting_settings']['zeus']['testid_conv'] = ( isset( $options['acting_settings']['zeus']['testid_conv'] ) ) ? $options['acting_settings']['zeus']['testid_conv'] : '';
			$options['acting_settings']['zeus']['test_type_conv'] = ( isset( $options['acting_settings']['zeus']['test_type_conv'] ) ) ? $options['acting_settings']['zeus']['test_type_conv'] : '';
			$options['acting_settings']['zeus']['pay_cvs'] = ( isset( $options['acting_settings']['zeus']['pay_cvs'] ) ) ? $options['acting_settings']['zeus']['pay_cvs'] : array();
			$options['acting_settings']['zeus']['activate'] = ( isset( $options['acting_settings']['zeus']['activate'] ) ) ? $options['acting_settings']['zeus']['activate'] : 'off';
			update_option( 'usces', $options );
		}

		$this->unavailable_method = array( 'acting_welcart_card', 'acting_escott_card', 'acting_sbps_card' );
	}

	/**
	 * 決済有効判定
	 * 引数が指定されたとき、支払方法で使用している場合に「有効」とする
	 * @param  ($type)
	 * @return boolean
	 */
	public function is_validity_acting( $type = '' ) {

		$acting_opts = $this->get_acting_settings();
		if( empty( $acting_opts) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method = false;

		switch( $type ) {
		case 'card':
			foreach( $payment_method as $payment ) {
				if( 'acting_zeus_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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

		case 'bank':
			foreach( $payment_method as $payment ) {
				if( 'acting_zeus_bank' == $payment['settlement'] && 'activate' == $payment['use'] ) {
					$method = true;
					break;
				}
			}
			if( $method && $this->is_activate_bank() ) {
				return true;
			} else {
				return false;
			}
			break;

		case 'conv':
			foreach( $payment_method as $payment ) {
				if( 'acting_zeus_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
	 * 銀行振込決済（入金おまかせサービス）有効判定
	 * @param  -
	 * @return boolean $res
	 */
	public function is_activate_bank() {

		$acting_opts = $this->get_acting_settings();
		if( ( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) && 
			( isset( $acting_opts['bank_activate'] ) && 'on' == $acting_opts['bank_activate'] ) ) {
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
	 * 管理画面メッセージ表示
	 * @fook   admin_notices
	 * @param  -
	 * @return -
	 */
	public function admin_notices() {

		$acting_opts = $this->get_acting_settings();
		if( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ) {
			$options = get_option( 'usces' );
			if( !isset( $acting_opts['vercheck'] ) || '115' != $acting_opts['vercheck'] ) {
				echo '<div class="error"><p>決済に「ゼウス」をご利用の場合は、<a href="'.admin_url( 'admin.php?page=usces_settlement' ).'">「セキュリティーコード」の設定内容を確認</a>して更新ボタンを押してください。設定を更新するとこのメッセージは表示されなくなります。</p></div>';
			}
		}
	}

	/**
	 * @fook   admin_print_footer_scripts
	 * @param  -
	 * @return -
	 * @echo   js
	 */
	public function admin_scripts() {

		$admin_page = ( isset( $_GET['page'] ) ) ? $_GET['page'] : '';
		switch( $admin_page ):
		case 'usces_settlement':
			$settlement_selected = get_option( 'usces_settlement_selected' );
			if( in_array( 'zeus', (array)$settlement_selected ) ):
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	if( '2' == $( "input[name='connection']:checked" ).val() ) {
		$( ".authkey_zeus" ).css( "display", "" );
		$( ".3dsecur_zeus" ).css( "display", "" );
	} else {
		$( ".authkey_zeus" ).css( "display", "none" );
		$( ".3dsecur_zeus" ).css( "display", "none" );
		$( "#3dsecur_zeus_2" ).attr( "checked", "checked" );
	}
	$( document ).on( "click", "input[name='connection']", function() {
		if( '2' == $( "input[name='connection']:checked" ).val() ) {
			$( ".authkey_zeus" ).css( "display", "" );
			$( ".3dsecur_zeus" ).css( "display", "" );
		} else {
			$( ".authkey_zeus" ).css( "display", "none" );
			$( ".3dsecur_zeus" ).css( "display", "none" );
			$( "#3dsecur_zeus_2" ).attr( "checked", "checked" );
		}
	});
	if( '1' == $( "input[name='3dsecur']:checked" ).val() ) {
		$( "#connection_zeus_2" ).attr( "checked", "checked" );
		$( ".authkey_zeus" ).css( "display", "" );
		$( ".3dsecur_zeus" ).css( "display", "" );
	}
	$( document ).on( "click", "input[name='3dsecur']", function() {
		if( '1' == $( "input[name='3dsecur']:checked" ).val() ) {
			$( "#connection_zeus_2" ).attr( "checked", "checked" );
			$( ".authkey_zeus" ).css( "display", "" );
			$( ".3dsecur_zeus" ).css( "display", "" );
		}
	});
});
</script>
<?php
			endif;
			break;
		endswitch;
	}

	/**
	 * 決済オプション登録・更新
	 * @fook   usces_action_admin_settlement_update
	 * @param  -
	 * @return -
	 */
	public function settlement_update() {
		global $usces;

		if( $this->paymod_id != $_POST['acting'] ) {
			return;
		}

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['zeus'] );
		$options['acting_settings']['zeus']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['zeus']['clientip'] = ( isset( $_POST['clientip'] ) ) ? trim( $_POST['clientip'] ) : '';
		$options['acting_settings']['zeus']['connection'] = ( isset( $_POST['connection'] ) ) ? $_POST['connection'] : 1;
		$options['acting_settings']['zeus']['3dsecur'] = ( isset( $_POST['3dsecur'] ) ) ? $_POST['3dsecur'] : 2;
		$options['acting_settings']['zeus']['security'] = ( isset( $_POST['security'] ) ) ? $_POST['security'] : 2;
		if( isset( $_POST['authkey'] ) ) {
			$options['acting_settings']['zeus']['authkey'] = trim( $_POST['authkey'] );
		}
		$options['acting_settings']['zeus']['quickcharge'] = ( isset( $_POST['quickcharge'] ) ) ? $_POST['quickcharge'] : '';
		$options['acting_settings']['zeus']['batch'] = ( isset( $_POST['batch'] ) ) ? $_POST['batch'] : '';
		$options['acting_settings']['zeus']['howpay'] = ( isset( $_POST['howpay'] ) ) ? $_POST['howpay'] : '';
		$options['acting_settings']['zeus']['bank_activate'] = ( isset( $_POST['bank_activate'] ) ) ? $_POST['bank_activate'] : '';
		$options['acting_settings']['zeus']['bank_ope'] = ( isset( $_POST['bank_ope'] ) ) ? $_POST['bank_ope'] : '';
		$options['acting_settings']['zeus']['clientip_bank'] = ( isset( $_POST['clientip_bank'] ) ) ? trim( $_POST['clientip_bank'] ) : '';
		$options['acting_settings']['zeus']['testid_bank'] = ( isset( $_POST['testid_bank'] ) ) ? trim( $_POST['testid_bank'] ) : '';
		$options['acting_settings']['zeus']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['zeus']['conv_ope'] = ( isset( $_POST['conv_ope'] ) ) ? $_POST['conv_ope'] : '';
		$options['acting_settings']['zeus']['clientip_conv'] = ( isset( $_POST['clientip_conv'] ) ) ? trim( $_POST['clientip_conv'] ) : '';
		$options['acting_settings']['zeus']['testid_conv'] = ( isset( $_POST['testid_conv'] ) ) ? trim( $_POST['testid_conv'] ) : '';
		$options['acting_settings']['zeus']['test_type_conv'] = ( ( isset( $_POST['testid_conv'] ) && WCUtils::is_blank( $_POST['testid_conv'] ) ) || ( !isset( $_POST['test_type'] ) ) ) ? 0 : $_POST['test_type'];
		$options['acting_settings']['zeus']['pay_cvs'] = ( isset( $_POST['pay_cvs'] ) ) ? $_POST['pay_cvs'] : array();

		if( 'on' == $options['acting_settings']['zeus']['card_activate'] ) {
			if( WCUtils::is_blank( $_POST['clientip'] ) ) {
				$this->error_mes .= '※カード決済IPコードを入力してください<br />';
			}
			if( isset( $_POST['authkey'] ) && WCUtils::is_blank( $_POST['authkey'] ) && isset( $_POST['security'] ) && 3 == $_POST['security'] ) {
				$this->error_mes .= '※認証キーを入力してください<br />';
			}
			if( isset( $_POST['batch'] ) && 'on' == $_POST['batch'] ) {
				if( isset( $_POST['quickcharge'] ) && 'on' == $_POST['quickcharge'] ) {
				} else {
					$this->error_mes .= '※バッチ処理を利用する場合は、QuickCharge を利用するにしてください<br />';
					$options['acting_settings']['zeus']['quickcharge'] = 'on';
				}
			}
		}
		if( 'on' == $options['acting_settings']['zeus']['bank_activate'] ) {
			if( WCUtils::is_blank( $_POST['clientip_bank'] ) ) {
				$this->error_mes .= '※銀行振込決済（入金おまかせサービス）IPコードを入力してください<br />';
			}
			if( WCUtils::is_blank( $_POST['testid_bank'] ) && isset( $_POST['bank_ope'] ) && 'test' == $_POST['bank_ope'] ) {
				$this->error_mes .= '※銀行振込決済（入金おまかせサービス）テストIDを入力してください<br />';
			}
		}
		if( 'on' == $options['acting_settings']['zeus']['conv_activate'] ) {
			if( WCUtils::is_blank( $_POST['clientip_conv'] ) ) {
				$this->error_mes .= '※コンビニ決済IPコードを入力してください<br />';
			}
			if( WCUtils::is_blank( $_POST['testid_conv'] ) && isset( $_POST['conv_ope'] ) && 'test' == $_POST['conv_ope'] ) {
				$this->error_mes .= '※コンビニ決済テストIDを入力してください<br />';
			}
			if( empty( $_POST['pay_cvs'] ) ) {
				$this->error_mes .= '※コンビニ種類を選択してください<br />';
			}
		}
		if( 'on' == $options['acting_settings']['zeus']['card_activate'] || 'on' == $options['acting_settings']['zeus']['bank_activate'] || 'on' == $options['acting_settings']['zeus']['conv_activate'] ) {
			$unavailable_activate = false;
			foreach( $payment_method as $settlement => $payment ) {
				if( in_array( $settlement, $this->unavailable_method ) && 'activate' == $payment['use'] ) {
					$unavailable_activate = true;
					break;
				}
			}
			if( $unavailable_activate ) {
				$this->error_mes .= __( '* Settlement that can not be used together is activated.', 'usces' ).'<br />';
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['zeus']['card_activate'] || 'on' == $options['acting_settings']['zeus']['bank_activate'] || 'on' == $options['acting_settings']['zeus']['conv_activate'] ) {
				$options['acting_settings']['zeus']['activate'] = 'on';
				$options['acting_settings']['zeus']['ipaddrs'] = array( '210.164.6.67', '202.221.139.50' );
				$toactive = array();
				if( 'on' == $options['acting_settings']['zeus']['card_activate'] ) {
					$options['acting_settings']['zeus']['card_url'] = 'https://linkpt.cardservice.co.jp/cgi-bin/secure.cgi';
					$options['acting_settings']['zeus']['card_secureurl'] = 'https://linkpt.cardservice.co.jp/cgi-bin/secure/api.cgi';
					$options['acting_settings']['zeus']['card_tokenurl'] = 'https://linkpt.cardservice.co.jp/cgi-bin/token/token.cgi';
					$usces->payment_structure['acting_zeus_card'] = 'カード決済（ZEUS）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_zeus_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_zeus_card'] );
				}
				if( 'on' == $options['acting_settings']['zeus']['bank_activate'] ) {
					$options['acting_settings']['zeus']['bank_url'] = 'https://linkpt.cardservice.co.jp/cgi-bin/ebank.cgi';
					$usces->payment_structure['acting_zeus_bank'] = '銀行振込決済（ZEUS）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_zeus_bank' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_zeus_bank'] );
				}
				if( 'on' == $options['acting_settings']['zeus']['conv_activate'] ) {
					$options['acting_settings']['zeus']['conv_url'] = 'https://linkpt.cardservice.co.jp/cgi-bin/cvs.cgi';
					$usces->payment_structure['acting_zeus_conv'] = 'コンビニ決済（ZEUS）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_zeus_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_zeus_conv'] );
				}
				$options['acting_settings']['zeus']['vercheck'] = '115';
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['zeus']['activate'] = 'off';
				unset( $usces->payment_structure['acting_zeus_card'], $usces->payment_structure['acting_zeus_bank'], $usces->payment_structure['acting_zeus_conv'] );
			}
			if( 'on' != $options['acting_settings']['zeus']['quickcharge'] || 'off' == $options['acting_settings']['zeus']['activate'] ) {
				usces_clear_quickcharge( 'zeus_pcid' );
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
			$options['acting_settings']['zeus']['activate'] = 'off';
			unset( $usces->payment_structure['acting_zeus_card'], $usces->payment_structure['acting_zeus_bank'], $usces->payment_structure['acting_zeus_conv'] );
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
	 * クレジット決済設定画面タブ
	 * @fook   usces_action_settlement_tab_title
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_title() {

		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'zeus', (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_zeus">ゼウス</a></li>';
		}
	}

	/**
	 * クレジット決済設定画面フォーム
	 * @fook   usces_action_settlement_tab_body
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_body() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'zeus', (array)$settlement_selected ) ):
?>
	<div id="uscestabs_zeus">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'zeus' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="zeus_form" id="zeus_form">
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_zeus_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_zeus_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_clid_zeus">カード決済IPコード</a></th>
				<td><input name="clientip" type="text" id="clid_zeus" value="<?php if( isset( $acting_opts['clientip'] ) ) echo esc_html( $acting_opts['clientip'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_clid_zeus" class="explanation"><td colspan="2">契約時にゼウスから発行されるクレジットカード決済用のIPコード（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_connection_zeus">接続方式</a></th>
				<td><label><input name="connection" type="radio" id="connection_zeus_1" value="1"<?php if( isset( $acting_opts['connection'] ) && $acting_opts['connection'] == 1 ) echo ' checked="checked"'; ?> /><span>Secure Link</span></label><br />
					<label><input name="connection" type="radio" id="connection_zeus_2" value="2"<?php if( isset( $acting_opts['connection'] ) && $acting_opts['connection'] == 2 ) echo ' checked="checked"'; ?> /><span>Secure API</span></label>
				</td>
			</tr>
			<tr id="ex_connection_zeus" class="explanation"><td colspan="2">認証接続方法。契約に従って指定する必要があります。</td></tr>
			<tr class="authkey_zeus">
				<th><a class="explanation-label" id="label_ex_authkey_zeus">認証キー</a></th>
				<td><input name="authkey" type="text" id="clid_zeus" value="<?php if( isset( $acting_opts['authkey'] ) ) echo esc_html( $acting_opts['authkey'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_authkey_zeus" class="explanation authkey_zeus"><td colspan="2">契約時にゼウスから発行されるSecure API用認証キー（半角数字）</td></tr>
			<tr class="3dsecur_zeus">
				<th><a class="explanation-label" id="label_ex_3dsecur_zeus">3Dセキュア（※）</a></th>
				<td><label><input name="3dsecur" type="radio" id="3dsecur_zeus_1" value="1"<?php if( isset( $acting_opts['3dsecur'] ) && $acting_opts['3dsecur'] == 1 ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="3dsecur" type="radio" id="3dsecur_zeus_2" value="2"<?php if( isset( $acting_opts['3dsecur'] ) && $acting_opts['3dsecur'] == 2 ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_3dsecur_zeus" class="explanation 3dsecur_zeus"><td colspan="2">3Dセキュアを利用するにはSecure APIを利用した接続が必要です。契約に従って指定する必要があります。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_security_zeus">セキュリティーコード（※）</a></th>
				<td><label><input name="security" type="radio" id="security_zeus_1" value="1"<?php if( isset( $acting_opts['security'] ) && $acting_opts['security'] == 1 ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="security" type="radio" id="security_zeus_2" value="2"<?php if( isset( $acting_opts['security'] ) && $acting_opts['security'] == 2 ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_security_zeus" class="explanation"><td colspan="2">セキュリティーコードの入力を必須とするかどうかを指定します。契約に従って指定する必要があります。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_quickcharge_zeus">QuickCharge</a></th>
				<td><label><input name="quickcharge" type="radio" id="quickcharge_zeus_1" value="on"<?php if( isset( $acting_opts['quickcharge'] ) && $acting_opts['quickcharge'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="quickcharge" type="radio" id="quickcharge_zeus_2" value="off"<?php if( isset( $acting_opts['quickcharge'] ) && $acting_opts['quickcharge'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_quickcharge_zeus" class="explanation"><td colspan="2">ログインして一度購入したメンバーは、次の購入時にはカード番号を入力する必要がなくなります。</td></tr>
			<?php if( defined( 'WCEX_AUTO_DELIVERY' ) ): ?>
			<tr>
				<th><a class="explanation-label" id="label_ex_batch_zeus">バッチ処理</a></th>
				<td><label><input name="batch" type="radio" id="batch_zeus_1" value="on"<?php if( isset( $acting_opts['batch'] ) && $acting_opts['batch'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="batch" type="radio" id="batch_zeus_2" value="off"<?php if( isset( $acting_opts['batch'] ) && $acting_opts['batch'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_batch_zeus" class="explanation"><td colspan="2">ゼウス決済を定期購入でご利用の場合は、「利用する」にしてください。また、QuickCharge も「利用する」にしてください。</td></tr>
			<?php endif; ?>
			<tr>
				<th><a class="explanation-label" id="label_ex_howpay_zeus">お客様の支払方法</a></th>
				<td><label><input name="howpay" type="radio" id="howpay_zeus_1" value="on"<?php if( isset( $acting_opts['howpay'] ) && $acting_opts['howpay'] == 'on' ) echo ' checked="checked"'; ?> /><span>分割払いに対応する</span></label><br />
					<label><input name="howpay" type="radio" id="howpay_zeus_2" value="off"<?php if( isset( $acting_opts['howpay'] ) && $acting_opts['howpay'] == 'off' ) echo ' checked="checked"'; ?> /><span>一括払いのみ</span></label>
				</td>
			</tr>
			<tr id="ex_howpay_zeus" class="explanation"><td colspan="2">お客様が利用するクレジットカードのカード会社により、選択できる分割回数が異なります。<?php if( defined( 'WCEX_AUTO_DELIVERY' ) ) echo '定期購入商品は常に「一括払い」で決済されます。'; ?></td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_bank_zeus">銀行振込決済（入金おまかせサービス）</a></th>
				<td><label><input name="bank_activate" type="radio" id="bank_activate_zeus_1" value="on"<?php if( isset( $acting_opts['bank_activate'] ) && $acting_opts['bank_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="bank_activate" type="radio" id="bank_activate_zeus_2" value="off"<?php if( isset( $acting_opts['bank_activate'] ) && $acting_opts['bank_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_bank_zeus" class="explanation"><td colspan="2">銀行振込支払いの自動照会機能です。振込みがあった場合、自動的に入金済みになり、入金確認メールが自動送信されます。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_bank_ope_zeus">稼働環境</a></th>
				<td><label><input name="bank_ope" type="radio" id="bank_ope_zeus_1" value="test"<?php if( isset( $acting_opts['bank_ope'] ) && $acting_opts['bank_ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="bank_ope" type="radio" id="bank_ope_zeus_2" value="public"<?php if( isset( $acting_opts['bank_ope'] ) && $acting_opts['bank_ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_bank_ope_zeus" class="explanation"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_bank_clid_zeus">入金おまかせIPコード</a></th>
				<td><input name="clientip_bank" type="text" id="bank_clid_zeus" value="<?php if( isset( $acting_opts['clientip_bank'] ) ) echo esc_html( $acting_opts['clientip_bank'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_bank_clid_zeus" class="explanation"><td colspan="2">契約時にゼウスから発行される入金おまかせサービス用のIPコード（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_bank_testid_zeus">テストID</a></th>
				<td><input name="testid_bank" type="text" id="testid_bank_zeus" value="<?php if( isset( $acting_opts['testid_bank'] ) ) echo esc_html( $acting_opts['testid_bank'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_bank_testid_zeus" class="explanation"><td colspan="2">契約時にゼウスから発行される入金おまかせサービス接続テストで必要なテストID（半角数字）</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_zeus">コンビニ決済サービス</a></th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_zeus_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_zeus_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_conv_zeus" class="explanation"><td colspan="2">コンビニ支払いができる決済サービスです。払い込みがあった場合、自動的に入金済みになります。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_ope_zeus">稼働環境</a></th>
				<td><label><input name="conv_ope" type="radio" id="conv_ope_zeus_1" value="test"<?php if( isset( $acting_opts['conv_ope'] ) && $acting_opts['conv_ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="conv_ope" type="radio" id="conv_ope_zeus_2" value="public"<?php if( isset( $acting_opts['conv_ope'] ) && $acting_opts['conv_ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_conv_ope_zeus" class="explanation"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_clid_zeus">コンビニ決済IPコード</a></th>
				<td><input name="clientip_conv" type="text" id="conv_clid_zeus" value="<?php if( isset( $acting_opts['clientip_conv'] ) ) echo esc_html( $acting_opts['clientip_conv'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_conv_clid_zeus" class="explanation"><td colspan="2">契約時にゼウスから発行されるコンビニ決済サービス用のIPコード（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_testid_zeus">テストID</a></th>
				<td><input name="testid_conv" type="text" id="testid_conv_zeus" value="<?php if( isset( $acting_opts['testid_conv'] ) ) echo esc_html( $acting_opts['testid_conv'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_conv_testid_zeus" class="explanation"><td colspan="2">契約時にゼウスから発行されるコンビニ決済サービス接続テストで必要なテストID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_testtype_zeus">テストタイプ</a></th>
				<td><label><input name="test_type" type="radio" id="conv_testtype_zeus_1" value="0"<?php if( isset( $acting_opts['test_type_conv'] ) && WCUtils::is_zero( $acting_opts['test_type_conv'] ) ) echo ' checked="checked"'; ?> /><span>入金テスト無し</span></label><br />
					<label><input name="test_type" type="radio" id="conv_testtype_zeus_2" value="1"<?php if( isset( $acting_opts['test_type_conv'] ) && $acting_opts['test_type_conv'] == 1 ) echo ' checked="checked"'; ?> /><span>売上確定テスト</span></label><br />
					<label><input name="test_type" type="radio" id="conv_testtype_zeus_3" value="2"<?php if( isset( $acting_opts['test_type_conv'] ) && $acting_opts['test_type_conv'] == 2 ) echo ' checked="checked"'; ?> /><span>売上取消テスト</span></label>
				</td>
			</tr>
			<tr id="ex_conv_testtype_zeus" class="explanation"><td colspan="2">テスト環境でのテストタイプを指定します。</td></tr>
			<tr>
				<th rowspan="7"><a class="explanation-label" id="label_ex_pay_cvs_zeus">コンビニ種類</a></th>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D001" value="D001"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D001', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D001' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D002" value="D002"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D002', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D002' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D030" value="D030"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D030', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D030' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D040" value="D040"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D040', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D040' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D015" value="D015"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D015', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D015' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D050" value="D050"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D050', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D050' ) ); ?></span></label></td>
			</tr>
			<tr>
				<td><label><input name="pay_cvs[]" type="checkbox" id="pay_cvs_D060" value="D060"<?php if( isset( $acting_opts['pay_cvs'] ) && in_array( 'D060', $acting_opts['pay_cvs'] ) ) echo ' checked'; ?> /><span><?php echo esc_html(usces_get_conv_name( 'D060' ) ); ?></span></label></td>
			</tr>
			<tr id="ex_pay_cvs_zeus" class="explanation"><td colspan="2">契約時にご利用のお申込みをいただいたコンビニを選択します。</td></tr>
		</table>
		<input name="acting" type="hidden" value="zeus" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="ゼウスの設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php _e( 'ZEUS Japanese Settlement', 'usces' ); ?></strong></p>
		<a href="https://www.cardservice.co.jp/" target="_blank">ゼウス決済サービスの詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「非通過型・トークン方式」の決済システムです。</p>
		<p>「非通過型」とは、決済会社のページへは遷移せず、Welcart のページのみで完結する決済システムです。<br />
		デザインの統一されたスタイリッシュな決済が可能です。但し、カード番号を扱いますので専用SSLが必須となります。<br />
		入力されたカード番号はトークンに置き換えてゼウスのシステムに送信されますので、Welcart に保存することはありません。</p>
		<p>　</p>
		<p>※ 3Dセキュアとセキュリティーコード </p>
		<p>3Dセキュアとおよびセキュリティーコードの利用は、決済サービス契約時に決定します。契約内容に従って指定しないと正常に動作しませんのでご注意ください。<br />
		詳しくは<a href="http://www.cardservice.co.jp/" target="_blank">株式会社ゼウス</a>（代表：03-3498-9030）にお問い合わせください。</p>
		<p>　</p>
		<p><strong>テスト稼動について</strong></p>
		<p>銀行振込決済（入金おまかせサービス）およびコンビニ決済のテストを行う際は、「稼働環境」で「テスト環境」を選択し、「テストID」の項目にゼウスから発行されるテストIDを入力してください。<br />
		また、本稼働の際には、「本番環境」を選択して更新してください。</p>
	</div>
	</div><!--uscestabs_zeus-->
<?php
		endif;
	}

	/**
	 * 併用不可決済モジュール
	 * @fook   usces_filter_unavailable_payments
	 * @param  -
	 * @return -
	 */
	public function unavailable_payments() {
		return $this->unavailable_method;
	}

	/**
	 * 結果通知前処理
	 * @fook   usces_construct
	 * @param  -
	 * @return -
	 */
	public function acting_construct() {

		$acting_opts = $this->get_acting_settings();
		if( in_array( $_SERVER['REMOTE_ADDR'], $acting_opts['ipaddrs'] ) ) {
			$rand = ( isset( $_REQUEST['sendpoint'] ) ) ? $_REQUEST['sendpoint'] : '';
			$datas = usces_get_order_acting_data( $rand );
			if( empty( $datas['sesid'] ) ) {
				if( isset( $_REQUEST['result'] ) && 'OK' == $_REQUEST['result'] && isset( $_REQUEST['money'] ) && '0' == $_REQUEST['money'] ) {
				} else {
					$log = array( 'acting'=>'zeus', 'key'=>$rand, 'result'=>'SESSION ERROR', 'data'=>$_REQUEST );
					usces_save_order_acting_error( $log );
					usces_log( 'zeus construct : error1', 'acting_transaction.log' );
				}
			} else {
				if( isset( $_REQUEST['acting'] ) && 'zeus_bank' == $_REQUEST['acting'] ) {
					usces_restore_order_acting_data( $rand );
				}
				usces_log( 'zeus construct : '.$_REQUEST['sendpoint'], 'acting_transaction.log' );
			}
		}
	}

	/**
	 * 結果通知処理
	 * @fook   usces_after_cart_instant
	 * @param  -
	 * @return -
	 */
	public function acting_transaction() {
		global $usces;

		//*** zeus_card ***//
		if( isset( $_REQUEST['acting'] ) && 'zeus_card' == $_REQUEST['acting'] && isset( $_REQUEST['result'] ) && isset( $_REQUEST['ordd'] ) ) {
			foreach( $_REQUEST as $key => $value ) {
				if( 'uscesid' == $key ) {
				} else {
					$data[$key] = $value;
				}
			}
			usces_log( 'zeus card cgi : '.print_r( $data, true ), 'acting_transaction.log' );

			$rand = ( isset( $data['sendpoint'] ) ) ? $data['sendpoint'] : '';
			if( empty( $rand ) ) {
				$log = array( 'acting'=>$data['acting'], 'key'=>'(empty key)', 'result'=>$data['result'], 'data'=>$data );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus card error1 : '.print_r( $data, true ), 'acting_transaction.log' );
				header( "HTTP/1.0 200 OK" );
				die( 'error1' );
			}

			if( 'OK' == $data['result'] ) {
				$acting_opts = $this->get_acting_settings();
				if( 'on' == $acting_opts['quickcharge'] && !empty( $data['sendid'] ) ) {
					if( isset( $data['cardnumber'] ) ) {
						$usces->set_member_meta_value( 'zeus_partofcard', $data['cardnumber'], $data['sendid'] );
						$usces->set_member_meta_value( 'zeus_pcid', '8888888888888882', $data['sendid'] );
					}
				}
				header( "HTTP/1.0 200 OK" );
				die( 'zeus' );

			} else {
				$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>$data['result'], 'data'=>$data );
				usces_save_order_acting_error( $log );
				header( "HTTP/1.0 200 OK" );
				die( 'error3' );
			}

		//*** zeus_bank ***//
		} elseif( isset( $_REQUEST['acting'] ) && 'zeus_bank' == $_REQUEST['acting'] && isset( $_REQUEST['order_no'] ) && isset( $_REQUEST['tracking_no'] ) ) {
			foreach( $_REQUEST as $key => $value ) {
				if( 'uscesid' == $key ) {
				} else {
					$data[$key] = $value;
				}
			}
			usces_log( 'zeus bank cgi data : '.print_r( $data, true ), 'acting_transaction.log' );

			if( '04' === $data['status'] || '05' === $data['status'] ) {
				$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>$data['status'], 'data'=>$data );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus bank error0 : status='.$data['status'], 'acting_transaction.log' );
				header( "HTTP/1.0 200 OK" );
				die( 'error0' );
			}

			$order_id = $this->get_order_id( $data['tracking_no'] );
			if( $order_id == NULL ) {
				$res = $usces->order_processing();
				if( 'error' == $res ) {
					$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>'ORDER DATA REGISTERED ERROR', 'data'=>$data );
					usces_save_order_acting_error( $log );
					usces_log( 'zeus bank error1 : order_processing', 'acting_transaction.log' );
					header( "HTTP/1.0 200 OK" );
					die( 'error1' );

				} else {
					usces_log( 'zeus bank order : OK', 'acting_transaction.log' );
					$order_id = $usces->cart->get_order_entry( 'ID' );
					$upvalue = array( 'acting' => $data['acting'], 'order_no' => $data['order_no'], 'tracking_no' => $data['tracking_no'], 'status' => $data['status'], 'error_message' => $data['error_message'], 'money' => $data['money'] );
					$usces->set_order_meta_value( 'acting_'.$data['tracking_no'], usces_serialize( $upvalue ), $order_id );
					$usces->set_order_meta_value( 'wc_trans_id', $data['order_no'], $order_id );
					$usces->set_order_meta_value( 'trans_id', $data['order_no'], $order_id );
					$usces->cart->clear_cart();
				}
			}

			if( '03' === $data['status'] ) {
				$res = usces_change_order_receipt( $order_id, 'receipted' );
			} else {
				$res = usces_change_order_receipt( $order_id, 'noreceipt' );
			}
			if( false === $res ) {
				$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>'ORDER DATA UPDATE ERROR', 'data'=>$data );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus bank error2 : update usces_order', 'acting_transaction.log' );
				header( "HTTP/1.0 200 OK" );
				die( 'error2' );
			}
			if( '03' === $data['status'] ) {
				usces_action_acting_getpoint( $order_id );
			}

			$upvalue = array( 'acting' => $data['acting'], 'order_no' => $data['order_no'], 'tracking_no' => $data['tracking_no'], 'status' => $data['status'], 'error_message' => $data['error_message'], 'money' => $data['money'] );
			$res = $usces->set_order_meta_value( 'acting_'.$data['tracking_no'], usces_serialize( $upvalue ), $order_id );
			if( false === $res ) {
				$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>'ORDER META DATA UPDATE ERROR', 'data'=>$data );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus bank error3 : update usces_order_meta', 'acting_transaction.log' );
				header( "HTTP/1.0 200 OK" );
				die( 'error3' );
			}

			usces_log( 'zeus bank transaction : '.$data['tracking_no'], 'acting_transaction.log' );
			header( "HTTP/1.0 200 OK" );
			die( 'zeus' );

		//*** zeus_conv ***//
		} elseif( isset( $_REQUEST['acting'] ) && 'zeus_conv' == $_REQUEST['acting'] && isset( $_REQUEST['status'] ) && isset( $_REQUEST['sendpoint'] ) && isset( $_REQUEST['clientip'] ) ) {
			foreach( $_REQUEST as $key => $value ) {
				if( 'uscesid' == $key ) {
				} elseif( 'username' == $key ) {
					$data[$key] = mb_convert_encoding( $value, 'UTF-8', 'SJIS' );
				} else {
					$data[$key] = $value;
				}
			}
			usces_log( 'zeus conv cgi data : '.print_r( $data, true ), 'acting_transaction.log' );

			$order_id = $this->get_order_id( $data['sendpoint'] );
			if( $order_id == NULL ) {
			} else {
				if( '05' !== $data['status'] ) {
					if( '04' === $data['status'] ) {
						$res = usces_change_order_receipt( $order_id, 'receipted' );
					} else {
						$res = usces_change_order_receipt( $order_id, 'noreceipt' );
					}
					if( false === $res ) {
						$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>'ORDER DATA UPDATE ERROR', 'data'=>$data );
						usces_save_order_acting_error( $log );
						usces_log( 'zeus conv error2 : '.print_r( $data, true ), 'acting_transaction.log' );
						header( "HTTP/1.0 200 OK" );
						die( 'error2' );
					}
					if( '04' === $data['status'] ) {
						usces_action_acting_getpoint( $order_id );
					}

					$upvalue = array( 'acting' => $data['acting'], 'order_no' => $data['order_no'], 'status' => $data['status'], 'error_code' => $data['error_code'], 'money' => $data['money'] );
					$res = $usces->set_order_meta_value( 'acting_'.$data['sendpoint'], usces_serialize( $upvalue ), $order_id );
					if( false === $res ) {
						$log = array( 'acting'=>$data['acting'], 'key'=>$data['sendpoint'], 'result'=>'ORDER META DATA UPDATE ERROR', 'data'=>$data );
						usces_save_order_acting_error( $log );
						usces_log( 'zeus conv error3 : '.print_r( $data, true ), 'acting_transaction.log' );
						header( "HTTP/1.0 200 OK" );
						die( 'error3' );
					}
				}
			}

			usces_log( 'zeus conv transaction : '.$data['sendpoint'], 'acting_transaction.log' );
			header( "HTTP/1.0 200 OK" );
			die( 'zeus' );
		}

		if( isset( $_REQUEST['backfrom_zeus_bank'] ) && '1' == $_REQUEST['backfrom_zeus_bank'] ) {
			$usces->cart->clear_cart();
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

		switch( $payment['settlement'] ) {
		case 'acting_zeus_card':
			$div_name = '';
			$acting_opts = $this->get_acting_settings();
			if( 'on' == $acting_opts['howpay'] ) {
				$acting_data = usces_unserialize( $usces->get_order_meta_value( 'acting_zeus_card', $order_id ) );
				$howpay = ( isset( $acting_data['howpay'] ) ) ? $acting_data['howpay'] : '1';
				$div = ( isset( $acting_data['div'] ) ) ? $acting_data['div'] : '01';
				if( $howpay == '1' ) {
					$div_name = '　一括払い';
				} else {
					switch( $div ) {
					case '01':
						$div_name = '　一括払い';
						break;
					case '99':
						$div_name = '　分割（リボ払い）';
						break;
					case '03':
						$div_name = '　分割（3回）';
						break;
					case '05':
						$div_name = '　分割（5回）';
						break;
					case '06':
						$div_name = '　分割（6回）';
						break;
					case '10':
						$div_name = '　分割（10回）';
						break;
					case '12':
						$div_name = '　分割（12回）';
						break;
					case '15':
						$div_name = '　分割（15回）';
						break;
					case '18':
						$div_name = '　分割（18回）';
						break;
					case '20':
						$div_name = '　分割（20回）';
						break;
					case '24':
						$div_name = '　分割（24回）';
						break;
					}
				}
			}
			if( '' != $div_name ) {
				$msg_payment = __( '** Payment method **', 'usces' )."\r\n";
				$msg_payment .= usces_mail_line( 1, $data['order_email'] );//********************
				$msg_payment .= $payment['name'].$div_name;
				$msg_payment .= "\r\n\r\n";
			}
			break;

		case 'acting_zeus_bank':
			break;

		case 'acting_zeus_conv':
			$conv_name = '';
			$acting_data = $this->get_order_meta_acting( $order_id );
			if( isset( $acting_data['pay_cvs'] ) ) {
				$conv_name = usces_get_conv_name( $acting_data['pay_cvs'] );
			}
			if( '' != $conv_name ) {
				$msg_payment = __( '** Payment method **', 'usces' )."\r\n";
				$msg_payment .= usces_mail_line( 1, $data['order_email'] );//********************
				$msg_payment .= $payment['name'].'　（'.$conv_name.'）';
				$msg_payment .= "\r\n\r\n";
			}
			break;
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
		if( 'acting_zeus_card' == $payment['settlement'] ) {
			$complete = true;
		}
		return $complete;
	}

	/**
	 * 購入完了メッセージ
	 * @fook   usces_filter_completion_settlement_message
	 * @param  $html, $usces_entries
	 * @return string $html
	 */
	public function completion_settlement_message( $html, $usces_entries ) {
		global $usces;

		if( isset( $_REQUEST['acting'] ) && 'zeus_conv' == $_REQUEST['acting'] ) {
			$html .= '<div id="status_table"><h5>ゼウス・コンビニ決済</h5>'."\n";
			$html .= '<table>'."\n";
			$html .= '<tr><th>オーダー番号</th><td>'.esc_html( $usces->payment_results['order_no'] )."</td></tr>\n";
			$html .= '<tr><th>お支払先</th><td>'.esc_html(usces_get_conv_name( $usces->payment_results['pay_cvs'] ) )."</td></tr>\n";
			switch( $usces->payment_results['pay_cvs'] ) {
			case 'D001'://セブンイレブン
				$html .= '<tr><th>払込票番号</th><td>'.esc_html( $usces->payment_results['pay_no1'] )."</td></tr>\n";
				$html .= '<tr><th>URL</th><td><a href="'.esc_attr( $usces->payment_results['pay_url'] ).'" target="_blank">'.esc_html( $usces->payment_results['pay_url'] ).'</a></td></tr>'."\n";
				break;
			case 'D002'://ローソン
			case 'D050'://ミニストップ
				$html .= '<tr><th>受付番号</th><td>'.esc_html( $usces->payment_results['pay_no1'] )."</td></tr>\n";
				if( isset( $usces->payment_results['pay_no2'] ) ) 
				$html .= '<tr><th>確認番号</th><td>'.esc_html( $usces->payment_results['pay_no2'] )."</td></tr>\n";
				break;
			case 'D040'://サークルKサンクス
			case 'D015'://セイコーマート
				$html .= '<tr><th>お支払受付番号</th><td>'.esc_html( $usces->payment_results['pay_no1'] )."</td></tr>\n";
				break;
			case 'D030'://ファミリーマート
				$html .= '<tr><th>注文番号</th><td>'.esc_html( $usces->payment_results['pay_no1'] )."</td></tr>\n";
				$html .= '<tr><th>企業コード</th><td>'.esc_html( $usces->payment_results['pay_no2'] )."</td></tr>\n";
				break;
			case 'D060'://デイリーヤマザキ
				$html .= '<tr><th>オンライン決済番号</th><td>'.esc_html( $usces->payment_results['pay_no1'] )."</td></tr>\n";
				break;
			}
			$html .= '<tr><th>お支払期限</th><td>'.esc_html(substr( $usces->payment_results['pay_limit'], 0, 4 ).'年'.substr( $usces->payment_results['pay_limit'], 4, 2 ).'月'.substr( $usces->payment_results['pay_limit'], 6, 2 ).'日' )."(期限を過ぎますとお支払ができません)</td></tr>\n";
			//$html .= '<!-- <tr><th>エラーコード</th><td>'.esc_html( $usces->payment_results['error_code'] )."</td></tr> -->\n";
			$html .= '</table>'."\n";
			$html .= '<p>「お支払いのご案内」は、'.esc_html( $usces_entries['customer']['mailaddress1'] ).'　宛にメールさせていただいております。</p>'."\n";
			$html .= "</div>\n";
		}
		return $html;
	}

	/**
	 * 決済リンクキー
	 * @fook   usces_filter_get_link_key
	 * @param  $linkkey $results
	 * @return string $linkkey
	 */
	public function get_link_key( $linkkey, $results ) {

		if( isset( $results['order_number'] ) && isset( $results['sendpoint'] ) ) {
			$linkkey = $_REQUEST['sendpoint'];
		}
		return $linkkey;
	}

	/**
	 * 受注データ復旧処理
	 * @fook   usces_action_revival_order_data
	 * @param  $order_id $log_key $acting
	 * @return -
	 */
	public function revival_orderdata( $order_id, $log_key, $acting ) {
		global $usces;

		if( !in_array( $acting, $this->pay_method ) ) {
			return;
		}

		$data = array();
		switch( $acting ) {
		case 'acting_zeus_card':
			$data['sendpoint'] = $log_key;
			$usces->set_order_meta_value( $acting, usces_serialize( $data ), $order_id );
		case 'acting_zeus_conv':
		case 'acting_zeus_bank':
			$usces->set_order_meta_value( 'acting_'.$log_key, usces_serialize( $data ), $order_id );
			break;
		}
	}

	/**
	 * 受注データから取得する決済情報のキー
	 * @fook   usces_filter_settle_info_field_meta_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_meta_keys( $keys ) {

		$keys = array_merge( $keys, array( 'div' ) );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * @fook   usces_filter_settle_info_field_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_keys( $keys ) {

		$keys = array_merge( $keys, array( 'div' ) );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * @fook   usces_filter_settle_info_field_value
	 * @param  $value $key $acting
	 * @return string $value
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {

		if( false !== strpos( $acting, 'zeus_card' ) ) {
			if( 'div' == $key ) {
				switch( $value ) {
				case '01':
					$value = '一括払い';
					break;
				case '99':
					$value = '分割（リボ払い）';
					break;
				case '03':
					$value = '分割（3回）';
					break;
				case '05':
					$value = '分割（5回）';
					break;
				case '06':
					$value = '分割（6回）';
					break;
				case '10':
					$value = '分割（10回）';
					break;
				case '12':
					$value = '分割（12回）';
					break;
				case '15':
					$value = '分割（15回）';
					break;
				case '18':
					$value = '分割（18回）';
					break;
				case '20':
					$value = '分割（20回）';
					break;
				case '24':
					$value = '分割（24回）';
					break;
				}
			}

		} elseif( 'zeus_bank' == $acting ) {
			if( 'status' == $key ) {
				if( '01' === $value ) {
					$value = '受付中';
				} elseif( '02' === $value ) {
					$value = '未入金';
				} elseif( '03' === $value ) {
					$value = '入金済';
				} elseif( '04' === $value ) {
					$value = 'エラー';
				} elseif( '05' === $value ) {
					$value = '入金失敗';
				}
			} elseif( 'error_message' == $key ) {
				if( '0002' === $value ) {
					$value = '入金不足';
				} elseif( '0003' === $value ) {
					$value = '過剰入金';
				}
			}

		} elseif( 'zeus_conv' == $acting ) {
			if( 'pay_cvs' == $key ) {
				$value = esc_html(usces_get_conv_name( $value ) );
			} elseif( 'status' == $key ) {
				if( '01' === $value ) {
					$value = '未入金';
				} elseif( '02' === $value ) {
					$value = '申込エラー';
				} elseif( '03' === $value ) {
					$value = '期日切';
				} elseif( '04' === $value ) {
					$value = '入金済';
				} elseif( '05' === $value ) {
					$value = '売上確定';
				} elseif( '06' === $value ) {
					$value = '入金取消';
				} elseif( '11' === $value ) {
					$value = 'キャンセル後入金';
				} elseif( '12' === $value ) {
					$value = 'キャンセル後売上';
				} elseif( '13' === $value ) {
					$value = 'キャンセル後取消';
				}
			} elseif( 'pay_limit' == $key ) {
				$value = substr( $value, 0, 4 ).'年'.substr( $value, 4, 2 ).'月'.substr( $value, 6, 2 ).'日';
			}
		}
		return $value;
	}

	/**
	 * 会員データ編集画面 QuickCharge 登録情報
	 * @fook   usces_action_admin_member_info
	 * @param  $data $member_metas $usces_member_history
	 * @return -
	 * @echo   html
	 */
	public function admin_member_info( $data, $member_metas, $usces_member_history ) {

		$cardinfo = array();
		foreach( $member_metas as $value ) {
			if( in_array( $value['meta_key'], array( 'zeus_pcid', 'zeus_partofcard' ) ) ) {
				$cardinfo[$value['meta_key']] = $value['meta_value'];
			}
		}
		if( 0 < count( $cardinfo) ):
			foreach( $cardinfo as $key => $value ):
				if( $key != 'zeus_pcid' ):
					if( $key == 'zeus_partofcard' ) $label = __( 'Lower 4 digits', 'usces' );
					elseif( $key == 'zeus_limitofcard' ) $label = __( 'Expiration date', 'usces' );
					else $label = $key; ?>
		<tr>
			<td class="label"><?php echo esc_html( $label ); ?></td>
			<td><div class="rod_left shortm"><?php echo esc_html( $value ); ?></div></td>
		</tr>
<?php			endif;
			endforeach;
			if( array_key_exists( 'zeus_pcid', $cardinfo ) ): ?>
		<tr>
			<td class="label">QuickCharge</td>
			<td><div class="rod_left shortm"><?php _e( 'Registered', 'usces' ); ?></div></td>
		</tr>
<?php			if( !usces_have_member_regular_order( $data['ID'] ) ): ?>
		<tr>
			<td class="label"><input type="checkbox" name="zeus_pcid" id="zeus_pcid" value="delete"></td>
			<td><label for="zeus_pcid">QuickCharge を解除する</label></td>
		</tr>
<?php			endif;
			endif;
		endif;
	}

	/**
	 * 会員データ編集画面 QuickCharge 登録情報更新
	 * @fook   usces_action_post_update_memberdata
	 * @param  $member_id $res
	 * @return -
	 */
	public function admin_update_memberdata( $member_id, $res ) {
		global $usces;

		if( !$this->is_activate_card() || false === $res ) {
			return;
		}

		if( isset( $_POST['zeus_pcid'] ) && $_POST['zeus_pcid'] == 'delete' ) {
			$usces->del_member_meta( 'zeus_pcid', $member_id );
			$usces->del_member_meta( 'zeus_partofcard', $member_id );
			$usces->del_member_meta( 'zeus_limitofcard', $member_id );
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

		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flg = ( 'acting' == $payment['settlement'] ) ? $payment['module'] : $payment['settlement'];

		switch( $acting_flg ) {
		case 'acting_zeus_card':
			if( !isset( $usces_entries['order']['cbrand'] ) || ( isset( $usces_entries['order']['howpay'] ) && '1' === $usces_entries['order']['howpay'] ) ) {
				$str = '　一括払い';
			} else {
				$div_name = 'div_'.$usces_entries['order']['cbrand'];
				switch( $usces_entries['order'][$div_name] ) {
				case '01':
					$str = '　一括払い';
					break;
				case '99':
					$str = '　分割（リボ払い）';
					break;
				case '03':
					$str = '　分割（3回）';
					break;
				case '05':
					$str = '　分割（5回）';
					break;
				case '06':
					$str = '　分割（6回）';
					break;
				case '10':
					$str = '　分割（10回）';
					break;
				case '12':
					$str = '　分割（12回）';
					break;
				case '15':
					$str = '　分割（15回）';
					break;
				case '18':
					$str = '　分割（18回）';
					break;
				case '20':
					$str = '　分割（20回）';
					break;
				case '24':
					$str = '　分割（24回）';
					break;
				}
			}
			break;

		case 'acting_zeus_bank':
			break;

		case 'acting_zeus_conv':
			if( isset( $usces_entries['order']['pay_cvs'] ) ) {
				$conv_name = usces_get_conv_name( $usces_entries['order']['pay_cvs'] );
				$str = ( '' != $conv_name ) ? '　（'.$conv_name.'）' : '';
			}
			break;
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

		switch( $payment['settlement'] ) {
		case 'acting_zeus_card':
			if( $this->is_validity_acting( 'card' ) ) {
				$payments_str .= "'".$payment['name']."': 'zeus', ";
			}
			break;
		case 'acting_zeus_conv':
			if( $this->is_validity_acting( 'conv' ) ) {
				$payments_str .= "'".$payment['name']."': 'zeus_conv', ";
			}
			break;
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

		switch( $payment['settlement'] ) {
		case 'acting_zeus_card':
			if( $this->is_validity_acting( 'card' ) ) {
				$payments_arr[] = 'zeus';
			}
			break;
		case 'acting_zeus_conv':
			if( $this->is_validity_acting( 'conv' ) ) {
				$payments_arr[] = 'zeus_conv';
			}
			break;
		}
		return $payments_arr;
	}

	/**
	 * カード情報入力チェック
	 * @fook   usces_filter_delivery_check
	 * @param  $mes
	 * @return string $mes
	 */
	public function delivery_check( $mes ) {
		global $usces;

		$settlement = '';
		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );

		foreach( (array)$payment_method as $id => $payment ) {
			if( isset( $_POST['offer']['payment_name'] ) && $payment['name'] == $_POST['offer']['payment_name'] ) {
				$settlement = $payment['settlement'];
				break;
			}
		}

		switch( $settlement ) {
		case 'acting_zeus_card':
			if( 'zeus' != $_POST['acting'] ) {
				$mes .= 'カード決済データが不正です！';
			} elseif( empty( $_POST['zeus_card_option'] ) || ( $_POST['zeus_card_option'] == 'new' && empty( $_POST['zeus_token_value'] ) ) ) {
				$mes .= 'カード決済データが不正です！';
			}
			break;

		case 'acting_zeus_conv':
			if( WCUtils::is_blank( $_POST['username_conv'] ) ) {
				$mes .= "お名前を入力してください。<br />";
			} elseif( !preg_match( "/^[ァ-ヶー]+$/u", $_POST['username_conv'] ) ) {
				$mes .= "お名前は全角カタカナで入力してください。<br />";
			}
			break;
		}
		return $mes;
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

		switch( $payment['settlement'] ) {
		case 'acting_zeus_card':
			if( !$this->is_validity_acting( 'card' ) ) {
				return $html;
			}

			$acting_opts = $this->get_acting_settings();
			$html .= '<input type="hidden" name="acting" value="'.$this->paymod_id.'">';
			$html .= '<table class="customer_form" id="'.$this->paymod_id.'">';
			$html .= '<tr><th scope="row">クレジットカード情報</th><td id="zeus_token_card_info_area"></td></tr>';

			$howpay = ( isset( $_POST['howpay'] ) ) ? $_POST['howpay'] : '1';
			$cbrand = ( isset( $_POST['cbrand'] ) ) ? $_POST['cbrand'] : '';
			$div = ( isset( $_POST['div'] ) ) ? $_POST['div'] : '';

			$html_howpay = '';
			$member_page = ( isset( $_GET['page'] ) && 'member_update_settlement' == $_GET['page'] ) ? true : false;
			if( 'on' == $acting_opts['howpay'] && !$member_page ) {
				$html_howpay .= '
					<tr>
						<th scope="row">'.__( 'payment method', 'usces' ).'</th>
						<td>
							<input name="offer[howpay]" type="radio" value="1" id="howdiv1"'.( ( '1' === $howpay ) ? ' checked' : '' ).' /><label for="howdiv1">'.__( 'Single payment', 'usces' ).'</label>&nbsp;&nbsp;&nbsp;
							<input name="offer[howpay]" type="radio" value="0" id="howdiv2"'.( ( '0' === $howpay ) ? ' checked' : '' ).' /><label for="howdiv2">'.__( 'Payment in installments', 'usces' ).'</label>
						</td>
					</tr>
					<tr id="cbrand_zeus">
						<th scope="row">'.__( 'Card brand', 'usces' ).'</th>
						<td>
						<select name="offer[cbrand]" id="cbrand">
							<option value=""'.( ( WCUtils::is_blank( $cbrand ) ) ? ' selected="selected"' : '' ).'>--------</option>
							<option value="1"'.( ( '1' === $cbrand ) ? ' selected="selected"' : '' ).'>JCB</option>
							<option value="1"'.( ( '1' === $cbrand ) ? ' selected="selected"' : '' ).'>VISA</option>
							<option value="1"'.( ( '1' === $cbrand ) ? ' selected="selected"' : '' ).'>MASTER</option>
							<option value="2"'.( ( '2' === $cbrand ) ? ' selected="selected"' : '' ).'>DINERS</option>
							<option value="1"'.( ( '1' === $cbrand ) ? ' selected="selected"' : '' ).'>AMEX</option>
						</select>
						</td>
					</tr>
					<tr id="div_zeus">
						<th scope="row">'.__( 'Number of payments', 'usces' ).'</th>
						<td>
						<select name="offer[div_1]" id="brand1">
							<option value="01"'.( ( '01' === $div ) ? ' selected="selected"' : '' ).'>'.__( 'Single payment', 'usces' ).'</option>
							<option value="99"'.( ( '99' === $div ) ? ' selected="selected"' : '' ).'>'.__( 'Libor Funding pay', 'usces' ).'</option>
							<option value="03"'.( ( '03' === $div ) ? ' selected="selected"' : '' ).'>3'.__( '-time payment', 'usces' ).'</option>
							<option value="05"'.( ( '05' === $div ) ? ' selected="selected"' : '' ).'>5'.__( '-time payment', 'usces' ).'</option>
							<option value="06"'.( ( '06' === $div ) ? ' selected="selected"' : '' ).'>6'.__( '-time payment', 'usces' ).'</option>
							<option value="10"'.( ( '10' === $div ) ? ' selected="selected"' : '' ).'>10'.__( '-time payment', 'usces' ).'</option>
							<option value="12"'.( ( '12' === $div ) ? ' selected="selected"' : '' ).'>12'.__( '-time payment', 'usces' ).'</option>
							<option value="15"'.( ( '15' === $div ) ? ' selected="selected"' : '' ).'>15'.__( '-time payment', 'usces' ).'</option>
							<option value="18"'.( ( '18' === $div ) ? ' selected="selected"' : '' ).'>18'.__( '-time payment', 'usces' ).'</option>
							<option value="20"'.( ( '20' === $div ) ? ' selected="selected"' : '' ).'>20'.__( '-time payment', 'usces' ).'</option>
							<option value="24"'.( ( '24' === $div ) ? ' selected="selected"' : '' ).'>24'.__( '-time payment', 'usces' ).'</option>
						</select>
						<select name="offer[div_2]" id="brand2">
							<option value="01"'.( ( '01' === $div ) ? ' selected="selected"' : '' ).'>'.__( 'Single payment', 'usces' ).'</option>
							<option value="99"'.( ( '99' === $div ) ? ' selected="selected"' : '' ).'>'.__( 'Libor Funding pay', 'usces' ).'</option>
						</select>
						<select name="offer[div_3]" id="brand3">
							<option value="01"'.( ( '01' === $div ) ? ' selected="selected"' : '' ).'>'.__( 'Single payment only', 'usces' ).'</option>
						</select>
						</td>
					</tr>
				</table>';
			}
			$html .= apply_filters( 'usces_filter_delivery_secure_form_howpay', $html_howpay );
			break;

		case 'acting_zeus_conv':
			if( !$this->is_validity_acting( 'conv' ) ) {
				return $html;
			}

			$acting_opts = $this->get_acting_settings();
			$pay_cvs = ( isset( $_POST['pay_cvs'] ) ) ? $_POST['pay_cvs'] : '';
			$entry = $usces->cart->get_entry();
			$username = ( isset( $_POST['username_conv'] ) ) ? $_POST['username_conv'] : $entry['customer']['name3'].$entry['customer']['name4'];

			$html .= '
			<table class="customer_form" id="'.$this->paymod_id.'_conv">
				<tr>
				<th scope="row">'.__( 'Convenience store for payment', 'usces' ).'</th>
				<td colspan="2">
				<select name="offer[pay_cvs]" id="pay_cvs_zeus">';
			foreach( (array)$acting_opts['pay_cvs'] as $pay_cvs_code ) {
				$selected = ( $pay_cvs_code == $pay_cvs ) ? ' selected="selected"' : '';
				$html .= '
				<option value="'.$pay_cvs_code.'"'.$selected.'>'.usces_get_conv_name( $pay_cvs_code ).'</option>';
			}
			$html .= '
				</select>
				</td>
				</tr>
				<tr>
				<th scope="row"><em>'.__( '*', 'usces' ).'</em>'.__( 'Full name', 'usces' ).'</th>
				<td colspan="2"><input name="username_conv" id="username_conv" type="text" size="30" value="'.esc_attr( $username ).'" />'.__( '(full-width Kana)', 'usces' ).'</td>
				</tr>
			</table>';
			break;
		}

		return $html;
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

		switch( $acting_flg ) {
		case 'acting_zeus_card':
			$acting_opts = $this->get_acting_settings();
			$usces->save_order_acting_data( $rand );
			usces_save_order_acting_data( $rand );
			$mem_id = '';
			if( $usces->is_member_logged_in() ) {
				$member = $usces->get_member();
				$mem_id = $member['ID'];
			}
			$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
				<input type="hidden" name="card_option" value="'.$_POST['zeus_card_option'].'">
				<input type="hidden" name="token_key" value="'.$_POST['zeus_token_value'].'">
				<input type="hidden" name="money" value="'.usces_crform( $usces_entries['order']['total_full_price'], false, false, 'return', false ).'">
				<input type="hidden" name="telno" value="'.esc_attr( str_replace( '-', '', $usces_entries['customer']['tel'] ) ).'">
				<input type="hidden" name="email" value="'.esc_attr( $usces_entries['customer']['mailaddress1'] ).'">
				<input type="hidden" name="sendid" value="'.$mem_id.'">
				<input type="hidden" name="sendpoint" value="'.$rand.'">';
			if( isset( $usces_entries['order']['cbrand'] ) && isset( $usces_entries['order']['howpay'] ) && WCUtils::is_zero( $usces_entries['order']['howpay'] ) ) {
				$div_name = 'div_'.$usces_entries['order']['cbrand'];
				$html .= '<input type="hidden" name="howpay" value="'.$usces_entries['order']['howpay'].'">
					<input type="hidden" name="cbrand" value="'.$usces_entries['order']['cbrand'].'">
					<input type="hidden" name="div" value="'.$usces_entries['order'][$div_name].'">
					<input type="hidden" name="div_1" value="'.$usces_entries['order']['div_1'].'">
					<input type="hidden" name="div_2" value="'.$usces_entries['order']['div_2'].'">
					<input type="hidden" name="div_3" value="'.$usces_entries['order']['div_3'].'">';
			}
			$html .= '<div class="send">
				'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
				<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
				<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' /></div>
				<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
			break;

		case 'acting_zeus_conv':
			$member = $usces->get_member();
			$acting_opts = $this->get_acting_settings();
			$usces->save_order_acting_data( $rand );
			usces_save_order_acting_data( $rand );
			$pay_cvs = ( isset( $usces_entries['order']['pay_cvs'] ) ) ? $usces_entries['order']['pay_cvs'] : '';
			$html = '<form id="purchase_form" action="'.USCES_CART_URL.'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
				<input type="hidden" name="act" value="secure_order">
				<input type="hidden" name="money" value="'.usces_crform( $usces_entries['order']['total_full_price'], false, false, 'return', false ).'">
				<input type="hidden" name="username" value="'.esc_attr( $_POST['username_conv'] ).'">
				<input type="hidden" name="telno" value="'.esc_attr( str_replace( '-', '', $usces_entries['customer']['tel'] ) ).'">
				<input type="hidden" name="email" value="'.esc_attr( $usces_entries['customer']['mailaddress1'] ).'">
				<input type="hidden" name="pay_cvs" value="'.$pay_cvs.'">
				<input type="hidden" name="sendid" value="'.$member['ID'].'">
				<input type="hidden" name="sendpoint" value="'.$rand.'">';
			$html .= '
				<div class="send">
				'.apply_filters( 'usces_filter_confirm_before_backbutton', NULL, $payments, $acting_flg, $rand ).'
				<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' />
				<input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', NULL ).$purchase_disabled.' /></div>
				<input type="hidden" name="username_conv" value="'.esc_attr( $_POST['username_conv'] ).'">
				<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
			break;

		case 'acting_zeus_bank':
			$member = $usces->get_member();
			$acting_opts = $this->get_acting_settings();
			$usces->save_order_acting_data( $rand );
			usces_save_order_acting_data( $rand );
			$html = '<form id="purchase_form" action="'.$acting_opts['bank_url'].'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}" accept-charset="Shift_JIS">
				<input type="hidden" name="clientip" value="'.esc_attr( $acting_opts['clientip_bank'] ).'">
				<input type="hidden" name="act" value="order">
				<input type="hidden" name="money" value="'.usces_crform( $usces_entries['order']['total_full_price'], false, false, 'return', false ).'">';
			if( isset( $acting_opts['bank_ope'] ) && 'test' == $acting_opts['bank_ope'] ) {
				$html .= '<input type="hidden" name="username" value="'.esc_attr( trim( $usces_entries['customer']['name3'] ).trim( $usces_entries['customer']['name4'] ).'_'.$acting_opts['testid_bank'] ).'">';
				$html .= '<input type="hidden" name="telno" value="99999999999">';
			} else {
				$html .= '<input type="hidden" name="username" value="'.esc_attr( trim( $usces_entries['customer']['name3'] ).trim( $usces_entries['customer']['name4'] ) ).'">';
				$html .= '<input type="hidden" name="telno" value="'.esc_attr( str_replace( '-', '', $usces_entries['customer']['tel'] ) ).'">';
			}
			$html .= '<input type="hidden" name="email" value="'.esc_attr( $usces_entries['customer']['mailaddress1'] ).'">
				<input type="hidden" name="sendid" value="'.$member['ID'].'">
				<input type="hidden" name="sendpoint" value="'.$rand.'">
				<input type="hidden" name="siteurl" value="'.get_option( 'home' ) . '/?backfrom_zeus_bank=1">
				<input type="hidden" name="sitestr" value="「'.esc_attr( get_option( 'blogname' ) ).'」トップページへ">';
			$html .= '<input type="hidden" name="dummy" value="&#65533;" />';
			$html .= '<div class="send"><input name="purchase" type="submit" id="purchase_button" class="checkout_button" value="'.apply_filters( 'usces_filter_confirm_checkout_button_value', __( 'Checkout', 'usces' ) ).'"'.apply_filters( 'usces_filter_confirm_nextbutton', ' onClick="document.charset=\'Shift_JIS\';"' ).$purchase_disabled.' /></div>';
			$html .= '</form>';
			$html .= '<form action="'.USCES_CART_URL.'" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
				<div class="send"><input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="'.__( 'Back', 'usces' ).'"'.apply_filters( 'usces_filter_confirm_prebutton', NULL ).' /></div>
				<input type="hidden" name="_nonce" value="'.wp_create_nonce( $acting_flg ).'">';
			break;
		}
		return $html;
	}

	/**
	 * 内容確認ページ ポイントフォーム
	 * @fook   usces_action_confirm_page_point_inform
	 * @param  -
	 * @return -
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

		$usces_entries = $usces->cart->get_entry();
		$payment = usces_get_payments_by_name( $usces_entries['order']['payment_name'] );
		$acting_flg = ( 'acting' == $payment['settlement'] ) ? $payment['module'] : $payment['settlement'];

		switch( $acting_flg ) {
		case 'acting_zeus_card':
			$acting_opts = $this->get_acting_settings();
			if( isset( $_POST['zeus_card_option'] ) ) {
				$html .= '<input type="hidden" name="zeus_card_option" value="'.esc_attr( $_POST['zeus_card_option'] ).'">';
			}
			if( isset( $_POST['zeus_card_option'] ) && 'new' == $_POST['zeus_card_option'] ) {
				$html .= '<input type="hidden" name="zeus_token_value" value="'.esc_attr( $_POST['zeus_token_value'] ).'">';
			}
			break;

		case 'acting_zeus_conv':
			if( isset( $_POST['pay_cvs'] ) ) {
				$html .= '<input type="hidden" name="offer[pay_cvs]" value="'.esc_attr( $usces_entries['order']['pay_cvs'] ).'">
					<input type="hidden" name="username_conv" value="'.esc_attr( $_POST['username_conv'] ).'">';
			}
			break;
		}
		return $html;
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

		$entry = $usces->cart->get_entry();
		$cart = $usces->cart->get_cart();

		if( !$entry || !$cart ) {
			wp_redirect( USCES_CART_URL );
		}

		if( !wp_verify_nonce( $_REQUEST['_nonce'], $acting_flg ) ) {
			wp_redirect( USCES_CART_URL );
		}

		$acting_opts = $this->get_acting_settings();
		parse_str( $post_query, $post_data );

		//Secure API
		if( 'acting_zeus_card' == $acting_flg && 2 == $acting_opts['connection'] ) {

			//3D Secure
			if( 1 == $acting_opts['3dsecur'] ) {
				if( !isset( $_REQUEST['PaRes'] ) ) {
					//Enrol Reqest
					usces_log( 'zeus card 3dsecure entry data (acting_processing) : '.print_r( $entry, true ), 'acting_transaction.log' );
					$this->zeus_3dsecure_enrol();
				} else {
					//Auth Reqest
					usces_log( 'zeus card 3dsecure : auth', 'acting_transaction.log' );
					$this->zeus_3dsecure_auth();
				}

			} else {
				usces_log( 'zeus card no3d entry data (acting_processing) : '.print_r( $entry, true ), 'acting_transaction.log' );
				$res = $this->zeus_secure_payreq();
				return $res;
			}

		//Secure Link
		} elseif( 'acting_zeus_card' == $acting_flg && 1 == $acting_opts['connection'] ) {

			$interface = parse_url( $acting_opts['card_url'] );
			if( defined( 'ZEUS_SSL_TEST' ) ) {
				$interface['host'] = ZEUS_SSL_TEST.$interface['host'];
			}
			if( defined( 'ZEUS_TLS_TEST' ) ) {
				$interface['host'] = ZEUS_TLS_TEST;
			}

			usces_log( 'zeus card securelink entry data (acting_processing) : '.print_r( $entry, true ), 'acting_transaction.log' );

			$sendid = ( 'on' == $acting_opts['quickcharge'] && $usces->is_member_logged_in() && isset( $_POST['sendid'] ) ) ? $_POST['sendid'] : '';

			$params = array();
			$params['send'] = 'mall';
			$params['clientip'] = $acting_opts['clientip'];
			if( 'on' == $acting_opts['quickcharge'] && isset( $_POST['card_option'] ) && 'prev' == $_POST['card_option'] && !empty( $sendid ) ) {
				$params['cardnumber'] = '8888888888888882';
				$params['expyy'] = '00';
				$params['expmm'] = '00';
			} elseif( isset( $_POST['token_key'] ) ) {
				$params['token_key'] = $_POST['token_key'];
			}
			$params['money'] = $_POST['money'];
			$params['telno'] = str_replace( '-', '', $_POST['telno'] );
			$params['email'] = $_POST['email'];
			$params['sendid'] = $sendid;
			$params['sendpoint'] = $_POST['sendpoint'];
			$params['printord'] = 'yes';
			$params['return_value'] = 'yes';
			if( 'on' == $acting_opts['howpay'] && isset( $_POST['howpay'] ) && WCUtils::is_zero( $_POST['howpay'] ) ) {
				$params['div'] = $_POST['div'];
			}
			$vars = http_build_query( $params );

			$header  = "POST ".$interface['path']." HTTP/1.1\r\n";
			$header .= "Host: ".$_SERVER['HTTP_HOST']."\r\n";
			$header .= "User-Agent: PHP Script\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: ".strlen( $vars )."\r\n";
			$header .= "Connection: close\r\n\r\n";
			$header .= $vars;

			$fp = @stream_socket_client( 'tlsv1.2://'.$interface['host'].':443', $errno, $errstr, 30 );
			if( !$fp ) {
				usces_log( 'zeus card : TLS(v1.2) Error', 'acting_transaction.log' );
				$fp = fsockopen( 'ssl://'.$interface['host'], 443, $errno, $errstr, 30 );
				if( !$fp ) {
					usces_log( 'zeus card : SSL Error', 'acting_transaction.log' );
					$log = array( 'acting'=>'zeus_card', 'key'=>$_POST['sendpoint'], 'result'=>'SSL/TLS ERROR ( '.$errno.' )', 'data'=>array( $errstr ) );
					usces_save_order_acting_error( $log );
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0' );
					exit;
				}
			}

			if( $fp ) {
				$page = '';
				fwrite( $fp, $header );
				while( !feof( $fp ) ) {
					$scr = fgets( $fp, 1024 );
					$page .= $scr;
				}
				fclose( $fp );

				if( false !== strpos( $page, 'Success_order' ) ) {
					//usces_auth_order_acting_data( $_POST['sendpoint'] );
					usces_ordered_acting_data( $_POST['sendpoint'], 'propriety' );
					usces_log( 'zeus card : Success_order ', 'acting_transaction.log' );
					$ordd = $this->get_order_number( $page );
					$args = '&order_number='.$ordd.'&wctid='.$_POST['sendpoint'];
					if( 'on' == $acting_opts['howpay'] && isset( $_POST['howpay'] ) && WCUtils::is_zero( $_POST['howpay'] ) ) {
						$args .= '&howpay='.$_POST['howpay'].'&div='.$_POST[$div];
					}
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=1'.$args );
					exit;

				} else {
					$err_code = $this->get_err_code( $page );
					usces_log( 'zeus card : Certification Error : '.$err_code, 'acting_transaction.log' );
					$data = explode( "\r\n", $page );
					$log = array( 'acting'=>'zeus_card', 'key'=>$_POST['sendpoint'], 'result'=>$err_code, 'data'=>$data );
					usces_save_order_acting_error( $log );
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&err_code='.substr( $err_code, -3 ) );
					exit;
				}
			}
			exit;

		} elseif( 'acting_zeus_conv' == $acting_flg ) {

			$interface = parse_url( $acting_opts['conv_url'] );
			if( defined( 'ZEUS_SSL_TEST' ) ) {
				$interface['host'] = ZEUS_SSL_TEST.$interface['host'];
			}
			if( defined( 'ZEUS_TLS_TEST' ) ) {
				$interface['host'] = ZEUS_TLS_TEST;
			}

			$params = array();
			$params['clientip'] = $acting_opts['clientip_conv'];
			$params['act'] = $_POST['act'];
			$params['money'] = $_POST['money'];
			$params['username'] = mb_convert_encoding( $_POST['username'], 'SJIS', 'UTF-8' );
			$params['telno'] = str_replace( '-', '', $_POST['telno'] );
			$params['email'] = $_POST['email'];
			$params['pay_cvs'] = $_POST['pay_cvs'];
			$params['sendid'] = $_POST['sendid'];
			$params['sendpoint'] = $_POST['sendpoint'];
			if( isset( $acting_opts['conv_ope'] ) && 'test' == $acting_opts['conv_ope'] ) {
				$params['testid'] = $acting_opts['testid_conv'];
				$params['test_type'] = $acting_opts['test_type_conv'];
			}
			$vars = http_build_query( $params );

			$header  = "POST ".$interface['path']." HTTP/1.1\r\n";
			$header .= "Host: ".$_SERVER['HTTP_HOST']."\r\n";
			$header .= "User-Agent: PHP Script\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: ".strlen( $vars )."\r\n";
			$header .= "Connection: close\r\n\r\n";
			$header .= $vars;

			$fp = @stream_socket_client( 'tlsv1.2://'.$interface['host'].':443', $errno, $errstr, 30 );
			if( !$fp ) {
				usces_log( 'zeus conv : TLS(v1.2) Error', 'acting_transaction.log' );
				$fp = fsockopen( 'ssl://'.$interface['host'], 443, $errno, $errstr, 30 );
				if( !$fp ) {
					usces_log( 'zeus conv : SSL Error', 'acting_transaction.log' );
					$log = array( 'acting'=>'zeus_conv', 'key'=>$_POST['sendpoint'], 'result'=>'SSL/TLS ERROR ( '.$errno.' )', 'data'=>array( $errstr ) );
					usces_save_order_acting_error( $log );
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_conv&acting_return=0' );
					exit;
				}
			}

			if( $fp ) {
				$page = '';
				$qstr = '';
				fwrite( $fp, $header );
				while( !feof( $fp ) ) {
					$scr = fgets( $fp, 1024 );
					$page .= $scr;
					if( false !== strpos( $scr, 'order_no' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'pay_no1' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'pay_no2' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'pay_limit' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'pay_url' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'error_code' ) )
						$qstr .= trim( $scr ).'&';
					if( false !== strpos( $scr, 'sendpoint' ) )
						$qstr .= trim( $scr ).'&';
				}
				$qstr .= 'pay_cvs='.$_POST['pay_cvs'].'&wctid='.$_POST['sendpoint'];
				fclose( $fp );

				if( false !== strpos( $page, 'Success_order' ) ) {
					usces_log( 'zeus conv entry data (acting_processing) : '.print_r( $entry, true ), 'acting_transaction.log' );
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_conv&acting_return=1&'.$qstr );
					exit;

				} else {
					usces_log( 'zeus data NG : '.$page, 'acting_transaction.log' );
					parse_str( $qstr, $data );
					$log = array( 'acting'=>'zeus_conv', 'key'=>$_POST['sendpoint'], 'result'=>'CERTIFICATION ERROR', 'data'=>$data );
					usces_save_order_acting_error( $log );
					header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_conv&acting_return=0' );
					exit;
				}
			}
			exit;
		}
	}

	/**
	 * 決済完了ページ制御
	 * @fook   usces_filter_check_acting_return_results
	 * @param  $results
	 * @return array $results
	 */
	public function acting_return( $results ) {

		$acting = ( isset( $_GET['acting'] ) ) ? $_GET['acting'] : '';
		switch( $acting ) {
		case 'zeus_card':
			$results = $_REQUEST;
			if( $_REQUEST['acting_return'] && isset( $_REQUEST['wctid'] ) && usces_is_trusted_acting_data( $_REQUEST['wctid'] ) ) {
				$results[0] = 1;
			} else {
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;

		case 'zeus_conv':
			$results = $_GET;
			if( $_REQUEST['acting_return'] ) {
				$results[0] = 1;
			} else {
				$results[0] = 0;
			}
			$results['reg_order'] = true;
			break;
		}

		return $results;
	}

	/**
	 * 重複オーダー禁止処理
	 * @fook   usces_filter_check_acting_return_duplicate
	 * @param  $trans_id $results
	 * @return string $trans_id
	 */
	public function check_acting_return_duplicate( $trans_id, $results ) {
		global $usces;

		$acting = ( isset( $_GET['acting'] ) ) ? $_GET['acting'] : '';
		switch( $acting ) {
		case 'zeus_card':
			if( isset( $_REQUEST['ordd'] ) ) {
				$trans_id = $_REQUEST['ordd'];
			} elseif( isset( $_REQUEST['zeusordd'] ) ) {
				$trans_id = $_REQUEST['zeusordd'];
			}
			break;
		case 'zeus_conv':
		case 'zeus_bank':
			$trans_id = ( isset( $_REQUEST['order_no'] ) ) ? $_REQUEST['order_no'] : '';
			break;
		}
		return $trans_id;
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

		//*** zeus card ***//
		if( $payments['settlement'] == 'acting_zeus_card' ) {
			$acting_opts = $this->get_acting_settings();
			if( 2 == $acting_opts['connection'] && !empty( $results['zeusordd'] ) && !empty( $results['zeussuffix'] ) ) {
				$data = array();
				$data['acting'] = 'zeus_card Secure API';
				$data['order_number'] = $results['zeusordd'];
				if( 'on' == $acting_opts['howpay'] ) {
					if( $entry['order']['howpay'] == '0' ) {
						$div_name = 'div_'.$entry['order']['cbrand'];
						$data['howpay'] = '0';
						$data['div'] = $entry['order'][$div_name];
					} else {
						$data['howpay'] = '1';
						$data['div'] = '01';
					}
				}
				$usces->set_order_meta_value( 'acting_zeus_card', usces_serialize( $data ), $order_id );
				$usces->set_order_meta_value( 'wc_trans_id', $results['zeusordd'], $order_id );
				$usces->set_order_meta_value( 'trans_id', $results['zeusordd'], $order_id );

				if( 'on' == $acting_opts['quickcharge'] && $usces->is_member_logged_in() ) {
					$usces->set_member_meta_value( 'zeus_pcid', '8888888888888882' );
					$usces->set_member_meta_value( 'zeus_partofcard', $_GET['zeussuffix'] );
				}

			} elseif( 1 == $acting_opts['connection'] && !empty( $results['order_number'] ) ) {
				$data = array();
				$data['acting'] = 'zeus_card';
				$data['order_number'] = $results['order_number'];
				if( 'on' == $acting_opts['howpay'] ) {
					if( $entry['order']['howpay'] == '0' ) {
						$div_name = 'div_'.$entry['order']['cbrand'];
						$data['howpay'] = $entry['order']['howpay'];
						$data['div'] = $entry['order'][$div_name];
					} else {
						$data['howpay'] = '1';
						$data['div'] = '01';
					}
				}
				$usces->set_order_meta_value( 'acting_zeus_card', usces_serialize( $data ), $order_id );
				$usces->set_order_meta_value( 'wc_trans_id', $results['order_number'], $order_id );
				$usces->set_order_meta_value( 'trans_id', $results['order_number'], $order_id );
			}

		//*** zeus conv ***//
		} elseif( $payments['settlement'] == 'acting_zeus_conv' && !empty( $results['wctid'] ) ) {
			$data = array();
			$data['acting'] = $results['acting'];
			$data['status'] = $results['status'];
			$data['order_no'] = $results['order_no'];
			$data['pay_no1'] = $results['pay_no1'];
			$data['pay_no2'] = $results['pay_no2'];
			$data['pay_url'] = $results['pay_url'];
			$data['pay_limit'] = $results['pay_limit'];
			$data['error_code'] = $results['error_code'];
			$data['pay_cvs'] = $results['pay_cvs'];
			$data['wctid'] = $results['wctid'];
			$usces->set_order_meta_value( 'acting_'.$results['wctid'], usces_serialize( $data ), $order_id );
			if( !empty( $results['order_no'] ) ) {
				$usces->set_order_meta_value( 'wc_trans_id', $results['order_no'], $order_id );
				$usces->set_order_meta_value( 'trans_id', $results['order_no'], $order_id );
			}
		}
	}

	/**
	 * 決済エラーメッセージ
	 * @fook   usces_filter_get_error_settlement
	 * @param  $html
	 * @return string $html
	 */
	public function error_page_message( $html ) {

		if( isset( $_REQUEST['acting'] ) && ( 'zeus_conv' == $_REQUEST['acting'] || 'zeus_card' == $_REQUEST['acting'] || 'zeus_bank' == $_REQUEST['acting'] ) ) {
			if( 'zeus_card' == $_REQUEST['acting'] ) {
				$html .= '<div class="support_box">';
				if( isset( $_GET['code'] ) ) {
					$html .= '<br />エラーコード：'.esc_html( $_GET['code'] );
					if( in_array( $_GET['code'], array( '02130514', '02130517', '02130619', '02130620', '02130621', '02130640' ) ) ) {
						$html .= '<br />カード番号が正しくないようです。';
					} elseif( in_array( $_GET['code'], array( '02130714', '02130717', '02130725', '02130814', '02130817', '02130825' ) ) ) {
						$html .= '<br />カードの有効期限が正しくないようです。';
					} elseif( in_array( $_GET['code'], array( '02130922' ) ) ) {
						$html .= '<br />カードの有効期限が切れているようです。';
					} elseif( in_array( $_GET['code'], array( '02131117', '02131123', '02131124' ) ) ) {
						$html .= '<br />カードの名義が正しくないようです。';
					} elseif( in_array( $_GET['code'], array( '02131414', '02131417', '02131437' ) ) ) {
						$html .= '<br />お客様情報の電話番号が正しくないようです。';
					} elseif( in_array( $_GET['code'], array( '02131527', '02131528', '02131529', '02131537' ) ) ) {
						$html .= '<br />お客様情報のEメールアドレスが正しくないようです。';
					}
					$html .= '<br />
					<br />
					<a href="'.USCES_CUSTOMER_URL.'">もう一度決済を行う 》</a><br />';
				} else {
					$html .= '<br />エラーコード：'.esc_html( $_GET['err_code'] );
					$html .= '<br />
					カード番号を再入力する場合はこちらをクリックしてください。<br />
					<br />
					<a href="'.USCES_CUSTOMER_URL.'&re-enter=1">カード番号の再入力 》</a><br />';
				}
				$html .= '<br />
				株式会社ゼウス カスタマーサポート　（24時間365日対応）<br />
				電話番号：0570-02-3939　（つながらないときは 03-4334-0500）<br />
				E-mail:support@cardservice.co.jp
				</div>'."\n";

			} else {
				$html .= '<div class="support_box">';
				if( isset( $_GET['error_code'] ) ) {
					$html .= '<br />エラーコード：'.esc_html( $_GET['code'] );
					if( in_array( $_GET['code'], array( '800002', '0013' ) ) ) {
						$html .= '<br />このコンビニはお取り扱いできません。詳細に関してはカスタマーサポートまでお問い合わせください。';
					} elseif( in_array( $_GET['code'], array( '900000', '0011' ) ) ) {
						$html .= '<br />お申し込み情報が正しく入力されていないか、通信時にエラーが発生している可能性がございます。入力情報を再度ご確認いただいた上でお申し込みをいただくか、カスタマーサポートまでお問い合わせください。';
					} elseif( in_array( $_GET['code'], array( '0008' ) ) ) {
						$html .= '<br />このコンビニはお取り扱いできません。別のコンビニをご選択いただき、再度お申し込みをいただくか、カスタマーサポートまでお問い合わせください。';
					}
				} else {
					if( 'zeus_conv' == $_REQUEST['acting'] ) {
						$html .= '<br />このコンビニはお取り扱いできません。詳細に関してはカスタマーサポートまでお問い合わせください。';
					} else {
						$html .= '<br />詳細に関してはカスタマーサポートまでお問い合わせください。';
					}
				}
				$html .= '<br />
				<br />
				<a href="'.USCES_CUSTOMER_URL.'">もう一度決済を行う 》</a><br />';
				$html .= '<br />
				株式会社ゼウス カスタマーサポート　（24時間365日対応）<br />
				電話番号：0570-08-3000　（つながらないときは 03-3498-9888）<br />
				E-mail:support@cardservice.co.jp
				</div>'."\n";
			}
		}
		return $html;
	}

	/**
	 * @fook   usces_filter_uscesL10n
	 * @param  $l10n $post_id
	 * @return string $l10n
	 */
	public function set_uscesL10n( $l10n, $post_id ) {
		global $usces;

		if( !$this->is_validity_acting( 'card' ) ) {
			return $l10n;
		}

		if( 'delivery' == $usces->page ) {
			$acting_opts = $this->get_acting_settings();
			$pcid = NULL;
			$partofcard = NULL;
			if( $usces->is_member_logged_in() ) {
				$member = $usces->get_member();
				if( !isset( $_GET['re-enter'] ) ) {
					$pcid = $usces->get_member_meta_value( 'zeus_pcid', $member['ID'] );
					$partofcard = $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] );
				}
			}
			$l10n .= "'zeus_form': 'cart',\n";
			$l10n .= "'zeus_security': '".$acting_opts['security']."',\n";
			$l10n .= "'zeus_quickcharge': '".$acting_opts['quickcharge']."',\n";
			$l10n .= "'zeus_howpay': '".$acting_opts['howpay']."',\n";
			$l10n .= "'zeus_thisyear': '".date_i18n( 'Y' )."',\n";
			$l10n .= "'zeus_pcid': '".$pcid."',\n";
			$l10n .= "'zeus_partofcard': '".$partofcard."',\n";
			if( !empty( $pcid ) && !empty( $partofcard ) ) {
				$member_update_settlement = add_query_arg( array( 'page'=>'member_update_settlement', 're-enter'=>1 ), USCES_MEMBER_URL );
				$l10n .= "'zeus_cardupdate_url': '".urlencode( $member_update_settlement )."',\n";
			}

		} elseif( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			if( isset( $_GET['page'] ) && ( 'member_register_settlement' == $_GET['page'] || 'member_update_settlement' == $_GET['page'] ) ) {
				$acting_opts = $this->get_acting_settings();
				$member = $usces->get_member();
				$pcid = $usces->get_member_meta_value( 'zeus_pcid', $member['ID'] );
				$partofcard = $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] );
				$l10n .= "'zeus_form': 'member',\n";
				$l10n .= "'zeus_security': '".$acting_opts['security']."',\n";
				$l10n .= "'zeus_quickcharge': '".$acting_opts['quickcharge']."',\n";
				$l10n .= "'zeus_thisyear': '".date_i18n( 'Y' )."',\n";
				$l10n .= "'zeus_thismonth': '".date_i18n( 'm' )."',\n";
				$l10n .= "'zeus_pcid': '".$pcid."',\n";
				$l10n .= "'zeus_partofcard': '".$partofcard."',\n";
			}
		}
		return $l10n;
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
			if( 'on' == $acting_opts['card_activate'] ):
				wp_enqueue_style( 'zeus-token-style', USCES_FRONT_PLUGIN_URL.'/css/zeus_token.css' );
				wp_enqueue_script( 'zeus-token-script', USCES_FRONT_PLUGIN_URL.'/js/zeus_token.js' );
				wp_enqueue_script( 'usces_cart_zeus', USCES_FRONT_PLUGIN_URL.'/js/cart_zeus.js', array( 'jquery' ), USCES_VERSION, true );
?>
<script type="text/javascript">
var zeusTokenIpcode = "<?php echo esc_attr( $acting_opts['clientip'] ); ?>";
</script>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {

	$( document ).on( "click", "input[name='offer[howpay]']", function() {
		if( '1' == $( this ).val() ) {
			$( "#cbrand_zeus" ).css( "display", "none" );
			$( "#div_zeus" ).css( "display", "none" );
		} else {
			$( "#cbrand_zeus" ).css( "display", "" );
		}
	});

	$( document ).on( "change", "select[name='offer[cbrand]']", function() {
		$( "#div_zeus" ).css( "display", "" );
		if( '1' == $( this ).val() ) {
			$( "#brand1" ).css( "display", "" );
			$( "#brand2" ).css( "display", "none" );
			$( "#brand3" ).css( "display", "none" );
		} else if( '2' == $( this ).val() ) {
			$( "#brand1" ).css( "display", "none" );
			$( "#brand2" ).css( "display", "" );
			$( "#brand3" ).css( "display", "none" );
		} else if( '3' == $( this ).val() ) {
			$( "#brand1" ).css( "display", "none" );
			$( "#brand2" ).css( "display", "none" );
			$( "#brand3" ).css( "display", "" );
		} else {
			$( "#brand1" ).css( "display", "none" );
			$( "#brand2" ).css( "display", "none" );
			$( "#brand3" ).css( "display", "none" );
		}
	});

	if( '' != $( "select[name='offer[cbrand]'] option:selected" ).val() ) {
		$( "#div_zeus" ).css( "display", "" );
	}
	if( '1' == $( "input[name='offer[howpay]']:checked" ).val() ) {
		$( "#cbrand_zeus" ).css( "display", "none" );
		$( "#div_zeus" ).css( "display", "none" );
	} else {
		$( "#cbrand_zeus" ).css( "display", "" );
	}
	if( '1' == $( "select[name='offer[cbrand]'] option:selected" ).val() ) {
		$( "#brand1" ).css( "display", "" );
		$( "#brand2" ).css( "display", "none" );
		$( "#brand3" ).css( "display", "none" );
	} else if( '2' == $( "select[name='offer[cbrand]'] option:selected" ).val() ) {
		$( "#brand1" ).css( "display", "none" );
		$( "#brand2" ).css( "display", "" );
		$( "#brand3" ).css( "display", "none" );
	} else if( '3' == $( "select[name='offer[cbrand]'] option:selected" ).val() ) {
		$( "#brand1" ).css( "display", "none" );
		$( "#brand2" ).css( "display", "none" );
		$( "#brand3" ).css( "display", "" );
	} else {
		$( "#brand1" ).css( "display", "none" );
		$( "#brand2" ).css( "display", "none" );
		$( "#brand3" ).css( "display", "none" );
	}
});
</script>
<?php
			endif;

		//マイページ
		elseif( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ):
			if( isset( $_GET['page'] ) && ( 'member_register_settlement' == $_GET['page'] || 'member_update_settlement' == $_GET['page'] ) ):
				$acting_opts = $this->get_acting_settings();
				wp_enqueue_style( 'zeus-token-style', USCES_FRONT_PLUGIN_URL.'/css/zeus_token.css' );
				wp_enqueue_script( 'zeus-token-script', USCES_FRONT_PLUGIN_URL.'/js/zeus_token.js' );
				wp_enqueue_script( 'usces_member_zeus', USCES_FRONT_PLUGIN_URL.'/js/member_zeus.js', array( 'jquery' ), USCES_VERSION, true );
?>
<script type="text/javascript">
var zeusTokenIpcode = "<?php echo esc_attr( $acting_opts['clientip'] ); ?>";
</script>
<?php
			else:
				$member = $usces->get_member();
				if( usces_have_member_regular_order( $member['ID'] ) ):
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	$( "input[name='deletemember']" ).css( "display", "none" );
});
</script>
<?php
				endif;
			endif;
		endif;
	}

	/**
	 * 利用可能な決済モジュール
	 * @fook   usces_filter_available_payment_method
	 * @param  $payments
	 * @return array $payments
	 */
	public function set_available_payment_method( $payments ) {
		global $usces;

		if( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			$payment_method = array();
			foreach( (array)$payments as $id => $payment ) {
				if( 'acting_zeus_card' == $payment['settlement'] ) {
					$payment_method[$id] = $payments[$id];
					break;
				}
			}
			if( !empty( $payment_method ) ) $payments = $payment_method;
		}
		return $payments;
	}

	/**
	 * クレジットカード登録・変更ページ表示
	 * @fook   usces_filter_template_redirect
	 * @param  -
	 * @return -
	 */
	public function member_update_settlement() {
		global $usces;

		if( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			if( !usces_is_membersystem_state() || !usces_is_login() ) {
				return;
			}

			$acting_opts = $this->get_acting_settings();
			if( 'on' != $acting_opts['quickcharge'] ) {
				return;
			}

			if( isset( $_REQUEST['page'] ) && 'member_update_settlement' == $_REQUEST['page'] ) {
				$usces->page = 'member_update_settlement';
				$this->member_update_settlement_form();
				exit();

			} elseif( isset( $_REQUEST['page'] ) && 'member_register_settlement' == $_REQUEST['page'] && 'on' == $acting_opts['batch'] ) {
				$usces->page = 'member_register_settlement';
				$this->member_update_settlement_form();
				exit();
			}
		}
		return false;
	}

	/**
	 * 会員データ削除チェック
	 * @fook   usces_filter_delete_member_check
	 * @param  $del $member_id
	 * @return boolean $del
	 */
	public function delete_member_check( $del, $member_id ) {

		if( usces_have_member_regular_order( $member_id ) ) {
			$del = false;
		}
		return $del;
	}

	/**
	 * クレジットカード登録・変更ページリンク
	 * @fook   usces_action_member_submenu_list
	 * @param  -
	 * @return -
	 * @echo   update_settlement()
	 */
	public function e_update_settlement() {
		global $usces;

		$member = $usces->get_member();
		$html = $this->update_settlement( '', $member );
		echo $html;
	}

	/**
	 * クレジットカード登録・変更ページリンク
	 * @fook   usces_filter_member_submenu_list
	 * @param  $html $member
	 * @return string $html
	 */
	public function update_settlement( $html, $member ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if( 'on' == $acting_opts['quickcharge'] ) {
			$member = $usces->get_member();
			$pcid = $usces->get_member_meta_value( 'zeus_pcid', $member['ID'] );
			$partofcard = $usces->get_member_meta_value( 'zeus_partofcard', $member['ID'] );
			if( !empty( $pcid ) && !empty( $partofcard ) ) {
				$update_settlement_url = add_query_arg( array( 'page' => 'member_update_settlement', 're-enter' => 1 ), USCES_MEMBER_URL );
				$html .= '
				<div class="gotoedit">
				<a href="'.$update_settlement_url.'">'.__( "Change the credit card is here >>", 'usces' ).'</a>
				</div>';
			} elseif( 'on' == $acting_opts['batch'] ) {
				$register_settlement_url = add_query_arg( array( 'page'=>'member_register_settlement', 're-enter'=>1 ), USCES_MEMBER_URL );
				$html .= '
				<div class="gotoedit">
				<a href="'.$register_settlement_url.'">'.__( "Credit card registration is here >>", 'usces' ).'</a>
				</div>';
			}
		}
		return $html;
	}

	/**
	 * クレジットカード登録・変更ページ
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function member_update_settlement_form() {
		global $usces;

		$script = '';
		$message = '';
		$html = '';
		$update_settlement_url = add_query_arg( array( 'page' => 'member_update_settlement', 'settlement' => 1, 're-enter' => 1 ), USCES_MEMBER_URL );
		$register = ( 'member_register_settlement' == $usces->page ) ? true : false;

		$member = $usces->get_member();
		$acting_opts = $this->get_acting_settings();

		if( isset( $_POST['zeus_card_update'] ) ) {
			if( !wp_verify_nonce( $_POST['wc_nonce'], 'member_update_settlement' ) || empty( $_POST['zeus_token_value'] ) ) {
				$usces->error_message = __( 'failure in update', 'usces' );

			} else {
				$interface = parse_url( $acting_opts['card_url'] );
				if( defined( 'ZEUS_SSL_TEST' ) ) {
					$interface['host'] = ZEUS_SSL_TEST.$interface['host'];
				}
				if( defined( 'ZEUS_TLS_TEST' ) ) {
					$interface['host'] = ZEUS_TLS_TEST;
				}
				$rand = usces_rand();

				$params = array();
				$params['send'] = 'mall';
				$params['clientip'] = $acting_opts['clientip'];
				$params['token_key'] = $_POST['zeus_token_value'];
				$params['cardnumber'] = '8888888888888882';
				$params['expyy'] = '00';
				$params['expmm'] = '00';
				$params['money'] = '0';
				$params['telno'] = str_replace( '-', '', $member['tel'] );
				$params['email'] = $member['mailaddress1'];
				$params['sendid'] = $member['ID'];
				$params['sendpoint'] = $rand;
				$params['printord'] = '';
				$params['return_value'] = 'yes';
				$vars = http_build_query( $params );

				$header  = "POST ".$interface['path']." HTTP/1.1\r\n";
				$header .= "Host: ".$_SERVER['HTTP_HOST']."\r\n";
				$header .= "User-Agent: PHP Script\r\n";
				$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$header .= "Content-Length: ".strlen( $vars )."\r\n";
				$header .= "Connection: close\r\n\r\n";
				$header .= $vars;

				$fp = @stream_socket_client( 'tlsv1.2://'.$interface['host'].':443', $errno, $errstr, 30 );
				if( !$fp ) {
					usces_log( 'zeus card : TLS(v1.2) Error', 'acting_transaction.log' );
					$fp = fsockopen( 'ssl://'.$interface['host'], 443, $errno, $errstr, 30 );
					if( !$fp ) {
						usces_log( 'zeus card : SSL Error', 'acting_transaction.log' );
						header( 'Location: '.$update_settlement_url );
						exit;
					}
				}

				if( $fp ) {
					$page = '';
					fwrite( $fp, $header );
					while( !feof( $fp ) ) {
						$scr = fgets( $fp, 1024 );
						$page .= $scr;
					}
					fclose( $fp );

					if( false !== strpos( $page, 'Success_order' ) ) {
						usces_log( 'zeus card : Settlement update', 'acting_transaction.log' );
						$usces->error_message = '';
						$message = __( 'Successfully updated.', 'usces' );
						if( !empty( $_POST['zeus_token_masked_card_no'] ) ) {
							$partofcard = substr( $_POST['zeus_token_masked_card_no'], -4 );
							$usces->set_member_meta_value( 'zeus_partofcard', $partofcard );
						}
						$this->send_update_settlement_mail();
					} else {
						$err_code = $this->get_err_code( $page );
						usces_log( 'zeus card : Certification Error : '.$err_code, 'acting_transaction.log' );
						$usces->error_message = __( 'failure in update', 'usces' );
					}
				} else {
					usces_log( 'zeus card : Socket Error', 'acting_transaction.log' );
					$usces->error_message = __( 'failure in update', 'usces' );
				}

				if( '' != $message ) {
					$script .= "
<script type=\"text/javascript\">
jQuery.event.add( window, 'load', function() {
	alert( '".$message."' );
});
</script>";
				}
			}
		}
		$error_message = apply_filters( 'usces_filter_member_update_settlement_error_message', $usces->error_message );

		ob_start();
		get_header();
?>
<div id="content" class="two-column">
<div class="catbox">
<?php if( have_posts() ): usces_remove_filter(); ?>
<div class="post" id="wc_member_update_settlement">
<?php if( $register ): ?>
<h1 class="member_page_title"><?php _e( 'Credit card registration', 'usces' ); ?></h1>
<?php else: ?>
<h1 class="member_page_title"><?php _e( 'Credit card update', 'usces' ); ?></h1>
<?php endif; ?>
<div class="entry">
<div id="memberpages">
<div class="whitebox">
	<div id="memberinfo">
	<div class="header_explanation">
	<?php do_action( 'usces_action_member_update_settlement_page_header' ); ?>
	</div>
	<div class="error_message"><?php echo $error_message; ?></div>
	<form id="member-card-info" action="<?php echo $update_settlement_url; ?>" method="post" onKeyDown="if(event.keyCode == 13) {return false;}">
		<input type="hidden" name="acting" value="<?php echo esc_attr( $this->paymod_id ); ?>">
		<table class="customer_form" id="<?php echo esc_attr( $this->paymod_id ); ?>">
		<tr><th scope="row"><?php _e( 'Credit card information', 'usces' ); ?></th><td id="zeus_token_card_info_area"></td></tr>
		</table>
		<div class="send">
		<?php if( $register ): ?>
			<input type="hidden" name="zeus_card_update" value="register" />
			<input type="button" id="card-register" class="card-update" value="<?php _e( 'Register' ); ?>" />
		<?php else: ?>
			<input type="hidden" name="zeus_card_update" value="update" />
			<input type="button" id="card-update" class="card-update" value="<?php _e( 'update it', 'usces' ); ?>" />
		<?php endif; ?>
			<input type="button" name="back" value="<?php _e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo USCES_MEMBER_URL; ?>'" />
			<input type="button" name="top" value="<?php _e( 'Back to the top page.', 'usces' ); ?>" onclick="location.href='<?php echo home_url(); ?>'" />
		</div>
		<?php do_action( 'usces_action_member_update_settlement_page_inform' ); ?>
		<?php wp_nonce_field( 'member_update_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="footer_explanation">
	<?php do_action( 'usces_action_member_update_settlement_page_footer' ); ?>
	</div>
	</div><!-- end of memberinfo -->
</div><!-- end of whitebox -->
</div><!-- end of memberpages -->
</div><!-- end of entry -->
</div><!-- end of post -->
<?php else: ?>
<p><?php _e( 'Sorry, no posts matched your criteria.', 'usces' ); ?></p>
<?php endif; ?>
</div><!-- end of catbox -->
</div><!-- end of content -->
<?php if( '' != $script ) echo $script; ?>
<?php
		$sidebar = apply_filters( 'usces_filter_member_update_settlement_page_sidebar', 'cartmember' );
		if( !empty( $sidebar ) ) get_sidebar( $sidebar );

		get_footer();
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;
	}

	/**
	 * クレジットカード変更メール
	 * @param  -
	 * @return -
	 */
	public function send_update_settlement_mail() {
		global $usces;

		$member = $usces->get_member();
		$mail_data = $usces->options['mail_data'];

		$subject = apply_filters( 'usces_filter_send_update_settlement_mail_subject', __( 'Confirmation of credit card update', 'usces' ), $member );
		$mail_header = __( 'Your credit card information has been updated on the membership page.', 'usces' )."\r\n\r\n";
		$mail_footer = $mail_data['footer']['thankyou'];
		$name = usces_localized_name( $member['name1'], $member['name2'], 'return' );

		$message  = '--------------------------------'."\r\n";
		$message .= __( 'Member ID', 'usces' ).' : '.$member['ID']."\r\n";
		$message .= __( 'Name', 'usces' ).' : '.sprintf( _x( '%s', 'honorific', 'usces' ), $name )."\r\n";
		$message .= __( 'e-mail adress', 'usces' ).' : '.$member['mailaddress1']."\r\n";
		$message .= '--------------------------------'."\r\n\r\n";
		$message .= __( 'If you have not requested this email, sorry to trouble you, but please contact us.', 'usces' )."\r\n\r\n";
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message', $message, $member );
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message_head', $mail_header, $member ).$message.apply_filters( 'usces_filter_send_update_settlement_mail_message_foot', $mail_footer, $member )."\r\n";
		$message  = sprintf( __( 'Dear %s', 'usces' ), $name )."\r\n\r\n".$message;

		$send_para = array(
			'to_name' => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
			'to_address' => $member['mailaddress1'],
			'from_name' => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
		);
		usces_send_mail( $send_para );

		$admin_para = array(
			'to_name' => apply_filters( 'usces_filter_bccmail_to_admin_name', 'Shop Admin' ), 
			'to_address' => $usces->options['order_mail'],
			'from_name' => apply_filters( 'usces_filter_bccmail_from_admin_name', 'Welcart Auto BCC' ), 
			'from_address' => $usces->options['sender_mail'],
			'return_path' => $usces->options['sender_mail'],
			'subject' => $subject,
			'message' => $message
		);
		usces_send_mail( $admin_para );
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
	 * usces_zeus_3dsecure_enrol
	 * ( call from acting_processing )
	 * @param  -
	 * @return -
	 */
	protected function zeus_3dsecure_enrol() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$sendid = ( 'on' == $acting_opts['quickcharge'] && isset( $_POST['sendid'] ) ) ? $_POST['sendid'] : '';
		$sendpoint = ( isset( $_POST['sendpoint'] ) ) ? $_POST['sendpoint'] : '';

		$data = array();
		$data['authentication']['clientip'] = $acting_opts['clientip'];
		$data['authentication']['key'] = $acting_opts['authkey'];
		if( 'on' == $acting_opts['quickcharge'] && isset( $_POST['card_option'] ) && 'prev' == $_POST['card_option'] && !empty( $sendid ) ) {
			$data['card']['history']['key'] = 'sendid';
			$data['card']['history']['action'] = 'send_email';
		} elseif( isset( $_POST['token_key'] ) ) {
			$data['token_key'] = $_POST['token_key'];
		}
		$data['payment']['amount'] = $_POST['money'];
		if( isset( $_POST['howpay'] ) && WCUtils::is_zero( $_POST['howpay'] ) ) {
			$data['payment']['count'] = $_POST['div'];
		} else {
			$data['payment']['count'] = '01';
		}
		$data['user']['telno'] = str_replace( '-', '', $_POST['telno'] );
		$data['user']['email'] = $_POST['email'];
		$data['uniq_key']['sendid'] = $sendid;
		$data['uniq_key']['sendpoint'] = $sendpoint;

		$EnrolReq = '<?xml version="1.0" encoding="utf-8"?>';
		$EnrolReq .= '<request service="secure_link_3d" action="enroll">';
		$EnrolReq .= $this->assoc2xml( $data );
		$EnrolReq .= '</request>';
		usces_log( 'EnrolReq : '.print_r( $EnrolReq, true ), 'acting_transaction.log' );

		$xml = $this->get_xml( $acting_opts['card_secureurl'], $EnrolReq );
		if( empty( $xml) ) {
			$log = array( 'acting'=>'zeus_card_API(3D Enrol)', 'key'=>$sendpoint, 'result'=>'EnrolRes Error', 'data'=>$EnrolReq );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus : EnrolReq Error', 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status=EnrolReq&code=0' );
			exit;
		}

		$EnrolRes = $this->xml2assoc( $xml );
		usces_log( 'EnrolRes : '.print_r( $EnrolRes, true ), 'acting_transaction.log' );

		if( 'outside' == $EnrolRes['response']['result']['status'] ) {
			usces_log( 'EnrolRes : outside', 'acting_transaction.log' );
			usces_ordered_acting_data( $sendpoint, 'propriety' );

			$data = array();
			$data['xid'] = $EnrolRes['response']['xid'];//$_REQUEST['MD'];
			$PayReq = '<?xml version="1.0" encoding="utf-8" ?>';
			$PayReq .= '<request service="secure_link_3d" action="payment">';
			$PayReq .= $this->assoc2xml( $data );
			$PayReq .= '</request>';

			$xml = $this->get_xml( $acting_opts['card_secureurl'], $PayReq );
			if( empty( $xml) ) {
				$log = array( 'acting'=>'zeus_card_API(3D Payment)', 'key'=>$sendpoint, 'result'=>'PayRes Error', 'data'=>$PayReq );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus : PayReq Error', 'acting_transaction.log' );
				header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status=PayRes&code=0' );
				exit;
			}

			$PayRes = $this->xml2assoc( $xml );
			usces_log( 'usces_zeus_3dsecure_enrol : PayRes '.print_r( $PayRes, true ), 'acting_transaction.log' );

			if( 'success' == $PayRes['response']['result']['status'] ) {
				header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=1&zeussuffix='.$PayRes['response']['card']['number']['suffix'].'&zeusordd='.$PayRes['response']['order_number'].'&wctid='.$sendpoint );
				exit;

			} else {
				$log = array( 'acting'=>'zeus_card_API(3D Payment)', 'key'=>$sendpoint, 'result'=>$PayRes['response']['result']['status'].':'.$PayRes['response']['result']['code'], 'data'=>$PayRes );
				usces_save_order_acting_error( $log );
				usces_log( 'zeus bad status : status='.$PayRes['response']['result']['status'].' code='.$PayRes['response']['result']['code'], 'acting_transaction.log' );
				header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status='.$PayRes['response']['result']['status'].'&code='.$PayRes['response']['result']['code'] );
				exit;
			}

		} elseif( 'success' == $EnrolRes['response']['result']['status'] ) {
			usces_log( 'EnrolRes : success', 'acting_transaction.log' );
			usces_ordered_acting_data( $sendpoint, 'propriety' );
			?>
			<form name="zeus" action="<?php echo $EnrolRes['response']['redirection']['acs_url']; ?>" method="post">
			<input type="hidden" name="MD" value="<?php echo $EnrolRes['response']['xid']; ?>" />
			<input type="hidden" name="PaReq" value="<?php echo $EnrolRes['response']['redirection']['PaReq']; ?>" />
			<input type="hidden" name="TermUrl" value="<?php echo USCES_CART_URL.$usces->delim.'purchase=1&PaRes=1&sendpoint='.$sendpoint; ?>" />
			</form>
			<script type="text/javascript">document.zeus.submit();</script>
			<?php
			exit;

		} else {
			$log = array( 'acting'=>'zeus_card_API(3D Enrol)', 'key'=>$sendpoint, 'result'=>$EnrolRes['response']['result']['status'].':'.$EnrolRes['response']['result']['code'], 'data'=>$EnrolRes );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus bad status : status='.$EnrolRes['response']['result']['status'].' code='.$EnrolRes['response']['result']['code'], 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status='.$EnrolRes['response']['result']['status'].'&code='.$EnrolRes['response']['result']['code'] );
			exit;
		}
	}

	/**
	 * usces_zeus_3dsecure_auth
	 * ( call from acting_processing )
	 * @param  -
	 * @return -
	 */
	protected function zeus_3dsecure_auth() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$sendpoint = ( isset( $_REQUEST['sendpoint'] ) ) ? $_REQUEST['sendpoint'] : '';

		$data = array();
		$data['xid'] = $_REQUEST['MD'];
		$data['PaRes'] = $_REQUEST['PaRes'];
		$AuthReq = '<?xml version="1.0" encoding="utf-8" ?>';
		$AuthReq .= '<request service="secure_link_3d" action="authentication">';
		$AuthReq .= $this->assoc2xml( $data );
		$AuthReq .= '</request>';

		$xml = $this->get_xml( $acting_opts['card_secureurl'], $AuthReq );
		if( false !== strpos( $xml, 'Invalid' ) ) {
			$log = array( 'acting'=>'zeus_card_API(3D Auth)', 'key'=>$sendpoint, 'result'=>'AuthReq Error', 'data'=>$AuthReq );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus : AuthReq Error'.print_r( $xml, true ), 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status=AuthReq&code=0' );
			exit;
		}

		$AuthRes = $this->xml2assoc( $xml );
		usces_log( 'usces_zeus_3dsecure_auth : AuthRes '.print_r( $AuthRes, true ), 'acting_transaction.log' );

		if( 'success' != $AuthRes['response']['result']['status'] ) {
			$log = array( 'acting'=>'zeus_card_API(3D Auth)', 'key'=>$sendpoint, 'result'=>$AuthRes['response']['result']['status'].':'.$AuthRes['response']['result']['code'], 'data'=>$AuthRes );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus bad status : status='.$AuthRes['response']['result']['status'].' code='.$AuthRes['response']['result']['code'], 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status='.$AuthRes['response']['result']['status'].'&code='.$AuthRes['response']['result']['code'] );
			exit;
		}

		$data = array();
		$data['xid'] = $_REQUEST['MD'];
		$PayReq = '<?xml version="1.0" encoding="utf-8" ?>';
		$PayReq .= '<request service="secure_link_3d" action="payment">';
		$PayReq .= $this->assoc2xml( $data );
		$PayReq .= '</request>';

		$xml = $this->get_xml( $acting_opts['card_secureurl'], $PayReq );
		if( empty( $xml) ) {
			$log = array( 'acting'=>'zeus_card_API(3D Auth)', 'key'=>$sendpoint, 'result'=>'PayReq Error', 'data'=>$PayReq );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus : PayReq Error', 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status=PayRes&code=0' );
			exit;
		}

		$PayRes = $this->xml2assoc( $xml );
		usces_log( 'usces_zeus_3dsecure_auth : PayRes '.print_r( $PayRes, true ), 'acting_transaction.log' );

		if( 'success' == $PayRes['response']['result']['status'] ) {
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=1&zeussuffix='.$PayRes['response']['card']['number']['suffix'].'&zeusordd='.$PayRes['response']['order_number'].'&wctid='.$sendpoint );
			exit;

		} else {
			$log = array( 'acting'=>'zeus_card_API(3D Auth)', 'key'=>$sendpoint, 'result'=>$PayRes['response']['result']['status'].':'.$PayRes['response']['result']['code'], 'data'=>$PayRes );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus bad status : status='.$PayRes['response']['result']['status'].' code='.$PayRes['response']['result']['code'], 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status='.$PayRes['response']['result']['status'].'&code='.$PayRes['response']['result']['code'] );
			exit;
		}
		exit;
	}

	/**
	 * usces_zeus_secure_payreq
	 * ( call from acting_processing )
	 * @param  -
	 * @return -
	 */
	protected function zeus_secure_payreq() {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$sendid = ( 'on' == $acting_opts['quickcharge'] && isset( $_POST['sendid'] ) ) ? $_POST['sendid'] : '';
		$sendpoint = ( isset( $_POST['sendpoint'] ) ) ? $_POST['sendpoint'] : '';

		$data = array();
		$data['authentication']['clientip'] = $acting_opts['clientip'];
		$data['authentication']['key'] = $acting_opts['authkey'];
		if( 'on' == $acting_opts['quickcharge'] && isset( $_POST['card_option'] ) && 'prev' == $_POST['card_option'] && !empty( $sendid ) ) {
			$data['card']['history']['key'] = 'sendid';
			$data['card']['history']['action'] = 'send_email';
		} elseif( isset( $_POST['token_key'] ) ) {
			$data['token_key'] = $_POST['token_key'];
		}
		$data['payment']['amount'] = $_POST['money'];
		if( isset( $_POST['howpay'] ) && WCUtils::is_zero( $_POST['howpay'] ) ) {
			$data['payment']['count'] = $_POST['div'];
		} else {
			$data['payment']['count'] = '01';
		}
		$data['user']['telno'] = str_replace( '-', '', $_POST['telno'] );
		$data['user']['email'] = $_POST['email'];
		$data['uniq_key']['sendid'] = $sendid;
		$data['uniq_key']['sendpoint'] = $_POST['sendpoint'];

		$PayReq = '<?xml version="1.0" encoding="utf-8" ?>';
		$PayReq .= '<request service="secure_link" action="payment">';
		$PayReq .= $this->assoc2xml( $data );
		$PayReq .= '</request>';

		$xml = $this->get_xml( $acting_opts['card_secureurl'], $PayReq );
		if( empty( $xml) ) {
			$log = array( 'acting'=>'zeus_card_API', 'key'=>$sendpoint, 'result'=>'PayReq Error', 'data'=>$PayReq );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus : PayReq Error', 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status=PayReq&code=0' );
			exit;
		}

		usces_ordered_acting_data( $sendpoint, 'propriety' );

		$PayRes = $this->xml2assoc( $xml );
		usces_log( 'usces_zeus_secure_payreq : PayRes'.print_r( $PayRes, true ), 'acting_transaction.log' );

		if( 'success' == $PayRes['response']['result']['status'] ) {
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=1&zeussuffix='.$PayRes['response']['card']['number']['suffix'].'&zeusordd='.$PayRes['response']['order_number'].'&wctid='.$sendpoint );
			exit;

		} else {
			$log = array( 'acting'=>'zeus_card_API', 'key'=>$sendpoint, 'result'=>$PayRes['response']['result']['status'].':'.$PayRes['response']['result']['code'], 'data'=>$PayRes );
			usces_save_order_acting_error( $log );
			usces_log( 'zeus bad status : status='.$PayRes['response']['result']['status'].' code='.$PayRes['response']['result']['code'], 'acting_transaction.log' );
			header( 'Location: '.USCES_CART_URL.$usces->delim.'acting=zeus_card&acting_return=0&status='.$PayRes['response']['result']['status'].'&code='.$PayRes['response']['result']['code'] );
			exit;
		}
	}

	/**
	 * usces_xml2assoc
	 * @param  $xml
	 * @return array $arr
	 */
	protected function xml2assoc( $xml ) {

		$arr = array();
		if( !preg_match_all( '|\<\s*?(\w+).*?\>(.*)\<\/\s*\\1.*?\>|s', $xml, $m ) ) return $xml;
		if( is_array( $m[1] ) ) {
			for( $i = 0; $i < sizeof( $m[1] ); $i++ ) {
				$arr[$m[1][$i]] = $this->xml2assoc( $m[2][$i] );
			}
		} else {
			$arr[$m[1]] = $this->xml2assoc( $m[2] );
		}
		return $arr;
	}

	/**
	 * usces_assoc2xml
	 * @param  $prm_array
	 * @return string $xml
	 */
	protected function assoc2xml( $prm_array ) {

		$xml = '';
		if( is_array( $prm_array ) ) {
			$i = 0;
			foreach( $prm_array as $index => $element ) {
				if( is_array( $element ) ) {
					$acts = explode( '_', $index, 3 );
					if( is_array( $acts ) && 2 < count( $acts ) && 'history' == $acts[0] && 'action' == $acts[1] ) {
						$xml .= '<history action="'.$acts[2].'">';
						$xml .= $this->assoc2xml( $element );
						$xml .= '</history>';
					} else {
						$xml .= '<'.$index.'>';
						$xml .= $this->assoc2xml( $element );
						$xml .= '</'.$index.'>';
					}
				} else {
					$xml .= '<'.$index.'>'.$element.'</'.$index.'>';
				}
				$i++;
				if( $i > 500 ) break;
			}
		}
		return $xml;
	}

	/**
	 * usces_get_xml
	 * @param  $url $paras
	 * @return string $xml
	 */
	protected function get_xml( $url, $paras ) {

		$interface = parse_url( $url );
		if( defined( 'ZEUS_SSL_TEST' ) ) {
			$interface['host'] = ZEUS_SSL_TEST.$interface['host'];
		}
		if( defined( 'ZEUS_TLS_TEST' ) ) {
			$interface['host'] = ZEUS_TLS_TEST;
		}

		$header  = "POST ".$interface['path']." HTTP/1.1\r\n";
		$header .= "Host: ".$_SERVER['HTTP_HOST']."\r\n";
		$header .= "User-Agent: PHP Script\r\n";
		$header .= "Content-Type: text/xml\r\n";
		$header .= "Content-Length: ".strlen( $paras )."\r\n";
		$header .= "Connection: close\r\n\r\n";
		$header .= $paras;

		$fp = @stream_socket_client( 'tlsv1.2://'.$interface['host'].':443', $errno, $errstr, 30 );
		if( !$fp ) {
			usces_log( 'zeus API : TLS(v1.2) Error', 'acting_transaction.log' );
			$fp = fsockopen( 'ssl://'.$interface['host'], 443, $errno, $errstr, 30 );
			if( !$fp ) {
				usces_log( 'zeus API : SSL Error', 'acting_transaction.log' );
			}
		}

		$xml = '';
		if( $fp ) {
			fwrite( $fp, $header );
			while( !feof( $fp ) ) {
				$xml .= fgets( $fp, 1024 );
			}
			fclose( $fp );
		}
		return $xml;
	}

	/**
	 * get_clientip
	 * @param  $acting
	 * @return string $clientip
	 */
	protected function get_clientip( $acting ) {

		$clientip = '';
		$acting_opts = $this->get_acting_settings();
		switch( $acting ) {
		case 'zeus_card':
			$clientip = $acting_opts['clientip'];
			break;
		case 'zeus_conv':
			$clientip = $acting_opts['clientip_conv'];
			break;
		case 'zeus_bank':
			$clientip = $acting_opts['clientip_bank'];
			break;
		}
		return $clientip;
	}

	/**
	 * get_order_number
	 * @param  $page
	 * @return string $ordd
	 */
	protected function get_order_number( $page ) {
		if( empty( $page) ) return '';

		$log = explode( "\r\n", $page );
		$ordd = '';
		foreach( (array)$log as $line ) {
			if( false !== strpos( $line, 'ordd' ) ) {
				list( $status, $ordd ) = explode( "=", $line );
			}
		}
		return $ordd;
	}

	/**
	 * get_err_code
	 * @param  $page
	 * @return string $err_code
	 */
	protected function get_err_code( $page ) {
		if( empty( $page ) ) return '';

		$log = explode( "\r\n", $page );
		$err_code = '';
		foreach( (array)$log as $line ) {
			if( false !== strpos( $line, 'err_code' ) ) {
				list( $name, $err_code ) = explode( "=", $line );
			}
		}
		return $err_code;
	}

	/**
	 * Get order_id by meta_data ( conv, bank )
	 * @param  $order_id
	 * @return string $err_code
	 */
	protected function get_order_id( $key ) {
		global $wpdb;

		$order_meta_table_name = $wpdb->prefix.'usces_order_meta';
		$query = $wpdb->prepare( "SELECT order_id FROM $order_meta_table_name WHERE meta_key = %s", 'acting_'.$key );
		$order_id = $wpdb->get_var( $query );
		return $order_id;
	}

	/**
	 * Get order_meta_data ( conv, bank )
	 * @param  $order_id
	 * @return array $acting_data
	 */
	protected function get_order_meta_acting( $order_id ) {
		global $wpdb;

		$order_meta_table_name = $wpdb->prefix.'usces_order_meta';
		$query = $wpdb->prepare( "SELECT meta_value FROM $order_meta_table_name WHERE order_id = %d AND meta_key LIKE %s", $order_id, 'acting_%' );
		$meta_value = $wpdb->get_var( $query );
		$acting_data = usces_unserialize( $meta_value );
		return $acting_data;
	}
}
