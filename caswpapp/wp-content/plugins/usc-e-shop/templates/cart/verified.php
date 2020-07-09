<?php

$member_compmode = $this->page;
$html = '<div id="memberpages">

<div class="post">';

$html .= '<div class="header_explanation">';
$header = '';
$html .= apply_filters('usces_filter_memberverified_page_header', $header);
$html .= '</div>';


$html .= '<h2>' . __('Member registration complete', 'usces') . '</h2>';
$html .= '<p>' . __('Member registration is complete. Click "Continue shopping" to continue shopping.', 'usces') . '</p>';


$html .= '<div class="footer_explanation">';
$footer = '';
$html .= apply_filters('usces_filter_memberverified_page_footer', $footer);
$html .= '</div>';

$html .= '<form id="purchase_form" action="' . USCES_CART_URL . '" method="post" onkeydown="if (event.keyCode == 13) {return false;}">
	<input name="backDelivery" type="submit" class="to_deliveryinfo_button" value="'.__('Continue shopping', 'usces').'" />
	</form>&nbsp;&nbsp;';
$html .= '<div class="send"><a href="' . home_url() . '" class="back_to_top_button">' . __('Back to the top page.', 'usces') . '</a></div>'."\n";

	
$html .= '</div>

	</div>';
