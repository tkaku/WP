<?php
/**
 * メタップスペイメント
 * ( 旧 ペイデザイン )
 * ( 旧 デジタルチェック )
 *
 * @class    DIGITALCHECK_SETTLEMENT
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    1.9.20
 */
class DIGITALCHECK_SETTLEMENT
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

		$this->paymod_id = 'digitalcheck';
		$this->pay_method = array(
			'acting_digitalcheck_card',
			'acting_digitalcheck_conv',
		);
		$this->acting_name = 'メタップスペイメント';
		$this->acting_formal_name = 'メタップスペイメント';
		$this->acting_company_url = 'https://www.metaps-payment.com/';

		$this->initialize_data();

		if( is_admin() ) {
			//add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_filter( 'usces_filter_settle_info_field_meta_keys', array( $this, 'settlement_info_field_meta_keys' ) );
			add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ) );
			//add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
			add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
		}

		if( $this->is_activate_conv() ) {
			add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'order_mail_payment' ), 10, 6 );
			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['digitalcheck'] ) ) {
			$options['acting_settings']['digitalcheck']['card_activate'] = 'off';
			$options['acting_settings']['digitalcheck']['card_ip'] = '';
			$options['acting_settings']['digitalcheck']['card_pass'] = '';
			$options['acting_settings']['digitalcheck']['card_kakutei'] = '';
			$options['acting_settings']['digitalcheck']['card_user_id'] = '';
			$options['acting_settings']['digitalcheck']['conv_activate'] = 'off';
			$options['acting_settings']['digitalcheck']['conv_ip'] = '';
			$options['acting_settings']['digitalcheck']['conv_store'] = array();
			$options['acting_settings']['digitalcheck']['conv_kigen'] = '14';
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
				if( 'acting_digitalcheck_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
				if( 'acting_digitalcheck_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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

		unset( $options['acting_settings']['digitalcheck'] );
		$options['acting_settings']['digitalcheck']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : 'off';
		$options['acting_settings']['digitalcheck']['card_ip'] = ( isset( $_POST['card_ip'] ) ) ? $_POST['card_ip'] : '';
		$options['acting_settings']['digitalcheck']['card_pass'] = ( isset( $_POST['card_pass'] ) ) ? $_POST['card_pass'] : '';
		$options['acting_settings']['digitalcheck']['card_kakutei'] = ( isset( $_POST['card_kakutei'] ) ) ? $_POST['card_kakutei'] : '';
		$options['acting_settings']['digitalcheck']['card_user_id'] = ( isset( $_POST['card_user_id'] ) ) ? $_POST['card_user_id'] : '';
		$options['acting_settings']['digitalcheck']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : 'off';
		$options['acting_settings']['digitalcheck']['conv_ip'] = ( isset( $_POST['conv_ip'] ) ) ? $_POST['conv_ip'] : '';
		$options['acting_settings']['digitalcheck']['conv_store'] = ( isset( $_POST['conv_store'] ) ) ? $_POST['conv_store'] : array();
		$options['acting_settings']['digitalcheck']['conv_kigen'] = ( isset( $_POST['conv_kigen'] ) ) ? $_POST['conv_kigen'] : '14';

		if( 'on' == $options['acting_settings']['digitalcheck']['card_activate'] ) {
			if( WCUtils::is_blank( $_POST['card_ip'] ) ) {
				$this->error_mes .= '※加盟店コードを入力してください<br />';
			}
			if( 'on' == $options['acting_settings']['digitalcheck']['card_user_id'] ) {
				if( WCUtils::is_blank( $_POST['card_pass'] ) ) {
					$this->error_mes .= '※加盟店パスワードを入力してください<br />';
				}
			}
		}
		if( 'on' == $options['acting_settings']['digitalcheck']['conv_activate'] ) {
			if( WCUtils::is_blank( $_POST['conv_ip'] ) ) {
				$this->error_mes .= '※加盟店コードを入力してください<br />';
			}
			if( empty( $_POST['conv_store'] ) ) {
				$this->error_mes .= '※利用コンビニを選択してください<br />';
			}
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['digitalcheck']['card_activate'] || 'on' == $options['acting_settings']['digitalcheck']['conv_activate'] ) {
				$options['acting_settings']['digitalcheck']['activate'] = 'on';
				$toactive = array();
				if( 'on' == $options['acting_settings']['digitalcheck']['card_activate'] ) {
					$options['acting_settings']['digitalcheck']['send_url_card'] = "https://www.paydesign.jp/settle/settle3/bp3.dll";
					if( 'on' == $options['acting_settings']['digitalcheck']['card_user_id'] ) {
						$options['acting_settings']['digitalcheck']['send_url_user_id'] = "https://www.paydesign.jp/settle/settlex/credit2.dll";
					} else {
						$options['acting_settings']['digitalcheck']['send_url_user_id'] = "";
					}
					$usces->payment_structure['acting_digitalcheck_card'] = 'カード決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_digitalcheck_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_digitalcheck_card'] );
				}
				if( 'on' == $options['acting_settings']['digitalcheck']['conv_activate'] ) {
					$options['acting_settings']['digitalcheck']['send_url_conv'] = "https://www.paydesign.jp/settle/settle3/bp3.dll";
					$usces->payment_structure['acting_digitalcheck_conv'] = 'コンビニ決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_digitalcheck_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_digitalcheck_conv'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['digitalcheck']['activate'] = 'off';
				unset( $usces->payment_structure['acting_digitalcheck_card'] );
				unset( $usces->payment_structure['acting_digitalcheck_conv'] );
			}
			if( 'on' != $options['acting_settings']['digitalcheck']['card_user_id'] || 'off' == $options['acting_settings']['digitalcheck']['activate'] ) {
				usces_clear_quickcharge( 'digitalcheck_ip_user_id' );
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
			$options['acting_settings']['digitalcheck']['activate'] = 'off';
			unset( $usces->payment_structure['acting_digitalcheck_card'] );
			unset( $usces->payment_structure['acting_digitalcheck_conv'] );
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
	<div id="uscestabs_digitalcheck">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'digitalcheck' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="digitalcheck_form" id="digitalcheck_form">
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_digitalcheck_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked="checked"'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_digitalcheck_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked="checked"'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_ip_digitalcheck">加盟店コード</a></th>
				<td><input name="card_ip" type="text" id="card_ip_digitalcheck" value="<?php echo esc_html( isset( $acting_opts['card_ip'] ) ? $acting_opts['card_ip'] : '' ); ?>" class="regular-text" maxlength="10" /></td>
			</tr>
			<tr id="ex_card_ip_digitalcheck" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行される加盟店コード（半角英数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_pass_digitalcheck">加盟店パスワード</a></th>
				<td><input name="card_pass" type="text" id="card_pass_digitalcheck" value="<?php echo esc_html( isset( $acting_opts['card_pass'] ) ? $acting_opts['card_pass'] : '' ); ?>" class="regular-text" maxlength="10" /></td>
			</tr>
			<tr id="ex_card_pass_digitalcheck" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行される加盟店パスワード（半角英数字）<br />ユーザID決済をご利用の場合は、必須となります。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_kakutei">決済自動確定</a></th>
				<td><label><input name="card_kakutei" type="radio" id="card_kakutei_0" value="0"<?php if( isset( $acting_opts['card_kakutei'] ) && $acting_opts['card_kakutei'] == '0' ) echo ' checked'; ?> /><span>与信のみ</span></label><br />
					<label><input name="card_kakutei" type="radio" id="card_kakutei_1" value="1"<?php if( isset( $acting_opts['card_kakutei'] ) && $acting_opts['card_kakutei'] == '1' ) echo ' checked'; ?> /><span>売上確定</span></label>
				</td>
			</tr>
			<tr id="ex_card_kakutei" class="explanation"><td colspan="2">注文の際にクレジットの与信のみを行ないます。<br />実際の売上として計上するには確定処理が必要となります。省略時は「売上確定（確定まで同時に行う）」です。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_user_id">ユーザID決済</a></th>
				<td><label><input name="card_user_id" type="radio" id="card_user_id_1" value="on"<?php if( isset( $acting_opts['card_user_id'] ) && $acting_opts['card_user_id'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="card_user_id" type="radio" id="card_user_id_2" value="off"<?php if( isset( $acting_opts['card_user_id'] ) && $acting_opts['card_user_id'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_card_user_id" class="explanation"><td colspan="2">過去にクレジットカードでのお取引があるユーザは、次回からカード情報の入力を省略することが可能となります。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ決済</th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_digitalcheck_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked="checked"' ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_digitalcheck_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked="checked"' ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_ip_digitalcheck">加盟店コード</a></th>
				<td><input name="conv_ip" type="text" id="conv_ip_digitalcheck" value="<?php echo esc_html( isset( $acting_opts['conv_ip'] ) ? $acting_opts['conv_ip'] : '' ); ?>" class="regular-text" maxlength="10" /></td>
			</tr>
			<tr id="ex_conv_ip_digitalcheck" class="explanation"><td colspan="2">契約時に<?php echo $this->acting_name; ?>から発行される加盟店コード（半角英数字）</td></tr>
			<tr>
				<th rowspan="4"><a class="explanation-label" id="label_ex_conv_store_digitalcheck">利用コンビニ</a></th>
				<td><label><input name="conv_store[]" type="checkbox" id="conv_store_1" value="1"<?php if( isset( $acting_opts['conv_store'] ) && in_array( '1', $acting_opts['conv_store'] ) ) echo ' checked'; ?> /><span>Loppi決済（ローソン・セイコーマート・ミニストップ）</span></label></td>
			</tr>
			<tr>
				<td><label><input name="conv_store[]" type="checkbox" id="conv_store_2" value="2"<?php if( isset( $acting_opts['conv_store'] ) && in_array( '2', $acting_opts['conv_store'] ) ) echo ' checked'; ?> /><span>Seven決済（セブンイレブン）</span></label></td>
			</tr>
			<tr>
				<td><label><input name="conv_store[]" type="checkbox" id="conv_store_3" value="3"<?php if( isset( $acting_opts['conv_store'] ) && in_array( '3', $acting_opts['conv_store'] ) ) echo ' checked'; ?> /><span>FAMIMA決済（ファミリーマート）</span></label></td>
			</tr>
			<tr>
				<td><label><input name="conv_store[]" type="checkbox" id="conv_store_73" value="73"<?php if( isset( $acting_opts['conv_store'] ) && in_array( '73', $acting_opts['conv_store'] ) ) echo ' checked'; ?> /><span>オンライン決済（デイリーヤマザキ・ヤマザキデイリーストアー）</span></label></td>
			</tr>
			<tr id="ex_conv_store_digitalcheck" class="explanation"><td colspan="2">収納先のコンビニを選択します。コンビニ毎の審査が必要となり、審査通過後にご利用可能となります。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_kigen_digitalcheck">支払期限</a></th>
				<td>
				<?php
					$selected = array_fill( 1, 30, '' );
					if( isset( $acting_opts['conv_kigen'] ) ) {
						$selected[$acting_opts['conv_kigen']] = ' selected';
					} else {
						$selected[14] = ' selected';
					}
				?>
				<select name="conv_kigen" id="conv_kigen">
				<?php for( $i = 1; $i <= 30; $i++ ): ?>
					<option value="<?php echo esc_html( $i ); ?>"<?php echo esc_html( $selected[$i] ); ?>><?php echo esc_html( $i ); ?></option>
				<?php endfor; ?>
				</select>（日数）</td>
			</tr>
			<tr id="ex_conv_kigen_digitalcheck" class="explanation"><td colspan="2">コンビニ店頭でお支払いいただける期限となります。</td></tr>
		</table>
		<input name="acting" type="hidden" value="digitalcheck" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?>の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php echo $this->acting_formal_name; ?></strong></p>
		<a href="<?php echo $this->acting_company_url; ?>" target="_blank"><?php echo $this->acting_name; ?>の詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへ遷移してカード情報を入力する決済システムです。</p>
	</div>
	</div><!--uscestabs_digitalcheck-->
<?php
		endif;
	}

	/**
	 * 受注データから取得する決済情報のキー
	 * @fook   usces_filter_settle_info_field_meta_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_meta_keys( $keys ) {

		$keys = array_merge( $keys, array( 'SID', 'DATE', 'TIME', 'CVS', 'SHNO' ) );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * @fook   usces_filter_settle_info_field_keys
	 * @param  $keys
	 * @return array $keys
	 */
	public function settlement_info_field_keys( $keys ) {

		$keys = array_merge( $keys, array( 'SID', 'DATE', 'TIME', 'CVS', 'SHNO' ) );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * @fook   usces_filter_settle_info_field_value
	 * @param  $value $key $acting
	 * @return string $value
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {

		return $value;
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

		if( isset( $_REQUEST['SID'] ) && isset( $_REQUEST['FUKA'] ) ) {
			if( substr( $_REQUEST['FUKA'], 0, 24 ) == 'acting_digitalcheck_card' ) {
				$data['SID'] = esc_sql( $_REQUEST['SID'] );
				$usces->set_order_meta_value( $_REQUEST['FUKA'], serialize( $data ), $order_id );
			}
			if( substr( $_REQUEST['FUKA'], 0, 24 ) == 'acting_digitalcheck_conv' ) {
				$data['SID'] = esc_sql( $_REQUEST['SID'] );
				if( !empty( $_REQUEST['CVS'] ) ) $data['CVS'] = esc_sql( $_REQUEST['CVS'] );
				if( !empty( $_REQUEST['SHNO'] ) ) $data['SHNO'] = esc_sql( $_REQUEST['SHNO'] );
				if( !empty( $_REQUEST['FURL'] ) ) $data['FURL'] = esc_sql( $_REQUEST['FURL'] );
				$usces->set_order_meta_value( $_REQUEST['FUKA'], serialize( $data ), $order_id );
			}
			$usces->set_order_meta_value( 'SID', $_REQUEST['SID'], $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $_REQUEST['SID'], $order_id );
		}
	}

	/**
	 * 会員データ編集画面 ユーザID決済登録情報
	 * @fook   usces_action_admin_member_info
	 * @param  $member_data $member_meta_data $member_history
	 * @return -
	 * @echo   -
	 */
	public function member_settlement_info( $member_data, $member_meta_data, $member_history ) {

		if( 0 < count( $member_meta_data ) ):
			$cardinfo = array();
			foreach( $member_meta_data as $value ) {
				if( in_array( $value['meta_key'], array( 'digitalcheck_ip_user_id' ) ) ) {
					$cardinfo[$value['meta_key']] = $value['meta_value'];
				}
			}
			if( 0 < count( $cardinfo ) ):
				foreach( $cardinfo as $key => $value ):
					if( $key != 'digitalcheck_ip_user_id' ): ?>
		<tr>
			<td class="label"><?php echo esc_html( $key ); ?></td>
			<td><div class="rod_left shortm"><?php echo esc_html( $value ); ?></div></td>
		</tr>
<?php				endif;
				endforeach;
				if( array_key_exists( 'digitalcheck_ip_user_id', $cardinfo ) ): ?>
		<tr>
			<td class="label">ユーザID決済</td>
			<td><div class="rod_left shortm">登録あり</div></td>
		</tr>
		<tr>
			<td class="label"><input type="checkbox" name="digitalcheck_ip_user_id" id="digitalcheck_ip_user_id" value="delete"></td>
			<td><label for="digitalcheck_ip_user_id">ユーザID決済を解除する</label></td>
		</tr>
<?php			endif;
			endif;
		endif;
	}

	/**
	 * 会員データ編集画面 ユーザID決済登録解除
	 * @fook   usces_action_post_update_memberdata
	 * @param  -
	 * @return -
	 * @echo   -
	 */
	public function member_edit_post( $member_id, $res ) {
		global $usces;

		if( isset( $_POST['digitalcheck_ip_user_id'] ) && $_POST['digitalcheck_ip_user_id'] == 'delete' ) {
			$usces->del_member_meta( 'digitalcheck_ip_user_id', $member_id );
		}
	}

	/**
	 * オンライン収納代行決済用サンキューメール
	 * @fook   usces_filter_send_order_mail_payment
	 * @param  $msg_payment $order_id $payment $cart $entry $data
	 * @return string $msg_payment
	 */
	public function order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ) {
		global $usces;

		if( 'acting_digitalcheck_conv' != $payment['settlement'] ) {
			return $msg_payment;
		}

		$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
		if( isset( $args['CVS'] ) ) {
			$msg_payment .= '支払先 : '.$this->get_conv_name( $args['CVS'] )."\r\n";
		}
		if( isset( $args['SHNO'] ) ) {
			$msg_payment .= '支払番号 : '.$args['SHNO']."\r\n";
		}
		if( isset( $args['FURL'] ) ) {
			$msg_payment .= '支払情報URL : '.$args['FURL']."\r\n";
		}
		return $msg_payment;
	}

	/**
	 * 管理画面送信メール
	 * @fook   usces_filter_order_confirm_mail_payment
	 * @param  $msg_payment $order_id $payment $cart $data
	 * @return string $msg_payment
	 */
	public function order_confirm_mail_payment( $msg_payment, $order_id, $payment, $cart, $data ) {
		global $usces;

		if( 'acting_digitalcheck_conv' != $payment['settlement'] ) {
			return $msg_payment;
		}

		//if( 'orderConfirmMail' == $_POST['mode'] || 'changeConfirmMail' == $_POST['mode'] ) {
		if( 'orderConfirmMail' == $_POST['mode'] ) {
			$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
			if( isset( $args['CVS'] ) ) {
				$msg_payment .= '支払先 : '.$this->get_conv_name( $args['CVS'] )."\r\n";
			}
			if( isset( $args['SHNO'] ) ) {
				$msg_payment .= '支払番号 : '.$args['SHNO']."\r\n";
			}
			if( isset( $args['FURL'] ) ) {
				$msg_payment .= '支払情報URL : '.$args['FURL']."\r\n";
			}
		}
		return $msg_payment;
	}

	/**
	 * コンビニ名称取得
	 * @param  $code
	 * @return string $name
	 */
	protected function get_conv_name( $code ) {
		switch( $code ) {
		case 'SEVEN':
			$name = 'セブン-イレブン';
			break;
		case 'loppi':
			$name = 'ローソン/ミニストップ/セイコーマート';
			break;
		case 'famima':
			$name = 'ファミリーマート';
			break;
		case 'wellnet':
			$name = 'デイリーヤマザキ/ヤマザキデイリーストア';
			break;
		default:
			$name = $code;
		}
		return $name;
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
