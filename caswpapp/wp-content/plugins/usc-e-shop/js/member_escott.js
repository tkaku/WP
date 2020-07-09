jQuery( document ).ready( function( $ ) {
	$( document ).on( "change", "#expmm", function( e ) {
		$( "input[name='expmm']" ).val( $( "#expmm option:selected" ).val() );
	});
	$( document ).on( "change", "#expyy", function( e ) {
		$( "input[name='expyy']" ).val( $( "#expyy option:selected" ).val() );
	});
});
jQuery( function( $ ) {
	memberEScott = {
		getToken: function() {
			if( $( "#register" ).val() != undefined ) {
				var check = true;
				if( "" == $( "#cardno" ).val() ) {
					check = false;
				}
				if( undefined == $( "#expyy" ).get( 0 ) || undefined == $( "#expmm" ).get( 0 ) ) {
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
			}

			var cardno = $( "#cardno" ).val();
			var expyy = $( "#expyy option:selected" ).val();
			if( "" != expyy ) {
				expyy = expyy.substr( -2, 2 );
			}
			var expmm = $( "#expmm option:selected" ).val();
			var seccd = ( $( "#seccd" ).val() != undefined ) ? $( "#seccd" ).val() : "";

			SpsvApi.spsvCreateToken( cardno, expyy, expmm, seccd, "", "", "", "", "" );
		}
	};

	$( document ).on( "click", "#card-register", function( e ) {
		if( $( "#token" ).val() != undefined ) {
			memberEScott.getToken();
		} else {
			$( "#member-card-info" ).submit();
		}
	});

	$( document ).on( "click", "#card-update", function( e ) {
		if( $( "#token" ).val() != undefined ) {
			if( "" == $( "#cardno" ).val() ) {
				$( "#member-card-info" ).submit();
			} else {
				memberEScott.getToken();
			}
		} else {
			$( "#member-card-info" ).submit();
		}
	});
});

function setToken( token, card ) {
	if( token ) {
		document.getElementById( "token" ).value = token;
		document.getElementById( "member-card-info" ).submit();
	}
}
