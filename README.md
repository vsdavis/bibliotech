<div align="center">

# 📚 BiblioTech

**Sistema de Gerenciamento de Biblioteca Escolar**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=flat-square&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/Servidor-XAMPP-FB7A24?style=flat-square&logo=apache&logoColor=white)
![Status](https://img.shields.io/badge/Status-Funcional-2F855A?style=flat-square)

*Projeto de Extensão em Programação — UNINOVE · ADS · 2026*

</div>

---

## Sobre o projeto

O **BiblioTech** digitaliza e centraliza a gestão de bibliotecas escolares de pequeno e médio porte. Substitui fichas de papel e planilhas manuais por uma aplicação web que controla o acervo, registra empréstimos e devoluções, gerencia usuários com permissões granulares e emite relatórios gerenciais — tudo em tempo real e com segurança.

---

## Funcionalidades

- ✅ **Autenticação segura** — login com sessão PHP, cookies HttpOnly + SameSite, regeneração de ID
- ✅ **Dashboard** — indicadores em cards, gráficos de empréstimos e títulos mais retirados
- ✅ **Gestão de livros** — cadastro, edição, inativação, controle de estoque por exemplar
- ✅ **Gestão de alunos** — cadastro completo, matrícula única, bloqueio de inativação com pendências
- ✅ **Gestão de usuários** — perfis Administrador e Bibliotecário, permissões individuais por módulo
- ✅ **Empréstimos e devoluções** — registro com trava de linha (SELECT FOR UPDATE), atualização automática de atraso
- ✅ **Relatórios** — empréstimos (filtro por status e período) e inventário do acervo, com impressão
- ✅ **Proteção CSRF** em todos os formulários de escrita
- ✅ **Exclusão lógica** — registros inativados preservam o histórico

---

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.2 |
| Banco de dados | MySQL / MariaDB (via XAMPP) |
| Frontend | HTML5 · CSS3 · JavaScript |
| Gráficos | Chart.js (hospedado localmente) |
| Acesso ao banco | PDO com prepared statements reais |
| Servidor local | XAMPP (Apache + MariaDB) |

---

## Pré-requisitos

- [XAMPP](https://www.apachefriends.org/pt_br/index.html) com Apache e MySQL/MariaDB ativos
- PHP 8.1 ou superior (incluso no XAMPP)
- Navegador moderno (Chrome, Firefox, Edge)

---

## Instalação

### 1 · Clonar ou extrair o projeto

Coloque a pasta do projeto dentro do diretório `htdocs` do XAMPP:

```
C:\xampp\htdocs\bibliotech\
```

> O nome da pasta **deve** ser `bibliotech` — ele é usado como base URL em todo o sistema.

### 2 · Criar o banco de dados

1. Abra o **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Crie um banco chamado `bibliotech`
3. Com o banco selecionado, clique em **Importar** e selecione o arquivo:

```
banco do projeto/bibliotech.sql
```

O script cria todas as tabelas, views, procedures, índices e os dados iniciais.

### 3 · Verificar a conexão

Abra `includes/conexao.php` e confirme as configurações:

```php
const DB_HOST    = 'localhost';
const DB_NAME    = 'bibliotech';
const DB_USER    = 'root';
const DB_PASS    = '';        // padrão XAMPP: sem senha
const DB_CHARSET = 'utf8mb4';
```

Altere `DB_USER` e `DB_PASS` se o seu XAMPP usar credenciais diferentes.

### 4 · Acessar o sistema

Abra o navegador em:

```
http://localhost/bibliotech
```

---

## Credenciais padrão

| Perfil | E-mail | Senha |
|--------|--------|-------|
| Administrador | `admin@bibliotech.com` | `admin123` |
| Bibliotecário | `bibliotecario@bibliotech.com` | `biblio123` |

> ⚠️ Troque as senhas logo após o primeiro acesso em **Usuários → Editar**.

---

## Estrutura de pastas

```
bibliotech/
├── includes/
│   ├── conexao.php       # Conexão PDO
│   ├── auth.php          # Verificação de autenticação
│   ├── helpers.php       # Funções auxiliares (e, flash, csrf, hasPermission…)
│   ├── header.php        # Cabeçalho HTML comum
│   ├── navbar.php        # Barra de navegação
│   └── footer.php        # Rodapé
│
├── livros/               # Listagem, cadastro, edição, inativação + back-end
├── alunos/               # Idem para alunos
├── usuarios/             # Idem para usuários + tela de permissões
├── emprestimos/          # Registro de empréstimos, devoluções e listagem
├── relatorios/           # Relatórios de empréstimos e de inventário
│
├── assets/
│   ├── css/style.css     # Folha de estilo única (paleta Verde Sereno)
│   ├── js/script.js      # Scripts gerais (menu, busca, senha)
│   ├── js/chart.umd.js   # Chart.js (local, funciona offline)
│   ├── js/dashboard-charts.js
│   └── img/              # Logo e ícones SVG
│
├── banco do projeto/
│   ├── bibliotech.sql    # Dump completo do banco
│   └── sql comands/      # Scripts auxiliares (usuário e permissões)
│
├── dashboard.php
├── login.php
├── login-back.php
├── logout.php
└── index.php             # Redireciona para login ou dashboard
```

---

## Banco de dados

```
bibliotech
├── Tabelas
│   ├── usuarios            # Operadores do sistema
│   ├── permissoes          # Catálogo de permissões disponíveis
│   ├── usuario_permissoes  # Associação usuário ↔ permissão
│   ├── livros              # Acervo (qtd total e disponível)
│   ├── alunos              # Alunos cadastrados
│   └── emprestimos         # Empréstimos e devoluções
│
├── Views
│   ├── vw_dashboard           # Indicadores do painel inicial
│   └── vw_emprestimos_ativos  # Empréstimos com dados de aluno, livro e dias de atraso
│
└── Procedures
    ├── sp_registrar_emprestimo   # Empréstimo em transação com SELECT FOR UPDATE
    └── sp_registrar_devolucao    # Devolução em transação com rollback automático
```

---

## Segurança

| Proteção | Implementação |
|----------|--------------|
| SQL Injection | PDO + `ATTR_EMULATE_PREPARES = false` |
| Senhas | `password_hash` / `password_verify` (bcrypt) |
| XSS | `htmlspecialchars` via `e()` em toda saída |
| CSRF | Token `bin2hex(random_bytes(32))` + `hash_equals` |
| Fixação de sessão | `session_regenerate_id(true)` no login |
| Controle de acesso | `requirePermission('modulo.acao')` em cada página |
| Cookies | `HttpOnly` + `SameSite: Lax` |
| Erros técnicos | Somente `error_log`; usuário recebe mensagem genérica |

---

## Permissões disponíveis

As permissões seguem o formato `modulo.acao`. O administrador tem acesso total por padrão.

```
dashboard.visualizar
livros.visualizar · livros.cadastrar · livros.editar · livros.inativar
alunos.visualizar · alunos.cadastrar · alunos.editar · alunos.inativar
emprestimos.visualizar · emprestimos.cadastrar · emprestimos.devolver
relatorios.visualizar
usuarios.visualizar · usuarios.cadastrar · usuarios.editar · usuarios.inativar · usuarios.permissoes
```

---



**Orientação:** Prof. Sérgio João Guimarães da Silva  
**Instituição:** UNINOVE — Universidade Nove de Julho  
**Curso:** Análise e Desenvolvimento de Sistemas  
**Disciplina:** Projeto de Extensão em Programação · 2026