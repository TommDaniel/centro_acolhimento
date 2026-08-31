# ADR 0003 — Infraestrutura futura em Contabo VPS, Swarm e Redis

- Status: aceito como arquitetura-alvo; fundação PostgreSQL local/CI concluída por `ARQ-01A` em 31/08/2026, produção pendente
- Data: 12/08/2026
- Tarefas: `ARQ-01`, `ARQ-02`, `ARQ-03`, `ARQ-04`, `ARQ-07`, `ARQ-08`, `OPS-02`
- Referência: [Laravel Horizon 13.x](https://laravel.com/docs/13.x/horizon)
- Detalhamento: [arquitetura-alvo](../architecture/target-architecture.md)

## Contexto

A POC atual usa SQLite e filesystem local/público, inadequados para dados restritos e sensíveis. O orçamento total inicial é de aproximadamente R$ 60 por mês, há apenas quatro usuários e uma aplicação, e a hospedagem futura aprovada é uma Contabo VPS.

O objetivo da fase 1 é reduzir risco de perda e tornar execução, filas e deploy reproduzíveis dentro desse limite. Desempenho não motiva cache amplo. Esta decisão não afirma que a infraestrutura já existe nem autoriza dados reais.

## Estado de implementação

`ARQ-01A` adota PostgreSQL 17 como banco canônico no Docker local, PHPUnit
Feature e Playwright E2E. O serviço local usa rede interna, volume persistente,
healthcheck e credenciais explicitamente não produtivas. A migration histórica
de numeração foi ajustada, excepcionalmente antes de qualquer cadeia produtiva,
para parsing PHP portátil; uma migration aditiva indexa as chaves estrangeiras.
O SQLite sintético da POC permanece intocado e não é importado.
Até o cutover Contabo, ele é usado somente pela demonstração efêmera da
Vercel, mediante `VERCEL=true`; local, CI e E2E permanecem PostgreSQL. Essa
exceção não autoriza dados reais e será removida ao desativar o deploy legado.

Esse corte não implementa a infraestrutura descrita abaixo para a Contabo:
Swarm, TLS, roles mínimas separadas, limites de conexão, monitoramento,
backup/WAL/PITR, cofre, restore e RPO/RTO continuam pendentes em `ARQ-01/07`.

## Decisão

Executar o monólito modular Laravel/Inertia/React em Docker Swarm inicialmente com uma única VPS e um único nó. Swarm será usado para declarar serviços, healthchecks, rolling update, secrets e reinício de processos. Um nó único continua sendo `single point of failure`: múltiplas réplicas no mesmo host não oferecem alta disponibilidade contra perda da VPS, rede, disco ou região.

PostgreSQL e cada serviço Redis stateful ficam presos por placement constraint/label ao nó que possui seu volume dedicado. O binding é explícito; o Swarm não pode reagendar um serviço stateful para volume local vazio. Antes de adicionar nós, o plano deve escolher e testar migração/failover de dados, volumes externos/replicados ou serviço dedicado/gerenciado. Réplica de container e rescheduling sem dados não contam como HA.

### Serviços da fase 1

- reverse proxy/TLS, expondo somente HTTPS;
- uma ou mais réplicas da aplicação Laravel conforme capacidade medida;
- workers Laravel Queues supervisionados por Horizon;
- scheduler com uma única execução lógica;
- PostgreSQL autogerido em rede privada e volume persistente dedicado;
- três serviços/processos Redis separados, com volumes próprios para cache, sessões e filas;
- agentes de backup e observabilidade sem conteúdo sensível;
- integração futura com object storage privado, cujo fornecedor/região ainda depende de avaliação.

Imagens serão imutáveis e ambientes terão bancos, Redis, buckets e segredos separados. Credenciais entram por Docker secrets ou mecanismo equivalente, nunca em imagem, stack file versionado, argumento de processo ou log. Redes internas não publicam PostgreSQL nem Redis. Se o estado de manager do Swarm for incluído no disaster recovery, sua unlock key fica em cofre/local segregado do backup do manager.

### PostgreSQL e backups

PostgreSQL é a fonte de verdade para fatos assistenciais, estado durável e outbox transacional. Na fase 1 ele será autogerido na VPS por restrição orçamentária, com volume persistente, TLS e acesso somente pela rede privada.

A aplicação usa role própria sem `SUPERUSER`, `CREATEDB`, `CREATEROLE` ou DDL de rotina. Migrations usam role separada e temporariamente disponibilizada ao job controlado; backup/restore usa outra role com privilégios mínimos compatíveis. Credenciais e auditoria são separadas, e a aplicação nunca recebe as roles operacionais.

`max_connections` e pools de aplicação/Horizon serão limitados pela memória e capacidade medida da VPS. PgBouncer ou pooling externo só entra após evidência de saturação e teste de compatibilidade com transações, locks e session state. `statement_timeout`, `idle_in_transaction_session_timeout` e `lock_timeout` terão limites por workload. Operação inclui autovacuum/`ANALYZE`, acompanhamento de bloat, locks, disco e conexões, patching com janela/runbook de manutenção e `pg_stat_statements` de acesso restrito, sem parâmetros ou payloads sensíveis em logs/métricas.

Backup base e arquivamento WAL/PITR serão criptografados e copiados obrigatoriamente para destino fora da VPS, com credenciais e retenção separadas. Um cofre/escrow criptografado e segregado fora da VPS mantém as versões de `APP_KEY` e das chaves/segredos de backup, OAuth, push e TOTP necessárias para descriptografar dados ainda vigentes. Acesso, rotação e descarte dessas versões são auditados; nenhum segredo entra no repositório. Restore/PITR integral, incluindo chaves necessárias, será testado periodicamente em ambiente isolado contra RPO/RTO aprovados. Snapshot da própria VPS ou volume no mesmo host não conta como única estratégia de backup.

Mover PostgreSQL para nó dedicado ou serviço gerenciado quando RPO/RTO não forem atingidos, manutenção exigir indisponibilidade inaceitável, houver contenção sustentada de CPU/I/O/memória, crescimento além da capacidade validada ou a redução de risco/carga operacional justificar o custo.

### Redis, filas e outbox

Redis é obrigatório na fase 1, mas cada finalidade exige serviço/processo e volume próprios. Bancos lógicos numerados dentro da mesma instância não isolam eviction, persistência, fsync, memória ou falha e não atendem esta decisão:

| Classe | Durabilidade | Política | Regra de uso |
|---|---|---|---|
| Cache | Descartável; volume separado não é fonte de verdade | TTL obrigatório e eviction dimensionada | Somente dados derivados; nunca única cópia; cache seletivo por medição |
| Sessões | Persistência/fsync conforme RPO, volume dedicado | `noeviction` | Sem recaller; cookie de browser-session, idle 15 min e absoluto 8 h; DR valida geração ou invalida antes de aceitar sessão restaurada |
| Filas | Persistência/fsync conforme RPO, volume e backup dedicados | `noeviction`, persistência e Horizon | Retry/backoff, timeout, idempotência, failed jobs, outbox e alerta de atraso |

Persistência, fsync, backup/restore e alertas de memória, disco, latência, falha e idade da fila serão definidos/testados contra o RPO de sessões e filas. Restore não pode ressuscitar sessão expirada, revogada ou anterior à geração de logout global, inativação, reset, recuperação, re-enrollment ou perda de dispositivo: validar geração/revogação no PostgreSQL ou invalidar todas as sessões restauradas e exigir senha + TOTP. Recaller permanece desativado, inclusive após restore. Filas restauradas são reconciliadas de modo idempotente com outbox/efeitos PostgreSQL.

Laravel Queues + Horizon executam Gmail/Pub/Sub futuro, PDFs, exports, miniaturas, antimalware e notificações. Efeito durável nasce no PostgreSQL na mesma transação que a outbox; o worker confirma efeito idempotente e pode ser repetido. Redis não é o registro exclusivo de um fato ou compromisso. A documentação oficial registra que Horizon não é compatível com Redis Cluster; antes dessa topologia, uma nova decisão deve manter Redis não-cluster dedicado para Horizon ou substituir Horizon/driver por alternativa compatível, avaliada e testada.

O dashboard `/horizon` é superfície administrativa: Gate/Policy no backend exige conta ativa, sessão `mfa_verified` e permissão operacional explícita. Preferencialmente, reverse proxy/rede administrativa ou VPN acrescenta uma segunda barreira, sem substituir autorização. Visitante, usuário autenticado comum, sessão `password_only` e operador fora do vínculo autorizado recebem negação testada; esconder link não é controle.

Payload de job contém somente identificadores opacos, tipo/versão da operação, correlation/idempotency ID e metadados técnicos mínimos. Não transporta corpo/assunto de e-mail, documento/anexo, narrativa assistencial, nome/CPF/CNS ou outra PII, URL assinada, token, chave ou segredo. O worker busca no PostgreSQL/object storage apenas o necessário, revalidando autorização/estado no momento da execução.

Configurar e testar trim/retenção de jobs recentes, concluídos, silenciados e failed jobs conforme finalidade e política de retenção. Exceções, tags, métricas e logs são redigidos e não serializam payload sensível. Acesso ao Redis de filas e à conexão reservada do Horizon é restrito por rede e credencial próprias aos serviços/workers; operador humano usa o dashboard autorizado, não acesso Redis direto de rotina.

### Arquivos privados

Object storage S3-compatible privado, criptografado, versionado e com URLs curtas continua obrigação de `ARQ-02`, mas fornecedor, região, DPA e custo ainda estão pendentes. Centralizar execução na VPS não significa manter objetos apenas nela: uma cópia local ou uma única cópia no host não é durável. Banco, objeto, checksum e estado de quarentena precisam de reconciliação, backup isolado e teste conjunto de restauração.

## Escala e fase 2

Antes de dados reais, o corte P0 de `ARQ-07` aprova capacidade mínima, orçamento, SLO/RPO/RTO, alertas e restore integral de banco, objetos e chaves/segredos. Crescimento e otimização permanecem no teste P2 de `ARQ-08`.

Expandir somente por medição ou nova fronteira operacional. Gatilhos incluem falha de SLO/RPO/RTO, saturação sustentada de CPU/memória/I/O, atraso de fila, crescimento de banco/objetos, necessidade de manutenção sem janela aceitável, segunda aplicação/organização ou risco incompatível com nó único.

A fase 2 pode adicionar nós Swarm em domínios de falha distintos, separar workers, mover PostgreSQL/Redis para serviços dedicados ou gerenciados e adotar object storage/backup com redundância adequada. Antes disso, placement e volumes stateful precisam de plano de migração/failover testado. Adicionar um segundo container no mesmo host não satisfaz esses gatilhos de HA.

## Consequências e riscos

- A fase 1 cabe melhor no orçamento e reduz complexidade externa, mas concentra aplicação, banco, Redis e workers em um host.
- Falha total da VPS interrompe o serviço; recuperação depende de infraestrutura recriada, backup fora da VPS e runbook testado.
- PostgreSQL e Redis autogeridos exigem patches, manutenção, autovacuum/análise, monitoramento, capacidade, backup e resposta a incidente.
- Separação de Redis usa mais memória, mas evita que eviction de cache derrube sessões ou trabalhos.
- Horizon acrescenta superfície administrativa e retenção operacional; Gate/rede, minimização, trim e revisão de failed jobs são controles obrigatórios antes de produção.
- Swarm melhora orquestração e deploy, não disponibilidade física no single-node.
- Capacidade mínima, storage privado, cofre de chaves e RPO/RTO/restore integral são gates P0 antes de dados reais.

## Rollback e saída

Deploy de aplicação usa imagem anterior e healthcheck, desde que migrations permaneçam compatíveis com roll-forward. Mudanças de banco não usam `migrate:fresh`; rollback destrutivo é proibido. Falha em Redis cache permite descarte/aquecimento gradual; sessões e filas exigem recuperação pelo procedimento e reconciliação com PostgreSQL/outbox, sem fingir sucesso nem reativar sessão antiga/revogada.

Se Contabo/Swarm ou serviços autogeridos deixarem de cumprir custo, segurança ou SLO, reconstruir a stack a partir de imagens, configuração sem segredos e backups verificados em outro provedor/nós; recuperar chaves necessárias do cofre segregado; restaurar PostgreSQL por base + WAL/PITR; reconciliar object storage por checksum; e reprocessar outbox idempotente. A VPS original nunca será a única fonte necessária à saída.
