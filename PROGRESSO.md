# PROGRESSO — LER BEM

Status do desenvolvimento e da entrega. Atualizado na publicação da versão implantada.

## Concluído

| Módulo | Arquivos | Situação |
|--------|----------|----------|
| Esquema do banco (escolas, usuarios, professores, alunos, turmas, leituras, questionarios, perguntas, alternativas, respostas, configuracoes) | `database/schema.sql` | Concluído — FKs, índices e restrições |
| Núcleo (PDO, sessão, CSRF, escape, isolamento por escola) | `functions.php`, `config/config.php` | Concluído |
| Instalador web e CLI | `install.php`, `includes/installer.php`, `database/install_cli.php` | Concluído e executado em produção |
| Layout, identidade visual, menu lateral, responsividade | `includes/layout.php`, `assets/css/style.css`, `assets/js/app.js`, `assets/img/logo.svg` | Concluído |
| Área do aluno sem login | `index.php`, `aluno/*`, `api/*` | Concluído |
| Autenticação de professor/admin | `login.php`, `logout.php`, `includes/auth.php` | Concluído |
| Dashboard do professor | `professor/index.php` | Concluído |
| Alunos (CRUD, busca, filtro, histórico, desempenho) | `professor/alunos.php`, `aluno_form.php`, `aluno_acao.php`, `aluno_historico.php` | Concluído |
| Turmas (CRUD, vínculo de alunos, desempenho coletivo) | `professor/turmas.php`, `turma_form.php`, `turma_acao.php` | Concluído |
| Leituras (lista, detalhe, correção) | `professor/leituras.php`, `professor/leitura.php` | Concluído |
| Questionários e perguntas (3 tipos, correção automática/manual) | `professor/questionarios.php`, `questionario_form.php`, `pergunta_form.php`, `questionario_acao.php`, `pergunta_acao.php` | Concluído |
| Módulo de IA modular por variável de ambiente | `professor/ia.php`, `includes/ai.php` | Concluído (desativado sem `AI_API_KEY`) |
| Relatórios com filtros + exportação CSV/PDF | `professor/relatorios.php`, `relatorios_export.php`, `includes/pdf.php` | Concluído |
| Administração (escolas e professores) | `admin/index.php`, `escolas.php`, `escola_form.php`, `professores.php`, `professor_form.php` | Concluído |
| Configurações da escola/conta | `professor/configuracoes.php` | Concluído |
| Container de produção | `Dockerfile`, `docker/nginx-ler-bem.conf`, `docker/php-security.ini`, `docker/mariadb-ler-bem.cnf`, `docker/entrypoint.sh` | Concluído — imagem aprovada no scan |
| Deploy | Build `build-1794282162900-3abc2ba8` (revisão 81) | Concluído — https://fdcqtih45b.dev.onappjet.com |

## Verificado

- `php -l` em todos os arquivos PHP.
- `check_deployment` sem erros (porta 80).
- Scan de vulnerabilidades da imagem: 38 pacotes, 0 achados bloqueantes.
- `PHP_VERSION` 8.4.27 confirmado dentro da imagem final.
- Produção: 3 sondagens HTTP 200 consecutivas; `/` e `/login.php` em 1280 px e 390 px sem erros.
- Instalação automática do banco em produção (esquema, escola, 4 turmas e administrador).
- **Fluxos reais executados no app publicado (revisão 81, sem erros de página ou console):**
  - login do administrador e cadastro de professor;
  - login do professor e abertura do dashboard (totais, "Precisam de acompanhamento", gráficos);
  - criação de turma e de aluno vinculado à turma, com mensagem de sucesso e listagem;
  - identificação do aluno sem login (nome + escola + turma) e registro de leitura com a **data do
dia**, com confirmação e página de detalhe (título, autor, data, páginas, resumo).

## Pendências e recomendações

- **Validar manualmente** os fluxos restantes na sua instalação: edição/exclusão de alunos e turmas,
  resposta e correção de questionário (objetivas e abertas) e exportações CSV/PDF. Já verificados no
  app publicado: login, cadastro de professor, criação de turma/aluno, dashboard e registro de leitura.
- **Trocar a senha inicial** do administrador após o primeiro acesso.
- **Configurar a IA** apenas se desejar: `AI_API_KEY`, `AI_API_URL`, `AI_MODEL`. Sem chave, o módulo
  fica indisponível e o sistema continua íntegro.
- **Remover/renomear `install.php`** em produção (ele já se bloqueia após a instalação).
- Backups periódicos do banco MySQL/MariaDB são responsabilidade da hospedagem.
- Domínio próprio e disco persistente não fazem parte do plano atual de hospedagem (Starter/Pro);
  para uso definitivo, instale na hospedagem PHP da escola seguindo o README.

## Histórico de correções de deploy

1. Base Debian 13 (`php:8.4.27-apache`) reprovada no scan de imagem (curl sem correção publicada pelo
   Debian) → runtime migrado para Alpine (`php:8.4.27-fpm-alpine` + nginx + MariaDB).
2. Container saudável, mas a hospedagem sonda a porta **80** e o nginx escutava apenas 3000 →
   nginx passou a escutar 80 e 3000.
3. MariaDB subia com `port: 0` (pacote Alpine desativa TCP), quebrando a instalação automática →
   `--skip-networking=0 --port=3306` no entrypoint, `skip-networking = 0` no `my.cnf` e espera pela
   conexão TCP antes de rodar o instalador.
4. Dashboard do professor retornava **HTTP 500** (`Reference 'ultima' not supported`) porque o MariaDB
   não aceita `ORDER BY`/`HAVING` com expressão sobre alias de agregação → consulta reescrita com
   *derived table* em `professor/index.php`.
5. Erro de JavaScript "Canvas is already in use" nos gráficos: `initCharts()` executava duas vezes na
   mesma página → guarda por canvas (`data-chart-done`) e `Chart.getChart(cv).destroy()` antes de
   recriar, em `assets/js/app.js`.
6. Leitura com a data de **hoje** era recusada como "data no futuro":
   `createFromFormat('Y-m-d', ...)` preenche hora/minuto com o relógio atual, ficando maior que a
   meia-noite de hoje → formato `'!Y-m-d'` em `aluno/leitura_nova.php` e `professor/aluno_form.php`.
