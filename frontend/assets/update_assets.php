<?php
$source_logo = 'C:\\Users\\jhenr\\.gemini\\antigravity\\brain\\75ae0083-177b-4060-a2a4-b6f2dbd37d45\\media__1775032202240.png';
$dest_logo = 'c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_mqf.png';

if (file_exists($source_logo)) {
    copy($source_logo, $dest_logo);
    $b64 = base64_encode(file_get_contents($dest_logo));
    file_put_contents('c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/logo_b64.js', 'window.LOGO_MQF_B64 = "data:image/png;base64,' . $b64 . '";');
    echo "Logo updated! \n";
} else {
    echo "Logo not found! \n";
}

$source_icon = 'C:\\Users\\jhenr\\Downloads\\FIANZAS-ADUANALES-1024x1024.ico';
$dest_icon = 'c:/wamp64/www/PLATAFORMA_INTEGRADA/frontend/assets/favicon.ico';

if (file_exists($source_icon)) {
    copy($source_icon, $dest_icon);
    echo "Icon updated! \n";
} else {
    echo "Icon not found! \n";
}
