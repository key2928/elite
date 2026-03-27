<?php
require 'config.php';

$erro = '';

function redirecionarPainel($tipo) {
    if ($tipo === 'admin') { header('Location: admin.php'); exit; }
    if (in_array($tipo, ['treinador', 'professor', 'instrutor'])) { header('Location: treinador.php'); exit; }
    header('Location: index.php'); exit;
}

// Já logado na sessão
if (isset($_SESSION['usuario_tipo'])) {
    redirecionarPainel($_SESSION['usuario_tipo']);
}

// Cookie "manter conectado"
if (isset($_COOKIE['konex_user'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([(int)$_COOKIE['konex_user']]);
    $usuario = $stmt->fetch();
    if ($usuario) {
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        redirecionarPainel($usuario['tipo']);
    } else {
        setcookie('konex_user', '', time() - 3600, '/');
    }
}

// Formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($login !== '' && $senha !== '') {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$login]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id']   = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            if (!empty($_POST['lembrar'])) {
                setcookie('konex_user', $usuario['id'], time() + 86400 * 30, '/');
            }
            redirecionarPainel($usuario['tipo']);
        } else {
            $erro = 'Login ou senha incorretos!';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls</title>
    <meta name="description" content="Academia Elite Thai Girls — Muay Thai & Fitness">
    <meta name="theme-color" content="#d62bc5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elite Thai Girls">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Elite Thai Girls">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <link rel="apple-touch-icon" href="icon.svg">
    <style>
        *{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
        body{background:#0b0710;margin:0;display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100vh;min-height:100dvh;padding:20px}
        .brand{text-align:center;margin-bottom:28px}
        .brand-logo{width:80px;height:80px;margin:0 auto 12px;display:block}
        .brand-name{color:#fff;font-size:22px;font-weight:800;text-transform:uppercase;letter-spacing:2px;line-height:1.2}
        .brand-name span{background:linear-gradient(90deg,#d62bc5,#7b2cbf);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .login-box{background:#15111b;padding:36px 30px;border-radius:20px;width:100%;max-width:400px;text-align:center;box-shadow:0 0 30px rgba(214,43,197,.2)}
        h1{color:#d62bc5;font-size:24px;text-transform:uppercase;font-style:italic;margin-bottom:24px;letter-spacing:2px}
        .erro{background:#4a1525;color:#ffb3c6;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #ff4d6d}
        input[type="text"],input[type="password"]{width:100%;padding:15px;border-radius:10px;border:none;background:#eef2f5;margin-bottom:15px;font-size:16px}
        .check{display:flex;align-items:center;margin-bottom:25px;color:#a0a0a0;font-size:14px}
        .check input{margin-right:10px;width:18px;height:18px;cursor:pointer}
        .btn{width:100%;padding:15px;border:none;border-radius:10px;background:linear-gradient(90deg,#d62bc5,#7b2cbf);color:#fff;font-size:16px;font-weight:bold;cursor:pointer;text-transform:uppercase;transition:opacity .3s}
        .btn:hover{opacity:.8}
        @media(min-width:768px){
            body{background:radial-gradient(ellipse at 50% 30%,rgba(214,43,197,.15) 0%,#0b0710 70%)}
            .login-box{box-shadow:0 20px 60px rgba(214,43,197,.25)}
        }
    </style>
</head>
<body>
<div class="brand">
    <img src="icon.svg" alt="Elite Thai Girls" class="brand-logo">
    <div class="brand-name"><span>Elite Thai</span> Girls</div>
</div>
<div class="login-box">
    <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
    <h1>ENTRAR</h1>
    <form method="POST">
        <input type="text" name="email" placeholder="Seu E-mail ou Login" required>
        <input type="password" name="senha" placeholder="••••••••" required>
        <div class="check">
            <input type="checkbox" name="lembrar" id="lembrar">
            <label for="lembrar">Manter conectado</label>
        </div>
        <button type="submit" class="btn">ENTRAR NO TREINO</button>
    </form>
</div>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>