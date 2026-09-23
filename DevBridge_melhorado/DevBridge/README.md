# DevBridge — Sistema de contratação de profissionais técnicos

Aplicação PHP + PostgreSQL para clientes, programadores e técnicos de redes.

## Instalação

1. Extraia a pasta DevBridge para o diretório público do XAMPP, por exemplo htdocs/DevBridge.
2. Ative as extensões PHP pdo e pdo_pgsql no php.ini.
3. Ajuste as credenciais PostgreSQL em backend/config/config.php ou defina as variáveis de ambiente DEVBRIDGE_DB_HOST, DEVBRIDGE_DB_PORT, DEVBRIDGE_DB_NAME, DEVBRIDGE_DB_USER e DEVBRIDGE_DB_PASS.
4. Para uma instalação nova, execute database/criar_banco.sql.
5. Para uma base DevBridge existente, execute somente database/atualizar_banco.sql. Esta migração não apaga utilizadores existentes e cria perfis base para profissionais já cadastrados.
6. Abra http://localhost/DevBridge/.

## Perfis e fluxo

- Cliente: publica projetos com orçamento, prazo, competências e anexos; recebe propostas, escolhe o profissional, acompanha o contrato e avalia.
- Programador: cria perfil com foto, linguagens/tecnologias, competências e portfólio; pesquisa projetos, envia propostas e acompanha contratos.
- Técnico de Redes: cria perfil com foto, especializações de redes, competências e portfólio de trabalhos; pesquisa projetos, envia propostas e acompanha contratos.

O login lê o tipo que já estiver guardado na tabela usuarios, portanto contas antigas continuam a entrar sem novo registo. Programadores e técnicos antigos recebem um registo profissional automaticamente quando iniciam sessão.

## Segurança e uploads

- Senhas são guardadas com password_hash() e verificadas com password_verify().
- A API usa PDO com queries preparadas, sessão, controlo de acesso por perfil e token CSRF para operações que alteram dados.
- Uploads aceitam JPG, PNG, WEBP e, para anexos, PDF; validam MIME, extensão e tamanho e recebem nomes aleatórios.
- Os ficheiros ficam em uploads/perfis, uploads/projetos, uploads/portfolio e uploads/propostas. O .htaccess da pasta impede execução de ficheiros PHP em Apache.

## Notas de compatibilidade

O projeto requer PHP 8.0+ e PostgreSQL. Os ícones de linguagens usam emoji e URLs Simple Icons como alternativa visual, sem dependência de bibliotecas externas.
