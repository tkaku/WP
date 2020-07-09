<?php
$html = '<div id="error-page">

<h2>' . __( 'Your order has not been completed', 'usces' ) . '</h2>
<div class="post">';

$html .= uesces_get_error_settlement( 'return' );

$html .= '</div>

</div>';
?>