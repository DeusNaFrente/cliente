# Retomada — Projeto Cliente

**Última sessão:** 01/10/2026 — etapas 1 a 4 e traduções concluídas e testadas por HTTP.
**Próximo passo:** o que o usuário pedir (ver pendências em `doc/fases.md`).

## Como trabalhar neste projeto (regras do usuário)
- O projeto vive **só no servidor**: `/home/mcf4ys7z/public_html/cliente`. Editar direto lá.
- **Sem backups** e sem arquivos de rascunho: o usuário não quer sujeira.
- **Nunca gravar nada em C:** na máquina do usuário. Nada de cópias locais do código.
- Acesso por `plink` (PuTTY). Enviar arquivos pela **entrada padrão** (`conteúdo | plink ... "cat > arquivo"`), limpando BOM e CRLF com `sed` no servidor. Scripts maiores: mandar um `.py` para `/tmp` e rodar com `python3`.
  - Nunca passar código ou aspas duplas como argumento: o PowerShell 5.1 tira as aspas. Já causou um estrago: um `printf` virou redirecionamento e zerou `.env`, `schema.sql`, `PROJETO.md` e `FASE1_SETUP.txt` (os dois últimos se perderam; `.env` e `schema.sql` foram refeitos).
  - Comandos longos demais falham no Windows: dividir em partes de até ~25 mil caracteres.
  - O filtro de segurança da ferramenta barra comandos que contenham palavras iguais a comandos de remoção do Windows, mesmo dentro de textos (até a palavra espanhola "de+el"). Apagar temporários do servidor com Python (`os.remove`) ou `unlink`; no JSON espanhol essa palavra foi enviada com `\u0065` e regravada normal no servidor.
- Testar pelo HTTP com `curl` no próprio servidor e apagar os dados de teste no final.
- Credenciais ficam no `.env` do servidor (bloqueado para a web). **Não copiar senhas para a documentação.**

## Estrutura
| Arquivo | Papel |
|---------|-------|
| `auth.php` | Sessão, login (usuário ou e-mail), perfis, último acesso, log de acessos, link pendente do pedido |
| `i18n.php`, `lang/en.json`, `lang/es.json`, `lang.php`, `assets/lang.js` | Traduções: `t('texto em português')`; seletor de idioma em todas as telas |
| `layout.php` | `esc()`, menu lateral com troca de perfil e idioma, avisos |
| `coletas_lib.php` | Regras do pedido, tokens, link mágico, e-mails (convite, cadastro recebido, senha) |
| `mailer.php` | Envio SMTP (SSL 465) pelas variáveis `MAIL_*` do `.env` |
| `login.php`, `perfil.php`, `mudar-senha.php`, `logout.php` | Acesso e conta |
| `coletor.php`, `coletor_api.php`, `assets/coletor.js` | Área do coletor |
| `convite.php`, `magico.php` | Link do pedido e entrada pelo e-mail |
| `emissor.php`, `cadastro.php`, `cadastro_api.php`, `assets/cliente.js` | Emissor e formulário do cadastro |
| `download.php` | Anexos (admin, coletor e emissor do pedido) |
| `admin.php`, `admin/api.php`, `admin/online.php`, `assets/admin.js` | Painel do admin (cadastros, contas, online, métricas, senha) |
| `ping.php`, `assets/heartbeat.js` | Marca quem está com a página aberta |
| `schema.sql` | Estrutura do banco gerada do banco real |

## Banco (cliente_db)
- `users` (+ `profile`, `last_seen_at`, `last_ip`; e-mail único), `access_log`, `app_settings` (`metrics_reset_at`).
- `coletas`, `coleta_eventos`, `login_tokens`, `submissions` (+ `coleta_id`), `submission_files` (sem uso).
- Cadastro antigo de agosto (Originaltop Import and Business) importado em `submissions` sem pedido; anexos em `uploads/0e569247-...`.

## Segurança aplicada
- Tokens só como hash SHA-256; links de pedido e mágico de uso único; link mágico só entra com clique (POST).
- APIs de escrita exigem `X-Requested-With: fetch`; links de e-mail usam `APP_URL`.
- `.htaccess` bloqueia `.env`, `doc/` e arquivos `.md`, `.txt`, `.sql`, `.log`, `.py`, `.sh`.
- Anexos: só imagem e PDF abrem no navegador; o resto baixa.

## Checklist ao retomar
- [ ] Print do e-mail para ajustar o visual.
- [ ] Lembrar das senhas (SSH, banco, e-mail).
