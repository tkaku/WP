<?php
$agree_member = ( isset($this->options['agree_member']) && !empty($this->options['agree_member']) ) ? $this->options['agree_member']: 'deactivate';
if( !empty($this->options['member_page_data']) ){
	$member_page_datas = stripslashes_deep($this->options['member_page_data']);
}else{
	$member_page_datas = array();
}
?>

<script type="text/javascript">
jQuery(function($){

	if( $.fn.jquery < "1.10" ) {
		var $tabs = $( '#uscestabs_member' ).tabs({
			cookie: {
				// store cookie for a day, without, it would be a session cookie
				expires: 1
			}
		});
	} else {
		$( "#uscestabs_member" ).tabs({
			active: ( $.cookie( "uscestabs_member" ) ) ? $.cookie( "uscestabs_member" ) : 0
			, activate: function( event, ui ){
				$.cookie( "uscestabs_member", $( this ).tabs( "option", "active" ) );
			}
		});
	}

	customField = {
		settings: {
			url: uscesL10n.requestFile,
			type: 'POST',
			cache: false
		},

		//** Custom Member **
		addMember: function() {
			var key = $( "#newcsmbkey" ).val();
			var name = $( "#newcsmbname" ).val();
			var value = $( "#newcsmbvalue" ).val();
			var means = $( "#newcsmbmeans" ).val();
			var essential = ( $( "input#newcsmbessential" ).attr( "checked" ) ) ? '1' : '0';
			var position = $( "#newcsmbposition" ).val();
			var mes = '';
			if( '' == key || !checkCode( key ) ) {
				mes += '<p>'+uscesL10n.message[21]+'</p>';
			}
			if( '' == name ) {
				mes += '<p>'+uscesL10n.message[22]+'</p>';
			}
			if( '' == value && ( 0 == means || 1 == means || 3 == means || 4 == means ) ) {
				mes += '<p>'+uscesL10n.message[2]+'</p>';
			} else if( '' != value && ( 2 == means || 5 == means ) ) {
				mes += '<p>'+uscesL10n.message[3]+'</p>';
			}
			if( '' != mes ) {
				mes = '<div class="error">'+mes+'</div>';
				$( "#ajax-response-csmb" ).html( mes );
				return false;
			}

			$( "#newcsmb_loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );

			var s = customField.settings;
			s.data = {
				action: 'custom_field_ajax',
				field: 'member',
				add: 1,
				newkey: key,
				newname: name,
				newvalue: value,
				newmeans: means,
				newessential: essential,
				newposition: position,
				wc_nonce: '<?php echo wp_create_nonce( 'custom_field_ajax' ); ?>'
			}
			s.dataType = 'json';
			s.success = function( data, dataType ) {
				$( "#ajax-response-csmb" ).html( '' );
				$( "#newcsmb_loading" ).html( '' );
				if( 'OK' == data.status ) {
					if( 0 < data.dupkey ) {
						$( "#ajax-response-csmb" ).html( '<div class="error"><p>'+uscesL10n.message[23]+'</p></div>' );
					} else {
						if( data.list.length > 1 ) $( "table#csmb-list-table" ).removeAttr( "style" );
						$( "tbody#csmb-list" ).html( data.list );
						$( "#csmb-" + key ).css( { 'background-color': '#FF4' } );
						$( "#csmb-" + key ).animate( { 'background-color': '#FFFFEE' }, 2000 );
						$( "#newcsmbkey" ).val( "" );
						$( "#newcsmbname" ).val( "" );
						$( "#newcsmbvalue" ).val( "" );
						$( "#newcsmbmeans" ).val( 0 );
						$( "#newcsmbessential" ).attr( { checked: false } );
					}
				} else {
					if( 0 < data.msg.length ) $( "#ajax-response-csmb" ).html( data.msg );
				}
			};
			s.error = function( msg ) {
				$( "#ajax-response-csmb" ).html( msg );
				$( "#newcsmb_loading" ).html( '' );
			};
			$.ajax( s );
			return false;
		},

		updMember: function( key ) {
			var name = $( ':input[name="csmb['+key+'][name]"]' ).val();
			var value = $( ':input[name="csmb['+key+'][value]"]' ).val();
			var means = $( ':input[name="csmb['+key+'][means]"]' ).val();
			var essential = ( $( ':input[name="csmb['+key+'][essential]"]' ).attr( "checked" ) ) ? '1' : '0';
			var position = $( ':input[name="csmb['+key+'][position]"]' ).val();
			var mes = '';
			if( '' == key || !checkCode( key ) ) {
				mes += '<p>'+uscesL10n.message[21]+'</p>';
			}
			if( '' == name ) {
				mes += '<p>'+uscesL10n.message[22]+'</p>';
			}
			if( '' == value && ( 0 == means || 1 == means || 3 == means || 4 == means ) ) {
				mes += '<p>'+uscesL10n.message[2]+'</p>';
			} else if( '' != value && ( 2 == means || 5 == means ) ) {
				mes += '<p>'+uscesL10n.message[3]+'</p>';
			}
			if( '' != mes ) {
				mes = '<div class="error">'+mes+'</div>';
				$( "#ajax-response-csmb" ).html( mes );
				return false;
			}

			$( "#csmb_loading-" + key ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );

			var s = customField.settings;
			s.data = {
				action: 'custom_field_ajax',
				field: 'member',
				update: 1,
				key: key,
				name: name,
				value: value,
				means: means,
				essential: essential,
				position: position,
				wc_nonce: '<?php echo wp_create_nonce( 'custom_field_ajax' ); ?>'
			}
			s.dataType = 'json';
			s.success = function( data, dataType ) {
				$( "#ajax-response-csmb" ).html( '' );
				$( "#csmb_loading-" + key ).html( '' );
				if( 'OK' == data.status ) {
					$( "tbody#csmb-list" ).html( data.list );
					$( "#csmb-" + key ).css( { 'background-color': '#FF4' } );
					$( "#csmb-" + key ).animate( { 'background-color': '#FFFFEE' }, 2000 );
				} else {
					if( 0 < data.msg.length ) $( "#ajax-response-csmb" ).html( data.msg );
				}
			};
			s.error = function( msg ) {
				$( "#ajax-response-csmb" ).html( msg );
				$( "#csmb_loading-" + key ).html( '' );
			};
			$.ajax( s );
			return false;
		},

		delMember: function( key ) {
			$( "#csmb-" + key ).css( { 'background-color': '#F00' } );
			$( "#csmb-" + key ).animate( { 'background-color': '#FFFFEE' }, 1000 );
			var s = customField.settings;
			s.data = {
				action: 'custom_field_ajax',
				field: 'member',
				delete: 1,
				key: key,
				wc_nonce: '<?php echo wp_create_nonce( 'custom_field_ajax' ); ?>'
			}
			s.dataType = 'json';
			s.success = function( data, dataType ) {
				$( "#ajax-response-csmb" ).html( '' );
				if( 'OK' == data.status ) {
					$( "tbody#csmb-list" ).html( data.list );
					if( data.list.length < 1 ) $( "table#csmb-list-table" ).attr( "style", "display: none" );
				} else {
					if( 0 < data.msg.length ) $( "#ajax-response-csmb" ).html( data.msg );
				}
			};
			s.error = function( msg ) {
				$( "#ajax-response-csmb" ).html( msg );
			};
			$.ajax( s );
			return false;
		}
	};

});
</script>
<div class="wrap">
<div class="usces_admin">
<h1>Welcart Shop <?php _e('Member Page Setting','usces'); ?></h1>
<?php usces_admin_action_status(); ?>
<form action="" method="post" name="option_form" id="option_form">
<input name="usces_option_update" type="submit" class="button button-primary" value="<?php _e('change decision','usces'); ?>" />
<div id="poststuff" class="metabox-holder">
<div class="uscestabs" id="uscestabs_member">
	<ul>
		<li><a href="#member_page_setting_0"><?php _e('Member Page Setting','usces'); ?></a></li>
		<li><a href="#member_page_setting_1"><?php _e('Explanation in Member page','usces'); ?></a></li>
		<li><a href="#member_page_setting_2"><?php _e('Custom member field','usces'); ?></a></li>
	</ul>

<div id="member_page_setting_0">
	<div class="postbox">
		<h3 class="hndle"><span><?php _e('Member Page Setting','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_member_page_setting');"><?php _e('(Explain)','usces'); ?></a></h3>
		<div class="inside">
			<table class="form_table">
				<tr>
					<th><a style="cursor:pointer;" onclick="toggleVisibility('ex_agree_member');"><?php _e('Membership Agreement','usces'); ?></a></th>
					<td width="10"><input name="agree_member" id="agree_member_activate" value="activate" type="radio"<?php if('activate' === $agree_member) echo ' checked="checked"' ?>></td><td width="100"><label for="agree_member_activate"><?php _e('Seek', 'usces'); ?></label></td>
					<td width="10"><input name="agree_member" id="agree_member_deactivate" value="deactivate" type="radio"<?php if('deactivate' === $agree_member) echo ' checked="checked"' ?>></td><td width="100"><label for="agree_member_deactivate"><?php _e('Not seek', 'usces'); ?></label></td>
					<td><div id="ex_agree_member" class="explanation"><?php _e('Whether or not seek consent to the membership agreement at the time of member registration', 'usces'); ?></div></td>
				</tr>
			</table>
			<table class="form_table">
				<tr>
				    <th><?php _e('Explanation of membership','usces'); ?></th>
				    <td><textarea name="agree_member_exp" id="agree_member_exp" class="textarea_fld"><?php echo (isset($member_page_datas['agree_member_exp']) ? $member_page_datas['agree_member_exp'] : ''); ?></textarea></td>
					<td>&nbsp;</td>
				</tr>
				<tr>
				    <th><?php _e('Text of membership','usces'); ?></th>
				    <td><textarea name="agree_member_cont" id="agree_member_cont" class="textarea_fld"><?php echo (isset($member_page_datas['agree_member_cont']) ? $member_page_datas['agree_member_cont'] : ''); ?></textarea></td>
					<td>&nbsp;</td>
				</tr>
			</table>
			<hr size="1" color="#CCCCCC" />
			<div id="ex_member_page_setting" class="explanation"><?php _e('Make the various settings in the member page.','usces'); ?></div>
		</div>
	</div>
</div><!--member_page_setting_0-->

<div id="member_page_setting_1">
<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in a Login page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_login_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[login]" id="header[login]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['login']) ? $member_page_datas['header']['login'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[login]" id="footer[login]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['login']) ? $member_page_datas['footer']['login'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_login_page" class="explanation"><?php _e('You can set additional explanation to insert in a login page.','usces'); ?></div>
</div>
</div><!--postbox-->

<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in a New Member page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_newmember_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[newmember]" id="header[newmember]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['newmember']) ? $member_page_datas['header']['newmember'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[newmember]" id="footer[newmember]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['newmember']) ? $member_page_datas['footer']['newmember'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_newmember_page" class="explanation"><?php _e('You can set additional explanation to insert in a new member page.','usces'); ?></div>
</div>
</div><!--postbox-->

<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in New Password page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_newpass_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[newpass]" id="header[newpass]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['newpass']) ? $member_page_datas['header']['newpass'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[newpass]" id="footer[newpass]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['newpass']) ? $member_page_datas['footer']['newpass'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_newpass_page" class="explanation"><?php _e('You can set additional explanation to insert in a new password page.','usces'); ?></div>
</div>
</div><!--postbox-->

<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in a Change Password page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_changepass_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[changepass]" id="header[changepass]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['changepass']) ? $member_page_datas['header']['changepass'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[changepass]" id="footer[changepass]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['changepass']) ? $member_page_datas['footer']['changepass'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_changepass_page" class="explanation"><?php _e('You can set additional explanation to insert in a change password page.','usces'); ?></div>
</div>
</div><!--postbox-->

<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in a Member Information page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_memberinfo_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[memberinfo]" id="header[memberinfo]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['memberinfo']) ? $member_page_datas['header']['memberinfo'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[memberinfo]" id="footer[memberinfo]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['memberinfo']) ? $member_page_datas['footer']['memberinfo'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_memberinfo_page" class="explanation"><?php _e('You can set additional explanation to insert in a member information page.','usces'); ?></div>
</div>
</div><!--postbox-->

<div class="postbox">
<h3 class="hndle"><span><?php _e('Explanation in a Completion page','usces'); ?></span><a style="cursor:pointer;" onclick="toggleVisibility('ex_completion_page');"><?php _e('(Explain)','usces'); ?></a></h3>
<div class="inside">
<table class="form_table">
	<tr>
	    <th><?php _e('header','usces'); ?></th>
	    <td><textarea name="header[completion]" id="header[completion]" class="textarea_fld"><?php echo (isset($member_page_datas['header']['completion']) ? $member_page_datas['header']['completion'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
	<tr>
	    <th><?php _e('footer','usces'); ?></th>
	    <td><textarea name="footer[completion]" id="footer[completion]" class="textarea_fld"><?php echo (isset($member_page_datas['footer']['completion']) ? $member_page_datas['footer']['completion'] : ''); ?></textarea></td>
		<td>&nbsp;</td>
	</tr>
</table>
<hr size="1" color="#CCCCCC" />
<div id="ex_completion_page" class="explanation"><?php _e('You can set additional explanation to insert in a completion page.','usces'); ?></div>
</div>
</div><!--postbox-->
</div><!--member_page_setting_1-->
<?php
	$csmb_meta = usces_has_custom_field_meta('member');
	$csmb_display = (empty($csmb_meta)) ? ' style="display: none;"' : '';
	$csmb_means = get_option('usces_custom_member_select');
	$csmb_meansoption = '';
	foreach($csmb_means as $meankey => $meanvalue) {
		$csmb_meansoption .= '<option value="'.esc_attr($meankey).'">'.esc_html($meanvalue)."</option>\n";
	}
	$positions = get_option('usces_custom_field_position_select');
	$positionsoption = '';
	foreach($positions as $poskey => $posvalue) {
		$positionsoption .= '<option value="'.esc_attr($poskey).'">'.esc_html($posvalue)."</option>\n";
	}
?>
<div id="member_page_setting_2">
	<div class="postbox">
	<h3 class="hndle"><span><?php _e('Custom member field', 'usces'); ?><a style="cursor:pointer;" onclick="toggleVisibility('ex_custom_member');"><?php _e('(Explain)','usces'); ?></a></span></h3>
	<div class="inside">
	<div id="postoptcustomstuff">
	<table id="csmb-list-table" class="list"<?php echo $csmb_display; ?>>
		<thead>
		<tr>
		<th class="left"><?php _e('key name','usces') ?></th>
		<th rowspan="2"><?php _e('selected amount','usces') ?></th>
		</tr>
		<tr>
		<th class="left"><?php _e('field name','usces') ?></th>
		</tr>
		</thead>
		<tbody id="csmb-list">
<?php
	if(is_array($csmb_meta)) {
		foreach($csmb_meta as $key => $entry) 
			echo _list_custom_member_meta_row($key, $entry);
	}
?>
		</tbody>
	</table>
	<div id="ajax-response-csmb"></div>
	<p><strong><?php _e('Add a new custom member field','usces') ?> : </strong></p>
	<table id="newmeta2">
		<thead>
		<tr>
		<th class="left"><?php _e('key name','usces') ?></th>
		<th rowspan="2"><?php _e('selected amount','usces') ?></th>
		</tr>
		<tr>
		<th class="left"><?php _e('field name','usces') ?></th>
		</tr>
		</thead>

		<tbody>
		<tr>
		<td class='item-opt-key'>
		<input type="text" name="newcsmbkey" id="newcsmbkey" class="optname" value="" />
		<input type="text" name="newcsmbname" id="newcsmbname" class="optname" value="" />
		<div class="optcheck"><select name='newcsmbmeans' id='newcsmbmeans'><?php echo $csmb_meansoption; ?></select>
		<input type="checkbox" name="newcsmbessential" id="newcsmbessential" /><label for='newcsmbessential'><?php _e('Required','usces') ?></label>
		<select name='newcsmbposition' id='newcsmbposition'><?php echo $positionsoption; ?></select></div>
		</td>
		<td class='item-opt-value'><textarea name="newcsmbvalue" id="newcsmbvalue" class='optvalue'></textarea></td>
		</tr>

		<tr><td colspan="2" class="submit">
		<input type="button" class="button" name="add_csmb" id="add_csmb" value="<?php _e('Add custom member field','usces') ?>" onclick="customField.addMember();" />
		<div id="newcsmb_loading" class="meta_submit_loading"></div>
		</td></tr>
		</tbody>
	</table>

	<hr size="1" color="#CCCCCC" />
	<div id="ex_custom_member" class="explanation"><?php _e("You can add an arbitrary field to the member information page.", 'usces'); ?></div>
	</div>
	</div>
	</div><!--postbox-->
</div><!--member_page_setting_2-->
</div><!--tabs-->


</div><!--poststuff-->
<input name="usces_option_update" type="submit" class="button button-primary" value="<?php _e('change decision','usces'); ?>" />
<?php wp_nonce_field( 'admin_member', 'wc_nonce' ); ?>
</form>
</div><!--usces_admin-->
</div><!--wrap-->