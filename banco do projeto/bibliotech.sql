-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 31/05/2026 às 01:40
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `bibliotech`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_devolucao` (IN `p_emprestimo_id` INT, IN `p_data_devolucao` DATE)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_emprestimo` (IN `p_aluno_id` INT, IN `p_livro_id` INT, IN `p_usuario_id` INT, IN `p_data_emprestimo` DATE, IN `p_data_prevista_devolucao` DATE)   BEGIN
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

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `turma` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `alunos`
--

INSERT INTO `alunos` (`id`, `nome`, `matricula`, `turma`, `email`, `telefone`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'João Silva', '24001', '3 A', 'joao16733@gmail.com', '(11) 98203-3340', 1, '2026-05-13 01:01:59', '2026-05-13 01:01:59'),
(2, 'David Vieira Souza', '202601', '3 B', 'david@dev.com.br', '11961220427', 1, '2026-05-30 16:48:24', '2026-05-30 16:48:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `emprestimos`
--

CREATE TABLE `emprestimos` (
  `id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `livro_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_emprestimo` date NOT NULL,
  `data_prevista_devolucao` date NOT NULL,
  `data_devolucao` date DEFAULT NULL,
  `status` enum('ativo','devolvido','em_atraso') NOT NULL DEFAULT 'ativo',
  `observacao` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Despejando dados para a tabela `emprestimos`
--

INSERT INTO `emprestimos` (`id`, `aluno_id`, `livro_id`, `usuario_id`, `data_emprestimo`, `data_prevista_devolucao`, `data_devolucao`, `status`, `observacao`, `criado_em`, `atualizado_em`) VALUES
(1, 1, 1, 1, '2026-05-15', '2026-05-16', '2026-05-17', 'devolvido', 'teste', '2026-05-15 02:30:29', '2026-05-16 23:56:05'),
(2, 2, 2, 1, '2026-05-30', '2026-06-01', NULL, 'ativo', 'Este livro é novo então quando retornar ainda e para estar em perfeito estado!', '2026-05-30 17:13:16', '2026-05-30 17:13:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `livros`
--

CREATE TABLE `livros` (
  `id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `autor` varchar(150) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `editora` varchar(100) DEFAULT NULL,
  `ano_publicacao` year(4) DEFAULT NULL,
  `quantidade_total` int(11) NOT NULL DEFAULT 1 CHECK (`quantidade_total` >= 0),
  `quantidade_disponivel` int(11) NOT NULL DEFAULT 1 CHECK (`quantidade_disponivel` >= 0),
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Despejando dados para a tabela `livros`
--

INSERT INTO `livros` (`id`, `titulo`, `autor`, `isbn`, `editora`, `ano_publicacao`, `quantidade_total`, `quantidade_disponivel`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Dom Casmurro', 'Machado de Assis', '978-85-359-0277-5', 'Companhia das Letras', '0000', 3, 3, 1, '2026-05-09 02:06:10', '2026-05-16 23:56:05'),
(2, 'Harry Potter e a Pedra Filosofal', 'Rowling J.K.', '978-8532511010', 'Editora Rocco', '1997', 10, 9, 1, '2026-05-30 17:10:12', '2026-05-30 17:13:16');

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `grupo` varchar(50) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `permissoes`
--

INSERT INTO `permissoes` (`id`, `codigo`, `nome`, `descricao`, `grupo`, `ativo`, `criado_em`) VALUES
(1, 'dashboard.visualizar', 'Acessar o Dashboard', 'Permite visualizar o painel inicial com indicadores.', 'Dashboard', 1, '2026-05-09 02:36:11'),
(2, 'livros.visualizar', 'Visualizar livros', 'Permite acessar a listagem do acervo de livros.', 'Livros', 1, '2026-05-09 02:36:11'),
(3, 'livros.cadastrar', 'Cadastrar livros', 'Permite adicionar novos livros ao acervo.', 'Livros', 1, '2026-05-09 02:36:11'),
(4, 'livros.editar', 'Editar livros', 'Permite alterar dados de livros existentes.', 'Livros', 1, '2026-05-09 02:36:11'),
(5, 'livros.inativar', 'Inativar livros', 'Permite remover livros do acervo (inativação lógica).', 'Livros', 1, '2026-05-09 02:36:11'),
(6, 'alunos.visualizar', 'Visualizar alunos', 'Permite acessar a listagem de alunos.', 'Alunos', 1, '2026-05-09 02:36:11'),
(7, 'alunos.cadastrar', 'Cadastrar alunos', 'Permite cadastrar novos alunos.', 'Alunos', 1, '2026-05-09 02:36:11'),
(8, 'alunos.editar', 'Editar alunos', 'Permite alterar dados de alunos existentes.', 'Alunos', 1, '2026-05-09 02:36:11'),
(9, 'alunos.inativar', 'Inativar alunos', 'Permite inativar alunos do sistema.', 'Alunos', 1, '2026-05-09 02:36:11'),
(10, 'emprestimos.visualizar', 'Visualizar empréstimos', 'Permite acessar a listagem de empréstimos.', 'Empréstimos', 1, '2026-05-09 02:36:11'),
(11, 'emprestimos.cadastrar', 'Registrar empréstimos', 'Permite registrar novos empréstimos.', 'Empréstimos', 1, '2026-05-09 02:36:11'),
(12, 'emprestimos.devolver', 'Registrar devoluções', 'Permite registrar devoluções de livros.', 'Empréstimos', 1, '2026-05-09 02:36:11'),
(13, 'relatorios.visualizar', 'Acessar relatórios', 'Permite acessar todos os relatórios do sistema.', 'Relatórios', 1, '2026-05-09 02:36:11'),
(14, 'usuarios.visualizar', 'Visualizar usuários', 'Permite listar os usuários do sistema.', 'Usuários', 1, '2026-05-09 02:36:11'),
(15, 'usuarios.cadastrar', 'Cadastrar usuários', 'Permite criar novos usuários.', 'Usuários', 1, '2026-05-09 02:36:11'),
(16, 'usuarios.editar', 'Editar usuários', 'Permite alterar dados de usuários existentes.', 'Usuários', 1, '2026-05-09 02:36:11'),
(17, 'usuarios.inativar', 'Inativar usuários', 'Permite inativar usuários do sistema.', 'Usuários', 1, '2026-05-09 02:36:11'),
(18, 'usuarios.permissoes', 'Gerenciar permissões', 'Permite editar permissões individuais de cada usuário.', 'Usuários', 1, '2026-05-09 02:36:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('admin','bibliotecario') NOT NULL DEFAULT 'bibliotecario',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `perfil`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Administrador', 'admin@bibliotech.com', '$2y$10$SxAFrvBQwTmCukzd83HOUeKnkaWvXN9u7drFxJq5U6SnvXcG4NRUS', 'admin', 1, '2026-05-08 02:34:03', '2026-05-08 02:34:03'),
(2, 'Bibliotecário Padrão', 'bibliotecario@bibliotech.com', '$2y$10$//fcb6Gho1dp0yfH0/SjgeyBvNKZvyxqHiu3dzqeYw6MQa8QFXxZG', 'bibliotecario', 1, '2026-05-08 02:34:03', '2026-05-08 02:34:03'),
(3, 'teste01', 'teste01@bibliotech.com', '$2y$10$4nDYxBDTAz.QmKld9xRCDehjYnUfeigBoyMV2kZj0TzXgcUMArFgu', 'bibliotecario', 1, '2026-05-09 02:48:04', '2026-05-09 02:48:04');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_permissoes`
--

CREATE TABLE `usuario_permissoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `permissao_id` int(11) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuario_permissoes`
--

INSERT INTO `usuario_permissoes` (`id`, `usuario_id`, `permissao_id`, `permitido`, `criado_em`) VALUES
(1, 1, 1, 1, '2026-05-09 02:36:11'),
(2, 1, 2, 1, '2026-05-09 02:36:11'),
(3, 1, 3, 1, '2026-05-09 02:36:11'),
(4, 1, 4, 1, '2026-05-09 02:36:11'),
(5, 1, 5, 1, '2026-05-09 02:36:11'),
(6, 1, 6, 1, '2026-05-09 02:36:11'),
(7, 1, 7, 1, '2026-05-09 02:36:11'),
(8, 1, 8, 1, '2026-05-09 02:36:11'),
(9, 1, 9, 1, '2026-05-09 02:36:11'),
(10, 1, 10, 1, '2026-05-09 02:36:11'),
(11, 1, 11, 1, '2026-05-09 02:36:11'),
(12, 1, 12, 1, '2026-05-09 02:36:11'),
(13, 1, 13, 1, '2026-05-09 02:36:11'),
(14, 1, 14, 1, '2026-05-09 02:36:11'),
(15, 1, 15, 1, '2026-05-09 02:36:11'),
(16, 1, 16, 1, '2026-05-09 02:36:11'),
(17, 1, 17, 1, '2026-05-09 02:36:11'),
(18, 1, 18, 1, '2026-05-09 02:36:11'),
(32, 2, 7, 1, '2026-05-09 02:36:11'),
(33, 2, 8, 1, '2026-05-09 02:36:11'),
(34, 2, 6, 1, '2026-05-09 02:36:11'),
(35, 2, 1, 1, '2026-05-09 02:36:11'),
(36, 2, 11, 1, '2026-05-09 02:36:11'),
(37, 2, 12, 1, '2026-05-09 02:36:11'),
(38, 2, 10, 1, '2026-05-09 02:36:11'),
(39, 2, 3, 1, '2026-05-09 02:36:11'),
(40, 2, 4, 1, '2026-05-09 02:36:11'),
(41, 2, 2, 1, '2026-05-09 02:36:11'),
(42, 2, 13, 1, '2026-05-09 02:36:11'),
(47, 3, 1, 1, '2026-05-09 02:48:04');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_dashboard`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_dashboard` (
`total_livros` bigint(21)
,`livros_disponiveis` decimal(32,0)
,`total_alunos` bigint(21)
,`emprestimos_ativos` bigint(21)
,`emprestimos_atraso` bigint(21)
,`emprestimos_devolvidos` bigint(21)
,`emprestimos_total` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_emprestimos_ativos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_emprestimos_ativos` (
`emprestimo_id` int(11)
,`status` enum('ativo','devolvido','em_atraso')
,`data_emprestimo` date
,`data_prevista_devolucao` date
,`data_devolucao` date
,`observacao` text
,`aluno_id` int(11)
,`aluno_nome` varchar(100)
,`aluno_matricula` varchar(20)
,`aluno_turma` varchar(20)
,`livro_id` int(11)
,`livro_titulo` varchar(200)
,`livro_autor` varchar(150)
,`livro_isbn` varchar(20)
,`registrado_por` varchar(100)
,`dias_atraso` int(7)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_dashboard`
--
DROP TABLE IF EXISTS `vw_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_dashboard`  AS SELECT (select count(0) from `livros` where `livros`.`ativo` = 1) AS `total_livros`, (select sum(`livros`.`quantidade_disponivel`) from `livros` where `livros`.`ativo` = 1) AS `livros_disponiveis`, (select count(0) from `alunos` where `alunos`.`ativo` = 1) AS `total_alunos`, (select count(0) from `emprestimos` where `emprestimos`.`status` = 'ativo') AS `emprestimos_ativos`, (select count(0) from `emprestimos` where `emprestimos`.`status` = 'em_atraso') AS `emprestimos_atraso`, (select count(0) from `emprestimos` where `emprestimos`.`status` = 'devolvido') AS `emprestimos_devolvidos`, (select count(0) from `emprestimos`) AS `emprestimos_total` ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_emprestimos_ativos`
--
DROP TABLE IF EXISTS `vw_emprestimos_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_emprestimos_ativos`  AS SELECT `e`.`id` AS `emprestimo_id`, `e`.`status` AS `status`, `e`.`data_emprestimo` AS `data_emprestimo`, `e`.`data_prevista_devolucao` AS `data_prevista_devolucao`, `e`.`data_devolucao` AS `data_devolucao`, `e`.`observacao` AS `observacao`, `a`.`id` AS `aluno_id`, `a`.`nome` AS `aluno_nome`, `a`.`matricula` AS `aluno_matricula`, `a`.`turma` AS `aluno_turma`, `l`.`id` AS `livro_id`, `l`.`titulo` AS `livro_titulo`, `l`.`autor` AS `livro_autor`, `l`.`isbn` AS `livro_isbn`, `u`.`nome` AS `registrado_por`, CASE WHEN `e`.`data_devolucao` is not null THEN 0 ELSE greatest(0,to_days(curdate()) - to_days(`e`.`data_prevista_devolucao`)) END AS `dias_atraso` FROM (((`emprestimos` `e` join `alunos` `a` on(`a`.`id` = `e`.`aluno_id`)) join `livros` `l` on(`l`.`id` = `e`.`livro_id`)) join `usuarios` `u` on(`u`.`id` = `e`.`usuario_id`)) ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alunos_matricula` (`matricula`),
  ADD KEY `idx_alunos_nome` (`nome`),
  ADD KEY `idx_alunos_ativo` (`ativo`);

--
-- Índices de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_emp_usuario` (`usuario_id`),
  ADD KEY `idx_emp_status` (`status`),
  ADD KEY `idx_emp_aluno` (`aluno_id`),
  ADD KEY `idx_emp_livro` (`livro_id`),
  ADD KEY `idx_emp_data_prevista` (`data_prevista_devolucao`);

--
-- Índices de tabela `livros`
--
ALTER TABLE `livros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_livros_titulo` (`titulo`),
  ADD KEY `idx_livros_ativo` (`ativo`);

--
-- Índices de tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissoes_codigo` (`codigo`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`);

--
-- Índices de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuario_permissao` (`usuario_id`,`permissao_id`),
  ADD KEY `idx_up_usuario` (`usuario_id`),
  ADD KEY `idx_up_permissao` (`permissao_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `emprestimos`
--
ALTER TABLE `emprestimos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `livros`
--
ALTER TABLE `livros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `emprestimos`
--
ALTER TABLE `emprestimos`
  ADD CONSTRAINT `fk_emp_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_emp_livro` FOREIGN KEY (`livro_id`) REFERENCES `livros` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_emp_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD CONSTRAINT `fk_up_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_atualizar_atrasos` ON SCHEDULE EVERY 1 DAY STARTS '2026-05-08 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE emprestimos
           SET status = 'em_atraso'
         WHERE status = 'ativo'
           AND data_prevista_devolucao < CURDATE()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
