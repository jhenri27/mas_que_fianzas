<?php
// Copiar el ICO como PNG para uso como img en el sidebar
$srcIco = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\Iconos\\FIANZAS-ICONO-1024x1024.ico';
$dstIco = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\frontend\\assets\\mqf-logo-sidebar.ico';

if (copy($srcIco, $dstIco)) {
    echo "ICO copiado OK\n";
} else {
    echo "Error copiando ICO\n";
}

// Intentar también copiar la versión PNG si existiera
$srcPng = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\Iconos\\FIANZAS-ICONO-1024x1024.png';
$dstPng = 'C:\\wamp64\\www\\PLATAFORMA_INTEGRADA\\frontend\\assets\\mqf-logo-sidebar.png';
if (file_exists($srcPng)) {
    copy($srcPng, $dstPng);
    echo "PNG copiado OK\n";
} else {
    echo "PNG no encontrado, usando ICO\n";
}
