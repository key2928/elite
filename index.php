<?php
require 'config.php';

if (!isset($_SESSION['usuario_tipo'])) { header('Location: login.php'); exit; }
if ($_SESSION['usuario_tipo'] === 'admin') { header('Location: admin.php'); exit; }
if (in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) { header('Location: treinador.php'); exit; }

$msg_ficha = '';
$id = (int) $_SESSION['usuario_id'];

try {
    // Atualizar ficha de saúde
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_ficha') {
        $restricoes = trim($_POST['restricoes'] ?? '');
        $pdo->prepare("UPDATE usuarios SET restricoes_medicas = ? WHERE id = ?")->execute([$restricoes, $id]);
        $msg_ficha = 'Ficha de saúde atualizada com sucesso!';
    }

    // Dados do aluno
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $aluna = $stmt->fetch();
    if (!$aluna) { header('Location: logout.php'); exit; }

    // XP e Rank
    $xp    = (int)($aluna['xp_atual'] ?? 0);
    $nivel = floor($xp / 100) + 1;
    if ($xp < 200)      { $rank = 'Aprendiz (Safira)'; }
    elseif ($xp < 500)  { $rank = 'Focado (Ametista)'; }
    elseif ($xp < 1000) { $rank = 'Guerreiro (Rubi)'; }
    elseif ($xp < 2000) { $rank = 'Imparável (Diamante)'; }
    else                 { $rank = 'Lenda Elite'; }

    $xp_prox_nivel   = $nivel * 100;
    $porcentagem_xp  = min(100, (($xp - (($nivel - 1) * 100)) / 100) * 100);

    // Fidelidade
    $treinos_totais       = (int)($aluna['treinos_concluidos'] ?? 0);
    $progresso_fidelidade = $treinos_totais % 10;
    $faltam_fidelidade    = 10 - $progresso_fidelidade;

    // Missão ativa
    $missao = false;
    try { $missao = $pdo->query("SELECT * FROM missoes_semana WHERE status = 'ativa' ORDER BY id DESC LIMIT 1")->fetch(); } catch (Exception $ex) {}

    // Plano e pagamentos (prepared statements)
    $stmt = $pdo->prepare("SELECT p.data_vencimento, pl.nome_plano, p.observacao_aluna FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id WHERE p.aluna_id = ? ORDER BY p.id DESC LIMIT 1");
    $stmt->execute([$id]);
    $plano = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT p.data_pagamento, p.valor_pago, pl.nome_plano FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id WHERE p.aluna_id = ? ORDER BY p.data_pagamento DESC LIMIT 3");
    $stmt->execute([$id]);
    $historico_pag = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM conquistas WHERE aluna_id = ? ORDER BY data_conquista DESC LIMIT 6");
    $stmt->execute([$id]);
    $medalhas = $stmt->fetchAll();

    $avisos = $pdo->query("SELECT * FROM mural_avisos ORDER BY data_publicacao DESC LIMIT 3")->fetchAll();
    $grade_horarios = $pdo->query("SELECT * FROM horarios_treino ORDER BY id ASC")->fetchAll();

    $treinadoras = [];
    try { $treinadoras = $pdo->query("SELECT nome, telefone FROM usuarios WHERE tipo = 'treinador'")->fetchAll(); } catch (Exception $ex) {}

} catch (Exception $ex) {
    die("<div style='background:#111;color:#ff4444;padding:20px'><b>Erro:</b> " . e($ex->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Minha Jornada - Elite Thai</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#09060f;--card:#140d1c;--pink:linear-gradient(90deg,#d62bc5,#7b2cbf);--glow:rgba(214,43,197,.35);--txt:#f8f9fa;--cinza:#b5a8c9;--borda:#2a1b3d}
        *{box-sizing:border-box}
        body{background:var(--bg);color:var(--txt);font-family:'Poppins',sans-serif;margin:0;padding:0}
        .app{max-width:600px;margin:0 auto;padding:20px;padding-bottom:80px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .card{background:var(--card);border-radius:20px;padding:24px;margin-bottom:20px;border:1px solid var(--borda);box-shadow:0 10px 30px rgba(0,0,0,.5);animation:fadeUp .6s ease-out forwards;opacity:0}
        .card:nth-child(1){animation-delay:.1s}.card:nth-child(2){animation-delay:.15s}.card:nth-child(3){animation-delay:.2s}.card:nth-child(4){animation-delay:.25s}.card:nth-child(5){animation-delay:.3s}.card:nth-child(6){animation-delay:.35s}.card:nth-child(7){animation-delay:.4s}.card:nth-child(8){animation-delay:.45s}.card:nth-child(9){animation-delay:.5s}.card:nth-child(10){animation-delay:.55s}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;margin-top:10px}
        .user-info h1{margin:0;font-size:26px;font-weight:800;letter-spacing:-.5px}
        .badge-rank{background:var(--pink);color:#fff;padding:6px 14px;border-radius:20px;font-weight:600;font-size:12px;display:inline-block;margin-top:8px;box-shadow:0 4px 15px var(--glow);text-transform:uppercase;letter-spacing:1px}
        .btn-sair{background:#2a1b3d;color:#ff4d4d;width:45px;height:45px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:18px;transition:.3s}
        .card-titulo{margin:0 0 20px;font-size:15px;text-transform:uppercase;font-weight:800;display:flex;align-items:center;gap:10px;letter-spacing:1px}
        .card-titulo i{background:var(--pink);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:20px}
        .luva{width:45px;height:45px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;transition:.3s}
        .luva-on{background:var(--pink);box-shadow:0 5px 15px var(--glow);color:#fff;transform:scale(1.05)}
        .luva-off{background:rgba(255,255,255,.03);border:1px dashed #555;color:#444}
        .input-vip{width:100%;padding:16px;border-radius:12px;background:rgba(0,0,0,.4);border:1px solid rgba(56,239,125,.5);color:#38ef7d;font-weight:800;font-size:12px;text-align:center;letter-spacing:.5px;outline:none;margin-bottom:0}
        .btn-copiar{background:#38ef7d;color:#000;border:none;padding:0 18px;border-radius:12px;font-weight:800;cursor:pointer;transition:.3s;box-shadow:0 5px 15px rgba(56,239,125,.3);display:flex;align-items:center}
        .btn-indicar{display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;text-decoration:none;padding:16px;border-radius:15px;font-weight:800;text-transform:uppercase;font-size:14px;width:100%;border:none;cursor:pointer;transition:.3s;box-shadow:0 10px 20px rgba(56,239,125,.3)}
        .ficha-textarea{width:100%;background:#050308;border:1px solid var(--borda);color:#fff;padding:15px;border-radius:12px;font-family:'Poppins',sans-serif;font-size:13px;resize:vertical;margin-bottom:15px;transition:.3s}
        .btn-salvar{background:var(--pink);color:#fff;border:none;width:100%;padding:14px;border-radius:10px;font-weight:800;text-transform:uppercase;font-size:13px;cursor:pointer;transition:.3s;box-shadow:0 5px 15px var(--glow)}
        .msg-ok{background:rgba(46,204,113,.1);color:#2ecc71;border:1px solid #2ecc71;padding:12px;border-radius:8px;font-size:12px;text-align:center;margin-bottom:15px;font-weight:600}
        .btn-checkin{display:block;width:100%;text-align:center;background:#2a1b3d;color:#d62bc5;padding:12px;border-radius:8px;font-size:13px;font-weight:800;text-transform:uppercase;text-decoration:none;transition:.3s;cursor:pointer;border:1px solid #d62bc5}
        .dias-semana{display:flex;justify-content:center;gap:15px;margin-bottom:25px;flex-wrap:wrap}
        .dia{width:45px;height:45px;border-radius:50%;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#555;transition:.3s;border:2px solid transparent}
        .dia.ativo{background:var(--pink);color:#fff;box-shadow:0 5px 15px var(--glow);transform:scale(1.1);border-color:#fff}
        .xp-box{background:#050308;padding:18px;border-radius:15px;border:1px solid var(--borda)}
        .xp-texto{display:flex;justify-content:space-between;font-size:13px;color:var(--cinza);margin-bottom:12px;font-weight:600;text-transform:uppercase}
        .xp-barra{background:#2a1b3d;height:12px;border-radius:10px;overflow:hidden}
        .xp-fill{background:var(--pink);height:100%;border-radius:10px;box-shadow:0 0 10px var(--glow);transition:width 1s ease-in-out}
        .btn-grupo{display:flex;align-items:center;justify-content:center;gap:10px;background:#25D366;color:#fff;text-decoration:none;padding:16px;border-radius:12px;font-weight:800;text-transform:uppercase;font-size:14px;margin-bottom:25px;transition:.3s;box-shadow:0 5px 15px rgba(37,211,102,.3)}
        .instrutora-item{display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.03);padding:16px;border-radius:12px;border:1px solid var(--borda);text-decoration:none;color:#fff;transition:.3s;margin-bottom:12px}
        .aviso{padding:16px;border-radius:12px;margin-bottom:12px;background:rgba(255,255,255,.03);border-left:4px solid #555;font-size:13px;line-height:1.6;color:var(--cinza)}
        .aviso.urgente{border-left-color:#ff4444;background:linear-gradient(90deg,rgba(255,68,68,.1),transparent)}
        .grid-medalhas{display:grid;grid-template-columns:repeat(auto-fill,minmax(85px,1fr));gap:12px}
        .medalha{background:rgba(255,255,255,.02);padding:15px 10px;text-align:center;border-radius:16px;border:1px solid var(--borda);transition:.3s}
        .medalha-icone{font-size:34px;margin-bottom:10px;filter:drop-shadow(0 4px 6px rgba(0,0,0,.5))}
        .medalha-nome{font-size:11px;font-weight:600;color:var(--cinza);line-height:1.3}
        .plano-destaque{background:linear-gradient(135deg,rgba(214,43,197,.1),transparent);padding:22px;border-radius:16px;border:1px solid rgba(214,43,197,.3);margin-bottom:25px;position:relative;overflow:hidden}
        .venc-data{font-size:26px;font-weight:800;display:block;margin-top:5px}
        .vence-ok{color:#2ecc71}.vence-perto{color:#f1c40f}.vence-passou{color:#ff4444}
        .hist-item{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px dashed var(--borda);font-size:13px;color:var(--cinza)}
        .hist-item:last-child{border-bottom:none}
    </style>
</head>
<body>
<div class="app">

    <!-- Header -->
    <div class="header card" style="padding:20px;border-radius:25px">
        <div class="user-info">
            <div style="font-size:12px;color:var(--cinza);text-transform:uppercase;font-weight:600;letter-spacing:1px">A Sua Jornada</div>
            <h1><?= e(explode(' ', $aluna['nome'])[0]) ?></h1>
            <div class="badge-rank"><i class="fas fa-gem" style="margin-right:5px"></i> <?= $rank ?></div>
        </div>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <!-- Cartão Fidelização -->
    <div class="card" style="border-left:4px solid #d62bc5;position:relative;overflow:hidden">
        <div style="position:absolute;top:-50px;right:-50px;width:100px;height:100px;background:var(--pink);filter:blur(50px);opacity:.3"></div>
        <h3 class="card-titulo"><i class="fas fa-id-card"></i> Cartão Fidelização</h3>
        <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:20px">Complete 10 treinos e troque por um prêmio exclusivo!</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center">
            <?php for ($i = 0; $i < 10; $i++): ?>
                <div class="luva <?= $i < $progresso_fidelidade ? 'luva-on' : 'luva-off' ?>"><i class="fas fa-hand-rock"></i></div>
            <?php endfor; ?>
        </div>
        <div style="text-align:center;margin-top:15px;font-size:11px;font-weight:800;color:#d62bc5;text-transform:uppercase;letter-spacing:1px">
            <?= $faltam_fidelidade == 10 ? 'Comece hoje a sua jornada!' : "Faltam {$faltam_fidelidade} treinos para o seu prêmio!" ?>
        </div>
    </div>

    <!-- Passe Livre VIP -->
    <div class="card" style="border-color:#38ef7d;background:linear-gradient(180deg,var(--card),rgba(56,239,125,.05))">
        <h3 class="card-titulo" style="color:#38ef7d"><i class="fas fa-ticket-alt" style="background:none;-webkit-text-fill-color:#38ef7d"></i> Passe Livre VIP</h3>
        <p style="font-size:13px;color:var(--cinza);margin-bottom:15px;line-height:1.5">Presenteie um(a) amigo(a) com <strong>1 Aula VIP Gratuita</strong>!</p>
        <div style="display:flex;gap:10px;margin-bottom:15px">
            <input type="text" id="linkConvite" value="https://iubsites.com/academia/convite.php?ref=<?= $id ?>" readonly class="input-vip">
            <button onclick="copiarLink(this)" class="btn-copiar"><i class="fas fa-copy"></i></button>
        </div>
        <button onclick="compartilharLink()" class="btn-indicar">
            <i class="fas fa-share-nodes"></i> Compartilhar Convite
        </button>
    </div>

    <!-- Ficha de Saúde -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-notes-medical" style="color:#d62bc5;background:none;-webkit-text-fill-color:#d62bc5"></i> Ficha de Saúde</h3>
        <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Mantenha a sua ficha médica atualizada.</p>
        <?php if ($msg_ficha): ?>
            <div class="msg-ok"><i class="fas fa-check-circle"></i> <?= e($msg_ficha) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="acao" value="atualizar_ficha">
            <textarea name="restricoes" class="ficha-textarea" rows="3" placeholder="Ex: Sinto dor lombar, asma leve..."><?= e($aluna['restricoes_medicas'] ?? '') ?></textarea>
            <button type="submit" class="btn-salvar"><i class="fas fa-save"></i> Atualizar Ficha</button>
        </form>
    </div>

    <!-- Missão da Semana -->
    <?php if ($missao): ?>
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-crosshairs"></i> Missão da Semana</h3>
        <div style="background:rgba(214,43,197,.05);border:1px dashed #d62bc5;padding:20px;border-radius:15px;text-align:center" id="card-missao">
            <i class="fas fa-fire-alt" style="font-size:34px;color:#FF8C00;margin-bottom:12px;filter:drop-shadow(0 0 10px rgba(255,140,0,.5))"></i>
            <h4 style="margin:0 0 8px;text-transform:uppercase;font-size:16px;letter-spacing:1px"><?= e($missao['titulo']) ?></h4>
            <p style="font-size:13px;color:var(--cinza);margin-bottom:20px;line-height:1.5"><?= nl2br(e($missao['descricao'])) ?></p>
            <a href="#" class="btn-checkin" onclick="concluirMissao(this);return false;" style="background:transparent;border:2px solid #d62bc5;color:#d62bc5">
                <i class="fas fa-check"></i> Aceitar e Concluir
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Evolução -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-bolt"></i> Evolução</h3>
        <p style="font-size:12px;color:var(--cinza);text-align:center;margin-top:-10px;margin-bottom:20px">Mantenha a ofensiva de treinos para ganhar mais XP!</p>
        <div class="dias-semana">
            <?php if (count($grade_horarios) > 0): ?>
                <?php foreach ($grade_horarios as $hr): ?>
                    <div class="dia ativo"><?= strtoupper(mb_substr($hr['dia_semana'], 0, 1)) ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="font-size:12px;color:#555">Sem treinos configurados.</p>
            <?php endif; ?>
        </div>
        <div class="xp-box">
            <div class="xp-texto">
                <span>Nível <?= $nivel ?></span>
                <span style="color:#d62bc5"><?= $xp ?> / <?= $xp_prox_nivel ?> XP</span>
            </div>
            <div class="xp-barra">
                <div class="xp-fill" style="width:<?= $porcentagem_xp ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Comunidade -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-users"></i> Comunidade Elite</h3>
        <a href="https://chat.whatsapp.com/JqGPW7GUNLMLIyZ8btRffy" target="_blank" class="btn-grupo">
            <i class="fab fa-whatsapp" style="font-size:22px"></i> Entrar no Grupo Oficial
        </a>
        <h4 style="font-size:13px;margin:20px 0 10px;text-transform:uppercase">Falar com o Treinador</h4>
        <?php foreach ($treinadoras as $treina): ?>
            <?php $numero = preg_replace('/\D/', '', $treina['telefone'] ?? ''); ?>
            <a href="<?= $numero ? "https://wa.me/55{$numero}" : '#' ?>" target="_blank" class="instrutora-item">
                <div><i class="fas fa-fire" style="color:#d62bc5;margin-right:10px"></i> <strong><?= e($treina['nome']) ?></strong></div>
                <i class="fab fa-whatsapp" style="color:#25D366;font-size:24px"></i>
            </a>
        <?php endforeach; ?>
        <?php if (empty($treinadoras)): ?>
            <p style="font-size:12px;color:var(--cinza)">Nenhum treinador registado.</p>
        <?php endif; ?>
    </div>

    <!-- Localização -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-map-marker-alt"></i> Onde Treinamos</h3>
        <div style="background:rgba(255,255,255,.02);padding:15px;border-radius:12px;border:1px solid var(--borda);display:flex;align-items:center;gap:15px">
            <div style="background:rgba(214,43,197,.1);color:#d62bc5;width:40px;height:40px;border-radius:10px;display:flex;justify-content:center;align-items:center;font-size:20px;flex-shrink:0">
                <i class="fas fa-map-pin"></i>
            </div>
            <div>
                <strong style="font-size:14px;display:block;margin-bottom:3px">Elite Thai</strong>
                <span style="color:var(--cinza);font-size:13px;line-height:1.4">Av. Santos Dumont, 392<br>Ao lado do açougue Fangolar<br>Itumbiara - GO</span>
            </div>
        </div>
        <a href="https://maps.google.com/?q=Av.+Santos+Dumont+392,+Itumbiara+-+GO" target="_blank" class="btn-indicar" style="margin-top:15px;padding:12px;font-size:13px;background:transparent;border:1px solid #d62bc5;color:#d62bc5;box-shadow:none">
            <i class="fas fa-location-arrow"></i> Ver no Mapa
        </a>
    </div>

    <!-- Avisos -->
    <?php if (count($avisos) > 0): ?>
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-bullhorn"></i> Avisos da Academia</h3>
        <?php foreach ($avisos as $aviso): ?>
            <div class="aviso <?= $aviso['tipo'] === 'urgente' ? 'urgente' : '' ?>">
                <strong><?= e($aviso['titulo']) ?></strong>
                <?= nl2br(e($aviso['mensagem'])) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Conquistas -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-award"></i> As Suas Conquistas</h3>
        <?php if (count($medalhas) > 0): ?>
            <div class="grid-medalhas">
                <?php foreach ($medalhas as $m): ?>
                <div class="medalha">
                    <div class="medalha-icone"><?= $m['icone_emoji'] ?></div>
                    <div class="medalha-nome"><?= e($m['nome_medalha']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color:var(--cinza);font-size:13px;text-align:center">Treine forte para ganhar a sua primeira medalha!</p>
        <?php endif; ?>
    </div>

    <!-- Plano -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-wallet"></i> O Meu Plano</h3>
        <?php if ($plano):
            $hoje = new DateTime();
            $venc = new DateTime($plano['data_vencimento']);
            $diferenca = $hoje->diff($venc)->days;
            $passou = $venc < $hoje;
            $classe_cor = $passou ? 'vence-passou' : ($diferenca <= 5 ? 'vence-perto' : 'vence-ok');
            $icone_status = $passou ? 'fa-circle-xmark' : ($diferenca <= 5 ? 'fa-triangle-exclamation' : 'fa-circle-check');
        ?>
            <div class="plano-destaque">
                <div style="font-size:13px;color:var(--cinza);text-transform:uppercase;font-weight:600">
                    Plano Atual: <span style="color:#fff"><?= e($plano['nome_plano']) ?></span>
                </div>
                <div class="venc-data <?= $classe_cor ?>">
                    <i class="fas <?= $icone_status ?>"></i> Vence dia <?= date('d/m/Y', strtotime($plano['data_vencimento'])) ?>
                </div>
                <?php if ($plano['observacao_aluna']): ?>
                    <div style="margin-top:15px;font-size:12px;color:#fff;background:rgba(0,0,0,.4);padding:12px;border-radius:8px;border-left:3px solid #d62bc5">
                        <i class="fas fa-info-circle" style="color:#d62bc5;margin-right:5px"></i>
                        <?= e($plano['observacao_aluna']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="plano-destaque" style="border-color:#ff4444;background:rgba(255,68,68,.1)">
                <p style="color:#ff4444;font-size:14px;font-weight:bold;margin:0"><i class="fas fa-ban"></i> Nenhum plano ativo.</p>
            </div>
        <?php endif; ?>

        <h4 style="font-size:14px;margin:15px 0 10px;text-transform:uppercase">Últimos Recibos</h4>
        <div style="background:#050308;padding:0 15px;border-radius:12px;border:1px solid var(--borda)">
            <?php foreach ($historico_pag as $hp): ?>
                <div class="hist-item">
                    <span><i class="fas fa-file-invoice" style="margin-right:10px;color:#d62bc5"></i> <?= date('d/m/Y', strtotime($hp['data_pagamento'])) ?></span>
                    <span style="color:#2ecc71;font-weight:800">R$ <?= number_format($hp['valor_pago'], 2, ',', '.') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($historico_pag)): ?>
                <p style="font-size:12px;color:#555;padding:15px 0;margin:0;text-align:center">Nenhum pagamento registado.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function concluirMissao(btn) {
    btn.innerHTML = '<i class="fas fa-trophy"></i> MISSÃO CUMPRIDA!';
    btn.style.background = 'var(--pink)';
    btn.style.color = '#fff';
    btn.style.border = '2px solid transparent';
    btn.style.boxShadow = '0 5px 15px var(--glow)';
    btn.style.pointerEvents = 'none';
    var card = document.getElementById('card-missao');
    card.style.borderColor = '#2ecc71';
    card.style.background = 'rgba(46,204,113,.05)';
}

function copiarLink(btn) {
    var el = document.getElementById('linkConvite');
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand('copy');
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.style.background = '#fff';
    setTimeout(function(){ btn.innerHTML = orig; btn.style.background = '#38ef7d'; }, 2000);
}

function compartilharLink() {
    if (navigator.share) {
        navigator.share({
            title: 'Passe Livre - Elite Thai',
            text: 'Vem treinar comigo na Elite Thai! Resgata o teu Passe Livre VIP:',
            url: document.getElementById('linkConvite').value
        }).catch(function(){});
    } else {
        copiarLink(document.querySelector('.btn-copiar'));
        alert('Link copiado!');
    }
}
</script>
</body>
</html>
