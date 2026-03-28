<?php
require 'config.php';

$aluna_nome  = '';
$aluna_id    = 0;
$link_invalido = false;
$msg_sucesso = '';
$msg_erro    = '';

if (isset($_GET['ref'])) {
    $aluna_id = (int)$_GET['ref'];
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND tipo = 'aluno'");
    $stmt->execute([$aluna_id]);
    $aluna_ref = $stmt->fetch();
    if ($aluna_ref) {
        $aluna_nome = explode(' ', $aluna_ref['nome'])[0];
    } else {
        $link_invalido = true;
    }
} else {
    $link_invalido = true;
}

$horarios = [];
try { $horarios = $pdo->query("SELECT * FROM horarios_treino ORDER BY id ASC")->fetchAll(); } catch (Exception $e) {}

if (!$link_invalido && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'resgatar_convite') {
    $nome       = trim($_POST['nome'] ?? '');
    $telefone   = preg_replace('/\D/', '', trim($_POST['telefone'] ?? ''));
    $id_indicou = (int)($_POST['aluna_id_indicou'] ?? 0);

    if ($nome === '' || strlen($telefone) < 10 || strlen($telefone) > 11) {
        $msg_erro = 'Preencha seu nome e WhatsApp corretamente (somente números, 10 ou 11 dígitos).';
    } elseif ($id_indicou <= 0) {
        $msg_erro = 'Link de convite inválido.';
    } else {
        try {
            $stmtDup = $pdo->prepare("SELECT id FROM leads_indicacoes WHERE telefone_convidada = ?");
            $stmtDup->execute([$telefone]);
            if ($stmtDup->fetch()) {
                $msg_sucesso = 'Passe Livre garantido! 🎉 Em breve a nossa treinadora vai chamar no WhatsApp para agendar o dia da sua aula VIP.';
            } else {
                $pdo->prepare("INSERT INTO leads_indicacoes (aluna_id_indicou, nome_convidada, telefone_convidada) VALUES (?, ?, ?)")
                    ->execute([$id_indicou, $nome, $telefone]);
                $msg_sucesso = 'Passe Livre garantido! 🎉 Em breve a nossa treinadora vai chamar no WhatsApp para agendar o dia da sua aula VIP.';
            }
        } catch (Exception $e) {
            $msg_erro = 'Ocorreu um erro. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Passe Livre VIP - Elite Thai Girls</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#09060f;--card:#140d1c;--pink:linear-gradient(90deg,#d62bc5,#7b2cbf);--green:linear-gradient(135deg,#11998e,#38ef7d);--txt:#f8f9fa;--borda:#2a1b3d}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--bg);color:var(--txt);font-family:'Poppins',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
        .logo{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:3px;color:#d62bc5;margin-bottom:20px;opacity:.7}
        .box{background:var(--card);padding:36px 28px;border-radius:24px;width:100%;max-width:440px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.9);border:1px solid var(--borda);position:relative;overflow:hidden}
        .box::before{content:'';position:absolute;top:-60px;left:50%;transform:translateX(-50%);width:200px;height:200px;background:var(--pink);filter:blur(80px);opacity:.3;z-index:0;pointer-events:none}
        .conteudo{position:relative;z-index:1}
        .badge-vip{display:inline-flex;align-items:center;gap:6px;background:rgba(214,43,197,.15);border:1px solid rgba(214,43,197,.4);color:#d62bc5;padding:6px 14px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin-bottom:18px}
        h1{font-size:22px;text-transform:uppercase;font-weight:800;margin-bottom:10px;letter-spacing:.5px;line-height:1.3}
        .destaque{background:var(--pink);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .subtitulo{color:#b5a8c9;font-size:13px;line-height:1.6;margin-bottom:24px}
        .ticket{background:linear-gradient(135deg,rgba(214,43,197,.12),rgba(123,44,191,.08));border:2px dashed rgba(214,43,197,.5);padding:22px 20px;border-radius:18px;margin-bottom:28px;position:relative}
        .ticket-icon{width:56px;height:56px;background:rgba(214,43,197,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:26px;color:#d62bc5;filter:drop-shadow(0 0 12px rgba(214,43,197,.5))}
        .ticket-titulo{font-size:17px;text-transform:uppercase;font-weight:800;letter-spacing:2px;margin-bottom:4px}
        .ticket-sub{font-size:11px;color:#b5a8c9;letter-spacing:1px;text-transform:uppercase}
        .divider{border:none;border-top:1px dashed rgba(214,43,197,.3);margin:18px 0}
        .hor-titulo{font-size:11px;color:#b5a8c9;text-transform:uppercase;margin-bottom:10px;font-weight:700;letter-spacing:1px}
        .hor-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:left}
        .hor-item{background:rgba(0,0,0,.4);padding:10px 12px;border-radius:10px;font-size:12px;border:1px solid rgba(214,43,197,.15)}
        .hor-dia{color:#b5a8c9;font-size:11px;margin-bottom:2px}
        .hor-hora{color:#d62bc5;font-weight:800;font-size:14px}
        .endereco{background:rgba(0,0,0,.35);padding:14px;border-radius:12px;border:1px solid rgba(214,43,197,.15);font-size:12px;color:#b5a8c9;line-height:1.6;text-align:center}
        .endereco strong{color:#fff;display:block;margin-bottom:4px;font-size:13px}
        .form-group{margin-bottom:14px;text-align:left}
        .form-label{font-size:11px;color:#b5a8c9;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block}
        input[type=text],input[type=tel]{width:100%;padding:15px 16px;border-radius:12px;border:1px solid var(--borda);background:#050308;color:#fff;font-size:14px;font-family:'Poppins',sans-serif;transition:.3s}
        input[type=text]:focus,input[type=tel]:focus{outline:none;border-color:#d62bc5;box-shadow:0 0 0 3px rgba(214,43,197,.15)}
        .btn-resgatar{width:100%;padding:17px;border:none;border-radius:14px;background:var(--green);color:#000;font-size:15px;font-weight:800;cursor:pointer;transition:.3s;text-transform:uppercase;letter-spacing:1px;box-shadow:0 10px 25px rgba(56,239,125,.25);display:flex;justify-content:center;align-items:center;gap:10px;margin-top:6px}
        .btn-resgatar:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(56,239,125,.35)}
        .btn-resgatar:active{transform:translateY(0)}
        .msg-ok{background:rgba(56,239,125,.08);border:1px solid rgba(56,239,125,.4);color:#38ef7d;padding:20px;border-radius:15px;font-weight:600;font-size:14px;line-height:1.6}
        .msg-erro{background:rgba(255,68,68,.08);border:1px solid rgba(255,68,68,.4);color:#ff6b6b;padding:14px 16px;border-radius:12px;font-size:13px;margin-bottom:16px;font-weight:600}
        .aviso-legal{font-size:10px;margin-top:14px;opacity:.45;line-height:1.5}
        .invalido-icone{font-size:50px;margin-bottom:16px;opacity:.4}
        .invalido-titulo{font-size:20px;font-weight:800;text-transform:uppercase;margin-bottom:10px;color:#b5a8c9}
        .invalido-sub{font-size:13px;color:#7a6e8a;line-height:1.6}
    </style>
</head>
<body>
<div class="logo"><i class="fas fa-fist-raised"></i> Elite Thai Girls</div>
<div class="box">
    <div class="conteudo">
        <?php if ($link_invalido): ?>
            <div class="invalido-icone">🔗</div>
            <div class="invalido-titulo">Link Inválido</div>
            <p class="invalido-sub">Este link de convite não é válido ou expirou.<br>Peça um novo link diretamente para a sua amiga.</p>

        <?php elseif ($msg_sucesso): ?>
            <i class="fas fa-check-circle" style="font-size:58px;color:#38ef7d;margin-bottom:18px;display:block;filter:drop-shadow(0 0 16px rgba(56,239,125,.5))"></i>
            <h1 style="color:#38ef7d;margin-bottom:12px">Passe Garantido!</h1>
            <div class="msg-ok"><?= e($msg_sucesso) ?></div>
            <p class="aviso-legal" style="opacity:.6;margin-top:20px">📍 Elite Thai Girls — Av. Santos Dumont, 392 · Itumbiara - GO</p>

        <?php else: ?>
            <div class="badge-vip"><i class="fas fa-star"></i> Convite Exclusivo</div>
            <h1><span class="destaque"><?= e($aluna_nome) ?></span> te convidou!</h1>
            <p class="subtitulo">Treinar acompanhado é muito melhor! Você ganhou um <strong style="color:#fff">Passe Livre VIP</strong> para experimentar o Muay Thai na <strong style="color:#fff">Elite Thai Girls</strong>.</p>

            <div class="ticket">
                <div class="ticket-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="ticket-titulo">1 Aula VIP Gratuita</div>
                <div class="ticket-sub">Para você usar quando quiser</div>

                <?php if (count($horarios) > 0): ?>
                <hr class="divider">
                <div class="hor-titulo"><i class="fas fa-clock"></i> Nossos Horários</div>
                <div class="hor-grid">
                    <?php foreach ($horarios as $h): ?>
                        <div class="hor-item">
                            <div class="hor-dia"><?= e($h['dia_semana']) ?></div>
                            <div class="hor-hora"><?= e($h['horario']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <hr class="divider">
                <div class="hor-titulo"><i class="fas fa-map-marker-alt"></i> Onde Estamos</div>
                <div class="endereco">
                    <strong>Elite Thai Girls</strong>
                    Av. Santos Dumont, 392 · Itumbiara - GO<br>
                    <span style="font-size:11px">Ao lado do Açougue Fangolar</span>
                </div>
            </div>

            <?php if ($msg_erro): ?>
                <div class="msg-erro"><i class="fas fa-exclamation-circle"></i> <?= e($msg_erro) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="acao" value="resgatar_convite">
                <input type="hidden" name="aluna_id_indicou" value="<?= $aluna_id ?>">
                <div class="form-group">
                    <label class="form-label" for="inp-nome"><i class="fas fa-user"></i> Seu Nome Completo</label>
                    <input type="text" id="inp-nome" name="nome" placeholder="Ex: Maria Silva" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="inp-tel"><i class="fab fa-whatsapp"></i> Seu WhatsApp</label>
                    <input type="tel" id="inp-tel" name="telefone" placeholder="Ex: 64 9 9999-9999" required>
                </div>
                <button type="submit" class="btn-resgatar">
                    <i class="fab fa-whatsapp" style="font-size:20px"></i> Resgatar Meu Passe VIP
                </button>
            </form>
            <p class="aviso-legal">Ao resgatar, você autoriza a academia a entrar em contato via WhatsApp para agendamento.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>