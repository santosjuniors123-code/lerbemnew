# LER BEM — Sistema de Gestão de Leitura Escolar

Sistema completo (não é protótipo) para gestão de leitura em **múltiplas escolas**, com área do aluno
sem login, painel do professor, questionários com correção automática, geração de perguntas por IA
(modular e opcional), relatórios com exportação em CSV e PDF e área administrativa.

- **Stack:** PHP 8.2+ (testado em 8.4.27), MySQL/MariaDB, PDO, HTML5, CSS3, JavaScript puro, Chart.js.
- **Sem Node.js no servidor de produção.** Roda em hospedagem PHP tradicional.
- **Multiescolas:** todo registro pertence a uma escola e nenhuma consulta cruza escolas.
- **Instalador** web (`install.php`) e por linha de comando (`database/install_cli.php`).

---

## 1. Requisitos

| Item | Versão |
|------|--------|
| PHP | 8.2 ou superior (8.4.27 usado em produção) |
| Extensões PHP | `pdo_mysql`, `mbstring`, `json`, `session`, `openssl` |
| Banco | MySQL 5.7+ / MariaDB 10.3+ (testado em MariaDB 11.8) |
| Servidor web | Apache com `mod_php`/PHP-FPM ou nginx + PHP-FPM |

---

## 2. Estrutura do projeto

```
/
├── index.php                 # Identificação do aluno (nome + turma) e início da leitura
├── login.php / logout.php    # Autenticação de professores e administradores
├── install.php               # Instalador web (bloqueia após a instalação)
├── functions.php             # Bootstrap: sessão, PDO, CSRF, escape, helpers de escola
├── assets/
│   ├── css/style.css         # Identidade visual LER BEM (responsiva, menu lateral)
│   ├── js/app.js             # Gráficos, confirmações, máscaras, estados de carregamento
│   └── img/logo.svg
├── config/config.php         # Configuração central (env > config.local.php)
├── includes/                 # layout, auth, installer, ai, pdf, validação
├── admin/                    # Escolas, professores e visão geral (super-admin)
├── professor/                # Dashboard, alunos, turmas, leituras, questionários, IA, relatórios
├── aluno/                    # Registro de leitura, respostas e confirmação
├── api/                      # Endpoints JSON (turmas, respostas, gráficos)
├── database/
│   ├── schema.sql            # Esquema completo (tabelas, FKs, índices)
│   └── install_cli.php       # Instalação por CLI (usada no Docker/SSH)
├── docker/                   # nginx, php.ini de segurança, my.cnf, entrypoint
├── uploads/                  # Arquivos enviados (gravável)
└── Dockerfile
```

---

## 3. Instalação em hospedagem PHP tradicional

1. Envie todos os arquivos para o diretório público (ex.: `public_html/`).
2. Crie o banco de dados e um usuário MySQL com permissões no banco.
3. Garanta permissão de escrita em `uploads/` e `config/` (ex.: `chmod 775`).
4. Acesse `https://seu-dominio/install.php` e informe: dados do banco, nome da escola e o
   primeiro administrador (e-mail + senha).
5. Ao final, o instalador grava `config/config.local.php` e cria as turmas padrão.
   **Remova ou renomeie `install.php`** depois de instalar (ele também se bloqueia sozinho quando
   já existe usuário cadastrado).
6. Acesse `login.php` e entre com o administrador criado.

### Instalação por linha de comando

```bash
DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=ler_bem DB_USER=lerbem DB_PASSWORD='senha-forte' \
ADMIN_NOME='Direção' ADMIN_EMAIL='direcao@escola.com' ADMIN_PASSWORD='SenhaForte!123' \
php database/install_cli.php
```

### Variáveis de ambiente (opcionais, têm prioridade sobre `config.local.php`)

| Variável | Função |
|----------|--------|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Conexão com o banco |
| `APP_URL` | URL base (links absolutos/e-mails) |
| `APP_DEBUG` | `1` mostra detalhes de erro (use apenas em desenvolvimento) |
| `AI_API_KEY` | Chave da API de IA — **sem ela o sistema funciona normalmente** |
| `AI_API_URL` | Endpoint compatível com `/chat/completions` |
| `AI_MODEL` | Modelo usado na geração de perguntas |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Credenciais iniciais (Docker/CLI) |

---

## 4. Instalação com Docker (nginx + PHP-FPM + MariaDB)

A imagem sobe nginx na porta **80**, PHP-FPM e MariaDB no mesmo container; o entrypoint cria o banco,
aplica o esquema e cadastra a escola/administrador na primeira execução.

```bash
docker build -t ler-bem .
docker run -p 80:80 \
  -e ADMIN_EMAIL='admin@escola.com' \
  -e ADMIN_PASSWORD='TroqueEstaSenha!123' \
  -e ESCOLA_NOME='Escola Municipal Exemplo' \
  ler-bem
```

Sem `ADMIN_PASSWORD`, uma senha aleatória é gerada e impressa no log do container.
Para IA, acrescente `-e AI_API_KEY=...`.

---

## 5. Credenciais iniciais

Não há credenciais padrão fixas no código. O primeiro administrador é definido por você no
instalador (web, CLI ou variáveis `ADMIN_EMAIL`/`ADMIN_PASSWORD`). Senhas são gravadas com
`password_hash()` (bcrypt) — nunca em texto puro. Troque a senha inicial após o primeiro acesso em
**Configurações**.

---

## 6. Segurança implementada

- Senhas com `password_hash()` / `password_verify()`.
- Sessões PHP endurecidas (`httponly`, `samesite=Lax`, `use_strict_mode`, regeneração de ID no login,
  expiração por inatividade) e logout com destruição de sessão/cookie.
- SQL Injection: 100% PDO com *prepared statements* (sem concatenação de entrada).
- XSS: escape de saída com `e()` (htmlspecialchars) em todas as views.
- CSRF: token por sessão validado em todo POST (`lb_csrf_field()` / `lb_csrf_check()`).
- Controle de acesso: `lb_require_professor()`, `lb_require_admin()` e verificação de papel por página.
- Isolamento por escola: `escola_id` em todas as tabelas de domínio e filtro obrigatório em toda
  consulta (`lb_escola_id()`), inclusive nos relatórios e nas ações de exclusão.
- Validação de formulários no servidor (tipos, limites, e-mail, datas, uploads).
- `install.php` bloqueado após a instalação; `config/`, `database/`, `includes/` e `docker/`
  inacessíveis via web (`.htaccess` no Apache, `deny` no nginx).
- Headers de segurança (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) e
  `session.upload_progress.enabled=0` no `conf.d` da imagem.

---

## 7. Funcionalidades

**Aluno (sem login):** identifica-se por nome + turma (validado contra os alunos cadastrados da
escola), registra leitura (título, autor, data, páginas lidas, resumo), responde questionários e
recebe confirmação do registro. Não acessa nenhuma área administrativa.

**Professor:** dashboard com totais de alunos/turmas/leituras, alunos que mais leem, alunos que
precisam de acompanhamento, leituras recentes, histórico de atividades e gráficos (Chart.js).
Menu lateral com Dashboard, Alunos, Turmas, Leituras, Questionários, Relatórios, Configurações e Sair.

**Alunos:** cadastrar, editar, excluir, pesquisar, filtrar por turma, histórico individual,
quantidade de leituras e desempenho. Campos: nome, turma, data de cadastro, status ativo/inativo.

**Turmas:** criar, editar, excluir, associar alunos, quantidade de alunos e desempenho coletivo.

**Questionários:** múltipla escolha, verdadeiro/falso e resposta aberta; enunciado, alternativas,
resposta correta e explicação. Objetivas corrigidas automaticamente; abertas ficam para correção
manual do professor.

**IA (modular):** informar título, conteúdo/texto, quantidade de perguntas e dificuldade; a geração
usa apenas o conteúdo fornecido, e o professor revisa, edita e aprova antes de publicar. Sem
`AI_API_KEY` o módulo fica desativado e o restante do sistema continua funcionando.

**Relatórios:** leituras por aluno/turma/período, alunos mais participativos, páginas lidas,
questionários respondidos e aproveitamento, com filtros por data, turma e aluno; exportação em
**CSV** e **PDF** (gerador interno em `includes/pdf.php`).

**Administração:** cadastro de escolas e de professores por escola, com vínculo obrigatório à escola.

---

## 8. Verificações executadas

- Sintaxe PHP validada em todos os arquivos: `bash scripts/check-php.sh` (o script percorre o projeto e
  sai com código 1 se algum arquivo falhar em `php -l`).
- `check_deployment` sem erros (porta 80).
- Imagem Docker aprovada no scan de vulnerabilidades (38 pacotes, 0 achados bloqueantes) e
  `PHP_VERSION` 8.4.27 verificado dentro da imagem final.
- Deploy verificado em produção: 3 sondagens HTTP consecutivas com status 200 e rotas `/` e
  `/login.php` renderizando corretamente em 1280 px e 390 px (mobile), sem erros de página.
- Instalação automática do banco comprovada em produção: esquema aplicado, escola e 4 turmas criadas,
  administrador cadastrado.
- **Fluxos reais executados no app publicado (revisão 81), sem erros de página ou console:** login do
  administrador, cadastro de professor, login do professor, dashboard, criação de turma, criação de
  aluno vinculado à turma, identificação do aluno sem login e registro de leitura com a data do dia
  (confirmação + página de detalhe).

### Correções encontradas nessa verificação

1. Dashboard do professor retornava HTTP 500 por causa de `ORDER BY`/`HAVING` sobre alias de agregação
   (não suportado pelo MariaDB) — reescrito com *derived table*.
2. Gráficos criavam duas instâncias de Chart.js no mesmo canvas — agora há guarda por canvas e
   destruição da instância anterior.
3. Leitura com a data de hoje era recusada como "futura": `createFromFormat('Y-m-d', ...)` preenche a
   hora com o relógio atual — corrigido com o modificador `'!'` (`'!Y-m-d'`).

Ainda devem ser validados manualmente na sua instalação: edição/exclusão de alunos e turmas, resposta

e correção de questionário e exportações CSV/PDF.

---

## 9. Produção

Instância publicada desta versão: **https://fdcqtih45b.dev.onappjet.com**

Para hospedagem definitiva, envie os arquivos via FTP/Git, rode o instalador e configure
`APP_URL` com o domínio final.
