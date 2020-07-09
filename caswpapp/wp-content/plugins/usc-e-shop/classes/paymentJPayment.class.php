<?php
/**
 * ロボットペイメント
 * (旧 Cloud Payment )
 * (旧 J-Payment )
 *
 * @class    JPAYMENT_SETTLEMENT
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    1.9.20
 */
class JPAYMENT_SETTLEMENT
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

		$this->paymod_id = 'jpayment';
		$this->pay_method = array(
			'acting_jpayment_card',
			'acting_jpayment_conv',
			'acting_jpayment_bank',
		);
		$this->acting_name = 'ROBOT PAYMENT';
		$this->acting_formal_name = 'ROBOT PAYMENT';
		$this->acting_company_url = 'https://www.robotpayment.co.jp/';

		$this->initialize_data();

		if( is_admin() ) {
			//add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
		}

		if( $this->is_activate_card() || $this->is_activate_conv() || $this->is_activate_bank() ) {
			add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
		}

		if( $this->is_activate_conv() || $this->is_activate_bank() ) {
			add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
			add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'send_order_mail_payment' ), 10, 6 );
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
		if( !isset( $options['acting_settings'] ) || !isset( $options['acting_settings']['jpayment'] ) ) {
			$options['acting_settings']['jpayment']['activate'] = '';
			$options['acting_settings']['jpayment']['aid'] = '';
			$options['acting_settings']['jpayment']['card_activate'] = 'off';
			$options['acting_settings']['jpayment']['card_jb'] = '';
			$options['acting_settings']['jpayment']['conv_activate'] = 'off';
			$options['acting_settings']['jpayment']['webm_activate'] = 'off';
			$options['acting_settings']['jpayment']['bitc_activate'] = 'off';
			$options['acting_settings']['jpayment']['suica_activate'] = 'off';
			$options['acting_settings']['jpayment']['bank_activate'] = 'off';
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
				if( 'acting_jpayment_card' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
				if( 'acting_jpayment_conv' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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

		case 'bank':
			foreach( $payment_method as $payment ) {
				if( 'acting_jpayment_bank' == $payment['settlement'] && 'activate' == $payment['use'] ) {
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
	 * バンクチェック決済有効判定
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

		unset( $options['acting_settings']['jpayment'] );
		$options['acting_settings']['jpayment']['aid'] = ( isset( $_POST['aid'] ) ) ? trim( $_POST['aid'] ) : '';
		$options['acting_settings']['jpayment']['card_activate'] = ( isset( $_POST['card_activate'] ) ) ? $_POST['card_activate'] : '';
		$options['acting_settings']['jpayment']['card_jb'] = ( isset( $_POST['card_jb'] ) ) ? $_POST['card_jb'] : '';
		$options['acting_settings']['jpayment']['conv_activate'] = ( isset( $_POST['conv_activate'] ) ) ? $_POST['conv_activate'] : '';
		$options['acting_settings']['jpayment']['bank_activate'] = ( isset( $_POST['bank_activate'] ) ) ? $_POST['bank_activate'] : '';

		if( WCUtils::is_blank( $_POST['aid'] ) ) {
			$this->error_mes .= '※店舗IDコードを入力してください<br />';
		}
		if( isset( $_POST['card_activate'] ) && 'on' == $_POST['card_activate'] && empty( $_POST['card_jb'] ) ) {
			$this->error_mes .= '※ジョブタイプを指定してください<br />';
		}

		if( '' == $this->error_mes ) {
			$usces->action_status = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if( 'on' == $options['acting_settings']['jpayment']['card_activate'] || 'on' == $options['acting_settings']['jpayment']['conv_activate'] || 'on' == $options['acting_settings']['jpayment']['bank_activate'] ) {
				$options['acting_settings']['jpayment']['activate'] = 'on';
				$options['acting_settings']['jpayment']['send_url'] = 'https://credit.j-payment.co.jp/gateway/payform.aspx';
				$toactive = array();
				if( 'on' == $options['acting_settings']['jpayment']['card_activate'] ) {
					$usces->payment_structure['acting_jpayment_card'] = 'カード決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_jpayment_card' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_jpayment_card'] );
				}
				if( 'on' == $options['acting_settings']['jpayment']['conv_activate'] ) {
					$usces->payment_structure['acting_jpayment_conv'] = 'コンビニ決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_jpayment_conv' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_jpayment_conv'] );
				}
				if( 'on' == $options['acting_settings']['jpayment']['bank_activate'] ) {
					$usces->payment_structure['acting_jpayment_bank'] = 'バンクチェック決済（'.$this->acting_name.'）';
					foreach( $payment_method as $settlement => $payment ) {
						if( 'acting_jpayment_bank' == $settlement && 'deactivate' == $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_jpayment_bank'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if( 0 < count( $toactive ) ) {
					$usces->action_message .= __( "Please update the payment method to \"Activate\". <a href=\"admin.php?page=usces_initial#payment_method_setting\">General Setting > Payment Methods</a>", 'usces' );
				}
			} else {
				$options['acting_settings']['jpayment']['activate'] = 'off';
				unset( $usces->payment_structure['acting_jpayment_card'] );
				unset( $usces->payment_structure['acting_jpayment_conv'] );
				unset( $usces->payment_structure['acting_jpayment_bank'] );
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
			$options['acting_settings']['jpayment']['activate'] = 'off';
			unset( $usces->payment_structure['acting_jpayment_card'] );
			unset( $usces->payment_structure['acting_jpayment_conv'] );
			unset( $usces->payment_structure['acting_jpayment_bank'] );
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
	<div id="uscestabs_jpayment">
	<div class="settlement_service"><span class="service_title"><?php echo $this->acting_formal_name; ?></span></div>
	<?php if( isset( $_POST['acting'] ) && 'jpayment' == $_POST['acting'] ): ?>
		<?php if( '' != $this->error_mes ): ?>
		<div class="error_message"><?php echo $this->error_mes; ?></div>
		<?php elseif( isset( $acting_opts['activate'] ) && 'on' == $acting_opts['activate'] ): ?>
		<div class="message">十分にテストを行ってから運用してください。</div>
		<?php endif; ?>
	<?php endif; ?>
	<form action="" method="post" name="jpayment_form" id="jpayment_form">
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_aid_jpayment">店舗ID</a></th>
				<td><input name="aid" type="text" id="aid_jpayment" value="<?php echo esc_html( isset( $acting_opts['aid'] ) ? $acting_opts['aid'] : '' ); ?>" class="regular-text" maxlength="6" /></td>
			</tr>
			<tr id="ex_aid_jpayment" class="explanation"><td colspan="2">契約時に <?php echo $this->acting_name; ?> から発行される店舗ID（半角数字）</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_card_jpayment">クレジットカード決済</a></th>
				<td><label><input name="card_activate" type="radio" id="card_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['card_activate'] ) && $acting_opts['card_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_card_jpayment" class="explanation"><td colspan="2">クレジットカード決済を利用するかどうか。<br />※自動継続課金には対応していません。</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_card_jb_jpayment">ジョブタイプ</a></th>
				<td><!--<label><input name="card_jb" type="radio" id="card_jb_jpayment_1" value="CHECK"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'CHECK' ) echo ' checked'; ?> /><span>有効性チェック</span></label><br />-->
					<label><input name="card_jb" type="radio" id="card_jb_jpayment_2" value="AUTH"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'AUTH' ) echo ' checked'; ?> /><span>仮売上処理</span></label><br />
					<label><input name="card_jb" type="radio" id="card_jb_jpayment_3" value="CAPTURE"<?php if( isset( $acting_opts['card_jb'] ) && $acting_opts['card_jb'] == 'CAPTURE' ) echo ' checked'; ?> /><span>仮実同時売上処理</span></label>
				</td>
			</tr>
			<tr id="ex_card_jb_jpayment" class="explanation"><td colspan="2">決済の種類を指定します。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_conv_jpayment">コンビニ決済</a></th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['conv_activate'] ) && $acting_opts['conv_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_conv_jpayment" class="explanation"><td colspan="2">コンビニ（ペーパーレス）決済を利用するかどうか。</td></tr>
		</table>
		<!--<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_webm_jpayment">WebMoney決済</a></th>
				<td><label><input name="webm_activate" type="radio" id="webm_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['webm_activate'] ) && $acting_opts['webm_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="webm_activate" type="radio" id="webm_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['webm_activate'] ) && $acting_opts['webm_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_webm_jpayment" class="explanation"><td colspan="2">電子マネー（WebMoney）決済を利用するかどうか。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_bitc_jpayment">BitCash決済</a></th>
				<td><label><input name="bitc_activate" type="radio" id="bitc_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['bitc_activate'] ) && $acting_opts['bitc_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="bitc_activate" type="radio" id="bitc_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['bitc_activate'] ) && $acting_opts['bitc_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_bitc_jpayment" class="explanation"><td colspan="2">電子マネー（BitCash）決済を利用するかどうか。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_suica_jpayment">モバイルSuica決済</a></th>
				<td><label><input name="suica_activate" type="radio" id="suica_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['suica_activate'] ) && $acting_opts['suica_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="suica_activate" type="radio" id="suica_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['suica_activate'] ) && $acting_opts['suica_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_suica_jpayment" class="explanation"><td colspan="2">電子マネー（モバイルSuica）決済を利用するかどうか。</td></tr>
		</table>-->
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_bank_jpayment">バンクチェック決済</a></th>
				<td><label><input name="bank_activate" type="radio" id="bank_activate_jpayment_1" value="on"<?php if( isset( $acting_opts['bank_activate'] ) && $acting_opts['bank_activate'] == 'on' ) echo ' checked'; ?> /><span>利用する</span></label><br />
					<label><input name="bank_activate" type="radio" id="bank_activate_jpayment_2" value="off"<?php if( isset( $acting_opts['bank_activate'] ) && $acting_opts['bank_activate'] == 'off' ) echo ' checked'; ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_bank_jpayment" class="explanation"><td colspan="2">バンクチェック決済を利用するかどうか。</td></tr>
		</table>
		<input name="acting" type="hidden" value="jpayment" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo $this->acting_name; ?> の設定を更新する" />
		<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php echo $this->acting_formal_name; ?></strong></p>
		<a href="<?php echo $this->acting_company_url; ?>" target="_blank"><?php echo $this->acting_name; ?> の詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへ遷移してカード情報を入力する決済システムです。</p>
	</div>
	</div><!--uscestabs_jpayment-->
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

		if( isset( $_REQUEST['acting'] ) && ( 'jpayment_card' == $_REQUEST['acting'] || 'jpayment_conv' == $_REQUEST['acting'] || 'jpayment_bank' == $_REQUEST['acting'] ) ) {
			$usces->set_order_meta_value( 'settlement_id', $_GET['cod'], $order_id );
			if( !empty( $_GET['gid'] ) ) {
				$usces->set_order_meta_value( 'wc_trans_id', $_GET['gid'], $order_id );
			}
			foreach( $_GET as $key => $value ) {
				if( 'purchase_jpayment' != $key )
					$data[$key] = esc_sql( $value );
			}
			$usces->set_order_meta_value( 'acting_'.$_REQUEST['acting'], serialize( $data ), $order_id );
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

		if( $payment['settlement'] == 'acting_jpayment_conv' ) {
			$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
			$msg_payment .= '決済番号 : '.$args['gid']."\r\n";
			$msg_payment .= '決済金額 : '.number_format( $args['ta'] ).__( 'dollars', 'usces' )."\r\n";
			$msg_payment .= 'お支払先 : '.usces_get_conv_name( $args['cv'] )."\r\n";
			$msg_payment .= 'コンビニ受付番号 : '.$args['no']."\r\n";
			if( $args['cv'] != '030' ) {//ファミリーマート以外
				$msg_payment .= 'コンビニ受付番号情報URL : '.$args['cu']."\r\n";
			}
			$msg_payment .= "\r\n".usces_mail_line( 2, $data['order_email'] )."\r\n";//--------------------

		} elseif( $payment['settlement'] == 'acting_jpayment_bank' ) {
			$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
			$msg_payment .= '決済番号 : '.$args['gid']."\r\n";
			$msg_payment .= '決済金額 : '.number_format( $args['ta'] ).__( 'dollars', 'usces' )."\r\n";
			$bank = explode( '.', $args['bank'] );
			$msg_payment .= '銀行コード : '.$bank[0]."\r\n";
			$msg_payment .= '銀行名 : '.$bank[1]."\r\n";
			$msg_payment .= '支店コード : '.$bank[2]."\r\n";
			$msg_payment .= '支店名 : '.$bank[3]."\r\n";
			$msg_payment .= '口座種別 : '.$bank[4]."\r\n";
			$msg_payment .= '口座番号 : '.$bank[5]."\r\n";
			$msg_payment .= '口座名義 : '.$bank[6]."\r\n";
			$msg_payment .= '支払期限 : '.substr( $args['exp'], 0, 4 ).'年'.substr( $args['exp'], 4, 2 ).'月'.substr( $args['exp'], 6, 2 )."日\r\n";
			$msg_payment .= "\r\n".usces_mail_line( 2, $data['order_email'] )."\r\n";//--------------------
		}
		return $msg_payment;
	}

	/**
	 * サンキューメール
	 * @fook   usces_filter_send_order_mail_payment
	 * @param  $msg_payment $order_id $payment $cart $entry $data
	 * @return string $msg_payment
	 */
	public function send_order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ) {
		global $usces;

		if( $payment['settlement'] == 'acting_jpayment_conv' ) {
			$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
			$msg_payment .= '決済番号 : '.$args['gid']."\r\n";
			$msg_payment .= '決済金額 : '.number_format( $args['ta'] ).__( 'dollars', 'usces' )."\r\n";
			$msg_payment .= 'お支払先 : '.usces_get_conv_name( $args['cv'] )."\r\n";
			$msg_payment .= 'コンビニ受付番号 : '.$args['no']."\r\n";
			if( $args['cv'] != '030' ) {//ファミリーマート以外
				$msg_payment .= 'コンビニ受付番号情報URL : '.$args['cu']."\r\n";
			}
			$msg_payment .= "\r\n".usces_mail_line( 2, $entry['customer']['mailaddress1'] )."\r\n";//--------------------

		} elseif( $payment['settlement'] == 'acting_jpayment_bank' ) {
			$args = maybe_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
			$msg_payment .= '決済番号 : '.$args['gid']."\r\n";
			$msg_payment .= '決済金額 : '.number_format( $args['ta'] ).__( 'dollars', 'usces' )."\r\n";
			$bank = explode( '.', $args['bank'] );
			$msg_payment .= '銀行コード : '.$bank[0]."\r\n";
			$msg_payment .= '銀行名 : '.$bank[1]."\r\n";
			$msg_payment .= '支店コード : '.$bank[2]."\r\n";
			$msg_payment .= '支店名 : '.$bank[3]."\r\n";
			$msg_payment .= '口座種別 : '.$bank[4]."\r\n";
			$msg_payment .= '口座番号 : '.$bank[5]."\r\n";
			$msg_payment .= '口座名義 : '.$bank[6]."\r\n";
			$msg_payment .= '支払期限 : '.substr( $args['exp'], 0, 4 ).'年'.substr( $args['exp'], 4, 2 ).'月'.substr( $args['exp'], 6, 2 )."日\r\n";
			$msg_payment .= "\r\n".usces_mail_line( 2, $entry['customer']['mailaddress1'] )."\r\n";//--------------------
		}
		return $msg_payment;
	}

	/**
	 * 購入完了メッセージ
	 * @fook   usces_filter_completion_settlement_message
	 * @param  $html, $usces_entries
	 * @return string $html
	 */
	public function completion_settlement_message( $html, $usces_entries ) {
		global $usces;

		if( isset( $_REQUEST['acting'] ) && 'jpayment_conv' == $_REQUEST['acting'] ) {
			$html .= '<div id="status_table"><h5>'.$this->acting_formal_name.' コンビニペーパーレス決済</h5>'."\n";
			$html .= '<table>'."\n";
			$html .= '<tr><th>決済番号</th><td>'.esc_html( $_GET['gid'] )."</td></tr>\n";
			$html .= '<tr><th>決済金額</th><td>'.esc_html( $_GET['ta'] )."</td></tr>\n";
			$html .= '<tr><th>お支払先</th><td>'.esc_html( usces_get_conv_name( $_GET['cv'] ) )."</td></tr>\n";
			$html .= '<tr><th>コンビニ受付番号</th><td>'.esc_html( $_GET['no'] )."</td></tr>\n";
			if( $_GET['cv'] != '030' ) {//ファミリーマート以外
				$html .= '<tr><th>コンビニ受付番号情報URL</th><td><a href="'.esc_html( $_GET['cu'] ).'" target="_blank">'.esc_html( $_GET['cu'] )."</a></td></tr>\n";
			}
			$html .= '</table>'."\n";
			$html .= '<p>「お支払いのご案内」は、'.esc_html( $usces_entries['customer']['mailaddress1'] ).'　宛にメールさせていただいております。</p>'."\n";
			$html .= "</div>\n";
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
}
