<?php

$member_compmode = $this->page;
$html = '<div id="memberpages">

<div class="post">';

$html .= '<div class="header_explanation">';
$header = '';
$html .= apply_filters('usces_filter_memberverifying_page_header', $header);
$html .= '</div>';

$remaining_hour = USCES_VERIFY_MEMBERS_EMAIL::get_remaining_hour();

$html .= '<h2>' . __('Completed sending an authentication email', 'usces') . '</h2>';
$html .= '<p>' . __('An authentication email has been sent. Click on the approval URL in the email to complete membership registration.', 'usces') . '</p>';
$html .= '<p>' . sprintf( __( "This registration will be invalidated if authentication is not completed within %d hours.", 'usces' ), $remaining_hour ) . '</p>';


$html .= '<div class="footer_explanation">';
$footer = '';
$html .= apply_filters('usces_filter_memberverifying_page_footer', $footer);
$html .= '</div>';

$html .= '<p><a href="' . USCES_MEMBER_URL . '">' . __('to vist membership information page', 'usces') . '</a></p>'."\n";
$html .= '<div class="send"><a href="' . home_url() . '" class="back_to_top_button">' . __('Back to the top page.', 'usces') . '</a></div>'."\n";

	
$html .= '</div>

	</div>';
?>
