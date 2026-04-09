<?php
$b64 = base64_encode(file_get_contents('c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_mqf.png'));
file_put_contents('c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_b64.js', 'window.LOGO_MQF_B64 = "data:image/png;base64,' . $b64 . '";');
echo 'File created';
