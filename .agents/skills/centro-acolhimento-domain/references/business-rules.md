# Regras de negócio

## 1. Identidade, ingresso e acolhimento

- `crianca/adolescente` representa a pessoa; `acolhimento` representa um episódio. Uma pessoa pode possuir vários episódios, mas no máximo um episódio aberto por unidade, salvo regra institucional futura explicitamente aprovada.
- Abrir episódio exige data/hora, unidade, motivo/fundamento, processo quando disponível, origem do encaminhamento e ator. Órgão condutor e pessoa condutora são campos distintos; “Outro” exige complemento.
- Situação operacional recomendada para episódio aberto: `na_unidade`, `evadido`, `internado`. `desacolhido` encerra episódio e exige data, motivo/destino e responsável.
- Transições preliminares: entrada → na unidade; na unidade → evadido/internado/desacolhido; evadido/internado → na unidade/desacolhido. Transição inválida deve falhar no domínio, não apenas na UI. A coordenação precisa aprovar `DEC-01` antes de produção.
- Cada transição cria uma movimentação com início, eventual fim, local/destino, observação, ator e timestamps. Nunca reescreva a movimentação anterior para aparentar que não ocorreu.
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
- Regra recomendada até `DEC-02`: equipe como padrão, com filtros pessoa/setor/equipe. Informação assistencial relevante não deve ser escondida em evento “pessoal”.
- Autorização de leitura/edição considera perfil, unidade, setor, vínculo e sensibilidade do registro vinculado.
- Consulte por intervalo visível e paginação; não carregue histórico completo.
- Reatribuição por férias/afastamento muda responsável, não autoria nem histórico.

## 5. Atendimentos, encaminhamentos e indicadores

- Toda ocorrência contabilizável possui área, tipo, data/hora, pessoa, unidade, setor, responsáveis, status e origem rastreável.
- Encaminhamento possui destino, finalidade, data, responsável, prazo, situação e desfecho. Retry/duplo clique não cria duplicata.
- Definições de “atendimento”, “encaminhamento”, “retorno”, pessoa única e dupla contagem dependem de taxonomia aprovada em `DEC-03`.
- Painel e exportação usam o mesmo serviço de consulta. Todo agregado deve permitir drill-down autorizado até os registros que o compõem.
- Período usa regra explícita de inclusão de limites e fuso. Fechamento de período é imutável; correção exige reabertura auditada.
- Prefira agregado quando a finalidade não exige nomes. Exportação nominativa requer permissão e auditoria específicas.

## 6. Documentos, ofícios e PDFs

- Documento começa como rascunho editável. Finalização cria versão imutável com conteúdo, autor, signatários, destinatário, número, data, hash e timestamps.
- Retificação/cancelamento cria evento ou nova versão ligada à anterior; não substitui PDF emitido.
- Número de ofício vem de sequência central, transacional e única por unidade/ano. Reserva, cancelamento e número manual precisam histórico; nunca use `MAX + 1` sem proteção.
- Destinatário é opcional e estruturado: instituição/órgão, pessoa, cargo/função, tratamento e complemento. O documento salva snapshot.
- Se `DEC-04` adotar apenas bloco nominal, selecione de 1 a 4 profissionais ativos e preserve nome, cargo, usuário e ordem como snapshot. Ausência/férias permite qualquer composição válida, inclusive uma pessoa.
- Foto no PIA é opção explícita do documento ou regra aprovada; ausência/erro não quebra geração. Respeite proporção, orientação e acesso privado.
- Renderização deve suportar português, múltiplas páginas e assinaturas sem sobreposição.

## 7. Concorrência e consistência

- Operações compostas usam transação; invariantes relevantes também existem como constraint/índice no PostgreSQL.
- Use locking/controle otimista para número de ofício, mudança de situação, versão de medicação e fechamento de relatório.
- Jobs, webhooks e ações sujeitas a retry recebem chave de idempotência ou chave natural adequada.
- Falha entre banco e object storage deve ser reconciliável; registre estado de upload/quarentena em vez de alegar sucesso parcial.
- Timestamps de eventos são UTC. Horários recorrentes preservam horário civil e timezone aprovados; não converta uma recorrência isolada, como “08:00”, em instante UTC.
