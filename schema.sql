-- ============================================================
-- ELITE GIRLS / KONEX - Schema Completo do Banco de Dados
-- Senha do Admin Master: konex2026 | Login: konex
-- ============================================================

CREATE DATABASE IF NOT EXISTS `iubsit15_academia` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `iubsit15_academia`;

-- ============================================================
-- TABELA: usuarios
-- Armazena todos os usuários do sistema com controle de acesso
-- Níveis: admin | professor | treinador | instrutor | aluno
-- ============================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`                    INT(11) NOT NULL AUTO_INCREMENT,
    `nome`                  VARCHAR(150) NOT NULL,
    `email`                 VARCHAR(150) NOT NULL,
    `telefone`              VARCHAR(20) DEFAULT NULL,
    `senha`                 VARCHAR(255) NOT NULL,
    `tipo`                  ENUM('admin','professor','treinador','instrutor','aluno') NOT NULL DEFAULT 'aluno',
    `restricoes_medicas`    TEXT DEFAULT NULL,
    `xp_atual`              INT(11) NOT NULL DEFAULT 0,
    `treinos_concluidos`    INT(11) NOT NULL DEFAULT 0,
    `ativo`                 TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Ficha Médica Muay Thai
    `data_nascimento`       DATE DEFAULT NULL,
    `tipo_sanguineo`        VARCHAR(5) DEFAULT NULL,
    `peso`                  DECIMAL(5,2) DEFAULT NULL,
    `altura`                DECIMAL(5,2) DEFAULT NULL,
    `doencas_cronicas`      TEXT DEFAULT NULL,
    `medicamentos_uso`      TEXT DEFAULT NULL,
    `historico_lesoes`      TEXT DEFAULT NULL,
    `emergencia_nome`       VARCHAR(150) DEFAULT NULL,
    `emergencia_telefone`   VARCHAR(20) DEFAULT NULL,
    `objetivo_treino`       TEXT DEFAULT NULL,
    `nivel_experiencia`     ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADMIN MASTER (login: konex | senha: konex2026)
-- Hash bcrypt gerado com password_hash('konex2026', PASSWORD_DEFAULT)
-- ============================================================
INSERT INTO `usuarios` (`nome`, `email`, `senha`, `tipo`) VALUES
('Gestão Konex', 'konex', '$2y$10$ruGtUh1PuFB7.1HsDCA21OCvLd7wyr0MTD7W.aN4D3zdTfORXEKWG', 'admin')
ON DUPLICATE KEY UPDATE `senha` = VALUES(`senha`), `tipo` = 'admin';

-- ============================================================
-- TABELA: planos_tabela
-- Planos de mensalidade disponíveis na academia
-- ============================================================
CREATE TABLE IF NOT EXISTS `planos_tabela` (
    `id`              INT(11) NOT NULL AUTO_INCREMENT,
    `nome_plano`      VARCHAR(100) NOT NULL,
    `valor`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `duracao_meses`   INT(11) NOT NULL DEFAULT 1,
    `ativo`           TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `planos_tabela` (`nome_plano`, `valor`, `duracao_meses`) VALUES
('Mensal', 150.00, 1),
('Trimestral', 400.00, 3),
('Semestral', 750.00, 6)
ON DUPLICATE KEY UPDATE `nome_plano` = VALUES(`nome_plano`);

-- ============================================================
-- TABELA: horarios_treino
-- Grade de horários das aulas
-- ============================================================
CREATE TABLE IF NOT EXISTS `horarios_treino` (
    `id`          INT(11) NOT NULL AUTO_INCREMENT,
    `dia_semana`  VARCHAR(20) NOT NULL,
    `horario`     VARCHAR(20) NOT NULL,
    `descricao`   VARCHAR(150) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: pagamentos
-- Histórico de pagamentos / matrículas dos alunos
-- ============================================================
CREATE TABLE IF NOT EXISTS `pagamentos` (
    `id`                  INT(11) NOT NULL AUTO_INCREMENT,
    `aluna_id`            INT(11) NOT NULL,
    `treinador_id`        INT(11) DEFAULT NULL,
    `plano_id`            INT(11) NOT NULL,
    `valor_pago`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `data_pagamento`      DATE NOT NULL,
    `data_vencimento`     DATE NOT NULL,
    `observacao_aluna`    TEXT DEFAULT NULL,
    `forma_pagamento`     ENUM('pix','credito','debito','dinheiro') NOT NULL DEFAULT 'pix',
    `percentual_academia` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    PRIMARY KEY (`id`),
    KEY `fk_pag_aluna`     (`aluna_id`),
    KEY `fk_pag_plano`     (`plano_id`),
    KEY `fk_pag_treinador` (`treinador_id`),
    CONSTRAINT `fk_pag_aluna`     FOREIGN KEY (`aluna_id`)     REFERENCES `usuarios`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pag_plano`     FOREIGN KEY (`plano_id`)     REFERENCES `planos_tabela` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pag_treinador` FOREIGN KEY (`treinador_id`) REFERENCES `usuarios`      (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: mural_avisos
-- Avisos publicados pelo admin visíveis para todos
-- ============================================================
CREATE TABLE IF NOT EXISTS `mural_avisos` (
    `id`                INT(11) NOT NULL AUTO_INCREMENT,
    `autor_id`          INT(11) NOT NULL,
    `titulo`            VARCHAR(150) NOT NULL,
    `mensagem`          TEXT NOT NULL,
    `tipo`              ENUM('info','aviso','urgente') NOT NULL DEFAULT 'info',
    `data_publicacao`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_aviso_autor` (`autor_id`),
    CONSTRAINT `fk_aviso_autor` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: leads_indicacoes
-- Convites / indicações de novos alunos por alunos existentes
-- ============================================================
CREATE TABLE IF NOT EXISTS `leads_indicacoes` (
    `id`                  INT(11) NOT NULL AUTO_INCREMENT,
    `aluna_id_indicou`    INT(11) NOT NULL,
    `nome_convidada`      VARCHAR(150) NOT NULL,
    `telefone_convidada`  VARCHAR(20) NOT NULL,
    `status`              ENUM('novo','contatado','matriculado') NOT NULL DEFAULT 'novo',
    `data_indicacao`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_lead_aluna` (`aluna_id_indicou`),
    CONSTRAINT `fk_lead_aluna` FOREIGN KEY (`aluna_id_indicou`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: missoes_semana
-- Missões semanais criadas por treinadores/instrutores
-- ============================================================
CREATE TABLE IF NOT EXISTS `missoes_semana` (
    `id`            INT(11) NOT NULL AUTO_INCREMENT,
    `treinador_id`  INT(11) NOT NULL,
    `titulo`        VARCHAR(150) NOT NULL,
    `descricao`     TEXT DEFAULT NULL,
    `status`        ENUM('ativa','inativa') NOT NULL DEFAULT 'ativa',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_missao_treinador` (`treinador_id`),
    CONSTRAINT `fk_missao_treinador` FOREIGN KEY (`treinador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: conquistas
-- Medalhas/conquistas atribuídas aos alunos
-- ============================================================
CREATE TABLE IF NOT EXISTS `conquistas` (
    `id`              INT(11) NOT NULL AUTO_INCREMENT,
    `aluna_id`        INT(11) NOT NULL,
    `treinador_id`    INT(11) DEFAULT NULL,
    `nome_medalha`    VARCHAR(100) NOT NULL,
    `icone_emoji`     VARCHAR(20) DEFAULT NULL,
    `xp_ganho`        INT(11) NOT NULL DEFAULT 0,
    `data_conquista`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_conq_aluna`     (`aluna_id`),
    KEY `fk_conq_treinador` (`treinador_id`),
    CONSTRAINT `fk_conq_aluna`     FOREIGN KEY (`aluna_id`)     REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conq_treinador` FOREIGN KEY (`treinador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: turmas (Turmas com professor e horário)
-- ============================================================
CREATE TABLE IF NOT EXISTS `turmas` (
    `id`           INT(11) NOT NULL AUTO_INCREMENT,
    `nome`         VARCHAR(100) NOT NULL,
    `professor_id` INT(11) DEFAULT NULL,
    `horario_id`   INT(11) DEFAULT NULL,
    `ativo`        TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `fk_turma_prof` (`professor_id`),
    KEY `fk_turma_hor`  (`horario_id`),
    CONSTRAINT `fk_turma_prof` FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_turma_hor`  FOREIGN KEY (`horario_id`)   REFERENCES `horarios_treino` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: turma_professores (Múltiplos professores por turma)
-- ============================================================
CREATE TABLE IF NOT EXISTS `turma_professores` (
    `turma_id`     INT(11) NOT NULL,
    `professor_id` INT(11) NOT NULL,
    PRIMARY KEY (`turma_id`, `professor_id`),
    KEY `fk_tp_turma` (`turma_id`),
    KEY `fk_tp_prof`  (`professor_id`),
    CONSTRAINT `fk_tp_turma` FOREIGN KEY (`turma_id`)     REFERENCES `turmas`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tp_prof`  FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: aluno_turmas (Alunos alocados em turmas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `aluno_turmas` (
    `id`       INT(11) NOT NULL AUTO_INCREMENT,
    `aluno_id` INT(11) NOT NULL,
    `turma_id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_aluno_turma` (`aluno_id`, `turma_id`),
    KEY `fk_at_aluno` (`aluno_id`),
    KEY `fk_at_turma` (`turma_id`),
    CONSTRAINT `fk_at_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_at_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: presencas (Registo de presença diária)
-- ============================================================
CREATE TABLE IF NOT EXISTS `presencas` (
    `id`            INT(11) NOT NULL AUTO_INCREMENT,
    `aluno_id`      INT(11) NOT NULL,
    `professor_id`  INT(11) DEFAULT NULL,
    `data_presenca` DATE NOT NULL,
    `presente`      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_presenca_dia` (`aluno_id`, `data_presenca`),
    KEY `fk_pres_aluno` (`aluno_id`),
    KEY `fk_pres_prof`  (`professor_id`),
    CONSTRAINT `fk_pres_aluno` FOREIGN KEY (`aluno_id`)     REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pres_prof`  FOREIGN KEY (`professor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRAÇÕES — colunas adicionadas em atualização
-- Execute em bancos já existentes para aplicar as mudanças
-- ============================================================

ALTER TABLE `usuarios`
    ADD COLUMN IF NOT EXISTS `data_nascimento`     DATE             DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `tipo_sanguineo`      VARCHAR(5)       DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `peso`                DECIMAL(5,2)     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `altura`              DECIMAL(5,2)     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `doencas_cronicas`    TEXT             DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `medicamentos_uso`    TEXT             DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `historico_lesoes`    TEXT             DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `emergencia_nome`     VARCHAR(150)     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `emergencia_telefone` VARCHAR(20)      DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `objetivo_treino`     TEXT             DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `nivel_experiencia`   ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante';

ALTER TABLE `pagamentos`
    ADD COLUMN IF NOT EXISTS `forma_pagamento` ENUM('pix','credito','debito','dinheiro') NOT NULL DEFAULT 'pix';

-- Migração: popular turma_professores a partir do professor_id legado em turmas
INSERT IGNORE INTO `turma_professores` (`turma_id`, `professor_id`)
SELECT `id`, `professor_id` FROM `turmas` WHERE `professor_id` IS NOT NULL;

CREATE TABLE IF NOT EXISTS `brindes` (
    `id`          INT(11) NOT NULL AUTO_INCREMENT,
    `nome`        VARCHAR(150) NOT NULL,
    `descricao`   TEXT DEFAULT NULL,
    `ativo`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brindes` (`nome`,`descricao`) VALUES
('Garrafa Elite','Garrafa personalizada Elite Thai Girls'),
('Camiseta Elite','Camiseta oficial da academia'),
('Luva Elite','Luva de treino personalizada')
ON DUPLICATE KEY UPDATE nome=nome;

CREATE TABLE IF NOT EXISTS `brindes_aluna` (
    `id`              INT(11) NOT NULL AUTO_INCREMENT,
    `aluna_id`        INT(11) NOT NULL,
    `brinde_id`       INT(11) DEFAULT NULL,
    `brinde_manual`   VARCHAR(200) DEFAULT NULL,
    `mes_referencia`  VARCHAR(7) NOT NULL,
    `roleta_girada`   TINYINT(1) NOT NULL DEFAULT 0,
    `entregue`        TINYINT(1) NOT NULL DEFAULT 0,
    `data_entrega`    DATETIME DEFAULT NULL,
    `instrutor_id`    INT(11) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_aluna_mes` (`aluna_id`,`mes_referencia`),
    KEY `fk_ba_aluna`    (`aluna_id`),
    KEY `fk_ba_brinde`   (`brinde_id`),
    KEY `fk_ba_instrutor`(`instrutor_id`),
    CONSTRAINT `fk_ba_aluna`     FOREIGN KEY (`aluna_id`)    REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ba_brinde`    FOREIGN KEY (`brinde_id`)   REFERENCES `brindes`  (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ba_instrutor` FOREIGN KEY (`instrutor_id`)REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migração: brindes e brindes_aluna (execute em bancos existentes)
-- (tables created above with IF NOT EXISTS — safe to re-run)
