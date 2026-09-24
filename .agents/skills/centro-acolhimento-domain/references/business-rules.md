# Regras de negócio

## 1. Identidade, ingresso e acolhimento

- `crianca/adolescente` representa a pessoa; `acolhimento` representa um episódio. Uma pessoa pode possuir vários episódios, mas no máximo um episódio aberto por unidade, salvo regra institucional futura explicitamente aprovada.
- Abrir episódio exige data/hora, unidade, motivo/fundamento, processo quando disponível, origem do encaminhamento e ator. Órgão condutor e pessoa condutora são campos distintos; “Outro” exige complemento.
- `DEC-01A` definiu a situação operacional do episódio aberto: `na_unidade`, `evadido` quando a pessoa fugiu/está desaparecida, e `internado` quando está temporariamente hospitalizada por saúde. Evasão e internação não encerram nem substituem o episódio. `desacolhido` encerra o episódio e exige data, motivo/destino e responsável.
- Transições vigentes: entrada → `na_unidade`; `na_unidade` → `evadido`/`internado`/`desacolhido`; `evadido`/`internado` → `na_unidade`/`desacolhido`. Transição inválida deve falhar no domínio, não apenas na UI.
- Cada transição, inclusive retorno, cria nova movimentação append-only com início, eventual fim, local/destino, observação, ator e timestamps. Retorno restabelece `na_unidade` sem apagar nem reescrever a ausência anterior.
- Somente a necessidade de alocação e transferência histórica entre casas internas permanece pendente em `DEC-01`; isso não bloqueia episódios, situações, retornos nem filtros de `DEC-01A`.
- Filtros “Acolhidos”, “Desacolhidos”, “Evadidos”, “Internados” derivam do episódio e situação vigentes. Cada pessoa aparece uma vez por escopo consultado; contagem e lista usam a mesma consulta/regra.
- Possível duplicidade de pessoa gera alerta e revisão humana; não faça merge automático por nome/documento.

## 2. Saúde

- Atendimento de saúde exige pessoa, episódio/unidade aplicável, data/hora, tipo/local, motivo, relato, acompanhante, desfecho, autor e timestamps. Estabelecimento/profissional e anexos são opcionais conforme o caso.
- `UBS`, `UPA`, `Hospital`, `CAPS` e `Outro` são códigos estáveis com rótulos apresentáveis; “Outro” exige descrição. Não armazene o rótulo como única chave.
- Encaminhamento e atendimento são fatos distintos. Criar agenda/lembrete não marca atendimento/exame como realizado.
- Exame segue máquina de estados: solicitado → agendado → realizado → resultado recebido; solicitado/agendado também podem ser cancelados. Datas e autor de cada transição são preservados.
- Medicação contínua possui uma identidade estável de tratamento e versões imutáveis. Uma alteração insere, na mesma transação, nova versão ligada por `supersedes_version_id`; a versão anterior nunca sofre `UPDATE`/`DELETE`. Somente o ponteiro técnico da versão corrente pode mudar sob lock/controle otimista. Encerramento, cancelamento e retificação também geram versão/evento imutável.
- Todos os campos de `SAU-02` pertencem ao snapshot: medicamento/princípio ativo, apresentação, dose, via, frequência/horários, início/fim clínicos, prescritor, origem, observações, fonte, autor e horário do registro. Separe vigência clínica (`effective_start/end`) de tempo do sistema (`recorded_at` e sucessão); fim clínico não representa substituição técnica.
- Antes de modelar, decida se mudar medicamento/princípio ativo é nova versão do mesmo tratamento ou novo tratamento ligado por `replaces_id`. Não infira. Nunca mostre versão encerrada, cancelada ou substituída como corrente.
- Administração pontual/tratamento de dose única é um evento separado e não entra na lista de medicações contínuas.
- O sistema registra fonte e `informacao_confirmada_em`. Um alerta de desatualização exige prazo e atores autorizados aprovados; sem essa decisão, não classifique a informação como atualizada/desatualizada. O sistema não interpreta clinicamente nem calcula doses.
- A sensibilidade sexual/reprodutiva é marcada explicitamente por ator autorizado, nunca inferida de medicamento, sexo ou gênero. A permissão específica é aplicada antes de lista, busca, contagem e detalhe; toda leitura é auditada com apresentação mínima necessária.
- Mutação assistencial e o evento de auditoria correspondente confirmam atomicamente; se a auditoria estiver em outro serviço, use outbox transacional.

## 3. Educação

- Matrícula/vínculo escolar é temporal: escola, rede, ano/série, turma, turno, situação e intervalo de vigência. Transferência encerra o vínculo anterior e cria outro.
- Contatos educacionais usam tipos estáveis: reunião/ida à escola, ligação recebida, ligação realizada, atendimento, ocorrência, matrícula/transferência e outro.
- Registro exige data/hora, pessoa, escola quando aplicável, participantes, motivo/relato, encaminhamentos e autor.
- Tarefa educacional pode referenciar o contato e a agenda, mas não é contada como contato/atendimento concluído até haver desfecho explícito.

## 4. Agenda

- Criador, responsável, participantes e escopo de visibilidade são conceitos separados.
- `DEC-02` definiu agenda assistencial compartilhada entre `equipe_tecnica` e `administradora`; não existe evento assistencial privado entre essas contas. “Minha agenda”, pessoa, setor, equipe e responsável são filtros/atribuições do mesmo conjunto autorizado, não barreiras de acesso.
- Na fase atual, contas ativas `equipe_tecnica` e `administradora` possuem o mesmo acesso funcional assistencial aprovado. Policies continuam separadas por ação para gestão de contas, consulta de auditoria, download/exportação, cancelamento, segredos e infraestrutura; somente a administradora gere contas/acessos e consulta a auditoria funcional minimizada em modo somente leitura.
- Consulte por intervalo visível e paginação; não carregue histórico completo.
- Reatribuição por férias/afastamento muda responsável, não autoria nem histórico.

## 5. Atendimentos, encaminhamentos e indicadores

- Toda ocorrência contabilizável possui área, tipo, data/hora, pessoa, unidade, setor, responsáveis, status, versão da taxonomia e origem rastreável.
- Taxonomia v0 de `DEC-03`: atendimento é interação/serviço concluído; encaminhamento é ação dirigida a destino/finalidade; retorno é resposta, resultado ou contato significativo vinculado ao fato original; acompanhamento é atendimento concluído de seguimento. Agenda/lembrete/tarefa planejada não contam; planejado, cancelado e falta são métricas operacionais separadas.
- Áreas v0 usam códigos estáveis: `saude`, `educacao_escola`, `psicologia`, `servico_social` e `reaproximacao_familiar`. Alteração futura cria nova versão da taxonomia e não reescreve período já fechado.
- Relatórios mostram ocorrências e pessoas únicas. A data real do fato em `America/Sao_Paulo` define o período. Retorno com interação concluída pode contar como atendimento, mas não cria segundo encaminhamento.
- Encaminhamento possui destino, finalidade, data, responsável, prazo, situação e desfecho. Um registro canônico não é contado novamente por extensão específica, retry, duplo clique ou retificação.
- Painel e exportação usam o mesmo serviço de consulta. Todo agregado deve permitir drill-down autorizado até os registros que o compõem.
- Período usa regra explícita de inclusão de limites e fuso. Fechamento de período é imutável; correção exige reabertura auditada.
- Prefira agregado quando a finalidade não exige nomes. Exportação nominativa requer permissão e auditoria específicas.

## 6. Documentos, ofícios e PDFs

- Documento começa como rascunho editável. Finalização cria versão imutável com conteúdo, autor, profissionais identificados, destinatário, número, data, hash e timestamps.
- Retificação/cancelamento cria evento ou nova versão ligada à anterior; não substitui PDF emitido.
- Número de ofício vem de sequência central, transacional e única por unidade/ano. Reserva, cancelamento e número manual precisam histórico; nunca use `MAX + 1` sem proteção.
- Destinatário é opcional e estruturado: instituição/órgão, pessoa, cargo/função, tratamento e complemento. O documento salva snapshot.
- Conforme `DEC-04`, selecione de 1 a 4 profissionais ativos para blocos de identificação e preserve nome, cargo/função, conselho + número de registro quando aplicável, usuário e ordem como snapshot. Ausência/férias permite qualquer composição válida, inclusive uma pessoa. O bloco não recebe rótulo nem alegação de assinatura, autoria criptográfica ou validade jurídica.
- Foto no PIA é opção explícita do documento ou regra aprovada; ausência/erro não quebra geração. Respeite proporção, orientação e acesso privado.
- Renderização deve suportar português, múltiplas páginas e 1–4 blocos de identificação sem sobreposição.

## 7. Concorrência e consistência

- Operações compostas usam transação; invariantes relevantes também existem como constraint/índice no PostgreSQL.
- Use locking/controle otimista para número de ofício, mudança de situação, versão de medicação e fechamento de relatório.
- Jobs, webhooks e ações sujeitas a retry recebem chave de idempotência ou chave natural adequada.
- Falha entre banco e object storage deve ser reconciliável; registre estado de upload/quarentena em vez de alegar sucesso parcial.
- Timestamps de eventos são UTC. Horários recorrentes preservam horário civil e timezone aprovados; não converta uma recorrência isolada, como “08:00”, em instante UTC.

## 8. Gmail, propostas de prazo e notificações

- E-mail é entrada não confiável e indício, nunca autoridade. O MVP extrai deterministicamente somente datas literais e classifica `audiencia_compromisso`, `prazo` ou `mera_mencao`; data relativa fica `ambigua` e não é calculada. LLM permanece fora da fase atual.
- Toda proposta nasce `pendente_de_revisao`. Nenhuma agenda ou notificação existe antes da aprovação de técnica ou administradora autorizada.
- Antes da aprovação, usuária autorizada pode corrigir data/hora, classificação, responsável, participantes/destinatários e lembrete. O padrão é dia civil anterior às 09:00 em `America/Sao_Paulo`, não 24 horas antes.
- Se o horário padrão já passou, exija escolha explícita e não envie retroativamente. O MVP não ajusta fim de semana/feriado automaticamente; a decisão humana fica registrada.
- Após aprovação, qualquer mudança cria nova revisão append-only com justificativa e preserva a versão anterior. Jobs antigos são invalidados/cancelados e o reagendamento é idempotente; nunca sobrescreva compromisso silenciosamente.
- Audite criar, corrigir, aprovar, rejeitar, cancelar, reagendar e notificar com ator, alvo, resultado, UTC, campos alterados, justificativa, revision ID e correlation ID, sem copiar corpo do e-mail, narrativa, PII ou valor sensível para log técnico.
