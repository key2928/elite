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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elite Thai</title>
    <style>
        *{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
        body{background:#0b0710;margin:0;display:flex;justify-content:center;align-items:center;height:100vh}
        .login-box{background:#15111b;padding:40px;border-radius:20px;width:100%;max-width:400px;text-align:center;box-shadow:0 0 20px rgba(0,0,0,.5)}
        h1{color:#d62bc5;font-size:28px;text-transform:uppercase;font-style:italic;margin-bottom:30px;letter-spacing:2px}
        .erro{background:#4a1525;color:#ffb3c6;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #ff4d6d}
        input[type="text"],input[type="password"]{width:100%;padding:15px;border-radius:10px;border:none;background:#eef2f5;margin-bottom:15px;font-size:16px}
        .check{display:flex;align-items:center;margin-bottom:25px;color:#a0a0a0;font-size:14px}
        .check input{margin-right:10px;width:18px;height:18px;cursor:pointer}
        .btn{width:100%;padding:15px;border:none;border-radius:10px;background:linear-gradient(90deg,#d62bc5,#7b2cbf);color:#fff;font-size:16px;font-weight:bold;cursor:pointer;text-transform:uppercase;transition:opacity .3s}
        .btn:hover{opacity:.8}
    </style>
</head>
<body>
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
</body>
</html>