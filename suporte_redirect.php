<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario']) || !is_array($_SESSION['usuario']) || count($_SESSION['usuario']) < 4) {
    header("Location: login.php");
    exit();
}

// Redirecionar baseado no tipo de usuário
if ($_SESSION['usuario'][3] == 1) {
    // Usuário é admin - redirecionar para painel admin
    header("Location: suporte_admin.php");
} else {
    // Usuário normal - redirecionar para suporte normal
    header("Location: suporte.php");
}
exit();
?>
