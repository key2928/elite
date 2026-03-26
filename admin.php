<?php
// Proteção e Alertas
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// CORREÇÃO DO ERRO DA IMAGEM: Verifica se a sessão já existe antes de iniciar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Só a Admin entra aqui
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$msg_sucesso = '';
$msg_erro = '';

// ==========================================
// PROCESSAMENTO DE FORMULÁRIOS (CRUD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Cadastrar Usuário (Aluna, Treinadora ou Admin)
    if (isset($_POST['acao']) && $_POST['acao'] == 'add_usuario') {
        $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, tipo) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_POST['nome'], $_POST['email'], $_POST['telefone'], $senha_hash, $_POST['tipo']]);
            $msg_sucesso = "Usuário cadastrado com sucesso!";
        } catch(PDOException $e) {
            $msg_erro = "Erro: Este e-mail já está cadastrado no sistema.";
        }
    }

    // 2. Excluir Usuário
    if (isset($_POST['acao']) && $_POST['acao'] == 'excluir_usuario') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$_POST['id']]);
        $msg_sucesso = "Usuário removido do sistema.";
    }

    // 3. Adicionar Plano
    if (isset($_POST['acao']) && $_POST['acao'] == 'add_plano') {
        $pdo->prepare("INSERT INTO planos_tabela (nome_plano, valor, duracao_meses) VALUES (?, ?, ?)")
            ->execute([$_POST['nome_plano'], $_POST['valor'], $_POST['duracao']]);
        $msg_sucesso = "Novo plano criado com sucesso!";
    }

    // 4. Adicionar Horário
    if (isset($_POST['acao']) && $_POST['acao'] == 'add_horario') {
        $pdo->prepare("INSERT INTO horarios_treino (dia_semana, horario, descricao) VALUES (?, ?, ?)")
            ->execute([$_POST['dia'], $_POST['hora'], $_POST['desc']]);
        $msg_sucesso = "Horário adicionado à grade!";
    }

    // 5. Excluir Horário
    if (isset($_POST['acao']) && $_POST['acao'] == 'excluir_horario') {
        $pdo->prepare("DELETE FROM horarios_treino WHERE id = ?")->execute([$_POST['id']]);
        $msg_sucesso = "Horário removido da grade.";
    }

    // 6. Adicionar Aviso no Mural
    if (isset($_POST['acao']) && $_POST['acao'] == 'add_aviso') {
        $pdo->prepare("INSERT INTO mural_avisos (autor_id, titulo, mensagem, tipo) VALUES (?, ?, ?, ?)")
            ->execute([$_SESSION['usuario_id'], $_POST['titulo'], $_POST['mensagem'], $_POST['tipo']]);
        $msg_sucesso = "Aviso publicado no aplicativo das alunas!";
    }

    // 7. Atualizar Status do Lead (Indicação VIP)
    if (isset($_POST['acao']) && $_POST['acao'] == 'atualizar_lead') {
        $pdo->prepare("UPDATE leads_indicacoes SET status = ? WHERE id = ?")
            ->execute([$_POST['status'], $_POST['id']]);
        $msg_sucesso = "Status da indicação atualizado!";
    }
}

// ==========================================
// BUSCAS NO BANCO DE DADOS PARA A TELA
// ==========================================
// Total do Caixa (Mês Atual)
$mes_atual = date('m');
$ano_atual = date('Y');
$total_caixa = $pdo->query("SELECT SUM(valor_pago) FROM pagamentos WHERE MONTH(data_pagamento) = $mes_atual AND YEAR(data_pagamento) = $ano_atual")->fetchColumn();
$total_caixa = $total_caixa ? $total_caixa : 0;

$pagamentos = $pdo->query("SELECT p.*, u.nome as aluna_nome, pl.nome_plano FROM pagamentos p JOIN usuarios u ON p.aluna_id = u.id JOIN planos_tabela pl ON p.plano_id = pl.id ORDER BY p.data_pagamento DESC LIMIT 50")->fetchAll();
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY tipo, nome")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos_tabela")->fetchAll();
$horarios = $pdo->query("SELECT * FROM horarios_treino")->fetchAll();

// Leads (Novas Indicações do Link VIP)
$leads = [];
try { 
    $leads = $pdo->query("SELECT l.*, u.nome as quem_indicou FROM leads_indicacoes l JOIN usuarios u ON l.aluna_id_indicou = u.id ORDER BY l.data_indicacao DESC")->fetchAll(); 
} catch(Exception $e) {}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel Admin - Konex Creative</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-fundo: #09060f; --bg-card: #140d1c; --pink-grad: linear-gradient(90deg, #d62bc5, #7b2cbf); --pink-glow: rgba(214, 43, 197, 0.35); --texto-claro: #f8f9fa; --texto-cinza: #b5a8c9; --borda: #2a1b3d; }
        * { box-sizing: border-box; }
        body { background: var(--bg-fundo); color: var(--texto-claro); font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; padding-bottom: 80px; -webkit-font-smoothing: antialiased;}
        .app-container { max-width: 800px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: var(--bg-card); padding: 15px 20px; border-radius: 20px; border: 1px solid var(--borda); }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; font-weight: 800; color: #fff; }
        .header span { color: #d62bc5; }
        .btn-sair { background: #2a1b3d; color: #ff4d4d; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 16px; transition: 0.3s; }

        .alerta { background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; text-align: center; }
        .alerta-erro { background: rgba(255, 68, 68, 0.1); border: 1px solid #ff4444; color: #ff4444; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; text-align: center; }

        /* Menu de Abas (Navegação Rápida) */
        .tabs-menu { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 20px; -webkit-overflow-scrolling: touch; }
        .tabs-menu::-webkit-scrollbar { display: none; }
        .tab-btn { background: #050308; border: 1px solid var(--borda); color: var(--texto-cinza); padding: 12px 20px; border-radius: 12px; font-family: 'Poppins'; font-weight: 600; cursor: pointer; transition: 0.3s; white-space: nowrap; font-size: 13px;}
        .tab-btn.ativo { background: var(--pink-grad); color: #fff; border-color: transparent; box-shadow: 0 5px 15px var(--pink-glow); }

        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.ativo { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .card { background: var(--bg-card); padding: 24px; border-radius: 20px; margin-bottom: 20px; border: 1px solid var(--borda); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .card-titulo { margin: 0 0 18px 0; font-size: 15px; text-transform: uppercase; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #fff; letter-spacing: 1px;}
        .card-titulo i { color: #d62bc5; font-size: 20px; }

        /* Formulários */
        input, select, textarea { width: 100%; padding: 15px; margin-bottom: 12px; border-radius: 12px; border: 1px solid var(--borda); background: #050308; color: #fff; font-family: 'Poppins', sans-serif; font-size: 14px;}
        input:focus, select:focus, textarea:focus { outline: none; border-color: #d62bc5; }
        .btn-submit { background: var(--pink-grad); color: white; border: none; padding: 16px; width: 100%; border-radius: 12px; font-weight: 800; text-transform: uppercase; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px var(--pink-glow); font-size: 14px; letter-spacing: 1px;}
        .btn-submit:hover { transform: translateY(-2px); }

        /* Listas em Cartões (Para Mobile) */
        .item-lista { background: rgba(255,255,255,0.02); border: 1px solid var(--borda); padding: 15px; border-radius: 12px; margin-bottom: 12px; }
        .item-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .item-nome { font-weight: 800; font-size: 15px; color: #fff; }
        .badge { padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .badge-admin { background: rgba(214, 43, 197, 0.2); color: #d62bc5; border: 1px solid #d62bc5; }
        .badge-treinador { background: rgba(255, 140, 0, 0.2); color: #FF8C00; border: 1px solid #FF8C00; }
        .badge-professor { background: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid #3498db; }
        .badge-instrutor { background: rgba(26, 188, 156, 0.2); color: #1abc9c; border: 1px solid #1abc9c; }
        .badge-aluno { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .badge-aluna { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        
        .btn-excluir { background: rgba(255, 68, 68, 0.1); color: #ff4444; border: none; padding: 8px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; transition: 0.3s; }
        .btn-excluir:hover { background: #ff4444; color: #fff; }

        .alerta-saude { background: rgba(214, 43, 197, 0.1); border-left: 3px solid #d62bc5; padding: 10px; border-radius: 8px; font-size: 12px; color: #fff; margin-top: 10px; line-height: 1.5; }
        
        /* Status Leads */
        .status-novo { color: #f1c40f; } .status-contatado { color: #3498db; } .status-matriculado { color: #2ecc71; }
    </style>
</head>
<body>
<div class="app-container">

    <div class="header">
        <h1><span>Konex</span> Admin</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= $msg_sucesso ?></div><?php endif; ?>
    <?php if($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= $msg_erro ?></div><?php endif; ?>

    <div class="tabs-menu">
        <button class="tab-btn ativo" onclick="openTab('caixa')"><i class="fas fa-wallet"></i> Caixa</button>
        <button class="tab-btn" onclick="openTab('leads')"><i class="fas fa-ticket-alt"></i> Indicações VIP</button>
        <button class="tab-btn" onclick="openTab('equipe')"><i class="fas fa-users"></i> Equipa & Alunas</button>
        <button class="tab-btn" onclick="openTab('horarios')"><i class="fas fa-clock"></i> Horários</button>
        <button class="tab-btn" onclick="openTab('planos')"><i class="fas fa-tags"></i> Planos</button>
        <button class="tab-btn" onclick="openTab('mural')"><i class="fas fa-bullhorn"></i> Mural</button>
    </div>

    <div id="caixa" class="tab-content ativo">
        <div class="card" style="border-color: #2ecc71; background: linear-gradient(180deg, var(--bg-card) 0%, rgba(46, 204, 113, 0.05) 100%);">
            <h3 class="card-titulo" style="color: #2ecc71;"><i class="fas fa-chart-line" style="color: #2ecc71;"></i> Receita do Mês</h3>
            <div style="font-size: 36px; font-weight: 800; color: #fff;">
                R$ <?= number_format($total_caixa, 2, ',', '.') ?>
            </div>
            <p style="font-size: 12px; color: var(--texto-cinza); margin: 0;">Entradas registadas em <?= date('F/Y') ?></p>
        </div>

        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-list"></i> Últimos Pagamentos</h3>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach($pagamentos as $pag): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= htmlspecialchars($pag['aluna_nome']) ?></span>
                            <span style="color: #2ecc71; font-weight: 800;">R$ <?= number_format($pag['valor_pago'], 2, ',', '.') ?></span>
                        </div>
                        <div style="font-size: 12px; color: var(--texto-cinza);">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($pag['nome_plano']) ?> | 
                            <i class="fas fa-calendar-check"></i> Vence: <?= date('d/m/Y', strtotime($pag['data_vencimento'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($pagamentos)): ?><p style="color: var(--texto-cinza); font-size: 13px; text-align: center;">Nenhum pagamento registado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div id="leads" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-ticket-alt"></i> Amigas Convidadas (Leads)</h3>
            <p style="font-size: 12px; color: var(--texto-cinza); margin-top: -10px; margin-bottom: 15px;">Pessoas que resgataram o Passe Livre através das alunas.</p>
            
            <div style="max-height: 500px; overflow-y: auto;">
                <?php foreach($leads as $lead): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= htmlspecialchars($lead['nome_convidada']) ?></span>
                            <span class="badge status-<?= $lead['status'] ?>"><i class="fas fa-circle"></i> <?= $lead['status'] ?></span>
                        </div>
                        <div style="font-size: 12px; color: var(--texto-cinza); margin-bottom: 10px;">
                            <i class="fab fa-whatsapp" style="color: #2ecc71;"></i> <?= htmlspecialchars($lead['telefone_convidada']) ?><br>
                            <i class="fas fa-gift" style="color: #d62bc5;"></i> Indicada por: <strong><?= htmlspecialchars($lead['quem_indicou']) ?></strong>
                        </div>
                        
                        <form method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="acao" value="atualizar_lead">
                            <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                            <select name="status" style="margin: 0; padding: 8px; font-size: 12px;">
                                <option value="novo" <?= $lead['status'] == 'novo' ? 'selected' : '' ?>>Novo</option>
                                <option value="contatado" <?= $lead['status'] == 'contatado' ? 'selected' : '' ?>>Contactada</option>
                                <option value="matriculado" <?= $lead['status'] == 'matriculado' ? 'selected' : '' ?>>Matriculada</option>
                            </select>
                            <button type="submit" class="btn-submit" style="padding: 8px; font-size: 12px; width: auto;"><i class="fas fa-save"></i></button>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['telefone_convidada']) ?>" target="_blank" class="btn-submit" style="padding: 8px; font-size: 12px; width: auto; background: #25D366; text-decoration: none; text-align: center; box-shadow:none;"><i class="fab fa-whatsapp"></i> Chamar</a>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($leads)): ?><p style="color: var(--texto-cinza); font-size: 13px; text-align: center;">Nenhuma indicação ainda. Incentive as alunas a partilharem o link!</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div id="equipe" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-user-plus"></i> Novo Cadastro</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="add_usuario">
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <input type="email" name="email" placeholder="E-mail / Login de Acesso" required>
                <input type="text" name="telefone" placeholder="WhatsApp (Recomendado)">
                <input type="password" name="senha" placeholder="Senha de Acesso" required>
                <select name="tipo" required>
                    <option value="" disabled selected>Selecione o Nível de Acesso</option>
                    <option value="aluno">Aluno(a)</option>
                    <option value="professor">Professor(a)</option>
                    <option value="treinador">Treinador(a)</option>
                    <option value="instrutor">Instrutor(a)</option>
                    <option value="admin">Administrador(a) — Master</option>
                </select>
                <button type="submit" class="btn-submit">Cadastrar no Sistema</button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-users"></i> Usuários do Sistema</h3>
            <div style="max-height: 500px; overflow-y: auto;">
                <?php foreach($usuarios as $u): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= htmlspecialchars($u['nome']) ?></span>
                            <span class="badge badge-<?= htmlspecialchars($u['tipo']) ?>"><?= htmlspecialchars($u['tipo']) ?></span>
                        </div>
                        <div style="font-size: 12px; color: var(--texto-cinza); margin-bottom: 5px;">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($u['email']) ?> | <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($u['telefone'] ?? 'N/A') ?>
                        </div>
                        
                        <?php if(in_array($u['tipo'], ['aluno','aluna']) && !empty($u['restricoes_medicas'])): ?>
                            <div class="alerta-saude">
                                <strong><i class="fas fa-notes-medical"></i> Ficha Médica:</strong><br>
                                <?= nl2br(htmlspecialchars($u['restricoes_medicas'])) ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 10px; text-align: right;">
                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja EXCLUIR este usuário?');">
                                <input type="hidden" name="acao" value="excluir_usuario">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i> Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($usuarios)): ?><p style="color: var(--texto-cinza); font-size: 13px; text-align: center;">Nenhum usuário cadastrado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <div id="horarios" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-clock"></i> Gerir Horários</h3>
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="acao" value="add_horario">
                <input type="text" name="dia" placeholder="Dia (Ex: Segunda-feira)" required>
                <input type="text" name="hora" placeholder="Horário (Ex: 20:30 às 21:30)" required>
                <input type="text" name="desc" placeholder="Descrição (Ex: Treino Feminino)" required>
                <button type="submit" class="btn-submit">Adicionar à Grade</button>
            </form>

            <?php foreach($horarios as $h): ?>
                <div class="item-lista item-topo">
                    <div>
                        <span class="item-nome"><?= htmlspecialchars($h['dia_semana']) ?></span><br>
                        <span style="font-size: 12px; color: var(--texto-cinza);"><?= htmlspecialchars($h['horario']) ?> - <?= htmlspecialchars($h['descricao']) ?></span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="acao" value="excluir_horario">
                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
                        <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="planos" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-tags"></i> Criar Novo Plano</h3>
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="acao" value="add_plano">
                <input type="text" name="nome_plano" placeholder="Nome do Plano (Ex: Trimestral VIP)" required>
                <input type="number" step="0.01" name="valor" placeholder="Valor Total (R$)" required>
                <input type="number" name="duracao" placeholder="Duração em Meses (Ex: 3)" required>
                <button type="submit" class="btn-submit">Gravar Plano</button>
            </form>

            <h3 class="card-titulo" style="margin-top: 30px;">Planos Ativos</h3>
            <?php foreach($planos as $p): ?>
                <div class="item-lista item-topo">
                    <div>
                        <span class="item-nome"><?= htmlspecialchars($p['nome_plano']) ?></span><br>
                        <span style="font-size: 12px; color: #2ecc71; font-weight: bold;">R$ <?= number_format($p['valor'], 2, ',', '.') ?> (Válido por <?= $p['duracao_meses'] ?> meses)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="mural" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-bullhorn"></i> Mural de Avisos</h3>
            <p style="font-size: 12px; color: var(--texto-cinza); margin-top:-10px; margin-bottom: 15px;">Estes recados aparecem na tela inicial de todas as alunas.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="add_aviso">
                <input type="text" name="titulo" placeholder="Título do Aviso" required>
                <textarea name="mensagem" rows="3" placeholder="Escreva a mensagem aqui..." required></textarea>
                <select name="tipo">
                    <option value="informativo">Informativo Normal</option>
                    <option value="urgente">⚠️ Aviso Urgente (Vermelho)</option>
                </select>
                <button type="submit" class="btn-submit">Publicar no App</button>
            </form>
        </div>
    </div>

</div>

<script>
function openTab(tabName) {
    // Esconde todos os conteúdos
    var contents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < contents.length; i++) {
        contents[i].style.display = "none";
        contents[i].classList.remove("ativo");
    }
    // Remove a cor ativa dos botões
    var btns = document.getElementsByClassName("tab-btn");
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove("ativo");
    }
    // Mostra a aba selecionada e pinta o botão
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("ativo");
    event.currentTarget.classList.add("ativo");
}
</script>

</body>
</html>