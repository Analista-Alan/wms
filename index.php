<?php
// Este arquivo existe apenas para quem apontou o DocumentRoot/pasta do XAMPP
// direto para "wms-geagro" em vez de "wms-geagro/public". Ele redireciona
// para dentro da pasta pública correta.
header('Location: public/login.php');
exit;
