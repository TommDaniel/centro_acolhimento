# ADR 0004 — Organização e unidade únicas

- Status: aceito; fundação física `ARQ-01B/ARQ-06A` concluída e validada em 10/09/2026
- Data: 28/08/2026
- Tarefas: `DEC-05`, `DEC-06`, `IAM-03`, `ARQ-01`, `ARQ-06`, `SEG-02`, `SEG-03`, `ACO-01`, `DOC-04`, `DOC-05`, `QA-01`
- Detalhamento: [arquitetura-alvo](../architecture/target-architecture.md)

## Contexto

A implantação atenderá uma organização de acolhimento institucional e uma unidade operacional/local/complexo físico. Dentro desse complexo existem várias casas destinadas à moradia e ao cuidado cotidiano de crianças e adolescentes, com atuação de equipe de cuidado cotidiano, equipe técnica e pessoal jurídico/administrativo. A organização usa cinco documentos funcionais: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais, sem que o sistema substitua revisão profissional ou determine automaticamente efeito jurídico.

As casas são localizações internas da unidade única, não unidades operacionais, organizações ou tenants. `DEC-01` ainda precisa decidir se a alocação e a transferência entre casas exigem histórico estruturado; esta ADR não antecipa tabela, cardinalidade ou regra de movimentação interna.

A POC atual não possui entidades nem chaves consistentes para esse contexto, embora registros assistenciais, auditoria e numeração documental precisem saber a qual instituição e unidade pertencem.

Os dados incluem identificação, saúde, documentos judiciais, fotografias e vínculos familiares de crianças e adolescentes. Mesmo com apenas uma organização e uma unidade, o contexto não deve ficar implícito em texto, configuração dispersa ou campo enviado pela interface. Ao mesmo tempo, implementar agora tenancy SaaS ou multiunidade criaria vínculos, telas, filtros, Policies e testes sem necessidade aprovada.

Esta ADR concluiu `DEC-05` no nível documental. O corte `ARQ-01B/ARQ-06A` agora materializa tabelas, chaves nullable, provisionamento/backfill e proteção dos novos writes; ainda não conclui Policies/RBAC, auditoria, `NOT NULL` nem infraestrutura produtiva.

## Opções consideradas

1. **Uma organização e uma unidade implícitas, sem entidades próprias.** Reduz tabelas no curto prazo, mas enfraquece integridade, auditoria, numeração e uma futura migração controlada.
2. **Uma organização e uma unidade explícitas por implantação.** Mantém o contexto institucional mínimo sem introduzir seleção ou isolamento multiunidade. **Opção escolhida.**
3. **Uma organização com várias unidades desde já.** Exigiria vínculo M:N de usuários, autorização entre unidades, transferências, filtros e operação não solicitados.
4. **SaaS multi-organização.** Ampliaria tenancy, administração e operação sem requisito aprovado e com maior risco para dados sensíveis.

## Decisão

Cada implantação atende exatamente uma organização cliente e uma unidade operacional. Não haverá SaaS multi-organização, suporte multiunidade, seleção de unidade, filtro de unidade nem operações entre unidades nesta fase.

Organização e unidade serão entidades/chaves explícitas mínimas no modelo de produção. A unidade pertence à organização. Pessoa pertence à organização; episódio de acolhimento pertence à unidade única; usuário pertence à unidade única. Papéis, setores, vínculos com caso e permissões ainda dependem de `SEG-02`, mas não serão modelados como vínculo M:N usuário–unidade.

O backend conhece o contexto da implantação por configuração controlada e relações persistidas. Organização/unidade não são escolhas do cliente: formulários, URL, query string, props Inertia, importações e integrações não podem trocar esse contexto. A aplicação não oferecerá rota ou tela de administração para criar ou alternar organizações/unidades.

## Invariantes

- existe uma organização cliente e uma unidade operacional ativas por implantação;
- a unidade única pertence à organização única;
- as várias casas do complexo são localizações internas da unidade única e não podem ser tratadas como organização, unidade selecionável ou tenant;
- toda pessoa pertence à organização;
- todo episódio de acolhimento pertence à unidade única e preserva essa referência no histórico;
- todo usuário pertence à unidade única; papel e setor não alteram esse pertencimento;
- fatos assistenciais carregam o contexto quando necessário ou o derivam de relação obrigatória e inequívoca;
- documentos finalizados, snapshots, movimentações e auditoria preservam organização/unidade históricas;
- documentos numerados usam sequência central, transacional e única por tipo/unidade/ano quando exigida pelo dicionário aprovado;
- o cliente nunca escolhe organização/unidade, e contexto recebido do cliente não é usado como autoridade;
- nenhuma segunda organização ou unidade pode ser ativada sem reabrir esta decisão e implantar previamente os controles necessários.

## Modelo de dados e rastreabilidade

O modelo conceitual mínimo contém organização e unidade. A unidade referencia a organização. Pessoa referencia a organização; episódio e usuário referenciam a unidade. Identificadores são opacos e atributos apresentáveis, como nome institucional, não substituem as chaves.

Registros filhos derivam organização/unidade de uma relação obrigatória quando isso for inequívoco. Duplicar chaves em todas as tabelas é rejeitado como abstração prematura. A chave é armazenada diretamente quando necessária para integridade, auditoria, snapshot, numeração ou uma consulta comprovada; redundância exige constraint e validação transacional contra a relação autoritativa.

Documentos e snapshots guardam as chaves e os atributos apresentáveis necessários para que alteração cadastral futura não reescreva o passado. Auditoria append-only registra organização, unidade, ator, ação, alvo, resultado e horário UTC, sem copiar narrativa, documento ou payload sensível. Arquivos privados podem usar prefixo opaco por organização/unidade e nome aleatório, sem PII no caminho; o acesso continua sujeito a Policy e auditoria.

Não haverá filtro de unidade em listagens, indicadores ou agenda enquanto existir somente uma. Índices são guiados pelas consultas efetivas; organização/unidade entram em constraints e índices apenas quando necessários à integridade, unicidade ou rastreabilidade. Sequência documental possui unicidade por `(tipo, unidade_id, ano, numero)` quando aplicável e nunca usa `MAX + 1` sem proteção transacional.

## Autorização

O servidor resolve organização e unidade a partir do contexto controlado da implantação e das relações persistidas. Requests não aceitam `organizacao_id` ou `unidade_id` como campos atribuíveis para alterar o pertencimento de pessoa, episódio, usuário, documento ou fato assistencial. Jobs, exports, PDFs, downloads e integrações reutilizam o contexto persistido, não valores fornecidos pelo cliente.

Policies e consultas continuam obrigatórias por recurso e ação. `SEG-02` decidiu que todas as contas ativas com papel `equipe_tecnica` possuem o mesmo conjunto assistencial aprovado dentro da unidade única; isso não concede acesso a cuidadoras, jurídico, administrativo, visitantes, integrações ou administradoras sem papel técnico. Testes negativos cobrem conta inativa, papel não técnico, IDOR por troca de recurso/ID, mass assignment, payload com organização/unidade adulterada, autopromoção, papel fora da allowlist e gestão de papel sem step-up recente.

## Migração e implantação

A migração será aditiva, verificável e compatível com roll-forward, sem `migrate:fresh`:

1. criar as estruturas de organização e unidade e as chaves inicialmente compatíveis com os dados legados;
2. criar a organização cliente e a unidade única por configuração/procedimento controlado da implantação, sem hardcode de nome ou outro dado real em migration, seed, teste ou repositório;
3. adicionar `organizacao_id`/`unidade_id` inicialmente anuláveis apenas aos agregados que precisam das chaves diretas;
4. mapear explicitamente pessoas, episódios, usuários, documentos e demais registros aplicáveis para o único contexto;
5. gerar reconciliação por contagens e identificadores técnicos, sem PII, e bloquear o avanço diante de órfão ou relação inconsistente;
6. atualizar a aplicação para gravar e resolver o contexto no backend, sem aceitar seleção pelo cliente;
7. somente após backfill e validação aplicar `NOT NULL`; FKs, `UNIQUE`, `CHECK` e os índices necessários à integridade já entram no corte aditivo com colunas nullable;
8. comprovar em PostgreSQL integridade, autorização, numeração concorrente e restauração antes de go-live.

O corte inicial executa os passos 1 a 6 por migrations aditivas e pelo comando idempotente `institution:provision-context`. FKs, `UNIQUE`, `CHECK` e índices de suporte já protegem o schema durante essa fase; somente a nulabilidade é mantida deliberadamente para o rollout. O passo 7 foi separado em `ARQ-01C` e só pode ocorrer após reconciliação no ambiente-alvo. O runbook está em [`docs/development/institution-context.md`](../development/institution-context.md).

Rollback operacional retorna à imagem anterior apenas enquanto compatível com o schema aditivo. Dados e colunas criados são preservados; falhas são corrigidas por migration adiante. Não se apaga histórico, organização, unidade ou vínculos para desfazer a implantação.

## Consequências

- O contexto institucional deixa de depender de convenção implícita e passa a ser auditável.
- Pessoa e episódio ficam separados corretamente: pessoa no escopo organizacional e episódio na unidade operacional.
- Usuários não precisam de vínculo M:N, seletor ou unidade corrente; papel/setor continuam independentes e sob `SEG-02`.
- Telas e relatórios não carregam filtros de unidade sem utilidade.
- A eventual seleção/filtro de casa será definida apenas se `DEC-01` aprovar sua necessidade operacional e seu histórico; essa interface nunca equivale a selecionar unidade/tenant.
- A sequência dos documentos que exigirem número continua corretamente definida por tipo/unidade/ano sem preparar fluxos multiunidade.
- Uma futura expansão exigirá trabalho explícito antes de cadastrar a segunda unidade/organização; as chaves atuais ajudam a migração, mas não constituem isolamento pronto.
- Esta decisão não declara concluídos `ARQ-01/06`, `SEG-02/03`, `ACO-01`, `DOC-05` ou `QA-01` e não autoriza dados reais.

## Alternativas rejeitadas

Não será criada relação M:N usuário–unidade, unidade corrente de sessão, seletor de unidade, Policy cross-unit, transferência entre unidades ou filtro por unidade. Também não serão adicionados `organizacao_id` e `unidade_id` indiscriminadamente em toda tabela sem finalidade de integridade ou histórico.

Não será adotado um singleton apenas textual ou confiado ao frontend. Tampouco será alegado suporte SaaS/multiunidade porque existem tabelas e chaves de organização/unidade.

## Riscos e gatilhos de reavaliação

- Aceitar organização/unidade do request pode permitir mass assignment e contexto inconsistente mesmo com uma única unidade; o backend deve ignorar ou rejeitar esses campos e usar relações persistidas.
- Hardcode de nome/ID real em migration ou seed pode vazar dado institucional e dificultar ambientes; o provisionamento deve usar configuração controlada.
- Backfill sem reconciliação pode deixar órfãos ou documentos sem contexto histórico.
- Chaves explícitas podem ser confundidas com suporte multiunidade; documentação e testes devem deixar claro que isolamento entre unidades não foi implementado.

Reabrir esta ADR antes de criar ou ativar uma segunda unidade ou organização, ou quando surgir requisito de operação entre unidades, transferência, visão consolidada, administração por unidade, SSO/diretório multi-organização ou isolamento contratual. Antes da expansão, aprovar modelo de vínculos, matriz de autorização, migração/backfill, isolamento de consultas/cache/jobs/arquivos, auditoria e testes de IDOR. Até isso ocorrer, a segunda unidade/organização falha fechado e não é cadastrável pela aplicação.

## Pendências e contratos derivados

- `SEG-02`: papel técnico uniforme, demais papéis/ações e controles de sensibilidade dentro da unidade única;
- `ARQ-01/06`: schema físico, provisioning controlado, estratégia de backfill, constraints, índices e provas em PostgreSQL;
- `SEG-03`: eventos e acesso à auditoria com organização/unidade registradas;
- `ACO-01`: vínculo do episódio à unidade e preservação histórica, ainda sujeito às transições de `DEC-01`;
- `DEC-01`/`ACO-01`: decidir e, se aprovado, preservar alocação e transferências entre casas internas sem confundi-las com unidade;
- `DOC-05`: sequência concorrente e única por tipo documental/unidade/ano quando aplicável;
- `QA-01`: testes de contexto adulterado, IDOR, mass assignment, órfãos e constraints.

Não se assume suporte multiunidade, transferência entre unidades, filtro de unidade, unidade selecionável ou múltiplos vínculos de unidade por usuário.
