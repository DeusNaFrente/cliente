# Fases do Projeto Cliente (Coleta de Dados)

**Site:** https://cliente.j2tec.com.br
**Atualizado:** 01/10/2026 — modelo coletor/emissor completo (etapas 1 a 4) e traduções.

## Modelo

- **Admin:** gerencia todas as contas, vê cadastros, quem está online e métricas.
- **Contas comuns:** todas iguais. No primeiro login escolhem **Coletor** ou **Emissor** e trocam quando quiserem (menu lateral).
- **Coletor:** cria um pedido (PF: nome + CPF; PJ: razão social + CNPJ), gera um link de uso único e envia a mensagem. Lista com filtros, histórico, "Reenviar" (link novo, o anterior morre), "Ver e editar cadastro".
- **Emissor:** abre o link, informa o e-mail e recebe um link mágico (1 hora, uso único; conta nova recebe usuário e senha provisória). Cai direto no formulário pré-preenchido, envia e pode reenviar.
- O coletor recebe e-mail quando o cadastro chega ou é atualizado.

## Etapas

| Etapa | O quê | Status |
|-------|-------|--------|
| 1 | Perfis, "Online agora", log de acessos, bloqueio derruba sessão | ✅ |
| 2 | Área do coletor, link do pedido, link mágico por e-mail | ✅ |
| 3 | Formulário do emissor ligado ao pedido, reenvio, edição pelo coletor, anexos | ✅ |
| 4 | Admin: cadastros (detalhe, anexos, excluir), contas (criar, bloquear, nova senha), métricas (período, zerar) | ✅ |
| — | Traduções PT/EN/ES em todas as telas do coletor e do emissor (admin fica em PT) | ✅ |

## Pendências
- Ajustar o visual do e-mail quando o usuário mandar o print.
- Trocar senhas de SSH e do banco (ficaram expostas na web até 30/09/2026) e dar senha própria ao e-mail `clientecoletadados` (usuário pediu para deixar para depois).
- Teste visual no navegador pelo usuário (os testes desta fase foram feitos por HTTP no servidor).
