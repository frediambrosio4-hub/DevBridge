-- DevBridge — criação limpa para PostgreSQL.
-- ATENÇÃO: este ficheiro remove as tabelas DevBridge existentes.

DROP TABLE IF EXISTS avaliacoes CASCADE;
DROP TABLE IF EXISTS proposta_anexos CASCADE;
DROP TABLE IF EXISTS propostas CASCADE;
DROP TABLE IF EXISTS contratacoes CASCADE;
DROP TABLE IF EXISTS projeto_anexos CASCADE;
DROP TABLE IF EXISTS projeto_competencia CASCADE;
DROP TABLE IF EXISTS projetos CASCADE;
DROP TABLE IF EXISTS portfolio_imagens CASCADE;
DROP TABLE IF EXISTS portfolio CASCADE;
DROP TABLE IF EXISTS profissional_especializacao CASCADE;
DROP TABLE IF EXISTS especializacoes_rede CASCADE;
DROP TABLE IF EXISTS profissional_linguagem CASCADE;
DROP TABLE IF EXISTS linguagens_programacao CASCADE;
DROP TABLE IF EXISTS profissional_competencia CASCADE;
DROP TABLE IF EXISTS competencias CASCADE;
DROP TABLE IF EXISTS fotos_perfil CASCADE;
DROP TABLE IF EXISTS profissionais CASCADE;
DROP TABLE IF EXISTS usuarios CASCADE;

CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    descricao VARCHAR(1000),
    localizacao VARCHAR(150),
    foto_path VARCHAR(500),
    -- Mantido apenas para compatibilidade com instalações anteriores.
    foto_url VARCHAR(500),
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_tipo_usuario CHECK (tipo IN ('CLIENTE', 'PROGRAMADOR', 'TECNICO_REDES', 'ADMINISTRADOR'))
);

CREATE TABLE profissionais (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    tipo_profissional VARCHAR(30) NOT NULL,
    descricao TEXT,
    localizacao VARCHAR(150),
    experiencia TEXT,
    CONSTRAINT chk_tipo_profissional CHECK (tipo_profissional IN ('PROGRAMADOR', 'TECNICO_REDES'))
);

CREATE TABLE fotos_perfil (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0),
    data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE linguagens_programacao (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(80) NOT NULL UNIQUE,
    icone VARCHAR(80) NOT NULL,
    logo_url VARCHAR(500)
);

CREATE TABLE especializacoes_rede (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    icone VARCHAR(80) NOT NULL
);

CREATE TABLE competencias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE profissional_linguagem (
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    linguagem_id INTEGER NOT NULL REFERENCES linguagens_programacao(id) ON DELETE CASCADE,
    nivel VARCHAR(30) NOT NULL DEFAULT 'Intermédio',
    PRIMARY KEY (profissional_id, linguagem_id),
    CONSTRAINT chk_nivel_linguagem CHECK (nivel IN ('Iniciante', 'Intermédio', 'Avançado'))
);

CREATE TABLE profissional_especializacao (
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    especializacao_id INTEGER NOT NULL REFERENCES especializacoes_rede(id) ON DELETE CASCADE,
    PRIMARY KEY (profissional_id, especializacao_id)
);

CREATE TABLE profissional_competencia (
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    competencia_id INTEGER NOT NULL REFERENCES competencias(id) ON DELETE CASCADE,
    PRIMARY KEY (profissional_id, competencia_id)
);

CREATE TABLE projetos (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    -- Campo legado: recebe a mesma categoria em instalações compatíveis.
    setor VARCHAR(100),
    orcamento NUMERIC(12,2) NOT NULL CHECK (orcamento > 0),
    prazo DATE NOT NULL,
    localizacao VARCHAR(150),
    tipo_trabalho VARCHAR(20) NOT NULL DEFAULT 'REMOTO',
    status VARCHAR(40) NOT NULL DEFAULT 'RECEBENDO_PROPOSTAS',
    data_publicacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_tipo_trabalho CHECK (tipo_trabalho IN ('REMOTO', 'PRESENCIAL', 'HIBRIDO')),
    CONSTRAINT chk_status_projeto CHECK (status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS', 'PROFISSIONAL_ESCOLHIDO', 'EM_ANDAMENTO', 'ENTREGUE', 'CONCLUIDO', 'CANCELADO'))
);

CREATE TABLE projeto_competencia (
    projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    competencia_id INTEGER NOT NULL REFERENCES competencias(id) ON DELETE CASCADE,
    PRIMARY KEY (projeto_id, competencia_id)
);

CREATE TABLE projeto_anexos (
    id SERIAL PRIMARY KEY,
    projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0),
    data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE propostas (
    id SERIAL PRIMARY KEY,
    projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    valor NUMERIC(12,2) NOT NULL CHECK (valor > 0),
    prazo INTEGER NOT NULL CHECK (prazo > 0),
    mensagem TEXT NOT NULL,
    descricao_solucao TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'ENVIADA',
    data_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_status_proposta CHECK (status IN ('ENVIADA', 'VISUALIZADA', 'EM_NEGOCIACAO', 'ACEITA', 'RECUSADA', 'CANCELADA')),
    CONSTRAINT uq_proposta_profissional_projeto UNIQUE (projeto_id, profissional_id)
);

CREATE TABLE proposta_anexos (
    id SERIAL PRIMARY KEY,
    proposta_id INTEGER NOT NULL REFERENCES propostas(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0),
    data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contratacoes (
    id SERIAL PRIMARY KEY,
    projeto_id INTEGER NOT NULL UNIQUE REFERENCES projetos(id) ON DELETE CASCADE,
    proposta_id INTEGER NOT NULL UNIQUE REFERENCES propostas(id) ON DELETE CASCADE,
    cliente_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    status VARCHAR(30) NOT NULL DEFAULT 'AGUARDANDO_INICIO',
    data_inicio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_conclusao TIMESTAMP,
    CONSTRAINT chk_status_contratacao CHECK (status IN ('AGUARDANDO_INICIO', 'EM_ANDAMENTO', 'ENTREGUE', 'CONCLUIDA', 'CANCELADA'))
);

CREATE TABLE portfolio (
    id SERIAL PRIMARY KEY,
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,
    tecnologias VARCHAR(500),
    area VARCHAR(150),
    equipamentos VARCHAR(500),
    link_projeto VARCHAR(500),
    link_github VARCHAR(500),
    data_realizacao DATE,
    observacoes TEXT,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE portfolio_imagens (
    id SERIAL PRIMARY KEY,
    portfolio_id INTEGER NOT NULL REFERENCES portfolio(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0),
    data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE avaliacoes (
    id SERIAL PRIMARY KEY,
    contratacao_id INTEGER NOT NULL REFERENCES contratacoes(id) ON DELETE CASCADE,
    avaliador_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    avaliado_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    nota SMALLINT NOT NULL CHECK (nota BETWEEN 1 AND 5),
    comentario TEXT,
    data_avaliacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_auto_avaliacao CHECK (avaliador_id <> avaliado_id),
    CONSTRAINT uq_avaliacao UNIQUE (contratacao_id, avaliador_id)
);

CREATE INDEX idx_projetos_cliente_status ON projetos(cliente_id, status);
CREATE INDEX idx_projetos_descoberta ON projetos(categoria, localizacao, orcamento);
CREATE INDEX idx_propostas_projeto ON propostas(projeto_id);
CREATE INDEX idx_contratacoes_cliente ON contratacoes(cliente_id);
CREATE INDEX idx_portfolio_profissional ON portfolio(profissional_id);
CREATE INDEX idx_avaliacoes_avaliado ON avaliacoes(avaliado_id);

INSERT INTO linguagens_programacao (nome, icone, logo_url) VALUES
('HTML', '🌐', 'https://cdn.simpleicons.org/html5'),
('CSS', '🎨', 'https://cdn.simpleicons.org/css3'),
('JavaScript', '🟨', 'https://cdn.simpleicons.org/javascript'),
('PHP', '🐘', 'https://cdn.simpleicons.org/php'),
('Java', '☕', 'https://cdn.simpleicons.org/openjdk'),
('Python', '🐍', 'https://cdn.simpleicons.org/python'),
('C', '🔤', 'https://cdn.simpleicons.org/c'),
('C++', '➕', 'https://cdn.simpleicons.org/cplusplus'),
('C#', '♯', 'https://cdn.simpleicons.org/csharp'),
('SQL', '🗃️', 'https://cdn.simpleicons.org/postgresql'),
('PostgreSQL', '🐘', 'https://cdn.simpleicons.org/postgresql'),
('MySQL', '🐬', 'https://cdn.simpleicons.org/mysql'),
('React', '⚛️', 'https://cdn.simpleicons.org/react'),
('Node.js', '🟢', 'https://cdn.simpleicons.org/nodedotjs')
ON CONFLICT (nome) DO NOTHING;

INSERT INTO especializacoes_rede (nome, icone) VALUES
('Telecomunicações', '📡'), ('Cisco', '🔷'), ('CCNA', '🏅'), ('Routing', '↔️'),
('Switching', '🔀'), ('Redes LAN', '🏢'), ('Redes WAN', '🌍'), ('Wi-Fi', '📶'),
('Segurança de Redes', '🛡️'), ('Packet Tracer', '🧩'), ('Configuração de Routers', '📡'),
('Configuração de Switches', '🔀'), ('Cabeamento Estruturado', '🧵')
ON CONFLICT (nome) DO NOTHING;
