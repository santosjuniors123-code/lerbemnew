-- =====================================================================
-- LER BEM - Sistema de Gestao de Leitura Escolar
-- Esquema MySQL 8 / MariaDB 10.4+ (utf8mb4)
-- Importacao idempotente: CREATE TABLE IF NOT EXISTS
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- ESCOLAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS escolas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(150) NOT NULL,
  slug          VARCHAR(160) NOT NULL,
  cidade        VARCHAR(120) DEFAULT NULL,
  uf            CHAR(2) DEFAULT NULL,
  telefone      VARCHAR(30) DEFAULT NULL,
  email         VARCHAR(150) DEFAULT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escolas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- USUARIOS (administradores globais e professores)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id     INT UNSIGNED DEFAULT NULL,
  nome          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  papel         ENUM('admin','professor') NOT NULL DEFAULT 'professor',
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acesso DATETIME DEFAULT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_escola (escola_id),
  CONSTRAINT fk_usuarios_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PROFESSORES (perfil vinculado a um usuario e a uma escola)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS professores (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id     INT UNSIGNED NOT NULL,
  usuario_id    INT UNSIGNED NOT NULL,
  nome          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  disciplina    VARCHAR(120) DEFAULT NULL,
  telefone      VARCHAR(30) DEFAULT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_professores_usuario (usuario_id),
  KEY idx_professores_escola (escola_id),
  CONSTRAINT fk_professores_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE,
  CONSTRAINT fk_professores_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TURMAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS turmas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id     INT UNSIGNED NOT NULL,
  nome          VARCHAR(80) NOT NULL,
  ano_letivo    SMALLINT UNSIGNED NOT NULL,
  turno         ENUM('manha','tarde','noite','integral') NOT NULL DEFAULT 'manha',
  serie         VARCHAR(60) DEFAULT NULL,
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turmas_escola_nome_ano (escola_id, nome, ano_letivo),
  KEY idx_turmas_escola (escola_id),
  CONSTRAINT fk_turmas_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ALUNOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alunos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id       INT UNSIGNED NOT NULL,
  turma_id        INT UNSIGNED DEFAULT NULL,
  nome            VARCHAR(150) NOT NULL,
  nome_busca      VARCHAR(150) NOT NULL,
  data_nascimento DATE DEFAULT NULL,
  status          ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alunos_escola_turma_nome (escola_id, turma_id, nome_busca),
  KEY idx_alunos_escola (escola_id),
  KEY idx_alunos_turma (turma_id),
  KEY idx_alunos_nome_busca (nome_busca),
  CONSTRAINT fk_alunos_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE,
  CONSTRAINT fk_alunos_turma FOREIGN KEY (turma_id) REFERENCES turmas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- QUESTIONARIOS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS questionarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id     INT UNSIGNED NOT NULL,
  professor_id  INT UNSIGNED NOT NULL,
  titulo        VARCHAR(180) NOT NULL,
  livro_titulo  VARCHAR(200) DEFAULT NULL,
  descricao     TEXT DEFAULT NULL,
  nivel         ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
  origem        ENUM('manual','ia') NOT NULL DEFAULT 'manual',
  status        ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'rascunho',
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_questionarios_escola_status (escola_id, status),
  KEY idx_questionarios_professor (professor_id),
  CONSTRAINT fk_questionarios_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE,
  CONSTRAINT fk_questionarios_professor FOREIGN KEY (professor_id) REFERENCES professores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PERGUNTAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS perguntas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  questionario_id INT UNSIGNED NOT NULL,
  enunciado      TEXT NOT NULL,
  tipo           ENUM('multipla','vf','aberta') NOT NULL DEFAULT 'multipla',
  explicacao     TEXT DEFAULT NULL,
  ordem          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  pontos         DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (id),
  KEY idx_perguntas_questionario (questionario_id, ordem),
  CONSTRAINT fk_perguntas_questionario FOREIGN KEY (questionario_id) REFERENCES questionarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ALTERNATIVAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alternativas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pergunta_id  INT UNSIGNED NOT NULL,
  texto        VARCHAR(255) NOT NULL,
  correta      TINYINT(1) NOT NULL DEFAULT 0,
  ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_alternativas_pergunta (pergunta_id, ordem),
  CONSTRAINT fk_alternativas_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LEITURAS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leituras (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id        INT UNSIGNED NOT NULL,
  aluno_id         INT UNSIGNED NOT NULL,
  turma_id         INT UNSIGNED DEFAULT NULL,
  questionario_id  INT UNSIGNED DEFAULT NULL,
  titulo           VARCHAR(200) NOT NULL,
  autor            VARCHAR(150) DEFAULT NULL,
  data_leitura     DATE NOT NULL,
  paginas          INT UNSIGNED NOT NULL DEFAULT 0,
  resumo           TEXT NOT NULL,
  status           ENUM('registrada','pendente_correcao','validada') NOT NULL DEFAULT 'registrada',
  nota             DECIMAL(5,2) DEFAULT NULL,
  acertos          SMALLINT UNSIGNED DEFAULT NULL,
  total_objetivas  SMALLINT UNSIGNED DEFAULT NULL,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leituras_escola_data (escola_id, data_leitura),
  KEY idx_leituras_aluno (aluno_id),
  KEY idx_leituras_turma (turma_id),
  KEY idx_leituras_questionario (questionario_id),
  CONSTRAINT fk_leituras_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE,
  CONSTRAINT fk_leituras_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id) ON DELETE CASCADE,
  CONSTRAINT fk_leituras_turma FOREIGN KEY (turma_id) REFERENCES turmas (id) ON DELETE SET NULL,
  CONSTRAINT fk_leituras_questionario FOREIGN KEY (questionario_id) REFERENCES questionarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- RESPOSTAS (uma linha por pergunta respondida)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS respostas (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id       INT UNSIGNED NOT NULL,
  leitura_id      INT UNSIGNED NOT NULL,
  questionario_id INT UNSIGNED NOT NULL,
  aluno_id        INT UNSIGNED NOT NULL,
  pergunta_id     INT UNSIGNED NOT NULL,
  alternativa_id  INT UNSIGNED DEFAULT NULL,
  resposta_texto  TEXT DEFAULT NULL,
  correta         TINYINT(1) DEFAULT NULL,
  pontos_obtidos  DECIMAL(5,2) DEFAULT NULL,
  feedback        TEXT DEFAULT NULL,
  corrigida_em    DATETIME DEFAULT NULL,
  corrigida_por   INT UNSIGNED DEFAULT NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_respostas_leitura_pergunta (leitura_id, pergunta_id),
  KEY idx_respostas_escola (escola_id),
  KEY idx_respostas_questionario (questionario_id),
  KEY idx_respostas_aluno (aluno_id),
  KEY idx_respostas_pendentes (escola_id, correta),
  CONSTRAINT fk_respostas_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_leitura FOREIGN KEY (leitura_id) REFERENCES leituras (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_questionario FOREIGN KEY (questionario_id) REFERENCES questionarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_pergunta FOREIGN KEY (pergunta_id) REFERENCES perguntas (id) ON DELETE CASCADE,
  CONSTRAINT fk_respostas_alternativa FOREIGN KEY (alternativa_id) REFERENCES alternativas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CONFIGURACOES (por escola, chave/valor)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id     INT UNSIGNED NOT NULL,
  chave         VARCHAR(60) NOT NULL,
  valor         TEXT DEFAULT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_configuracoes_escola_chave (escola_id, chave),
  CONSTRAINT fk_configuracoes_escola FOREIGN KEY (escola_id) REFERENCES escolas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TENTATIVAS DE LOGIN (protecao contra forca bruta)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tentativas_login (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(150) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  sucesso    TINYINT(1) NOT NULL DEFAULT 0,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tentativas_email_data (email, criado_em),
  KEY idx_tentativas_ip_data (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
