<?php
// Proteção e Alertas
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Verificação de Acesso: Treinador, Professor ou Instrutor
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) { 
    header("Location: login.php"); 
    exit; 
}

$msg_sucesso = '';
$msg_erro = '';

// ==========================================
// PROCESSAMENTO DOS FORMULÁRIOS (AÇÕES)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Marcar Presença (Fidelidade + XP)
    if ($_POST['acao'] == 'add_treino') {
        $id_aluna = (int)$_POST['aluna_id'];
        $pdo->prepare("UPDATE usuarios SET treinos_concluidos = COALESCE(treinos_concluidos, 0) + 1, xp_atual = COALESCE(xp_atual, 0) + 20 WHERE id = ?")->execute([$id_aluna]);
        $msg_sucesso = "Presença confirmada! +1 no cartão fidelização e +20 XP para a aluna.";
    }

    // 2. Dar Feedback/Medalha
    if ($_POST['acao'] == 'feedback') {
        $medalha = explode("|", $_POST['medalha']);
        $pdo->prepare("INSERT INTO conquistas (aluna_id, treinador_id, nome_medalha, icone_emoji, xp_ganho) VALUES (?, ?, ?, ?, 50)")->execute([$_POST['aluna_id'], $_SESSION['usuario_id'], $medalha[0], $medalha[1]]);
        $pdo->prepare("UPDATE usuarios SET xp_atual = COALESCE(xp_atual, 0) + 50 WHERE id = ?")->execute([$_POST['aluna_id']]);
        $msg_sucesso = "Medalha de {$medalha[0]} enviada para a aluna (+50 XP)!";
    }

    // 3. Lançar Nova Missão da Semana
    if ($_POST['acao'] == 'nova_missao') {
        $pdo->query("UPDATE missoes_semana SET status = 'inativa' WHERE status = 'ativa'");
        $pdo->prepare("INSERT INTO missoes_semana (treinador_id, titulo, descricao) VALUES (?, ?, ?)")->execute([$_SESSION['usuario_id'], $_POST['titulo'], $_POST['descricao']]);
        $msg_sucesso = "Nova Missão da Semana lançada no aplicativo de todas as alunas!";
    }

    // 4. Receber Pagamento
    if ($_POST['acao'] == 'pagamento') {
        $plano_id = $_POST['plano_id'];
        $duracao = $pdo->query("SELECT duracao_meses FROM planos_tabela WHERE id = $plano_id")->fetchColumn();
        $vencimento = date('Y-m-d', strtotime("+$duracao months"));
        
        $pdo->prepare("INSERT INTO pagamentos (aluna_id, treinador_id, plano_id, valor_pago, data_pagamento, data_vencimento, observacao_aluna) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$_POST['aluna_id'], $_SESSION['usuario_id'], $plano_id, $_POST['valor'], date('Y-m-d'), $vencimento, $_POST['obs']]);
        $msg_sucesso = "Pagamento recebido com sucesso! O plano da aluna foi atualizado.";
    }

    // 5. Cadastrar Novo Aluno(a)
    if ($_POST['acao'] == 'nova_aluna') {
        $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, tipo) VALUES (?, ?, ?, ?, 'aluno')")->execute([$_POST['nome'], $_POST['email'], $_POST['telefone'], $senha_hash]);
            $msg_sucesso = "Novo(a) aluno(a) cadastrado(a) e pronto(a) para o tatame!";
        } catch(PDOException $e) {
            $msg_erro = "Erro: Este e-mail já está registado no sistema.";
        }
    }

    // 6. Editar Aluna
    if ($_POST['acao'] == 'editar_aluno') {
        $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?")->execute([$_POST['nome'], $_POST['email'], $_POST['telefone'], $_POST['aluno_id']]);
        $msg_sucesso = "Dados da aluna atualizados com sucesso!";
    }

    // 7. Excluir Aluna
    if ($_POST['acao'] == 'excluir_aluno') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$_POST['excluir_id']]);
        $msg_sucesso = "Cadastro da aluna removido do sistema.";
    }
}

// ==========================================
// BUSCAS NO BANCO DE DADOS
// ==========================================
$alunas = $pdo->query("SELECT * FROM usuarios WHERE tipo IN ('aluno','aluna') ORDER BY nome")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos_tabela WHERE ativo = 1")->fetchAll();

$missao_atual = false;
try {
    $missao_atual = $pdo->query("SELECT * FROM missoes_semana WHERE status = 'ativa' ORDER BY id DESC LIMIT 1")->fetch();
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel da Treinadora - Elite Thai</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --bg-fundo: #09060f; 
            --bg-card: #140d1c; 
            --pink-grad: linear-gradient(90deg, #d62bc5, #7b2cbf); 
            --pink-glow: rgba(214, 43, 197, 0.35); 
            --texto-claro: #f8f9fa; 
            --texto-cinza: #b5a8c9; 
            --borda: #2a1b3d; 
        }
        
        * { box-sizing: border-box; }
        body { background: var(--bg-fundo); color: var(--texto-claro); font-family: 'Poppins', sans-serif; margin: 0; padding: 20px; padding-bottom: 60px; -webkit-font-smoothing: antialiased;}
        .app-container { max-width: 600px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: var(--bg-card); padding: 15px 20px; border-radius: 20px; border: 1px solid var(--borda); }
        .header h1 { margin: 0; font-size: 16px; text-transform: uppercase; font-weight: 800; color: #fff; }
        .header span { color: #d62bc5; }
        .btn-sair { background: #2a1b3d; color: #ff4d4d; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 16px; transition: 0.3s; }
        
        .alerta { background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; text-align: center; font-size: 14px;}
        .alerta-erro { background: rgba(255, 68, 68, 0.1); border: 1px solid #ff4444; color: #ff4444; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; text-align: center; font-size: 14px;}

        .card { background: var(--bg-card); padding: 24px; border-radius: 20px; margin-bottom: 20px; border: 1px solid var(--borda); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .card-titulo { margin: 0 0 18px 0; font-size: 15px; text-transform: uppercase; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #fff; letter-spacing: 1px;}
        .card-titulo i { background: var(--pink-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px; }
        
        input, select, textarea { width: 100%; padding: 15px; margin-bottom: 12px; border-radius: 12px; border: 1px solid var(--borda); background: #050308; color: #fff; font-family: 'Poppins', sans-serif; font-size: 14px;}
        input:focus, select:focus, textarea:focus { outline: none; border-color: #d62bc5; }
        
        .btn-submit { background: var(--pink-grad); color: white; border: none; padding: 16px; width: 100%; border-radius: 12px; font-weight: 800; text-transform: uppercase; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px var(--pink-glow); font-size: 14px; letter-spacing: 1px;}
        .btn-submit:hover { transform: translateY(-2px); }

        .chip-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .chip { background: #050308; border: 1px solid var(--borda); padding: 12px; border-radius: 12px; cursor: pointer; font-size: 13px; font-weight: 600; transition: 0.3s; flex-grow: 1; text-align: center; color: var(--texto-cinza);}
        .chip input { display: none; }
        .chip:has(input:checked) { background: var(--pink-grad); border-color: transparent; box-shadow: 0 5px 15px var(--pink-glow); color: #fff;}

        /* Lista de Alunas */
        .aluna-item { background: rgba(255,255,255,0.02); border: 1px solid var(--borda); padding: 15px; border-radius: 12px; margin-bottom: 12px; }
        .aluna-topo { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .aluna-nome { font-weight: 800; font-size: 15px; color: #fff; text-transform: capitalize;}
        .aluna-info { font-size: 12px; color: var(--texto-cinza); margin-bottom: 5px; }
        
        /* Botões de Ação na Lista */
        .btn-acao { background: #2a1b3d; color: #fff; border: none; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .btn-editar:hover { background: #f1c40f; color: #000; }
        .btn-excluir { color: #ff4444; background: rgba(255, 68, 68, 0.1); }
        .btn-excluir:hover { background: #ff4444; color: #fff; }
        
        /* Alerta de Saúde (Anamnese) */
        .alerta-saude { background: rgba(214, 43, 197, 0.1); border-left: 3px solid #d62bc5; padding: 12px; border-radius: 8px; font-size: 12px; color: #fff; margin-top: 10px; line-height: 1.5; }
        
        /* Modal de Edição Invisível por Padrão */
        #modalEdit { display: none; background: #050308; padding: 15px; border-radius: 12px; border: 1px solid #d62bc5; margin-top: 10px; }
    </style>
</head>
<body>
<div class="app-container">

    <div class="header">
        <h1><span>Elite</span> Treinadora</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= $msg_sucesso ?></div><?php endif; ?>
    <?php if($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= $msg_erro ?></div><?php endif; ?>

    <div class="card" style="border-left: 4px solid #2ecc71;">
        <h3 class="card-titulo"><i class="fas fa-id-card" style="background:none; -webkit-text-fill-color: #2ecc71;"></i> Presença no Tatame</h3>
        <p style="font-size: 12px; color: var(--texto-cinza); margin-top:-10px; margin-bottom: 15px;">Adicione +1 treino no cartão da aluna e liberte XP.</p>
        <form method="POST">
            <input type="hidden" name="acao" value="add_treino">
            <select name="aluna_id" required>
                <option value="">Selecione a aluna presente...</option>
                <?php foreach($alunas as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-submit" style="background: linear-gradient(90deg, #11998e, #38ef7d); box-shadow: 0 5px 15px rgba(56, 239, 125, 0.3); color: #000;">
                <i class="fas fa-check"></i> Confirmar Presença
            </button>
        </form>
    </div>

    <div class="card" style="border-color: #FF8C00;">
        <h3 class="card-titulo" style="color: #FF8C00;"><i class="fas fa-crosshairs" style="background:none; -webkit-text-fill-color: #FF8C00;"></i> Lançar Missão</h3>
        <?php if($missao_atual): ?>
            <p style="font-size: 12px; color: #aaa; margin-top: -10px; margin-bottom: 15px;">Missão Ativa: <strong><?= htmlspecialchars($missao_atual['titulo']) ?></strong></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_missao">
            <input type="text" name="titulo" placeholder="Título (Ex: Guarda de Ferro)" required>
            <textarea name="descricao" rows="2" placeholder="Qual o desafio tático para as alunas?" required></textarea>
            <button type="submit" class="btn-submit" style="background: linear-gradient(90deg, #FF8C00, #FF3D00); box-shadow: 0 5px 15px rgba(255,140,0,0.3);">
                🚀 Publicar no App
            </button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-users"></i> Gestão de Alunas</h3>
        <p style="font-size: 12px; color: var(--texto-cinza); margin-top:-10px; margin-bottom: 15px;">Atenção à ficha de saúde antes do treino.</p>
        
        <div style="max-height: 400px; overflow-y: auto; padding-right: 5px; margin-bottom: 20px;">
            <?php foreach($alunas as $a): ?>
                <div class="aluna-item">
                    <div class="aluna-topo">
                        <span class="aluna-nome"><?= htmlspecialchars($a['nome']) ?></span>
                        <div style="display: flex; gap: 5px;">
                            <button onclick="abrirEdicao(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['nome'])) ?>', '<?= htmlspecialchars(addslashes($a['email'])) ?>', '<?= htmlspecialchars(addslashes($a['telefone'] ?? '')) ?>')" class="btn-acao btn-editar" title="Editar"><i class="fas fa-edit"></i></button>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja EXCLUIR o registo desta aluna?');">
                                <input type="hidden" name="acao" value="excluir_aluno">
                                <input type="hidden" name="excluir_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn-acao btn-excluir" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div class="aluna-info"><i class="fas fa-envelope" style="color:#d62bc5; margin-right: 5px;"></i> <?= htmlspecialchars($a['email']) ?></div>
                    <div class="aluna-info"><i class="fab fa-whatsapp" style="color:#2ecc71; margin-right: 5px;"></i> <?= htmlspecialchars($a['telefone'] ?? 'Sem número') ?></div>
                    <div class="aluna-info"><i class="fas fa-hand-rock" style="color:#FF8C00; margin-right: 5px;"></i> Treinos Concluídos: <strong><?= $a['treinos_concluidos'] ?? 0 ?></strong></div>
                    
                    <?php if(!empty($a['restricoes_medicas'])): ?>
                        <div class="alerta-saude">
                            <strong><i class="fas fa-notes-medical"></i> Atenção Médica:</strong><br>
                            <?= nl2br(htmlspecialchars($a['restricoes_medicas'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($alunas)): ?>
                <p style="font-size: 13px; color: var(--texto-cinza); text-align: center;">Nenhuma aluna registada.</p>
            <?php endif; ?>
        </div>

        <div id="modalEdit">
            <h4 style="margin: 0 0 10px 0; color: #f1c40f;">Editar Aluna</h4>
            <form method="POST">
                <input type="hidden" name="acao" value="editar_aluno">
                <input type="hidden" name="aluno_id" id="edit_id">
                <input type="text" name="nome" id="edit_nome" required>
                <input type="email" name="email" id="edit_email" required>
                <input type="text" name="telefone" id="edit_telefone" placeholder="WhatsApp">
                <div style="display:flex; gap: 10px;">
                    <button type="submit" class="btn-submit" style="background: #f1c40f; color:#000; box-shadow:none;">Salvar</button>
                    <button type="button" onclick="document.getElementById('modalEdit').style.display='none'" class="btn-submit" style="background: #333; box-shadow:none;">Cancelar</button>
                </div>
            </form>
        </div>

        <hr style="border-color: var(--borda); margin: 25px 0;">

        <h4 style="margin: 0 0 15px 0; color: #fff; text-transform: uppercase; font-size: 14px;"><i class="fas fa-user-plus" style="color:#d62bc5; margin-right: 5px;"></i> Nova Matrícula</h4>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_aluna">
            <input type="text" name="nome" placeholder="Nome Completo" required>
            <input type="email" name="email" placeholder="E-mail de Acesso" required>
            <input type="text" name="telefone" placeholder="WhatsApp (Ex: 64999999999)">
            <input type="password" name="senha" placeholder="Senha Provisória" required>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Cadastrar Aluna</button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-medal"></i> Recompensar Aluna</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="feedback">
            <select name="aluna_id" required>
                <option value="">Quem brilhou no tatame hoje?</option>
                <?php foreach($alunas as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nome']) ?></option><?php endforeach; ?>
            </select>
            <div class="chip-container">
                <label class="chip"><input type="radio" name="medalha" value="Gás Infinito|🫁" required> 🫁 Gás Infinito</label>
                <label class="chip"><input type="radio" name="medalha" value="Guarda de Ferro|🛡️"> 🛡️ Defesa</label>
                <label class="chip"><input type="radio" name="medalha" value="Chute Potente|💥"> 💥 Chute Forte</label>
                <label class="chip"><input type="radio" name="medalha" value="Foco Total|🧠"> 🧠 Foco Total</label>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-star"></i> Enviar Medalha (+50 XP)</button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-wallet"></i> Receber Pagamento</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="pagamento">
            <select name="aluna_id" required>
                <option value="">Selecione a Aluna...</option>
                <?php foreach($alunas as $a): ?><option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nome']) ?></option><?php endforeach; ?>
            </select>
            <select name="plano_id" required>
                <option value="">Selecione o Plano...</option>
                <?php foreach($planos as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome_plano']) ?></option><?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="valor" placeholder="Valor recebido (R$)" required>
            <textarea name="obs" rows="2" placeholder="Observações do plano (Opcional)"></textarea>
            <button type="submit" class="btn-submit" style="background: #2ecc71; box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3); color: #000;"><i class="fas fa-file-invoice-dollar"></i> Registar Recibo</button>
        </form>
    </div>

</div>

<script>
// Função para abrir o formulário de edição de alunas sem recarregar a página
function abrirEdicao(id, nome, email, telefone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nome').value = nome;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_telefone').value = telefone;
    
    let modal = document.getElementById('modalEdit');
    modal.style.display = 'block';
    
    // Rola a tela suavemente até ao formulário de edição
    modal.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

</body>
</html>