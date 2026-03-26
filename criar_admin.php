<?php
$db_info = ['host' => 'localhost', 'db' => 'iubsit15_academia', 'user' => 'iubsit15_academiuser', 'pass' => '@Vanvan123'];
try {
    $pdo = new PDO("mysql:host={$db_info['host']};dbname={$db_info['db']};charset=utf8", $db_info['user'], $db_info['pass']);
} catch (PDOException $e) { die("Erro de Conexão: " . $e->getMessage()); }

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = 'van@van';
    $senha_criptografada = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    
    $check = $pdo->prepare("SELECT id FROM usuarios WHERE login = ?");
    $check->execute([$login]);
    
    if ($check->fetch()) {
        $pdo->prepare("UPDATE usuarios SET senha = ?, nivel_acesso = 'admin' WHERE login = ?")->execute([$senha_criptografada, $login]);
        $msg = "Senha do Admin atualizada com sucesso!";
    } else {
        $pdo->prepare("INSERT INTO usuarios (nome, login, senha, nivel_acesso) VALUES ('Vanessa', ?, ?, 'admin')")->execute([$login, $senha_criptografada]);
        $msg = "Conta Admin criada com sucesso!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerador Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f0a1a] text-white flex items-center justify-center h-screen">
    <div class="bg-[#1a1429] p-8 rounded-3xl border border-[#D946EF] text-center w-96 shadow-[0_0_20px_rgba(217,70,239,0.3)]">
        <h1 class="text-2xl font-black italic text-[#D946EF] mb-6">Master Admin</h1>
        <?php if($msg): ?><p class="bg-[#10B981]/20 text-[#10B981] p-3 rounded-xl mb-4 text-xs font-bold"><?php echo $msg; ?></p><?php endif; ?>
        <form method="POST" class="space-y-4 text-left">
            <div>
                <label class="text-[10px] font-black uppercase text-slate-400">Login Fixo</label>
                <input type="text" value="konex" disabled class="w-full p-4 bg-black/50 border border-white/10 rounded-xl font-bold text-slate-500 cursor-not-allowed">
            </div>
            <div>
                <label class="text-[10px] font-black uppercase text-slate-400">Nova Senha</label>
                <input type="password" name="senha" required class="w-full p-4 bg-white/5 border border-white/10 rounded-xl font-bold outline-none focus:border-[#D946EF]">
            </div>
            <button class="w-full bg-gradient-to-r from-[#D946EF] to-[#7C3AED] text-white font-black py-4 rounded-xl uppercase shadow-lg hover:scale-105 transition-transform">Salvar Senha</button>
            <a href="index.php" class="block text-center text-[10px] font-black uppercase text-slate-400 mt-4 hover:text-white">Ir para o Login</a>
        </form>
    </div>
</body>
</html>