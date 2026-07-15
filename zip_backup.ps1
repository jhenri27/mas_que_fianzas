$src = 'c:\wamp64\www\PLATAFORMA_INTEGRADA\backups'
$dest = 'C:\Users\jhenr\OneDrive\Backup_Plataforma_Integrada.zip'
if (Test-Path $dest) { Remove-Item $dest }
Compress-Archive -Path $src -DestinationPath $dest
