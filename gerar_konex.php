<?php
// gerar_konex.php - Configura o acesso Master Admin (uso único)
require 'config.php';

$login     = 'konex';
$senha_pura = 'konex2026';

// Criptografa a senha para o sistema aceitar no login
$senha_criptografada = password_hash($senha_pura, PASSWORD_DEFAULT);

try {
    // Cria ou atualiza o admin master garantindo login único
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->execute([$login]);

    if ($check->fetch()) {
        $pdo->prepare("UPDATE usuarios SET senha = ?, tipo = 'admin', nome = 'Gestão Konex' WHERE email = ?")
            ->execute([$senha_criptografada, $login]);
        $acao = "Senha do Admin atualizada";
    } else {
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('Gestão Konex', ?, ?, 'admin')")
            ->execute([$login, $senha_criptografada]);
        $acao = "Conta Admin criada com sucesso";
    }

    echo "<div style='background:#0f0a1a; color:#fff; padding:30px; font-family:sans-serif; text-align:center; border-radius:16px; max-width:420px; margin:60px auto; border:1px solid #D946EF;'>";
    echo "<h2 style='color:#D946EF;'>✅ $acao!</h2>";
    echo "<p style='color:#a0a0c0;'>Credenciais do acesso master:</p>";
    echo "<p>Login: <b style='color:#F97316;'>$login</b></p>";
    echo "<p>Senha: <b style='color:#F97316;'>$senha_pura</b></p>";
    echo "<br><a href='login.php' style='background:linear-gradient(90deg,#D946EF,#7C3AED); color:#fff; padding:12px 28px; text-decoration:none; border-radius:10px; font-weight:bold;'>Ir para o Login</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Erro ao configurar: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>