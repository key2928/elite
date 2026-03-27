<?php
require 'config.php';

if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$msg_sucesso = '';
$msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Cadastrar Usuário
    if ($acao === 'add_usuario') {
        $senha_hash = password_hash($_POST['senha'] ?? '', PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, tipo) VALUES (?,?,?,?,?)")
                ->execute([$_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', $senha_hash, $_POST['tipo'] ?? 'aluno']);
            $msg_sucesso = 'Usuário cadastrado com sucesso!';
        } catch (PDOException $e) {
            $msg_erro = 'Erro: Este e-mail já está cadastrado.';
        }
    }

    // 2. Excluir Usuário
    if ($acao === 'excluir_usuario') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Usuário removido.';
    }

    // 3. Adicionar Plano
    if ($acao === 'add_plano') {
        $pdo->prepare("INSERT INTO planos_tabela (nome_plano, valor, duracao_meses) VALUES (?,?,?)")
            ->execute([$_POST['nome_plano'] ?? '', $_POST['valor'] ?? 0, (int)($_POST['duracao'] ?? 1)]);
        $msg_sucesso = 'Plano criado com sucesso!';
    }

    // 4. Adicionar Horário
    if ($acao === 'add_horario') {
        $pdo->prepare("INSERT INTO horarios_treino (dia_semana, horario, descricao) VALUES (?,?,?)")
            ->execute([$_POST['dia'] ?? '', $_POST['hora'] ?? '', $_POST['desc'] ?? '']);
        $msg_sucesso = 'Horário adicionado!';
    }

    // 5. Excluir Horário
    if ($acao === 'excluir_horario') {
        $pdo->prepare("DELETE FROM horarios_treino WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Horário removido.';
    }

    // 6. Adicionar Aviso
    if ($acao === 'add_aviso') {
        $pdo->prepare("INSERT INTO mural_avisos (autor_id, titulo, mensagem, tipo) VALUES (?,?,?,?)")
            ->execute([$_SESSION['usuario_id'], $_POST['titulo'] ?? '', $_POST['mensagem'] ?? '', $_POST['tipo'] ?? 'info']);
        $msg_sucesso = 'Aviso publicado!';
    }

    // 7. Atualizar Lead
    if ($acao === 'atualizar_lead') {
        $pdo->prepare("UPDATE leads_indicacoes SET status = ? WHERE id = ?")
            ->execute([$_POST['status'] ?? 'novo', (int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Status da indicação atualizado!';
    }
}

// Dados para a tela
$mes_atual  = date('m');
$ano_atual  = date('Y');
$stmt = $pdo->prepare("SELECT SUM(valor_pago) FROM pagamentos WHERE MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?");
$stmt->execute([$mes_atual, $ano_atual]);
$total_caixa = $stmt->fetchColumn() ?: 0;

$pagamentos = $pdo->query("SELECT p.*, u.nome as aluna_nome, pl.nome_plano FROM pagamentos p JOIN usuarios u ON p.aluna_id = u.id JOIN planos_tabela pl ON p.plano_id = pl.id ORDER BY p.data_pagamento DESC LIMIT 50")->fetchAll();
$usuarios   = $pdo->query("SELECT * FROM usuarios ORDER BY tipo, nome")->fetchAll();
$planos     = $pdo->query("SELECT * FROM planos_tabela")->fetchAll();
$horarios   = $pdo->query("SELECT * FROM horarios_treino")->fetchAll();

$leads = [];
try {
    $leads = $pdo->query("SELECT l.*, u.nome as quem_indicou FROM leads_indicacoes l JOIN usuarios u ON l.aluna_id_indicou = u.id ORDER BY l.data_indicacao DESC")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel Admin - Elite Thai</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#09060f;--card:#140d1c;--pink:linear-gradient(90deg,#d62bc5,#7b2cbf);--glow:rgba(214,43,197,.35);--txt:#f8f9fa;--cinza:#b5a8c9;--borda:#2a1b3d}
        *{box-sizing:border-box}
        body{background:var(--bg);color:var(--txt);font-family:'Poppins',sans-serif;margin:0;padding:20px;padding-bottom:80px}
        .app{max-width:800px;margin:0 auto}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;background:var(--card);padding:15px 20px;border-radius:20px;border:1px solid var(--borda)}
        .header h1{margin:0;font-size:18px;text-transform:uppercase;font-weight:800}
        .header span{color:#d62bc5}
        .btn-sair{background:#2a1b3d;color:#ff4d4d;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:16px;transition:.3s}
        .alerta{background:rgba(46,204,113,.1);border:1px solid #2ecc71;color:#2ecc71;padding:15px;border-radius:12px;margin-bottom:20px;font-weight:600;text-align:center}
        .alerta-erro{background:rgba(255,68,68,.1);border:1px solid #ff4444;color:#ff4444;padding:15px;border-radius:12px;margin-bottom:20px;font-weight:600;text-align:center}
        .tabs-menu{display:flex;gap:10px;overflow-x:auto;padding-bottom:10px;margin-bottom:20px;-webkit-overflow-scrolling:touch}
        .tabs-menu::-webkit-scrollbar{display:none}
        .tab-btn{background:#050308;border:1px solid var(--borda);color:var(--cinza);padding:12px 20px;border-radius:12px;font-family:'Poppins';font-weight:600;cursor:pointer;transition:.3s;white-space:nowrap;font-size:13px}
        .tab-btn.ativo{background:var(--pink);color:#fff;border-color:transparent;box-shadow:0 5px 15px var(--glow)}
        .tab-content{display:none;animation:fadeIn .3s ease-in-out}
        .tab-content.ativo{display:block}
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .card{background:var(--card);padding:24px;border-radius:20px;margin-bottom:20px;border:1px solid var(--borda);box-shadow:0 10px 30px rgba(0,0,0,.5)}
        .card-titulo{margin:0 0 18px;font-size:15px;text-transform:uppercase;font-weight:800;display:flex;align-items:center;gap:10px;letter-spacing:1px}
        .card-titulo i{color:#d62bc5;font-size:20px}
        input,select,textarea{width:100%;padding:15px;margin-bottom:12px;border-radius:12px;border:1px solid var(--borda);background:#050308;color:#fff;font-family:'Poppins',sans-serif;font-size:14px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#d62bc5}
        .btn-submit{background:var(--pink);color:#fff;border:none;padding:16px;width:100%;border-radius:12px;font-weight:800;text-transform:uppercase;cursor:pointer;transition:.3s;box-shadow:0 5px 15px var(--glow);font-size:14px;letter-spacing:1px}
        .btn-submit:hover{transform:translateY(-2px)}
        .item-lista{background:rgba(255,255,255,.02);border:1px solid var(--borda);padding:15px;border-radius:12px;margin-bottom:12px}
        .item-topo{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
        .item-nome{font-weight:800;font-size:15px}
        .badge{padding:4px 10px;border-radius:8px;font-size:11px;font-weight:800;text-transform:uppercase}
        .badge-admin{background:rgba(214,43,197,.2);color:#d62bc5;border:1px solid #d62bc5}
        .badge-treinador{background:rgba(255,140,0,.2);color:#FF8C00;border:1px solid #FF8C00}
        .badge-professor{background:rgba(52,152,219,.2);color:#3498db;border:1px solid #3498db}
        .badge-instrutor{background:rgba(26,188,156,.2);color:#1abc9c;border:1px solid #1abc9c}
        .badge-aluno{background:rgba(46,204,113,.2);color:#2ecc71;border:1px solid #2ecc71}
        .btn-excluir{background:rgba(255,68,68,.1);color:#ff4444;border:none;padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer;transition:.3s}
        .btn-excluir:hover{background:#ff4444;color:#fff}
        .alerta-saude{background:rgba(214,43,197,.1);border-left:3px solid #d62bc5;padding:10px;border-radius:8px;font-size:12px;color:#fff;margin-top:10px;line-height:1.5}
        .status-novo{color:#f1c40f}.status-contatado{color:#3498db}.status-matriculado{color:#2ecc71}
    </style>
</head>
<body>
<div class="app">

    <div class="header">
        <h1><span>Elite</span> Admin</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if ($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= e($msg_sucesso) ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= e($msg_erro) ?></div><?php endif; ?>

    <div class="tabs-menu">
        <button class="tab-btn ativo" onclick="openTab('caixa', this)"><i class="fas fa-wallet"></i> Caixa</button>
        <button class="tab-btn" onclick="openTab('leads', this)"><i class="fas fa-ticket-alt"></i> Indicações VIP</button>
        <button class="tab-btn" onclick="openTab('equipe', this)"><i class="fas fa-users"></i> Equipa & Alunos</button>
        <button class="tab-btn" onclick="openTab('horarios', this)"><i class="fas fa-clock"></i> Horários</button>
        <button class="tab-btn" onclick="openTab('planos', this)"><i class="fas fa-tags"></i> Planos</button>
        <button class="tab-btn" onclick="openTab('mural', this)"><i class="fas fa-bullhorn"></i> Mural</button>
    </div>

    <!-- CAIXA -->
    <div id="caixa" class="tab-content ativo">
        <div class="card" style="border-color:#2ecc71;background:linear-gradient(180deg,var(--card),rgba(46,204,113,.05))">
            <h3 class="card-titulo" style="color:#2ecc71"><i class="fas fa-chart-line" style="color:#2ecc71"></i> Receita do Mês</h3>
            <div style="font-size:36px;font-weight:800">R$ <?= number_format($total_caixa, 2, ',', '.') ?></div>
            <p style="font-size:12px;color:var(--cinza);margin:0">Entradas em <?= date('m/Y') ?></p>
        </div>
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-list"></i> Últimos Pagamentos</h3>
            <div style="max-height:400px;overflow-y:auto">
                <?php foreach ($pagamentos as $pag): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($pag['aluna_nome']) ?></span>
                            <span style="color:#2ecc71;font-weight:800">R$ <?= number_format($pag['valor_pago'], 2, ',', '.') ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--cinza)">
                            <i class="fas fa-tag"></i> <?= e($pag['nome_plano']) ?> |
                            <i class="fas fa-calendar-check"></i> Vence: <?= date('d/m/Y', strtotime($pag['data_vencimento'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($pagamentos)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhum pagamento registado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LEADS -->
    <div id="leads" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-ticket-alt"></i> Convidados (Leads)</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Pessoas que resgataram o Passe Livre.</p>
            <div style="max-height:500px;overflow-y:auto">
                <?php foreach ($leads as $lead): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($lead['nome_convidada']) ?></span>
                            <span class="badge status-<?= e($lead['status']) ?>"><i class="fas fa-circle"></i> <?= e($lead['status']) ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--cinza);margin-bottom:10px">
                            <i class="fab fa-whatsapp" style="color:#2ecc71"></i> <?= e($lead['telefone_convidada']) ?><br>
                            <i class="fas fa-gift" style="color:#d62bc5"></i> Indicado por: <strong><?= e($lead['quem_indicou']) ?></strong>
                        </div>
                        <form method="POST" style="display:flex;gap:10px">
                            <input type="hidden" name="acao" value="atualizar_lead">
                            <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                            <select name="status" style="margin:0;padding:8px;font-size:12px">
                                <option value="novo" <?= $lead['status'] === 'novo' ? 'selected' : '' ?>>Novo</option>
                                <option value="contatado" <?= $lead['status'] === 'contatado' ? 'selected' : '' ?>>Contactado</option>
                                <option value="matriculado" <?= $lead['status'] === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
                            </select>
                            <button type="submit" class="btn-submit" style="padding:8px;font-size:12px;width:auto"><i class="fas fa-save"></i></button>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone_convidada']) ?>" target="_blank" class="btn-submit" style="padding:8px;font-size:12px;width:auto;background:#25D366;text-decoration:none;text-align:center;box-shadow:none"><i class="fab fa-whatsapp"></i></a>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhuma indicação ainda.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- EQUIPE -->
    <div id="equipe" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-user-plus"></i> Novo Cadastro</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="add_usuario">
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <input type="email" name="email" placeholder="E-mail / Login" required>
                <input type="text" name="telefone" placeholder="WhatsApp">
                <input type="password" name="senha" placeholder="Senha de Acesso" required>
                <select name="tipo" required>
                    <option value="" disabled selected>Nível de Acesso</option>
                    <option value="aluno">Aluno(a)</option>
                    <option value="professor">Professor(a)</option>
                    <option value="treinador">Treinador(a)</option>
                    <option value="instrutor">Instrutor(a)</option>
                    <option value="admin">Administrador(a)</option>
                </select>
                <button type="submit" class="btn-submit">Cadastrar</button>
            </form>
        </div>
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-users"></i> Usuários do Sistema</h3>
            <div style="max-height:500px;overflow-y:auto">
                <?php foreach ($usuarios as $u): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($u['nome']) ?></span>
                            <span class="badge badge-<?= e($u['tipo']) ?>"><?= e($u['tipo']) ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--cinza);margin-bottom:5px">
                            <i class="fas fa-envelope"></i> <?= e($u['email']) ?> | <i class="fab fa-whatsapp"></i> <?= e($u['telefone'] ?? 'N/A') ?>
                        </div>
                        <?php if (in_array($u['tipo'], ['aluno']) && !empty($u['restricoes_medicas'])): ?>
                            <div class="alerta-saude">
                                <strong><i class="fas fa-notes-medical"></i> Ficha Médica:</strong><br>
                                <?= nl2br(e($u['restricoes_medicas'])) ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top:10px;text-align:right">
                            <form method="POST" onsubmit="return confirm('Excluir este usuário?')">
                                <input type="hidden" name="acao" value="excluir_usuario">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i> Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($usuarios)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhum usuário cadastrado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- HORÁRIOS -->
    <div id="horarios" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-clock"></i> Gerir Horários</h3>
            <form method="POST" style="margin-bottom:20px">
                <input type="hidden" name="acao" value="add_horario">
                <input type="text" name="dia" placeholder="Dia (Ex: Segunda-feira)" required>
                <input type="text" name="hora" placeholder="Horário (Ex: 20:30 às 21:30)" required>
                <input type="text" name="desc" placeholder="Descrição (Ex: Treino Feminino)" required>
                <button type="submit" class="btn-submit">Adicionar</button>
            </form>
            <?php foreach ($horarios as $h): ?>
                <div class="item-lista item-topo">
                    <div>
                        <span class="item-nome"><?= e($h['dia_semana']) ?></span><br>
                        <span style="font-size:12px;color:var(--cinza)"><?= e($h['horario']) ?> - <?= e($h['descricao']) ?></span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="acao" value="excluir_horario">
                        <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                        <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PLANOS -->
    <div id="planos" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-tags"></i> Criar Plano</h3>
            <form method="POST" style="margin-bottom:20px">
                <input type="hidden" name="acao" value="add_plano">
                <input type="text" name="nome_plano" placeholder="Nome do Plano (Ex: Trimestral VIP)" required>
                <input type="number" step="0.01" name="valor" placeholder="Valor (R$)" required>
                <input type="number" name="duracao" placeholder="Duração em Meses" required>
                <button type="submit" class="btn-submit">Gravar Plano</button>
            </form>
            <h3 class="card-titulo" style="margin-top:30px">Planos Ativos</h3>
            <?php foreach ($planos as $p): ?>
                <div class="item-lista item-topo">
                    <div>
                        <span class="item-nome"><?= e($p['nome_plano']) ?></span><br>
                        <span style="font-size:12px;color:#2ecc71;font-weight:bold">R$ <?= number_format($p['valor'], 2, ',', '.') ?> (<?= (int)$p['duracao_meses'] ?> meses)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MURAL -->
    <div id="mural" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-bullhorn"></i> Mural de Avisos</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Avisos que aparecem na tela dos alunos.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="add_aviso">
                <input type="text" name="titulo" placeholder="Título do Aviso" required>
                <textarea name="mensagem" rows="3" placeholder="Mensagem..." required></textarea>
                <select name="tipo">
                    <option value="info">Informativo</option>
                    <option value="urgente">⚠️ Urgente</option>
                </select>
                <button type="submit" class="btn-submit">Publicar</button>
            </form>
        </div>
    </div>

</div>

<script>
function openTab(tabName, btn) {
    var contents = document.getElementsByClassName('tab-content');
    for (var i = 0; i < contents.length; i++) {
        contents[i].style.display = 'none';
        contents[i].classList.remove('ativo');
    }
    var btns = document.getElementsByClassName('tab-btn');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove('ativo');
    }
    document.getElementById(tabName).style.display = 'block';
    document.getElementById(tabName).classList.add('ativo');
    btn.classList.add('ativo');
}
</script>
</body>
</html>
