<?php
// login.php - Tela de Entrada do Sistema
session_start();
require 'config.php';

$erro = '';

// Função inteligente para mandar cada um pro seu painel
function redirecionarPainel($tipo) {
    if ($tipo === 'admin') { header("Location: admin.php"); exit; }
    elseif (in_array($tipo, ['treinador', 'professor', 'instrutor'])) { header("Location: treinador.php"); exit; }
    else { header("Location: index.php"); exit; } // Aluno(a) cai no index!
}

// 1. Se JÁ ESTÁ logado na sessão atual
if (isset($_SESSION['usuario_tipo'])) {
    redirecionarPainel($_SESSION['usuario_tipo']);
}

// 2. Tenta logar pelo Cookie (Manter conectado)
if (isset($_COOKIE['konex_user']) && !isset($_SESSION['usuario_tipo'])) {
    $id_cookie = $_COOKIE['konex_user'];
    
    // Procura o usuário no banco
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$id_cookie]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        // Deu certo! Recria a sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];
        redirecionarPainel($usuario['tipo']);
    } else {
        // CORREÇÃO DO LOOP: Se o usuário não existe mais, apaga o cookie fantasma!
        setcookie('konex_user', '', time() - 3600, "/");
        setcookie('elite_thai_user', '', time() - 3600, "/"); 
    }
}

// 3. Processa o formulário quando clica em ENTRAR
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['email']);
    $senha = trim($_POST['senha']);
    $lembrar = isset($_POST['lembrar']) ? true : false;

    if (!empty($login) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $login);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            if ($lembrar) {
                setcookie('konex_user', $usuario['id'], time() + (86400 * 30), "/");
            }

            redirecionarPainel($usuario['tipo']);
        } else {
            $erro = "Login ou senha incorretos!";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elite Thai / Konex</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #0b0710; margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-container { background-color: #15111b; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        h1 { color: #d62bc5; font-size: 28px; text-transform: uppercase; font-style: italic; margin-bottom: 30px; letter-spacing: 2px; }
        .erro-msg { background-color: #4a1525; color: #ffb3c6; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border: 1px solid #ff4d6d; }
        input[type="text"], input[type="password"] { width: 100%; padding: 15px; border-radius: 10px; border: none; background-color: #eef2f5; margin-bottom: 15px; font-size: 16px; font-weight: 500;}
        .checkbox-group { display: flex; align-items: center; justify-content: flex-start; margin-bottom: 25px; color: #a0a0a0; font-size: 14px; }
        .checkbox-group input { margin-right: 10px; width: 18px; height: 18px; cursor: pointer; }
        .btn-entrar { width: 100%; padding: 15px; border: none; border-radius: 10px; background: linear-gradient(90deg, #d62bc5, #7b2cbf); color: white; font-size: 16px; font-weight: bold; cursor: pointer; margin-bottom: 25px; transition: opacity 0.3s; text-transform: uppercase;}
        .btn-entrar:hover { opacity: 0.8; }
        .links { display: flex; flex-direction: column; gap: 15px; }
        .links a { text-decoration: none; font-size: 13px; font-weight: bold; }
        .link-criar { color: #d62bc5; font-style: italic; font-size: 15px; text-transform: uppercase; }
        .link-esqueci { color: #555; }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if($erro): ?><div class="erro-msg"><?= $erro ?></div><?php endif; ?>
        <h1>ENTRAR</h1>
        <form method="POST">
            <input type="text" name="email" placeholder="Seu E-mail ou Login" required>
            <input type="password" name="senha" placeholder="••••••••" required>
            <div class="checkbox-group">
                <input type="checkbox" name="lembrar" id="lembrar">
                <label for="lembrar">Manter conectado</label>
            </div>
            <button type="submit" class="btn-entrar">ENTRAR NO TREINO</button>
        </form>
        <div class="links">
            <a href="#" class="link-criar">CRIAR MINHA CONTA</a>
            <a href="#" class="link-esqueci">ESQUECI MINHA SENHA</a>
        </div>
    </div>
</body>
</html>