-- ============================================================
--  BiblioTech — Script SQL de Criação do Banco de Dados
--  Versão: 1.0
--  Banco  : MySQL 8.x
--  Charset: utf8mb4 (suporte completo a Unicode / emojis)
--  Collate: utf8mb4_unicode_ci (ordenação correta em PT-BR)
-- ============================================================
--
--  REGRAS DE SEGURANÇA APLICADAS:
--    [1] Chaves primárias com AUTO_INCREMENT em todas as tabelas.
--    [2] Chaves estrangeiras com ON DELETE RESTRICT para proteger
--        a integridade referencial.
--    [3] UNIQUE em e-mail (usuarios) e matrícula (alunos).
--    [4] Campo "ativo" (TINYINT) em todas as tabelas principais —
--        registros nunca são excluídos fisicamente.
--    [5] Tipos de dados adequados a cada campo (YEAR, DATE,
--        ENUM, TEXT, TINYINT, etc.).
--    [6] Senhas nunca armazenadas em texto puro. Os hashes
--        abaixo foram gerados com bcrypt cost=10, compatível
--        com password_hash($senha, PASSWORD_DEFAULT) do PHP.
--    [7] Índices adicionais para acelerar as consultas mais
--        frequentes do sistema.
--
-- ============================================================


-- ------------------------------------------------------------
-- 0. BANCO DE DADOS
-- ------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS bibliotech
    CHARACTER SET  utf8mb4
    COLLATE        utf8mb4_unicode_ci;

USE bibliotech;


-- ------------------------------------------------------------
-- 1. TABELA: usuarios
--    Armazena os dados de acesso dos operadores do sistema.
--    Apenas perfis 'admin' e 'bibliotecario' fazem login.
--    Senhas armazenadas como hash bcrypt (password_hash PHP).
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS usuarios (
    id         INT             NOT NULL AUTO_INCREMENT,
    nome       VARCHAR(100)    NOT NULL,
    email      VARCHAR(100)    NOT NULL,           -- login único
    senha      VARCHAR(255)    NOT NULL,           -- hash bcrypt
    perfil     ENUM(
                   'admin',
                   'bibliotecario'
               )               NOT NULL DEFAULT 'bibliotecario',
    ativo      TINYINT(1)      NOT NULL DEFAULT 1, -- 1=ativo 0=inativo
    criado_em  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_usuarios     PRIMARY KEY (id),
    CONSTRAINT uq_usuarios_email UNIQUE (email)   -- [3] e-mail único
);

-- ── Usuário administrador padrão ────────────────────────────
--    Login : admin@bibliotech.com
--    Senha : admin123  (hash gerado com bcrypt cost=10)
--    ⚠  Altere a senha imediatamente após o primeiro acesso!
INSERT INTO usuarios (nome, email, senha, perfil) VALUES (
    'Administrador',
    'admin@bibliotech.com',
    '$2y$10$SxAFrvBQwTmCukzd83HOUeKnkaWvXN9u7drFxJq5U6SnvXcG4NRUS',
    'admin'
);

-- ── Usuário bibliotecário de demonstração ───────────────────
--    Login : bibliotecario@bibliotech.com
--    Senha : biblio123  (hash gerado com bcrypt cost=10)
--    ⚠  Altere ou remova este usuário em produção!
INSERT INTO usuarios (nome, email, senha, perfil) VALUES (
    'Bibliotecário Padrão',
    'bibliotecario@bibliotech.com',
    '$2y$10$//fcb6Gho1dp0yfH0/SjgeyBvNKZvyxqHiu3dzqeYw6MQa8QFXxZG',
    'bibliotecario'
);


-- ------------------------------------------------------------
-- 2. TABELA: livros
--    Acervo da biblioteca. Nunca excluídos: campo "ativo".
--    quantidade_disponivel nunca pode ser negativa (CHECK).
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS livros (
    id                    INT           NOT NULL AUTO_INCREMENT,
    titulo                VARCHAR(200)  NOT NULL,
    autor                 VARCHAR(150)  NOT NULL,
    isbn                  VARCHAR(20)   DEFAULT NULL, -- pode ser nulo (livros antigos)
    editora               VARCHAR(100)  DEFAULT NULL,
    ano_publicacao        YEAR          DEFAULT NULL,
    quantidade_total      INT           NOT NULL DEFAULT 1 CHECK (quantidade_total >= 0),
    quantidade_disponivel INT           NOT NULL DEFAULT 1 CHECK (quantidade_disponivel >= 0),
    ativo                 TINYINT(1)    NOT NULL DEFAULT 1, -- [4]
    criado_em             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_livros PRIMARY KEY (id),

    -- Garante que exemplares disponíveis nunca excedam o total
    CONSTRAINT chk_livros_qtd
        CHECK (quantidade_disponivel <= quantidade_total)
);


-- ------------------------------------------------------------
-- 3. TABELA: alunos
--    Alunos cadastrados para controle de empréstimos.
--    Não possuem acesso ao sistema (sem login/senha).
--    Matrícula é UNIQUE para evitar duplicatas. [3]
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS alunos (
    id         INT           NOT NULL AUTO_INCREMENT,
    nome       VARCHAR(100)  NOT NULL,
    matricula  VARCHAR(20)   NOT NULL,    -- identificador único do aluno [3]
    turma      VARCHAR(20)   DEFAULT NULL,
    email      VARCHAR(100)  DEFAULT NULL,
    telefone   VARCHAR(20)   DEFAULT NULL,
    ativo      TINYINT(1)    NOT NULL DEFAULT 1, -- [4]
    criado_em  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_alunos          PRIMARY KEY (id),
    CONSTRAINT uq_alunos_matricula UNIQUE (matricula) -- [3]
);


-- ------------------------------------------------------------
-- 4. TABELA: emprestimos
--    Núcleo do sistema. Relaciona alunos ↔ livros ↔ usuarios.
--    Registros nunca excluídos — status controla o ciclo de vida.
--    data_devolucao é NULL enquanto o livro não foi devolvido.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS emprestimos (
    id                      INT      NOT NULL AUTO_INCREMENT,

    -- Quem pegou o livro
    aluno_id                INT      NOT NULL,

    -- Qual livro foi emprestado
    livro_id                INT      NOT NULL,

    -- Qual usuário registrou o empréstimo
    usuario_id              INT      NOT NULL,

    -- Datas
    data_emprestimo         DATE     NOT NULL,
    data_prevista_devolucao DATE     NOT NULL,  -- prazo combinado
    data_devolucao          DATE     DEFAULT NULL, -- NULL = ainda não devolvido

    -- Status calculado/atualizado pelo backend
    status ENUM(
        'ativo',      -- emprestado e dentro do prazo
        'devolvido',  -- devolvido (data_devolucao preenchida)
        'em_atraso'   -- prazo vencido sem devolução
    ) NOT NULL DEFAULT 'ativo',

    observacao   TEXT       DEFAULT NULL,
    criado_em    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    -- Chaves primária e estrangeiras
    CONSTRAINT pk_emprestimos    PRIMARY KEY (id),

    CONSTRAINT fk_emp_aluno      -- [2]
        FOREIGN KEY (aluno_id)
        REFERENCES alunos(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_emp_livro      -- [2]
        FOREIGN KEY (livro_id)
        REFERENCES livros(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_emp_usuario    -- [2]
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    -- Data de devolução não pode ser anterior ao empréstimo
    CONSTRAINT chk_emp_datas
        CHECK (
            data_devolucao IS NULL
            OR data_devolucao >= data_emprestimo
        )
);


-- ============================================================
-- 5. ÍNDICES ADICIONAIS
--    Aceleram as consultas mais frequentes do sistema. [7]
-- ============================================================

-- Buscas de livros por título e autor (listagem e autocomplete)
CREATE INDEX idx_livros_titulo
    ON livros (titulo);

CREATE INDEX idx_livros_ativo
    ON livros (ativo);

-- Buscas de alunos por nome e matrícula
CREATE INDEX idx_alunos_nome
    ON alunos (nome);

CREATE INDEX idx_alunos_ativo
    ON alunos (ativo);

-- Consultas de empréstimos por status (listagem principal e relatórios)
CREATE INDEX idx_emp_status
    ON emprestimos (status);

-- Consultas por aluno (histórico do aluno)
CREATE INDEX idx_emp_aluno
    ON emprestimos (aluno_id);

-- Consultas por livro (saber quem está com cada livro)
CREATE INDEX idx_emp_livro
    ON emprestimos (livro_id);

-- Consultas por data prevista (relatório de atrasos)
CREATE INDEX idx_emp_data_prevista
    ON emprestimos (data_prevista_devolucao);


-- ============================================================
-- 6. VIEW: vw_emprestimos_ativos
--    Facilita a listagem principal de empréstimos com todos
--    os dados necessários em uma única consulta.
-- ============================================================

CREATE OR REPLACE VIEW vw_emprestimos_ativos AS
SELECT
    e.id                        AS emprestimo_id,
    e.status,
    e.data_emprestimo,
    e.data_prevista_devolucao,
    e.data_devolucao,
    e.observacao,

    a.id                        AS aluno_id,
    a.nome                      AS aluno_nome,
    a.matricula                 AS aluno_matricula,
    a.turma                     AS aluno_turma,

    l.id                        AS livro_id,
    l.titulo                    AS livro_titulo,
    l.autor                     AS livro_autor,
    l.isbn                      AS livro_isbn,

    u.nome                      AS registrado_por,

    -- Calcula atraso em dias (positivo = atrasado)
    CASE
        WHEN e.data_devolucao IS NOT NULL THEN 0
        ELSE GREATEST(0, DATEDIFF(CURDATE(), e.data_prevista_devolucao))
    END                         AS dias_atraso

FROM emprestimos e
    INNER JOIN alunos    a ON a.id = e.aluno_id
    INNER JOIN livros    l ON l.id = e.livro_id
    INNER JOIN usuarios  u ON u.id = e.usuario_id;


-- ============================================================
-- 7. VIEW: vw_dashboard
--    Todos os indicadores do painel em uma única consulta.
-- ============================================================

CREATE OR REPLACE VIEW vw_dashboard AS
SELECT
    -- Acervo
    (SELECT COUNT(*) FROM livros   WHERE ativo = 1)          AS total_livros,
    (SELECT SUM(quantidade_disponivel) FROM livros WHERE ativo = 1)
                                                              AS livros_disponiveis,

    -- Alunos
    (SELECT COUNT(*) FROM alunos   WHERE ativo = 1)          AS total_alunos,

    -- Empréstimos
    (SELECT COUNT(*) FROM emprestimos WHERE status = 'ativo')     AS emprestimos_ativos,
    (SELECT COUNT(*) FROM emprestimos WHERE status = 'em_atraso') AS emprestimos_atraso,
    (SELECT COUNT(*) FROM emprestimos WHERE status = 'devolvido') AS emprestimos_devolvidos,
    (SELECT COUNT(*) FROM emprestimos)                            AS emprestimos_total;


-- ============================================================
-- 8. PROCEDURE: sp_registrar_devolucao
--    Registra a devolução de um empréstimo em transação única:
--      1. Atualiza status e data_devolucao em emprestimos.
--      2. Incrementa quantidade_disponivel em livros.
--    Ambas as operações ocorrem atomicamente. [TRANSAÇÃO]
-- ============================================================

DELIMITER $$

CREATE PROCEDURE sp_registrar_devolucao (
    IN p_emprestimo_id INT,
    IN p_data_devolucao DATE
)
BEGIN
    DECLARE v_livro_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

        -- Recupera o livro_id do empréstimo
        SELECT livro_id
          INTO v_livro_id
          FROM emprestimos
         WHERE id = p_emprestimo_id
           AND status IN ('ativo', 'em_atraso')
         LIMIT 1;

        IF v_livro_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Empréstimo não encontrado ou já devolvido.';
        END IF;

        -- Marca como devolvido
        UPDATE emprestimos
           SET status          = 'devolvido',
               data_devolucao  = p_data_devolucao
         WHERE id = p_emprestimo_id;

        -- Devolve o exemplar ao acervo
        UPDATE livros
           SET quantidade_disponivel = quantidade_disponivel + 1
         WHERE id = v_livro_id;

    COMMIT;
END$$

DELIMITER ;


-- ============================================================
-- 9. PROCEDURE: sp_registrar_emprestimo
--    Registra um empréstimo em transação única:
--      1. Verifica se há exemplar disponível.
--      2. Insere o registro em emprestimos.
--      3. Decrementa quantidade_disponivel em livros.
--    Ambas as operações ocorrem atomicamente. [TRANSAÇÃO]
-- ============================================================

DELIMITER $$

CREATE PROCEDURE sp_registrar_emprestimo (
    IN p_aluno_id               INT,
    IN p_livro_id               INT,
    IN p_usuario_id             INT,
    IN p_data_emprestimo        DATE,
    IN p_data_prevista_devolucao DATE
)
BEGIN
    DECLARE v_disponivel INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

        -- Verifica disponibilidade (com lock de linha)
        SELECT quantidade_disponivel
          INTO v_disponivel
          FROM livros
         WHERE id = p_livro_id
           AND ativo = 1
         FOR UPDATE;

        IF v_disponivel IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Livro não encontrado ou inativo.';
        END IF;

        IF v_disponivel < 1 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Nenhum exemplar disponível para empréstimo.';
        END IF;

        -- Registra o empréstimo
        INSERT INTO emprestimos (
            aluno_id,
            livro_id,
            usuario_id,
            data_emprestimo,
            data_prevista_devolucao,
            status
        ) VALUES (
            p_aluno_id,
            p_livro_id,
            p_usuario_id,
            p_data_emprestimo,
            p_data_prevista_devolucao,
            'ativo'
        );

        -- Deduz o exemplar do acervo
        UPDATE livros
           SET quantidade_disponivel = quantidade_disponivel - 1
         WHERE id = p_livro_id;

    COMMIT;
END$$

DELIMITER ;


-- ============================================================
-- 10. EVENT: ev_atualizar_atrasos
--    Evento agendado (roda diariamente à meia-noite) para
--    marcar automaticamente como 'em_atraso' os empréstimos
--    cujo prazo de devolução já foi ultrapassado.
--    Requer: SET GLOBAL event_scheduler = ON;  no MySQL.
-- ============================================================

SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS ev_atualizar_atrasos
    ON SCHEDULE EVERY 1 DAY
    STARTS (CURRENT_DATE + INTERVAL 1 DAY)
    DO
        UPDATE emprestimos
           SET status = 'em_atraso'
         WHERE status = 'ativo'
           AND data_prevista_devolucao < CURDATE();


-- ============================================================
-- FIM DO SCRIPT
-- ============================================================
--
--  CREDENCIAIS PADRÃO (altere após o primeiro acesso):
--  ┌──────────────────────────────────┬─────────────────┬─────────────┐
--  │ E-mail                           │ Senha           │ Perfil      │
--  ├──────────────────────────────────┼─────────────────┼─────────────┤
--  │ admin@bibliotech.com             │ admin123        │ admin       │
--  │ bibliotecario@bibliotech.com     │ biblio123       │ bibliotecario│
--  └──────────────────────────────────┴─────────────────┴─────────────┘
--
--  COMO EXECUTAR:
--  1. Abra o phpMyAdmin (http://localhost/phpmyadmin)
--  2. Clique em "Importar" → selecione este arquivo .sql
--  3. Clique em "Executar"
--
--  OU via linha de comando:
--  mysql -u root -p < bibliotech.sql
--
-- ============================================================