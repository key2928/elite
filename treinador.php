<?php
require 'config.php';

if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) {
    header('Location: login.php');
    exit;
}

$msg_sucesso = '';
$msg_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Marcar Presença
    if ($acao === 'add_treino') {
        $id_aluna = (int)($_POST['aluna_id'] ?? 0);
        $pdo->prepare("UPDATE usuarios SET treinos_concluidos = COALESCE(treinos_concluidos,0)+1, xp_atual = COALESCE(xp_atual,0)+20 WHERE id = ?")
            ->execute([$id_aluna]);
        $msg_sucesso = 'Presença confirmada! +1 treino e +20 XP.';
    }

    // 2. Dar Medalha
    if ($acao === 'feedback') {
        $medalha = explode('|', $_POST['medalha'] ?? '');
        if (count($medalha) === 2) {
            $pdo->prepare("INSERT INTO conquistas (aluna_id, treinador_id, nome_medalha, icone_emoji, xp_ganho) VALUES (?,?,?,?,50)")
                ->execute([$_POST['aluna_id'], $_SESSION['usuario_id'], $medalha[0], $medalha[1]]);
            $pdo->prepare("UPDATE usuarios SET xp_atual = COALESCE(xp_atual,0)+50 WHERE id = ?")
                ->execute([$_POST['aluna_id']]);
            $msg_sucesso = "Medalha de {$medalha[0]} enviada (+50 XP)!";
        }
    }

    // 3. Nova Missão
    if ($acao === 'nova_missao') {
        $pdo->prepare("UPDATE missoes_semana SET status = 'inativa' WHERE status = 'ativa'")->execute();
        $pdo->prepare("INSERT INTO missoes_semana (treinador_id, titulo, descricao) VALUES (?,?,?)")
            ->execute([$_SESSION['usuario_id'], $_POST['titulo'] ?? '', $_POST['descricao'] ?? '']);
        $msg_sucesso = 'Nova Missão da Semana lançada!';
    }

    // 4. Pagamento
    if ($acao === 'pagamento') {
        $plano_id = (int)($_POST['plano_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT duracao_meses FROM planos_tabela WHERE id = ?");
        $stmt->execute([$plano_id]);
        $duracao = $stmt->fetchColumn();
        if ($duracao) {
            $vencimento = date('Y-m-d', strtotime("+{$duracao} months"));
            $pdo->prepare("INSERT INTO pagamentos (aluna_id, treinador_id, plano_id, valor_pago, data_pagamento, data_vencimento, observacao_aluna, forma_pagamento) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$_POST['aluna_id'], $_SESSION['usuario_id'], $plano_id, $_POST['valor'] ?? 0, date('Y-m-d'), $vencimento, $_POST['obs'] ?? '', $_POST['forma_pagamento'] ?? 'pix']);
            $msg_sucesso = 'Pagamento registado com sucesso!';
        }
    }

    // 5. Cadastrar Aluno
    if ($acao === 'nova_aluna') {
        $senha_hash = password_hash($_POST['senha'] ?? '', PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, tipo, data_nascimento, tipo_sanguineo, peso, altura, doencas_cronicas, medicamentos_uso, historico_lesoes, emergencia_nome, emergencia_telefone, objetivo_treino, nivel_experiencia, restricoes_medicas) VALUES (?,?,?,?,'aluno',?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([
                    $_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', $senha_hash,
                    ($_POST['data_nascimento'] ?: null), ($_POST['tipo_sanguineo'] ?: null),
                    ($_POST['peso'] ?: null), ($_POST['altura'] ?: null),
                    ($_POST['doencas_cronicas'] ?: null), ($_POST['medicamentos_uso'] ?: null),
                    ($_POST['historico_lesoes'] ?: null), ($_POST['emergencia_nome'] ?: null),
                    ($_POST['emergencia_telefone'] ?: null), ($_POST['objetivo_treino'] ?: null),
                    ($_POST['nivel_experiencia'] ?: 'iniciante'), ($_POST['restricoes_medicas'] ?: null),
                ]);
            $msg_sucesso = 'Aluno(a) cadastrado(a) com sucesso!';
        } catch (PDOException $e) {
            $msg_erro = 'Erro: Este e-mail já está registado.';
        }
    }

    // 6. Editar Aluno
    if ($acao === 'editar_aluno') {
        $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=? WHERE id=?")
            ->execute([$_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', (int)($_POST['aluno_id'] ?? 0)]);
        $msg_sucesso = 'Dados atualizados com sucesso!';
    }

    // 7. Editar Ficha Médica
    if ($acao === 'editar_ficha_aluno') {
        $pdo->prepare("UPDATE usuarios SET data_nascimento=?, tipo_sanguineo=?, peso=?, altura=?, doencas_cronicas=?, medicamentos_uso=?, historico_lesoes=?, emergencia_nome=?, emergencia_telefone=?, objetivo_treino=?, nivel_experiencia=?, restricoes_medicas=? WHERE id=?")
            ->execute([
                ($_POST['data_nascimento'] ?: null), ($_POST['tipo_sanguineo'] ?: null),
                ($_POST['peso'] ?: null), ($_POST['altura'] ?: null),
                ($_POST['doencas_cronicas'] ?: null), ($_POST['medicamentos_uso'] ?: null),
                ($_POST['historico_lesoes'] ?: null), ($_POST['emergencia_nome'] ?: null),
                ($_POST['emergencia_telefone'] ?: null), ($_POST['objetivo_treino'] ?: null),
                ($_POST['nivel_experiencia'] ?: 'iniciante'), ($_POST['restricoes_medicas'] ?: null),
                (int)($_POST['aluno_id'] ?? 0),
            ]);
        $msg_sucesso = 'Ficha médica atualizada com sucesso!';
    }

    // 8. Excluir Aluno
    if ($acao === 'excluir_aluno') {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([(int)($_POST['excluir_id'] ?? 0)]);
        $msg_sucesso = 'Cadastro removido do sistema.';
    }
}

$alunas = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'aluno' ORDER BY nome")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos_tabela WHERE ativo = 1")->fetchAll();

$missao_atual = false;
try { $missao_atual = $pdo->query("SELECT * FROM missoes_semana WHERE status = 'ativa' ORDER BY id DESC LIMIT 1")->fetch(); } catch (Exception $e) {}

$historico_pagamentos = [];
try {
    $rows = $pdo->query("SELECT p.*, pl.nome_plano FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id ORDER BY p.data_pagamento DESC LIMIT 500")->fetchAll();
    foreach ($rows as $row) {
        $historico_pagamentos[(int)$row['aluna_id']][] = $row;
    }
} catch (Exception $e) {}

$forma_labels = ['pix' => 'PIX', 'credito' => 'Cartão Crédito', 'debito' => 'Cartão Débito', 'dinheiro' => 'Dinheiro'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel Treinador - Elite Thai</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#09060f;--card:#140d1c;--pink:linear-gradient(90deg,#d62bc5,#7b2cbf);--glow:rgba(214,43,197,.35);--txt:#f8f9fa;--cinza:#b5a8c9;--borda:#2a1b3d}
        *{box-sizing:border-box}
        body{background:var(--bg);color:var(--txt);font-family:'Poppins',sans-serif;margin:0;padding:20px;padding-bottom:60px}
        .app{max-width:600px;margin:0 auto}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;background:var(--card);padding:15px 20px;border-radius:20px;border:1px solid var(--borda)}
        .header h1{margin:0;font-size:16px;text-transform:uppercase;font-weight:800}
        .header span{color:#d62bc5}
        .btn-sair{background:#2a1b3d;color:#ff4d4d;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:16px;transition:.3s}
        .alerta{background:rgba(46,204,113,.1);border:1px solid #2ecc71;color:#2ecc71;padding:15px;border-radius:12px;margin-bottom:20px;font-weight:600;text-align:center;font-size:14px}
        .alerta-erro{background:rgba(255,68,68,.1);border:1px solid #ff4444;color:#ff4444;padding:15px;border-radius:12px;margin-bottom:20px;font-weight:600;text-align:center;font-size:14px}
        .card{background:var(--card);padding:24px;border-radius:20px;margin-bottom:20px;border:1px solid var(--borda);box-shadow:0 10px 30px rgba(0,0,0,.5)}
        .card-titulo{margin:0 0 18px;font-size:15px;text-transform:uppercase;font-weight:800;display:flex;align-items:center;gap:10px;letter-spacing:1px}
        .card-titulo i{background:var(--pink);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:20px}
        input,select,textarea{width:100%;padding:15px;margin-bottom:12px;border-radius:12px;border:1px solid var(--borda);background:#050308;color:#fff;font-family:'Poppins',sans-serif;font-size:14px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#d62bc5}
        .btn-submit{background:var(--pink);color:#fff;border:none;padding:16px;width:100%;border-radius:12px;font-weight:800;text-transform:uppercase;cursor:pointer;transition:.3s;box-shadow:0 5px 15px var(--glow);font-size:14px;letter-spacing:1px}
        .btn-submit:hover{transform:translateY(-2px)}
        .chip-container{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px}
        .chip{background:#050308;border:1px solid var(--borda);padding:12px;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;transition:.3s;flex-grow:1;text-align:center;color:var(--cinza)}
        .chip input{display:none}
        .chip:has(input:checked){background:var(--pink);border-color:transparent;box-shadow:0 5px 15px var(--glow);color:#fff}
        .aluna-item{background:rgba(255,255,255,.02);border:1px solid var(--borda);padding:15px;border-radius:12px;margin-bottom:12px}
        .aluna-topo{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .aluna-nome{font-weight:800;font-size:15px;text-transform:capitalize}
        .aluna-info{font-size:12px;color:var(--cinza);margin-bottom:5px}
        .btn-acao{background:#2a1b3d;color:#fff;border:none;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:.3s}
        .btn-editar:hover{background:#f1c40f;color:#000}
        .btn-excluir{color:#ff4444;background:rgba(255,68,68,.1)}
        .btn-excluir:hover{background:#ff4444;color:#fff}
        .alerta-saude{background:rgba(214,43,197,.1);border-left:3px solid #d62bc5;padding:12px;border-radius:8px;font-size:12px;color:#fff;margin-top:10px;line-height:1.5}
        #modalEdit{display:none;background:#050308;padding:15px;border-radius:12px;border:1px solid #d62bc5;margin-top:10px}
        .section-toggle{display:none;background:#050308;padding:15px;border-radius:12px;border:1px solid var(--borda);margin-top:10px}
        .hist-item{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px dashed var(--borda);font-size:12px;color:var(--cinza)}
        .hist-item:last-child{border-bottom:none}
        .hist-badge{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800;background:rgba(214,43,197,.15);color:#d62bc5;border:1px solid #d62bc5}
        .ficha-label{font-size:11px;color:var(--cinza);display:block;margin-bottom:3px;text-transform:uppercase;font-weight:600}
        .row-2{display:flex;gap:10px}.row-2 > *{flex:1;min-width:0}
        .section-title{font-size:12px;color:#d62bc5;text-transform:uppercase;font-weight:800;letter-spacing:1px;margin:15px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--borda)}
    </style>
</head>
<body>
<div class="app">

    <div class="header">
        <h1><span>Elite</span> Treinador</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if ($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= e($msg_sucesso) ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= e($msg_erro) ?></div><?php endif; ?>

    <!-- Presença -->
    <div class="card" style="border-left:4px solid #2ecc71">
        <h3 class="card-titulo"><i class="fas fa-id-card" style="background:none;-webkit-text-fill-color:#2ecc71"></i> Presença no Tatame</h3>
        <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">+1 treino no cartão e +20 XP.</p>
        <form method="POST">
            <input type="hidden" name="acao" value="add_treino">
            <select name="aluna_id" required>
                <option value="">Selecione o(a) aluno(a)...</option>
                <?php foreach ($alunas as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#11998e,#38ef7d);box-shadow:0 5px 15px rgba(56,239,125,.3);color:#000">
                <i class="fas fa-check"></i> Confirmar Presença
            </button>
        </form>
    </div>

    <!-- Missão -->
    <div class="card" style="border-color:#FF8C00">
        <h3 class="card-titulo" style="color:#FF8C00"><i class="fas fa-crosshairs" style="background:none;-webkit-text-fill-color:#FF8C00"></i> Lançar Missão</h3>
        <?php if ($missao_atual): ?>
            <p style="font-size:12px;color:#aaa;margin-top:-10px;margin-bottom:15px">Ativa: <strong><?= e($missao_atual['titulo']) ?></strong></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_missao">
            <input type="text" name="titulo" placeholder="Título (Ex: Guarda de Ferro)" required>
            <textarea name="descricao" rows="2" placeholder="Desafio tático para os alunos?" required></textarea>
            <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#FF8C00,#FF3D00);box-shadow:0 5px 15px rgba(255,140,0,.3)">
                🚀 Publicar
            </button>
        </form>
    </div>

    <!-- Gestão de Alunos -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-users"></i> Gestão de Alunos</h3>

        <div style="max-height:400px;overflow-y:auto;padding-right:5px;margin-bottom:20px">
            <?php foreach ($alunas as $a): ?>
                <div class="aluna-item">
                    <div class="aluna-topo">
                        <span class="aluna-nome"><?= e($a['nome']) ?></span>
                        <div style="display:flex;gap:5px">
                            <button onclick="toggleDiv('ficha-<?= (int)$a['id'] ?>')" class="btn-acao" title="Ficha Médica" style="color:#d62bc5"><i class="fas fa-notes-medical"></i></button>
                            <button onclick="toggleDiv('hist-<?= (int)$a['id'] ?>')" class="btn-acao" title="Histórico" style="color:#f1c40f"><i class="fas fa-history"></i></button>
                            <button onclick="abrirEdicao(<?= (int)$a['id'] ?>, <?= e(json_encode($a['nome'])) ?>, <?= e(json_encode($a['email'])) ?>, <?= e(json_encode($a['telefone'] ?? '')) ?>)" class="btn-acao btn-editar" title="Editar"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este registo?')">
                                <input type="hidden" name="acao" value="excluir_aluno">
                                <input type="hidden" name="excluir_id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="btn-acao btn-excluir" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div class="aluna-info"><i class="fas fa-envelope" style="color:#d62bc5;margin-right:5px"></i> <?= e($a['email']) ?></div>
                    <div class="aluna-info"><i class="fab fa-whatsapp" style="color:#2ecc71;margin-right:5px"></i> <?= e($a['telefone'] ?? 'Sem número') ?></div>
                    <div class="aluna-info"><i class="fas fa-hand-rock" style="color:#FF8C00;margin-right:5px"></i> Treinos: <strong><?= (int)($a['treinos_concluidos'] ?? 0) ?></strong></div>
                    <?php if (!empty($a['restricoes_medicas']) || !empty($a['doencas_cronicas'])): ?>
                        <div class="alerta-saude">
                            <strong><i class="fas fa-notes-medical"></i> Atenção Médica:</strong><br>
                            <?php if (!empty($a['restricoes_medicas'])): ?><?= nl2br(e($a['restricoes_medicas'])) ?><br><?php endif; ?>
                            <?php if (!empty($a['doencas_cronicas'])): ?><span style="color:#f1c40f">Doenças: <?= nl2br(e($a['doencas_cronicas'])) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Ficha Médica Muay Thai (colapsável) -->
                    <div id="ficha-<?= (int)$a['id'] ?>" class="section-toggle">
                        <div class="section-title"><i class="fas fa-notes-medical"></i> Ficha Médica Muay Thai</div>
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_ficha_aluno">
                            <input type="hidden" name="aluno_id" value="<?= (int)$a['id'] ?>">
                            <div class="row-2">
                                <div>
                                    <span class="ficha-label">Data de Nascimento</span>
                                    <input type="date" name="data_nascimento" value="<?= e($a['data_nascimento'] ?? '') ?>" style="margin-bottom:8px">
                                </div>
                                <div>
                                    <span class="ficha-label">Tipo Sanguíneo</span>
                                    <select name="tipo_sanguineo" style="margin-bottom:8px">
                                        <option value="">Selecione</option>
                                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                                            <option value="<?= $ts ?>" <?= ($a['tipo_sanguineo'] ?? '') === $ts ? 'selected' : '' ?>><?= $ts ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row-2">
                                <div>
                                    <span class="ficha-label">Peso (kg)</span>
                                    <input type="number" step="0.1" name="peso" value="<?= e($a['peso'] ?? '') ?>" placeholder="Ex: 65.5" style="margin-bottom:8px">
                                </div>
                                <div>
                                    <span class="ficha-label">Altura (cm)</span>
                                    <input type="number" step="0.1" name="altura" value="<?= e($a['altura'] ?? '') ?>" placeholder="Ex: 168" style="margin-bottom:8px">
                                </div>
                            </div>
                            <span class="ficha-label">Nível de Experiência</span>
                            <select name="nivel_experiencia" style="margin-bottom:8px">
                                <option value="iniciante"      <?= ($a['nivel_experiencia'] ?? 'iniciante') === 'iniciante'      ? 'selected' : '' ?>>Iniciante</option>
                                <option value="intermediario"  <?= ($a['nivel_experiencia'] ?? '') === 'intermediario'  ? 'selected' : '' ?>>Intermediário</option>
                                <option value="avancado"       <?= ($a['nivel_experiencia'] ?? '') === 'avancado'       ? 'selected' : '' ?>>Avançado</option>
                            </select>
                            <span class="ficha-label">Objetivo do Treino</span>
                            <textarea name="objetivo_treino" rows="2" placeholder="Ex: Perder peso, competição, autodefesa..."><?= e($a['objetivo_treino'] ?? '') ?></textarea>
                            <span class="ficha-label">Restrições Médicas</span>
                            <textarea name="restricoes_medicas" rows="2" placeholder="Ex: Dor lombar, asma..."><?= e($a['restricoes_medicas'] ?? '') ?></textarea>
                            <span class="ficha-label">Doenças Crônicas</span>
                            <textarea name="doencas_cronicas" rows="2" placeholder="Ex: Hipertensão, diabetes..."><?= e($a['doencas_cronicas'] ?? '') ?></textarea>
                            <span class="ficha-label">Medicamentos em Uso</span>
                            <textarea name="medicamentos_uso" rows="2" placeholder="Ex: Losartana, metformina..."><?= e($a['medicamentos_uso'] ?? '') ?></textarea>
                            <span class="ficha-label">Histórico de Lesões</span>
                            <textarea name="historico_lesoes" rows="2" placeholder="Ex: Fratura no tornozelo em 2022..."><?= e($a['historico_lesoes'] ?? '') ?></textarea>
                            <div class="section-title"><i class="fas fa-phone-alt"></i> Contato de Emergência</div>
                            <div class="row-2">
                                <div>
                                    <span class="ficha-label">Nome</span>
                                    <input type="text" name="emergencia_nome" value="<?= e($a['emergencia_nome'] ?? '') ?>" placeholder="Nome" style="margin-bottom:8px">
                                </div>
                                <div>
                                    <span class="ficha-label">Telefone</span>
                                    <input type="text" name="emergencia_telefone" value="<?= e($a['emergencia_telefone'] ?? '') ?>" placeholder="WhatsApp" style="margin-bottom:8px">
                                </div>
                            </div>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:var(--pink);font-size:13px;padding:12px"><i class="fas fa-save"></i> Salvar Ficha</button>
                                <button type="button" onclick="toggleDiv('ficha-<?= (int)$a['id'] ?>')" class="btn-submit" style="background:#333;box-shadow:none;font-size:13px;padding:12px">Fechar</button>
                            </div>
                        </form>
                    </div>

                    <!-- Histórico de Renovações (colapsável) -->
                    <div id="hist-<?= (int)$a['id'] ?>" class="section-toggle">
                        <div class="section-title"><i class="fas fa-history"></i> Histórico de Renovações</div>
                        <?php if (!empty($historico_pagamentos[(int)$a['id']])): ?>
                            <?php foreach ($historico_pagamentos[(int)$a['id']] as $hp): ?>
                                <div class="hist-item">
                                    <div>
                                        <div style="color:#fff;font-weight:700"><?= e($hp['nome_plano']) ?></div>
                                        <div><i class="fas fa-calendar-alt" style="color:#d62bc5;margin-right:4px"></i> <?= date('d/m/Y', strtotime($hp['data_pagamento'])) ?> → Vence: <?= date('d/m/Y', strtotime($hp['data_vencimento'])) ?></div>
                                        <div style="margin-top:4px">
                                            <span class="hist-badge"><?= e($forma_labels[$hp['forma_pagamento'] ?? 'pix']) ?></span>
                                        </div>
                                        <?php if (!empty($hp['observacao_aluna'])): ?>
                                            <div style="color:#888;margin-top:3px;font-style:italic"><?= e($hp['observacao_aluna']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align:right;white-space:nowrap">
                                        <span style="color:#2ecc71;font-weight:800;font-size:14px">R$ <?= number_format($hp['valor_pago'], 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size:12px;color:#555;text-align:center;margin:10px 0">Sem histórico de pagamentos.</p>
                        <?php endif; ?>
                        <button type="button" onclick="toggleDiv('hist-<?= (int)$a['id'] ?>')" class="btn-submit" style="background:#333;box-shadow:none;font-size:12px;padding:10px;margin-top:10px">Fechar</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($alunas)): ?>
                <p style="font-size:13px;color:var(--cinza);text-align:center">Nenhum(a) aluno(a) registado(a).</p>
            <?php endif; ?>
        </div>

        <div id="modalEdit">
            <h4 style="margin:0 0 10px;color:#f1c40f">Editar Aluno</h4>
            <form method="POST">
                <input type="hidden" name="acao" value="editar_aluno">
                <input type="hidden" name="aluno_id" id="edit_id">
                <input type="text" name="nome" id="edit_nome" required>
                <input type="email" name="email" id="edit_email" required>
                <input type="text" name="telefone" id="edit_telefone" placeholder="WhatsApp">
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none">Salvar</button>
                    <button type="button" onclick="document.getElementById('modalEdit').style.display='none'" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                </div>
            </form>
        </div>

        <hr style="border-color:var(--borda);margin:25px 0">

        <h4 style="margin:0 0 15px;font-size:14px;text-transform:uppercase"><i class="fas fa-user-plus" style="color:#d62bc5;margin-right:5px"></i> Nova Matrícula</h4>
        <form method="POST">
            <input type="hidden" name="acao" value="nova_aluna">
            <div class="section-title" style="margin-top:0"><i class="fas fa-user"></i> Dados de Acesso</div>
            <input type="text" name="nome" placeholder="Nome Completo" required>
            <input type="email" name="email" placeholder="E-mail de Acesso" required>
            <input type="text" name="telefone" placeholder="WhatsApp (Ex: 64999999999)">
            <input type="password" name="senha" placeholder="Senha Provisória" required>
            <div class="section-title"><i class="fas fa-notes-medical"></i> Ficha Médica Muay Thai</div>
            <div class="row-2">
                <div>
                    <span class="ficha-label">Data de Nascimento</span>
                    <input type="date" name="data_nascimento">
                </div>
                <div>
                    <span class="ficha-label">Tipo Sanguíneo</span>
                    <select name="tipo_sanguineo">
                        <option value="">Selecione</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $ts): ?>
                            <option value="<?= $ts ?>"><?= $ts ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row-2">
                <div>
                    <span class="ficha-label">Peso (kg)</span>
                    <input type="number" step="0.1" name="peso" placeholder="Ex: 65.5">
                </div>
                <div>
                    <span class="ficha-label">Altura (cm)</span>
                    <input type="number" step="0.1" name="altura" placeholder="Ex: 168">
                </div>
            </div>
            <span class="ficha-label">Nível de Experiência</span>
            <select name="nivel_experiencia">
                <option value="iniciante">Iniciante</option>
                <option value="intermediario">Intermediário</option>
                <option value="avancado">Avançado</option>
            </select>
            <span class="ficha-label">Objetivo do Treino</span>
            <textarea name="objetivo_treino" rows="2" placeholder="Ex: Perder peso, competição, autodefesa..."></textarea>
            <span class="ficha-label">Restrições Médicas</span>
            <textarea name="restricoes_medicas" rows="2" placeholder="Ex: Dor lombar, asma leve..."></textarea>
            <span class="ficha-label">Doenças Crônicas</span>
            <textarea name="doencas_cronicas" rows="2" placeholder="Ex: Hipertensão, diabetes..."></textarea>
            <span class="ficha-label">Medicamentos em Uso</span>
            <textarea name="medicamentos_uso" rows="2" placeholder="Ex: Losartana, metformina..."></textarea>
            <span class="ficha-label">Histórico de Lesões</span>
            <textarea name="historico_lesoes" rows="2" placeholder="Ex: Fratura no tornozelo em 2022..."></textarea>
            <div class="section-title"><i class="fas fa-phone-alt"></i> Contato de Emergência</div>
            <div class="row-2">
                <input type="text" name="emergencia_nome" placeholder="Nome do Contato">
                <input type="text" name="emergencia_telefone" placeholder="Telefone de Emergência">
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Cadastrar</button>
        </form>
    </div>

    <!-- Medalhas -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-medal"></i> Recompensar Aluno</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="feedback">
            <select name="aluna_id" required>
                <option value="">Quem brilhou no tatame?</option>
                <?php foreach ($alunas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?></option><?php endforeach; ?>
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

    <!-- Pagamento -->
    <div class="card">
        <h3 class="card-titulo"><i class="fas fa-wallet"></i> Receber Pagamento</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="pagamento">
            <select name="aluna_id" required>
                <option value="">Selecione o(a) Aluno(a)...</option>
                <?php foreach ($alunas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?></option><?php endforeach; ?>
            </select>
            <select name="plano_id" required>
                <option value="">Selecione o Plano...</option>
                <?php foreach ($planos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['nome_plano']) ?></option><?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="valor" placeholder="Valor recebido (R$)" required>
            <select name="forma_pagamento" required>
                <option value="pix">💳 PIX</option>
                <option value="credito">💳 Cartão de Crédito</option>
                <option value="debito">💳 Cartão de Débito</option>
                <option value="dinheiro">💵 Dinheiro</option>
            </select>
            <textarea name="obs" rows="2" placeholder="Observações (Opcional)"></textarea>
            <button type="submit" class="btn-submit" style="background:#2ecc71;box-shadow:0 5px 15px rgba(46,204,113,.3);color:#000"><i class="fas fa-file-invoice-dollar"></i> Registar Recibo</button>
        </form>
    </div>

</div>

<script>
function abrirEdicao(id, nome, email, telefone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nome').value = nome;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_telefone').value = telefone;
    var modal = document.getElementById('modalEdit');
    modal.style.display = 'block';
    modal.scrollIntoView({behavior:'smooth', block:'center'});
}
function toggleDiv(id) {
    var el = document.getElementById(id);
    el.style.display = el.style.display === 'block' ? 'none' : 'block';
    if (el.style.display === 'block') {
        el.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
}
</script>
</body>
</html>