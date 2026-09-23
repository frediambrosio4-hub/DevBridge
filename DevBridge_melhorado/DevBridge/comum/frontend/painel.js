(async function () {
    const expected = document.body.dataset.role ? document.body.dataset.role.split(',') : [];
    const kind = document.body.dataset.page || 'dashboard';
    const user = await DevBridge.guard(expected);
    if (!user) return;
    const page = DevBridge.shell(user, kind);

    const E = DevBridge.esc;
    const sectionTitle = (title, subtitle, action) => '<div class="page-title"><div><h1>' + E(title) + '</h1><p class="muted">' + E(subtitle || '') + '</p></div>' + (action || '') + '</div>';
    const empty = text => '<div class="empty">' + E(text) + '</div>';
    const stars = number => '<span class="rating">' + '★'.repeat(Math.round(Number(number || 0))) + '☆'.repeat(5 - Math.round(Number(number || 0))) + '</span>';
    const initials = name => String(name || '?').slice(0, 1).toUpperCase();
    const skills = list => (list || []).map(item => '<span class="tech"><b>' + E(item.icone || '•') + '</b>' + E(item.nome || item) + (item.nivel ? ' <small>· ' + E(item.nivel) + '</small>' : '') + '</span>').join('');
    const fields = form => Object.fromEntries(new FormData(form));

    function projectCard(project, professional) {
        const photo = project.imagem_capa ? '<img class="portfolio-image" src="' + DevBridge.asset(project.imagem_capa) + '" alt="Imagem do projeto">' : '';
        const action = professional ? '<button class="open-proposal" data-id="' + project.id + '" data-title="' + E(project.titulo) + '">Enviar proposta</button>' : (project.status === 'RECEBENDO_PROPOSTAS' || project.status === 'PUBLICADO' ? '<button class="cancel-project secondary" data-id="' + project.id + '">Cancelar</button>' : '');
        return '<article class="item">' + photo + '<div class="section-title"><div><h3>' + E(project.titulo) + '</h3><p class="muted">por ' + E(project.cliente_nome || 'Cliente') + '</p></div>' + DevBridge.status(project.status) + '</div><p>' + E(project.descricao) + '</p><div class="meta"><span>▣ ' + E(project.categoria || project.setor || 'Outros') + '</span><span>⌖ ' + E(project.localizacao || 'Localização não indicada') + '</span><span>◷ ' + DevBridge.date(project.prazo) + '</span><span>◈ ' + DevBridge.money(project.orcamento) + '</span></div><div class="actions">' + action + '</div></article>';
    }

    async function dashboard() {
        const result = await DevBridge.api('dashboard');
        const data = result.stats;
        const isClient = user.tipo === 'CLIENTE';
        const isDev = user.tipo === 'PROGRAMADOR';
        const names = isClient
            ? [['Projetos publicados', data.projetos_publicados], ['Propostas recebidas', data.propostas_recebidas], ['Contratações', data.contratacoes], ['Em andamento', data.em_andamento]]
            : [['Projetos disponíveis', data.projetos_disponiveis], ['Propostas enviadas', data.propostas_enviadas], ['Contratos', data.contratos], ['Avaliação média', data.avaliacao_media]];
        const welcome = isClient ? 'Organize o seu próximo projeto com profissionais qualificados.' : (isDev ? 'Mostre a sua experiência e encontre projetos para desenvolver.' : 'Mostre os seus trabalhos de rede e encontre novas oportunidades.');
        const primary = isClient ? '<a class="button" href="novo-projeto.html">Publicar projeto</a>' : '<a class="button" href="projetos.html">Explorar projetos</a>';
        page.innerHTML = sectionTitle('Olá, ' + user.nome.split(' ')[0], welcome, primary) + '<section class="grid stats">' + names.map(item => '<article class="stat"><small>' + E(item[0]) + '</small><strong>' + E(item[1]) + '</strong></article>').join('') + '</section><section class="card"><span class="chip">' + E(user.tipo.replaceAll('_', ' ')) + '</span><h2 style="margin-top:10px">' + (isClient ? 'Tudo pronto para publicar?' : 'O seu perfil inspira confiança?') + '</h2><p class="muted">' + (isClient ? 'Inclua objetivo, orçamento, competências necessárias e fotos do local para receber propostas mais relevantes.' : 'Atualize fotografia, descrição, competências e portfólio antes de enviar propostas.') + '</p><div class="actions"><a class="button secondary" href="perfil.html">Ver meu perfil</a><a class="button outline" href="' + (isClient ? 'propostas.html' : 'minhas-propostas.html') + '">Acompanhar propostas</a></div></section>';
    }

    async function projects() {
        const professional = user.tipo !== 'CLIENTE';
        page.innerHTML = sectionTitle(professional ? 'Projetos disponíveis' : 'Meus projetos', professional ? 'Pesquise oportunidades por categoria, localização, competência ou orçamento.' : 'Gerencie as publicações e acompanhe as propostas recebidas.', professional ? '' : '<a class="button" href="novo-projeto.html">Publicar projeto</a>') + (professional ? '<form id="projectFilters" class="toolbar card"><input name="q" placeholder="Pesquisar projeto"><input name="categoria" placeholder="Categoria"><input name="competencia" placeholder="Competência"><input name="localizacao" placeholder="Localização"><button>Filtrar</button></form><div id="proposalBox"></div>' : '') + '<section id="projects" class="item-list"></section>';
        const list = page.querySelector('#projects');
        async function load(filters) {
            const result = await DevBridge.api('projetos', {}, filters || {});
            list.innerHTML = result.projetos.length ? result.projetos.map(item => projectCard(item, professional)).join('') : empty('Ainda não existem projetos para estes filtros.');
        }
        if (professional) {
            page.querySelector('#projectFilters').onsubmit = event => { event.preventDefault(); load(fields(event.target)); };
            page.querySelector('#proposalBox').addEventListener('submit', submitProposal);
            list.onclick = event => {
                const button = event.target.closest('.open-proposal');
                if (!button) return;
                page.querySelector('#proposalBox').innerHTML = '<form id="proposalForm" class="card"><h2>Proposta para ' + E(button.dataset.title) + '</h2><input type="hidden" name="projeto_id" value="' + button.dataset.id + '"><div class="grid grid-2"><label>Valor proposto (MT)<input name="valor" type="number" min="1" step="0.01" required></label><label>Prazo em dias<input name="prazo" type="number" min="1" required></label></div><label>Mensagem<input name="mensagem" required placeholder="Apresente a sua proposta"></label><label>Descrição da solução<textarea name="descricao_solucao" placeholder="Como pretende executar o trabalho?"></textarea></label><button>Enviar proposta</button><p class="error"></p></form>';
                page.querySelector('#proposalBox').scrollIntoView({behavior:'smooth'});
            };
        } else {
            list.onclick = async event => {
                const button = event.target.closest('.cancel-project');
                if (!button || !confirm('Cancelar este projeto?')) return;
                try { await DevBridge.api('projetos', {method:'DELETE'}, {id:button.dataset.id}); await load(); } catch (e) { alert(e.message); }
            };
        }
        await load();
    }

    async function submitProposal(event) {
        event.preventDefault();
        const error = event.target.querySelector('.error');
        try {
            const result = await DevBridge.api('propostas', {method:'POST', body:fields(event.target)});
            error.textContent = result.mensagem;
            event.target.reset();
        } catch (e) { error.textContent = e.message; }
    }

    async function newProject() {
        page.innerHTML = sectionTitle('Publicar projeto', 'Descreva o trabalho, defina prazo e orçamento. Pode incluir fotografias ou documentos PDF.') + '<section class="card"><form id="newProject"><label>Título<input name="titulo" maxlength="150" required></label><label>Descrição<textarea name="descricao" maxlength="5000" required></textarea></label><div class="grid grid-3"><label>Categoria<input name="categoria" placeholder="Ex.: Redes" required></label><label>Orçamento (MT)<input name="orcamento" type="number" min="1" step="0.01" required></label><label>Prazo<input name="prazo" type="date" required></label></div><div class="grid grid-2"><label>Localização<input name="localizacao" placeholder="Ex.: Maputo"></label><label>Modalidade<select name="tipo_trabalho"><option value="REMOTO">Remoto</option><option value="PRESENCIAL">Presencial</option><option value="HIBRIDO">Híbrido</option></select></label></div><label>Competências necessárias<input name="competencias" placeholder="Ex.: Cisco, Cabeamento Estruturado, Wi-Fi"></label><label>Fotos ou anexos (JPG, PNG, WEBP ou PDF; máximo 10 MB)<input name="anexos" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple></label><button>Publicar projeto</button><p class="error"></p></form></section>';
        page.querySelector('#newProject').onsubmit = async event => {
            event.preventDefault(); const form = event.target; const error = form.querySelector('.error'); error.textContent = '';
            try {
                const data = fields(form); data.competencias = data.competencias.split(',').map(x => x.trim()).filter(Boolean);
                const created = await DevBridge.api('projetos', {method:'POST', body:data});
                for (const file of form.anexos.files) {
                    const upload = new FormData(); upload.append('projeto_id', created.id); upload.append('anexo', file);
                    await DevBridge.api('projetos/anexo', {method:'POST', body:upload});
                }
                location.assign('projetos.html');
            } catch (e) { error.textContent = e.message; }
        };
    }

    async function proposals() {
        const client = user.tipo === 'CLIENTE';
        page.innerHTML = sectionTitle(client ? 'Propostas recebidas' : 'Minhas propostas', client ? 'Compare profissional, competências, avaliação, prazo e valor antes de escolher.' : 'Acompanhe a resposta dos clientes às suas propostas.') + '<section id="proposals" class="item-list"></section>';
        const box = page.querySelector('#proposals');
        async function load() {
            const result = await DevBridge.api('propostas');
            box.innerHTML = result.propostas.length ? result.propostas.map(item => {
                const profile = client ? '<div class="profile-head">' + DevBridge.avatar({nome:item.profissional_nome, foto_path:item.foto_path}, 'sm') + '<div><h3>' + E(item.profissional_nome) + '</h3><p class="muted">' + E(item.tipo_profissional.replaceAll('_', ' ')) + ' · ' + stars(item.avaliacao) + ' ' + E(item.avaliacao) + '</p></div></div>' : '<h3>' + E(item.projeto_titulo) + '</h3>';
                const action = client && item.status === 'ENVIADA' ? '<div class="actions"><button class="accept" data-id="' + item.id + '">Escolher profissional</button><button class="refuse secondary" data-id="' + item.id + '">Recusar</button></div>' : '';
                return '<article class="item">' + profile + '<p><strong>Projeto:</strong> ' + E(item.projeto_titulo) + '</p><p>' + E(item.mensagem) + '</p>' + (item.descricao_solucao ? '<p class="muted">' + E(item.descricao_solucao) + '</p>' : '') + '<div class="meta"><span>Valor: ' + DevBridge.money(item.valor) + '</span><span>Prazo: ' + E(item.prazo) + ' dias</span><span>' + DevBridge.status(item.status) + '</span>' + (item.especialidades ? '<span>' + E(item.especialidades) + '</span>' : '') + '</div>' + action + '</article>';
            }).join('') : empty('Nenhuma proposta encontrada.');
        }
        if (client) box.onclick = async event => {
            const accept = event.target.closest('.accept'), refuse = event.target.closest('.refuse');
            try {
                if (accept && confirm('Escolher este profissional para o projeto?')) await DevBridge.api('contratacoes', {method:'POST', body:{proposta_id:accept.dataset.id}});
                if (refuse && confirm('Recusar esta proposta?')) await DevBridge.api('propostas', {method:'DELETE'}, {id:refuse.dataset.id});
                if (accept || refuse) await load();
            } catch (e) { alert(e.message); }
        };
        await load();
    }

    async function professionals() {
        page.innerHTML = sectionTitle('Encontrar profissionais', 'Filtre por tipo, tecnologia, competência ou localização.') + '<form id="professionalFilters" class="toolbar card"><select name="tipo"><option value="">Todos os perfis</option><option value="PROGRAMADOR">Programadores</option><option value="TECNICO_REDES">Técnicos de Redes</option></select><input name="q" placeholder="Tecnologia ou competência"><input name="localizacao" placeholder="Localização"><button>Pesquisar</button></form><section id="professionals" class="grid grid-3"></section>';
        const list = page.querySelector('#professionals');
        async function load(query) {
            const result = await DevBridge.api('profissionais', {}, query || {});
            list.innerHTML = result.profissionais.length ? result.profissionais.map(person => '<article class="card"><div class="profile-head">' + DevBridge.avatar({nome:person.nome, foto_path:person.foto_path}) + '<div><h2>' + E(person.nome) + '</h2><span class="badge">' + E(person.tipo_profissional.replaceAll('_', ' ')) + '</span></div></div><p style="margin-top:14px">' + E(person.descricao_profissional || 'Perfil profissional em configuração.') + '</p><div class="meta"><span>⌖ ' + E(person.localizacao || 'Não indicado') + '</span><span>' + stars(person.avaliacao) + ' ' + E(person.avaliacao) + '</span></div></article>').join('') : empty('Nenhum profissional corresponde à pesquisa.');
        }
        page.querySelector('#professionalFilters').onsubmit = event => { event.preventDefault(); load(fields(event.target)); };
        await load();
    }

    async function contracts() {
        page.innerHTML = sectionTitle('Contratações', 'Acompanhe o estado de cada trabalho e registe a avaliação após a conclusão.') + '<section id="contracts" class="item-list"></section>';
        const list = page.querySelector('#contracts');
        async function load() {
            const result = await DevBridge.api('contratacoes');
            list.innerHTML = result.contratacoes.length ? result.contratacoes.map(item => {
                const options = ['AGUARDANDO_INICIO','EM_ANDAMENTO','ENTREGUE','CONCLUIDA','CANCELADA'].map(s => '<option value="' + s + '"' + (s === item.status ? ' selected' : '') + '>' + E(s.replaceAll('_', ' ')) + '</option>').join('');
                const evaluation = item.status === 'CONCLUIDA' ? '<form class="inline-form evaluation" data-id="' + item.id + '"><label>Avaliação<select name="nota"><option value="5">★★★★★ (5)</option><option value="4">★★★★ (4)</option><option value="3">★★★ (3)</option><option value="2">★★ (2)</option><option value="1">★ (1)</option></select></label><label>Comentário<input name="comentario" maxlength="2000" placeholder="Como foi a experiência?"></label><button>Avaliar</button></form>' : '';
                return '<article class="item"><div class="section-title"><div><h3>' + E(item.projeto_titulo) + '</h3><p class="muted">Com ' + E(item.contraparte_nome) + '</p></div>' + DevBridge.status(item.status) + '</div><form class="inline-form contract-status" data-id="' + item.id + '"><label>Atualizar estado<select name="status">' + options + '</select></label><button class="secondary">Guardar estado</button></form>' + evaluation + '</article>';
            }).join('') : empty('Ainda não existem contratações.');
        }
        list.onsubmit = async event => {
            event.preventDefault();
            const form = event.target;
            try {
                if (form.classList.contains('contract-status')) await DevBridge.api('contratacoes', {method:'PUT', body:fields(form)}, {id:form.dataset.id});
                if (form.classList.contains('evaluation')) await DevBridge.api('avaliacoes', {method:'POST', body:{contratacao_id:form.dataset.id, ...fields(form)}});
                await load();
            } catch (e) { alert(e.message); }
        };
        await load();
    }

    function portfolioItems(items) {
        return items.length ? '<div class="portfolio">' + items.map(item => {
            const image = item.imagens && item.imagens[0] ? '<img class="portfolio-image" src="' + DevBridge.asset(item.imagens[0].caminho) + '" alt="Imagem de ' + E(item.titulo) + '">' : '<div class="portfolio-image"></div>';
            const links = (item.link_projeto ? '<a href="' + E(item.link_projeto) + '" target="_blank" rel="noopener">Projeto</a> ' : '') + (item.link_github ? '<a href="' + E(item.link_github) + '" target="_blank" rel="noopener">GitHub</a>' : '');
            return '<article class="portfolio-item">' + image + '<div class="portfolio-body"><h3>' + E(item.titulo) + '</h3><p class="muted">' + E(item.descricao || '') + '</p><div class="meta"><span>' + E(item.tecnologias || item.area || '') + '</span>' + (item.data_realizacao ? '<span>' + DevBridge.date(item.data_realizacao) + '</span>' : '') + '</div><p>' + links + '</p></div></article>';
        }).join('') + '</div>' : empty('Ainda não há trabalhos no portfólio.');
    }

    async function profile(editing) {
        const result = await DevBridge.api('perfil');
        const p = result.perfil;
        const professional = user.tipo !== 'CLIENTE';
        if (!editing) {
            page.innerHTML = sectionTitle('Meu perfil', 'A sua apresentação pública na plataforma.', '<a class="button" href="' + (professional ? 'configurar-perfil.html' : 'perfil.html?editar=1') + '">Editar perfil</a>') + '<div class="profile-columns"><section class="card"><div class="profile-head">' + DevBridge.avatar({nome:p.nome, foto_path:p.foto_path}, 'profile-photo') + '<div><h1>' + E(p.nome) + '</h1><span class="badge">' + E(p.tipo.replaceAll('_', ' ')) + '</span><p class="muted">' + E(p.localizacao || p.localizacao_profissional || 'Localização não indicada') + '</p></div></div><hr class="separator"><h2>Sobre</h2><p>' + E(p.descricao_profissional || p.descricao || 'Ainda não foi adicionada uma descrição.') + '</p>' + (p.experiencia ? '<p><strong>Experiência:</strong> ' + E(p.experiencia) + '</p>' : '') + '<h2 style="margin-top:22px">Competências</h2><div class="badges">' + skills(p.linguagens.length ? p.linguagens : p.especializacoes) + skills(p.competencias) + '</div><h2 style="margin-top:22px">Portfólio</h2>' + portfolioItems(p.portfolio) + '</section><aside class="card"><h2>Avaliações</h2><p class="muted">' + stars((p.avaliacoes || []).reduce((total, a) => total + Number(a.nota), 0) / Math.max(p.avaliacoes.length, 1)) + ' ' + p.avaliacoes.length + ' avaliação(ões)</p><div class="item-list">' + (p.avaliacoes.length ? p.avaliacoes.map(a => '<div class="item"><strong>' + E(a.avaliador_nome) + '</strong><p>' + stars(a.nota) + '</p><p class="muted">' + E(a.comentario || '') + '</p></div>').join('') : '<p class="muted">Ainda não existem avaliações.</p>') + '</div></aside></div>';
            return;
        }
        const options = await DevBridge.api('perfil/opcoes');
        const selectedLanguages = new Set((p.linguagens || []).map(x => String(x.id)));
        const selectedSpecialties = new Set((p.especializacoes || []).map(x => String(x.id)));
        const choices = user.tipo === 'PROGRAMADOR' ? options.linguagens.map(x => '<label class="check"><input type="checkbox" name="linguagens" value="' + x.id + '"' + (selectedLanguages.has(String(x.id)) ? ' checked' : '') + '><span>' + E(x.icone || '•') + ' ' + E(x.nome) + '</span><select name="nivel_' + x.id + '"><option>Iniciante</option><option selected>Intermédio</option><option>Avançado</option></select></label>').join('') : options.especializacoes.map(x => '<label class="check"><input type="checkbox" name="especializacoes" value="' + x.id + '"' + (selectedSpecialties.has(String(x.id)) ? ' checked' : '') + '><span>' + E(x.icone || '•') + ' ' + E(x.nome) + '</span></label>').join('');
        page.innerHTML = sectionTitle('Editar perfil', 'Complete as informações que clientes e profissionais veem antes de contratar.') + '<div class="grid grid-2"><section class="card"><h2>Fotografia</h2><div class="profile-head">' + DevBridge.avatar({nome:p.nome, foto_path:p.foto_path}, 'profile-photo') + '<form id="photoForm"><label>Nova foto<input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required></label><button class="secondary">Enviar foto</button></form></div><hr class="separator"><form id="profileForm"><label>Nome<input name="nome" value="' + E(p.nome) + '" required></label><label>Localização<input name="localizacao" value="' + E(p.localizacao || p.localizacao_profissional || '') + '"></label><label>Descrição pessoal<textarea name="descricao">' + E(p.descricao || '') + '</textarea></label>' + (professional ? '<label>Descrição profissional<textarea name="descricao_profissional" required>' + E(p.descricao_profissional || '') + '</textarea></label><label>Experiência<textarea name="experiencia">' + E(p.experiencia || '') + '</textarea></label><label>Competências adicionais (separadas por vírgula)<input name="competencias" value="' + E((p.competencias || []).map(x => x.nome).join(', ')) + '"></label>' : '') + '<button>Guardar perfil</button><p class="error"></p></form></section>' + (professional ? '<section class="card"><h2>' + (user.tipo === 'PROGRAMADOR' ? 'Linguagens e tecnologias' : 'Áreas de especialização') + '</h2><div class="checks">' + choices + '</div><hr class="separator"><h2>Adicionar ao portfólio</h2><form id="portfolioForm"><label>Título<input name="titulo" required></label><label>Descrição<textarea name="descricao"></textarea></label><label>' + (user.tipo === 'PROGRAMADOR' ? 'Tecnologias usadas' : 'Área / equipamentos') + '<input name="tecnologias"></label><label>Link do projeto<input name="link_projeto" type="url"></label><label>Link GitHub (opcional)<input name="link_github" type="url"></label><label>Data<input name="data_realizacao" type="date"></label><label>Imagem do trabalho<input name="imagem" type="file" accept=".jpg,.jpeg,.png,.webp"></label><button class="secondary">Adicionar ao portfólio</button><p class="error"></p></form></section>' : '') + '</div>';
        page.querySelector('#photoForm').onsubmit = async event => {
            event.preventDefault(); try { await DevBridge.api('perfil/foto', {method:'POST', body:new FormData(event.target)}); location.reload(); } catch (e) { alert(e.message); }
        };
        page.querySelector('#profileForm').onsubmit = async event => {
            event.preventDefault(); const form = event.target; const data = fields(form);
            data.competencias = (data.competencias || '').split(',').map(x => x.trim()).filter(Boolean);
            data.linguagens = Array.from(page.querySelectorAll('input[name="linguagens"]:checked')).map(x => ({id:x.value, nivel:page.querySelector('[name="nivel_' + x.value + '"]').value}));
            data.especializacoes = Array.from(page.querySelectorAll('input[name="especializacoes"]:checked')).map(x => x.value);
            try { await DevBridge.api('perfil', {method:'PUT', body:data}); form.querySelector('.error').textContent = 'Perfil guardado com sucesso.'; } catch (e) { form.querySelector('.error').textContent = e.message; }
        };
        if (professional) page.querySelector('#portfolioForm').onsubmit = async event => {
            event.preventDefault(); const form = event.target;
            try {
                const created = await DevBridge.api('portfolio', {method:'POST', body:fields(form)});
                if (form.imagem.files[0]) { const upload = new FormData(); upload.append('portfolio_id', created.id); upload.append('imagem', form.imagem.files[0]); await DevBridge.api('portfolio/imagem', {method:'POST', body:upload}); }
                form.reset(); form.querySelector('.error').textContent = 'Item adicionado ao portfólio.';
            } catch (e) { form.querySelector('.error').textContent = e.message; }
        };
    }

    const tasks = {
        dashboard,
        projetos: projects,
        'novo-projeto': newProject,
        propostas,
        'minhas-propostas': proposals,
        profissionais,
        contratacoes: contracts,
        perfil: () => profile(new URLSearchParams(location.search).get('editar') === '1'),
        'configurar-perfil': () => profile(true)
    };
    try { await (tasks[kind] || dashboard)(); } catch (error) {
        page.innerHTML = '<div class="empty">Não foi possível carregar esta página: ' + E(error.message) + '</div>';
    }
})();
