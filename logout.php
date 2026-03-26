<?php
// logout.php - Script para limpar a sessão e os cookies com segurança
session_start();

// 1. Destrói todas as informações da sessão atual no servidor (Desloga)
session_destroy();

// 2. Apaga os cookies de "Manter conectado" no navegador do usuário
// Fazemos isso definindo o tempo de validade para o passado (time() - 3600)
setcookie('konex_user', '', time() - 3600, "/");
setcookie('elite_thai_user', '', time() - 3600, "/");

// 3. Redireciona o usuário de volta para a tela de login correta
header("Location: login.php");
exit;
?>