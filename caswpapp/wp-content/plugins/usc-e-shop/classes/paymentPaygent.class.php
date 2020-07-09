<?php
/**
 * ペイジェント
 *
 * @class    PAYGENT_SETTLEMENT
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    1.9.20
 */
class PAYGENT_SETTLEMENT
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

		$this->paymod_id = 'paygent';
		$this->pay_method = array(
			'acting_paygent_card',
			'acting_paygent_conv',
		);
		$this->acting_name = 'ペイジェント';
		$this->acting_formal_name = 'ペイジェント';
		$this->acting_company_url = 'http://www.paygent.co.jp/';

		$this->initialize_data();

		if( is_admin() ) {
			//add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['paygent'] ) ) {
			$options['acting_settings']['paygent']['seq_merchant_id'] = '';
			$options['acting_settings']['paygent']['hc'] = '';
			$options['acting_settings']['paygent']['ope'] = '';
			$options['acting_settings']['paygent']['card_activate'] = 'off';
			$options['acting_settings']['paygent']['payment_class'] = '';
			$options['acting_settings']['paygent']['use_card_conf_number'] = '';
			$options['acting_settings']['paygent']['stock_card_mode'] = '';
			$options['acting_settings']['paygent']['threedsecure_ryaku'] = '';
			$options['acting_settings']['paygent']['conv_activate'] = 'off';
			$options['acting_settings']['paygent']['conv_hc'] = '';
			$options['acting_settings']['paygent']['payment_term_day'] = '';
			$options['acting_settings']['paygent']['payment_term_min'] = '';
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
				if( 'acting_paygent_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
				if( 'acting_paygent_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
	 * @fook   admin_print_footer_scripts
	 * @param  -
	 * @return -
	 * @echo   js
	 */
	public function admin_scripts() {

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

		unset( $options['acting_settings']['paygent'] );
		$options['acting_settings']['paygent']['seq_merchant_id'] = ( isset( $_POST['seq_merchant_id'] ) ) ? trim( $_POST['seq_merchant_id'] ) : '';
		$options['acting_settings']['paygent']['hc'] = ( isset( $_POST['hc'] ) ) ? trim( $_POST['hc'] ) : '';
		$options['acting_settings']['paygent']['ope'] = ( isset( $_POST['ope'] ) ) ? $_POST['ope'] : '';
		$options['acting_settings']['paygent']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['paygent']['payment_class'] = ( isset( $_POST['payment_class'] ) ) ? $_POST['payment_class'] : '';
		$options['acting_settings']['paygent']['use_card_conf_number'] = ( isset( $_POST['use_card_conf_number'] ) ) ? $_POST['use_card_conf_number'] : '';
		$options['acting_settings']['paygent']['stock_card_mode'] = ( isset( $_POST['stock_card_mode'] ) ) ? $_POST['stock_card_mode'] : '';
		$options['acting_settings']['paygent']['threedsecure_ryaku'] = ( isset( $_POST['threedsecure_ryaku'] ) ) ? $_POST['threedsecure_ryaku'] : '';
		$options['acting_settings']['paygent']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['paygent']['conv_hc'] = ( isset( $_POST['conv_hc'] ) ) ? trim( $_POST['conv_hc'] ) : '';
		$options['acting_settings']['paygent']['payment_term_day'] = ( isset( $_POST['payment_term_day'] ) ) ? $_POST['payment_term_day'] : '';
		$options['acting_settings']['paygent']['payment_term_min'] = ( isset( $_POST['payment_term_min'] ) ) ? $_POST['payment_term_min'] : '';

		if( WCUtils::is_blank( $_POST['seq_merchant_id'] ) ) {
			$this->error_mes .= '※マーチャントIDを入力してください<br />';
		}
		if( '' == $options['acting_settings']['paygent']['hc'] ) {
			$this->error_mes .= '※ハッシュ値生成キーを入力してください<br />';
		}
		if( WCUtils::is_blank( $options['acting_settings']['paygent']['ope'] ) ) {
			$this->error_mes .= '※稼働環境を選択してください<br />';
		}
		if( 'on' == $options['acting_settings']['paygent']['conv_activate'] ) {
			if( '' == $options['acting_settings']['paygent']['payment_term_day'] && '' == $options['acting_settings']['paygent']['payment_term_min'] ) {
			} elseif( '' != $options['acting_settings']['paygent']['payment_term_day'] && '' != $options['acting_settings']['paygent']['payment_term_min'] ) {
				$this->error_mes .= '※「支払期間（日指定）」と「支払期間（分指定）」の両方を指定することはできません<br />';
			} elseif( '' != $options['acting_settings']['paygent']['payment_term_day'] ) {
				$term_day = (int)$options['acting_settings']['paygent']['payment_term_day'];
				if( $term_day < 2 || 60 < $term_day ) {
					$this->error_mes .= '※「支払期間（日指定）」が指定できる範囲を超えています<br />';
				}
			} elseif( '' != $options['acting_settings']['paygent']['payment_term_min'] ) {
				$term_min = (int)$options['acting_settings']['paygent']['payment_term_min'];
				if( $term_min < 5 || 2880 < $term_min ) {
					$this->error_mes .= '※「支払期間（分指定）」が指定できる範囲を超えています<br />';
				}
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['paygent']['card_activate'] || 'on' == $options['acting_settings']['paygent']['conv_activate'] ) {
				$options['acting_settings']['paygent']['activate'] = 'on';
				if( 'public' == $options['acting_settings']['paygent']['ope'] ) {
					$options['acting_settings']['paygent']['send_url'] = 'https://link.paygent.co.jp/v/u/request';
				} else {
					$options['acting_settings']['paygent']['send_url'] = "https://sandbox.paygent.co.jp/v/u/request";
				}
				$toactive = array();
				if( 'on' == $options['acting_settings']['paygent']['card_activate'] ) {
					$usces->payment_structure['acting_paygent_card'] = 'カード決済（'.$this->acting_name.'）';
					if( '' == $options['acting_settings']['paygent']['payment_class'] ) $options['acting_settings']['paygent']['payment_class'] = '0';
					if( '' == $options['acting_settings']['paygent']['use_card_conf_number'] ) $options['acting_settings']['paygent']['use_card_conf_number'] = 'off';
					if( '' == $options['acting_settings']['paygent']['stock_card_mode'] ) $options['acting_settings']['paygent']['stock_card_mode'] = 'off';
					if( '' == $options['acting_settings']['paygent']['threedsecure_ryaku'] ) $options['acting_settings']['paygent']['threedsecure_ryaku'] = 'off';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_paygent_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_paygent_card'] );
				}
				if( 'on' == $options['acting_settings']['paygent']['conv_activate'] ) {
					$usces->payment_structure['acting_paygent_conv'] = 'コンビニ決済（'.$this->acting_name.'）';
					if( '' == $options['acting_settings']['paygent']['payment_term_day'] && '' == $options['acting_settings']['paygent']['payment_term_min'] ) {
						$options['acting_settings']['paygent']['payment_term_day'] = 5;
					}
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_paygent_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_paygent_conv'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['paygent']['activate'] = 'off';
				unset( $usces->payment_structure['acting_paygent_card'] );
				unset( $usces->payment_structure['acting_paygent_conv'] );
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
			$options['acting_settings']['paygent']['activate'] = 'off';
			unset( $usces->payment_structure['acting_paygent_card'] );
			unset( $usces->payment_structure['acting_paygent_conv'] );
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
	<div id="uscestabs_paygent">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'paygent' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="paygent_form" id="paygent_form">
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_seq_merchant_id_paygent">マーチャントID</a></th>
				<td><input name="seq_merchant_id" type="text" id="seq_merchant_id_paygent" value="<?php echo esc_html( isset( $acting_opts['seq_merchant_id'] ) ? $acting_opts['seq_merchant_id'] : '' ); ?>" class="regular-text" maxlength="9" /></td>
			</tr>
			<tr id="ex_seq_merchant_id_paygent" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から割り当てられるマーチャントID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_hc_paygent">ハッシュ値生成キー</a></th>
				<td><input name="hc" type="text" id="hc_paygent" value="<?php echo esc_html( isset( $acting_opts['hc'] ) ? $acting_opts['hc'] : '' ); ?>" class="regular-text" maxlength="24" /></td>
			</tr>
			<tr id="ex_hc_paygent" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行されるハッシュ値生成キー（半角英数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_ope_paygent">稼働環境</a></th>
				<td><label><input name="ope" type="radio" id="ope_paygent_1" value="test"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'test' ) echo ' checked="checked"'; ?> /><span>テスト環境</span></label><br />
					<label><input name="ope" type="radio" id="ope_paygent_2" value="public"<?php if( isset( $acting_opts['ope'] ) && $acting_opts['ope'] == 'public' ) echo ' checked="checked"'; ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_ope_paygent" class="explanation"><td colspan="2">動作環境を切り替えます</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_paygent_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_paygent_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_payment_class_paygent">支払区分</a></th>
				<td><label><input name="payment_class" type="radio" id="payment_class_paygent_1" value="0"<?php if( isset( $acting_opts['payment_class'] ) && $acting_opts['payment_class'] == '0' ) echo ' checked="checked"'; ?> /><span>1回払いのみ</span></label><br />
					<label><input name="payment_class" type="radio" id="payment_class_paygent_2" value="1"<?php if( isset( $acting_opts['payment_class'] ) && $acting_opts['payment_class'] == '1' ) echo ' checked="checked"'; ?> /><span>全て</span></label><br />
					<label><input name="payment_class" type="radio" id="payment_class_paygent_2" value="2"<?php if( isset( $acting_opts['payment_class'] ) && $acting_opts['payment_class'] == '2' ) echo ' checked="checked"'; ?> /><span>ボーナス一括以外全て</span></label>
				</td>
			</tr>
			<tr id="ex_payment_class_paygent" class="explanation"><td colspan="2">ユーザーに支払を許可するカード支払方法の区分です。加盟店審査を経て加盟店様ごとに設定された支払可能回数から、購入者に提示する支払回数をさらに絞り込みたい場合に使用してください。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_use_card_conf_number_paygent">カード確認番号<br />利用フラグ</a></th>
				<td><label><input name="use_card_conf_number" type="radio" id="use_card_conf_number_paygent_1" value="on"<?php if( isset( $acting_opts['use_card_conf_number'] ) && $acting_opts['use_card_conf_number'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="use_card_conf_number" type="radio" id="use_card_conf_number_paygent_2" value="off"<?php if( isset( $acting_opts['use_card_conf_number'] ) && $acting_opts['use_card_conf_number'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_use_card_conf_number_paygent" class="explanation"><td colspan="2">確認番号の入力を必須とするかどうかを指定します。確認番号が実際に使用されるかどうかは、カードを発行したイシュアーに依存します。</td></tr>
			<tr>
				<th>カード情報お預りモード</th>
				<td><label><input name="stock_card_mode" type="radio" id="stock_card_mode_paygent_1" value="on"<?php if( isset( $acting_opts['stock_card_mode'] ) && $acting_opts['stock_card_mode'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="stock_card_mode" type="radio" id="stock_card_mode_paygent_2" value="off"<?php if( isset( $acting_opts['stock_card_mode'] ) && $acting_opts['stock_card_mode'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th>3Dセキュア</th>
				<td><label><input name="threedsecure_ryaku" type="radio" id="threedsecure_ryaku_paygent_1" value="on"<?php if( isset( $acting_opts['threedsecure_ryaku'] ) && $acting_opts['threedsecure_ryaku'] == 'on' ) echo ' checked="checked"'; ?> /><span>契約</span></label><br />
					<label><input name="threedsecure_ryaku" type="radio" id="threedsecure_ryaku_paygent_2" value="off"<?php if( isset( $acting_opts['threedsecure_ryaku'] ) && $acting_opts['threedsecure_ryaku'] == 'off' ) echo ' checked="checked"'; ?> /><span>未契約</span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ決済</th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_paygent_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_paygent_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_payment_term_day_paygent">支払期間（日指定）</a></th>
				<td><input name="payment_term_day" type="text" id="payment_term_day_paygent" value="<?php echo esc_html( isset( $acting_opts['payment_term_day'] ) ? $acting_opts['payment_term_day'] : '5' ); ?>" class="small-text" maxlength="2" /></td>
			</tr>
			<tr id="ex_payment_term_day_paygent" class="explanation"><td colspan="2">支払うことのできる期限を日で指定します。指定できる範囲は2以上60以下です。（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_payment_term_min_paygent">支払期間（分指定）</a></th>
				<td><input name="payment_term_min" type="text" id="payment_term_min_paygent" value="<?php echo esc_html( isset( $acting_opts['payment_term_min'] ) ? $acting_opts['payment_term_min'] : '' ); ?>" class="small-text" maxlength="4" /></td>
			</tr>
			<tr id="ex_payment_term_min_paygent" class="explanation"><td colspan="2">支払うことのできる期限を分で指定します。指定できる範囲は5以上2880以下です。（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_hc_paygent">差分通知<br />ハッシュ値生成キー</a></th>
				<td><input name="conv_hc" type="text" id="conv_hc_paygent" value="<?php echo esc_html( isset( $acting_opts['conv_hc'] ) ? $acting_opts['conv_hc'] : '' ); ?>" class="regular-text" maxlength="24" /></td>
			</tr>
			<tr id="ex_conv_hc_paygent" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行される差分通知ハッシュ値生成キー（半角英数字）</td></tr>
		</table>
		<input name="acting" type="hidden" value="paygent" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?>の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php echo $this->acting_formal_name; ?></strong></p>
		<a href="<?php echo $this->acting_company_url; ?>" target="_blank"><?php echo $this->acting_name; ?>の詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへ遷移してカード情報を入力する決済システムです。</p>
		<p>決済通知ステータスの申請は「任意」となっていますが、以下のように申請してください。<br />
			【クレジット決済】<br />
			　■ 申込済<br />
			【コンビニ決済】<br />
			　■ 速報検知済<br />
			　■ 消込済<br />
		</p>
	</div>
	</div><!--uscestabs_paygent-->
<?php
		endif;
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
