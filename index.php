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
        $pdo->prepare("UPDATE usuarios SET restricoes_medicas=?, data_nascimento=?, tipo_sanguineo=?, peso=?, altura=?, doencas_cronicas=?, medicamentos_uso=?, historico_lesoes=?, emergencia_nome=?, emergencia_telefone=?, objetivo_treino=?, nivel_experiencia=? WHERE id=?")
            ->execute([
                trim($_POST['restricoes'] ?? ''),
                ($_POST['data_nascimento'] ?: null), ($_POST['tipo_sanguineo'] ?: null),
                ($_POST['peso'] ?: null), ($_POST['altura'] ?: null),
                ($_POST['doencas_cronicas'] ?: null), ($_POST['medicamentos_uso'] ?: null),
                ($_POST['historico_lesoes'] ?: null), ($_POST['emergencia_nome'] ?: null),
                ($_POST['emergencia_telefone'] ?: null), ($_POST['objetivo_treino'] ?: null),
                ($_POST['nivel_experiencia'] ?: 'iniciante'),
                $id,
            ]);
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

    $stmt = $pdo->prepare("SELECT p.data_pagamento, p.valor_pago, p.data_vencimento, p.forma_pagamento, pl.nome_plano, u.nome as treinador_nome FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id LEFT JOIN usuarios u ON p.treinador_id = u.id WHERE p.aluna_id = ? ORDER BY p.data_pagamento DESC LIMIT 6");
    $stmt->execute([$id]);
    $historico_pag = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM conquistas WHERE aluna_id = ? ORDER BY data_conquista DESC LIMIT 6");
    $stmt->execute([$id]);
    $medalhas = $stmt->fetchAll();

    $avisos = $pdo->query("SELECT * FROM mural_avisos ORDER BY data_publicacao DESC LIMIT 3")->fetchAll();
    $grade_horarios = $pdo->query("SELECT * FROM horarios_treino ORDER BY id ASC")->fetchAll();

    // Presença do mês atual
    $presencas_mes = [];
    try {
        $stmtPM = $pdo->prepare("SELECT data_presenca, presente FROM presencas WHERE aluno_id=? AND data_presenca LIKE ? ORDER BY data_presenca ASC");
        $stmtPM->execute([$id, date('Y-m') . '%']);
        $presencas_mes = $stmtPM->fetchAll();
    } catch (Exception $ex) {}

    // Brindes ganhos pela aluna (roleta disponível?)
    $brindes_aluna = [];
    $brinde_roleta = null;
    try {
        $stmtBA = $pdo->prepare("SELECT ba.*, b.nome as brinde_nome FROM brindes_aluna ba LEFT JOIN brindes b ON ba.brinde_id=b.id WHERE ba.aluna_id=? ORDER BY ba.created_at DESC");
        $stmtBA->execute([$id]);
        $brindes_aluna = $stmtBA->fetchAll();
        foreach ($brindes_aluna as $ba) {
            if (!$ba['roleta_girada']) { $brinde_roleta = $ba; break; }
        }
    } catch (Exception $ex) {}

    // Brindes disponíveis para roleta
    $brindes_lista = [];
    try { $brindes_lista = $pdo->query("SELECT * FROM brindes WHERE ativo=1 ORDER BY nome")->fetchAll(); } catch (Exception $ex) {}

    $treinadoras = [];
    try { $treinadoras = $pdo->query("SELECT nome, telefone FROM usuarios WHERE tipo = 'treinador'")->fetchAll(); } catch (Exception $ex) {}

    // Turmas do aluno com professores e horários
    $minhas_turmas_aluno = [];
    try {
        $stmtTA = $pdo->prepare("SELECT t.nome, h.dia_semana, h.horario, GROUP_CONCAT(u.nome ORDER BY u.nome SEPARATOR ', ') as professores_nomes FROM aluno_turmas at_aluno JOIN turmas t ON at_aluno.turma_id = t.id LEFT JOIN horarios_treino h ON t.horario_id = h.id LEFT JOIN turma_professores tp ON t.id = tp.turma_id LEFT JOIN usuarios u ON tp.professor_id = u.id WHERE at_aluno.aluno_id = ? GROUP BY t.id ORDER BY t.nome");
        $stmtTA->execute([$id]);
        $minhas_turmas_aluno = $stmtTA->fetchAll();
    } catch (Exception $ex) {}

    // Próxima Aula
    $proxima_aula = null;
    try {
        if (!empty($minhas_turmas_aluno)) {
            $now = new DateTime();
            $weekday_map = [
                'segunda' => 1, 'monday' => 1,
                'terça'   => 2, 'terca'  => 2, 'tuesday'  => 2,
                'quarta'  => 3, 'wednesday' => 3,
                'quinta'  => 4, 'thursday'  => 4,
                'sexta'   => 5, 'friday'    => 5,
                'sábado'  => 6, 'sabado' => 6, 'saturday' => 6,
                'domingo' => 0, 'sunday'    => 0,
            ];
            $candidatos = [];
            foreach ($minhas_turmas_aluno as $mt) {
                if (empty($mt['dia_semana']) || empty($mt['horario'])) continue;
                $lc = mb_strtolower($mt['dia_semana'], 'UTF-8');
                $dw = null;
                foreach ($weekday_map as $k => $v) { if (str_contains($lc, $k)) { $dw = $v; break; } }
                if ($dw === null) continue;
                preg_match('/\d{2}:\d{2}/', $mt['horario'], $m);
                $hora_aula = $m[0] ?? '00:00';
                $diff = ($dw - (int)$now->format('w') + 7) % 7;
                if ($diff === 0) {
                    $hoje_aula = new DateTime($now->format('Y-m-d') . ' ' . $hora_aula);
                    if ($hoje_aula < $now) { $diff = 7; }
                }
                $proxima = (clone $now)->modify("+{$diff} days");
                $proxima->setTime((int)substr($hora_aula, 0, 2), (int)substr($hora_aula, 3, 2), 0);
                $candidatos[] = ['datetime' => $proxima, 'turma' => $mt];
            }
            if (!empty($candidatos)) {
                usort($candidatos, fn($a, $b) => $a['datetime'] <=> $b['datetime']);
                $proxima_aula = $candidatos[0];
            }
        }
    } catch (Exception $ex) {}

    // Dica do Dia
    $dicas_dia = [
        '💪 Aqueça bem antes de cada treino para prevenir lesões e melhorar o desempenho.',
        '🥤 Hidratação é essencial! Beba água antes, durante e após o treino.',
        '🧘 O descanso faz parte do treino. Uma boa noite de sono acelera a recuperação.',
        '🥊 Foque na técnica antes de aumentar a intensidade. Qualidade vale mais que quantidade.',
        '🍎 Uma boa alimentação representa 50% dos seus resultados. Cuide do que come!',
        '🔥 Consistência é mais poderosa que perfeição. Apareça mesmo nos dias difíceis.',
        '🏋️ Fortaleça o core — ele é a base de todos os movimentos do Muay Thai.',
        '😤 Na hora difícil, lembre-se do motivo que te trouxe até aqui. Não desista!',
        '⚡ Cada treino te deixa mais forte, mais ágil e mais confiante do que ontem.',
        '🤜 No Muay Thai, a mente desiste antes do corpo. Treine a sua mentalidade!',
        '🦵 Trabalhe o alongamento diário para ganhar amplitude de movimento nos chutes.',
        '🛡️ Defesa é tão importante quanto ataque. Aprenda a se proteger com maestria.',
        '🎯 Defina um objetivo claro para cada treino. Saber o que quer melhora o foco.',
        '👊 A combinação jab-cruzado é a base de tudo. Perfeiçoe-a sem parar.',
        '🌟 Celebre as suas pequenas vitórias — cada treino concluído é uma conquista!',
    ];
    $dica_hoje = $dicas_dia[date('z') % count($dicas_dia)];

} catch (Exception $ex) {
    die("<div style='background:#111;color:#ff4444;padding:20px'><b>Erro:</b> " . e($ex->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls</title>
    <meta name="description" content="Academia Elite Thai Girls — Muay Thai & Fitness">
    <meta name="theme-color" content="#d62bc5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elite Thai Girls">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <link rel="apple-touch-icon" href="icon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#09060f;--card:#140d1c;--pink:linear-gradient(90deg,#d62bc5,#7b2cbf);--glow:rgba(214,43,197,.35);--txt:#f8f9fa;--cinza:#b5a8c9;--borda:#2a1b3d}
        *{box-sizing:border-box}
        body{background:var(--bg);color:var(--txt);font-family:'Poppins',sans-serif;margin:0;padding:0}
        .app{max-width:600px;margin:0 auto;padding:16px;padding-bottom:90px}
        @keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .card{background:var(--card);border-radius:20px;padding:20px;margin-bottom:16px;border:1px solid var(--borda);box-shadow:0 10px 30px rgba(0,0,0,.5);animation:fadeUp .6s ease-out forwards;opacity:0}
        .card:nth-child(1){animation-delay:.1s}.card:nth-child(2){animation-delay:.15s}.card:nth-child(3){animation-delay:.2s}.card:nth-child(4){animation-delay:.25s}.card:nth-child(5){animation-delay:.3s}.card:nth-child(6){animation-delay:.35s}.card:nth-child(7){animation-delay:.4s}.card:nth-child(8){animation-delay:.45s}.card:nth-child(9){animation-delay:.5s}.card:nth-child(10){animation-delay:.55s}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;margin-top:10px}
        .user-info h1{margin:0;font-size:24px;font-weight:800;letter-spacing:-.5px}
        .badge-rank{background:var(--pink);color:#fff;padding:6px 14px;border-radius:20px;font-weight:600;font-size:12px;display:inline-block;margin-top:8px;box-shadow:0 4px 15px var(--glow);text-transform:uppercase;letter-spacing:1px}
        .btn-sair{background:#2a1b3d;color:#ff4d4d;width:45px;height:45px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:18px;transition:.3s;flex-shrink:0}
        .card-titulo{margin:0 0 16px;font-size:14px;text-transform:uppercase;font-weight:800;display:flex;align-items:center;gap:10px;letter-spacing:1px}
        .card-titulo i{background:var(--pink);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:20px}
        .luva{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;transition:.3s}
        .luva-on{background:var(--pink);box-shadow:0 5px 15px var(--glow);color:#fff;transform:scale(1.05)}
        .luva-off{background:rgba(255,255,255,.03);border:1px dashed #555;color:#444}
        .input-vip{width:100%;padding:14px;border-radius:12px;background:rgba(0,0,0,.4);border:1px solid rgba(56,239,125,.5);color:#38ef7d;font-weight:800;font-size:11px;text-align:center;letter-spacing:.5px;outline:none;margin-bottom:0}
        .btn-copiar{background:#38ef7d;color:#000;border:none;padding:0 16px;border-radius:12px;font-weight:800;cursor:pointer;transition:.3s;box-shadow:0 5px 15px rgba(56,239,125,.3);display:flex;align-items:center}
        .btn-indicar{display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;text-decoration:none;padding:14px;border-radius:15px;font-weight:800;text-transform:uppercase;font-size:13px;width:100%;border:none;cursor:pointer;transition:.3s;box-shadow:0 10px 20px rgba(56,239,125,.3)}
        .ficha-textarea{width:100%;background:#050308;border:1px solid var(--borda);color:#fff;padding:14px;border-radius:12px;font-family:'Poppins',sans-serif;font-size:13px;resize:vertical;margin-bottom:12px;transition:.3s}
        .btn-salvar{background:var(--pink);color:#fff;border:none;width:100%;padding:14px;border-radius:10px;font-weight:800;text-transform:uppercase;font-size:13px;cursor:pointer;transition:.3s;box-shadow:0 5px 15px var(--glow)}
        .msg-ok{background:rgba(46,204,113,.1);color:#2ecc71;border:1px solid #2ecc71;padding:12px;border-radius:8px;font-size:12px;text-align:center;margin-bottom:15px;font-weight:600}
        .btn-checkin{display:block;width:100%;text-align:center;background:#2a1b3d;color:#d62bc5;padding:12px;border-radius:8px;font-size:13px;font-weight:800;text-transform:uppercase;text-decoration:none;transition:.3s;cursor:pointer;border:1px solid #d62bc5}
        .dias-semana{display:flex;justify-content:center;gap:12px;margin-bottom:25px;flex-wrap:wrap}
        .dia{width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#555;transition:.3s;border:2px solid transparent}
        .dia.ativo{background:var(--pink);color:#fff;box-shadow:0 5px 15px var(--glow);transform:scale(1.1);border-color:#fff}
        .xp-box{background:#050308;padding:18px;border-radius:15px;border:1px solid var(--borda)}
        .xp-texto{display:flex;justify-content:space-between;font-size:13px;color:var(--cinza);margin-bottom:12px;font-weight:600;text-transform:uppercase}
        .xp-barra{background:#2a1b3d;height:12px;border-radius:10px;overflow:hidden}
        .xp-fill{background:var(--pink);height:100%;border-radius:10px;box-shadow:0 0 10px var(--glow);transition:width 1s ease-in-out}
        .btn-grupo{display:flex;align-items:center;justify-content:center;gap:10px;background:#25D366;color:#fff;text-decoration:none;padding:14px;border-radius:12px;font-weight:800;text-transform:uppercase;font-size:13px;margin-bottom:20px;transition:.3s;box-shadow:0 5px 15px rgba(37,211,102,.3)}
        .instrutora-item{display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,.03);padding:14px;border-radius:12px;border:1px solid var(--borda);text-decoration:none;color:#fff;transition:.3s;margin-bottom:12px}
        .aviso{padding:14px;border-radius:12px;margin-bottom:12px;background:rgba(255,255,255,.03);border-left:4px solid #555;font-size:13px;line-height:1.6;color:var(--cinza)}
        .aviso.urgente{border-left-color:#ff4444;background:linear-gradient(90deg,rgba(255,68,68,.1),transparent)}
        .grid-medalhas{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:10px}
        .medalha{background:rgba(255,255,255,.02);padding:14px 8px;text-align:center;border-radius:16px;border:1px solid var(--borda);transition:.3s}
        .medalha-icone{font-size:30px;margin-bottom:8px;filter:drop-shadow(0 4px 6px rgba(0,0,0,.5))}
        .medalha-nome{font-size:11px;font-weight:600;color:var(--cinza);line-height:1.3}
        .plano-destaque{background:linear-gradient(135deg,rgba(214,43,197,.1),transparent);padding:20px;border-radius:16px;border:1px solid rgba(214,43,197,.3);margin-bottom:20px;position:relative;overflow:hidden}
        .venc-data{font-size:24px;font-weight:800;display:block;margin-top:5px}
        .vence-ok{color:#2ecc71}.vence-perto{color:#f1c40f}.vence-passou{color:#ff4444}
        .hist-item{display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px dashed var(--borda);font-size:13px;color:var(--cinza)}
        .hist-item:last-child{border-bottom:none}
        /* Ficha de saúde collapsible */
        .ficha-toggle{display:flex;justify-content:space-between;align-items:center;cursor:pointer;user-select:none;-webkit-tap-highlight-color:transparent;padding:4px 0}
        .ficha-toggle .toggle-icon{width:32px;height:32px;border-radius:50%;background:rgba(214,43,197,.15);border:1px solid rgba(214,43,197,.4);display:flex;align-items:center;justify-content:center;color:#d62bc5;font-size:14px;transition:transform .3s,background .3s}
        .ficha-toggle.aberto .toggle-icon{transform:rotate(180deg);background:rgba(214,43,197,.3)}
        .ficha-campos{overflow:hidden;transition:max-height .4s ease,opacity .3s ease}
        .ficha-campos.fechado{max-height:0!important;opacity:0;pointer-events:none}
        /* Input styles for ficha */
        .card input[type="date"],.card input[type="number"],.card input[type="text"],.card select{width:100%;padding:13px 14px;border-radius:10px;border:1px solid var(--borda);background:#050308;color:#fff;font-family:'Poppins',sans-serif;font-size:13px;outline:none;transition:.3s;-webkit-appearance:none}
        .card select option{background:#15111b}
        /* PWA install banner */
        #pwa-banner{display:none;position:fixed;bottom:0;left:0;right:0;background:linear-gradient(135deg,#1a0a2e,#2a1b3d);border-top:1px solid rgba(214,43,197,.4);padding:14px 20px;z-index:999;align-items:center;gap:12px;box-shadow:0 -5px 20px rgba(0,0,0,.4)}
        #pwa-banner img{width:42px;height:42px;border-radius:10px;flex-shrink:0}
        #pwa-banner .pwa-txt{flex:1;min-width:0}
        #pwa-banner .pwa-txt strong{display:block;font-size:13px;color:#fff;font-weight:800}
        #pwa-banner .pwa-txt span{font-size:11px;color:var(--cinza)}
        #pwa-install-btn{background:var(--pink);color:#fff;border:none;padding:10px 16px;border-radius:10px;font-weight:800;font-size:12px;cursor:pointer;white-space:nowrap;flex-shrink:0}
        #pwa-close-btn{color:#888;font-size:18px;background:none;border:none;cursor:pointer;flex-shrink:0;padding:4px}
        /* Responsive layout improvements */
        @media(max-width:400px){
            .app{padding:12px;padding-bottom:90px}
            .card{padding:16px;border-radius:16px}
            .user-info h1{font-size:20px}
            .venc-data{font-size:20px}
        }
        @media(min-width:768px){
            body{background:radial-gradient(ellipse at 50% 20%,rgba(214,43,197,.12) 0%,var(--bg) 70%)}
            .app{max-width:720px;padding:30px}
            .card{padding:28px;border-radius:24px}
        }
        @media(min-width:1024px){
            .app{max-width:860px}
            #pwa-banner{left:50%;right:auto;transform:translateX(-50%);width:500px;border-radius:16px 16px 0 0}
        }
    </style>
</head>
<body>

<!-- PWA Install Banner -->
<div id="pwa-banner">
    <img src="icon.svg" alt="Elite Thai Girls">
    <div class="pwa-txt">
        <strong>Elite Thai Girls</strong>
        <span>Instale o app e acesse mais rápido!</span>
    </div>
    <button id="pwa-install-btn"><i class="fas fa-download"></i> Instalar</button>
    <button id="pwa-close-btn" onclick="fecharBanner()" aria-label="Fechar"><i class="fas fa-times"></i></button>
</div>

<div class="app">

    <!-- Header -->
    <div class="header card" style="padding:20px;border-radius:25px">
        <div class="user-info">
            <div style="font-size:11px;color:var(--cinza);text-transform:uppercase;font-weight:600;letter-spacing:1px;display:flex;align-items:center;gap:6px">
                <img src="icon.svg" alt="" style="width:18px;height:18px;border-radius:4px;vertical-align:middle">
                Elite Thai Girls
            </div>
            <h1><?= e(explode(' ', $aluna['nome'])[0]) ?></h1>
            <div class="badge-rank"><i class="fas fa-gem" style="margin-right:5px"></i> <?= $rank ?></div>
        </div>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <!-- Alerta de Plano Vencido / A Vencer -->
    <?php
    $plano_check = $plano ?? null;
    if ($plano_check):
        $hoje_check  = new DateTime();
        $venc_check  = new DateTime($plano_check['data_vencimento']);
        $diff_check  = (int)$hoje_check->diff($venc_check)->days;
        $passou_check = $venc_check < $hoje_check;
        if ($passou_check || $diff_check <= 7):
    ?>
    <div style="background:<?= $passou_check ? 'rgba(255,68,68,.12)' : 'rgba(241,196,15,.1)' ?>;border:1px solid <?= $passou_check ? '#ff4444' : '#f1c40f' ?>;border-radius:16px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
        <div style="font-size:26px;flex-shrink:0"><?= $passou_check ? '⚠️' : '⏳' ?></div>
        <div>
            <div style="font-weight:800;font-size:13px;color:<?= $passou_check ? '#ff4444' : '#f1c40f' ?>"><?= $passou_check ? 'Plano Vencido!' : 'Plano Vencendo em Breve!' ?></div>
            <div style="font-size:12px;color:var(--cinza);margin-top:3px"><?= $passou_check ? 'A sua mensalidade venceu em ' . date('d/m/Y', strtotime($plano_check['data_vencimento'])) . '. Renove para continuar treinando!' : "Faltam {$diff_check} dia(s) para vencer. Renove para não perder o acesso!" ?></div>
        </div>
    </div>
    <?php endif; endif; ?>

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

    <!-- Próxima Aula -->
    <?php if ($proxima_aula): ?>
    <?php
        $now_pa   = new DateTime();
        $diff_pa  = $now_pa->diff($proxima_aula['datetime']);
        $dias_pa  = (int)$diff_pa->days;
        if ($dias_pa === 0)      { $tempo_pa = "Hoje às " . $proxima_aula['datetime']->format('H:i'); }
        elseif ($dias_pa === 1)  { $tempo_pa = "Amanhã às " . $proxima_aula['datetime']->format('H:i'); }
        else                     { $tempo_pa = "Em {$dias_pa} dias — " . $proxima_aula['datetime']->format('d/m') . " às " . $proxima_aula['datetime']->format('H:i'); }
        $urgente_pa = $dias_pa === 0;
    ?>
    <div class="card" style="border-left:4px solid <?= $urgente_pa ? '#2ecc71' : '#7b2cbf' ?>;background:linear-gradient(135deg,var(--card),rgba(123,44,191,.07))">
        <h3 class="card-titulo"><i class="fas fa-dumbbell" style="background:none;-webkit-text-fill-color:<?= $urgente_pa ? '#2ecc71' : '#d62bc5' ?>"></i> Próxima Aula</h3>
        <div style="display:flex;align-items:center;gap:16px">
            <div style="background:<?= $urgente_pa ? 'rgba(46,204,113,.15)' : 'rgba(214,43,197,.1)' ?>;border:1px solid <?= $urgente_pa ? '#2ecc71' : '#d62bc5' ?>;border-radius:14px;padding:14px 18px;text-align:center;flex-shrink:0">
                <div style="font-size:24px;font-weight:800;color:<?= $urgente_pa ? '#2ecc71' : '#d62bc5' ?>"><?= $proxima_aula['datetime']->format('H:i') ?></div>
                <div style="font-size:10px;color:var(--cinza);text-transform:uppercase;font-weight:600"><?= $proxima_aula['datetime']->format('d/m') ?></div>
            </div>
            <div>
                <div style="font-weight:800;font-size:15px"><?= e($proxima_aula['turma']['nome']) ?></div>
                <div style="font-size:13px;color:var(--cinza);margin-top:4px"><i class="fas fa-clock" style="color:#7b2cbf;margin-right:5px"></i> <?= e($tempo_pa) ?></div>
                <?php if (!empty($proxima_aula['turma']['professores_nomes'])): ?>
                    <div style="font-size:12px;color:var(--cinza);margin-top:3px"><i class="fas fa-user-tie" style="color:#d62bc5;margin-right:5px"></i> <?= e($proxima_aula['turma']['professores_nomes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($urgente_pa): ?>
            <div style="margin-top:14px;background:rgba(46,204,113,.08);border:1px dashed #2ecc71;border-radius:10px;padding:10px;text-align:center;font-size:12px;color:#2ecc71;font-weight:700"><i class="fas fa-fire-alt" style="margin-right:5px"></i> É hoje! Prepare-se e não falte!</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Dica do Dia -->
    <div class="card" style="border-left:4px solid #FF8C00;background:linear-gradient(135deg,var(--card),rgba(255,140,0,.05))">
        <h3 class="card-titulo"><i class="fas fa-lightbulb" style="background:none;-webkit-text-fill-color:#FF8C00"></i> Dica do Dia</h3>
        <p style="font-size:14px;color:var(--txt);line-height:1.7;margin:0;font-weight:500"><?= e($dica_hoje) ?></p>
    </div>

    <!-- Ficha de Saúde -->
    <div class="card">
        <div class="ficha-toggle" id="ficha-toggle" onclick="toggleFicha()" role="button" tabindex="0" aria-expanded="false" aria-controls="ficha-campos">
            <h3 class="card-titulo" style="margin:0"><i class="fas fa-notes-medical" style="color:#d62bc5;background:none;-webkit-text-fill-color:#d62bc5"></i> Ficha de Saúde</h3>
            <div class="toggle-icon"><i class="fas fa-chevron-down"></i></div>
        </div>
        <div class="ficha-campos fechado" id="ficha-campos">
            <p style="font-size:12px;color:var(--cinza);margin:12px 0 15px">Mantenha a sua ficha médica atualizada.</p>
            <?php if ($msg_ficha): ?>
                <div class="msg-ok"><i class="fas fa-check-circle"></i> <?= e($msg_ficha) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="acao" value="atualizar_ficha">
                <div style="display:flex;gap:10px;margin-bottom:12px">
                    <div style="flex:1">
                        <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Data de Nascimento</label>
                        <input type="date" name="data_nascimento" value="<?= e($aluna['data_nascimento'] ?? '') ?>" style="margin-bottom:0">
                    </div>
                    <div style="flex:1">
                        <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Tipo Sanguíneo</label>
                        <select name="tipo_sanguineo" style="margin-bottom:0">
                            <option value="">Selecione</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                                <option value="<?= $ts ?>" <?= ($aluna['tipo_sanguineo'] ?? '') === $ts ? 'selected' : '' ?>><?= $ts ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-bottom:12px">
                    <div style="flex:1">
                        <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Peso (kg)</label>
                        <input type="number" step="0.1" name="peso" value="<?= e($aluna['peso'] ?? '') ?>" placeholder="Ex: 65.5" style="margin-bottom:0">
                    </div>
                    <div style="flex:1">
                        <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Altura (cm)</label>
                        <input type="number" step="0.1" name="altura" value="<?= e($aluna['altura'] ?? '') ?>" placeholder="Ex: 168" style="margin-bottom:0">
                    </div>
                </div>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Nível de Experiência</label>
                <select name="nivel_experiencia">
                    <option value="iniciante"     <?= ($aluna['nivel_experiencia'] ?? 'iniciante') === 'iniciante'     ? 'selected' : '' ?>>Iniciante</option>
                    <option value="intermediario" <?= ($aluna['nivel_experiencia'] ?? '') === 'intermediario' ? 'selected' : '' ?>>Intermediário</option>
                    <option value="avancado"      <?= ($aluna['nivel_experiencia'] ?? '') === 'avancado'      ? 'selected' : '' ?>>Avançado</option>
                </select>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Objetivo do Treino</label>
                <textarea name="objetivo_treino" class="ficha-textarea" rows="2" placeholder="Ex: Perda de peso, competição, autodefesa..."><?= e($aluna['objetivo_treino'] ?? '') ?></textarea>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Restrições Médicas</label>
                <textarea name="restricoes" class="ficha-textarea" rows="2" placeholder="Ex: Sinto dor lombar, asma leve..."><?= e($aluna['restricoes_medicas'] ?? '') ?></textarea>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Doenças Crônicas</label>
                <textarea name="doencas_cronicas" class="ficha-textarea" rows="2" placeholder="Ex: Hipertensão, diabetes..."><?= e($aluna['doencas_cronicas'] ?? '') ?></textarea>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Medicamentos em Uso</label>
                <textarea name="medicamentos_uso" class="ficha-textarea" rows="2" placeholder="Ex: Losartana, metformina..."><?= e($aluna['medicamentos_uso'] ?? '') ?></textarea>
                <label style="font-size:11px;color:var(--cinza);display:block;margin-bottom:4px;text-transform:uppercase;font-weight:600">Histórico de Lesões</label>
                <textarea name="historico_lesoes" class="ficha-textarea" rows="2" placeholder="Ex: Fratura no tornozelo em 2022..."><?= e($aluna['historico_lesoes'] ?? '') ?></textarea>
                <div style="font-size:12px;color:#d62bc5;text-transform:uppercase;font-weight:800;letter-spacing:1px;margin:15px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--borda)"><i class="fas fa-phone-alt"></i> Contato de Emergência</div>
                <div style="display:flex;gap:10px">
                    <input type="text" name="emergencia_nome" value="<?= e($aluna['emergencia_nome'] ?? '') ?>" placeholder="Nome do Contato" style="flex:1;margin-bottom:12px">
                    <input type="text" name="emergencia_telefone" value="<?= e($aluna['emergencia_telefone'] ?? '') ?>" placeholder="Telefone" style="flex:1;margin-bottom:12px">
                </div>
                <button type="submit" class="btn-salvar"><i class="fas fa-save"></i> Atualizar Ficha</button>
            </form>
        </div><!-- /ficha-campos -->
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
    <div class="card" id="card-evolucao">
        <h3 class="card-titulo"><i class="fas fa-chart-line"></i> Evolução</h3>
        <div class="xp-box" style="margin-bottom:15px">
            <div class="xp-texto">
                <span>Nível <?= $nivel ?></span>
                <span style="color:#d62bc5"><?= $xp ?> / <?= $xp_prox_nivel ?> XP</span>
            </div>
            <div class="xp-barra">
                <div class="xp-fill" style="width:<?= $porcentagem_xp ?>%"></div>
            </div>
        </div>
        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
            <div style="flex:1;min-width:100px;background:#050308;border:1px solid var(--borda);border-radius:12px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#d62bc5"><?= $treinos_totais ?></div>
                <div style="font-size:11px;color:var(--cinza);text-transform:uppercase;font-weight:600">Treinos</div>
            </div>
            <div style="flex:1;min-width:100px;background:#050308;border:1px solid var(--borda);border-radius:12px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#f1c40f"><?= count($medalhas) ?></div>
                <div style="font-size:11px;color:var(--cinza);text-transform:uppercase;font-weight:600">Medalhas</div>
            </div>
            <div style="flex:1;min-width:100px;background:#050308;border:1px solid var(--borda);border-radius:12px;padding:14px;text-align:center">
                <?php
                $faltas_mes = count(array_filter($presencas_mes, fn($p) => !(int)$p['presente']));
                $presentes_mes = count(array_filter($presencas_mes, fn($p) => (int)$p['presente']));
                ?>
                <div style="font-size:26px;font-weight:800;color:<?= $faltas_mes === 0 ? '#2ecc71' : '#ff4444' ?>"><?= $faltas_mes ?></div>
                <div style="font-size:11px;color:var(--cinza);text-transform:uppercase;font-weight:600">Faltas/mês</div>
            </div>
        </div>

        <!-- Presença do mês (calendário visual) -->
        <?php if (!empty($presencas_mes)): ?>
        <div style="font-size:12px;color:#d62bc5;text-transform:uppercase;font-weight:800;letter-spacing:1px;margin-bottom:10px"><i class="fas fa-calendar-check"></i> Frequência em <?= date('m/Y') ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:15px">
            <?php foreach ($presencas_mes as $pm): ?>
                <div style="width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;<?= (int)$pm['presente'] ? 'background:rgba(46,204,113,.2);color:#2ecc71;border:1px solid #2ecc71' : 'background:rgba(255,68,68,.2);color:#ff4444;border:1px solid #ff4444' ?>" title="<?= date('d/m', strtotime($pm['data_presenca'])) ?>">
                    <?= date('d', strtotime($pm['data_presenca'])) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Conquistas acumuladas -->
        <?php if (!empty($medalhas)): ?>
        <div style="font-size:12px;color:#f1c40f;text-transform:uppercase;font-weight:800;letter-spacing:1px;margin-bottom:10px"><i class="fas fa-trophy"></i> Conquistas</div>
        <div class="grid-medalhas">
            <?php foreach ($medalhas as $m): ?>
            <div class="medalha">
                <div class="medalha-icone"><?= $m['icone_emoji'] ?></div>
                <div class="medalha-nome"><?= e($m['nome_medalha']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Progressão de Ranks -->
        <div style="font-size:12px;color:#7b2cbf;text-transform:uppercase;font-weight:800;letter-spacing:1px;margin:15px 0 10px"><i class="fas fa-gem"></i> Jornada de Ranks</div>
        <?php
        $ranks_prog = [
            ['nome' => 'Aprendiz',   'gem' => 'Safira',   'xp_min' => 0,    'xp_max' => 199,  'cor' => '#3b82f6', 'rgba' => '59,130,246',  'emoji' => '💎'],
            ['nome' => 'Focada',     'gem' => 'Ametista', 'xp_min' => 200,  'xp_max' => 499,  'cor' => '#a855f7', 'rgba' => '168,85,247',  'emoji' => '🔮'],
            ['nome' => 'Guerreira',  'gem' => 'Rubi',     'xp_min' => 500,  'xp_max' => 999,  'cor' => '#ef4444', 'rgba' => '239,68,68',   'emoji' => '❤️‍🔥'],
            ['nome' => 'Imparável',  'gem' => 'Diamante', 'xp_min' => 1000, 'xp_max' => 1999, 'cor' => '#06b6d4', 'rgba' => '6,182,212',   'emoji' => '💠'],
            ['nome' => 'Lenda',      'gem' => 'Elite',    'xp_min' => 2000, 'xp_max' => null,  'cor' => '#f1c40f', 'rgba' => '241,196,15',  'emoji' => '👑'],
        ];
        foreach ($ranks_prog as $rp):
            $atual = $xp >= $rp['xp_min'] && ($rp['xp_max'] === null || $xp <= $rp['xp_max']);
            $concluido = $rp['xp_max'] !== null && $xp > $rp['xp_max'];
            $falta_r = $rp['xp_max'] !== null && !$concluido ? ($rp['xp_max'] + 1 - $xp) . ' XP para avançar' : ($rp['xp_max'] === null ? 'Rank máximo!' : 'Concluído ✓');
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px;border-radius:12px;margin-bottom:8px;background:<?= $atual ? 'rgba(' . $rp['rgba'] . ',.12)' : 'rgba(255,255,255,.02)' ?>;border:1px solid <?= $atual ? $rp['cor'] : 'rgba(255,255,255,.06)' ?>;<?= $atual ? 'box-shadow:0 4px 14px rgba(0,0,0,.4)' : '' ?>">
            <div style="font-size:22px;width:36px;text-align:center;flex-shrink:0;opacity:<?= $concluido || $atual ? '1' : '.35' ?>"><?= $rp['emoji'] ?></div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:800;font-size:13px;color:<?= $atual ? $rp['cor'] : ($concluido ? '#2ecc71' : 'var(--cinza)') ?>"><?= $rp['emoji'] ?> <?= $rp['nome'] ?> <span style="font-weight:400;font-size:11px">(<?= $rp['gem'] ?>)</span></div>
                <div style="font-size:11px;color:var(--cinza);margin-top:2px"><?= $rp['xp_max'] !== null ? $rp['xp_min'] . ' – ' . $rp['xp_max'] . ' XP' : $rp['xp_min'] . '+ XP' ?> &nbsp;·&nbsp; <span style="color:<?= $atual ? $rp['cor'] : ($concluido ? '#2ecc71' : 'var(--cinza)') ?>"><?= $falta_r ?></span></div>
            </div>
            <?php if ($atual): ?>
                <div style="background:<?= $rp['cor'] ?>;color:#000;font-size:10px;font-weight:800;padding:4px 10px;border-radius:8px;white-space:nowrap;flex-shrink:0">ATUAL</div>
            <?php elseif ($concluido): ?>
                <div style="color:#2ecc71;font-size:16px;flex-shrink:0">✓</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Brindes / Roleta -->
    <?php if (!empty($brindes_aluna)): ?>
    <div class="card" style="border-color:#f1c40f;background:linear-gradient(180deg,var(--card),rgba(241,196,15,.05))">
        <h3 class="card-titulo" style="color:#f1c40f"><i class="fas fa-gift" style="background:none;-webkit-text-fill-color:#f1c40f"></i> Meus Brindes</h3>

        <?php if ($brinde_roleta): ?>
        <div style="text-align:center;margin-bottom:20px">
            <p style="font-size:13px;color:#f1c40f;font-weight:700;margin-bottom:10px">🎉 Parabéns! Você completou o mês sem faltar! Gire a roleta para ganhar o seu prêmio!</p>
            <div id="roleta-container" style="position:relative;width:220px;height:220px;margin:0 auto 15px">
                <canvas id="roletaCanvas" width="220" height="220" style="border-radius:50%;border:3px solid #f1c40f;box-shadow:0 0 20px rgba(241,196,15,.5)"></canvas>
                <div id="roleta-ponteiro" style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);width:0;height:0;border-left:10px solid transparent;border-right:10px solid transparent;border-bottom:24px solid #f1c40f;filter:drop-shadow(0 2px 4px rgba(0,0,0,.5))"></div>
            </div>
            <button id="btnGirar" onclick="girarRoleta()" style="background:linear-gradient(90deg,#f1c40f,#e67e22);color:#000;border:none;padding:14px 28px;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;transition:.3s;box-shadow:0 5px 20px rgba(241,196,15,.4)"><i class="fas fa-sync-alt"></i> Girar!</button>
            <form id="formRoleta" method="POST" style="display:none">
                <input type="hidden" name="acao" value="girar_roleta">
                <input type="hidden" name="ba_id" value="<?= (int)$brinde_roleta['id'] ?>">
                <input type="hidden" name="brinde_id" id="roletaBrindeId" value="">
            </form>
        </div>
        <?php endif; ?>

        <?php foreach ($brindes_aluna as $ba): ?>
        <div style="background:rgba(255,255,255,.02);border:1px solid <?= $ba['entregue'] ? '#2ecc71' : ($ba['roleta_girada'] ? '#d62bc5' : '#f1c40f') ?>;padding:12px;border-radius:12px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <div style="font-weight:700;font-size:13px">
                        <?php $t = $ba['brinde_nome'] ?? $ba['brinde_manual'] ?? ''; ?>
                        <?= $ba['entregue'] ? '✅' : ($ba['roleta_girada'] ? '🎁' : '🎰') ?> <?= $t ? e($t) : 'Prêmio do mês' ?>
                    </div>
                    <div style="font-size:11px;color:var(--cinza)"><i class="fas fa-calendar-alt"></i> <?= e($ba['mes_referencia']) ?></div>
                </div>
                <span style="font-size:11px;font-weight:800;padding:3px 8px;border-radius:6px;<?= $ba['entregue'] ? 'background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid #2ecc71' : ($ba['roleta_girada'] ? 'background:rgba(214,43,197,.15);color:#d62bc5;border:1px solid #d62bc5' : 'background:rgba(241,196,15,.15);color:#f1c40f;border:1px solid #f1c40f') ?>">
                    <?= $ba['entregue'] ? 'Entregue' : ($ba['roleta_girada'] ? 'Aguardando' : 'Gire!') ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($minhas_turmas_aluno)): ?>
    <!-- Minha Turma -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-layer-group"></i> Minha Turma</h3>
        <?php foreach ($minhas_turmas_aluno as $mt): ?>
            <div style="background:rgba(255,255,255,.02);border:1px solid var(--borda);padding:16px;border-radius:12px;margin-bottom:12px">
                <div style="font-weight:800;font-size:15px;margin-bottom:6px"><?= e($mt['nome']) ?></div>
                <?php if (!empty($mt['dia_semana'])): ?>
                    <div style="font-size:13px;color:var(--cinza);margin-bottom:4px"><i class="fas fa-clock" style="color:#7b2cbf;margin-right:6px"></i> <?= e($mt['dia_semana'] . ' — ' . $mt['horario']) ?></div>
                <?php endif; ?>
                <?php if (!empty($mt['professores_nomes'])): ?>
                    <div style="font-size:13px;color:var(--cinza)"><i class="fas fa-user-tie" style="color:#d62bc5;margin-right:6px"></i> <?= e($mt['professores_nomes']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

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

        <h4 style="font-size:14px;margin:15px 0 10px;text-transform:uppercase">Histórico de Renovações</h4>
        <?php $fp_labels = ['pix'=>'PIX','credito'=>'Cartão Crédito','debito'=>'Cartão Débito','dinheiro'=>'Dinheiro']; ?>
        <div style="background:#050308;padding:0 15px;border-radius:12px;border:1px solid var(--borda)">
            <?php foreach ($historico_pag as $hp): ?>
                <div class="hist-item">
                    <div>
                        <div style="color:#fff;font-weight:700;font-size:13px"><?= e($hp['nome_plano']) ?></div>
                        <div><i class="fas fa-calendar-alt" style="margin-right:4px;color:#d62bc5"></i> <?= date('d/m/Y', strtotime($hp['data_pagamento'])) ?> → Vence: <?= date('d/m/Y', strtotime($hp['data_vencimento'])) ?></div>
                        <div style="margin-top:3px">
                            <span style="background:rgba(214,43,197,.15);color:#d62bc5;border:1px solid #d62bc5;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800"><?= e($fp_labels[$hp['forma_pagamento'] ?? 'pix']) ?></span>
                            <?php if (!empty($hp['treinador_nome'])): ?>
                                <span style="font-size:11px;color:#888;margin-left:6px"><i class="fas fa-user-tie"></i> <?= e($hp['treinador_nome']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
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
// ---- Ficha de Saúde toggle ----
function toggleFicha() {
    var campos  = document.getElementById('ficha-campos');
    var toggle  = document.getElementById('ficha-toggle');
    var aberto  = !campos.classList.contains('fechado');
    if (aberto) {
        campos.style.maxHeight = campos.scrollHeight + 'px';
        requestAnimationFrame(function(){
            campos.style.maxHeight = '0';
            campos.classList.add('fechado');
            toggle.classList.remove('aberto');
            toggle.setAttribute('aria-expanded', 'false');
        });
    } else {
        campos.classList.remove('fechado');
        campos.style.maxHeight = campos.scrollHeight + 'px';
        toggle.classList.add('aberto');
        toggle.setAttribute('aria-expanded', 'true');
        // Remove fixed max-height after animation so content can resize
        campos.addEventListener('transitionend', function handler() {
            campos.style.maxHeight = 'none';
            campos.removeEventListener('transitionend', handler);
        });
    }
}
// Open ficha automatically if there's a success message
<?php if ($msg_ficha): ?>
document.addEventListener('DOMContentLoaded', function(){ toggleFicha(); });
<?php endif; ?>

// ---- PWA Install banner ----
var pwaPrompt = null;
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    pwaPrompt = e;
    var banner = document.getElementById('pwa-banner');
    if (banner && !sessionStorage.getItem('pwa-dismissed')) {
        banner.style.display = 'flex';
    }
});
document.getElementById('pwa-install-btn').addEventListener('click', function() {
    if (pwaPrompt) {
        pwaPrompt.prompt();
        pwaPrompt.userChoice.then(function(r) {
            if (r.outcome === 'accepted') fecharBanner();
            pwaPrompt = null;
        });
    }
});
function fecharBanner() {
    var banner = document.getElementById('pwa-banner');
    if (banner) banner.style.display = 'none';
    sessionStorage.setItem('pwa-dismissed', '1');
}

// ---- Service Worker ----
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}

// ---- Existing functions ----
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
            title: 'Passe Livre - Elite Thai Girls',
            text: 'Vem treinar comigo na Elite Thai Girls! Resgata o teu Passe Livre VIP:',
            url: document.getElementById('linkConvite').value
        }).catch(function(){});
    } else {
        copiarLink(document.querySelector('.btn-copiar'));
        alert('Link copiado!');
    }
}

// Roulette wheel
<?php if ($brinde_roleta && !empty($brindes_lista)): ?>
(function(){
    var premios = <?= json_encode(array_column($brindes_lista, 'nome')) ?>;
    var ids     = <?= json_encode(array_column($brindes_lista, 'id')) ?>;
    var canvas  = document.getElementById('roletaCanvas');
    if (!canvas) return;
    var ctx    = canvas.getContext('2d');
    var arc    = Math.PI * 2 / premios.length;
    var colors = ['#d62bc5','#7b2cbf','#FF8C00','#3498db','#2ecc71','#e74c3c','#f1c40f','#1abc9c'];
    var currentAngle = 0;
    var spinning     = false;

    function drawWheel(angle) {
        ctx.clearRect(0,0,220,220);
        for (var i = 0; i < premios.length; i++) {
            ctx.beginPath();
            ctx.moveTo(110,110);
            ctx.arc(110,110,108, angle + arc*i, angle + arc*(i+1));
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();
            ctx.strokeStyle = '#09060f';
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.save();
            ctx.translate(110,110);
            ctx.rotate(angle + arc*i + arc/2);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 11px Poppins,sans-serif';
            var txt = premios[i].length > 12 ? premios[i].substring(0,12)+'\u2026' : premios[i];
            ctx.fillText(txt, 100, 5);
            ctx.restore();
        }
    }

    drawWheel(0);

    window.girarRoleta = function() {
        if (spinning) return;
        spinning = true;
        document.getElementById('btnGirar').disabled = true;
        var extra    = Math.PI * 2 * (5 + Math.floor(Math.random()*5));
        var ganhou   = Math.floor(Math.random() * premios.length);
        var finalAngle = extra + (Math.PI * 2 - arc * ganhou - arc/2);
        var start    = null;
        var duration = 4000;

        function animate(ts) {
            if (!start) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var ease     = 1 - Math.pow(1 - progress, 4);
            currentAngle = ease * finalAngle;
            drawWheel(currentAngle);
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                spinning = false;
                var nome = premios[ganhou];
                var bId  = ids[ganhou] || '';
                document.getElementById('roletaBrindeId').value = bId;
                setTimeout(function(){
                    // Show toast notification instead of alert
                    var toast = document.createElement('div');
                    toast.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:linear-gradient(135deg,#1a0a2e,#2a1b3d);border:2px solid #f1c40f;border-radius:20px;padding:28px 32px;z-index:9999;text-align:center;color:#fff;font-family:Poppins,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.8);max-width:320px;width:90%';
                    toast.innerHTML = '<div style="font-size:48px;margin-bottom:12px">🎉</div><div style="font-size:18px;font-weight:800;color:#f1c40f;margin-bottom:8px">Você ganhou!</div><div style="font-size:15px;margin-bottom:20px">' + nome + '</div><div style="font-size:12px;color:#b5a8c9;margin-bottom:16px">Aguarde a entrega do seu prêmio!</div><button onclick="this.parentNode.remove();document.getElementById(\'formRoleta\').submit();" style="background:linear-gradient(90deg,#f1c40f,#e67e22);color:#000;border:none;padding:12px 28px;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer">✔ Ok, obrigada!</button>';
                    document.body.appendChild(toast);
                }, 200);
            }
        }
        requestAnimationFrame(animate);
    };
})();
<?php endif; ?>
</script>
</body>
</html>
