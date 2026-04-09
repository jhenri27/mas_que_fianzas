<?php
$src = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\Iconos\\FIANZAS-ADUANALES-1024x1024.ico';
$dst = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\frontend\\assets\\mqf-favicon.ico';
$srcPng = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\Iconos\\FIANZAS-ADUANALES-1024x1024.png';
$dstPng = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\frontend\\assets\\mqf-favicon.png';
copy($src, $dst) ? print("ICO copiado OK\n") : print("Error copiando ICO\n");
copy($srcPng, $dstPng) ? print("PNG copiado OK\n") : print("Error copiando PNG\n");
