# Regras do backend Laravel

- Leia `../AGENTS.md`, `../RTK.md` e use `$centro-acolhimento-domain` para qualquer entidade assistencial.
- Controllers coordenam requisição/resposta. Validação fica em `FormRequest`, autorização em `Policy/Gate` e regras transacionais em Actions/Services de domínio.
- Aplique autorização antes de carregar ou mutar anexos e relações. Escopo de consulta deve respeitar organização, unidade, setor, vínculo com o caso e sensibilidade; UUID não substitui autorização.
- Use transações para mudanças com mais de um registro, locks/constraints para invariantes concorrentes e idempotência em retries/jobs.
- Movimentações, versões de medicação, atendimentos, documentos finalizados e auditoria são históricos append-only. Correção cria novo evento/versão, sem sobrescrita silenciosa.
- Use constraints, foreign keys, índices derivados de consultas reais, eager loading e paginação. Evite N+1, `SELECT *` e carregar calendários/históricos inteiros.
- Persistir timestamps em UTC; converter apenas na borda de apresentação.
- Arquivos sensíveis ficam em storage privado, com nome gerado, validação de conteúdo, política no download e auditoria. Nunca confie somente em extensão/MIME declarado.
- Evite mass assignment amplo, SQL montado por string, desserialização insegura e conteúdo sensível em exceções/logs.
- Migrações de produção são incrementais e roll-forward; não use `migrate:fresh`, seed fictício automático ou cascade delete sem análise de retenção.
- Cada mudança exige PHPUnit de domínio/Feature, matriz positiva e negativa de Policy e, quando aplicável, teste em PostgreSQL.
