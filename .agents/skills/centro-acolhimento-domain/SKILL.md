---
name: centro-acolhimento-domain
description: Aplica as regras de negócio, privacidade, segurança e testes do Centro de Acolhimento. Use em qualquer tarefa sobre cadastro e ingresso de criança/adolescente, episódio ou situação de acolhimento, movimentação/evasão/internação/desacolhimento, PIA/ofício/PDF/assinatura, foto/anexo, Saúde/medicação/exame, Educação/escola, atendimento/encaminhamento, agenda, relatório/indicador, usuário/permissão/auditoria ou LGPD.
---

# Domínio do Centro de Acolhimento

## Objetivo

Transformar requisitos assistenciais em mudanças rastreáveis sem perder histórico nem expor dados de crianças e adolescentes. Esta skill complementa `TODO.md`, `AGENTS.md` e `RTK.md`.

## Fluxo obrigatório

1. Identifique o `task_id` de `TODO.md`, critérios de aceite e decisões `DEC-*` relacionadas.
2. Leia [regras de negócio](references/business-rules.md) para toda mudança de domínio.
3. Leia [segurança e LGPD](references/security-lgpd.md) quando houver identidade, permissão, dado sensível, arquivo, documento, busca, exportação, log, integração ou exclusão.
4. Leia [matriz de testes](references/testing-matrix.md) antes de implementar ou testar.
5. Liste invariantes, transições, atores, dados, auditoria e falhas antes de escolher tabelas/endpoints/componentes.
6. Se uma decisão pendente alterar semântica, visibilidade, retenção, contagem ou valor jurídico, pare e faça uma pergunta objetiva. Não invente política institucional.
7. Implemente o menor corte vertical completo e registre o handoff de `RTK.md`.

## Invariantes globais

- A pessoa existe independentemente de seus episódios de acolhimento. Nunca use o status da pessoa como substituto do histórico de entradas, saídas e ausências.
- Fatos assistenciais, versões de medicação, movimentações, auditoria e documentos finalizados são append-only. Retificação aponta para o registro anterior e preserva autoria/data.
- Toda leitura e mutação usa autorização no servidor. Escopo permitido é aplicado na consulta, não filtrado depois nem apenas ocultado na interface.
- Contagens vêm de eventos estruturados e taxonomia versionada. Texto livre nunca é a fonte única de indicador.
- Arquivos e fotos são privados e o acesso é auditável. PDF autorizado não transforma o arquivo-fonte em público.
- Valores exibidos em documento final são snapshots; mudar cadastro depois não reescreve o passado.
- Exclusão física de dado assistencial exige política de retenção aprovada. O padrão é inativação, encerramento, cancelamento ou retificação auditada.
- Nunca use dados reais em desenvolvimento, teste, prompt, log ou artefato de CI.

## Limites de interpretação

- `acolhido/desacolhido` descreve episódio; `na_unidade/evadido/internado` descreve situação operacional durante episódio aberto, conforme validação de `DEC-01`.
- “Assinatura” significa, até decisão jurídica em `DEC-04`, seleção de signatários e bloco nominal no PDF; não alegue assinatura eletrônica/digital ou validade jurídica.
- O módulo Saúde registra fatos operacionais e informações fornecidas por profissionais. Não prescreve, calcula dose, diagnostica ou recomenda tratamento.
- Dados de saúde sexual/reprodutiva exigem necessidade de conhecimento específica; gênero/sexo não é autorização.
- Agenda não substitui atendimento realizado. Evento agendado só vira fato contabilizável mediante registro/desfecho explícito.

## Resultado esperado do agente

Inclua no handoff: `task_id`, regras aplicadas, decisões pendentes, matriz de autorização, eventos de auditoria, estratégia de histórico/migração, testes positivos/negativos e riscos residuais. Sem esses itens, a análise de domínio está incompleta.
