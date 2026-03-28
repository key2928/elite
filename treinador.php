<?php
require 'config.php';

if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['treinador', 'professor', 'instrutor'])) {
    header('Location: login.php');
    exit;
}

// AJAX: return students for a given turma
if (isset($_GET['get_alunos_turma'])) {
    $tid = (int)($_GET['turma_id'] ?? 0);
    $rows = [];
    try {
        $s = $pdo->prepare("SELECT u.id FROM usuarios u JOIN aluno_turmas at2 ON u.id=at2.aluno_id WHERE at2.turma_id=? AND u.tipo='aluno' AND u.ativo=1");
        $s->execute([$tid]);
        $rows = $s->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
    header('Content-Type: application/json');
    echo json_encode(array_map('intval', $rows));
    exit;
}

function calcularDiasTreinados(string $mes, string $dia_semana): array {
    $map = [
        'segunda' => 1, 'monday' => 1,
        'terça'   => 2, 'terca'  => 2, 'tuesday'  => 2,
        'quarta'  => 3, 'wednesday' => 3,
        'quinta'  => 4, 'thursday'  => 4,
        'sexta'   => 5, 'friday'    => 5,
        'sábado'  => 6, 'sabado' => 6, 'saturday' => 6,
        'domingo' => 0, 'sunday'    => 0,
    ];
    $dw = null;
    $lc = mb_strtolower($dia_semana, 'UTF-8');
    foreach ($map as $key => $val) {
        if (str_contains($lc, $key)) { $dw = $val; break; }
    }
    if ($dw === null) return [];
    $dias = [];
    $data = new DateTime($mes . '-01');
    $ultimo = (int)$data->format('t');
    for ($d = 1; $d <= $ultimo; $d++) {
        $data->setDate((int)$data->format('Y'), (int)$data->format('m'), $d);
        if ((int)$data->format('w') === $dw) {
            $dias[] = $data->format('Y-m-d');
        }
    }
    return $dias;
}

$msg_sucesso = '';
$msg_erro = '';
$prof_id = (int)$_SESSION['usuario_id'];
$recibo_dados = null;
$forma_labels = ['pix' => 'PIX', 'credito' => 'Cartão de Crédito', 'debito' => 'Cartão de Débito', 'dinheiro' => 'Dinheiro'];

// Carregar permissões granulares do usuário atual
$minha_permissoes = [];
try {
    $stmtP = $pdo->prepare("SELECT permissao FROM usuario_permissoes WHERE usuario_id = ?");
    $stmtP->execute([$prof_id]);
    $minha_permissoes = $stmtP->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
function hasPerm(array $perms, string $p): bool { return in_array($p, $perms, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Chamada (bulk attendance)
    if ($acao === 'chamada_turma') {
        $turma_id    = (int)($_POST['turma_id'] ?? 0);
        $data_aula   = $_POST['data_presenca'] ?? date('Y-m-d');
        $presentes   = array_map('intval', (array)($_POST['presentes'] ?? []));

        $stmtAlunos = $pdo->prepare("SELECT u.id FROM usuarios u JOIN aluno_turmas at2 ON u.id = at2.aluno_id WHERE at2.turma_id = ? AND u.tipo = 'aluno' AND u.ativo = 1");
        $stmtAlunos->execute([$turma_id]);
        $ids_turma = $stmtAlunos->fetchAll(PDO::FETCH_COLUMN);

        $novos = 0;
        foreach ($ids_turma as $aid) {
            $esta = in_array($aid, $presentes, true) ? 1 : 0;
            $pdo->prepare("INSERT INTO presencas (aluno_id, professor_id, data_presenca, presente) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE presente=VALUES(presente), professor_id=VALUES(professor_id)")
                ->execute([$aid, $prof_id, $data_aula, $esta]);
            if ($esta) {
                $pdo->prepare("UPDATE usuarios SET treinos_concluidos=COALESCE(treinos_concluidos,0)+1, xp_atual=COALESCE(xp_atual,0)+20 WHERE id=?")
                    ->execute([$aid]);
                $novos++;
            }
        }

        $mes = substr($data_aula, 0, 7);
        $hoje = date('Y-m-d');
        // Fetch turma schedule once, outside the per-student loop
        $stmtHor = $pdo->prepare("SELECT h.dia_semana FROM horarios_treino h JOIN turmas t ON t.horario_id = h.id WHERE t.id = ?");
        $stmtHor->execute([$turma_id]);
        $hor = $stmtHor->fetch();
        $dias_passados = [];
        $ultimo_dia = date('Y-m-t', strtotime($mes.'-01'));
        if ($hor) {
            $dias_treino = calcularDiasTreinados($mes, $hor['dia_semana']);
            $dias_passados = array_values(array_filter($dias_treino, fn($d) => $d <= $hoje));
        }

        foreach ($presentes as $aid) {
            if (!in_array($aid, $ids_turma, true)) continue;
            if ($hor && count($dias_passados) > 0) {
                $ph = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE aluno_id=? AND data_presenca LIKE ? AND presente=0");
                $ph->execute([$aid, $mes.'%']);
                $faltas = (int)$ph->fetchColumn();
                $pp = $pdo->prepare("SELECT COUNT(*) FROM presencas WHERE aluno_id=? AND data_presenca IN (".implode(',',array_fill(0,count($dias_passados),'?')).") AND presente=1");
                $pp->execute(array_merge([$aid], $dias_passados));
                $presencas_ok = (int)$pp->fetchColumn();
                if ($faltas === 0 && $presencas_ok === count($dias_passados) && strtotime($hoje) >= strtotime($ultimo_dia)) {
                    $chk = $pdo->prepare("SELECT id FROM brindes_aluna WHERE aluna_id=? AND mes_referencia=?");
                    $chk->execute([$aid, $mes]);
                    if (!$chk->fetch()) {
                        $pdo->prepare("INSERT INTO brindes_aluna (aluna_id, mes_referencia, instrutor_id) VALUES (?,?,?)")
                            ->execute([$aid, $mes, $prof_id]);
                    }
                }
            }
        }

        $msg_sucesso = "Chamada salva! {$novos} presente(s) registado(s) em " . date('d/m/Y', strtotime($data_aula)) . ".";
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
    if ($acao === 'pagamento' && hasPerm($minha_permissoes, 'pagamentos_registrar')) {
        $plano_id = (int)($_POST['plano_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT duracao_meses, nome_plano FROM planos_tabela WHERE id = ?");
        $stmt->execute([$plano_id]);
        $plano_row = $stmt->fetch();
        if ($plano_row) {
            $vencimento = date('Y-m-d', strtotime("+{$plano_row['duracao_meses']} months"));
            $pdo->prepare("INSERT INTO pagamentos (aluna_id, treinador_id, plano_id, valor_pago, data_pagamento, data_vencimento, observacao_aluna, forma_pagamento) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$_POST['aluna_id'], $_SESSION['usuario_id'], $plano_id, $_POST['valor'] ?? 0, date('Y-m-d'), $vencimento, $_POST['obs'] ?? '', $_POST['forma_pagamento'] ?? 'pix']);
            $msg_sucesso = 'Pagamento registado com sucesso!';
            // Dados para o recibo
            $stmtAluno = $pdo->prepare("SELECT nome FROM usuarios WHERE id=?");
            $stmtAluno->execute([(int)$_POST['aluna_id']]);
            $stmtProf = $pdo->prepare("SELECT nome FROM usuarios WHERE id=?");
            $stmtProf->execute([$_SESSION['usuario_id']]);
            $recibo_dados = [
                'aluno'    => ($stmtAluno->fetchColumn() ?: '—'),
                'plano'    => $plano_row['nome_plano'],
                'valor'    => number_format((float)($_POST['valor'] ?? 0), 2, ',', '.'),
                'forma'    => $forma_labels[$_POST['forma_pagamento'] ?? 'pix'] ?? 'PIX',
                'treinador'=> ($stmtProf->fetchColumn() ?: '—'),
                'data'     => date('d/m/Y'),
                'vencimento' => date('d/m/Y', strtotime($vencimento)),
                'obs'      => trim($_POST['obs'] ?? ''),
            ];
        }
    }

    // 5b. Adicionar Brinde
    if ($acao === 'add_brinde' && hasPerm($minha_permissoes, 'brindes_ver')) {
        $nome = trim($_POST['nome_brinde'] ?? '');
        if ($nome !== '') {
            $pdo->prepare("INSERT INTO brindes (nome, descricao) VALUES (?,?)")
                ->execute([$nome, trim($_POST['desc_brinde'] ?? '')]);
            $msg_sucesso = 'Brinde adicionado!';
        }
    }

    // 5c. Registar Brinde Manual (treinador atribui manualmente a aluna)
    if ($acao === 'brinde_manual' && hasPerm($minha_permissoes, 'brindes_ver')) {
        $aluna_id = (int)($_POST['aluna_id'] ?? 0);
        $mes      = $_POST['mes_referencia'] ?? date('Y-m');
        $texto    = trim($_POST['brinde_manual'] ?? '');
        if ($aluna_id && $texto) {
            try {
                $pdo->prepare("INSERT INTO brindes_aluna (aluna_id, mes_referencia, brinde_manual, instrutor_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE brinde_manual=VALUES(brinde_manual)")
                    ->execute([$aluna_id, $mes, $texto, $prof_id]);
                $msg_sucesso = 'Brinde registado!';
            } catch (PDOException $e) { $msg_erro = 'Erro ao registar.'; }
        }
    }

    // 5d. Entregar Brinde
    if ($acao === 'entregar_brinde' && hasPerm($minha_permissoes, 'brindes_entregar')) {
        $ba_id = (int)($_POST['ba_id'] ?? 0);
        $pdo->prepare("UPDATE brindes_aluna SET entregue=1, data_entrega=NOW() WHERE id=?")
            ->execute([$ba_id]);
        $msg_sucesso = 'Brinde marcado como entregue!';
    }

    // 5e. Girar Roleta (aluna girou, registar brinde)
    if ($acao === 'girar_roleta' && hasPerm($minha_permissoes, 'brindes_ver')) {
        $ba_id    = (int)($_POST['ba_id'] ?? 0);
        $brinde_id = (int)($_POST['brinde_id'] ?? 0);
        if ($ba_id) {
            $pdo->prepare("UPDATE brindes_aluna SET roleta_girada=1, brinde_id=? WHERE id=? AND roleta_girada=0")
                ->execute([$brinde_id ?: null, $ba_id]);
            $msg_sucesso = 'Parabéns! Brinde registado — aguarde a entrega!';
        }
    }

    // 5. Cadastrar Aluno
    if ($acao === 'nova_aluna' && hasPerm($minha_permissoes, 'alunos_editar')) {
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
            $novo_id = (int)$pdo->lastInsertId();
            if (!empty($_POST['turma_id'])) {
                $pdo->prepare("INSERT IGNORE INTO aluno_turmas (aluno_id, turma_id) VALUES (?,?)")
                    ->execute([$novo_id, (int)$_POST['turma_id']]);
            }
            $msg_sucesso = 'Aluno(a) cadastrado(a) com sucesso!';
        } catch (PDOException $e) {
            $msg_erro = 'Erro: Este e-mail já está registado.';
        }
    }

    // 6. Editar Aluno
    if ($acao === 'editar_aluno' && hasPerm($minha_permissoes, 'alunos_editar')) {
        $pdo->prepare("UPDATE usuarios SET nome=?, email=?, telefone=? WHERE id=?")
            ->execute([$_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', (int)($_POST['aluno_id'] ?? 0)]);
        $msg_sucesso = 'Dados atualizados com sucesso!';
    }

    // 7. Editar Ficha Médica
    if ($acao === 'editar_ficha_aluno' && hasPerm($minha_permissoes, 'alunos_editar')) {
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
    if ($acao === 'excluir_aluno' && hasPerm($minha_permissoes, 'alunos_editar')) {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([(int)($_POST['excluir_id'] ?? 0)]);
        $msg_sucesso = 'Cadastro removido do sistema.';
    }

    // 9. Adicionar Material de Marketing (galeria_upload)
    if ($acao === 'add_material' && hasPerm($minha_permissoes, 'galeria_upload')) {
        $titulo    = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = trim($_POST['categoria'] ?? 'Geral');
        $tipo      = $_POST['tipo_arquivo'] ?? 'outro';
        $tipos_validos_mat = ['imagem','video','documento','link','outro'];
        if (!in_array($tipo, $tipos_validos_mat)) $tipo = 'outro';

        $arquivo_path  = null;
        $drive_link    = null;
        $drive_file_id = null;
        $tamanho_kb    = null;

        if ($tipo === 'link') {
            $drive_link = trim($_POST['drive_link'] ?? '');
            if ($drive_link !== '') {
                $drive_file_id = extractDriveFileId($drive_link) ?: null;
            }
        } elseif (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
            $allowed_exts = ['jpg','jpeg','png','gif','webp','mp4','mov','avi','pdf','doc','docx','xls','xlsx','ppt','pptx'];
            $allowed_mimes = [
                'image/jpeg','image/png','image/gif','image/webp',
                'video/mp4','video/quicktime','video/x-msvideo',
                'application/pdf',
                'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            $orig_name = basename($_FILES['arquivo']['name']);
            $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $finfo     = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
            $mime_type = $finfo ? $finfo->file($_FILES['arquivo']['tmp_name']) : mime_content_type($_FILES['arquivo']['tmp_name']);
            if (!in_array($ext, $allowed_exts) || !in_array($mime_type, $allowed_mimes)) {
                $msg_erro = 'Tipo de arquivo não permitido.';
            } elseif ($_FILES['arquivo']['size'] > 50 * 1024 * 1024) {
                $msg_erro = 'Arquivo muito grande (máximo 50 MB).';
            } else {
                $upload_dir = __DIR__ . '/uploads/materiais/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $new_name = uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $upload_dir . $new_name)) {
                    $arquivo_path = 'uploads/materiais/' . $new_name;
                    $tamanho_kb   = (int)ceil($_FILES['arquivo']['size'] / 1024);
                    if ($tipo === 'outro') {
                        $img_exts = ['jpg','jpeg','png','gif','webp'];
                        $vid_exts = ['mp4','mov','avi'];
                        $doc_exts = ['pdf','doc','docx','xls','xlsx','ppt','pptx'];
                        if (in_array($ext, $img_exts)) $tipo = 'imagem';
                        elseif (in_array($ext, $vid_exts)) $tipo = 'video';
                        elseif (in_array($ext, $doc_exts)) $tipo = 'documento';
                    }
                } else {
                    $msg_erro = 'Erro ao fazer upload do arquivo.';
                }
            }
        }

        if ($msg_erro === '' && $titulo !== '') {
            try {
                $pdo->prepare("INSERT INTO materiais_marketing (titulo, descricao, categoria, tipo_arquivo, arquivo_path, drive_link, drive_file_id, tamanho_kb, criado_por) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$titulo, $descricao ?: null, $categoria, $tipo, $arquivo_path, $drive_link ?: null, $drive_file_id, $tamanho_kb, $prof_id]);
                $msg_sucesso = 'Material adicionado à galeria!';
            } catch (PDOException $e) {
                $msg_erro = 'Erro ao salvar material.';
            }
        } elseif ($msg_erro === '') {
            $msg_erro = 'Informe o título do material.';
        }
    }
}

// Turmas deste professor/treinador
$minhas_turmas = [];
try {
    $stmtT = $pdo->prepare("SELECT t.id, t.nome, h.dia_semana, h.horario FROM turmas t JOIN turma_professores tp ON t.id = tp.turma_id LEFT JOIN horarios_treino h ON t.horario_id = h.id WHERE tp.professor_id = ? AND t.ativo = 1 ORDER BY t.nome");
    $stmtT->execute([$prof_id]);
    $minhas_turmas = $stmtT->fetchAll();
} catch (Exception $e) {}

// Alunos apenas das turmas deste professor/treinador
$alunas = [];
try {
    $stmtA = $pdo->prepare("SELECT DISTINCT u.*, GROUP_CONCAT(t.nome ORDER BY t.nome SEPARATOR ', ') as turmas_nomes FROM usuarios u JOIN aluno_turmas at_aluno ON u.id = at_aluno.aluno_id JOIN turma_professores tp ON at_aluno.turma_id = tp.turma_id LEFT JOIN turmas t ON at_aluno.turma_id = t.id WHERE tp.professor_id = ? AND u.tipo = 'aluno' GROUP BY u.id ORDER BY u.nome");
    $stmtA->execute([$prof_id]);
    $alunas = $stmtA->fetchAll();
} catch (Exception $e) {
    $alunas = $pdo->query("SELECT * FROM usuarios WHERE tipo = 'aluno' ORDER BY nome")->fetchAll();
}

$planos = $pdo->query("SELECT * FROM planos_tabela WHERE ativo = 1")->fetchAll();

$missao_atual = false;
try { $missao_atual = $pdo->query("SELECT * FROM missoes_semana WHERE status = 'ativa' ORDER BY id DESC LIMIT 1")->fetch(); } catch (Exception $e) {}

$historico_pagamentos = [];
try {
    $aluno_ids = array_column($alunas, 'id');
    if (!empty($aluno_ids)) {
        $in = implode(',', array_fill(0, count($aluno_ids), '?'));
        $stmtHP = $pdo->prepare("SELECT p.*, pl.nome_plano FROM pagamentos p JOIN planos_tabela pl ON p.plano_id = pl.id WHERE p.aluna_id IN ($in) ORDER BY p.data_pagamento DESC LIMIT 500");
        $stmtHP->execute($aluno_ids);
        foreach ($stmtHP->fetchAll() as $row) {
            $historico_pagamentos[(int)$row['aluna_id']][] = $row;
        }
    }
} catch (Exception $e) {}

// Pastas das turmas deste treinador
$pastas_minhas_turmas = [];
try {
    $turma_ids = array_column($minhas_turmas, 'id');
    if (!empty($turma_ids)) {
        $inPT = implode(',', array_fill(0, count($turma_ids), '?'));
        $stmtPT = $pdo->prepare("SELECT pt.*, t.nome as turma_nome FROM pastas_turma pt JOIN turmas t ON pt.turma_id = t.id WHERE pt.turma_id IN ($inPT) AND pt.ativo = 1 ORDER BY t.nome");
        $stmtPT->execute($turma_ids);
        $pastas_minhas_turmas = $stmtPT->fetchAll();
    }
} catch (Exception $e) {}

// Brindes disponíveis
$brindes_disponiveis = [];
try { $brindes_disponiveis = $pdo->query("SELECT * FROM brindes WHERE ativo=1 ORDER BY nome")->fetchAll(); } catch (Exception $e) {}

// Brindes a entregar (ganhos pelos alunos deste treinador)
$brindes_pendentes = [];
try {
    $aluno_ids = array_column($alunas, 'id');
    if (!empty($aluno_ids)) {
        $in = implode(',', array_fill(0, count($aluno_ids), '?'));
        $stmtBP = $pdo->prepare("SELECT ba.*, u.nome as aluna_nome, b.nome as brinde_nome FROM brindes_aluna ba JOIN usuarios u ON ba.aluna_id=u.id LEFT JOIN brindes b ON ba.brinde_id=b.id WHERE ba.aluna_id IN ($in) ORDER BY ba.created_at DESC");
        $stmtBP->execute($aluno_ids);
        $brindes_pendentes = $stmtBP->fetchAll();
    }
} catch (Exception $e) {}

// Presences today (for attendance prefill)
$presencas_hoje = [];
try {
    $aluno_ids_all = array_column($alunas, 'id');
    if (!empty($aluno_ids_all)) {
        $in2 = implode(',', array_fill(0, count($aluno_ids_all), '?'));
        $args = array_merge($aluno_ids_all, [date('Y-m-d')]);
        $stmtPH = $pdo->prepare("SELECT aluno_id FROM presencas WHERE aluno_id IN ($in2) AND data_presenca=? AND presente=1");
        $stmtPH->execute($args);
        $presencas_hoje = array_map('intval', $stmtPH->fetchAll(PDO::FETCH_COLUMN));
    }
} catch (Exception $e) {}

// Materiais de Marketing (visível apenas com permissão galeria_ver)
$materiais_mkt = [];
if (hasPerm($minha_permissoes, 'galeria_ver')) {
    try {
        $materiais_mkt = $pdo->query("SELECT * FROM materiais_marketing WHERE ativo=1 ORDER BY created_at DESC")->fetchAll();
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Elite Thai Girls | Treinador</title>
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
        .matricula-details{border:1px solid var(--borda);border-radius:12px;margin-bottom:12px;overflow:hidden}
        .matricula-details[open]{border-color:#d62bc5}
        .matricula-summary{cursor:pointer;font-size:13px;font-weight:700;color:#d62bc5;list-style:none;padding:12px 15px;background:rgba(214,43,197,.07);display:flex;align-items:center;gap:8px;user-select:none}
        .matricula-summary::-webkit-details-marker{display:none}
        .matricula-summary::after{content:'▼';margin-left:auto;font-size:10px;transition:transform .3s;color:var(--cinza)}
        .matricula-details[open] .matricula-summary::after{transform:rotate(180deg)}
        .matricula-details > div{padding:0 15px 15px}
        .summary-hint{font-size:11px;color:var(--cinza);font-weight:400;margin-left:4px}
    </style>
</head>
<body>
<div class="app">

    <div class="header">
        <h1><span>Elite Thai Girls</span> | Treinador</h1>
        <a href="logout.php" class="btn-sair" title="Sair"><i class="fas fa-power-off"></i></a>
    </div>

    <?php if ($msg_sucesso): ?><div class="alerta"><i class="fas fa-check-circle"></i> <?= e($msg_sucesso) ?></div><?php endif; ?>
    <?php if ($msg_erro): ?><div class="alerta-erro"><i class="fas fa-exclamation-triangle"></i> <?= e($msg_erro) ?></div><?php endif; ?>

    <!-- Pastas das Turmas -->
    <?php if (!empty($pastas_minhas_turmas)): ?>
    <?php foreach ($pastas_minhas_turmas as $pasta): ?>
    <?php $drive_folder_id = extractDriveFolderId($pasta['drive_link']); ?>
    <div class="card" style="border-color:#4285F4;background:linear-gradient(180deg,var(--card),rgba(66,133,244,.07))">
        <h3 class="card-titulo" style="color:#4285F4"><i class="fab fa-google-drive" style="background:none;-webkit-text-fill-color:#4285F4"></i> <?= e($pasta['titulo']) ?></h3>
        <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:6px"><i class="fas fa-layer-group" style="color:#7b2cbf"></i> <?= e($pasta['turma_nome']) ?></p>
        <?php if (!empty($pasta['descricao'])): ?>
        <p style="font-size:13px;color:var(--cinza);margin-bottom:16px"><?= e($pasta['descricao']) ?></p>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($drive_folder_id): ?>
            <button type="button" onclick="abrirGaleria('<?= htmlspecialchars($drive_folder_id, ENT_QUOTES) ?>','<?= e($pasta['titulo']) ?>')" class="btn-submit" style="display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(90deg,#4285F4,#0F9D58);box-shadow:0 5px 15px rgba(66,133,244,.35);flex:1">
                <i class="fas fa-images"></i> Ver Galeria
            </button>
            <?php endif; ?>
            <a href="<?= e($pasta['drive_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn-submit" style="display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(90deg,#0F9D58,#4285F4);box-shadow:0 5px 15px rgba(15,157,88,.3);text-decoration:none;flex:1">
                <i class="fab fa-google-drive"></i> Upload / Drive
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Chamada -->
    <div class="card" style="border-left:4px solid #2ecc71">
        <h3 class="card-titulo"><i class="fas fa-clipboard-list" style="background:none;-webkit-text-fill-color:#2ecc71"></i> Chamada da Turma</h3>
        <p style="font-size:12px;color:var(--cinza);margin-top:-10px;margin-bottom:15px">Selecione a turma, confirme a data e marque quem compareceu.</p>
        <form method="POST" id="formChamada">
            <input type="hidden" name="acao" value="chamada_turma">
            <select name="turma_id" id="selectTurma" onchange="carregarAlunos()" required>
                <option value="">Selecione a turma...</option>
                <?php foreach ($minhas_turmas as $mt): ?>
                    <option value="<?= (int)$mt['id'] ?>"><?= e($mt['nome']) ?><?= $mt['dia_semana'] ? ' — ' . e($mt['dia_semana'] . ' ' . $mt['horario']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px">Data da Aula</label>
            <input type="date" name="data_presenca" id="dataPresenca" value="<?= date('Y-m-d') ?>" required style="margin-bottom:15px">
            <div id="listaAlunos" style="margin-bottom:15px;display:none">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                    <div style="font-size:12px;color:var(--cinza);text-transform:uppercase;font-weight:800;letter-spacing:1px">
                        <i class="fas fa-users" style="color:#2ecc71"></i> Alunos da Turma
                        <span id="contadorAlunos" style="background:rgba(46,204,113,.2);color:#2ecc71;border:1px solid #2ecc71;border-radius:20px;padding:2px 10px;font-size:11px;margin-left:8px">0</span>
                    </div>
                    <div style="display:flex;gap:6px">
                        <button type="button" onclick="selecionarTodos(true)" style="background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid #2ecc71;border-radius:8px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer"><i class="fas fa-check-square"></i> Todos</button>
                        <button type="button" onclick="selecionarTodos(false)" style="background:rgba(255,68,68,.1);color:#ff4444;border:1px solid #ff4444;border-radius:8px;padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer"><i class="fas fa-square"></i> Nenhum</button>
                    </div>
                </div>
                <div id="alunosContainer">
                <?php foreach ($alunas as $a): ?>
                <label id="la-<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.02);border:1px solid var(--borda);padding:12px;border-radius:12px;margin-bottom:8px;cursor:pointer;transition:.3s" class="aluno-check-row" data-aluno-id="<?= (int)$a['id'] ?>">
                    <input type="checkbox" name="presentes[]" value="<?= (int)$a['id'] ?>" style="width:20px;height:20px;margin:0;accent-color:#2ecc71;flex-shrink:0" <?= in_array((int)$a['id'], $presencas_hoje) ? 'checked' : '' ?>>
                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2ecc71,#11998e);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#000;flex-shrink:0;text-transform:uppercase"><?= mb_substr(e($a['nome']), 0, 1) ?></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($a['nome']) ?></div>
                        <?php if (!empty($a['turmas_nomes'])): ?>
                        <div style="font-size:11px;color:var(--cinza);margin-top:2px"><i class="fas fa-layer-group" style="color:#7b2cbf;margin-right:3px"></i><?= e($a['turmas_nomes']) ?></div>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-check-circle" style="color:#2ecc71;font-size:16px;opacity:0;transition:.2s" class="check-icon"></i>
                </label>
                <?php endforeach; ?>
                </div>
                <div id="semAlunos" style="display:none;text-align:center;padding:20px;color:var(--cinza);font-size:13px"><i class="fas fa-user-slash" style="font-size:24px;display:block;margin-bottom:8px;color:#333"></i>Nenhum aluno nesta turma</div>
            </div>
            <button type="submit" id="btnChamada" class="btn-submit" style="display:none;background:linear-gradient(90deg,#11998e,#38ef7d);box-shadow:0 5px 15px rgba(56,239,125,.3);color:#000">
                <i class="fas fa-check-double"></i> Salvar Chamada
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
    <?php if (hasPerm($minha_permissoes, 'alunos_ver')): ?>
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
                            <?php if (hasPerm($minha_permissoes, 'alunos_editar')): ?>
                            <button onclick="abrirEdicao(<?= (int)$a['id'] ?>, <?= e(json_encode($a['nome'])) ?>, <?= e(json_encode($a['email'])) ?>, <?= e(json_encode($a['telefone'] ?? '')) ?>)" class="btn-acao btn-editar" title="Editar"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este registo?')">
                                <input type="hidden" name="acao" value="excluir_aluno">
                                <input type="hidden" name="excluir_id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="btn-acao btn-excluir" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="aluna-info"><i class="fas fa-envelope" style="color:#d62bc5;margin-right:5px"></i> <?= e($a['email']) ?></div>
                    <div class="aluna-info"><i class="fab fa-whatsapp" style="color:#2ecc71;margin-right:5px"></i> <?= e($a['telefone'] ?? 'Sem número') ?></div>
                    <div class="aluna-info"><i class="fas fa-hand-rock" style="color:#FF8C00;margin-right:5px"></i> Treinos: <strong><?= (int)($a['treinos_concluidos'] ?? 0) ?></strong></div>
                    <?php if (!empty($a['turmas_nomes'])): ?>
                        <div class="aluna-info"><i class="fas fa-layer-group" style="color:#7b2cbf;margin-right:5px"></i> <?= e($a['turmas_nomes']) ?></div>
                    <?php endif; ?>
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
                        <?php if (hasPerm($minha_permissoes, 'alunos_editar')): ?>
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
                        <?php endif; // alunos_editar ?>
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

        <?php if (hasPerm($minha_permissoes, 'alunos_editar')): ?>
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
            <div class="section-title"><i class="fas fa-layer-group"></i> Turma</div>
            <select name="turma_id">
                <option value="">Sem turma (opcional)</option>
                <?php foreach ($minhas_turmas as $mt): ?>
                    <option value="<?= (int)$mt['id'] ?>"><?= e($mt['nome']) ?><?= $mt['dia_semana'] ? ' — ' . e($mt['dia_semana'] . ' ' . $mt['horario']) : '' ?></option>
                <?php endforeach; ?>
            </select>

            <details class="matricula-details">
                <summary class="matricula-summary"><i class="fas fa-notes-medical"></i> Ficha Médica Muay Thai <span class="summary-hint">(opcional — clique para expandir)</span></summary>
                <div style="padding-top:12px">
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
                </div>
            </details>

            <details class="matricula-details">
                <summary class="matricula-summary"><i class="fas fa-phone-alt"></i> Contato de Emergência <span class="summary-hint">(opcional — clique para expandir)</span></summary>
                <div style="padding-top:12px">
                    <div class="row-2">
                        <input type="text" name="emergencia_nome" placeholder="Nome do Contato">
                        <input type="text" name="emergencia_telefone" placeholder="Telefone de Emergência">
                    </div>
                </div>
            </details>

            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Cadastrar</button>
        </form>
        <?php endif; // alunos_editar ?>
    </div>
    <?php endif; // alunos_ver ?>

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
    <?php if (hasPerm($minha_permissoes, 'pagamentos_registrar')): ?>
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
    <?php endif; // pagamentos_registrar ?>

    <!-- Brindes -->
    <?php if (hasPerm($minha_permissoes, 'brindes_ver')): ?>
    <div class="card" style="border-color:#f1c40f">
        <h3 class="card-titulo" style="color:#f1c40f"><i class="fas fa-gift" style="background:none;-webkit-text-fill-color:#f1c40f"></i> Brindes & Recompensas</h3>

        <!-- Adicionar brinde -->
        <details style="margin-bottom:15px">
            <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#f1c40f;list-style:none;padding:10px;background:rgba(241,196,15,.07);border-radius:10px"><i class="fas fa-plus-circle"></i> Adicionar novo brinde</summary>
            <form method="POST" style="margin-top:12px">
                <input type="hidden" name="acao" value="add_brinde">
                <input type="text" name="nome_brinde" placeholder="Nome do brinde (Ex: Garrafa)" required>
                <input type="text" name="desc_brinde" placeholder="Descrição (opcional)">
                <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#f1c40f,#e67e22);color:#000;box-shadow:none;font-size:13px;padding:12px"><i class="fas fa-plus"></i> Salvar</button>
            </form>
        </details>

        <!-- Registar brinde manual -->
        <details style="margin-bottom:15px">
            <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#d62bc5;list-style:none;padding:10px;background:rgba(214,43,197,.07);border-radius:10px"><i class="fas fa-hand-holding-heart"></i> Atribuir brinde manualmente</summary>
            <form method="POST" style="margin-top:12px">
                <input type="hidden" name="acao" value="brinde_manual">
                <select name="aluna_id" required>
                    <option value="">Selecione a aluna...</option>
                    <?php foreach ($alunas as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e($a['nome']) ?></option><?php endforeach; ?>
                </select>
                <input type="month" name="mes_referencia" value="<?= date('Y-m') ?>" required>
                <input type="text" name="brinde_manual" placeholder="Ex: Camiseta Elite, Luva personalizada..." required>
                <button type="submit" class="btn-submit" style="background:var(--pink);font-size:13px;padding:12px"><i class="fas fa-gift"></i> Registar</button>
            </form>
        </details>

        <!-- Lista de brindes pendentes -->
        <div style="font-size:12px;color:var(--cinza);margin-bottom:10px;text-transform:uppercase;font-weight:800;letter-spacing:1px"><i class="fas fa-list-ul"></i> Brindes Registados</div>
        <?php if (empty($brindes_pendentes)): ?>
            <p style="font-size:12px;color:#555;text-align:center">Nenhum brinde registado ainda.</p>
        <?php else: ?>
        <?php foreach ($brindes_pendentes as $bp): ?>
            <div style="background:rgba(255,255,255,.02);border:1px solid <?= $bp['entregue'] ? '#2ecc71' : '#f1c40f' ?>;padding:12px;border-radius:12px;margin-bottom:8px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                    <strong style="font-size:13px"><?= e($bp['aluna_nome']) ?></strong>
                    <?php if ($bp['entregue']): ?>
                        <span style="background:rgba(46,204,113,.15);color:#2ecc71;border:1px solid #2ecc71;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800">✅ Entregue</span>
                    <?php elseif (!$bp['roleta_girada']): ?>
                        <span style="background:rgba(241,196,15,.15);color:#f1c40f;border:1px solid #f1c40f;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800">⏳ Aguardando roleta</span>
                    <?php else: ?>
                        <span style="background:rgba(214,43,197,.15);color:#d62bc5;border:1px solid #d62bc5;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800">🎁 Roleta girada</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--cinza)">
                    <?php $txt = $bp['brinde_nome'] ?? $bp['brinde_manual'] ?? '—'; ?>
                    <i class="fas fa-gift" style="color:#f1c40f;margin-right:4px"></i> <?= e($txt) ?>
                    &nbsp;|&nbsp;<i class="fas fa-calendar-alt" style="color:#7b2cbf;margin-right:4px"></i> <?= e($bp['mes_referencia']) ?>
                </div>
                <?php if (!$bp['entregue'] && hasPerm($minha_permissoes, 'brindes_entregar')): ?>
                <form method="POST" style="margin-top:8px">
                    <input type="hidden" name="acao" value="entregar_brinde">
                    <input type="hidden" name="ba_id" value="<?= (int)$bp['id'] ?>">
                    <button type="submit" class="btn-submit" style="background:linear-gradient(90deg,#11998e,#38ef7d);color:#000;box-shadow:none;font-size:12px;padding:10px"><i class="fas fa-box-open"></i> Marcar como Entregue</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; // brindes_ver ?>

    <!-- Galeria de Marketing -->
    <?php if (hasPerm($minha_permissoes, 'galeria_ver')): ?>
    <div class="card" style="border-color:#d62bc5">
        <h3 class="card-titulo"><i class="fas fa-images"></i> Galeria de Marketing</h3>

        <?php if (hasPerm($minha_permissoes, 'galeria_upload')): ?>
        <details style="margin-bottom:15px">
            <summary style="cursor:pointer;font-size:13px;font-weight:700;color:#d62bc5;list-style:none;padding:10px;background:rgba(214,43,197,.07);border-radius:10px"><i class="fas fa-cloud-upload-alt"></i> Adicionar material</summary>
            <form method="POST" enctype="multipart/form-data" style="margin-top:12px">
                <input type="hidden" name="acao" value="add_material">
                <input type="text" name="titulo" placeholder="Título do material" required>
                <textarea name="descricao" rows="2" placeholder="Descrição (opcional)"></textarea>
                <select name="categoria">
                    <option value="Geral">Geral</option>
                    <option value="Treino">Treino</option>
                    <option value="Nutrição">Nutrição</option>
                    <option value="Evento">Evento</option>
                    <option value="Institucional">Institucional</option>
                    <option value="Promocional">Promocional</option>
                </select>
                <select name="tipo_arquivo" id="mktTipoSelect" onchange="toggleMktUpload()">
                    <option value="imagem">🖼️ Imagem</option>
                    <option value="video">🎥 Vídeo</option>
                    <option value="documento">📄 Documento</option>
                    <option value="link">🔗 Link Google Drive</option>
                    <option value="outro">📁 Outro</option>
                </select>
                <div id="mktFileDiv">
                    <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fas fa-upload"></i> Arquivo (máx. 50 MB)</label>
                    <input type="file" name="arquivo" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="padding:10px">
                </div>
                <div id="mktLinkDiv" style="display:none">
                    <label style="font-size:12px;color:var(--cinza);display:block;margin-bottom:4px"><i class="fab fa-google-drive"></i> Link do Google Drive</label>
                    <input type="url" name="drive_link" placeholder="https://drive.google.com/file/d/...">
                </div>
                <button type="submit" class="btn-submit" style="font-size:13px;padding:12px"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
        </details>
        <?php endif; ?>

        <?php if (empty($materiais_mkt)): ?>
            <p style="font-size:12px;color:var(--cinza);text-align:center;padding:20px 0"><i class="fas fa-images" style="font-size:30px;display:block;margin-bottom:8px;opacity:.3"></i>Nenhum material na galeria.</p>
        <?php else: ?>
        <?php
            $mkt_icons  = ['imagem'=>'fa-image','video'=>'fa-film','documento'=>'fa-file-alt','link'=>'fa-link','outro'=>'fa-file'];
            $mkt_colors = ['imagem'=>'#d62bc5','video'=>'#3498db','documento'=>'#f39c12','link'=>'#4285F4','outro'=>'#7b2cbf'];
        ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px">
            <?php foreach ($materiais_mkt as $mat):
                $mt  = $mat['tipo_arquivo'];
                $mc  = $mkt_colors[$mt] ?? '#d62bc5';
                $mi  = $mkt_icons[$mt]  ?? 'fa-file';
                $is_img = ($mt === 'imagem' && !empty($mat['arquivo_path']));
                if (!empty($mat['arquivo_path'])) {
                    $vurl  = e($mat['arquivo_path']);
                    $durl  = e($mat['arquivo_path']);
                    $dattr = 'download';
                } elseif (!empty($mat['drive_file_id'])) {
                    $vurl  = 'https://drive.google.com/file/d/' . e($mat['drive_file_id']) . '/view';
                    $durl  = 'https://drive.google.com/uc?export=download&id=' . e($mat['drive_file_id']);
                    $dattr = 'target="_blank"';
                } elseif (!empty($mat['drive_link'])) {
                    $vurl  = e($mat['drive_link']);
                    $durl  = e($mat['drive_link']);
                    $dattr = 'target="_blank"';
                } else {
                    $vurl  = '';
                    $durl  = '';
                    $dattr = '';
                }
            ?>
            <div style="background:#050308;border:1px solid var(--borda);border-radius:12px;overflow:hidden">
                <div style="height:80px;display:flex;align-items:center;justify-content:center;background:<?= $mc ?>20;border-bottom:1px solid var(--borda)">
                    <?php if ($is_img): ?>
                        <img src="<?= e($mat['arquivo_path']) ?>" alt="<?= e($mat['titulo']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                        <i class="fas <?= $mi ?>" style="font-size:28px;color:<?= $mc ?>"></i>
                    <?php endif; ?>
                </div>
                <div style="padding:10px">
                    <div style="font-weight:700;font-size:12px;margin-bottom:6px;line-height:1.3"><?= e(mb_strimwidth($mat['titulo'], 0, 40, '...')) ?></div>
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                        <?php if ($vurl !== ''): ?>
                        <a href="<?= $vurl ?>" target="_blank" style="background:rgba(52,152,219,.15);color:#3498db;width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;text-decoration:none" title="Visualizar"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                        <?php if ($durl !== '' && hasPerm($minha_permissoes, 'galeria_download')): ?>
                        <a href="<?= $durl ?>" <?= $dattr ?> style="background:rgba(46,204,113,.15);color:#2ecc71;width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;text-decoration:none" title="Download"><i class="fas fa-download"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; // galeria_ver ?>

</div>

<!-- Modal: Galeria da Turma -->
<div id="modalGaleria" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);z-index:9999;flex-direction:column;align-items:stretch">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:#140d1c;border-bottom:1px solid #2a1b3d;flex-shrink:0">
        <div style="display:flex;align-items:center;gap:12px">
            <i class="fab fa-google-drive" style="color:#4285F4;font-size:22px"></i>
            <span id="galeriaTitulo" style="font-weight:800;font-size:15px;text-transform:uppercase;letter-spacing:1px"></span>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <a id="galeriaLinkDrive" href="#" target="_blank" rel="noopener noreferrer" style="background:linear-gradient(90deg,#0F9D58,#4285F4);color:#fff;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:6px"><i class="fab fa-google-drive"></i> Upload</a>
            <button onclick="fecharGaleria()" style="background:#2a1b3d;border:none;color:#ff4444;width:38px;height:38px;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div style="flex:1;overflow:hidden;position:relative">
        <iframe id="galeriaFrame" src="" style="width:100%;height:100%;border:none;background:#000" allowfullscreen></iframe>
    </div>
    <div style="padding:12px 20px;background:#140d1c;border-top:1px solid #2a1b3d;text-align:center;font-size:11px;color:#b5a8c9;flex-shrink:0">
        <i class="fas fa-info-circle"></i> Para fazer <strong>upload</strong>, clique em "Upload / Drive" acima · Para <strong>baixar</strong> um arquivo, clique nele na galeria e escolha Download
    </div>
</div>

<!-- Modal: Recibo de Pagamento -->
<?php if ($recibo_dados): ?>
<div id="modalRecibo" style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.88);z-index:9998;align-items:center;justify-content:center;padding:20px">
    <div style="background:#140d1c;border:1px solid #2ecc71;border-radius:20px;padding:28px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.8)">
        <div style="text-align:center;margin-bottom:20px">
            <div style="font-size:36px;margin-bottom:6px">🧾</div>
            <h3 style="margin:0;font-size:16px;text-transform:uppercase;letter-spacing:2px;color:#2ecc71;font-weight:800">Recibo de Pagamento</h3>
            <p style="font-size:11px;color:#b5a8c9;margin:4px 0 0">Elite Thai Girls · <?= e($recibo_dados['data']) ?></p>
        </div>
        <div style="background:rgba(46,204,113,.06);border:1px solid rgba(46,204,113,.2);border-radius:14px;padding:18px;margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #2a1b3d;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-user" style="color:#d62bc5;margin-right:6px;width:16px;text-align:center"></i> Aluno(a)</span>
                <strong><?= e($recibo_dados['aluno']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #2a1b3d;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-tag" style="color:#f1c40f;margin-right:6px;width:16px;text-align:center"></i> Plano</span>
                <strong><?= e($recibo_dados['plano']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #2a1b3d;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-dollar-sign" style="color:#2ecc71;margin-right:6px;width:16px;text-align:center"></i> Valor</span>
                <strong style="color:#2ecc71;font-size:15px">R$ <?= e($recibo_dados['valor']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #2a1b3d;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-credit-card" style="color:#3498db;margin-right:6px;width:16px;text-align:center"></i> Forma</span>
                <strong><?= e($recibo_dados['forma']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #2a1b3d;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-calendar-check" style="color:#7b2cbf;margin-right:6px;width:16px;text-align:center"></i> Vencimento</span>
                <strong><?= e($recibo_dados['vencimento']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:13px">
                <span style="color:#b5a8c9"><i class="fas fa-user-tie" style="color:#FF8C00;margin-right:6px;width:16px;text-align:center"></i> Lançado por</span>
                <strong><?= e($recibo_dados['treinador']) ?></strong>
            </div>
            <?php if ($recibo_dados['obs'] !== ''): ?>
            <div style="padding:8px 0 0;font-size:12px;color:#b5a8c9;border-top:1px dashed #2a1b3d;margin-top:4px"><i class="fas fa-comment-alt" style="color:#b5a8c9;margin-right:4px"></i> <?= e($recibo_dados['obs']) ?></div>
            <?php endif; ?>
        </div>
        <button onclick="document.getElementById('modalRecibo').style.display='none'" class="btn-submit" style="background:linear-gradient(90deg,#2ecc71,#27ae60);color:#000;box-shadow:0 5px 15px rgba(46,204,113,.3)"><i class="fas fa-check-circle"></i> Fechar Recibo</button>
    </div>
</div>
<?php endif; ?>

<script>
function abrirGaleria(folderId, titulo) {
    document.getElementById('galeriaTitulo').textContent = titulo;
    var embedUrl = 'https://drive.google.com/embeddedfolderview?id=' + folderId + '#grid';
    document.getElementById('galeriaFrame').src = embedUrl;
    document.getElementById('galeriaLinkDrive').href = 'https://drive.google.com/drive/folders/' + folderId;
    var modal = document.getElementById('modalGaleria');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function fecharGaleria() {
    document.getElementById('modalGaleria').style.display = 'none';
    document.getElementById('galeriaFrame').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharGaleria();
});
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

// Chamada: filter students by selected turma using AJAX JSON endpoint
function carregarAlunos() {
    var turmaId = document.getElementById('selectTurma').value;
    var lista = document.getElementById('listaAlunos');
    var btn   = document.getElementById('btnChamada');
    var contador = document.getElementById('contadorAlunos');
    var semAlunos = document.getElementById('semAlunos');
    if (!turmaId) {
        lista.style.display = 'none';
        btn.style.display   = 'none';
        document.querySelectorAll('.aluno-check-row').forEach(function(r){ r.style.display='none'; });
        return;
    }
    lista.style.display = 'block';
    btn.style.display   = 'block';
    fetch('treinador.php?get_alunos_turma=1&turma_id=' + turmaId)
        .then(function(r){ return r.json(); })
        .then(function(ids) {
            var count = 0;
            document.querySelectorAll('.aluno-check-row').forEach(function(row) {
                var aid = parseInt(row.getAttribute('data-aluno-id'));
                if (ids.includes(aid)) {
                    row.style.display = 'flex';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });
            contador.textContent = count;
            semAlunos.style.display = count === 0 ? 'block' : 'none';
            btn.style.display = count > 0 ? 'block' : 'none';
        })
        .catch(function() {
            var count = 0;
            document.querySelectorAll('.aluno-check-row').forEach(function(r){ r.style.display='flex'; count++; });
            contador.textContent = count;
            semAlunos.style.display = 'none';
        });
}
function selecionarTodos(marcar) {
    document.querySelectorAll('.aluno-check-row').forEach(function(row) {
        if (row.style.display !== 'none') {
            row.querySelector('input[type="checkbox"]').checked = marcar;
        }
    });
}
function toggleMktUpload() {
    var t = document.getElementById('mktTipoSelect');
    if (!t) return;
    var v = t.value;
    var fd = document.getElementById('mktFileDiv');
    var ld = document.getElementById('mktLinkDiv');
    if (fd) fd.style.display = v === 'link' ? 'none' : 'block';
    if (ld) ld.style.display = v === 'link' ? 'block' : 'none';
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
}
</script>
</body>
</html>