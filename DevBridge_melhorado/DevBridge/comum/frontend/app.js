const DevBridge = (() => {
    const base = new URL('../../../index.php', document.baseURI);
    const root = new URL('../../../', document.baseURI);
    let csrf = '';

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);
    const asset = path => !path ? '' : (/^https?:\/\//i.test(path) ? path : new URL(path.replace(/^\/+/, ''), root).href);
    const money = value => new Intl.NumberFormat('pt-MZ', {style: 'currency', currency: 'MZN', maximumFractionDigits: 2}).format(Number(value || 0));
    const date = value => value ? new Date(String(value).slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-PT') : '—';
    const status = value => '<span class="badge status-' + String(value || '').toLowerCase().replaceAll('_', '-') + '">' + esc(String(value || '').replaceAll('_', ' ')) + '</span>';
    const avatar = (user, cls = '') => user?.foto_path
        ? '<img class="avatar ' + cls + '" src="' + asset(user.foto_path) + '" alt="Foto de ' + esc(user.nome || '') + '">'
        : '<span class="avatar ' + cls + '">' + esc(String(user?.nome || '?').trim().slice(0, 1).toUpperCase()) + '</span>';

    async function api(route, options = {}, query = {}) {
        const url = new URL(base);
        url.searchParams.set('rota', route);
        Object.entries(query).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value);
        });
        const method = String(options.method || 'GET').toUpperCase();
        if (!['GET', 'HEAD'].includes(method) && !csrf) {
            const tokenResponse = await api('csrf');
            csrf = tokenResponse.csrf;
        }
        const headers = new Headers(options.headers || {});
        if (!['GET', 'HEAD'].includes(method)) headers.set('X-CSRF-Token', csrf);
        const request = {...options, method, headers};
        if (request.body && !(request.body instanceof FormData) && typeof request.body === 'object') {
            headers.set('Content-Type', 'application/json');
            request.body = JSON.stringify(request.body);
        }
        const response = await fetch(url, request);
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.erro || 'Não foi possível concluir o pedido.');
        return payload;
    }

    function destination(user) {
        const pages = {
            CLIENTE: 'modulos/cliente/frontend/dashboard.html',
            PROGRAMADOR: 'modulos/programador/frontend/dashboard.html',
            TECNICO_REDES: 'modulos/rede/frontend/dashboard.html'
        };
        return new URL(pages[user?.tipo] || 'modulos/acesso/frontend/login.html', root).href;
    }

    async function guard(roles = []) {
        try {
            const result = await api('me');
            if (roles.length && !roles.includes(result.usuario.tipo)) {
                window.location.assign(destination(result.usuario));
                return null;
            }
            return result.usuario;
        } catch {
            window.location.assign(new URL('modulos/acesso/frontend/login.html', root).href);
            return null;
        }
    }

    async function logout() {
        try { await api('logout', {method: 'POST'}); } finally {
            window.location.assign(new URL('modulos/acesso/frontend/index.html', root).href);
        }
    }

    function links(user) {
        if (user.tipo === 'CLIENTE') return [
            ['Início', 'dashboard.html'], ['Projetos', 'projetos.html'], ['Publicar projeto', 'novo-projeto.html'],
            ['Propostas', 'propostas.html'], ['Profissionais', 'profissionais.html'], ['Contratações', 'contratacoes.html'],
            ['Meu perfil', 'perfil.html']
        ];
        return [
            ['Início', 'dashboard.html'], ['Projetos disponíveis', 'projetos.html'], ['Minhas propostas', 'minhas-propostas.html'],
            ['Contratações', 'contratacoes.html'], ['Meu perfil', 'perfil.html'], ['Editar perfil', 'configurar-perfil.html']
        ];
    }

    function shell(user, page) {
        const current = location.pathname.split('/').pop();
        const nav = links(user).map(item => '<a href="' + item[1] + '" class="' + (current === item[1] ? 'active' : '') + '">' + esc(item[0]) + '</a>').join('');
        document.body.innerHTML = '<div class="app"><header class="app-head"><a class="brand" href="dashboard.html">Dev<span>Bridge</span></a><span class="grow"></span><span class="muted">' + esc(user.tipo.replaceAll('_', ' ')) + '</span><button id="logout" class="secondary">Sair</button></header><aside class="sidebar"><div class="sidebar-brand">Área DevBridge</div><div class="side-user">' + avatar(user, 'sm') + '<div><strong>' + esc(user.nome) + '</strong><small>' + esc(user.tipo.replaceAll('_', ' ')) + '</small></div></div><nav class="side-nav">' + nav + '</nav></aside><main class="main"><div class="main-inner" id="page"></div></main></div>';
        document.querySelector('#logout').onclick = logout;
        document.title = page + ' · DevBridge';
        return document.querySelector('#page');
    }

    return {api, guard, logout, destination, shell, esc, asset, money, date, status, avatar};
})();
