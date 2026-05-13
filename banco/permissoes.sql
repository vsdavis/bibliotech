USE bibliotech;
 
 
-- ------------------------------------------------------------
-- 1. TABELA: permissoes
--    Catálogo de todas as permissões do sistema.
--    "codigo" é a chave usada pelo PHP em hasPermission().
-- ------------------------------------------------------------
 
CREATE TABLE IF NOT EXISTS permissoes (
    id         INT          NOT NULL AUTO_INCREMENT,
    codigo     VARCHAR(50)  NOT NULL,                -- ex.: 'livros.editar'
    nome       VARCHAR(100) NOT NULL,                -- ex.: 'Editar livros'
    descricao  VARCHAR(255) DEFAULT NULL,
    grupo      VARCHAR(50)  NOT NULL,                -- ex.: 'Livros'
    ativo      TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    CONSTRAINT pk_permissoes        PRIMARY KEY (id),
    CONSTRAINT uq_permissoes_codigo UNIQUE (codigo)
);
 
 
-- ------------------------------------------------------------
-- 2. TABELA: usuario_permissoes
--    Permissões individuais concedidas a cada usuário.
--    Quando "permitido = 1", o usuário possui a permissão.
--    UNIQUE(usuario_id, permissao_id) impede duplicatas.
-- ------------------------------------------------------------
 
CREATE TABLE IF NOT EXISTS usuario_permissoes (
    id            INT       NOT NULL AUTO_INCREMENT,
    usuario_id    INT       NOT NULL,
    permissao_id  INT       NOT NULL,
    permitido     TINYINT(1) NOT NULL DEFAULT 1,
    criado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    CONSTRAINT pk_usuario_permissoes PRIMARY KEY (id),
 
    CONSTRAINT uq_usuario_permissao
        UNIQUE (usuario_id, permissao_id),
 
    CONSTRAINT fk_up_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
 
    CONSTRAINT fk_up_permissao
        FOREIGN KEY (permissao_id)
        REFERENCES permissoes(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
 
CREATE INDEX idx_up_usuario   ON usuario_permissoes (usuario_id);
CREATE INDEX idx_up_permissao ON usuario_permissoes (permissao_id);
 
 
-- ------------------------------------------------------------
-- 3. POPULAÇÃO INICIAL DE PERMISSÕES
-- ------------------------------------------------------------
 
INSERT INTO permissoes (codigo, nome, descricao, grupo) VALUES
    -- Dashboard
    ('dashboard.visualizar',  'Acessar o Dashboard',
     'Permite visualizar o painel inicial com indicadores.', 'Dashboard'),
 
    -- Livros
    ('livros.visualizar',     'Visualizar livros',
     'Permite acessar a listagem do acervo de livros.', 'Livros'),
    ('livros.cadastrar',      'Cadastrar livros',
     'Permite adicionar novos livros ao acervo.', 'Livros'),
    ('livros.editar',         'Editar livros',
     'Permite alterar dados de livros existentes.', 'Livros'),
    ('livros.inativar',       'Inativar livros',
     'Permite remover livros do acervo (inativação lógica).', 'Livros'),
 
    -- Alunos
    ('alunos.visualizar',     'Visualizar alunos',
     'Permite acessar a listagem de alunos.', 'Alunos'),
    ('alunos.cadastrar',      'Cadastrar alunos',
     'Permite cadastrar novos alunos.', 'Alunos'),
    ('alunos.editar',         'Editar alunos',
     'Permite alterar dados de alunos existentes.', 'Alunos'),
    ('alunos.inativar',       'Inativar alunos',
     'Permite inativar alunos do sistema.', 'Alunos'),
 
    -- Empréstimos
    ('emprestimos.visualizar','Visualizar empréstimos',
     'Permite acessar a listagem de empréstimos.', 'Empréstimos'),
    ('emprestimos.cadastrar', 'Registrar empréstimos',
     'Permite registrar novos empréstimos.', 'Empréstimos'),
    ('emprestimos.devolver',  'Registrar devoluções',
     'Permite registrar devoluções de livros.', 'Empréstimos'),
 
    -- Relatórios
    ('relatorios.visualizar', 'Acessar relatórios',
     'Permite acessar todos os relatórios do sistema.', 'Relatórios'),
 
    -- Usuários
    ('usuarios.visualizar',   'Visualizar usuários',
     'Permite listar os usuários do sistema.', 'Usuários'),
    ('usuarios.cadastrar',    'Cadastrar usuários',
     'Permite criar novos usuários.', 'Usuários'),
    ('usuarios.editar',       'Editar usuários',
     'Permite alterar dados de usuários existentes.', 'Usuários'),
    ('usuarios.inativar',     'Inativar usuários',
     'Permite inativar usuários do sistema.', 'Usuários'),
    ('usuarios.permissoes',   'Gerenciar permissões',
     'Permite editar permissões individuais de cada usuário.', 'Usuários');
 
 
-- ------------------------------------------------------------
-- 4. CONCEDE TODAS AS PERMISSÕES AO ADMIN PADRÃO
--    (id 1 = admin@bibliotech.com criado no script principal)
-- ------------------------------------------------------------
 
INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido)
SELECT u.id, p.id, 1
  FROM usuarios u
 CROSS JOIN permissoes p
 WHERE u.email = 'admin@bibliotech.com'
   AND p.ativo = 1
ON DUPLICATE KEY UPDATE permitido = 1;
 
 
-- ------------------------------------------------------------
-- 5. CONCEDE PERMISSÕES BÁSICAS AO BIBLIOTECÁRIO PADRÃO
--    (apenas operações comuns de biblioteca, sem gestão de usuários)
-- ------------------------------------------------------------
 
INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido)
SELECT u.id, p.id, 1
  FROM usuarios u
 CROSS JOIN permissoes p
 WHERE u.email = 'bibliotecario@bibliotech.com'
   AND p.codigo IN (
        'dashboard.visualizar',
        'livros.visualizar',     'livros.cadastrar', 'livros.editar',
        'alunos.visualizar',     'alunos.cadastrar', 'alunos.editar',
        'emprestimos.visualizar','emprestimos.cadastrar','emprestimos.devolver',
        'relatorios.visualizar'
   )
ON DUPLICATE KEY UPDATE permitido = 1;
 
 
-- ============================================================
-- FIM DA ATUALIZAÇÃO
-- ============================================================
 