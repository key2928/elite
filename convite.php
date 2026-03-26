<?php
// convite.php - Landing Page de Captura VIP
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

$aluna_nome = "Uma amiga";
$aluna_id = 0;
$msg_sucesso = '';

if (isset($_GET['ref'])) {
    $aluna_id = (int)$_GET['ref'];
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'aluna'");
    $stmt->execute([$aluna_id]);
    $aluna = $stmt->fetch();
    if ($aluna) { $aluna_nome = explode(' ', $aluna['nome'])[0]; }
}

$horarios = [];
try { $horarios = $pdo->query("SELECT * FROM horarios_treino ORDER BY id ASC")->fetchAll(); } 
catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'resgatar_convite') {
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $id_indicou = (int)$_POST['aluna_id_indicou'];

    try {
        $pdo->prepare("INSERT INTO leads_indicacoes (aluna_id_indicou, nome_convidada, telefone_convidada) VALUES (?, ?, ?)")->execute([$id_indicou, $nome, $telefone]);
        $msg_sucesso = "Passe Livre garantido! 🎉 Em breve a nossa treinadora vai chamar no WhatsApp para agendar o dia da sua aula VIP. Prepare-se para suar a camisa!";
    } catch(Exception $e) {
        $msg_sucesso = "Ocorreu um erro ao gerar seu passe. Tente novamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Convite VIP - Elite Thai</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --bg-fundo: #09060f; --bg-card: #140d1c; --pink-grad: linear-gradient(90deg, #d62bc5, #7b2cbf); --green-grad: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); --texto-claro: #f8f9fa; --borda: #2a1b3d; }
        * { box-sizing: border-box; }
        body { background-color: var(--bg-fundo); color: var(--texto-claro); font-family: 'Poppins', sans-serif; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; -webkit-font-smoothing: antialiased; }
        .convite-container { background-color: var(--bg-card); padding: 40px 30px; border-radius: 20px; width: 100%; max-width: 450px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.8); border: 1px solid var(--borda); position: relative; overflow: hidden; }
        .convite-container::before { content: ''; position: absolute; top: -50px; left: 50%; transform: translateX(-50%); width: 150px; height: 150px; background: var(--pink-grad); filter: blur(70px); opacity: 0.4; z-index: 0;}
        .conteudo { position: relative; z-index: 1; }
        h1 { color: #fff; font-size: 24px; text-transform: uppercase; font-weight: 800; margin-bottom: 10px; letter-spacing: 1px; }
        .destaque-nome { color: #d62bc5; }
        p { color: #b5a8c9; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }
        .ticket-vip { background: rgba(214, 43, 197, 0.1); border: 2px dashed #d62bc5; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
        .ticket-vip i { font-size: 40px; color: #d62bc5; margin-bottom: 10px; filter: drop-shadow(0 0 10px rgba(214, 43, 197, 0.5)); }
        .ticket-vip h2 { margin: 0; color: #fff; font-size: 18px; text-transform: uppercase; font-weight: 800; letter-spacing: 2px; }
        .horarios-container { margin-top: 20px; border-top: 1px dashed rgba(214, 43, 197, 0.4); padding-top: 20px; text-align: left;}
        .horarios-titulo { font-size: 12px; color: #b5a8c9; text-transform: uppercase; margin-bottom: 12px; font-weight: 600; text-align: center; letter-spacing: 1px;}
        .horario-item { display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.4); padding: 10px 15px; border-radius: 10px; margin-bottom: 8px; font-size: 13px; border: 1px solid rgba(214, 43, 197, 0.2);}
        .h-dia { color: #fff; font-weight: 600; }
        .h-hora { color: #d62bc5; font-weight: 800; }
        
        /* Estilo do Endereço */
        .endereco-box { margin-top: 20px; background: rgba(0,0,0,0.4); padding: 15px; border-radius: 10px; border: 1px solid rgba(214, 43, 197, 0.2); font-size: 13px; color: #b5a8c9; line-height: 1.5; text-align: center; }
        .endereco-box strong { color: #fff; display: block; margin-bottom: 5px; font-size: 14px;}

        input { width: 100%; padding: 16px; border-radius: 12px; border: 1px solid var(--borda); background-color: #050308; color: #fff; margin-bottom: 15px; font-size: 15px; font-family: 'Poppins', sans-serif; transition: 0.3s;}
        input:focus { outline: none; border-color: #d62bc5; box-shadow: 0 0 10px rgba(214, 43, 197, 0.2); }
        .btn-resgatar { width: 100%; padding: 18px; border: none; border-radius: 12px; background: var(--green-grad); color: #000; font-size: 16px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 10px 20px rgba(56, 239, 125, 0.2); display: flex; justify-content: center; align-items: center; gap: 10px;}
        .btn-resgatar:hover { transform: translateY(-3px); filter: brightness(1.1); box-shadow: 0 15px 25px rgba(56, 239, 125, 0.4);}
        .msg-sucesso { background: rgba(56, 239, 125, 0.1); border: 1px solid #38ef7d; color: #38ef7d; padding: 20px; border-radius: 15px; font-weight: 600; font-size: 15px; line-height: 1.5;}
    </style>
</head>
<body>

    <div class="convite-container">
        <div class="conteudo">
            <?php if($msg_sucesso): ?>
                <i class="fas fa-check-circle" style="font-size: 60px; color: #38ef7d; margin-bottom: 20px; filter: drop-shadow(0 0 15px rgba(56, 239, 125, 0.5));"></i>
                <h1 style="color: #38ef7d;">Tudo Certo!</h1>
                <div class="msg-sucesso"><?= $msg_sucesso ?></div>
            <?php else: ?>
                <h1>A <span class="destaque-nome"><?= htmlspecialchars($aluna_nome) ?></span> convidou você!</h1>
                <p>Treinar acompanhada é muito melhor. Você acaba de ganhar um passe livre para experimentar um treino de Muay Thai na <strong>Elite Thai</strong>!</p>
                
                <div class="ticket-vip">
                    <i class="fas fa-ticket-alt"></i>
                    <h2>1 Aula VIP Gratuita</h2>
                    
                    <?php if(count($horarios) > 0): ?>
                        <div class="horarios-container">
                            <div class="horarios-titulo"><i class="fas fa-clock"></i> Nossos Horários</div>
                            <?php foreach($horarios as $h): ?>
                                <div class="horario-item">
                                    <span class="h-dia"><?= htmlspecialchars($h['dia_semana']) ?></span>
                                    <span class="h-hora"><?= htmlspecialchars($h['horario']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="horarios-container" style="border-top: none; padding-top: 5px;">
                        <div class="horarios-titulo"><i class="fas fa-map-marker-alt"></i> Onde Estamos</div>
                        <div class="endereco-box">
                            <strong>Elite Thai</strong>
                            Av. Santos Dumont, 392<br>
                            Ao lado do açougue Fangolar<br>
                            Itumbiara - GO
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="acao" value="resgatar_convite">
                    <input type="hidden" name="aluna_id_indicou" value="<?= $aluna_id ?>">
                    
                    <input type="text" name="nome" placeholder="Seu Nome Completo" required>
                    <input type="tel" name="telefone" placeholder="Seu WhatsApp (Ex: 64999999999)" required>
                    
                    <button type="submit" class="btn-resgatar">
                        <i class="fab fa-whatsapp" style="font-size: 20px;"></i> Quero Minha Aula VIP
                    </button>
                </form>
                <p style="font-size: 11px; margin-top: 15px; margin-bottom: 0; opacity: 0.5;">Ao resgatar, você aceita que a academia entre em contato via WhatsApp.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>