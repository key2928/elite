<?php
// Proteção e Alertas
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

if (!isset($_SESSION['usuario_tipo'])) { header("Location: login.php"); exit; }
if ($_SESSION['usuario_tipo'] === 'admin') { header("Location: admin.php"); exit; }
if (in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) { header("Location: treinador.php"); exit; }

$msg_ficha = '';

try {
    $id = (int) $_SESSION['usuario_id'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_ficha') {
        $restricoes = trim($_POST['restricoes']);
        $pdo->prepare("UPDATE usuarios SET restricoes_medicas = ? WHERE id = ?")->execute([$restricoes, $id]);
        $msg_ficha = "Ficha de saúde atualizada com sucesso!";
    }

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $aluna = $stmt->fetch();
    
    if (!$aluna) { header("Location: logout.php"); exit; }

    $xp = isset($aluna['xp_atual']) ? (int)$aluna['xp_atual'] : 0;
    $nivel = floor($xp / 100) + 1;
    
    if ($xp < 200) { $rank = "Aprendiz (Safira)"; }
    elseif ($xp < 500) { $rank = "Focada (Ametista)"; }
    elseif ($xp < 1000) { $rank = "Guerreira (Rubi)"; }
    elseif ($xp < 2000) { $rank = "Imparável (Diamante)"; }
    else { $rank = "Lenda Elite"; }

    $xp_prox_nivel = $nivel * 100;
    $porcentagem_xp = min(100, (($xp - (($nivel - 1) * 100)) / 100) * 100);

    $treinos_totais = isset($aluna['treinos_concluidos']) ? (int)$aluna['treinos_concluidos'] : 0;
    $progresso_fidelidade = $treinos_totais % 10;
    $faltam_fidelidade = 10 - $progresso_fidelidade;

    $missao = false;
    try { $missao = $pdo->query("SELECT * FROM missoes_semana WHERE status = 'ativa' ORDER BY id DESC LIMIT 1")->fetch(); } catch(Exception $e) {}

    $plano = $pdo->query("SELECT p.data_vencimento, pl.nome_plano, p.observacao_aluna FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id WHERE p.aluna_id = $id ORDER BY p.id DESC LIMIT 1")->fetch();
    $historico_pag = $pdo->query("SELECT p.data_pagamento, p.valor_pago, pl.nome_plano FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id WHERE p.aluna_id = $id ORDER BY p.data_pagamento DESC LIMIT 3")->fetchAll();
    $medalhas = $pdo->query("SELECT * FROM conquistas WHERE aluna_id = $id ORDER BY data_conquista DESC LIMIT 6")->fetchAll();
    $avisos = $pdo->query("SELECT * FROM mural_avisos ORDER BY data_publicacao DESC LIMIT 3")->fetchAll();
    $grade_horarios = $pdo->query("SELECT * FROM horarios_treino ORDER BY id ASC")->fetchAll();
    
    $treinadoras = [];
    try { $treinadoras = $pdo->query("SELECT nome, telefone FROM usuarios WHERE tipo = 'treinador'")->fetchAll(); } 
    catch(Exception $e) { $treinadoras = $pdo->query("SELECT nome, '' as telefone FROM usuarios WHERE tipo = 'treinador'")->fetchAll(); }

} catch (Exception $e) {
    die("<div style='background:#111; color:#ff4444; padding:20px;'><b>⚠️ Erro de Sistema:</b><br>" . $e->getMessage() . "</div>");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>A Minha Jornada - Elite Thai</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --bg-fundo: #09060f; --bg-card: #140d1c; --pink-grad: linear-gradient(90deg, #d62bc5, #7b2cbf); --pink-glow: rgba(214, 43, 197, 0.35); --texto-claro: #f8f9fa; --texto-cinza: #b5a8c9; --borda: #2a1b3d; }
        * { box-sizing: border-box; }
        body { background-color: var(--bg-fundo); color: var(--texto-claro); font-family: 'Poppins', sans-serif; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
        .app-container { max-width: 600px; margin: 0 auto; padding: 20px; padding-bottom: 80px; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: var(--bg-card); border-radius: 20px; padding: 24px; margin-bottom: 20px; border: 1px solid var(--borda); box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: fadeUp 0.6s ease-out forwards; opacity: 0; }
        .card:nth-child(1) { animation-delay: 0.1s; } .card:nth-child(2) { animation-delay: 0.15s; } .card:nth-child(3) { animation-delay: 0.2s; } .card:nth-child(4) { animation-delay: 0.25s; } .card:nth-child(5) { animation-delay: 0.3s; } .card:nth-child(6) { animation-delay: 0.35s; } .card:nth-child(7) { animation-delay: 0.4s; } .card:nth-child(8) { animation-delay: 0.45s; } .card:nth-child(9) { animation-delay: 0.5s; } .card:nth-child(10) { animation-delay: 0.55s; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; margin-top: 10px; }
        .user-info h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .badge-rank { background: var(--pink-grad); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; margin-top: 8px; box-shadow: 0 4px 15px var(--pink-glow); text-transform: uppercase; letter-spacing: 1px;}
        .btn-sair { background: #2a1b3d; color: #ff4d4d; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; transition: 0.3s; }
        .card-titulo { margin: 0 0 20px 0; font-size: 15px; text-transform: uppercase; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px; letter-spacing: 1px; }
        .card-titulo i { background: var(--pink-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px; }
        .luva-fidelidade { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; transition: 0.3s; }
        .luva-ativa { background: var(--pink-grad); box-shadow: 0 5px 15px var(--pink-glow); color: #fff; transform: scale(1.05); }
        .luva-inativa { background: rgba(255,255,255,0.03); border: 1px dashed #555; color: #444; }
        .input-vip { width: 100%; margin-bottom: 0; padding: 16px; border-radius: 12px; background: rgba(0,0,0,0.4); border: 1px solid rgba(56, 239, 125, 0.5); color: #38ef7d; font-weight: 800; font-size: 12px; text-align: center; letter-spacing: 0.5px; outline: none;}
        .btn-copiar { background: #38ef7d; color: #000; border: none; padding: 0 18px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(56, 239, 125, 0.3); display: flex; align-items: center;}
        .btn-indicar { display: flex; align-items: center; justify-content: center; gap: 10px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; text-decoration: none; padding: 16px; border-radius: 15px; font-weight: 800; text-transform: uppercase; font-size: 14px; width: 100%; border: none; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(56, 239, 125, 0.3);}
        .ficha-textarea { width: 100%; background: #050308; border: 1px solid var(--borda); color: #fff; padding: 15px; border-radius: 12px; font-family: 'Poppins', sans-serif; font-size: 13px; resize: vertical; margin-bottom: 15px; transition: 0.3s;}
        .btn-salvar-ficha { background: var(--pink-grad); color: #fff; border: none; width: 100%; padding: 14px; border-radius: 10px; font-weight: 800; text-transform: uppercase; font-size: 13px; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px var(--pink-glow); }
        .msg-sucesso { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: 1px solid #2ecc71; padding: 12px; border-radius: 8px; font-size: 12px; text-align: center; margin-bottom: 15px; font-weight: 600;}
        .btn-checkin { display: block; width: 100%; text-align: center; background: #2a1b3d; color: #d62bc5; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; text-decoration: none; transition: 0.3s; cursor: pointer; border: 1px solid #d62bc5;}
        .dias-semana { display: flex; justify-content: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;}
        .dia { width: 45px; height: 45px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: #555; transition: 0.3s; border: 2px solid transparent;}
        .dia.ativo { background: var(--pink-grad); color: white; box-shadow: 0 5px 15px var(--pink-glow); transform: scale(1.1); border-color: #fff;}
        .xp-container { background: #050308; padding: 18px; border-radius: 15px; border: 1px solid var(--borda); }
        .xp-texto { display: flex; justify-content: space-between; font-size: 13px; color: var(--texto-cinza); margin-bottom: 12px; font-weight: 600; text-transform: uppercase;}
        .xp-barra-fundo { background: #2a1b3d; height: 12px; border-radius: 10px; overflow: hidden; position: relative; }
        .xp-barra-progresso { background: var(--pink-grad); height: 100%; border-radius: 10px; position: relative; box-shadow: 0 0 10px var(--pink-glow); transition: width 1s ease-in-out;}
        .btn-grupo-zap { display: flex; align-items: center; justify-content: center; gap: 10px; background: #25D366; color: #fff; text-decoration: none; padding: 16px; border-radius: 12px; font-weight: 800; text-transform: uppercase; font-size: 14px; margin-bottom: 25px; transition: 0.3s; box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);}
        .instrutora-lista { display: flex; flex-direction: column; gap: 12px; }
        .instrutora-item { display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); padding: 16px; border-radius: 12px; border: 1px solid var(--borda); text-decoration: none; color: #fff; transition: 0.3s;}
        .aviso { padding: 16px; border-radius: 12px; margin-bottom: 12px; background: rgba(255, 255, 255, 0.03); border-left: 4px solid #555; font-size: 13px; line-height: 1.6; color: var(--texto-cinza); }
        .aviso.urgente { border-left-color: #ff4444; background: linear-gradient(90deg, rgba(255, 68, 68, 0.1) 0%, transparent 100%); }
        .grid-medalhas { display: grid; grid-template-columns: repeat(auto-fill, minmax(85px, 1fr)); gap: 12px; }
        .medalha { background: rgba(255,255,255,0.02); padding: 15px 10px; text-align: center; border-radius: 16px; border: 1px solid var(--borda); transition: 0.3s; }
        .medalha-icone { font-size: 34px; margin-bottom: 10px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5)); }
        .medalha-nome { font-size: 11px; font-weight: 600; color: var(--texto-cinza); line-height: 1.3;}
        .plano-destaque { background: linear-gradient(135deg, rgba(214, 43, 197, 0.1) 0%, transparent 100%); padding: 22px; border-radius: 16px; border: 1px solid rgba(214, 43, 197, 0.3); margin-bottom: 25px; position: relative; overflow: hidden;}
        .venc-data { font-size: 26px; font-weight: 800; display: block; margin-top: 5px; }
        .vence-ok { color: #2ecc71; } .vence-perto { color: #f1c40f; } .vence-passou { color: #ff4444; }
        .hist-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px dashed var(--borda); font-size: 13px; color: var(--texto-cinza); }
        .hist-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="app-container">

    <div class="header card" style="padding: 20px; border-radius: 25px;">
        <div class="user-info">
            <div style="font-size: 12px; color: var(--texto-cinza); text-transform: uppercase; font-weight: 600; letter-spacing: 1px;">A Sua Jornada</div>
            <h1><?= htmlspecialchars(explode(' ', $aluna['nome'])[0]) ?></h1>
            <div class="badge-rank"><i class="fas fa-gem" style="margin-right: 5px;"></i> <?= $rank ?></div>
        </div>
        <a href="logout.php" class="btn-sair" title="Sair da App"><i class="fas fa-power-off"></i></a>
    </div>

    <div class="card" style="border-left: 4px solid #d62bc5; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: var(--pink-grad); filter: blur(50px); opacity: 0.3;"></div>
        <h3 class="card-titulo"><i class="fas fa-id-card"></i> Cartão Fidelização</h3>
        <p style="font-size: 12px; color: var(--texto-cinza); margin-top: -10px; margin-bottom: 20px;">
            Complete 10 treinos marcando presença e troque por um prêmio exclusivo da Elite Thai!
        </p>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
            <?php for($i=0; $i<10; $i++): ?>
                <?php if($i < $progresso_fidelidade): ?>
                    <div class="luva-fidelidade luva-ativa"><i class="fas fa-hand-rock"></i></div>
                <?php else: ?>
                    <div class="luva-fidelidade luva-inativa"><i class="fas fa-hand-rock"></i></div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 11px; font-weight: 800; color: #d62bc5; text-transform: uppercase; letter-spacing: 1px;">
            <?= $faltam_fidelidade == 10 ? "Comece hoje a sua jornada!" : "Faltam {$faltam_fidelidade} treinos para o seu prêmio!" ?>
        </div>
    </div>

    <div class="card" style="border-color: #38ef7d; background: linear-gradient(180deg, var(--bg-card) 0%, rgba(56, 239, 125, 0.05) 100%);">
        <h3 class="card-titulo" style="color: #38ef7d;"><i class="fas fa-ticket-alt" style="background: none; -webkit-text-fill-color: #38ef7d;"></i> Passe Livre VIP</h3>
        <p style="font-size: 13px; color: var(--texto-cinza); margin-bottom: 15px; line-height: 1.5;">
            Presenteie uma amiga com <strong>1 Aula VIP Gratuita</strong>! Envie o seu link exclusivo e ganhe vantagens se ela se matricular.
        </p>
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <input type="text" id="linkConvite" value="https://iubsites.com/academia/convite.php?ref=<?= $id ?>" readonly class="input-vip">
            <button onclick="copiarLink(this)" class="btn-copiar"><i class="fas fa-copy"></i></button>
        </div>
        <button onclick="compartilharLink()" class="btn-indicar">
            <i class="fas fa-share-nodes"></i> Compartilhar Convite
        </button>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-notes-medical" style="color:#d62bc5; background:none; -webkit-text-fill-color: #d62bc5;"></i> Ficha de Saúde</h3>
        <p style="font-size: 12px; color: var(--texto-cinza); margin-top: -10px; margin-bottom: 15px;">
            Mantenha a sua ficha médica atualizada para a treinadora adaptar o seu treino com segurança.
        </p>
        <?php if($msg_ficha): ?>
            <div class="msg-sucesso"><i class="fas fa-check-circle"></i> <?= $msg_ficha ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="acao" value="atualizar_ficha">
            <textarea name="restricoes" class="ficha-textarea" rows="3" placeholder="Ex: Sinto dor lombar, asma leve..."><?= htmlspecialchars($aluna['restricoes_medicas'] ?? '') ?></textarea>
            <button type="submit" class="btn-salvar-ficha"><i class="fas fa-save"></i> Atualizar Ficha</button>
        </form>
    </div>

    <?php if($missao): ?>
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-crosshairs"></i> Missão da Semana</h3>
        <div style="background: rgba(214, 43, 197, 0.05); border: 1px dashed #d62bc5; padding: 20px; border-radius: 15px; text-align: center; position: relative; transition: 0.3s;" id="card-missao">
            <i class="fas fa-fire-alt" style="font-size: 34px; color: #FF8C00; margin-bottom: 12px; filter: drop-shadow(0 0 10px rgba(255, 140, 0, 0.5));"></i>
            <h4 style="margin: 0 0 8px 0; color: #fff; text-transform: uppercase; font-size: 16px; letter-spacing: 1px;"><?= htmlspecialchars($missao['titulo']) ?></h4>
            <p style="font-size: 13px; color: var(--texto-cinza); margin-bottom: 20px; line-height: 1.5;">
                <?= nl2br(htmlspecialchars($missao['descricao'])) ?>
            </p>
            <a href="#" class="btn-checkin" onclick="concluirMissao(this); return false;" style="background: transparent; border: 2px solid #d62bc5; color: #d62bc5;">
                <i class="fas fa-check"></i> Aceitar e Concluir
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-bolt"></i> Evolução Semanal</h3>
        <p style="font-size: 12px; color: var(--texto-cinza); text-align: center; margin-top: -10px; margin-bottom: 20px;">
            Mantenha a ofensiva de treinos para ganhar mais XP!
        </p>
        <div class="dias-semana">
            <?php 
            if(count($grade_horarios) > 0) {
                foreach($grade_horarios as $hr) {
                    $letra_dia = strtoupper(substr($hr['dia_semana'], 0, 1));
                    echo "<div class='dia ativo'>{$letra_dia}</div>";
                }
            } else { echo "<p style='font-size: 12px; color: #555;'>Sem treinos configurados.</p>"; }
            ?>
        </div>
        <div class="xp-container">
            <div class="xp-texto">
                <span>Nível <?= $nivel ?></span>
                <span style="color: #d62bc5;"><?= $xp ?> / <?= $xp_prox_nivel ?> XP</span>
            </div>
            <div class="xp-barra-fundo">
                <div class="xp-barra-progresso" style="width: <?= $porcentagem_xp ?>%;"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-users"></i> Comunidade Elite</h3>
        <a href="https://chat.whatsapp.com/JqGPW7GUNLMLIyZ8btRffy" target="_blank" class="btn-grupo-zap">
            <i class="fab fa-whatsapp" style="font-size: 22px;"></i> Entrar no Grupo Oficial
        </a>
        <h4 style="color: #fff; font-size: 13px; margin: 20px 0 10px 0; text-transform: uppercase;">Falar com a Treinadora</h4>
        <div class="instrutora-lista">
            <?php foreach($treinadoras as $treina): ?>
                <?php 
                    $numero = isset($treina['telefone']) ? preg_replace('/\D/', '', $treina['telefone']) : ''; 
                    $link_zap = !empty($numero) ? "https://wa.me/55{$numero}" : "#";
                ?>
                <a href="<?= $link_zap ?>" target="_blank" class="instrutora-item">
                    <div>
                        <i class="fas fa-fire" style="color: #d62bc5; margin-right: 10px;"></i> 
                        <strong><?= htmlspecialchars($treina['nome']) ?></strong>
                    </div>
                    <i class="fab fa-whatsapp" style="color: #25D366; font-size: 24px;"></i>
                </a>
            <?php endforeach; ?>
            <?php if(empty($treinadoras)): ?>
                 <p style="font-size: 12px; color: var(--texto-cinza);">Nenhuma treinadora registada.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-map-marker-alt"></i> Onde Treinamos</h3>
        <div style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; border: 1px solid var(--borda); display: flex; align-items: center; gap: 15px;">
            <div style="background: rgba(214, 43, 197, 0.1); color: #d62bc5; width: 40px; height: 40px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 20px; flex-shrink: 0;">
                <i class="fas fa-map-pin"></i>
            </div>
            <div>
                <strong style="color: #fff; font-size: 14px; display: block; margin-bottom: 3px;">Elite Thai</strong>
                <span style="color: var(--texto-cinza); font-size: 13px; line-height: 1.4;">Av. Santos Dumont, 392<br>Ao lado do açougue Fangolar<br>Itumbiara - GO</span>
            </div>
        </div>
        <a href="https://maps.google.com/?q=Av.+Santos+Dumont+392,+Itumbiara+-+GO" target="_blank" class="btn-indicar" style="margin-top: 15px; padding: 12px; font-size: 13px; background: transparent; border: 1px solid #d62bc5; color: #d62bc5; box-shadow: none;">
            <i class="fas fa-location-arrow"></i> Ver no Mapa
        </a>
    </div>

    <?php if(count($avisos) > 0): ?>
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-bullhorn"></i> Avisos da Academia</h3>
        <?php foreach($avisos as $aviso): ?>
            <div class="aviso <?= $aviso['tipo'] == 'urgente' ? 'urgente' : '' ?>">
                <strong><?= htmlspecialchars($aviso['titulo']) ?></strong>
                <?= nl2br(htmlspecialchars($aviso['mensagem'])) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-award"></i> As Suas Conquistas</h3>
        <?php if(count($medalhas) > 0): ?>
            <div class="grid-medalhas">
                <?php foreach($medalhas as $m): ?>
                <div class="medalha">
                    <div class="medalha-icone"><?= $m['icone_emoji'] ?></div>
                    <div class="medalha-nome"><?= htmlspecialchars($m['nome_medalha']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--texto-cinza); font-size: 13px; text-align: center;">Treine forte e dê o seu melhor para ganhar a sua primeira medalha da treinadora!</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-wallet"></i> O Meu Plano</h3>
        <?php if($plano): 
            $hoje = new DateTime();
            $venc = new DateTime($plano['data_vencimento']);
            $diferenca = $hoje->diff($venc)->days;
            $passou = $venc < $hoje;
            
            $classe_cor = $passou ? "vence-passou" : (($diferenca <= 5) ? "vence-perto" : "vence-ok");
            $icone_status = $passou ? "fa-circle-xmark" : (($diferenca <= 5) ? "fa-triangle-exclamation" : "fa-circle-check");
        ?>
            <div class="plano-destaque">
                <div style="font-size: 13px; color: var(--texto-cinza); text-transform: uppercase; font-weight: 600;">
                    Plano Atual: <span style="color:#fff;"><?= htmlspecialchars($plano['nome_plano']) ?></span>
                </div>
                <div class="venc-data <?= $classe_cor ?>">
                    <i class="fas <?= $icone_status ?>"></i> Vence dia <?= date('d/m/Y', strtotime($plano['data_vencimento'])) ?>
                </div>
                <?php if($plano['observacao_aluna']): ?>
                    <div style="margin-top: 15px; font-size: 12px; color: #fff; background: rgba(0,0,0,0.4); padding: 12px; border-radius: 8px; border-left: 3px solid #d62bc5;">
                        <i class="fas fa-info-circle" style="color: #d62bc5; margin-right: 5px;"></i> 
                        <?= htmlspecialchars($plano['observacao_aluna']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="plano-destaque" style="border-color: #ff4444; background: rgba(255, 68, 68, 0.1);">
                <p style="color: #ff4444; font-size: 14px; font-weight: bold; margin: 0;"><i class="fas fa-ban"></i> Nenhum plano ativo encontrado.</p>
            </div>
        <?php endif; ?>

        <h4 style="color: #fff; font-size: 14px; margin: 15px 0 10px 0; text-transform: uppercase;">Últimos Recibos</h4>
        <div style="background: #050308; padding: 0 15px; border-radius: 12px; border: 1px solid var(--borda);">
            <?php foreach($historico_pag as $hp): ?>
                <div class="hist-item">
                    <span><i class="fas fa-file-invoice" style="margin-right: 10px; color: #d62bc5;"></i> <?= date('d/m/Y', strtotime($hp['data_pagamento'])) ?></span>
                    <span style="color: #2ecc71; font-weight: 800;">R$ <?= number_format($hp['valor_pago'], 2, ',', '.') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if(empty($historico_pag)): ?>
                <p style="font-size: 12px; color: #555; padding: 15px 0; margin: 0; text-align: center;">Nenhum pagamento registado no histórico.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Missão
function concluirMissao(btn) {
    btn.innerHTML = '<i class="fas fa-trophy"></i> MISSÃO CUMPRIDA!';
    btn.style.background = 'var(--pink-grad)';
    btn.style.color = '#fff';
    btn.style.border = '2px solid transparent';
    btn.style.boxShadow = '0 5px 15px var(--pink-glow)';
    btn.style.pointerEvents = 'none';
    
    let card = document.getElementById('card-missao');
    card.style.borderColor = '#2ecc71';
    card.style.background = 'rgba(46, 204, 113, 0.05)';
    
    btn.style.transform = 'scale(1.05)';
    setTimeout(() => btn.style.transform = 'scale(1)', 200);
}

// Copiar Link VIP
function copiarLink(btn) {
    var copyText = document.getElementById("linkConvite");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    document.execCommand("copy");
    
    let iconeOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.style.background = '#fff';
    setTimeout(() => {
        btn.innerHTML = iconeOriginal;
        btn.style.background = '#38ef7d';
    }, 2000);
}

// Compartilhar Nativo
function compartilharLink() {
    if (navigator.share) {
        navigator.share({
            title: 'Passe Livre - Elite Thai',
            text: 'Vem treinar Muay Thai comigo na Elite Thai! Resgata o teu Passe Livre VIP usando o meu link:',
            url: document.getElementById("linkConvite").value
        }).catch((error) => console.log('Erro ao partilhar', error));
    } else {
        copiarLink(document.querySelector('.btn-copiar'));
        alert("Link copiado para a área de transferência!");
    }
}
</script>

</body>
</html>