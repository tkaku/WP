<?php
/**
 * ルミーズ
 *
 * Version: 1.0.0
 * Author: Collne Inc.
 */

class REMISE_SETTLEMENT
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

	public function __construct() {

		$this->paymod_id = 'remise';
		$this->pay_method = array(
			'acting_remise_card',
			'acting_remise_conv'
		);
		$this->acting_name = 'ルミーズ';
		$this->acting_formal_name = __( 'Remise Japanese Settlement', 'usces' );
		$this->acting_company_url = 'http://www.remise.jp/';

		$this->initialize_data();

		if( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
		}

		if( $this->is_validity_acting( 'card' ) ) {
			add_filter( 'usces_filter_template_redirect', array( $this, 'member_update_settlement' ), 1 );
			add_action( 'usces_action_member_submenu_list', array( $this, 'e_update_settlement' ) );
			add_filter( 'usces_filter_member_submenu_list', array( $this, 'update_settlement' ), 10, 2 );
			if( is_admin() ) {
				add_action( 'usces_action_admin_member_info', array( $this, 'member_settlement_info' ), 10, 3 );
				add_action( 'usces_action_post_update_memberdata', array( $this, 'member_edit_post' ), 10, 2 );
			}
		}

		if( $this->is_validity_acting( 'conv' ) ) {
			add_action( 'usces_filter_completion_settlement_message', array( $this, 'completion_settlement_message' ), 10, 2 );
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['remise'] ) ) {
			$options['acting_settings']['remise']['SHOPCO'] = '';
			$options['acting_settings']['remise']['HOSTID'] = '';
			$options['acting_settings']['remise']['card_activate'] = 'off';
			$options['acting_settings']['remise']['card_jb'] = '';
			$options['acting_settings']['remise']['card_pc_ope'] = '';
			$options['acting_settings']['remise']['payquick'] = '';
			$options['acting_settings']['remise']['howpay'] = '';
			$options['acting_settings']['remise']['continuation'] = '';
			$options['acting_settings']['remise']['conv_activate'] = 'off';
			$options['acting_settings']['remise']['conv_pc_ope'] = '';
			$options['acting_settings']['remise']['S_PAYDATE'] = '';
			$options['acting_settings']['remise']['send_url_mbl'] = '';
			$options['acting_settings']['remise']['send_url_pc'] = '';
			$options['acting_settings']['remise']['send_url_cvs_mbl'] = '';
			$options['acting_settings']['remise']['send_url_cvs_pc'] = '';
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
				if( 'acting_remise_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
				if( 'acting_remise_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
	 * コンビニ・電子マネー決済有効判定
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
			if( in_array( 'paypal_wpp', (array)$settlement_selected ) ):
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	if( 'on' == $( "input[name='card_activate']:checked" ).val() ) {
		$( ".card_form_remise" ).css( "display", "" );
	} else {
		$( ".card_form_remise" ).css( "display", "none" );
	}
	$( "input[name='card_activate']" ).click( function() {
		if( 'on' == $( "input[name='card_activate']:checked" ).val() ) {
			$( ".card_form_remise" ).css( "display", "" );
		} else {
			$( ".card_form_remise" ).css( "display", "none" );
		}
	});
	if( 'on' == $( "input[name='conv_activate']:checked" ).val() ) {
		$( ".conv_form_remise" ).css( "display", "" );
	} else {
		$( ".conv_form_remise" ).css( "display", "none" );
	}
	$( "input[name='conv_activate']" ).click( function() {
		if( 'on' == $( "input[name='conv_activate']:checked" ).val() ) {
			$( ".conv_form_remise" ).css( "display", "" );
		} else {
			$( ".conv_form_remise" ).css( "display", "none" );
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

		if( 'remise' != $_POST['acting'] ) {
			return;
		}

		$this->error_mes = '';
		$options = get_option( 'usces' );
		$payment_method = usces_get_system_option( 'usces_payment_method', 'settlement' );

		unset( $options['acting_settings']['remise'] );
		$options['acting_settings']['remise']['plan'] = ( isset( $_POST['plan'] ) ) ? $_POST['plan'] : '';
		$options['acting_settings']['remise']['SHOPCO'] = ( isset( $_POST['SHOPCO'] ) ) ? $_POST['SHOPCO'] : '';
		$options['acting_settings']['remise']['HOSTID'] = ( isset( $_POST['HOSTID'] ) ) ? $_POST['HOSTID'] : '';
		$options['acting_settings']['remise']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['remise']['card_jb'] = ( isset( $_POST['card_jb'] ) ) ? $_POST['card_jb'] : '';
		$options['acting_settings']['remise']['card_pc_ope'] = ( isset( $_POST['card_pc_ope'] ) ) ? $_POST['card_pc_ope'] : '';
		$options['acting_settings']['remise']['payquick'] = ( isset( $_POST['payquick'] ) ) ? $_POST['payquick'] : '';
		$options['acting_settings']['remise']['howpay'] = ( isset( $_POST['howpay'] ) ) ? $_POST['howpay'] : '';
		$options['acting_settings']['remise']['continuation'] = ( isset( $_POST['continuation'] ) ) ? $_POST['continuation'] : '';
		$options['acting_settings']['remise']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['remise']['conv_pc_ope'] = ( isset( $_POST['conv_pc_ope'] ) ) ? $_POST['conv_pc_ope'] : '';
		$options['acting_settings']['remise']['S_PAYDATE'] = ( isset( $_POST['S_PAYDATE'] ) ) ? $_POST['S_PAYDATE'] : '';
		$options['acting_settings']['remise']['send_url_mbl'] = ( isset( $_POST['send_url_mbl'] ) ) ? $_POST['send_url_mbl'] : '';
		$options['acting_settings']['remise']['send_url_pc'] = ( isset( $_POST['send_url_pc'] ) ) ? $_POST['send_url_pc'] : '';
		$options['acting_settings']['remise']['send_url_cvs_mbl'] = ( isset( $_POST['send_url_cvs_mbl'] ) ) ? $_POST['send_url_cvs_mbl'] : '';
		$options['acting_settings']['remise']['send_url_cvs_pc'] = ( isset( $_POST['send_url_cvs_pc'] ) ) ? $_POST['send_url_cvs_pc'] : '';

		if( 'on' == $options['acting_settings']['remise']['card_activate'] || 'on' == $options['acting_settings']['remise']['conv_activate'] ) {
			//if( isset( $_POST['plan_remise'] ) && WCUtils::is_zero( $_POST['plan_remise'] ) ) {
			//	$this->error_mes .= '※サービスプランを選択してください<br />';
			//}
			if( WCUtils::is_blank( $_POST['SHOPCO'] ) ) {
				$this->error_mes .= '※加盟店コードを入力してください<br />';
			}
			if( WCUtils::is_blank( $_POST['HOSTID'] ) ) {
				$this->error_mes .= '※ホスト番号を入力してください<br />';
			}
			if( 'on' == $options['acting_settings']['remise']['card_activate'] ) {
				if( 'public' == $options['acting_settings']['remise']['card_pc_ope'] && WCUtils::is_blank( $_POST['send_url_pc'] ) ) {
					$this->error_mes .= '※クレジットカード決済の本番URLを入力してください<br />';
				}
			}
			if( 'on' == $options['acting_settings']['remise']['conv_activate'] ) {
				if( WCUtils::is_blank( $_POST['S_PAYDATE'] ) ) {
					$this->error_mes .= '※支払期限を入力してください<br />';
				}
				if( 'public' == $options['acting_settings']['remise']['conv_pc_ope'] && WCUtils::is_blank( $_POST['send_url_cvs_pc'] ) ) {
					$this->error_mes .= '※コンビニ・電子マネー決済の本番URLを入力してください<br />';
				}
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['remise']['card_activate'] || 'on' == $options['acting_settings']['remise']['conv_activate'] ) {
				$options['acting_settings']['remise']['activate'] = 'on';
				$options['acting_settings']['remise']['REMARKS3'] = 'A0000875';
				$toactive = array();
				if( 'on' == $options['acting_settings']['remise']['card_activate'] ) {
					if( 'test' == $options['acting_settings']['remise']['card_pc_ope'] ) {
						$options['acting_settings']['remise']['send_url_pc_test'] = 'https://test.remise.jp/rpgw2/pc/card/paycard.aspx';
						$options['acting_settings']['remise']['send_url_mbl_test'] = 'https://test.remise.jp/rpgw2/mbl/card/paycard.aspx';
					}
					$usces->payment_structure['acting_remise_card'] = 'カード決済（ルミーズ）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_remise_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_remise_card'] );
				}
				if( 'on' == $options['acting_settings']['remise']['conv_activate'] ) {
					if( 'test' == $options['acting_settings']['remise']['conv_pc_ope'] ) {
						$options['acting_settings']['remise']['send_url_cvs_pc_test'] = 'https://test.remise.jp/rpgw2/pc/cvs/paycvs.aspx';
						$options['acting_settings']['remise']['send_url_cvs_mbl_test'] = 'https://test.remise.jp/rpgw2/mbl/cvs/paycvs.aspx';
					}
					$usces->payment_structure['acting_remise_conv'] = 'コンビニ決済（ルミーズ）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_remise_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_remise_conv'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['paypal']['activate'] = 'off';
				unset( $usces->payment_structure['acting_remise_card'] );
				unset( $usces->payment_structure['acting_remise_conv'] );
			}
			if( 'on' != $options['acting_settings']['remise']['card_activate'] || 'on' != $options['acting_settings']['remise']['payquick'] || 'off' == $options['acting_settings']['paypal']['activate'] ) {
				usces_clear_quickcharge( 'remise_pcid' );
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
			$options['acting_settings']['remise']['activate'] = 'off' ;
			unset( $usces->payment_structure['acting_remise_card'] );
			unset( $usces->payment_structure['acting_remise_conv'] );
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
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ) {
			echo '<li><a href="#uscestabs_'.$this->paymod_id.'">'.$this->acting_name.'</a></li>';
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
		if( in_array( $this->paymod_id, (array)$settlement_selected ) ):
?>
	<div id="uscestabs_remise">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'remise' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message"><?php _e( 'Test thoroughly before use.', 'usces' ); ?></div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="remise_form" id="remise_form">
		<table class="settle_table">
			<!--<tr>
				<th><a class="explanation-label" id="label_ex_plan_remise">契約プラン</a></th>
				<td>
					<select name="plan" id="plan_remise">
						<option value="0"<?php echo( ( isset( $acting_opts['plan'] ) && '0' === $acting_opts['plan'] ) ? ' selected="selected"' : '' ); ?>>-------------------------</option>
						<option value="1"<?php echo( ( isset( $acting_opts['plan'] ) && '1' === $acting_opts['plan'] ) ? ' selected="selected"' : '' ); ?>>スーパーバリュープラン</option>
						<option value="2"<?php echo( ( isset( $acting_opts['plan'] ) && '2' === $acting_opts['plan'] ) ? ' selected="selected"' : '' ); ?>>ライトプラン</option>
					</select>
				</td>
			</tr>
			<tr id="ex_plan_remise" class="explanation"><td colspan="2"><?php echo $this->acting_name; ?>と契約したサービスプランを選択してください。<br />契約が変更したい場合は<?php echo $this->acting_name; ?>へお問合せください。</td></tr>-->
			<tr>
				<th><a class="explanation-label" id="label_ex_SHOPCO_remise">加盟店コード</a></th>
				<td><input name="SHOPCO" type="text" id="SHOPCO_remise" value="<?php echo esc_html( isset( $acting_opts['SHOPCO'] ) ? $acting_opts['SHOPCO'] : '' ); ?>" class="regular-text" maxlength="8" /></td>
			</tr>
			<tr id="ex_SHOPCO_remise" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行される加盟店コード（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_HOSTID_remise">ホスト番号</a></th>
				<td><input name="HOSTID" type="text" id="HOSTID_remise" value="<?php echo esc_html( isset( $acting_opts['HOSTID'] ) ? $acting_opts['HOSTID'] : '' ); ?>" class="regular-text" maxlength="8" /></td>
			</tr>
			<tr id="ex_HOSTID_remise" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から割り当てられるホスト番号（半角数字）</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_remise_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_remise_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_card_jb_remise">ジョブコード</a></th>
				<td><!--<label><input name="card_jb" type="radio" id="card_jb_remise_1" value="CHECK"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'CHECK' ) echo ' checked'; ?> /><span>有効性チェック</span></label><br />-->
					<label><input name="card_jb" type="radio" id="card_jb_remise_2" value="AUTH"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'AUTH' ) echo ' checked'; ?> /><span>仮売上処理</span></label><br />
					<label><input name="card_jb" type="radio" id="card_jb_remise_3" value="CAPTURE"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'CAPTURE' ) echo ' checked'; ?> /><span>売上処理</span></label>
				</td>
			</tr>
			<tr id="ex_card_jb_remise" class="explanation card_form_remise"><td colspan="2">決済の種類を指定します。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_payquick_remise">ペイクイック機能</a></th>
				<td><label><input name="payquick" type="radio" id="payquick_remise_1" value="on"<?php if( isset( $acting_opts['payquick'] ) && $acting_opts['payquick'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="payquick" type="radio" id="payquick_remise_2" value="off"<?php if( isset( $acting_opts['payquick'] ) && $acting_opts['payquick'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_payquick_remise" class="explanation card_form_remise"><td colspan="2">Welcart の会員システムを利用している場合、会員に対して2回目以降の決済の際、クレジットカード番号、有効期限、名義人の入力が不要となります。<br />クレジットカード情報はWelcart では保存せず、<?php echo $this->acting_name; ?>のデータベースにて安全に保管されます。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_howpay_remise">お客様の支払方法</a></th>
				<td><label><input name="howpay" type="radio" id="howpay_remise_1" value="on"<?php if( isset( $acting_opts['howpay'] ) && $acting_opts['howpay'] == 'on' ) echo ' checked="checked"'; ?> /><span>分割払いに対応する</span></label><br />
					<label><input name="howpay" type="radio" id="howpay_remise_2" value="off"<?php if( isset( $acting_opts['howpay'] ) && $acting_opts['howpay'] == 'off' ) echo ' checked="checked"'; ?> /><span>一括払いのみ</span></label>
				</td>
			</tr>
			<tr id="ex_howpay_remise" class="explanation card_form_remise"><td colspan="2">「一括払い」以外をご利用の場合は<?php echo $this->acting_name; ?>側の設定が必要となります。前もって<?php echo $this->acting_name; ?>にお問合せください。<br >「スーパーバリュープラン」の場合は「一括払いのみ」を選択してください。</td></tr>
			<?php if( defined( 'WCEX_DLSELLER' ) ): ?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_continuation_remise">自動継続課金</a></th>
				<td><label><input name="continuation" type="radio" id="continuation_remise_1" value="on"<?php if( isset( $acting_opts['continuation'] ) && $acting_opts['continuation'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="continuation" type="radio" id="continuation_remise_2" value="off"<?php if( isset( $acting_opts['continuation'] ) && $acting_opts['continuation'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_continuation_remise" class="explanation card_form_remise"><td colspan="2">定期的に発生する月会費などの煩わしい課金処理を完全に自動化することができる機能です。<br />詳しくは<?php echo $this->acting_name; ?>にお問合せください。</td></tr>
			<?php endif; ?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_card_pc_ope_remise">稼働環境</a></th>
				<td><label><input name="card_pc_ope" type="radio" id="card_pc_ope_remise_1" value="test"<?php if( isset( $acting_opts['card_pc_ope'] ) && $acting_opts['card_pc_ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="card_pc_ope" type="radio" id="card_pc_ope_remise_2" value="public"<?php if( isset( $acting_opts['card_pc_ope'] ) && $acting_opts['card_pc_ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_card_pc_ope_remise" class="explanation card_form_remise"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_pc_remise">本番URL(PC)</a></th>
				<td><input name="send_url_pc" type="text" id="send_url_pc_remise" value="<?php echo esc_html( isset( $acting_opts['send_url_pc'] ) ? $acting_opts['send_url_pc'] : '' ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_pc_remise" class="explanation card_form_remise"><td colspan="2">クレジットカード決済の本番環境(PC)で接続するURLを設定します。</td></tr>
			<?php if( defined( 'WCEX_MOBILE' ) ): ?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_mbl_remise">本番URL(携帯)</a></th>
				<td><input name="send_url_mbl" type="text" id="send_url_mbl_remise" value="<?php echo esc_html( isset( $acting_opts['send_url_mbl'] ) ? $acting_opts['send_url_mbl'] : '' ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_mbl_remise" class="explanation card_form_remise"><td colspan="2">クレジットカード決済の本番環境(携帯)で接続するURLを設定します。</td></tr>
			<?php endif; ?>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ・電子マネー決済</a></th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_remise_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_remise_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_paydate_remise">支払期限</a></th>
				<td><input name="S_PAYDATE" type="text" id="S_PAYDATE_remise" value="<?php echo esc_html( isset( $acting_opts['S_PAYDATE'] ) ? $acting_opts['S_PAYDATE'] : '' ); ?>" class="small-text" maxlength="3" />日</td>
			</tr>
			<tr id="ex_paydate_remise" class="explanation conv_form_remise"><td colspan="2">日数を設定します。（半角数字）</td></tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_conv_pc_ope_remise">稼働環境</a></th>
				<td><label><input name="conv_pc_ope" type="radio" id="conv_pc_ope_remise_1" value="test"<?php if( isset( $acting_opts['conv_pc_ope'] ) && $acting_opts['conv_pc_ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="conv_pc_ope" type="radio" id="conv_pc_ope_remise_2" value="public"<?php if( isset( $acting_opts['conv_pc_ope'] ) && $acting_opts['conv_pc_ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_conv_pc_ope_remise" class="explanation conv_form_remise"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_cvs_pc_remise">本番URL(PC)</a></th>
				<td><input name="send_url_cvs_pc" type="text" id="send_url_cvs_pc_remise" value="<?php echo esc_html( isset( $acting_opts['send_url_cvs_pc'] ) ? $acting_opts['send_url_cvs_pc'] : '' ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_cvs_pc_remise" class="explanation conv_form_remise"><td colspan="2">コンビニ・電子マネー決済の本番環境(PC)で接続するURLを設定します。</td></tr>
			<?php if( defined( 'WCEX_MOBILE' ) ): ?>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_cvs_mbl_remise">本番URL(携帯)</a></th>
				<td><input name="send_url_cvs_mbl" type="text" id="send_url_cvs_mbl_remise" value="<?php echo esc_html( isset( $acting_opts['send_url_cvs_mbl'] ) ? $acting_opts['send_url_cvs_mbl'] : '' ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_cvs_mbl_remise" class="explanation conv_form_remise"><td colspan="2">コンビニ・電子マネー決済の本番環境(携帯)で接続するURLを設定します。</td></tr>
			<?php endif; ?>
		</table>
		<input name="acting" type="hidden" value="remise" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?>の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php _e( 'Remise Japanese Settlement', 'usces' ); ?></strong></p>
		<a href="<?php echo $this->acting_company_url; ?>" target="_blank"><?php echo $this->acting_name; ?>の詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへは遷移してカード情報を入力する決済システムです。</p>
		<p>「自動継続課金」を利用するには「WCEX DL Seller」拡張プラグインのインストールが必要です。</p>
	</div>
	</div><!--uscestabs_remise-->
<?php
		endif;
	}

	/**
	 * 受注データ登録
	 * Call from usces_reg_orderdata() and usces_new_orderdata().
	 * @fook   usces_action_reg_orderdata
	 * @param  @array $cart, $entry, $order_id, $member_id, $payments, $charging_type, $results
	 * @return -
	 * @echo   -
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

		if( isset( $_REQUEST['X-S_TORIHIKI_NO'] ) ) {
			$usces->set_order_meta_value( 'settlement_id', $_REQUEST['X-S_TORIHIKI_NO'], $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $_REQUEST['X-S_TORIHIKI_NO'], $order_id );
			if( isset( $_REQUEST['X-AC_MEMBERID'] ) ) {
				$usces->set_order_meta_value( $_REQUEST['X-AC_MEMBERID'], 'continuation', $order_id );
				$usces->set_member_meta_value( 'continue_memberid_'.$order_id, $_REQUEST['X-AC_MEMBERID'] );
			}
		}
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
			if( 'on' != $acting_opts['payquick'] ) {
				return;
			}

			if( isset( $_REQUEST['page'] ) && 'member_update_settlement' == $_REQUEST['page'] ) {
				$usces->page = 'member_update_settlement';
				$this->member_update_settlement_form();
				exit();
			}
		}
		return false;
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
		if( 'on' == $acting_opts['payquick'] ) {
			$member = $usces->get_member();
			$pcid = $usces->get_member_meta_value( 'remise_pcid', $member['ID'] );
			if( !empty( $pcid ) ) {
				$update_settlement_url = add_query_arg( array( 'page' => 'member_update_settlement', 're-enter' => 1 ), USCES_MEMBER_URL );
				$html .= '
				<div class="gotoedit">
				<a href="'.$update_settlement_url.'">'.__( "Change the credit card is here >>", 'usces' ).'</a>
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

		$member = $usces->get_member();
		$member_id = $member['ID'];
		$update_settlement_url = add_query_arg( array( 'page' => 'member_update_settlement', 'settlement' => 1, 're-enter' => 1 ), USCES_MEMBER_URL );
		$acting_opts = $this->get_acting_settings();
		$send_url = ( 'public' == $acting_opts['card_pc_ope'] ) ? $acting_opts['send_url_pc'] : $acting_opts['send_url_pc_test'];
		$rand = '0000000'.$member_id;
		$partofcard = $usces->get_member_meta_value( 'partofcard', $member_id );
		$limitofcard = $usces->get_member_meta_value( 'limitofcard', $member_id );
		$error_message = apply_filters( 'usces_filter_member_update_settlement_error_message', $usces->error_message );

		ob_start();
		get_header();
?>
<div id="content" class="two-column">
<div class="catbox">
<?php if( have_posts() ): usces_remove_filter(); ?>
<div class="post" id="wc_<?php usces_page_name(); ?>">
<h1 class="member_page_title"><?php _e( 'Credit card update', 'usces' ); ?></h1>
<div class="entry">
<div id="memberpages">
<div class="whitebox">
	<div id="memberinfo">
	<div class="header_explanation">
<?php do_action( 'usces_action_member_update_settlement_page_header' ); ?>
	</div>
	<div class="error_message"><?php echo $error_message; ?></div>
	<div><?php echo __( 'Since the transition to the page of the settlement company by clicking the "Update", please fill out the information for the new card.<br />In addition, this process is intended to update the card information such as credit card expiration date, it is not in your contract renewal of service.<br />To check the current contract, please refer to the member page.', 'dlseller' ); ?><br /><br /></div>
<?php if( !empty( $partofcard ) && !empty( $limitofcard ) ): ?>
	<table>
		<tbody>
		<tr>
			<th scope="row"><?php _e( 'The last four digits of your card number', 'usces' ); ?></th><td><?php echo esc_html( $partofcard ); ?></div></td>
			<th scope="row"><?php _e( 'Expiration date', 'usces' ); ?></th><td><?php echo esc_html( $limitofcard ); ?></td>
		</tr>
		</tbody>
	</table>
<?php endif; ?>
	<form id="member-card-info" action="<?php echo esc_attr( $send_url ); ?>" method="post" onKeyDown="if(event.keyCode == 13){return false;}" accept-charset="Shift_JIS">
		<input type="hidden" name="SHOPCO" value="<?php echo esc_attr( $acting_opts['SHOPCO'] ); ?>" />
		<input type="hidden" name="HOSTID" value="<?php echo esc_attr( $acting_opts['HOSTID'] ); ?>" />
		<input type="hidden" name="REMARKS3" value="<?php echo $acting_opts['REMARKS3']; ?>" />
		<input type="hidden" name="S_TORIHIKI_NO" value="<?php echo $rand; ?>" />
		<input type="hidden" name="JOB" value="CHECK" />
		<input type="hidden" name="MAIL" value="<?php echo esc_attr( $member['mailaddress1'] ); ?>" />
		<input type="hidden" name="ITEM" value="0000990" />
		<input type="hidden" name="RETURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="NG_RETURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="EXITURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="OPT" value="welcart_card_update" />
		<input type="hidden" name="PAYQUICK" value="1" />
		<input type="hidden" name="dummy" value="&#65533;" />
		<div class="send">
			<input type="submit" name="purchase" class="checkout_button" value="<?php echo __( 'Update', 'dlseller' ); ?>" onclick="document.charset='Shift_JIS';" />
			<input type="button" name="back" value="<?php _e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo USCES_MEMBER_URL; ?>'" />
			<input type="button" name="top" value="<?php _e( 'Back to the top page.', 'usces' ); ?>" onclick="location.href='<?php echo home_url(); ?>'" />
		</div>
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
<?php
		$sidebar = apply_filters( 'usces_filter_member_update_settlement_page_sidebar', 'cartmember' );
		if( !empty( $sidebar ) ) get_sidebar( $sidebar );

		get_footer();
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;
	}

	/**
	 * 会員データ編集画面 ペイクイック登録情報
	 * @fook   usces_action_admin_member_info
	 * @param  $member_data $member_meta_data $member_history
	 * @return -
	 * @echo   -
	 */
	public function member_settlement_info( $member_data, $member_meta_data, $member_history ) {

		if( 0 < count( $member_meta_data ) ):
			$cardinfo = array();
			foreach( $member_meta_data as $value ) {
				if( in_array( $value['meta_key'], array( 'remise_pcid', 'partofcard', 'limitofcard', 'remise_memid' ) ) ) {
					$cardinfo[$value['meta_key']] = $value['meta_value'];
				}
			}
			if( 0 < count( $cardinfo ) ):
				foreach( $cardinfo as $key => $value ):
					if( $key != 'remise_pcid' ) :
						if( $key == 'partofcard' ) $label = '下4桁';
						elseif( $key == 'limitofcard' ) $label = '有効期限';
						elseif( $key == 'remise_memid' ) $label = 'メンバーID';
						else $label = $key; ?>
			<tr>
				<td class="label"><?php echo esc_html( $label ); ?></td>
				<td><div class="rod_left shortm"><?php echo esc_html( $value ); ?></div></td>
			</tr>
<?php				endif;
				endforeach;
				if( array_key_exists( 'remise_pcid', $cardinfo ) ): ?>
			<tr>
				<td class="label">ペイクイック</td>
				<td><div class="rod_left shortm">登録あり</div></td>
			</tr>
			<tr>
				<td class="label"><input type="checkbox" name="remise_pcid" id="remise_pcid" value="delete"></td>
				<td><label for="remise_pcid">ペイクイックを解除する</label></td>
			</tr>
<?php			endif;
			endif;
		endif;
	}

	/**
	 * 会員データ編集画面 ペイクイック登録解除
	 * @fook   usces_action_post_update_memberdata
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function member_edit_post( $member_id, $res ) {
		global $usces;

		if( isset( $_POST['remise_pcid'] ) && $_POST['remise_pcid'] == 'delete' ) {
			$usces->del_member_meta( 'remise_pcid', $member_id );
		}
	}

	/**
	 * 購入完了メッセージ
	 * @fook   usces_filter_completion_settlement_message
	 * @param  $html, $usces_entries
	 * @return string $html
	 */
	public function completion_settlement_message( $html, $usces_entries ) {
		global $usces;

		if( isset( $_REQUEST['acting'] ) && 'remise_conv' == $_REQUEST['acting'] ) {
			$html .= '<div id="status_table"><h5>ルミーズ・コンビニ決済</h5>'."\n";
			$html .= '<table>'."\n";
			$html .= '<tr><th>ご請求番号</th><td>' . esc_html( $_REQUEST["X-S_TORIHIKI_NO"] ) . "</td></tr>\n";
			$html .= '<tr><th>ご請求合計金額</th><td>' . esc_html( $_REQUEST["X-TOTAL"] ) . "</td></tr>\n";
			$html .= '<tr><th>お支払期限</th><td>' . esc_html( substr( $_REQUEST["X-PAYDATE"], 0, 4 ).'年' . substr( $_REQUEST["X-PAYDATE"], 4, 2 ).'月' . substr( $_REQUEST["X-PAYDATE"], 6, 2 ).'日' ) . "(期限を過ぎますとお支払ができません)</td></tr>\n";
			$html .= '<tr><th>お支払先</th><td>' . esc_html( usces_get_conv_name( $_REQUEST["X-PAY_CSV"] ) ) . "</td></tr>\n";
			$html .= $this->get_remise_conv_return( $_REQUEST["X-PAY_CSV"] );
			$html .= '</table>'."\n";
			$html .= '<p>「お支払いのご案内」は、' . esc_html( $usces_entries['customer']['mailaddress1'] ) . '　宛にメールさせていただいております。</p>'."\n";
			$html .= "</div>\n";
		}
		return $html;
	}

	/**
	 * コンビニステータス
	 * @param  $code
	 * @return string $html
	 */
	protected function get_remise_conv_return( $code ) {
		switch( $code ) {
			case 'D001': //セブンイレブン
				$html = '<tr><th>払込番号</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				$html .= '<tr><th>払込票のURL</th><td><a href="'.esc_html( $_REQUEST["X-PAY_NO2"] ).'" target="_blank">'.esc_html( $_REQUEST["X-PAY_NO2"] )."</a></td></tr>\n";
				break;
			case 'D002': //ローソン
			case 'D015': //セイコーマート
			case 'D405': //ペイジー
				$html = '<tr><th>受付番号</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				$html .= '<tr><th>支払方法案内URL</th><td><a href="'.esc_html( $_REQUEST["X-PAY_NO2"] ).'" target="_blank">'.esc_html( $_REQUEST["X-PAY_NO2"] )."</a></td></tr>\n";
				break;
			case 'D003': //サンクス
			case 'D004': //サークルK
			case 'D005': //ミニストップ
			case 'D010': //デイリーヤマザキ
			case 'D011': //ヤマザキデイリーストア
				$html = '<tr><th>決済番号</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				$html .= '<tr><th>支払方法案内URL</th><td><a href="'.esc_html( $_REQUEST["X-PAY_NO2"] ).'" target="_blank">'.esc_html( $_REQUEST["X-PAY_NO2"] )."</a></td></tr>\n";
				break;
			case 'D030': //ファミリーマート
				$html = '<tr><th>コード</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				$html .= '<tr><th>注文番号</th><td>'.esc_html( $_REQUEST["X-PAY_NO2"] )."</td></tr>\n";
				break;
			case 'D401': //CyberEdy
			case 'D404': //楽天銀行
			case 'D406': //ジャパネット銀行
			case 'D407': //Suicaインターネットサービス
			case 'D451': //ウェブマネー
			case 'D452': //ビットキャッシュ
			case 'D453': //JCBプレモカード
				$html = '<tr><th>受付番号</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				$html .= '<tr><th>支払手続URL</th><td><a href="'.esc_html( $_REQUEST["X-PAY_NO2"] ).'" target="_blank">'.esc_html( $_REQUEST["X-PAY_NO2"] )."</a></td></tr>\n";
				break;
			case 'P901': //コンビニ払込票
			case 'P902': //コンビニ払込票（郵便振替対応）
				$html = '<tr><th>受付番号</th><td>' . esc_html( $_REQUEST["X-PAY_NO1"] ) . "</td></tr>\n";
				break;
			default:
				$html = '';
		}
		return $html;
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
	 * 契約中の自動継続課金情報
	 * @param  $member_id
	 * @return 
	 */
	protected function have_member_continue_order( $member_id ) {
		global $wpdb;

		$continue = 0;
		$continuation_table = $wpdb->prefix.'usces_continuation';
		$query = $wpdb->prepare( "SELECT * FROM {$continuation_table} WHERE `con_member_id` = %d AND `con_status` = 'continuation' ORDER BY `con_price` DESC", $member_id );
		$continue_order = $wpdb->get_results( $query, ARRAY_A );
		if( $continue_order && 0 < count( $continue_order ) ) {
			$continue = $continue_order[0]['con_order_id'];
		}
		return $continue;
	}
}
