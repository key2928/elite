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
            $pdo->prepare("INSERT INTO usuarios (nome, email, telefone, senha, tipo, data_nascimento) VALUES (?,?,?,?,?,?)")
                ->execute([$_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', $senha_hash, $_POST['tipo'] ?? 'aluno', ($_POST['data_nascimento'] ?: null)]);
            $novo_id = $pdo->lastInsertId();
            if (!empty($_POST['turma_id']) && ($_POST['tipo'] ?? '') === 'aluno') {
                $pdo->prepare("INSERT IGNORE INTO aluno_turmas (aluno_id, turma_id) VALUES (?,?)")
                    ->execute([$novo_id, (int)$_POST['turma_id']]);
            }
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

    // 2b. Editar Usuário (nome, login, senha, telefone, nascimento, permissão)
    if ($acao === 'editar_usuario') {
        $id_edit    = (int)($_POST['id'] ?? 0);
        $nome       = trim($_POST['nome'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $telefone   = trim($_POST['telefone'] ?? '');
        $tipo       = $_POST['tipo'] ?? 'aluno';
        $data_nasc  = $_POST['data_nascimento'] ?: null;
        $nova_senha = $_POST['nova_senha'] ?? '';
        $tipos_validos = ['admin', 'professor', 'treinador', 'instrutor', 'aluno'];
        if ($id_edit && $nome !== '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && in_array($tipo, $tipos_validos, true)) {
            try {
                if ($nova_senha !== '') {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=?, tipo=?, data_nascimento=?, senha=? WHERE id=?")
                        ->execute([$nome, $email, $telefone ?: null, $tipo, $data_nasc, $senha_hash, $id_edit]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=?, tipo=?, data_nascimento=? WHERE id=?")
                        ->execute([$nome, $email, $telefone ?: null, $tipo, $data_nasc, $id_edit]);
                }
                $msg_sucesso = 'Usuário atualizado com sucesso!';
            } catch (PDOException $e) {
                $msg_erro = 'Erro: Este e-mail/login já está em uso por outro usuário.';
            }
        } else {
            $msg_erro = 'Dados inválidos. Verifique o nome, e-mail e o nível de permissão.';
        }
    }

    // 3. Adicionar Plano
    if ($acao === 'add_plano') {
        $pdo->prepare("INSERT INTO planos_tabela (nome_plano, valor, duracao_meses) VALUES (?,?,?)")
            ->execute([$_POST['nome_plano'] ?? '', $_POST['valor'] ?? 0, (int)($_POST['duracao'] ?? 1)]);
        $msg_sucesso = 'Plano criado com sucesso!';
    }

    // 4. Editar Plano
    if ($acao === 'editar_plano') {
        $pdo->prepare("UPDATE planos_tabela SET nome_plano=?, valor=?, duracao_meses=? WHERE id=?")
            ->execute([$_POST['nome_plano'] ?? '', $_POST['valor'] ?? 0, (int)($_POST['duracao_meses'] ?? 1), (int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Plano atualizado!';
    }

    // 5. Excluir Plano
    if ($acao === 'excluir_plano') {
        try {
            $pdo->prepare("DELETE FROM planos_tabela WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
            $msg_sucesso = 'Plano removido.';
        } catch (PDOException $e) {
            $msg_erro = 'Não é possível remover este plano — há pagamentos associados a ele.';
        }
    }

    // 6. Adicionar Horário
    if ($acao === 'add_horario') {
        $pdo->prepare("INSERT INTO horarios_treino (dia_semana, horario, descricao) VALUES (?,?,?)")
            ->execute([$_POST['dia'] ?? '', $_POST['hora'] ?? '', $_POST['desc'] ?? '']);
        $msg_sucesso = 'Horário adicionado!';
    }

    // 7. Excluir Horário
    if ($acao === 'excluir_horario') {
        $pdo->prepare("DELETE FROM horarios_treino WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Horário removido.';
    }

    // 7b. Editar Horário
    if ($acao === 'editar_horario') {
        $pdo->prepare("UPDATE horarios_treino SET dia_semana=?, horario=?, descricao=? WHERE id=?")
            ->execute([$_POST['dia'] ?? '', $_POST['hora'] ?? '', $_POST['desc'] ?? '', (int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Horário atualizado!';
    }

    // 8. Adicionar Turma
    if ($acao === 'add_turma') {
        $hor_id  = !empty($_POST['horario_id']) ? (int)$_POST['horario_id'] : null;
        $pdo->prepare("INSERT INTO turmas (nome, horario_id) VALUES (?,?)")
            ->execute([$_POST['nome'] ?? '', $hor_id]);
        $turma_id = (int)$pdo->lastInsertId();
        if (!empty($_POST['professor_ids']) && is_array($_POST['professor_ids'])) {
            $stmtTP = $pdo->prepare("INSERT IGNORE INTO turma_professores (turma_id, professor_id) VALUES (?,?)");
            foreach ($_POST['professor_ids'] as $pid) {
                $stmtTP->execute([$turma_id, (int)$pid]);
            }
        }
        $msg_sucesso = 'Turma criada com sucesso!';
    }

    // 9. Excluir Turma
    if ($acao === 'excluir_turma') {
        $pdo->prepare("DELETE FROM turmas WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Turma removida.';
    }

    // 9b. Editar Turma
    if ($acao === 'editar_turma') {
        $id_turma = (int)($_POST['id'] ?? 0);
        $hor_id   = !empty($_POST['horario_id']) ? (int)$_POST['horario_id'] : null;
        if ($id_turma) {
            $pdo->prepare("UPDATE turmas SET nome=?, horario_id=? WHERE id=?")
                ->execute([$_POST['nome'] ?? '', $hor_id, $id_turma]);
            $pdo->prepare("DELETE FROM turma_professores WHERE turma_id=?")->execute([$id_turma]);
            if (!empty($_POST['professor_ids']) && is_array($_POST['professor_ids'])) {
                $stmtTP = $pdo->prepare("INSERT IGNORE INTO turma_professores (turma_id, professor_id) VALUES (?,?)");
                foreach ($_POST['professor_ids'] as $pid) {
                    $stmtTP->execute([$id_turma, (int)$pid]);
                }
            }
            $msg_sucesso = 'Turma atualizada!';
        }
    }

    // 10. Adicionar Aviso
    if ($acao === 'add_aviso') {
        $pdo->prepare("INSERT INTO mural_avisos (autor_id, titulo, mensagem, tipo) VALUES (?,?,?,?)")
            ->execute([$_SESSION['usuario_id'], $_POST['titulo'] ?? '', $_POST['mensagem'] ?? '', $_POST['tipo'] ?? 'info']);
        $msg_sucesso = 'Aviso publicado!';
    }

    // 10b. Editar Aviso
    if ($acao === 'editar_aviso') {
        $pdo->prepare("UPDATE mural_avisos SET titulo=?, mensagem=?, tipo=? WHERE id=?")
            ->execute([$_POST['titulo'] ?? '', $_POST['mensagem'] ?? '', $_POST['tipo'] ?? 'info', (int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Aviso atualizado!';
    }

    // 10c. Excluir Aviso
    if ($acao === 'excluir_aviso') {
        $pdo->prepare("DELETE FROM mural_avisos WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Aviso removido!';
    }

    // 11. Atualizar Lead
    if ($acao === 'atualizar_lead') {
        $pdo->prepare("UPDATE leads_indicacoes SET status = ? WHERE id = ?")
            ->execute([$_POST['status'] ?? 'novo', (int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Status da indicação atualizado!';
    }

    // 11b. Excluir Lead
    if ($acao === 'excluir_lead') {
        $pdo->prepare("DELETE FROM leads_indicacoes WHERE id = ?")
            ->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Lead removido!';
    }

    // 12. Pagamento Admin
    if ($acao === 'add_pagamento_admin') {
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

    // 13. Adicionar Brinde (Admin)
    if ($acao === 'add_brinde_admin') {
        $nome = trim($_POST['nome_brinde'] ?? '');
        if ($nome !== '') {
            $pdo->prepare("INSERT INTO brindes (nome, descricao) VALUES (?,?)")
                ->execute([$nome, trim($_POST['desc_brinde'] ?? '')]);
            $msg_sucesso = 'Brinde adicionado!';
        }
    }

    // 14. Desativar Brinde (Admin)
    if ($acao === 'desativar_brinde_admin') {
        $pdo->prepare("UPDATE brindes SET ativo=0 WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Brinde removido.';
    }

    // 14b. Editar Brinde (Admin)
    if ($acao === 'editar_brinde_admin') {
        $nome = trim($_POST['nome_brinde'] ?? '');
        if ($nome !== '') {
            $pdo->prepare("UPDATE brindes SET nome=?, descricao=? WHERE id=?")
                ->execute([$nome, trim($_POST['desc_brinde'] ?? ''), (int)($_POST['id'] ?? 0)]);
            $msg_sucesso = 'Brinde atualizado!';
        }
    }

    // 15. Entregar Brinde (Admin)
    if ($acao === 'entregar_brinde_admin') {
        $pdo->prepare("UPDATE brindes_aluna SET entregue=1, data_entrega=NOW() WHERE id=?")
            ->execute([(int)($_POST['ba_id'] ?? 0)]);
        $msg_sucesso = 'Brinde marcado como entregue!';
    }

    // 16. Adicionar Pasta da Turma
    if ($acao === 'add_pasta') {
        $turma_id   = (int)($_POST['turma_id'] ?? 0);
        $titulo     = trim($_POST['titulo'] ?? 'Pasta da Turma');
        $descricao  = trim($_POST['descricao'] ?? '');
        $drive_link = trim($_POST['drive_link'] ?? '');
        if ($turma_id && $drive_link !== '') {
            $pdo->prepare("INSERT INTO pastas_turma (turma_id, titulo, descricao, drive_link) VALUES (?,?,?,?)")
                ->execute([$turma_id, $titulo ?: 'Pasta da Turma', $descricao, $drive_link]);
            $msg_sucesso = 'Pasta configurada com sucesso!';
        } else {
            $msg_erro = 'Selecione a turma e informe o link do Google Drive.';
        }
    }

    // 17. Editar Pasta da Turma
    if ($acao === 'editar_pasta') {
        $id_pasta   = (int)($_POST['id'] ?? 0);
        $titulo     = trim($_POST['titulo'] ?? 'Pasta da Turma');
        $descricao  = trim($_POST['descricao'] ?? '');
        $drive_link = trim($_POST['drive_link'] ?? '');
        if ($id_pasta && $drive_link !== '') {
            $pdo->prepare("UPDATE pastas_turma SET titulo=?, descricao=?, drive_link=? WHERE id=?")
                ->execute([$titulo ?: 'Pasta da Turma', $descricao, $drive_link, $id_pasta]);
            $msg_sucesso = 'Pasta atualizada!';
        }
    }

    // 18. Excluir Pasta da Turma
    if ($acao === 'excluir_pasta') {
        $pdo->prepare("DELETE FROM pastas_turma WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        $msg_sucesso = 'Pasta removida.';
    }
}

// ── Dados ───────────────────────────────────────────────────
$mes_atual = date('m');
$ano_atual = date('Y');

$stmt = $pdo->prepare("SELECT SUM(valor_pago) FROM pagamentos WHERE MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?");
$stmt->execute([$mes_atual, $ano_atual]);
$total_caixa = $stmt->fetchColumn() ?: 0;

$pagamentos  = $pdo->query("SELECT p.*, u.nome as aluna_nome, pl.nome_plano, t.nome as treinador_nome FROM pagamentos p JOIN usuarios u ON p.aluna_id = u.id JOIN planos_tabela pl ON p.plano_id = pl.id LEFT JOIN usuarios t ON p.treinador_id = t.id ORDER BY p.data_pagamento DESC LIMIT 50")->fetchAll();
$usuarios    = $pdo->query("SELECT * FROM usuarios ORDER BY tipo, nome")->fetchAll();
$planos      = $pdo->query("SELECT * FROM planos_tabela")->fetchAll();
$horarios    = $pdo->query("SELECT * FROM horarios_treino")->fetchAll();
$alunas      = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'aluno' ORDER BY nome")->fetchAll();
$professores = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo IN ('professor','treinador','instrutor') ORDER BY nome")->fetchAll();

$turmas = [];
try {
    $turmas = $pdo->query("SELECT t.*, GROUP_CONCAT(u.nome ORDER BY u.nome SEPARATOR ', ') as professores_nomes, h.dia_semana, h.horario FROM turmas t LEFT JOIN turma_professores tp ON t.id = tp.turma_id LEFT JOIN usuarios u ON tp.professor_id = u.id LEFT JOIN horarios_treino h ON t.horario_id = h.id WHERE t.ativo = 1 GROUP BY t.id ORDER BY t.nome")->fetchAll();
} catch (Exception $e) {}

// Pre-fetch all turma-professor relationships to avoid N+1 queries in the edit form
$turma_professores_map = [];
try {
    $tp_rows = $pdo->query("SELECT turma_id, professor_id FROM turma_professores")->fetchAll();
    foreach ($tp_rows as $row) {
        $turma_professores_map[(int)$row['turma_id']][] = (int)$row['professor_id'];
    }
} catch (Exception $e) {}

// Alunos com status de pagamento
$alunos_status = [];
try {
    $alunos_raw = $pdo->query("SELECT u.*, GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ') as turmas_nomes FROM usuarios u LEFT JOIN aluno_turmas at2 ON u.id = at2.aluno_id LEFT JOIN turmas t ON at2.turma_id = t.id WHERE u.tipo = 'aluno' GROUP BY u.id ORDER BY u.nome")->fetchAll();
    $hoje = date('Y-m-d');
    foreach ($alunos_raw as $al) {
        $stpag = $pdo->prepare("SELECT id FROM pagamentos WHERE aluna_id = ? AND data_vencimento >= ? LIMIT 1");
        $stpag->execute([$al['id'], $hoje]);
        $al['em_dia'] = (bool)$stpag->fetch();
        $alunos_status[] = $al;
    }
} catch (Exception $e) {}

$leads = [];
try {
    $leads = $pdo->query("SELECT l.*, u.nome as quem_indicou FROM leads_indicacoes l JOIN usuarios u ON l.aluna_id_indicou = u.id ORDER BY l.data_indicacao DESC")->fetchAll();
} catch (Exception $e) {}

$brindes_admin = [];
try { $brindes_admin = $pdo->query("SELECT * FROM brindes WHERE ativo=1 ORDER BY nome")->fetchAll(); } catch (Exception $e) {}

$avisos = [];
try { $avisos = $pdo->query("SELECT * FROM mural_avisos ORDER BY data_publicacao DESC")->fetchAll(); } catch (Exception $e) {}

$pastas_turma = [];
try {
    $pastas_turma = $pdo->query("SELECT pt.*, t.nome as turma_nome FROM pastas_turma pt JOIN turmas t ON pt.turma_id = t.id ORDER BY t.nome")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls | Admin</title>
    <meta name="theme-color" content="#d62bc5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Elite Thai Girls">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <link rel="apple-touch-icon" href="icon.svg">
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
        .badge-emdia{background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid #2ecc71;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:800}
        .badge-atrasado{background:rgba(255,68,68,.15);color:#ff4444;border:1px solid #ff4444;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:800}
        .btn-excluir{background:rgba(255,68,68,.1);color:#ff4444;border:none;padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer;transition:.3s}
        .btn-excluir:hover{background:#ff4444;color:#fff}
        .btn-editar{background:rgba(241,196,15,.1);color:#f1c40f;border:none;padding:8px 12px;border-radius:8px;font-size:12px;cursor:pointer;transition:.3s}
        .btn-editar:hover{background:#f1c40f;color:#000}
        .alerta-saude{background:rgba(214,43,197,.1);border-left:3px solid #d62bc5;padding:10px;border-radius:8px;font-size:12px;color:#fff;margin-top:10px;line-height:1.5}
        .status-novo{color:#f1c40f}.status-contatado{color:#3498db}.status-matriculado{color:#2ecc71}
        .edit-inline{display:none;background:#050308;padding:15px;border-radius:12px;border:1px solid #f1c40f;margin-top:10px}
    </style>
</head>
<body>
<div class="app">

    <div class="header">
        <h1><span>Elite Thai</span> Girls | Admin</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if ($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= e($msg_sucesso) ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= e($msg_erro) ?></div><?php endif; ?>

    <div class="tabs-menu">
        <button class="tab-btn ativo" onclick="openTab('caixa',this)"><i class="fas fa-wallet"></i> Caixa</button>
        <button class="tab-btn" onclick="openTab('leads',this)"><i class="fas fa-ticket-alt"></i> Indicações VIP</button>
        <button class="tab-btn" onclick="openTab('equipe',this)"><i class="fas fa-users"></i> Usuários &amp; Acesso</button>
        <button class="tab-btn" onclick="openTab('alunos',this)"><i class="fas fa-id-badge"></i> Alunos</button>
        <button class="tab-btn" onclick="openTab('horarios',this)"><i class="fas fa-clock"></i> Horários</button>
        <button class="tab-btn" onclick="openTab('planos',this)"><i class="fas fa-tags"></i> Planos</button>
        <button class="tab-btn" onclick="openTab('mural',this)"><i class="fas fa-bullhorn"></i> Mural</button>
        <button class="tab-btn" onclick="openTab('brindes',this)"><i class="fas fa-gift"></i> Brindes</button>
        <button class="tab-btn" onclick="openTab('pastas',this)"><i class="fab fa-google-drive"></i> Pastas</button>
    </div>

    <!-- CAIXA -->
    <div id="caixa" class="tab-content ativo">
        <div class="card" style="border-color:#2ecc71;background:linear-gradient(180deg,var(--card),rgba(46,204,113,.05))">
            <h3 class="card-titulo" style="color:#2ecc71"><i class="fas fa-chart-line" style="color:#2ecc71"></i> Receita do Mês</h3>
            <div style="font-size:36px;font-weight:800">R$ <?= number_format($total_caixa, 2, ',', '.') ?></div>
            <p style="font-size:12px;color:var(--cinza);margin:5px 0 0">Entradas em <?= date('m/Y') ?></p>
        </div>

        <!-- Formulário de pagamento -->
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-file-invoice-dollar"></i> Registar Pagamento</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="add_pagamento_admin">
                <select name="aluna_id" required>
                    <option value="">Selecione o(a) Aluno(a)...</option>
                    <?php foreach ($alunas as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="plano_id" required>
                    <option value="">Selecione o Plano...</option>
                    <?php foreach ($planos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['nome_plano']) ?> — R$ <?= number_format($p['valor'], 2, ',', '.') ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" step="0.01" name="valor" placeholder="Valor recebido (R$)" required>
                <select name="forma_pagamento" required>
                    <option value="pix">💳 PIX</option>
                    <option value="credito">💳 Cartão de Crédito</option>
                    <option value="debito">💳 Cartão de Débito</option>
                    <option value="dinheiro">💵 Dinheiro</option>
                </select>
                <textarea name="obs" rows="2" placeholder="Observações (Opcional)"></textarea>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Registar</button>
            </form>
        </div>

        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-list"></i> Últimos Pagamentos</h3>
            <?php $fp_labels = ['pix'=>'PIX','credito'=>'Cartão Crédito','debito'=>'Cartão Débito','dinheiro'=>'Dinheiro']; ?>
            <div style="max-height:400px;overflow-y:auto">
                <?php foreach ($pagamentos as $pag): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($pag['aluna_nome']) ?></span>
                            <span style="color:#2ecc71;font-weight:800">R$ <?= number_format($pag['valor_pago'], 2, ',', '.') ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--cinza)">
                            <i class="fas fa-tag"></i> <?= e($pag['nome_plano']) ?> |
                            <i class="fas fa-calendar-check"></i> Vence: <?= date('d/m/Y', strtotime($pag['data_vencimento'])) ?> |
                            <i class="fas fa-credit-card"></i> <?= e($fp_labels[$pag['forma_pagamento'] ?? 'pix']) ?>
                            <?php if (!empty($pag['treinador_nome'])): ?>
                                | <i class="fas fa-user-tie"></i> <?= e($pag['treinador_nome']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($pagamentos)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhum pagamento registado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LEADS -->
    <div id="leads" class="tab-content">
        <?php
        $leads_stats = ['total' => count($leads), 'novo' => 0, 'contatado' => 0, 'matriculado' => 0];
        foreach ($leads as $l) { if (isset($leads_stats[$l['status']])) { $leads_stats[$l['status']]++; } }
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px">
            <div style="background:var(--card);border:1px solid rgba(241,196,15,.3);border-radius:14px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#f1c40f"><?= $leads_stats['total'] ?></div>
                <div style="font-size:10px;color:var(--cinza);text-transform:uppercase;letter-spacing:.5px">Total</div>
            </div>
            <div style="background:var(--card);border:1px solid rgba(52,152,219,.3);border-radius:14px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#3498db"><?= $leads_stats['contatado'] ?></div>
                <div style="font-size:10px;color:var(--cinza);text-transform:uppercase;letter-spacing:.5px">Contatados</div>
            </div>
            <div style="background:var(--card);border:1px solid rgba(46,204,113,.3);border-radius:14px;padding:14px;text-align:center">
                <div style="font-size:26px;font-weight:800;color:#2ecc71"><?= $leads_stats['matriculado'] ?></div>
                <div style="font-size:10px;color:var(--cinza);text-transform:uppercase;letter-spacing:.5px">Matriculados</div>
            </div>
        </div>
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-ticket-alt"></i> Convidados (Leads)</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Pessoas que resgataram o Passe Livre VIP.</p>
            <div style="max-height:500px;overflow-y:auto">
                <?php foreach ($leads as $lead):
                    $wa_msg = urlencode("Olá " . $lead['nome_convidada'] . "! 👋 Somos da Elite Thai Girls. Você recebeu um Passe Livre VIP indicado por " . $lead['quem_indicou'] . ". Gostaríamos de agendar sua aula experimental gratuita! Que dia fica melhor? 🥊");
                    $wa_num = preg_replace('/\D/', '', $lead['telefone_convidada']);
                    $data_fmt = date('d/m/Y H:i', strtotime($lead['data_indicacao']));
                ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($lead['nome_convidada']) ?></span>
                            <span class="badge status-<?= e($lead['status']) ?>"><i class="fas fa-circle" style="font-size:7px"></i> <?= ucfirst(e($lead['status'])) ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--cinza);margin-bottom:10px">
                            <i class="fab fa-whatsapp" style="color:#2ecc71"></i> <?= e($lead['telefone_convidada']) ?>
                            &nbsp;·&nbsp;<i class="fas fa-gift" style="color:#d62bc5"></i> Indicado por <strong style="color:#fff"><?= e($lead['quem_indicou']) ?></strong>
                            <br><i class="fas fa-clock" style="opacity:.5"></i> <span style="opacity:.5"><?= $data_fmt ?></span>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <form method="POST" style="display:contents">
                                <input type="hidden" name="acao" value="atualizar_lead">
                                <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                                <select name="status" style="margin:0;padding:8px 10px;font-size:12px;flex:1;min-width:120px">
                                    <option value="novo" <?= $lead['status'] === 'novo' ? 'selected' : '' ?>>🟡 Novo</option>
                                    <option value="contatado" <?= $lead['status'] === 'contatado' ? 'selected' : '' ?>>🔵 Contatado</option>
                                    <option value="matriculado" <?= $lead['status'] === 'matriculado' ? 'selected' : '' ?>>🟢 Matriculado</option>
                                </select>
                                <button type="submit" class="btn-submit" style="padding:8px 12px;font-size:12px;width:auto" title="Salvar"><i class="fas fa-save"></i></button>
                            </form>
                            <a href="https://wa.me/55<?= $wa_num ?>?text=<?= $wa_msg ?>" target="_blank" class="btn-submit" style="padding:8px 12px;font-size:12px;width:auto;background:#25D366;text-decoration:none;text-align:center;box-shadow:none" title="Abrir WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <form method="POST" onsubmit="return confirm('Remover este lead?')" style="display:contents">
                                <input type="hidden" name="acao" value="excluir_lead">
                                <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                                <button type="submit" class="btn-submit" style="padding:8px 12px;font-size:12px;width:auto;background:rgba(255,68,68,.2);box-shadow:none;border:1px solid #ff4444;color:#ff4444" title="Remover"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($leads)): ?><p style="color:var(--cinza);font-size:13px;text-align:center;padding:20px 0">Nenhuma indicação ainda. Os Passes Livres resgatados aparecerão aqui.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- EQUIPE -->
    <div id="equipe" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-user-plus"></i> Criar Novo Usuário</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Defina o login, senha e permissão de acesso do novo usuário.</p>
            <form method="POST" id="formAddUsuario">
                <input type="hidden" name="acao" value="add_usuario">
                <input type="text" name="nome" placeholder="Nome Completo" required>
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-envelope"></i> Login / E-mail</label>
                <input type="email" name="email" placeholder="E-mail ou login de acesso" required>
                <input type="text" name="telefone" placeholder="WhatsApp (Opcional)">
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-calendar-alt"></i> Data de Nascimento</label>
                <input type="date" name="data_nascimento" style="margin-bottom:12px">
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-lock"></i> Senha de Acesso</label>
                <input type="password" name="senha" placeholder="Senha" required>
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-shield-alt"></i> Nível de Permissão</label>
                <select name="tipo" required id="tipoSelect" onchange="toggleTurmaField()">
                    <option value="" disabled selected>Selecione a permissão...</option>
                    <option value="aluno">Aluno(a)</option>
                    <option value="professor">Professor(a)</option>
                    <option value="treinador">Treinador(a)</option>
                    <option value="instrutor">Instrutor(a)</option>
                    <option value="admin">Administrador(a)</option>
                </select>
                <div id="turmaField" style="display:none">
                    <select name="turma_id">
                        <option value="">Sem turma (opcional)</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= e($t['nome']) ?><?= $t['dia_semana'] ? ' — ' . e($t['dia_semana'] . ' ' . $t['horario']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> Criar Usuário</button>
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
                        <?php if ($u['tipo'] === 'aluno' && !empty($u['restricoes_medicas'])): ?>
                            <div class="alerta-saude">
                                <strong><i class="fas fa-notes-medical"></i> Ficha Médica:</strong><br>
                                <?= nl2br(e($u['restricoes_medicas'])) ?>
                            </div>
                        <?php endif; ?>
                        <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
                            <button type="button" class="btn-editar" onclick="toggleEditUsuario(<?= (int)$u['id'] ?>)"><i class="fas fa-key"></i> Editar Acesso</button>
                            <form method="POST" onsubmit="return confirm('Excluir este usuário?')">
                                <input type="hidden" name="acao" value="excluir_usuario">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i> Excluir</button>
                            </form>
                        </div>
                        <div id="editUsuario<?= (int)$u['id'] ?>" class="edit-inline" style="display:none">
                            <form method="POST">
                                <input type="hidden" name="acao" value="editar_usuario">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-user"></i> Nome Completo</label>
                                <input type="text" name="nome" value="<?= e($u['nome']) ?>" placeholder="Nome Completo" required>
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-envelope"></i> Login / E-mail</label>
                                <input type="email" name="email" value="<?= e($u['email']) ?>" placeholder="Login ou E-mail" required>
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fab fa-whatsapp"></i> WhatsApp</label>
                                <input type="text" name="telefone" value="<?= e($u['telefone'] ?? '') ?>" placeholder="WhatsApp (opcional)">
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-calendar-alt"></i> Data de Nascimento</label>
                                <input type="date" name="data_nascimento" value="<?= e($u['data_nascimento'] ?? '') ?>">
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-shield-alt"></i> Nível de Permissão</label>
                                <select name="tipo" required>
                                    <option value="aluno"      <?= $u['tipo'] === 'aluno'      ? 'selected' : '' ?>>Aluno(a)</option>
                                    <option value="professor"  <?= $u['tipo'] === 'professor'  ? 'selected' : '' ?>>Professor(a)</option>
                                    <option value="treinador"  <?= $u['tipo'] === 'treinador'  ? 'selected' : '' ?>>Treinador(a)</option>
                                    <option value="instrutor"  <?= $u['tipo'] === 'instrutor'  ? 'selected' : '' ?>>Instrutor(a)</option>
                                    <option value="admin"      <?= $u['tipo'] === 'admin'      ? 'selected' : '' ?>>Administrador(a)</option>
                                </select>
                                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-lock"></i> Nova Senha <span style="color:#888">(deixe em branco para não alterar)</span></label>
                                <input type="password" name="nova_senha" placeholder="Nova senha (opcional)">
                                <div style="display:flex;gap:10px">
                                    <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none"><i class="fas fa-save"></i> Salvar</button>
                                    <button type="button" onclick="toggleEditUsuario(<?= (int)$u['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($usuarios)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhum usuário cadastrado.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ALUNOS -->
    <div id="alunos" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-id-badge"></i> Alunos — Status de Pagamento</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">
                Total: <strong><?= count($alunos_status) ?></strong> aluno(s) |
                Em dia: <strong style="color:#2ecc71"><?= count(array_filter($alunos_status, fn($x) => $x['em_dia'])) ?></strong> |
                Atrasados: <strong style="color:#ff4444"><?= count(array_filter($alunos_status, fn($x) => !$x['em_dia'])) ?></strong>
            </p>
            <div style="max-height:600px;overflow-y:auto">
                <?php foreach ($alunos_status as $al): ?>
                    <div class="item-lista">
                        <div class="item-topo">
                            <span class="item-nome"><?= e($al['nome']) ?></span>
                            <?php if ($al['em_dia']): ?>
                                <span class="badge-emdia">✅ Em dia</span>
                            <?php else: ?>
                                <span class="badge-atrasado">❌ Atrasado</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:var(--cinza)">
                            <i class="fas fa-envelope"></i> <?= e($al['email']) ?>
                            <?php if (!empty($al['telefone'])): ?> | <i class="fab fa-whatsapp" style="color:#2ecc71"></i> <?= e($al['telefone']) ?><?php endif; ?>
                            <?php if (!empty($al['data_nascimento'])): ?> | <i class="fas fa-birthday-cake"></i> <?= date('d/m/Y', strtotime($al['data_nascimento'])) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($al['turmas_nomes'])): ?>
                            <div style="font-size:12px;color:#7b2cbf;margin-top:4px"><i class="fas fa-layer-group"></i> <?= e($al['turmas_nomes']) ?></div>
                        <?php else: ?>
                            <div style="font-size:12px;color:var(--cinza);margin-top:4px"><i class="fas fa-layer-group"></i> Sem turma</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($alunos_status)): ?><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhum(a) aluno(a) cadastrado(a).</p><?php endif; ?>
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
                <input type="text" name="desc" placeholder="Descrição (Ex: Treino Feminino)">
                <button type="submit" class="btn-submit">Adicionar</button>
            </form>
            <?php foreach ($horarios as $h): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome"><?= e($h['dia_semana']) ?></span><br>
                            <span style="font-size:12px;color:var(--cinza)"><?= e($h['horario']) ?> — <?= e($h['descricao'] ?? '') ?></span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditHorario(<?= (int)$h['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST">
                                <input type="hidden" name="acao" value="excluir_horario">
                                <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div id="editHorario<?= (int)$h['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_horario">
                            <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                            <input type="text" name="dia" value="<?= e($h['dia_semana']) ?>" placeholder="Dia (Ex: Segunda-feira)" required>
                            <input type="text" name="hora" value="<?= e($h['horario']) ?>" placeholder="Horário (Ex: 20:30 às 21:30)" required>
                            <input type="text" name="desc" value="<?= e($h['descricao'] ?? '') ?>" placeholder="Descrição (Ex: Treino Feminino)">
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none"><i class="fas fa-save"></i> Salvar</button>
                                <button type="button" onclick="toggleEditHorario(<?= (int)$h['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Turmas -->
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-layer-group"></i> Gerir Turmas</h3>
            <form method="POST" style="margin-bottom:20px">
                <input type="hidden" name="acao" value="add_turma">
                <input type="text" name="nome" placeholder="Nome da Turma (Ex: Turma A — Manhã)" required>
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:8px"><i class="fas fa-user-tie"></i> Professores (selecione um ou mais)</label>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                    <?php foreach ($professores as $pr): ?>
                        <label style="display:flex;align-items:center;gap:6px;background:#050308;border:1px solid var(--borda);padding:8px 12px;border-radius:8px;cursor:pointer;font-size:13px;color:var(--cinza)">
                            <input type="checkbox" name="professor_ids[]" value="<?= (int)$pr['id'] ?>" style="width:auto;margin:0"> <?= e($pr['nome']) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($professores)): ?><p style="font-size:12px;color:var(--cinza);margin:0">Nenhum professor/treinador cadastrado.</p><?php endif; ?>
                </div>
                <select name="horario_id">
                    <option value="">Sem horário vinculado</option>
                    <?php foreach ($horarios as $h): ?>
                        <option value="<?= (int)$h['id'] ?>"><?= e($h['dia_semana'] . ' — ' . $h['horario']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Criar Turma</button>
            </form>
            <?php if (empty($turmas)): ?>
                <p style="font-size:13px;color:var(--cinza);text-align:center">Nenhuma turma criada ainda.</p>
            <?php endif; ?>
            <?php foreach ($turmas as $t): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome"><?= e($t['nome']) ?></span><br>
                            <span style="font-size:12px;color:var(--cinza)">
                                <i class="fas fa-user-tie" style="color:#d62bc5"></i> <?= e($t['professores_nomes'] ?? 'Sem professor') ?> |
                                <i class="fas fa-clock" style="color:#7b2cbf"></i> <?= e(trim(($t['dia_semana'] ?? '') . ' ' . ($t['horario'] ?? ''))) ?: '—' ?>
                            </span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditTurma(<?= (int)$t['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST">
                                <input type="hidden" name="acao" value="excluir_turma">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn-excluir" onclick="return confirm('Excluir esta turma?')"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php
                    // Professores atuais desta turma (from pre-fetched map)
                    $profs_turma_ids = $turma_professores_map[(int)$t['id']] ?? [];
                    ?>
                    <div id="editTurma<?= (int)$t['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_turma">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="text" name="nome" value="<?= e($t['nome']) ?>" placeholder="Nome da Turma" required>
                            <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:8px"><i class="fas fa-user-tie"></i> Professores</label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                                <?php foreach ($professores as $pr): ?>
                                    <label style="display:flex;align-items:center;gap:6px;background:#050308;border:1px solid var(--borda);padding:8px 12px;border-radius:8px;cursor:pointer;font-size:13px;color:var(--cinza)">
                                        <input type="checkbox" name="professor_ids[]" value="<?= (int)$pr['id'] ?>" style="width:auto;margin:0" <?= in_array((int)$pr['id'], $profs_turma_ids) ? 'checked' : '' ?>> <?= e($pr['nome']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <select name="horario_id">
                                <option value="">Sem horário vinculado</option>
                                <?php foreach ($horarios as $h): ?>
                                    <option value="<?= (int)$h['id'] ?>" <?= (int)($t['horario_id'] ?? 0) === (int)$h['id'] ? 'selected' : '' ?>><?= e($h['dia_semana'] . ' — ' . $h['horario']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none"><i class="fas fa-save"></i> Salvar</button>
                                <button type="button" onclick="toggleEditTurma(<?= (int)$t['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
                    </div>
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
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome"><?= e($p['nome_plano']) ?></span><br>
                            <span style="font-size:12px;color:#2ecc71;font-weight:bold">R$ <?= number_format($p['valor'], 2, ',', '.') ?> (<?= (int)$p['duracao_meses'] ?> meses)</span>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditPlano(<?= (int)$p['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" onsubmit="return confirm('Excluir este plano?')">
                                <input type="hidden" name="acao" value="excluir_plano">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div id="editPlano<?= (int)$p['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_plano">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="text" name="nome_plano" value="<?= e($p['nome_plano']) ?>" placeholder="Nome do Plano" required>
                            <input type="number" step="0.01" name="valor" value="<?= e($p['valor']) ?>" placeholder="Valor (R$)" required>
                            <input type="number" name="duracao_meses" value="<?= (int)$p['duracao_meses'] ?>" placeholder="Duração (meses)" required>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none">Salvar</button>
                                <button type="button" onclick="toggleEditPlano(<?= (int)$p['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
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
        <?php if (!empty($avisos)): ?>
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-list"></i> Avisos Publicados</h3>
            <?php foreach ($avisos as $av): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome"><?= e($av['titulo']) ?></span>
                            <?php if ($av['tipo'] === 'urgente'): ?>
                                <span class="badge" style="background:rgba(255,68,68,.15);color:#ff4444;border:1px solid #ff4444;margin-left:6px">⚠️ Urgente</span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(52,152,219,.15);color:#3498db;border:1px solid #3498db;margin-left:6px">ℹ️ Info</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditAviso(<?= (int)$av['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" onsubmit="return confirm('Excluir este aviso?')">
                                <input type="hidden" name="acao" value="excluir_aviso">
                                <input type="hidden" name="id" value="<?= (int)$av['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div style="font-size:12px;color:var(--cinza);margin-bottom:8px"><?= nl2br(e($av['mensagem'])) ?></div>
                    <div id="editAviso<?= (int)$av['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_aviso">
                            <input type="hidden" name="id" value="<?= (int)$av['id'] ?>">
                            <input type="text" name="titulo" value="<?= e($av['titulo']) ?>" placeholder="Título do Aviso" required>
                            <textarea name="mensagem" rows="3" placeholder="Mensagem..."><?= e($av['mensagem']) ?></textarea>
                            <select name="tipo">
                                <option value="info" <?= $av['tipo'] === 'info' ? 'selected' : '' ?>>Informativo</option>
                                <option value="urgente" <?= $av['tipo'] === 'urgente' ? 'selected' : '' ?>>⚠️ Urgente</option>
                            </select>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none"><i class="fas fa-save"></i> Salvar</button>
                                <button type="button" onclick="toggleEditAviso(<?= (int)$av['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- BRINDES -->
    <div id="brindes" class="tab-content">
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-gift"></i> Gerir Brindes</h3>
            <form method="POST" style="margin-bottom:20px">
                <input type="hidden" name="acao" value="add_brinde_admin">
                <input type="text" name="nome_brinde" placeholder="Nome do brinde" required>
                <textarea name="desc_brinde" rows="2" placeholder="Descrição (opcional)"></textarea>
                <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Adicionar Brinde</button>
            </form>
            <?php foreach (($brindes_admin ?? []) as $br): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome">🎁 <?= e($br['nome']) ?></span>
                            <?php if (!empty($br['descricao'])): ?>
                                <br><span style="font-size:12px;color:var(--cinza)"><?= e($br['descricao']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditBrinde(<?= (int)$br['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST">
                                <input type="hidden" name="acao" value="desativar_brinde_admin">
                                <input type="hidden" name="id" value="<?= (int)$br['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                    <div id="editBrinde<?= (int)$br['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_brinde_admin">
                            <input type="hidden" name="id" value="<?= (int)$br['id'] ?>">
                            <input type="text" name="nome_brinde" value="<?= e($br['nome']) ?>" placeholder="Nome do brinde" required>
                            <textarea name="desc_brinde" rows="2" placeholder="Descrição (opcional)"><?= e($br['descricao'] ?? '') ?></textarea>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none"><i class="fas fa-save"></i> Salvar</button>
                                <button type="button" onclick="toggleEditBrinde(<?= (int)$br['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-box-open"></i> Brindes Ganhos — Entregas Pendentes</h3>
            <?php
            $brindes_ganhos_admin = [];
            try {
                $brindes_ganhos_admin = $pdo->query("SELECT ba.*, u.nome as aluna_nome, b.nome as brinde_nome FROM brindes_aluna ba JOIN usuarios u ON ba.aluna_id=u.id LEFT JOIN brindes b ON ba.brinde_id=b.id ORDER BY ba.entregue ASC, ba.created_at DESC")->fetchAll();
            } catch (Exception $e) {}
            ?>
            <?php if (empty($brindes_ganhos_admin)): ?>
                <p style="font-size:13px;color:var(--cinza);text-align:center">Nenhum brinde registado.</p>
            <?php else: ?>
                <?php foreach ($brindes_ganhos_admin as $bg): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <span class="item-nome"><?= e($bg['aluna_nome']) ?></span>
                        <?php if ($bg['entregue']): ?>
                            <span class="badge" style="background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid #2ecc71">✅ Entregue</span>
                        <?php else: ?>
                            <span class="badge" style="background:rgba(241,196,15,.15);color:#f1c40f;border:1px solid #f1c40f">🎁 Pendente</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:var(--cinza)">
                        <?= e($bg['brinde_nome'] ?? $bg['brinde_manual'] ?? '—') ?> |
                        <i class="fas fa-calendar-alt"></i> <?= e($bg['mes_referencia']) ?>
                        <?php if ($bg['entregue'] && $bg['data_entrega']): ?>
                            | Entregue em <?= date('d/m/Y', strtotime($bg['data_entrega'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!$bg['entregue']): ?>
                    <form method="POST" style="margin-top:8px">
                        <input type="hidden" name="acao" value="entregar_brinde_admin">
                        <input type="hidden" name="ba_id" value="<?= (int)$bg['id'] ?>">
                        <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#11998e,#38ef7d);color:#000;font-size:12px;padding:10px;box-shadow:none"><i class="fas fa-box-open"></i> Marcar Entregue</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- PASTAS DAS TURMAS -->
    <div id="pastas" class="tab-content">
        <div class="card" style="border-color:#4285F4;background:linear-gradient(180deg,var(--card),rgba(66,133,244,.06))">
            <h3 class="card-titulo" style="color:#4285F4"><i class="fab fa-google-drive" style="color:#4285F4"></i> Configurar Pasta por Turma</h3>
            <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Associe um link do Google Drive a uma turma. Apenas os membros da turma verão a pasta.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="add_pasta">
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-layer-group"></i> Turma</label>
                <select name="turma_id" required>
                    <option value="">Selecione a turma...</option>
                    <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= e($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-tag"></i> Título da Pasta</label>
                <input type="text" name="titulo" placeholder="Ex: Pasta da Turma Feminina" value="Pasta da Turma">
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-align-left"></i> Descrição (opcional)</label>
                <textarea name="descricao" rows="2" placeholder="Acesse fotos, vídeos e documentos da turma."></textarea>
                <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fab fa-google-drive"></i> Link da Pasta Google Drive</label>
                <input type="url" name="drive_link" placeholder="https://drive.google.com/drive/folders/..." required>
                <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#4285F4,#0F9D58);box-shadow:0 5px 15px rgba(66,133,244,.3)"><i class="fas fa-plus"></i> Salvar Pasta</button>
            </form>
        </div>

        <?php if (empty($pastas_turma)): ?>
            <div class="card"><p style="color:var(--cinza);font-size:13px;text-align:center">Nenhuma pasta configurada ainda.</p></div>
        <?php else: ?>
        <div class="card">
            <h3 class="card-titulo"><i class="fas fa-folder-open"></i> Pastas Configuradas</h3>
            <?php foreach ($pastas_turma as $pasta): ?>
                <div class="item-lista">
                    <div class="item-topo">
                        <div>
                            <span class="item-nome"><i class="fab fa-google-drive" style="color:#4285F4"></i> <?= e($pasta['titulo']) ?></span><br>
                            <span style="font-size:12px;color:#7b2cbf"><i class="fas fa-layer-group"></i> <?= e($pasta['turma_nome']) ?></span>
                            <?php if (!empty($pasta['descricao'])): ?>
                                <br><span style="font-size:11px;color:var(--cinza)"><?= e($pasta['descricao']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn-editar" onclick="toggleEditPasta(<?= (int)$pasta['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" onsubmit="return confirm('Excluir esta pasta?')">
                                <input type="hidden" name="acao" value="excluir_pasta">
                                <input type="hidden" name="id" value="<?= (int)$pasta['id'] ?>">
                                <button type="submit" class="btn-excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <div id="editPasta<?= (int)$pasta['id'] ?>" class="edit-inline">
                        <form method="POST">
                            <input type="hidden" name="acao" value="editar_pasta">
                            <input type="hidden" name="id" value="<?= (int)$pasta['id'] ?>">
                            <input type="text" name="titulo" value="<?= e($pasta['titulo']) ?>" placeholder="Título" required>
                            <textarea name="descricao" rows="2" placeholder="Descrição"><?= e($pasta['descricao'] ?? '') ?></textarea>
                            <input type="url" name="drive_link" value="<?= e($pasta['drive_link']) ?>" placeholder="Link Google Drive" required>
                            <div style="display:flex;gap:10px">
                                <button type="submit" class="btn-submit" style="background:#f1c40f;color:#000;box-shadow:none">Salvar</button>
                                <button type="button" onclick="toggleEditPasta(<?= (int)$pasta['id'] ?>)" class="btn-submit" style="background:#333;box-shadow:none">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
function toggleEdit(prefix, id) {
    var el = document.getElementById(prefix + id);
    el.style.display = el.style.display === 'block' ? 'none' : 'block';
}
function toggleEditHorario(id) { toggleEdit('editHorario', id); }
function toggleEditTurma(id) { toggleEdit('editTurma', id); }
function toggleEditAviso(id) { toggleEdit('editAviso', id); }
function toggleEditBrinde(id) { toggleEdit('editBrinde', id); }
function toggleEditUsuario(id) { toggleEdit('editUsuario', id); }
function toggleEditPlano(id) { toggleEdit('editPlano', id); }
function toggleEditPasta(id) { toggleEdit('editPasta', id); }
function toggleTurmaField() {
    var tipo = document.getElementById('tipoSelect').value;
    document.getElementById('turmaField').style.display = tipo === 'aluno' ? 'block' : 'none';
}
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>
