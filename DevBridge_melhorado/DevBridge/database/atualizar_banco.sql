-- DevBridge — migração não destrutiva para instalações existentes.
-- Execute este ficheiro UMA VEZ na base de dados já existente. Não elimina utilizadores.

BEGIN;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS descricao VARCHAR(1000);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS localizacao VARCHAR(150);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_path VARCHAR(500);
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_url VARCHAR(500);
UPDATE usuarios SET foto_path = foto_url WHERE foto_path IS NULL AND foto_url IS NOT NULL;

ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS localizacao VARCHAR(150);
ALTER TABLE profissionais ADD COLUMN IF NOT EXISTS experiencia TEXT;

CREATE TABLE IF NOT EXISTS fotos_perfil (
    id SERIAL PRIMARY KEY, usuario_id INTEGER NOT NULL UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL, caminho VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0), data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS linguagens_programacao (
    id SERIAL PRIMARY KEY, nome VARCHAR(80) NOT NULL UNIQUE, icone VARCHAR(80) NOT NULL DEFAULT '💻', logo_url VARCHAR(500)
);
ALTER TABLE linguagens_programacao ADD COLUMN IF NOT EXISTS icone VARCHAR(80) NOT NULL DEFAULT '💻';
ALTER TABLE linguagens_programacao ADD COLUMN IF NOT EXISTS logo_url VARCHAR(500);

CREATE TABLE IF NOT EXISTS especializacoes_rede (
    id SERIAL PRIMARY KEY, nome VARCHAR(120) NOT NULL UNIQUE, icone VARCHAR(80) NOT NULL DEFAULT '📡'
);
CREATE TABLE IF NOT EXISTS profissional_especializacao (
    profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    especializacao_id INTEGER NOT NULL REFERENCES especializacoes_rede(id) ON DELETE CASCADE,
    PRIMARY KEY (profissional_id, especializacao_id)
);

ALTER TABLE projetos ADD COLUMN IF NOT EXISTS categoria VARCHAR(100);
ALTER TABLE projetos ADD COLUMN IF NOT EXISTS setor VARCHAR(100);
ALTER TABLE projetos ADD COLUMN IF NOT EXISTS localizacao VARCHAR(150);
ALTER TABLE projetos ADD COLUMN IF NOT EXISTS tipo_trabalho VARCHAR(20) NOT NULL DEFAULT 'REMOTO';
UPDATE projetos SET categoria = COALESCE(categoria, setor, 'Outros') WHERE categoria IS NULL;
ALTER TABLE projetos ALTER COLUMN categoria SET NOT NULL;
ALTER TABLE projetos ALTER COLUMN setor DROP NOT NULL;
UPDATE projetos SET status = 'RECEBENDO_PROPOSTAS' WHERE status IN ('RASCUNHO', 'EM_NEGOCIACAO');
ALTER TABLE projetos DROP CONSTRAINT IF EXISTS chk_status_projeto;
ALTER TABLE projetos ADD CONSTRAINT chk_status_projeto CHECK (status IN ('PUBLICADO', 'RECEBENDO_PROPOSTAS', 'PROFISSIONAL_ESCOLHIDO', 'EM_ANDAMENTO', 'ENTREGUE', 'CONCLUIDO', 'CANCELADO'));

CREATE TABLE IF NOT EXISTS projeto_anexos (
    id SERIAL PRIMARY KEY, projeto_id INTEGER NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL, caminho VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0), data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE propostas ADD COLUMN IF NOT EXISTS descricao_solucao TEXT;
CREATE TABLE IF NOT EXISTS proposta_anexos (
    id SERIAL PRIMARY KEY, proposta_id INTEGER NOT NULL REFERENCES propostas(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL, caminho VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0), data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

UPDATE contratacoes SET status = 'ENTREGUE' WHERE status = 'AGUARDANDO_APROVACAO';
ALTER TABLE contratacoes DROP CONSTRAINT IF EXISTS chk_status_contratacao;
ALTER TABLE contratacoes ADD CONSTRAINT chk_status_contratacao CHECK (status IN ('AGUARDANDO_INICIO', 'EM_ANDAMENTO', 'ENTREGUE', 'CONCLUIDA', 'CANCELADA'));

CREATE TABLE IF NOT EXISTS portfolio (
    id SERIAL PRIMARY KEY, profissional_id INTEGER NOT NULL REFERENCES profissionais(id) ON DELETE CASCADE,
    titulo VARCHAR(150) NOT NULL, descricao TEXT, tecnologias VARCHAR(500), area VARCHAR(150), equipamentos VARCHAR(500),
    link_projeto VARCHAR(500), link_github VARCHAR(500), data_realizacao DATE, observacoes TEXT,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS portfolio_imagens (
    id SERIAL PRIMARY KEY, portfolio_id INTEGER NOT NULL REFERENCES portfolio(id) ON DELETE CASCADE,
    nome_ficheiro VARCHAR(255) NOT NULL, caminho VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL,
    tamanho INTEGER NOT NULL CHECK (tamanho > 0), data_upload TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Cria automaticamente o perfil base para profissionais já registados.
INSERT INTO profissionais (usuario_id, tipo_profissional)
SELECT u.id, u.tipo FROM usuarios u
WHERE u.tipo IN ('PROGRAMADOR', 'TECNICO_REDES')
AND NOT EXISTS (SELECT 1 FROM profissionais p WHERE p.usuario_id = u.id);

-- Converte a ligação antiga de áreas de redes, quando as tabelas antigas existirem.
DO $$
BEGIN
    IF to_regclass('public.profissional_tipo_sistema') IS NOT NULL THEN
        INSERT INTO especializacoes_rede (nome, icone)
        SELECT DISTINCT ts.nome, '📡' FROM tipos_sistema ts
        ON CONFLICT (nome) DO NOTHING;
        INSERT INTO profissional_especializacao (profissional_id, especializacao_id)
        SELECT pts.profissional_id, er.id FROM profissional_tipo_sistema pts
        JOIN tipos_sistema ts ON ts.id = pts.tipo_sistema_id
        JOIN especializacoes_rede er ON er.nome = ts.nome
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

INSERT INTO linguagens_programacao (nome, icone, logo_url) VALUES
('HTML', '🌐', 'https://cdn.simpleicons.org/html5'), ('CSS', '🎨', 'https://cdn.simpleicons.org/css3'),
('JavaScript', '🟨', 'https://cdn.simpleicons.org/javascript'), ('PHP', '🐘', 'https://cdn.simpleicons.org/php'),
('Java', '☕', 'https://cdn.simpleicons.org/openjdk'), ('Python', '🐍', 'https://cdn.simpleicons.org/python'),
('C', '🔤', 'https://cdn.simpleicons.org/c'), ('C++', '➕', 'https://cdn.simpleicons.org/cplusplus'),
('C#', '♯', 'https://cdn.simpleicons.org/csharp'), ('SQL', '🗃️', 'https://cdn.simpleicons.org/postgresql'),
('PostgreSQL', '🐘', 'https://cdn.simpleicons.org/postgresql'), ('MySQL', '🐬', 'https://cdn.simpleicons.org/mysql'),
('React', '⚛️', 'https://cdn.simpleicons.org/react'), ('Node.js', '🟢', 'https://cdn.simpleicons.org/nodedotjs')
ON CONFLICT (nome) DO NOTHING;

INSERT INTO especializacoes_rede (nome, icone) VALUES
('Telecomunicações', '📡'), ('Cisco', '🔷'), ('CCNA', '🏅'), ('Routing', '↔️'), ('Switching', '🔀'),
('Redes LAN', '🏢'), ('Redes WAN', '🌍'), ('Wi-Fi', '📶'), ('Segurança de Redes', '🛡️'),
('Packet Tracer', '🧩'), ('Configuração de Routers', '📡'), ('Configuração de Switches', '🔀'),
('Cabeamento Estruturado', '🧵')
ON CONFLICT (nome) DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_projetos_cliente_status ON projetos(cliente_id, status);
CREATE INDEX IF NOT EXISTS idx_propostas_projeto ON propostas(projeto_id);
CREATE INDEX IF NOT EXISTS idx_portfolio_profissional ON portfolio(profissional_id);
CREATE INDEX IF NOT EXISTS idx_avaliacoes_avaliado ON avaliacoes(avaliado_id);

COMMIT;
