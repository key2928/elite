<?php
require 'config.php';

// Redirect already-logged-in users to their panel
if (isset($_SESSION['usuario_tipo'])) {
    if ($_SESSION['usuario_tipo'] === 'admin') { header('Location: admin.php'); exit; }
    if (in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) { header('Location: treinador.php'); exit; }
    header('Location: index.php'); exit;
}

$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome']  ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefone  = trim($_POST['telefone'] ?? '');
    $senha     = $_POST['senha']   ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // Optional medical / fitness fields
    $data_nascimento     = ($_POST['data_nascimento']     ?? '') ?: null;
    $tipo_sanguineo      = ($_POST['tipo_sanguineo']      ?? '') ?: null;
    $peso                = ($_POST['peso']                ?? '') ?: null;
    $altura              = ($_POST['altura']              ?? '') ?: null;
    $nivel_experiencia   = in_array($_POST['nivel_experiencia'] ?? '', ['iniciante','intermediario','avancado'])
                           ? $_POST['nivel_experiencia'] : 'iniciante';
    $objetivo_treino     = trim($_POST['objetivo_treino']    ?? '') ?: null;
    $restricoes_medicas  = trim($_POST['restricoes_medicas'] ?? '') ?: null;
    $doencas_cronicas    = trim($_POST['doencas_cronicas']   ?? '') ?: null;
    $medicamentos_uso    = trim($_POST['medicamentos_uso']   ?? '') ?: null;
    $historico_lesoes    = trim($_POST['historico_lesoes']   ?? '') ?: null;
    $emergencia_nome     = trim($_POST['emergencia_nome']    ?? '') ?: null;
    $emergencia_telefone = trim($_POST['emergencia_telefone'] ?? '') ?: null;

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Nome, e-mail e senha são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Endereço de e-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios
                (nome, email, telefone, senha, tipo,
                 data_nascimento, tipo_sanguineo, peso, altura,
                 nivel_experiencia, objetivo_treino, restricoes_medicas,
                 doencas_cronicas, medicamentos_uso, historico_lesoes,
                 emergencia_nome, emergencia_telefone)
                VALUES (?,?,?,?,'aluno',?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $nome, $email, $telefone, $senha_hash,
                    $data_nascimento, $tipo_sanguineo, $peso, $altura,
                    $nivel_experiencia, $objetivo_treino, $restricoes_medicas,
                    $doencas_cronicas, $medicamentos_uso, $historico_lesoes,
                    $emergencia_nome, $emergencia_telefone
                ]);
            $sucesso = 'Matrícula realizada com sucesso! Aguarde a ativação do seu acesso pelo treinador.';
        } catch (PDOException $ex) {
            // SQLSTATE 23000 = Integrity constraint violation (e.g. duplicate e-mail)
            if ($ex->getCode() === '23000') {
                $erro = 'Este e-mail já está cadastrado. Tente outro ou faça login.';
            } else {
                $erro = 'Erro ao realizar o cadastro. Tente novamente mais tarde.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls — Cadastro</title>
    <meta name="description" content="Academia Elite Thai Girls — Muay Thai & Fitness">
    <meta name="theme-color" content="#d62bc5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elite Thai Girls">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <link rel="apple-touch-icon" href="icon.svg">
    <style>
        *{box-sizing:border-box;font-family:'Segoe UI',sans-serif}
        body{background:#0a0010;margin:0;display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100vh;min-height:100dvh;padding:20px 20px 40px;position:relative;overflow-x:hidden}
        #stars-canvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none}
        .brand{text-align:center;margin-bottom:28px;position:relative;z-index:1}
        .brand-name{color:#fff;font-size:28px;font-weight:800;text-transform:uppercase;letter-spacing:3px;line-height:1.2;text-shadow:0 0 20px rgba(123,44,191,.6)}
        .brand-name span{color:#c084fc}
        .box{background:rgba(15,5,25,.85);backdrop-filter:blur(10px);padding:36px 30px;border-radius:20px;width:100%;max-width:480px;text-align:center;box-shadow:0 0 40px rgba(123,44,191,.3),0 0 80px rgba(123,44,191,.1);border:1px solid rgba(123,44,191,.25);position:relative;z-index:1}
        h1{color:#c084fc;font-size:22px;text-transform:uppercase;font-style:italic;margin-bottom:24px;letter-spacing:2px;text-shadow:0 0 20px rgba(192,132,252,.4)}
        .alerta-erro{background:#4a1525;color:#ffb3c6;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #ff4d6d;text-align:left}
        .alerta-ok{background:#0d3320;color:#6effa8;padding:10px;border-radius:8px;margin-bottom:20px;font-weight:bold;border:1px solid #2ecc71;text-align:left}
        label{display:block;color:#b0b0c0;font-size:13px;text-align:left;margin-bottom:4px;margin-top:14px}
        input[type="text"],input[type="email"],input[type="password"],input[type="date"],input[type="number"],select,textarea{
            width:100%;padding:13px 15px;border-radius:10px;border:1px solid rgba(123,44,191,.3);
            background:rgba(20,10,35,.8);color:#fff;font-size:15px;transition:border-color .3s;outline:none
        }
        input:focus,select:focus,textarea:focus{border-color:#7b2cbf;box-shadow:0 0 8px rgba(123,44,191,.3)}
        ::placeholder{color:#555}
        textarea{resize:vertical;min-height:72px}
        select option{background:#1a0a2e}
        .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        details{border:1px solid rgba(123,44,191,.2);border-radius:12px;margin-top:18px;overflow:hidden}
        summary{cursor:pointer;font-size:13px;font-weight:700;color:#c084fc;list-style:none;padding:12px 15px;background:rgba(123,44,191,.07);display:flex;align-items:center;gap:8px;user-select:none}
        summary::-webkit-details-marker{display:none}
        summary::after{content:'▼';margin-left:auto;font-size:10px;transition:transform .3s;color:#888}
        details[open] summary::after{transform:rotate(180deg)}
        details > div{padding:0 15px 15px}
        .btn{width:100%;padding:15px;border:none;border-radius:10px;background:#6d28d9;color:#fff;font-size:16px;font-weight:bold;cursor:pointer;text-transform:uppercase;transition:all .3s;box-shadow:0 4px 20px rgba(109,40,217,.4);margin-top:22px}
        .btn:hover{background:#7c3aed;box-shadow:0 6px 28px rgba(109,40,217,.6);transform:translateY(-1px)}
        .link-login{margin-top:18px;color:#888;font-size:14px}
        .link-login a{color:#c084fc;text-decoration:none;font-weight:700}
        .link-login a:hover{text-decoration:underline}
        .secao{color:#c084fc;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:20px 0 6px;text-align:left;border-bottom:1px solid rgba(123,44,191,.2);padding-bottom:6px}
    </style>
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="brand">
    <div class="brand-name">Elite Thai <span>Girls</span></div>
</div>
<div class="box">
    <?php if ($erro): ?><div class="alerta-erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($sucesso): ?><div class="alerta-ok"><?= e($sucesso) ?></div><?php endif; ?>

    <?php if (!$sucesso): ?>
    <h1>Nova Matrícula</h1>
    <form method="POST" novalidate>

        <div class="secao">Dados de Acesso</div>

        <label for="nome">Nome Completo *</label>
        <input type="text" id="nome" name="nome" placeholder="Seu nome completo" required
               value="<?= e($_POST['nome'] ?? '') ?>">

        <label for="email">E-mail *</label>
        <input type="email" id="email" name="email" placeholder="seu@email.com" required
               value="<?= e($_POST['email'] ?? '') ?>">

        <label for="telefone">WhatsApp</label>
        <input type="text" id="telefone" name="telefone" placeholder="Ex: 64999999999"
               value="<?= e($_POST['telefone'] ?? '') ?>">

        <label for="senha">Senha *</label>
        <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required>

        <label for="confirmar">Confirmar Senha *</label>
        <input type="password" id="confirmar" name="confirmar" placeholder="Repita a senha" required>

        <div class="secao">Objetivo e Nível</div>

        <label for="nivel_experiencia">Nível de Experiência</label>
        <select id="nivel_experiencia" name="nivel_experiencia">
            <option value="iniciante"<?= ($_POST['nivel_experiencia'] ?? 'iniciante') === 'iniciante' ? ' selected' : '' ?>>Iniciante</option>
            <option value="intermediario"<?= ($_POST['nivel_experiencia'] ?? '') === 'intermediario' ? ' selected' : '' ?>>Intermediário</option>
            <option value="avancado"<?= ($_POST['nivel_experiencia'] ?? '') === 'avancado' ? ' selected' : '' ?>>Avançado</option>
        </select>

        <label for="objetivo_treino">Objetivo do Treino</label>
        <textarea id="objetivo_treino" name="objetivo_treino"
                  placeholder="Ex: Perder peso, aprender Muay Thai, autodefesa..."><?= e($_POST['objetivo_treino'] ?? '') ?></textarea>

        <details>
            <summary>🩺 Ficha Médica <span style="color:#888;font-weight:400">(opcional)</span></summary>
            <div>
                <div class="row2">
                    <div>
                        <label for="data_nascimento">Data de Nascimento</label>
                        <input type="date" id="data_nascimento" name="data_nascimento"
                               value="<?= e($_POST['data_nascimento'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="tipo_sanguineo">Tipo Sanguíneo</label>
                        <select id="tipo_sanguineo" name="tipo_sanguineo">
                            <option value="">Selecione</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                                <option value="<?= $ts ?>"<?= ($_POST['tipo_sanguineo'] ?? '') === $ts ? ' selected' : '' ?>><?= $ts ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row2">
                    <div>
                        <label for="peso">Peso (kg)</label>
                        <input type="number" id="peso" name="peso" step="0.1" min="0" placeholder="Ex: 65.5"
                               value="<?= e($_POST['peso'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="altura">Altura (cm)</label>
                        <input type="number" id="altura" name="altura" step="0.1" min="0" placeholder="Ex: 168"
                               value="<?= e($_POST['altura'] ?? '') ?>">
                    </div>
                </div>
                <label for="restricoes_medicas">Restrições Médicas</label>
                <textarea id="restricoes_medicas" name="restricoes_medicas"
                          placeholder="Ex: Dor lombar, asma leve..."><?= e($_POST['restricoes_medicas'] ?? '') ?></textarea>
                <label for="doencas_cronicas">Doenças Crônicas</label>
                <textarea id="doencas_cronicas" name="doencas_cronicas"
                          placeholder="Ex: Hipertensão, diabetes..."><?= e($_POST['doencas_cronicas'] ?? '') ?></textarea>
                <label for="medicamentos_uso">Medicamentos em Uso</label>
                <textarea id="medicamentos_uso" name="medicamentos_uso"
                          placeholder="Ex: Losartana, metformina..."><?= e($_POST['medicamentos_uso'] ?? '') ?></textarea>
                <label for="historico_lesoes">Histórico de Lesões</label>
                <textarea id="historico_lesoes" name="historico_lesoes"
                          placeholder="Ex: Fratura no tornozelo em 2022..."><?= e($_POST['historico_lesoes'] ?? '') ?></textarea>
            </div>
        </details>

        <details>
            <summary>📞 Contato de Emergência <span style="color:#888;font-weight:400">(opcional)</span></summary>
            <div>
                <div class="row2">
                    <div>
                        <label for="emergencia_nome">Nome do Contato</label>
                        <input type="text" id="emergencia_nome" name="emergencia_nome"
                               placeholder="Nome completo"
                               value="<?= e($_POST['emergencia_nome'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="emergencia_telefone">Telefone</label>
                        <input type="text" id="emergencia_telefone" name="emergencia_telefone"
                               placeholder="Ex: 64999999999"
                               value="<?= e($_POST['emergencia_telefone'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </details>

        <button type="submit" class="btn">Criar Conta</button>
    </form>
    <?php else: ?>
        <a href="login.php" class="btn" style="display:block;text-decoration:none;text-align:center;margin-top:10px">Ir para o Login</a>
    <?php endif; ?>

    <div class="link-login">Já tem conta? <a href="login.php">Entrar</a></div>
</div>
<script>
(function(){
    var canvas = document.getElementById('stars-canvas');
    var ctx = canvas.getContext('2d');
    var stars = [];
    var nebula = [];
    function resize(){
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    function init(){
        resize();
        stars = [];
        nebula = [];
        for(var i=0;i<200;i++){
            stars.push({
                x: Math.random()*canvas.width,
                y: Math.random()*canvas.height,
                r: Math.random()*1.5+0.2,
                alpha: Math.random()*0.8+0.2,
                speed: Math.random()*0.3+0.05,
                twinkle: Math.random()*0.02+0.005,
                dir: Math.random()>0.5?1:-1
            });
        }
        for(var j=0;j<4;j++){
            nebula.push({
                x: Math.random()*canvas.width,
                y: Math.random()*canvas.height,
                r: 80+Math.random()*180,
                alpha: 0.03+Math.random()*0.05
            });
        }
    }
    function draw(){
        ctx.clearRect(0,0,canvas.width,canvas.height);
        nebula.forEach(function(n){
            var g = ctx.createRadialGradient(n.x,n.y,0,n.x,n.y,n.r);
            g.addColorStop(0,'rgba(109,40,217,'+n.alpha+')');
            g.addColorStop(0.5,'rgba(76,10,130,'+n.alpha*0.5+')');
            g.addColorStop(1,'rgba(0,0,0,0)');
            ctx.beginPath();
            ctx.arc(n.x,n.y,n.r,0,Math.PI*2);
            ctx.fillStyle=g;
            ctx.fill();
        });
        stars.forEach(function(s){
            s.alpha += s.twinkle * s.dir;
            if(s.alpha>1){s.alpha=1;s.dir=-1;}
            if(s.alpha<0.1){s.alpha=0.1;s.dir=1;}
            ctx.beginPath();
            ctx.arc(s.x,s.y,s.r,0,Math.PI*2);
            ctx.fillStyle='rgba(255,255,255,'+s.alpha+')';
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    window.addEventListener('resize',function(){init();});
    init();
    draw();
})();
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>
