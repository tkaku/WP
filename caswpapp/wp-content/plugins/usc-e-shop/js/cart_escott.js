jQuery( document ).ready( function( $ ) {
	$( 'body input[type="submit"]' ).each( function( i, elem ) {
		if( "confirm" == $( this ).attr( "name" ) ) {
			$( this ).parents( "form" ).attr( "id", "delivery-form" );
		}
	});
	var token_dialog = '<div id="escott-token-dialog">'
		+'<div id="escott-token-response"></div>'
		+'<div id="escott-token-response-loading"></div>'
		+'<div id="escott-token-form"></div>'
		+'<div class="send settlement_form_send">'
			+'<input type="button" id="escott_token_cancel" class="back_to_delivery_button" value="' + uscesL10n.escott_token_btn_cancel + '" />&nbsp;&nbsp;'
			+'<input type="button" id="escott_token_next" class="to_confirm_button" value="' + uscesL10n.escott_token_btn_next + '" />'
		+'</div>'
	+'</div>';
	$( "#delivery-form" ).after( token_dialog );
	$( "#escott-token-dialog .send" ).css( "text-align", "center" );
	$( "#escott-token-dialog" ).css( "display", "none" );
});
jQuery( function( $ ) {
	cartEScott = {
		changePayType: function( cnum ) {
			var first_c = cnum.substr( 0, 1 );
			var second_c = cnum.substr( 1, 1 );
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
		},

		openTokenDialog: function() {
			$( "#escott-token-response" ).html( "" );
			$( "#escott-token-response-loading" ).html( '<img src="' + uscesL10n.loaderurl + '" />' );

			$.ajax({
				url: uscesL10n.front_ajaxurl,
				type: "POST",
				cache: false,
				dataType: "json",
				data: {
					usces_ajax_action: "escott_token_dialog",
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				if( retVal.status == "OK" ) {
					$( "#escott-token-form" ).html( "" );
					$( "#escott-token-form" ).append( retVal.result );
					if( retVal.member == "quick" ) {
						if( $( "#cardno" ).val() != undefined ) {
							cartEScott.changePayType( $( "#cardno" ).val() );
						}
					} else {
						$( "#cardno" ).bind( "change", function() {
							cartEScott.changePayType( $( this ).val() );
						});
					}
				}
				$( "#escott-token-dialog select" ).css( "width", "auto" );
				$( "#escott-token-response-loading" ).html( "" );
			}).fail( function( retVal ) {
				$( "#escott-token-response-loading" ).html( "" );
			});
			return false;
		},

		cardChange: function() {
			$( "#escott-token-response" ).html( "" );
			$( "#escott-token-response-loading" ).html( '<img src="' + uscesL10n.loaderurl + '" />' );

			$.ajax({
				url: uscesL10n.front_ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					usces_ajax_action: "escott_token_dialog",
					card_change: "1",
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				if( retVal.status == "OK" ) {
					$( "#escott-token-form" ).html( "" );
					$( "#escott-token-form" ).append( retVal.result );
					//if( retVal.member == "quick" ) {
					//	cartEScott.changePayType( $( "#cardno" ).val() );
					//} else {
						$( "#cardno" ).bind( "change", function() {
							cartEScott.changePayType( $( this ).val() );
						});
					//}
				}
				$( "#escott-token-dialog select" ).css( "width", "auto" );
				$( "#escott-token-response-loading" ).html( "" );
			}).fail( function( retVal ) {
				$( "#escott-token-response-loading" ).html( "" );
			});
			return false;
		},

		getToken: function() {
			$( "#escott-token-response" ).html( "" );

			var check = true;
			if( "" == $( "#cardno" ).val() ) {
				check = false;
			}
			if( undefined == $( "#expyy" ).get(0) || undefined == $( "#expmm" ).get(0) ) {
				check = false;
			} else if( "" == $( "#expyy option:selected" ).val() || "" == $( "#expmm option:selected" ).val() ) {
				check = false;
			}
			if( $( "#seccd" ).val() != undefined ) {
				if( "" == $( "#seccd" ).val() ) {
					check = false;
				}
			}
			if( !check ) {
				alert( uscesL10n.escott_token_error_message );
				return false;
			}

			var cardno = $( "#cardno" ).val();
			var expyy = $( "#expyy option:selected" ).val();
			if( "" != expyy ) {
				expyy = expyy.substr( -2, 2 );
			}
			var expmm = $( "#expmm option:selected" ).val();
			var seccd = ( $( "#seccd" ).val() != undefined ) ? $( "#seccd" ).val() : "";
			var paytype = ( $( "#paytype" ).val() != undefined ) ? $( "#paytype" ).val() : cartEScott.getPayType();
			$( "input[name='paytype']" ).val( paytype );
			if( $( "#quick_member" ).attr( "type" ) == "hidden" ) {
				$( "input[name='quick_member']" ).val( $( "#quick_member" ).val() );
			} else if( $( "#quick_member" ).prop( "checked" ) ) {
				$( "input[name='quick_member']" ).val( $( "#quick_member" ).val() );
			}
			if( $( "#card_change" ).val() != undefined ) {
				$( "input[name='card_change']" ).val( $( "#card_change" ).val() );
			}

			SpsvApi.spsvCreateToken( cardno, expyy, expmm, seccd, "", "", "", "", "" );
		},

		getPayType: function() {
			var paytype = "01";
			if( !$( "#paytype_default" ).prop( "disabled" ) ) {
				paytype = $( "#paytype_default option:selected" ).val();
			} else if( !$( "#paytype4535" ).prop( "disabled" ) ) {
				paytype = $( "#paytype4535 option:selected" ).val();
			} else if( !$( "#paytype37" ).prop( "disabled" ) ) {
				paytype = $( "#paytype37 option:selected" ).val();
			} else if( !$( "#paytype36" ).prop( "disabled" ) ) {
				paytype = $( "#paytype36 option:selected" ).val();
			}
			return paytype;
		}
	};

	$( "#escott-token-dialog" ).dialog({
		bgiframe: true,
		autoOpen: false,
		height: "auto",
		width: "auto",
		resizable: true,
		modal: true,
		open: function() {
			$( "#escott-token-dialog" ).parent( ".ui-dialog" ).attr( "id", "escott-dialog" );
			cartEScott.openTokenDialog();
		},
		close: function() {
			$( "#escott-token-form" ).html( "" );
		}
	});

	$( document ).on( "click", ".to_confirm_button", function( e ) {
		if( $( "#payment_name_" + uscesL10n.escott_token_payment_id ).prop( "checked" ) ) {
			$( "#escott-token-dialog" ).dialog( "option", "title", uscesL10n.escott_token_dialog_title );
			$( "#escott-token-dialog" ).dialog( "open" );
			return false;
		}
	});

	$( document ).on( "click", "#escott_card_change", function( e ) {
		e.preventDefault();
		cartEScott.cardChange();
	});

	$( document ).on( "click", "#escott_token_next", function( e ) {
		if( !$( "#escott_card_change" ).length ) {
			cartEScott.getToken();
		} else {
			var paytype = ( $( "#paytype" ).val() != undefined ) ? $( "#paytype" ).val() : cartEScott.getPayType();
			$( "input[name='paytype']" ).val( paytype );
			$( "input[name='quick_member']" ).val( "add" );
			$( "input[name='card_change']" ).val( "" );
			$( "#delivery-form" ).submit();
		}
	});

	$( document ).on( "click", "#escott_token_cancel", function( e ) {
		$( "#escott-token-dialog" ).dialog( "close" );
	});
});

function setToken( token, card ) {
	if( token ) {
		document.getElementById( "token" ).value = token;
		document.getElementById( "delivery-form" ).submit();
	} else {
		document.getElementById( "escott-token-response" ).value = "";
	}
}
