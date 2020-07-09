<?php
/**
 * SBペイメントサービス
 *
 * @class    SBPS_SETTLEMENT
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    1.9.16
 */
class SBPS_SETTLEMENT extends SBPS_MAIN
{
	/**
	 * Instance of this class.
	 */
	protected static $instance = null;

	public function __construct() {

		$this->acting_name = 'SBPS';
		$this->acting_formal_name = 'SBペイメントサービス';

		$this->acting_card = 'sbps_card';
		$this->acting_conv = 'sbps_conv';
		$this->acting_payeasy = 'sbps_payeasy';
		$this->acting_wallet = 'sbps_wallet';
		$this->acting_mobile = 'sbps_mobile';

		$this->acting_flg_card = 'acting_sbps_card';
		$this->acting_flg_conv = 'acting_sbps_conv';
		$this->acting_flg_payeasy = 'acting_sbps_payeasy';
		$this->acting_flg_wallet = 'acting_sbps_wallet';
		$this->acting_flg_mobile = 'acting_sbps_mobile';

		$this->pay_method = array(
			'acting_sbps_card',
			'acting_sbps_conv',
			'acting_sbps_payeasy',
			'acting_sbps_wallet',
			'acting_sbps_mobile',
		);

		parent::__construct( 'sbps' );

		$this->initialize_data();
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['sbps'] ) ) {
			$options['acting_settings']['sbps']['merchant_id'] = ( isset( $options['acting_settings']['sbps']['merchant_id'] ) ) ? $options['acting_settings']['sbps']['merchant_id'] : '';
			$options['acting_settings']['sbps']['service_id'] = ( isset( $options['acting_settings']['sbps']['service_id'] ) ) ? $options['acting_settings']['sbps']['service_id'] : '';
			$options['acting_settings']['sbps']['hash_key'] = ( isset( $options['acting_settings']['sbps']['hash_key'] ) ) ? $options['acting_settings']['sbps']['hash_key'] : '';
			$options['acting_settings']['sbps']['ope'] = ( isset( $options['acting_settings']['sbps']['ope'] ) ) ? $options['acting_settings']['sbps']['ope'] : '';
			$options['acting_settings']['sbps']['send_url'] = ( isset( $options['acting_settings']['sbps']['send_url'] ) ) ? $options['acting_settings']['sbps']['send_url'] : '';
			$options['acting_settings']['sbps']['send_url_check'] = ( isset( $options['acting_settings']['sbps']['send_url_check'] ) ) ? $options['acting_settings']['sbps']['send_url_check'] : '';
			$options['acting_settings']['sbps']['send_url_test'] = ( isset( $options['acting_settings']['sbps']['send_url_test'] ) ) ? $options['acting_settings']['sbps']['send_url_test'] : '';
			$options['acting_settings']['sbps']['card_activate'] = ( isset( $options['acting_settings']['sbps']['card_activate'] ) ) ? $options['acting_settings']['sbps']['card_activate'] : 'off';
			$options['acting_settings']['sbps']['3d_secure'] = ( isset( $options['acting_settings']['sbps']['3d_secure'] ) ) ? $options['acting_settings']['sbps']['3d_secure'] : 'off';
			$options['acting_settings']['sbps']['cust_manage'] = ( isset( $options['acting_settings']['sbps']['cust_manage'] ) ) ? $options['acting_settings']['sbps']['cust_manage'] : 'off';
			$options['acting_settings']['sbps']['sales'] = ( isset( $options['acting_settings']['sbps']['sales'] ) ) ? $options['acting_settings']['sbps']['sales'] : 'manual';
			$options['acting_settings']['sbps']['3des_key'] = ( isset( $options['acting_settings']['sbps']['3des_key'] ) ) ? $options['acting_settings']['sbps']['3des_key'] : '';
			$options['acting_settings']['sbps']['3desinit_key'] = ( isset( $options['acting_settings']['sbps']['3desinit_key'] ) ) ? $options['acting_settings']['sbps']['3desinit_key'] : '';
			$options['acting_settings']['sbps']['basic_id'] = ( isset( $options['acting_settings']['sbps']['basic_id'] ) ) ? $options['acting_settings']['sbps']['basic_id'] : '';
			$options['acting_settings']['sbps']['basic_password'] = ( isset( $options['acting_settings']['sbps']['basic_password'] ) ) ? $options['acting_settings']['sbps']['basic_password'] : '';
			$options['acting_settings']['sbps']['conv_activate'] = ( isset( $options['acting_settings']['sbps']['conv_activate'] ) ) ? $options['acting_settings']['sbps']['conv_activate'] : 'off';
			$options['acting_settings']['sbps']['payeasy_activate'] = ( isset( $options['acting_settings']['sbps']['payeasy_activate'] ) ) ? $options['acting_settings']['sbps']['payeasy_activate'] : 'off';
			$options['acting_settings']['sbps']['wallet_yahoowallet'] = ( isset( $options['acting_settings']['sbps']['wallet_yahoowallet'] ) ) ? $options['acting_settings']['sbps']['wallet_yahoowallet'] : 'off';
			$options['acting_settings']['sbps']['wallet_rakuten'] = ( isset( $options['acting_settings']['sbps']['wallet_rakuten'] ) ) ? $options['acting_settings']['sbps']['wallet_rakuten'] : 'off';
			$options['acting_settings']['sbps']['wallet_paypal'] = ( isset( $options['acting_settings']['sbps']['wallet_paypal'] ) ) ? $options['acting_settings']['sbps']['wallet_paypal'] : 'off';
			$options['acting_settings']['sbps']['wallet_netmile'] = 'off';
			$options['acting_settings']['sbps']['wallet_alipay'] = ( isset( $options['acting_settings']['sbps']['wallet_alipay'] ) ) ? $options['acting_settings']['sbps']['wallet_alipay'] : 'off';
			$options['acting_settings']['sbps']['wallet_activate'] = ( isset( $options['acting_settings']['sbps']['wallet_activate'] ) ) ? $options['acting_settings']['sbps']['wallet_activate'] : 'off';
			$options['acting_settings']['sbps']['mobile_docomo'] = ( isset( $options['acting_settings']['sbps']['mobile_docomo'] ) ) ? $options['acting_settings']['sbps']['mobile_docomo'] : 'off';
			$options['acting_settings']['sbps']['mobile_auone'] = ( isset( $options['acting_settings']['sbps']['mobile_auone'] ) ) ? $options['acting_settings']['sbps']['mobile_auone'] : 'off';
			$options['acting_settings']['sbps']['mobile_mysoftbank'] = 'off';
			$options['acting_settings']['sbps']['mobile_softbank2'] = ( isset( $options['acting_settings']['sbps']['mobile_softbank2'] ) ) ? $options['acting_settings']['sbps']['mobile_softbank2'] : 'off';
			$options['acting_settings']['sbps']['mobile_activate'] = ( isset( $options['acting_settings']['sbps']['mobile_activate'] ) ) ? $options['acting_settings']['sbps']['mobile_activate'] : 'off';
			update_option( 'usces', $options );
		}

		$available_settlement = get_option( 'usces_available_settlement' );
		if( !in_array( 'sbps', $available_settlement ) ) {
			$available_settlement['sbps'] = $this->acting_formal_name;
			update_option( 'usces_available_settlement', $available_settlement );
		}

		$noreceipt_status = get_option( 'usces_noreceipt_status' );
		if( !in_array( 'acting_sbps_conv', $noreceipt_status ) || !in_array( 'acting_sbps_payeasy', $noreceipt_status ) ) {
			$noreceipt_status[] = 'acting_sbps_conv';
			$noreceipt_status[] = 'acting_sbps_payeasy';
			update_option( 'usces_noreceipt_status', $noreceipt_status );
		}

		$this->unavailable_method = array( 'acting_dsk_card', 'acting_dsk_conv', 'acting_dsk_payeasy' );
	}

	/**
	 * @fook   admin_print_footer_scripts
	 * @param  -
	 * @return -
	 * @echo   js
	 */
	public function admin_scripts() {

		$admin_page = ( isset( $_GET['page'] ) ) ? wp_unslash( $_GET['page'] ) : '';
		switch( $admin_page ):
		case 'usces_settlement':
			$settlement_selected = get_option( 'usces_settlement_selected' );
			if( in_array( $this->paymod_id, (array)$settlement_selected ) ):
				$acting_opts = $this->get_acting_settings();
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {

	var sbps_card_activate = "<?php echo $acting_opts['card_activate']; ?>";
	if( "token" == sbps_card_activate ) {
		$( ".card_link_sbps" ).css( "display", "none" );
		$( ".card_token_sbps" ).css( "display", "" );
	} else if( "on" == sbps_card_activate ) {
		$( ".card_link_sbps" ).css( "display", "" );
		$( ".card_token_sbps" ).css( "display", "none" );
	} else {
		$( ".card_link_sbps" ).css( "display", "none" );
		$( ".card_token_sbps" ).css( "display", "none" );
	}

	$( document ).on( "change", ".card_activate_sbps", function() {
		if( "token" == $( this ).val() ) {
			$( ".card_link_sbps" ).css( "display", "none" );
			$( ".card_token_sbps" ).css( "display", "" );
		} else if( "on" == $( this ).val() ) {
			$( ".card_link_sbps" ).css( "display", "" );
			$( ".card_token_sbps" ).css( "display", "none" );
		} else {
			$( ".card_link_sbps" ).css( "display", "none" );
			$( ".card_token_sbps" ).css( "display", "none" );
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

		if( 'sbps' != $_POST['acting'] ) {
			return;
		}

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['sbps'] );
		$options['acting_settings']['sbps']['merchant_id'] = ( isset( $_POST['merchant_id'] ) ) ? trim( $_POST['merchant_id'] ) : '';
		$options['acting_settings']['sbps']['service_id'] = ( isset( $_POST['service_id'] ) ) ? trim( $_POST['service_id'] ) : '';
		$options['acting_settings']['sbps']['hash_key'] = ( isset( $_POST['hash_key'] ) ) ? trim( $_POST['hash_key'] ) : '';
		$options['acting_settings']['sbps']['ope'] = ( isset( $_POST['ope'] ) ) ? $_POST['ope'] : '';
		$options['acting_settings']['sbps']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : 'off';
		$options['acting_settings']['sbps']['3d_secure'] = ( isset( $_POST['3d_secure'] ) ) ? $_POST['3d_secure'] : 'off';
		$options['acting_settings']['sbps']['cust_manage'] = ( isset( $_POST['cust_manage'] ) ) ? $_POST['cust_manage'] : 'off';
		$options['acting_settings']['sbps']['sales'] = ( isset( $_POST['sales'] ) ) ? $_POST['sales'] : 'manual';
		$options['acting_settings']['sbps']['3des_key'] = ( isset( $_POST['3des_key'] ) ) ? trim( $_POST['3des_key'] ) : '';
		$options['acting_settings']['sbps']['3desinit_key'] = ( isset( $_POST['3desinit_key'] ) ) ? trim( $_POST['3desinit_key'] ) : '';
		$options['acting_settings']['sbps']['basic_id'] = ( isset( $_POST['basic_id'] ) ) ? trim( $_POST['basic_id'] ) : '';
		$options['acting_settings']['sbps']['basic_password'] = ( isset( $_POST['basic_password'] ) ) ? trim( $_POST['basic_password'] ) : '';
		$options['acting_settings']['sbps']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : 'off';
		$options['acting_settings']['sbps']['payeasy_activate'] = ( isset( $_POST['payeasy_activate'] ) ) ? $_POST['payeasy_activate'] : 'off';
		$options['acting_settings']['sbps']['wallet_yahoowallet'] = ( isset( $_POST['wallet_yahoowallet'] ) ) ? $_POST['wallet_yahoowallet'] : 'off';
		$options['acting_settings']['sbps']['wallet_rakuten'] = ( isset( $_POST['wallet_rakuten'] ) ) ? $_POST['wallet_rakuten'] : 'off';
		$options['acting_settings']['sbps']['wallet_paypal'] = ( isset( $_POST['wallet_paypal'] ) ) ? $_POST['wallet_paypal'] : 'off';
		$options['acting_settings']['sbps']['wallet_netmile'] = 'off';
		$options['acting_settings']['sbps']['wallet_alipay'] = ( isset( $_POST['wallet_alipay'] ) ) ? $_POST['wallet_alipay'] : 'off';
		$options['acting_settings']['sbps']['wallet_activate'] = ( isset( $_POST['wallet_activate'] ) ) ? $_POST['wallet_activate'] : 'off';
		$options['acting_settings']['sbps']['mobile_docomo'] = ( isset( $_POST['mobile_docomo'] ) ) ? $_POST['mobile_docomo'] : 'off';
		$options['acting_settings']['sbps']['mobile_auone'] = ( isset( $_POST['mobile_auone'] ) ) ? $_POST['mobile_auone'] : 'off';
		$options['acting_settings']['sbps']['mobile_mysoftbank'] = 'off';
		$options['acting_settings']['sbps']['mobile_softbank2'] = ( isset( $_POST['mobile_softbank2'] ) ) ? $_POST['mobile_softbank2'] : 'off';
		$options['acting_settings']['sbps']['mobile_activate'] = ( isset( $_POST['mobile_activate'] ) ) ? $_POST['mobile_activate'] : 'off';

		if( ( 'on' == $options['acting_settings']['sbps']['card_activate'] || 'token' == $options['acting_settings']['sbps']['card_activate'] ) || 
			'on' == $options['acting_settings']['sbps']['conv_activate'] || 
			'on' == $options['acting_settings']['sbps']['payeasy_activate'] || 
			'on' == $options['acting_settings']['sbps']['wallet_activate'] || 
			'on' == $options['acting_settings']['sbps']['mobile_activate'] ) {
			$unavailable_activate = false;
			foreach( $payment_method as $settlement => $payment ) {
				if( in_array( $settlement, $this->unavailable_method ) && 'activate' == $payment['use'] ) {
					$unavailable_activate = true;
					break;
				}
			}
			if( $unavailable_activate ) {
				$this->error_mes .= __( '* Settlement that can not be used together is activated.', 'usces' ).'<br />';
			} else {
				if( WCUtils::is_blank( $_POST['merchant_id'] ) ) {
					$this->error_mes .= '※マーチャントID を入力してください<br />';
				}
				if( WCUtils::is_blank( $_POST['service_id'] ) ) {
					$this->error_mes .= '※サービスID を入力してください<br />';
				}
				if( WCUtils::is_blank( $_POST['hash_key'] ) ) {
					$this->error_mes .= '※ハッシュキーを入力してください<br />';
				}
				if( 'token' == $options['acting_settings']['sbps']['card_activate'] ) {
					if( WCUtils::is_blank( $_POST['3des_key'] ) ) {
						$this->error_mes .= '※3DES 暗号化キーを入力してください<br />';
					}
					if( WCUtils::is_blank( $_POST['3desinit_key'] ) ) {
						$this->error_mes .= '※3DES 初期化キーを入力してください<br />';
					}
					if( WCUtils::is_blank( $_POST['basic_id'] ) ) {
						$this->error_mes .= '※Basic認証ID を入力してください<br />';
					}
					if( WCUtils::is_blank( $_POST['basic_password'] ) ) {
						$this->error_mes .= '※Basic認証 Password を入力してください<br />';
					}
				}
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			$toactive = array();
			if( 'on' == $options['acting_settings']['sbps']['card_activate'] || 'token' == $options['acting_settings']['sbps']['card_activate'] ) {
				$usces->payment_structure[$this->acting_flg_card] = 'カード決済（SBPS）';
				foreach( $payment_method as $settlement => $payment ) {
					if( $this->acting_flg_card == $settlement && 'deactivate' == $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[$this->acting_flg_card] );
			}
			if( 'on' == $options['acting_settings']['sbps']['conv_activate'] ) {
				$usces->payment_structure[$this->acting_flg_conv] = 'コンビニ決済（SBPS）';
				foreach( $payment_method as $settlement => $payment ) {
					if( $this->acting_flg_conv == $settlement && 'deactivate' == $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[$this->acting_flg_conv] );
			}
			if( 'on' == $options['acting_settings']['sbps']['payeasy_activate'] ) {
				$usces->payment_structure[$this->acting_flg_payeasy] = 'ペイジー決済（SBPS）';
				foreach( $payment_method as $settlement => $payment ) {
					if( $this->acting_flg_payeasy == $settlement && 'deactivate' == $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[$this->acting_flg_payeasy] );
			}
			if( 'on' == $options['acting_settings']['sbps']['wallet_yahoowallet'] || 
				'on' == $options['acting_settings']['sbps']['wallet_rakuten'] || 
				'on' == $options['acting_settings']['sbps']['wallet_paypal'] || 
				'on' == $options['acting_settings']['sbps']['wallet_alipay'] ) {
				$options['acting_settings']['sbps']['wallet_activate'] = 'on';
			} else {
				$options['acting_settings']['sbps']['wallet_activate'] = 'off';
			}
			if( 'on' == $options['acting_settings']['sbps']['wallet_activate'] ) {
				$usces->payment_structure[$this->acting_flg_wallet] = 'ウォレット決済（SBPS）';
				foreach( $payment_method as $settlement => $payment ) {
					if( $this->acting_flg_wallet == $settlement && 'deactivate' == $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[$this->acting_flg_wallet] );
			}
			if( 'on' == $options['acting_settings']['sbps']['mobile_docomo'] || 
				'on' == $options['acting_settings']['sbps']['mobile_auone'] || 
				'on' == $options['acting_settings']['sbps']['mobile_softbank2'] ) {
				$options['acting_settings']['sbps']['mobile_activate'] = 'on';
			} else {
				$options['acting_settings']['sbps']['mobile_activate'] = 'off';
			}
			if( 'on' == $options['acting_settings']['sbps']['mobile_activate'] ) {
				$usces->payment_structure[$this->acting_flg_mobile] = 'キャリア決済（SBPS）';
				foreach( $payment_method as $settlement => $payment ) {
					if( $this->acting_flg_mobile == $settlement && 'deactivate' == $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[$this->acting_flg_mobile] );
			}
			if( ( 'on' == $options['acting_settings']['sbps']['card_activate'] || 'token' == $options['acting_settings']['sbps']['card_activate'] ) || 
				'on' == $options['acting_settings']['sbps']['conv_activate'] || 
				'on' == $options['acting_settings']['sbps']['payeasy_activate'] || 
				'on' == $options['acting_settings']['sbps']['wallet_activate'] || 
				'on' == $options['acting_settings']['sbps']['mobile_activate'] ) {
				$options['acting_settings']['sbps']['activate'] = 'on';
				$options['acting_settings']['sbps']['send_url'] = 'https://fep.sps-system.com/f01/FepBuyInfoReceive.do';
				$options['acting_settings']['sbps']['send_url_check'] = 'https://stbfep.sps-system.com/Extra/BuyRequestAction.do';
				$options['acting_settings']['sbps']['send_url_test'] = 'https://stbfep.sps-system.com/f01/FepBuyInfoReceive.do';
				$options['acting_settings']['sbps']['token_url'] = 'https://token.sps-system.com/sbpstoken/com_sbps_system_token.js';
				$options['acting_settings']['sbps']['token_url_test'] = 'https://stbtoken.sps-system.com/sbpstoken/com_sbps_system_token.js';
				$options['acting_settings']['sbps']['api_url'] = 'https://fep.sps-system.com/api/xmlapi.do';
				$options['acting_settings']['sbps']['api_url_test'] = 'https://stbfep.sps-system.com/api/xmlapi.do';
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['sbps']['activate'] = 'off';
				unset( $usces->payment_structure[$this->acting_flg_card] );
				unset( $usces->payment_structure[$this->acting_flg_conv] );
				unset( $usces->payment_structure[$this->acting_flg_payeasy] );
				unset( $usces->payment_structure[$this->acting_flg_wallet] );
				unset( $usces->payment_structure[$this->acting_flg_mobile] );
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
			$options['acting_settings']['sbps']['activate'] = 'off';
			unset( $usces->payment_structure[$this->acting_flg_card] );
			unset( $usces->payment_structure[$this->acting_flg_conv] );
			unset( $usces->payment_structure[$this->acting_flg_payeasy] );
			unset( $usces->payment_structure[$this->acting_flg_wallet] );
			unset( $usces->payment_structure[$this->acting_flg_mobile] );
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
	 * クレジット決済設定画面フォーム
	 * @fook   usces_action_settlement_tab_body
	 * @param  -
	 * @return -
	 * @echo   html
	 */
	public function settlement_tab_body() {

		$acting_opts = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if( in_array( 'sbps', (array)$settlement_selected ) ):
?>
	<div id="uscestabs_sbps">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'sbps' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message"><?php _e( 'Test thoroughly before use.', 'usces' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="sbps_form" id="sbps_form">
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_merchant_id_sbps">マーチャントID</a></th>
				<td><input name="merchant_id" type="text" id="merchant_id_sbps" value="<?php echo esc_html( isset( $acting_opts['merchant_id'] ) ? $acting_opts['merchant_id'] : '' ); ?>" class="regular-text" maxlength="5" /></td>
			</tr>
			<tr id="ex_merchant_id_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるマーチャントID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_service_id_sbps">サービスID</a></th>
				<td><input name="service_id" type="text" id="service_id_sbps" value="<?php echo esc_html( isset( $acting_opts['service_id'] ) ? $acting_opts['service_id'] : '' ); ?>" class="regular-text" maxlength="3" /></td>
			</tr>
			<tr id="ex_service_id_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるサービスID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_hash_key_sbps">ハッシュキー</a></th>
				<td><input name="hash_key" type="text" id="hash_key_sbps" value="<?php echo esc_html( isset( $acting_opts['hash_key'] ) ? $acting_opts['hash_key'] : '' ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_hash_key_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるハッシュキー（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_ope_sbps"><?php _e( 'Operation Environment', 'usces' ); ?></a></th>
				<td><label><input name="ope" type="radio" id="ope_sbps_1" value="check"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'check' ) echo ' checked="checked"'; ?> /><span>接続支援サイト</span></label><br />
					<label><input name="ope" type="radio" id="ope_sbps_2" value="test"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="ope" type="radio" id="ope_sbps_3" value="public"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_ope_sbps" class="explanation"><td colspan="2"><?php _e( 'Switch the operating environment.', 'usces' ); ?></td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use with external link type', 'usces' ); ?></span></label><br />
					<label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_2" value="token"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'token' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use with non-passage type', 'usces' ); ?></span></label><br />
					<label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_0" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr class="card_link_sbps">
				<th>3Dセキュア</th>
				<td><label><input name="3d_secure" type="radio" id="3d_secure_sbps_1" value="on"<?php if( isset( $acting_opts['3d_secure'] ) && $acting_opts['3d_secure'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="3d_secure" type="radio" id="3d_secure_sbps_2" value="off"<?php if( isset( $acting_opts['3d_secure'] ) && $acting_opts['3d_secure'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_cust_manage_sbps">クレジットカード情報保存</a></th>
				<td><label><input name="cust_manage" type="radio" id="cust_manage_sbps_1" value="on"<?php if( $acting_opts['cust_manage'] == 'on' ) echo ' checked="checked"'; ?> /><span>保存する</span></label><br />
					<label><input name="cust_manage" type="radio" id="cust_manage_sbps_2" value="choice"<?php if( $acting_opts['cust_manage'] == 'choice' ) echo ' checked="checked"'; ?> /><span>会員が選択して保存する</span></label><br />
					<label><input name="cust_manage" type="radio" id="cust_manage_sbps_0" value="off"<?php if( $acting_opts['cust_manage'] == 'off' ) echo ' checked="checked"'; ?> /><span>保存しない</span></label>
				</td>
			</tr>
			<tr id="ex_cust_manage_sbps" class="explanation card_token_sbps"><td colspan="2">クレジットカード情報お預かりサービスを利用して、会員のカード情報をSBペイメントサービスに保存します。</td></tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_sales_sbps">売上方式</a></th>
				<td><label><input name="sales" type="radio" id="sales_sbps_manual" value="manual"<?php if( $acting_opts['sales'] == 'manual' ) echo ' checked="checked"'; ?> /><span>指定売上（仮売上）</span></label><br />
					<label><input name="sales" type="radio" id="sales_sbps_auto" value="auto"<?php if( $acting_opts['sales'] == 'auto' ) echo ' checked="checked"'; ?> /><span>自動売上（実売上）</span></label>
				</td>
			</tr>
			<tr id="ex_sales_sbps" class="explanation card_token_sbps"><td colspan="2">指定売上の場合は、決済時には与信のみ行い、SBPS決済管理ツールにて手動で売上処理を行います。自動売上の場合は、決済時に即時売上計上されます。</td></tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_3des_key_sbps">3DES<br />暗号化キー</a></th>
				<td><input name="3des_key" type="text" id="3des_key_sbps" value="<?php echo esc_html( isset( $acting_opts['3des_key'] ) ? $acting_opts['3des_key'] : '' ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_3des_key_sbps" class="explanation card_token_sbps"><td colspan="2">契約時にSBペイメントサービスから発行される 3DES 暗号化キー（半角英数）</td></tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_3desinit_key_sbps">3DES<br />初期化キー</a></th>
				<td><input name="3desinit_key" type="text" id="3desinit_key_sbps" value="<?php echo esc_html( isset( $acting_opts['3desinit_key'] ) ? $acting_opts['3desinit_key'] : '' ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_3desinit_key_sbps" class="explanation card_token_sbps"><td colspan="2">契約時にSBペイメントサービスから発行される 3DES 初期化キー（半角英数）</td></tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_basic_id_sbps">Basic認証ID</a></th>
				<td><input name="basic_id" type="text" id="basic_id_sbps" value="<?php echo esc_html( isset( $acting_opts['basic_id'] ) ? $acting_opts['basic_id'] : '' ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_basic_id_sbps" class="explanation card_token_sbps"><td colspan="2">契約時にSBペイメントサービスから発行される Basic認証ID（半角数字）</td></tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_basic_password_sbps">Basic認証Password</a></th>
				<td><input name="basic_password" type="text" id="basic_password_sbps" value="<?php echo esc_html( isset( $acting_opts['basic_password'] ) ? $acting_opts['basic_password'] : '' ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_basic_password_sbps" class="explanation card_token_sbps"><td colspan="2">契約時にSBペイメントサービスから発行される Basic認証 Password（半角英数）</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ決済</th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_sbps_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_sbps_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>Pay-easy（ペイジー）決済</th>
				<td><label><input name="payeasy_activate" type="radio" id="payeasy_activate_sbps_1" value="on"<?php if( isset( $acting_opts['payeasy_activate'] ) && $acting_opts['payeasy_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="payeasy_activate" type="radio" id="payeasy_activate_sbps_2" value="off"<?php if( isset( $acting_opts['payeasy_activate'] ) && $acting_opts['payeasy_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>Yahoo! ウォレット決済</th>
				<td><label><input name="wallet_yahoowallet" type="radio" id="wallet_yahoowallet_sbps_1" value="on"<?php if( isset( $acting_opts['wallet_yahoowallet'] ) && $acting_opts['wallet_yahoowallet'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_yahoowallet" type="radio" id="wallet_yahoowallet_sbps_2" value="off"<?php if( isset( $acting_opts['wallet_yahoowallet'] ) && $acting_opts['wallet_yahoowallet'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>楽天ペイ（オンライン決済）</th>
				<td><label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_1" value="on"<?php if( isset( $acting_opts['wallet_rakuten'] ) && $acting_opts['wallet_rakuten'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_2" value="off"<?php if( isset( $acting_opts['wallet_rakuten'] ) && $acting_opts['wallet_rakuten'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>PayPal 決済</th>
				<td><label><input name="wallet_paypal" type="radio" id="wallet_paypal_sbps_1" value="on"<?php if( isset( $acting_opts['wallet_paypal'] ) && $acting_opts['wallet_paypal'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_paypal" type="radio" id="wallet_paypal_sbps_2" value="off"<?php if( isset( $acting_opts['wallet_paypal'] ) && $acting_opts['wallet_paypal'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>Alipay 国際決済</th>
				<td><label><input name="wallet_alipay" type="radio" id="wallet_alipay_sbps_1" value="on"<?php if( isset( $acting_opts['wallet_alipay'] ) && $acting_opts['wallet_alipay'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_alipay" type="radio" id="wallet_alipay_sbps_2" value="off"<?php if( isset( $acting_opts['wallet_alipay'] ) && $acting_opts['wallet_alipay'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>ドコモ払い</th>
				<td><label><input name="mobile_docomo" type="radio" id="mobile_docomo_sbps_1" value="on"<?php if( isset( $acting_opts['mobile_docomo'] ) && $acting_opts['mobile_docomo'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_docomo" type="radio" id="mobile_docomo_sbps_2" value="off"<?php if( isset( $acting_opts['mobile_docomo'] ) && $acting_opts['mobile_docomo'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>au かんたん決済</th>
				<td><label><input name="mobile_auone" type="radio" id="mobile_auone_sbps_1" value="on"<?php if( isset( $acting_opts['mobile_auone'] ) && $acting_opts['mobile_auone'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_auone" type="radio" id="mobile_auone_sbps_2" value="off"<?php if( isset( $acting_opts['mobile_auone'] ) && $acting_opts['mobile_auone'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>ソフトバンク<br />まとめて支払い</th>
				<td><label><input name="mobile_softbank2" type="radio" id="mobile_softbank2_sbps_1" value="on"<?php if( isset( $acting_opts['mobile_softbank2'] ) && $acting_opts['mobile_softbank2'] == 'on' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_softbank2" type="radio" id="mobile_softbank2_sbps_2" value="off"<?php if( isset( $acting_opts['mobile_softbank2'] ) && $acting_opts['mobile_softbank2'] == 'off' ) echo ' checked="checked"'; ?> /><span><?php _e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<input name="acting" type="hidden" value="sbps" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="SBペイメントサービスの設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong>SBペイメントサービス</strong></p>
		<a href="https://www.welcart.com/wc-settlement/sbps_guide/" target="_blank">SBペイメントサービスの詳細はこちら 》</a>
		<p></p>
		<p>クレジットカード決済では、「非通過型（トークン決済方式）」と「外部リンク型」が選択できます。</p>
		<p>「非通過型」は、決済会社のページへは遷移せず、Welcart のページのみで決済まで完結します。デザインの統一性が保て、スムーズなチェックアウトが可能です。ただし、カード番号を扱いますので専用SSLが必須となります。入力されたカード番号はSBペイメントサービスのシステムに送信されますので、Welcart に保存することはありません。<br />
		「外部リンク型」は、決済会社のページへ遷移してカード情報を入力します。<br />
		クレジットカード決済以外の決済サービスでは、全て「外部リンク型」になります。</p>
		<p>尚、本番環境では、正規SSL証明書のみでのSSL通信となりますのでご注意ください。</p>
	</div>
	</div><!-- uscestabs_sbps -->
<?php
		endif;
	}
}
