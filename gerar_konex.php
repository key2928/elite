<?php
// gerar_konex.php - Configura o acesso oficial de Admin
require 'config.php';

$login = 'konex';
$senha_pura = '123456';

// Criptografa a senha para o sistema aceitar no login
$senha_criptografada = password_hash($senha_pura, PASSWORD_DEFAULT);

try {
    // 1. Limpa os admins antigos para garantir que só o Konex seja o dono
    $pdo->query("DELETE FROM usuarios WHERE tipo = 'admin'");

    // 2. Insere o seu novo login oficial
    $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES ('Gestão Konex', :email, :senha, 'admin')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $login,
        ':senha' => $senha_criptografada
    ]);

    echo "<div style='background: #111; color: #fff; padding: 20px; font-family: sans-serif; text-align: center; border-radius: 10px; max-width: 400px; margin: 50px auto;'>";
    echo "<h2 style='color: #2ecc71;'>✅ Acesso Configurado!</h2>";
    echo "<p>Pode testar o sistema. Seus dados são:</p>";
    echo "<p>Login: <b style='color: #FF6600;'>$login</b></p>";
    echo "<p>Senha: <b style='color: #FF6600;'>$senha_pura</b></p>";
    echo "<br><a href='index.php' style='background: #FF6600; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ir para o Login</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Erro ao configurar: " . $e->getMessage();
}
?>