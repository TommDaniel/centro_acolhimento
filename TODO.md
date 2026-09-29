# Plano de evolução do Centro de Acolhimento

> PRD enxuto + backlog técnico. Documento vivo, criado em 31/07/2026 e reorganizado em 25/09/2026 para priorizar um protótipo funcional de validação com as técnicas, preservando o backlog de produção e a distinção entre decisão, implementação e autorização para dados reais.

## 1. Como usar este documento

- Marcar uma tarefa como concluída somente quando todos os seus critérios de aceite e a definição de pronto forem atendidos.
- Prioridade **P0**: bloqueia o uso com dados reais ou cria risco grave de segurança/perda de dados.
- Prioridade **P1**: necessidade operacional explícita dos usuários.
- Prioridade **P2**: melhora confiabilidade, escala, manutenção ou experiência.
- Tamanhos `P/M/G/XG` são relativos e devem ser reestimados após as decisões de produto e o desenho do banco.
- Os identificadores (`SEG-01`, `SAU-02` etc.) devem ser usados em issues, commits e pull requests.
- Os marcos `PROT-01*` agrupam cortes de tarefas já existentes para apresentação e validação; concluí-los **não** conclui automaticamente as tarefas funcionais referenciadas nem autoriza dados reais.

## 2. Resultado esperado

Transformar a POC em um sistema de produção seguro e auditável que:

1. mantenha uma ficha única de cada criança/adolescente e o histórico de seus acolhimentos;
2. registre Saúde, Educação, atendimentos e encaminhamentos como dados estruturados;
3. gere documentos corretos, com destinatários e identificação flexível dos profissionais responsáveis;
4. entregue indicadores anuais sem controles paralelos manuais;
5. proteja dados pessoais e dados sensíveis conforme a LGPD, com especial atenção ao melhor interesse de crianças e adolescentes;
6. possa crescer em usuários, registros e arquivos sem perda de dados ou reescrita prematura da aplicação; uma segunda unidade ou organização exige reabrir `DEC-05` antes da expansão;
7. use contas individuais, autenticação forte e ciclo de acesso revogável, sem transformar uma caixa de e-mail compartilhada em identidade de usuário;
8. transforme mensagens institucionais em **propostas** rastreáveis de prazos e agenda, sempre sujeitas a revisão humana antes de produzir compromisso operacional; e
9. ofereça instalação PWA e lembretes no aplicativo/Web Push com minimização de dados, sem expor PII ou informação assistencial na tela bloqueada.

### Contexto institucional confirmado

- A aplicação atende uma **organização de acolhimento institucional** — descrita pelo solicitante também como “orfanato” — que opera uma única unidade/local/complexo físico.
- Dentro desse complexo existem várias casas destinadas à moradia e ao cuidado cotidiano de crianças e adolescentes. Essas casas são localizações internas da unidade única; não são unidades operacionais, organizações ou tenants.
- O trabalho institucional envolve equipe de cuidado cotidiano, equipe técnica e pessoal jurídico/administrativo. Na fase inicial do sistema haverá somente contas individuais de `equipe_tecnica` e `administradora`, conforme `DEC-02/SEG-02`; a matriz inicial de Policies/Gates e testes negativos foi entregue em `SEG-02A`, sem antecipar novos papéis.
- A organização responde a juízes e demais atores/órgãos do sistema de Justiça por meio dos cinco documentos funcionais aprovados em `DEC-06`: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais. O sistema apoia elaboração, rastreabilidade e prazos, mas não substitui revisão profissional nem determina automaticamente efeitos jurídicos.
- Ainda é decisão de produto em `DEC-01` se a alocação e a transferência entre casas precisam de registro estruturado e histórico próprio. Até a aprovação, “casa” não deve virar apenas um campo atual sobrescrevível nem ser confundida com a unidade.
- O panorama consolidado de arquitetura, módulos, dependências, riscos, roadmap e primeiros cortes executáveis está no [blueprint do projeto](docs/architecture/project-blueprint.md). `TODO.md` continua sendo a fonte de verdade dos estados e prioridades.

### Meta imediata — protótipo da rotina técnica (`PROT-01`)

- [ ] **PROT-01 — Apresentar uma jornada funcional às técnicas e validar o escopo antes de aprofundar a construção** (P1, G; agrega os cortes abaixo; não é aceite de produção)
  - Reutilizar a POC navegável, os formulários/PDFs, agenda e busca já existentes, o PostgreSQL de desenvolvimento/CI, o contexto institucional único, as Policies/auditoria iniciais e `IAM-02A`. Não reconstruir essas telas sem evidência de necessidade.
  - **Primeira apresentação funcional:** com contas individuais e dados explicitamente fictícios, uma técnica encontra a ficha, registra/consulta o ingresso e a situação, usa a agenda compartilhada, preenche/reabre os cinco documentos e visualiza PDFs demonstrativos. Outra técnica autorizada encontra o mesmo caso, a fonte da informação e quem a atualizou. Campos sem implementação devem ser identificados como prévia, nunca simulados como persistidos.
  - **Primeira apresentação visual:** Saúde, Educação e indicadores anuais mostram navegação, campos e exemplos sintéticos para colher feedback, **sem** alegar que lançamentos, medicações, exames ou contagens já são funcionais. `SAU-*`, `EDU-*`, `ATE-*` e `BI-*` continuam obrigatórios para a etapa funcional posterior, conforme o retorno das técnicas.
  - **Prova do protótipo:** PHPUnit/integração PostgreSQL para ingresso, situações, busca e autorização negativa; Playwright desktop/mobile percorrendo ficha → agenda → documentos; inspeção visual de PDFs com/sem retrato fictício e 1–4 profissionais quando esses cortes estiverem presentes. Campos de prévia não podem salvar nem entrar em contagens. Nenhum teste ou apresentação usa dado, foto ou documento real.
  - [ ] **PROT-01A — Preparar demonstração segura e reaproveitar a POC** (`IAM-02A`, `SEG-02A/03A`, `QA-01`, corte mínimo de `ARQ-02/SEG-05` se houver foto)
    - Preparar ambiente isolado e contas/fichas/arquivos **somente sintéticos**; confirmar login TOTP, permissões negativas, navegação móvel/teclado e ausência de vazamento em busca/PDF/log. Retrato de demonstração é fictício e privado, sem URL permanente; se esse controle ainda não existir, não demonstrar upload/foto como funcional.
    - [x] **PROT-01A1 — Proteger a busca inicial da POC** (`BE-01`, `SEG-02/03`): envio do termo por POST, paginação por referência opaca vinculada à sessão (até cinco buscas, dez minutos), resposta sem cache e política de referência no documento; resultados Inertia limitados aos campos do cartão e auditoria de execução sem termo, resultado ou identificador pesquisado. GET e POST serializam o estado da sessão para preservar buscas simultâneas. Testes PostgreSQL e Playwright desktop/mobile cobrem acesso negado, expiração, isolamento e concorrência de sessão, URL/histórico sem termo e navegação. O GET legado com `q` não executa a busca e redireciona sem ecoar o valor.
    - Ainda falta configurar o servidor web futuro para não registrar query strings em access logs: uma requisição GET antiga ou externa pode ser registrada antes de chegar ao redirecionamento Laravel. Manter `SESSION_ENCRYPT=true` e um cache com locks compartilhados entre workers (Redis planejado) nos ambientes de demonstração e produção. `PROT-01A1` isoladamente não liberou fotos/anexos, PDF ou dados reais; `PROT-01A` permanece aberto.
    - [x] **PROT-01A2 — Retrato privado para a demonstração** (`CAD-03`, `DOC-01`, corte local de `ARQ-02/SEG-05`): cadastro de retrato opcional; havendo retrato privado válido, o PIA demonstrativo o inclui automaticamente, conforme decisão do responsável em 27/09/2026. Novos retratos ficam no disco local privado, sem URL pública permanente: leitura autenticada/MFA/autorizada e auditada, validação real JPEG/PNG, correção EXIF e PDF com proporção limitada. Upload/download de anexos da ficha e do PIA estão temporariamente desativados inclusive no backend. Os três lançadores oficiais da demonstração (`composer run dev`, Compose `app` e `app-e2e`) recusam iniciar se `public/storage` existir; execução direta de `php artisan serve` não é um caminho validado. PostgreSQL: 201 testes passaram e 2 skips esperados; Playwright: 38/38 em desktop/celular; quatro PDFs sintéticos inspecionados. Arquivos públicos legados não foram migrados, apagados nem declarados seguros: antes de dados reais ainda são necessários inventário, cópia verificada, bloqueio `/storage/**` no servidor web, storage durável externo, antimalware, catálogo/reconciliação de versões e política de retenção. Este corte não conclui `ARQ-02`, `SEG-05`, `CAD-03` ou `DOC-01` completos nem libera dados reais.
  - [ ] **PROT-01B — Localizar, ingressar e acompanhar o acolhido** (`ACO-01/02/03`, `CAD-01/02/04`, `BE-01`, `DEC-01A/06C`)
    - Na “Nova criança”, cobrir os blocos da Ficha de ingresso adotada em `DEC-06C`, com obrigatoriedade provisória v0, incluindo escola/situação escolar e **órgão condutor separado da pessoa condutora**; reutilizar dados no PIA e na ficha de ingresso. Busca por nome/processo e informação/documento estruturado leva à ficha e mostra fonte, situação atual e atualização por pessoa/data. Filtros funcionais: Acolhidos, Desacolhidos, Evadidos, Internados e Todos; evasão/internação não encerram o acolhimento e o histórico de retorno permanece visível.
    - [x] **PROT-01B1 — Registrar episódios e movimentações atuais** (`ACO-01/03`, `DEC-01A/05`): separar pessoa de episódio, permitir ingresso explícito e a sequência na unidade → evasão → retorno → internação → retorno → desacolhimento, preservando movimentações imutáveis, autoria e horário. Exibir situação e linha do tempo na ficha; impedir alteração direta da situação pelo cadastro e proteger a unicidade de episódio aberto no PostgreSQL. Por decisão confirmada em 28/09/2026, não inventar horas, saída ou movimentos para cadastros legados com apenas data/status: mantê-los visíveis e identificados como informação anterior a conferir até reconciliação posterior. Não incluir ainda casas, escola, filtros finais ou busca ampliada; `ACO-01/02/03` e `PROT-01B` permanecem abertos até esses cortes.
      - Evidência de 29/09/2026: 231 testes PHP aprovados (5 skips esperados), 50/50 E2E em PostgreSQL e 4/4 cenários finais repetidos pelo QA em desktop/celular; QA e revisão sênior sem achados remanescentes. Dados anteriores continuam sujeitos a conferência humana e este corte não libera dados reais.
    - [x] **PROT-01B2 — Registrar informação escolar inicial e atualizações na ficha** (`CAD-01`, primeiro corte de `EDU-01`, `DEC-06C`): na “Nova criança”, permitir informar por seleção a situação escolar conhecida e, quando pertinente, escola, rede, matrícula, ano/série, turma e turno; ausência de informação não vira escola ou matrícula presumida. Guardar versões com fonte, data e autoria, mostrando a informação atual e o histórico na ficha e permitindo nova atualização sem apagar a anterior. Por decisão confirmada pelo responsável, a experiência do protótipo segue em duas etapas guiadas: identificação + escola em “Nova criança”, depois “Registrar ingresso” na ficha. O órgão e a pessoa condutora já são campos distintos nesse ingresso (`PROT-01B1`); não duplicá-los no cadastro da pessoa. A ficha de ingresso documental e o aproveitamento no PIA ficam no corte documental posterior, pois exigem snapshot/versionamento e validação das técnicas; `CAD-01` e `EDU-01` continuam abertos. Aceite: PostgreSQL, autorização negativa, auditoria, criação/atualização, celular e teclado verificados somente com dados fictícios.
      - Evidência de 29/09/2026: suíte oficial 256 testes aprovados/7 skips, concorrência PostgreSQL 2/2, E2E funcional 6/6 em desktop/celular e teclado 2/2; QA independente e revisão sênior em PASS. Nenhum dado real ou documento oficial foi usado; obrigatoriedade e taxonomia escolar seguem provisórias até validação das técnicas.
    - [x] **PROT-01B3 — Ampliar a busca protegida com contexto escolar atual e metadados demonstrativos** (`BE-01`, primeiro corte de `CAD-04`): mantendo termo em POST/referência opaca de sessão, localizar a ficha por identificação já autorizada, somente pela versão escolar atual e por tipo, título ou número de ofício de PIA, Visita técnica, Parecer e Pertences. Agrupar o resultado por ficha e exibir somente fonte lógica, escola/situação atual com sua atualização segura ou metadado documental mínimo com autoria/data exclusivamente de criação comprovada; nunca narrativa, item de pertence, conteúdo, caminho de arquivo ou chave de storage. O destino da ficha também recebe resposta `no-store`. Tipos documentais só casam por aliases completos, e `%`/`_` permanecem literais. Histórico/proveniência de atualização documental permanece fora deste corte em `CAD-04/DOC-06`. Este corte não pesquisa escola histórica, agenda, texto narrativo, arquivo ou prévias e não conclui `BE-01`, `CAD-04` ou `PROT-01B` completos.
      - Evidência de 29/09/2026: QA independente em PASS, com 24/24 testes centrais PostgreSQL (889 asserções), 14/14 unitários, 25 testes de regressão (2 skips condicionais esperados), concorrência dedicada 2/2 e E2E oficial da busca 6/6 em desktop/celular; Pint e build Vite aprovados. Revisão sênior final em PASS, sem achados remanescentes. Os cartões documentais mostram somente a autoria/data de criação comprovada; alterações em documentos mutáveis permanecem abertas em `CAD-04/DOC-06`. `ILIKE` cobre caixa no PostgreSQL, mas normalização livre sem acentos e índices medidos permanecem para `BE-01/BE-01B`. Somente dados sintéticos fictícios foram usados; este corte não libera dados reais.
  - [ ] **PROT-01C — Agenda compartilhada e cinco documentos preenchíveis** (`AGD-01/02`, `DOC-01/02/03/04`, `DEC-02/04/06C`)
    - Demonstrar criação/consulta/edição de compromisso com acolhido e responsável, filtros por pessoa/responsável e reatribuição sem apagar autoria. Demonstrar PIA do modelo mais completo, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega. Priorizar seleção/autopreenchimento; destinatário opcional no Parecer; 1–4 profissionais identificados de forma flexível no PDF, **sem alegar assinatura eletrônica**. Para concluir este corte, demonstrar o PIA com e sem retrato **fictício e privado** após `PROT-01A/DOC-01`; enquanto isso não estiver seguro, a foto fica explicitamente pendente, nunca exposta por URL pública. O Parecer mantém narrativa técnica guiada e pedido ao juiz; nenhum documento demonstrativo é enviado oficialmente.
  - [ ] **PROT-01D — Validar visualmente Saúde, Educação e informativo anual** (`SAU-01/02/03/04/05`, `EDU-01/02`, `ATE-01/02`, `BI-01`)
    - Mostrar às técnicas, com exemplos fictícios e indicação visível de **prévia não funcional**, os fluxos de UBS/UPA/Hospital/CAPS, acompanhante, encaminhamento, exames, medicação contínua e pontual, escola/matrícula, reuniões/ligações e números por área. Confirmar rótulos, campos, sequência de uso e o que deve vir primeiro na construção. Não inferir autorização para informação sexual/reprodutiva nem contar agenda como atendimento realizado.
  - [ ] **PROT-01E — Produzir vídeo didático da demonstração** (P1, M; depende de `PROT-01A/01B/01C/01D`)
    - Criar roteiro em português simples e vídeo de aproximadamente **5–10 minutos**, ajustando a duração para explicar com clareza sem acelerar telas. Percorrer busca/ficha/ingresso e situações, agenda, os cinco documentos/PDFs e as prévias de Saúde, Educação e informativo anual; mostrar também a experiência em celular. Iniciar com aviso de que pessoas, fotos e casos são fictícios; identificar na tela e na narração o que já funciona e o que é apenas prévia visual, sem simular salvamento, emissão oficial ou contagem.
    - Capturar a navegação reproduzível com o Playwright **fixado no `package-lock.json`**, pelo fluxo `npm run test:e2e`; editar o material para acrescentar narração clara, legendas sincronizadas em português, capítulos e destaque/zoom discreto no controle ou informação mencionada. Usar música de fundo suave com licença de uso verificada e crédito quando exigido, em volume que não prejudique a voz. Revisar leitura das legendas, contraste, ritmo e áudio em desktop e celular.
    - Usar exclusivamente ambiente, contas, fotos e documentos sintéticos. Antes de gravar e compartilhar, ocultar senhas, QR/segredo TOTP, códigos de recuperação, tokens, URLs sensíveis e qualquer notificação/dado real; revisar quadros, áudio, legendas, metadados e artefatos gerados. Distribuir somente por canal privado com acesso às pessoas convidadas após revisão, nunca por link público ou não listado tratado como controle de acesso. Guardar versão/data do vídeo e do roteiro para que a gravação não seja confundida com o estado futuro do sistema.
    - Aceite: o vídeo cobre os fluxos previstos, tem linguagem sem jargões, legendas fiéis, destaques sincronizados e música licenciada discreta; revisão confirma ausência de dados reais/segredos e distinção inequívoca entre funcionalidade e prévia. A gravação não substitui testes E2E nem homologação prática.
  - [ ] **PROT-01F — Coletar devolutivas das técnicas e registrar mudanças de escopo** (`DEC-06`, `QA-02`, `PROT-01E`)
    - Enviar vídeo e perguntas curtas por tela/documento: o que está correto, falta ou deve mudar, inclusive obrigatoriedade dos campos, foto no PIA e prioridade de Saúde/Educação/indicadores. Fazer sessão de dúvidas se necessário e registrar respostas atribuídas por fluxo, sem incluir caso real na devolutiva. Classificar cada retorno como defeito, refinamento ou novo escopo, ligar ao ID do backlog, estimar impacto e submeter decisões/alterações contratuais a aprovação das partes. Feedback por vídeo não equivale a técnica operar o sistema; programar teste prático posterior com dados fictícios em desktop/celular (`QA-02`). Feedback não altera automaticamente prazo nem requisito aprovado.
  - **Aceite de `PROT-01`:** `PROT-01A–E` demonstrados e devolutivas de `PROT-01F` rastreadas; o protótipo não é confundido com homologação final, deploy de produção ou liberação de dados reais. `DOC-05/06`, storage/segurança completos, módulos de Saúde/Educação/BI persistidos, Gmail/PWA e gates de produção continuam em suas tarefas próprias quando não concluídos.

**Referência de planejamento contratual informada pelo responsável:** prazo global estimado de **20–26 semanas** após as condições necessárias ao início; Entendimento e organização **2–3**, Desenho e protótipo **2–3**, Construção **7–9**, Validação e documentos **5–6**, Testes com a instituição **3–4**, Publicação e treinamento **1–2** semanas. `PROT-01` concentra a prioridade atual de desenho/validação e não altera essas cláusulas, não fixa uma data de início nem presume aceite da fase. Conforme as cláusulas informadas: ajuste por alterações/validações depende de comum acordo; atraso da contratante no fornecimento de informações, documentos ou aprovações pode prorrogar o cronograma; atraso de pagamento suspende temporariamente as atividades. Registrar esses fatos e seus impactos, sem recalcular automaticamente prazo ou escopo neste backlog. O encaixe das faixas no prazo global precisa constar de cronograma acordado, não de soma automática.

## 3. Estado atual verificado no código

### Já existe, mas precisa ser confirmado pelos usuários

- A foto pode ser enviada no cadastro e o template atual do PDF do PIA tenta exibi-la em “Dados de identificação”. Isso funciona com arquivo local disponível, mas não é confiável no deploy atual e ainda precisa de teste visual/regressivo (`DOC-01`).
- A agenda da POC já tem calendário e visualização compartilhada entre contas autorizadas, mas ainda carrega histórico amplo, não explica suficientemente o compartilhamento nem oferece os filtros/responsáveis previstos em `AGD-01/02`. A autorização atual é a das Policies, não a antiga regra de edição por setor descrita no README.
- O PIA tem campos narrativos de Saúde e Educação, mas não há módulos/abas para registrar atendimentos, medicações, exames, escola ou contatos ao longo do tempo.
- A POC já tem cadastro/ficha, busca inicial por identificadores, agenda, PIA, visita, parecer/relatório e termo de pertences com PDFs. A fundação local já usa PostgreSQL, contexto único, acesso por Policies/auditoria em primeiro corte e `IAM-02A` com TOTP; isso é base para `PROT-01`, não prova que as melhorias pedidas estejam prontas ou implantadas ao cliente.

### Lacunas funcionais confirmadas

- O cadastro aceita apenas `acolhida` e `desligada`; não representa evasão, internação, retorno nem histórico de mudança.
- A ficha de ingresso não contém todos os dados escolares nem a pessoa/órgão que conduziu o acolhimento.
- Não existe levantamento anual estruturado de atendimentos e encaminhamentos.
- O Parecer do acolhido não tem destinatário/procedimento judicial estruturados nem seleção flexível dos profissionais identificados no rodapé.

### Novas necessidades em descoberta (`PRD-IAM-EMAIL-PWA`)

- A análise de identidade comparou Keycloak autogerido, IdP gerenciado e autenticação Laravel. `DEC-07` escolheu Laravel Fortify com TOTP para toda conta humana atual/futura na aplicação única, incluindo os quatro usuários iniciais; OIDC/Keycloak volta à decisão apenas pelos gatilhos do [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md).
- Foi solicitada integração com uma caixa institucional confirmada como **Gmail `@gmail.com` de consumidor** para identificar possíveis prazos e propor eventos. A senha não será compartilhada com usuários nem com a aplicação; a rota futura candidata é OAuth web consentido, com acesso offline/refresh token e menor escopo possível, ainda condicionada a `DEC-08` e PoC sintética.
- O código atual não contém integração Gmail/Google OAuth, processamento por Gmail API/Cloud Pub/Sub, PWA/service worker ou Web Push. Nenhuma dessas capacidades está autorizada para dados reais antes dos marcos e decisões deste documento.
- `DEC-11` decidiu não usar LLM nesta fase. `DEC-09` aprovou extração inicial determinística somente de datas literais, sempre como proposta sob revisão humana; qualquer LLM exige reabertura formal futura e novos gates.

### Bloqueadores para dados reais

- O README identifica a aplicação como POC sem segurança de produção e destinada apenas a dados fictícios.
- `ARQ-01A` isolou o bootstrap SQLite efêmero e o reset sintético exclusivamente no runtime legado `VERCEL`; local/CI usam PostgreSQL. A remoção ocorre no cutover Contabo. A implantação PostgreSQL definitiva, com TLS, roles mínimas, backup/PITR e restore, continua bloqueada em `ARQ-01/03/07`.
- Fotos e documentos são gravados no disco `public` e expostos por URL; não há autorização no download, expiração de link, varredura antimalware ou armazenamento durável no deploy atual.
- Cadastro público GET/POST: **CONTROLADO por `SEG-01A` em 26/08/2026**; as rotas estão ausentes e PHPUnit/E2E desktop e mobile provaram a negação direta e a preservação do login. `IAM-02A` entregou o primeiro login/TOTP e revogação básica; `SEG-01/IAM-02` permanecem abertos para recovery codes, step-up, re-enrollment, recuperação assistida e lifecycle completo.
- `SEG-02A` já introduziu papéis aprovados, Policies por recurso/ação atual e negação de exclusão física nas rotas cobertas. `SEG-02` permanece aberto para ações futuras, busca/contagens sensíveis e revisão completa; novos módulos não herdam permissão somente por aparecerem na interface.
- `SEG-03A` já introduziu auditoria append-only e autoria/data em primeiro corte. Busca, demais documentos, agenda, PDF/download/exportação e histórico funcional versionado ainda exigem cobertura em `SEG-03`; não alegar trilha completa.
- Os testes de PostgreSQL, autorização/MFA e E2E de login existem, mas os fluxos de ingresso, situação, busca abrangente, documentos/PDF visual, Saúde/Educação e indicadores ainda carecem de testes de domínio e homologação (`QA-01/02`).
- O diretório local não versionado `docs/formatacoes` contém seis PDFs oficiais/28 páginas, inclusive exemplos preenchidos com dados reais e um PIA escaneado sem camada de texto. Em 26/08/2026 houve autorização explícita para inspeção local e sanitizada de estrutura/campos; nenhum valor foi transcrito e o resultado está em [`docs/document-field-matrix.md`](docs/document-field-matrix.md). Os originais e renderizações não podem ser copiados, versionados nem usados em teste/prompt/CI; sanitização, retenção e eventual descarte continuam dependentes de `LGPD-01/02/03`.

**Regra de lançamento:** não inserir dados reais antes de concluir o marco P0, inclusive o corte bloqueante de capacidade/continuidade de `ARQ-07`, testar restauração integral e aprovar formalmente o go-live.

## 4. Decisões de produto

- [ ] **DEC-01 — Definir estados e alocação entre casas** (P1, P; situações aprovadas, casas pendentes)
  - [x] **DEC-01A — Definir evasão e internação durante episódio aberto** (P1, P; decisão confirmada em 10/09/2026)
    - `evadido`: pessoa com episódio de acolhimento aberto que fugiu ou está desaparecida. A evasão não encerra nem substitui o episódio.
    - `internado`: pessoa com episódio de acolhimento aberto que está temporariamente hospitalizada por situação de saúde. A internação não encerra nem substitui o episódio.
    - `acolhido` deriva de episódio aberto; sua situação operacional vigente é `na_unidade`, `evadido` ou `internado`. `desacolhido` significa episódio encerrado por decisão/ato próprio, nunca consequência automática de evasão ou internação.
    - Evasão, internação e retorno geram movimentações append-only com autoria e data/hora. O retorno cria nova movimentação e restabelece `na_unidade`; não apaga nem sobrescreve a ausência anterior.
  - Continua pendente decidir se cada pessoa possui alocação em uma casa interna e se transferências entre casas exigem período, motivo, ator e histórico append-only. Casa é localização interna, nunca unidade/tenant.
  - Aceite restante: coordenação decide necessidade ou dispensa explícita do histórico entre casas e valida exemplos sintéticos de alocação/transferência. A implementação de episódios, transições e constraints permanece em `ACO-01/02/03`.

- [x] **DEC-02 — Definir agenda compartilhada na fase inicial** (P1, P; decisão confirmada em 10/09/2026)
  - A agenda é compartilhada entre as contas de `equipe_tecnica` e `administradora`. Não existe evento assistencial privado entre elas; “Minha agenda” e outros filtros são somente visões do mesmo conjunto autorizado, não barreiras de acesso.
  - Na fase inicial, ambas possuem o mesmo acesso funcional assistencial de leitura, criação, edição, finalização e retificação nos recursos aprovados. Somente a administradora gerencia contas e acessos.
  - Técnicas não recebem administração de usuários, auditoria operacional, segredos ou infraestrutura. Histórico funcional, auditoria e controles de segurança são append-only e não podem ser editados ou apagados por técnicas nem administradoras; a visualização operacional da auditoria ainda depende de `SEG-02/03`.
  - Aceite decisório concluído; implementação, Policies/Gates, query scoping e testes negativos permanecem em `AGD-01/02`, `IAM-03` e `SEG-02/03`.

- [x] **DEC-03 — Fechar a taxonomia v0 dos indicadores anuais** (P1, M; decisão confirmada em 16/09/2026)
  - `atendimento realizado` é uma interação ou serviço efetivamente concluído; `encaminhamento` é uma ação dirigida a destino e finalidade registrados; `retorno` é a resposta, o resultado ou um contato significativo ligado ao fato original, sem criar outro encaminhamento; `acompanhamento` é atendimento concluído de seguimento.
  - Um retorno também conta como atendimento quando houve interação concluída, mas continua vinculado ao encaminhamento/fato original e não vira um segundo encaminhamento. Agenda, lembrete ou tarefa planejada não contam como realização; planejado, cancelado e falta aparecem somente em métricas operacionais separadas.
  - O relatório apresenta separadamente **ocorrências** e **pessoas únicas**. A data/hora real do fato, apresentada em `America/Sao_Paulo`, define o período. As áreas v0 possuem códigos estáveis e taxonomia versionada: `saude` (Saúde), `educacao_escola` (Educação/Escola), `psicologia` (Psicologia), `servico_social` (Serviço Social) e `reaproximacao_familiar` (Reaproximação Familiar).
  - Um registro canônico não pode ser contado outra vez por extensão específica de Saúde/Educação, retry, duplo clique ou retificação. Mudança futura da taxonomia cria nova versão e não reescreve relatório anual já fechado.
  - Exemplo exclusivamente sintético: duas consultas concluídas e uma reunião escolar da mesma pessoa fictícia produzem `3 ocorrências` e `1 pessoa única`; se uma consulta também gerou um encaminhamento e depois houve retorno concluído, o retorno pode somar uma ocorrência, mas continua existindo apenas `1 encaminhamento`. Uma agenda cancelada produz `0 atendimentos realizados` e aparece apenas em cancelados.
  - Aceite decisório concluído; implementação, dicionário técnico, fixtures de totais conhecidos, fechamento e validação da diretoria permanecem em `ATE-01/02`, `BI-01/02/03` e `QA-01`.

- [x] **DEC-04 — Definir identificação profissional, sem assinatura eletrônica/digital** (P1, P; decisão confirmada em 10/09/2026)
  - A fase atual não implementará assinatura eletrônica, assinatura digital, certificado, integração externa nem cadeia de assinatura.
  - O PDF baixado/enviado exibirá apenas rodapé de identificação de 1 a 4 profissionais ativos selecionados: nome, cargo/função e conselho profissional + número de registro quando aplicável (`CRESS`, `CRP` ou outro conselho configurado). Esse bloco não declara assinatura, autoria criptográfica, integridade certificada, validade jurídica ou aceite pelo destinatário.
  - A seleção e a ordem ficam livres por documento, inclusive uma pessoa durante férias/afastamentos. Ao finalizar, o documento salva snapshot dos profissionais; a versão final é imutável e qualquer correção gera nova versão vinculada, sem sobrescrever a anterior.
  - Uma modalidade de assinatura eletrônica/digital externa poderá ser reavaliada no futuro somente por nova decisão jurídica e técnica. GOV.BR não foi adotado nem integra o contrato atual.
  - Aceite decisório concluído; implementação e testes de PDF com 1–4 identificações permanecem em `DOC-03/04/06`.

- [x] **DEC-05 — Decidir escopo por unidade/organização** (P0, P)
  - Decisão: cada implantação atende uma única organização cliente e uma única unidade operacional. Não será criado SaaS multi-organização nem suporte multiunidade nesta fase.
  - Pessoa pertence à organização; cada episódio de acolhimento pertence à unidade única. Usuários também pertencem a essa unidade; papéis, setores e permissões permanecem sob `SEG-02`, sem relação M:N usuário–unidade.
  - Organização e unidade permanecem entidades/chaves explícitas mínimas no modelo de produção para contexto, auditoria, numeração documental e evolução segura. O backend resolve esse contexto e não aceita que o cliente escolha ou troque organização/unidade.
  - Migração será aditiva e roll-forward: criar organização e unidade únicas por configuração controlada, fazer backfill explícito, validar órfãos e somente então tornar chaves obrigatórias. Não hardcodear dados reais e não usar `migrate:fresh`.
  - Uma futura segunda unidade ou organização exige reabrir esta ADR e implementar isolamento, vínculos, autorização, migração e testes antes de ativá-la; o desenho atual não está preparado para multiunidade.
  - Aceite concluído como decisão documental, sem alegar implementação: contexto, invariantes, autorização, dados, migração, consequências e gatilhos estão registrados no [ADR 0004](docs/adr/0004-organizacao-e-unidade-unicas.md).

- [ ] **DEC-06 — Consolidar os cinco documentos oficiais e o dicionário de campos** (P1, P)
  - Foi localizada uma ficha em branco, mas ela ainda precisa ser confirmada como versão vigente e aprovada; não inferir obrigatoriedade nem regra somente de conversas ou exemplos preenchidos.
  - Classificar cada campo como obrigatório, opcional, sensível, fonte, responsável pela atualização e regra de retenção.
  - **Decisão humana confirmada em 28/08/2026:** existem exatamente cinco documentos funcionais adotados nesta fase: **PIA; Relatório de visita técnica; Parecer do acolhido; Ficha de ingresso; e Termo de Recebimento/Entrega de documentos e pertences pessoais**. Informação, Ofício genérico, Relatório Técnico genérico e Ofício de encaminhamento do PIA não são tipos funcionais novos; as evidências antigas permanecem somente como material histórico de descoberta/compatibilidade, fora do template adotado.
  - **PIA adotado:** usar como estrutura funcional o exemplo mais completo fornecido pelas técnicas, identificado apenas pela procedência institucional sanitizada como modelo da Prefeitura Municipal de Capão da Canoa. Essa decisão substitui o caráter de mera referência funcional provisória, mas não transforma o PDF preenchido em template versionado, não autoriza copiar valores reais e não conclui obrigatoriedade, finalidade, sensibilidade, acesso ou retenção campo a campo.
  - **Parecer do acolhido:** corresponde ao exemplo de Parecer fornecido pelo cliente. Relata tecnicamente a situação do acolhido e formula solicitação ao juiz; exige destinatário e procedimento judicial estruturados, fatos referenciados, narrativa profissional longa com começo–meio–fim, pedido e conclusão. Não criar um tipo adicional de Ofício nem persistir/versionar apelido, nome próprio ou correspondência informal usada durante a descoberta.
  - **Delegação confirmada em 10/09/2026:** a equipe do projeto está autorizada a implementar a obrigatoriedade empírica v0 de `DEC-06C` enquanto aguarda a classificação das técnicas. A v0 é provisória, versionada e alterável por nova versão após feedback; isso não bloqueia o primeiro corte funcional, mas também não transforma a classificação em definitiva.
  - A delegação não aprova finalidade, base legal, sensibilidade, acesso, retenção, template jurídico ou obrigatoriedade definitiva campo a campo. Essas decisões permanecem com equipe técnica/coordenação, controlador, encarregado e jurídico conforme o assunto.
  - Aceite: dicionário de dados aprovado pela equipe técnica e coordenação.
  - [x] **DEC-06A — Catalogar de forma sanitizada os documentos oficiais disponíveis** (P1, M; descoberta concluída, não conclui `DEC-06`)
    - O catálogo inicial inspecionou cinco PDFs/21 páginas localmente sob autorização explícita, sem copiar valores preenchidos. Com o complemento de `DEC-06B`, a matriz agora cobre seis PDFs/28 páginas. Campos, áreas, divergências, gaps e dependências estão em [`docs/document-field-matrix.md`](docs/document-field-matrix.md).
    - O catálogo registra como evidência histórica a comunicação narrativa e os pacotes compostos observados, sem promovê-los a tipos funcionais. A estrutura narrativa alimenta o Parecer do acolhido; a Ficha de ingresso, o PIA e o Termo alimentam seus documentos homônimos. O Relatório de visita técnica foi confirmado pelo cliente e recebe contrato próprio na matriz.
  - [x] **DEC-06B — Catalogar o PIA autônomo escaneado de forma sanitizada** (P1, P; descoberta concluída, não conclui `DEC-06`)
    - Escopo: inspecionar localmente as sete páginas, identificar somente rótulos, agrupamentos, tipos de campo, blocos técnicos e metadados de fechamento/verificação; cruzar a estrutura com as áreas canônicas e propor redução de digitação.
    - Limite: nenhum valor, nome, número, endereço, contato, narrativa, diagnóstico, assinatura ou credencial observados foi transcrito; o arquivo continua ignorado, intocado e não é promovido a template, taxonomia, fixture, regra jurídica ou prova de validade de assinatura.
    - Aceite concluído: a [matriz sanitizada](docs/document-field-matrix.md) registra a terceira evidência física contendo corpo PIA, a estrutura dos campos, os textos longos indispensáveis, o preenchimento determinístico por fatos referenciados e as decisões que permanecem em `DEC-04/06`.
  - [x] **DEC-06C — Adotar a estrutura funcional do PIA mais completo e a obrigatoriedade empírica v0** (P1, P; estrutura decidida, obrigatoriedade ainda provisória; não conclui `DEC-06`)
    - Decisão do responsável do projeto: adotar a estrutura funcional do PIA mais completo fornecido pelas técnicas, da Prefeitura Municipal de Capão da Canoa. Ele continua sendo um PIA, não um documento/tipo adicional; os demais exemplos são somente evidências para cruzamento e compatibilidade. O PDF preenchido não entra no Git nem vira template, fixture ou fonte de valores.
    - A [matriz sanitizada](docs/document-field-matrix.md) registra a regra v0 em quatro estados (`obrigatório`, `obrigatório como decisão/estado`, `condicional` e `opcional`), aplicada somente ao finalizar. Rascunhos podem permanecer incompletos e ausência nunca autoriza inventar conteúdo. `Não coletado por minimização/finalidade pendente` é decisão válida, não autoriza coleta e não cria pendência nem responsável por completar.
    - A identificação mínima v0 vincula inequivocamente o PIA à pessoa canônica, usa nome completo disponível e data de nascimento conhecida ou estado documentado de ausência/divergência; documento civil não é obrigatório por padrão. O episódio usa sua data de ingresso, enquanto data/local de emissão são derivados/configurados na finalização.
    - Esta decisão não aprova template jurídico, assinatura eletrônica/digital, retenção, acesso, finalidade nem obrigatoriedade definitiva. A equipe do projeto foi autorizada em 10/09/2026 a implementar esta v0 sem aguardar a classificação final; mudanças posteriores preservam a versão anterior. `DEC-06` permanece aberto até revisão formal de uma técnica e aprovação da equipe técnica/coordenação.
  - [x] **DEC-06D — Preparar checklist sanitizado para validação pelas técnicas** (P1, P; artefato concluído, não conclui `DEC-06`)
    - A [matriz sanitizada](docs/document-field-matrix.md) contém um checklist copiável/imprimível para exatamente os cinco documentos aprovados, classificando cada campo como obrigatório, opcional, condicional ou não utilizar, sem qualquer valor pessoal ou institucional preenchido.
    - Invariantes funcionais já aprovados ficam em tabela separada, sem opção `Não usar`; as técnicas confirmam apenas rótulo/exibição e fonte/vínculo. Isso preserva a finalidade mínima de cada documento sem antecipar a obrigatoriedade dos demais campos.
    - A classificação v0 continua sendo sugestão empírica. A marcação das técnicas alimentará o dicionário definitivo, mas não aprova por si só finalidade, base legal, acesso, retenção, assinatura ou template oficial.

- [x] **DEC-07 — Escolher a arquitetura de identidade e MFA** (P0, M)
  - Decisão: usar Laravel Fortify nativo, com contas individuais e MFA obrigatório por TOTP RFC 6238 para toda conta humana atual ou futura, incluindo os quatro usuários iniciais. Cadastro público e passkeys ficam desativados por decisão do produto.
  - Contexto decisório: uma única aplicação, sem previsão de SSO ou outra aplicação por dois anos, quatro usuários, orçamento total aproximado de R$ 60/mês e menor carga operacional favorecem Fortify sobre Keycloak autogerido ou IdP gerenciado.
  - O e-mail continua como atributo e canal de reset, não como identidade coletiva nem MFA primário. Recuperação por e-mail, se adotada, é apenas contingência controlada com verificação reforçada, TTL, uso único, rate limit, auditoria e revogação; códigos de recuperação são de uso único e a recuperação assistida por administrador é atribuível.
  - `Break-glass` nunca usa credencial coletiva: exige identidade individual atribuível, escopo mínimo, TTL curto, duplo controle para ativação, alerta imediato, auditoria append-only, rotação/revogação após o uso e exercício periódico com revisão.
  - Reavaliar OIDC/Keycloak somente diante de segunda aplicação/SSO, múltiplas organizações ou diretório corporativo, ou custo operacional menor comprovado. A caixa Gmail institucional nunca pode ser usada como login compartilhado.
  - Aceite concluído como decisão documental, sem alegar PoC executada: opções, custos/riscos, MFA, recuperação, lifecycle, contingência e saída estão registrados no [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md). A implementação e seus testes permanecem em `IAM-02` e `SEG-01`.

- [ ] **DEC-08 — Definir modelo da caixa Gmail e autorização Google** (P0, M)
  - [x] **DEC-08A — Confirmar o tipo da conta** (P0, P; decisão confirmada em 10/09/2026)
    - A caixa é uma conta Gmail de consumidor `@gmail.com`, não Google Workspace nem Google Group/Collaborative Inbox. A senha não será compartilhada com funcionários nem fornecida à aplicação.
    - A rota futura candidata é OAuth web consentido pelo titular, com acesso offline/refresh token revogável e o menor escopo possível. Service account e domain-wide delegation não se aplicam a esta conta e não serão usados.
  - Separar acesso humano do principal OAuth da aplicação; registrar titular/autorizador, escopos, armazenamento criptografado, rotação/reautorização, revogação e blast radius do refresh token.
  - `gmail.readonly` é escopo restrito. Compatibilidade com Google Workspace API User Data and Developer Policy/Limited Use, verificação OAuth e eventual avaliação de segurança são gates de go-live, inclusive para armazenamento/transmissão, acesso humano e cópias persistentes.
  - Label/remetente servem apenas para roteamento e priorização, nunca para autorização ou confiança. Definir governança, versionamento e auditoria de labels/domínios; avaliar DKIM, SPF, DMARC, domínio/origem e encaminhamentos como sinais, mantendo todo conteúdo não confiável.
  - Decidir quais metadados/conteúdos mínimos serão necessários, se trecho durável é permitido, retenção/exclusão e alternativa por referência/hash. Corpo e anexo não serão armazenados por padrão.
  - `DEC-08` principal permanece aberto até ADR e PoC exclusivamente sintética comprovarem OAuth web/offline, menor escopo, Limited Use, titularidade, revogação, retenção/exclusão e blast radius. A confirmação do tipo da conta não autoriza conexão ou dado real.

- [x] **DEC-09 — Definir semântica v0 de prazos e revisão humana** (P1, M; decisão confirmada em 16/09/2026)
  - O MVP extrai deterministicamente somente datas literais e classifica a ocorrência como `audiencia_compromisso`, `prazo` ou `mera_mencao`. Data relativa, como “10 dias”, nasce `ambigua` e não é calculada automaticamente. LLM permanece fora da fase atual.
  - Toda extração nasce `pendente_de_revisao`. E-mail é indício, nunca autoridade; remetente, label, assunto e demais sinais não concedem confiança ou autorização. Nenhuma agenda ou notificação existe antes da aprovação humana por técnica ou administradora autorizada.
  - Antes de aprovar, a usuária pode corrigir dinamicamente data/hora, classificação, responsável, participantes/destinatários e lembrete. O padrão aprovado é **dia civil anterior às 09:00**, em `America/Sao_Paulo`, e não “24 horas antes”. Se esse horário já tiver passado, o sistema sinaliza a condição e exige escolha explícita, sem envio retroativo presumido.
  - Fim de semana e feriado não são ajustados automaticamente no MVP. A usuária decide manter ou alterar a data/lembrete, e essa decisão fica registrada. Datas relativas, jurisdição, termo inicial, dias úteis/corridos, calendário e cutoff permanecem fora de cálculo automático.
  - Após aprovação, toda correção de data/hora, classificação, responsáveis/participantes/destinatários ou lembrete cria nova revisão append-only, exige justificativa e preserva a versão anterior. Jobs antigos são cancelados/invalidados e os novos são agendados de forma idempotente; nada é sobrescrito silenciosamente.
  - Criar, corrigir, aprovar, rejeitar, cancelar, reagendar e notificar registram ator, ação, alvo, resultado, horário UTC, campos alterados, justificativa, revision ID e correlation ID. O log técnico não recebe corpo do e-mail, narrativa, PII nem valores sensíveis; antes/depois fica apenas no histórico funcional versionado e autorizado.
  - Exemplo exclusivamente sintético: “audiência em 20/10/2026 às 14h” gera proposta literal pendente e, se aprovada sem alteração, lembrete para 19/10/2026 às 09:00; “responder em 10 dias” permanece ambígua e sem data calculada.
  - Aceite decisório concluído; implementação e provas de revisão, versionamento, autorização, idempotência e auditoria permanecem em `EML-04/05`, `AGD-01/02`, `PWA-02`, `SEG-02/03` e `QA-01`.

- [ ] **DEC-10 — Definir PWA, canais e política de notificações** (P1, M)
  - Aprovar navegadores/dispositivos suportados, instalação em desktop/mobile, experiência offline e fallback quando Web Push estiver indisponível. No iOS, Web Push exige instalação na Home Screen.
  - [x] **DEC-10A — Adotar opt-in contextual e push minimizado** (P1, P; decisão confirmada em 10/09/2026)
    - Web Push exige opt-in explícito. A permissão é solicitada somente em contexto compreensível de ativação, nunca no primeiro carregamento.
    - O push contém mensagem genérica sem PII, nome, processo, diagnóstico, prazo sensível ou conteúdo assistencial na tela bloqueada; detalhes são buscados somente após autenticação e autorização no aplicativo.
  - `DEC-09` definiu o lembrete funcional padrão como dia civil anterior às 09:00 em `America/Sao_Paulo`. Continuam pendentes navegadores/dispositivos suportados, quiet hours, fallback, lifecycle de subscriptions por dispositivo e revogação em logout, inativação, troca de vínculo ou perda do dispositivo.
  - Aceite: política aprovada cobre consentimento, conteúdo mínimo, expiração/revogação, fallback, acessibilidade e comportamento por plataforma.

- [x] **DEC-11 — Não usar LLM nesta fase** (P1, M; decisão confirmada em 10/09/2026)
  - A fase atual não transmite conteúdo ou derivação Gmail/assistencial a LLM e usa somente as regras determinísticas aprovadas em `DEC-09`, com revisão humana obrigatória.
  - LLM é apenas possibilidade futura, reaberta por decisão formal após RIPD, avaliação de fornecedor/suboperadores/região, DPA, garantia contratual de `no-training`, Limited Use, minimização, retenção/eliminação, plano de saída e testes exclusivamente sintéticos.
  - Em cenário futuro aprovado, uma LLM poderia comparar/classificar texto e propor data, categoria e confiança para revisão humana. Nunca poderá abrir URL, executar ferramenta/código, criar prazo/agenda ou enviar notificação diretamente.
  - E-mail continua entrada não confiável; prompt injection e exfiltração permanecem abuse cases. Groq é somente candidato de eventual PoC sintética, não fornecedor decidido ou autorizado.

## 5. Backlog funcional

### Épico A — Acolhimento, movimentações e filtros

- [ ] **ACO-01 — Criar episódios de acolhimento e histórico de movimentações** (P1, G; depende de `DEC-01A` e implementa `DEC-05`; `DEC-01` condiciona apenas o histórico opcional de casas)
  - Criar `acolhimentos` com entrada, motivo, processo, unidade, órgão/pessoa condutora e eventual saída/motivo.
  - Criar histórico append-only de movimentações com tipo, início, fim/retorno, local, observação, autor e timestamps.
  - Se `DEC-01` aprovar histórico de casa, modelar alocação/transferência interna sem transformar casa em unidade e sem sobrescrever a localização histórica; se dispensar, registrar a justificativa e não criar uma abstração prematura.
  - Migrar `data_acolhimento`, `motivo_acolhimento` e `status` atuais sem perder informação.
  - Não sobrescrever fatos antigos ao atualizar a situação atual.
  - Aceite: é possível registrar acolhimento → evasão → retorno → internação → retorno → desacolhimento, vendo toda a linha do tempo e o estado atual correto.

- [x] **ACO-02 — Adicionar filtros Acolhidos, Desacolhidos, Evadidos e Internados** (P1, M; depende de `ACO-01` e `DEC-01A`)
  - Exibir os quatro filtros pedidos, mais “Todos”, “A conferir” e “Sem ingresso”, com contagens mutuamente exclusivas. Conforme decisão do responsável, “Acolhidos” significa `na_unidade`; evasão e internação permanecem episódios abertos, mas aparecem apenas em seus filtros próprios. Se houver total de episódios abertos, mostrá-lo separadamente da contagem “Acolhidos”.
  - “A conferir” reúne cadastros anteriores sem episódio validado identificáveis pelos dados legados de ingresso; “Sem ingresso” reúne cadastros sem episódio e sem esses dados. Ambos aparecem também em “Todos”, sem inferir situação a partir do campo legado `status`. Havendo episódio, a classificação sempre deriva do episódio/movimentação vigente. Se um registro antigo tiver somente o valor padrão `status=acolhida`, não há marcador confiável para distingui-lo de um cadastro novo: tratá-lo como “Sem ingresso” e sinalizar a limitação para reconciliação futura, sem inventar proveniência por data de criação.
  - Preservar filtro permitido e paginação na URL. A busca conserva o termo somente no POST/sessão e usa a referência opaca existente na URL; nunca colocar nome, processo ou outro termo sensível em `q`, path, links de paginação ou referrer.
  - Definir rótulos inclusivos e consistentes em toda a interface.
  - Aceite: cada registro aparece em exatamente o filtro definido pelas regras de `DEC-01A`; contagens batem com consulta de banco e há teste automatizado para todas as transições. A decisão restante de casas em `DEC-01` não bloqueia esses filtros.

- [ ] **ACO-03 — Mostrar situação e histórico na ficha do acolhido** (P1, M)
  - Destacar situação atual, desde quando, local atual e último responsável pelo registro.
  - Exibir linha do tempo de entradas, saídas, evasões, internações e retornos.
  - Aceite: a equipe identifica a situação atual sem abrir observações de texto livre.

### Épico B — Ficha de ingresso e cadastro

- [ ] **CAD-01 — Completar a ficha de ingresso** (P1, G; primeiro corte depende de `DEC-06C` e `ACO-01`; homologação posterior em `DEC-06`)
  - Incluir, no mínimo, informações escolares vigentes e quem/qual órgão conduziu ao acolhimento.
  - Modelar escola/matrícula e órgão/pessoa como dados estruturados, com opção “Outro” e complemento; evitar um único campo narrativo para tudo.
  - Reaproveitar os dados no PIA e demais documentos, sem redigitação.
  - Aceite do primeiro corte: campos e obrigatoriedade empírica v0 seguem `DEC-06C`, são versionados e geram ficha conferível sem inventar ausências. A homologação das técnicas em `DEC-06` pode criar nova versão sem sobrescrever documentos anteriores.

- [ ] **CAD-02 — Melhorar preenchimento, validação e qualidade dos dados** (P2, M)
  - Organizar o formulário em etapas: identificação, documentos, responsáveis, processo, ingresso, Educação e revisão.
  - Aplicar máscaras e validação coerente para CPF, CNS/Cartão SUS, datas e número de processo, sem rejeitar exceções legítimas.
  - Detectar possíveis duplicidades antes de criar cadastro.
  - Exibir indicador de completude e campos pendentes.
  - Aceite: formulário funciona em celular e desktop, é navegável por teclado e não perde dados ao retornar uma etapa.

- [ ] **CAD-03 — Tratar foto com privacidade e ciclo de vida** (P0, M; depende de `ARQ-02`)
  - A foto referida em `CAD-03/DOC-01` é o retrato da criança/adolescente solicitado no feedback: fica no perfil privado e pode, mediante decisão, ser incluído no PIA; não é foto de documento, assinatura nem imagem escaneada do formulário.
  - Decisão provisória para o protótipo em 27/09/2026: cadastro do retrato opcional e inclusão automática no PIA demonstrativo quando houver retrato privado válido; revisar com as técnicas na validação.
  - Armazenar foto de forma privada, com autorização de acesso e miniaturas geradas fora da requisição.
  - Definir finalidade/base legal, quem pode visualizar, prazo de retenção e comportamento após desacolhimento.
  - Remover metadados EXIF e validar conteúdo real do arquivo.
  - Aceite: não existe URL pública permanente; acesso negado é testado; foto continua disponível nos fluxos autorizados e no PIA.

- [ ] **CAD-04 — Criar ficha unificada e passagem de caso** (P1, G; depende de `ACO-01/03`, `SEG-02/03` e módulos canônicos)
  - Corte `PROT-01B`: reunir na ficha apenas fatos já canônicos (ingresso, situação, agenda e documentos demonstrativos), com fonte/autoria/data; Saúde/Educação em prévia visual não são fatos persistidos. A tarefa completa continua aberta até os módulos e a passagem de caso estarem funcionais.
  - Centralizar na ficha do acolhido: resumo atual, situação e desde quando, casa/local interno se `DEC-01` aprovar, responsáveis, pendências/prazos, últimos fatos e linha do tempo unificada.
  - Organizar abas de Ingresso, Família, Saúde, Educação, Atendimentos/Encaminhamentos, Agenda, Documentos/Anexos e Histórico, sem duplicar fatos entre módulos.
  - Para cada documento, mostrar tipo, título/número, status, versão, data, responsável, localização lógica, pendência e última atualização. Nunca expor caminho físico, chave de object storage ou URL permanente.
  - Criar uma “passagem de caso”/resumo operacional derivada de fatos canônicos e pendências, com autor e data. Complemento humano necessário deve ser versionado e auditado; não substitui fatos, não apaga histórico e nenhuma nota solta pode ser a única fonte da situação atual.
  - Em cada resumo, seção e registro mutável, exibir `Atualizado por [usuária] em [data/hora]`; persistir o evento em UTC e apresentar em `America/Sao_Paulo`. A interface pode mostrar os valores anteriores somente no histórico funcional autorizado, nunca no log técnico.
  - Aceite positivo: uma técnica autorizada localiza situação, pendências, responsáveis e documentos sem percorrer módulos desconectados e consegue identificar a fonte/data de cada informação.
  - Aceite negativo: usuário sem permissão não recebe campo, contagem, aba, resultado ou snippet sensível; férias/afastamento não alteram autoria nem permitem conta compartilhada.

### Épico C — Saúde

- [ ] **SAU-01 — Criar aba e módulo de Saúde** (P1, G)
  - Disponibilizar “Saúde” no menu e na ficha do acolhido, com resumo e linha do tempo.
  - Permitir “Novo atendimento de saúde” selecionando o acolhido.
  - Campos mínimos: data/hora, categoria/local (`UBS`, `UPA`, `Hospital`, `CAPS`, `Outro`), estabelecimento/profissional, motivo, descrição, quem acompanhou, desfecho e encaminhamentos.
  - Permitir anexos privados e registrar autor/data de cada lançamento.
  - Aceite: um atendimento pode ser criado, editado conforme permissão, consultado na linha do tempo e contabilizado nos indicadores.

- [ ] **SAU-02 — Registrar medicações de uso contínuo com histórico** (P1, G)
  - Campos: medicamento/princípio ativo, apresentação, dose, via, frequência/horários, início, fim, prescritor, origem da prescrição e observações.
  - Alterações devem encerrar a versão anterior e criar uma nova; nunca apagar silenciosamente a prescrição histórica.
  - Exibir lista vigente e histórico, com alerta claro de informação desatualizada.
  - Aceite: uma reavaliação no CAPS consegue substituir uma medicação preservando quem alterou, quando e os valores anteriores.

- [ ] **SAU-03 — Controlar exames e encaminhamentos de Saúde** (P1, G)
  - Estados mínimos: solicitado, agendado, realizado, cancelado e resultado recebido.
  - Registrar solicitação, local, datas, acompanhante, resultado/resumo e anexos autorizados.
  - Permitir tarefas/lembretes vinculados à agenda sem contar a agenda como atendimento realizado.
  - Aceite: a equipe identifica exames pendentes e registra “realizado, acompanhado por X” com rastreabilidade.

- [ ] **SAU-04 — Registrar tratamentos de dose única e outros cuidados** (P1, M)
  - Diferenciar administração pontual de medicamento e prescrição contínua.
  - Registrar data/hora, item, dose, motivo, profissional/origem, acompanhante e observação.
  - Aceite: o registro pontual aparece na linha do tempo sem ser exibido como medicação vigente.

- [ ] **SAU-05 — Modelar informações de saúde sexual/reprodutiva com acesso restrito** (P1, M)
  - Incluir anticoncepcionais no modelo de medicações/cuidados, evitando rótulos ou exposição desnecessária no resumo geral.
  - Definir acesso por necessidade de conhecimento e auditar toda leitura/alteração.
  - Aceite: somente perfis aprovados acessam os dados; tentativas negadas e acessos permitidos ficam auditados.

### Épico D — Educação

- [ ] **EDU-01 — Criar aba e módulo de Educação** (P1, G)
  - Disponibilizar “Educação” no menu e na ficha do acolhido.
  - Registrar escola, rede, ano/série, turma, turno, situação da matrícula, datas, contatos e responsáveis escolares.
  - Manter histórico de transferência, mudança de série e encerramento de matrícula.
  - Aceite: a ficha mostra a situação escolar vigente e todo o histórico sem depender do texto do PIA.

- [ ] **EDU-02 — Registrar atendimentos e contatos escolares** (P1, G)
  - Tipos mínimos: ida/reunião na escola, ligação recebida, ligação realizada, atendimento, ocorrência, matrícula/transferência e outro.
  - Campos: data/hora, escola, participantes, motivo, relato, encaminhamentos, responsável e anexos.
  - Aceite: reunião, ida à escola e ligação são pesquisáveis, aparecem na linha do tempo e alimentam os indicadores anuais.

- [ ] **EDU-03 — Acompanhar pendências e próximos passos** (P2, M)
  - Criar tarefas vinculadas a um registro educacional, com responsável e prazo.
  - Integrar com a agenda sem duplicar o atendimento no relatório.
  - Aceite: pendências vencidas e futuras podem ser filtradas por acolhido, escola e responsável.

### Épico E — Atendimentos, encaminhamentos e indicadores

- [ ] **ATE-01 — Criar uma fonte estruturada comum para atendimentos** (P1, G; depende de `DEC-03`)
  - Adotar uma entidade-base `atendimentos` com área, tipo, data/hora, acolhido, unidade, setor, responsáveis, descrição e status.
  - Extensões específicas de Saúde/Educação podem ter tabelas próprias ligadas ao atendimento-base.
  - Registrar reaproximação familiar e demais áreas definidas sem criar textos impossíveis de contar.
  - Aceite: todo item contabilizado no informativo anual possui origem rastreável e categoria válida.

- [ ] **ATE-02 — Estruturar encaminhamentos e retornos** (P1, G)
  - Registrar destino, finalidade, data, responsável, prazo, situação, retorno/desfecho e atendimento de origem.
  - Permitir encaminhamento sem atendimento prévio quando a regra de negócio autorizar.
  - Aceite: é possível distinguir “encaminhado”, “realizado” e “aguardando retorno” sem interpretar texto livre.

- [ ] **BI-01 — Criar painel e relatório anual de atendimentos/encaminhamentos** (P1, G; depende de `ATE-01/02`)
  - Filtros: período (com atalho anual), setor, área, tipo, situação e acolhido; não oferecer filtro de unidade enquanto houver somente uma.
  - Exibir total de atendimentos, pessoas únicas, encaminhamentos por situação e detalhamento por Saúde, Educação/Escola, Reaproximação Familiar e demais categorias aprovadas.
  - Permitir abrir a lista que compõe cada número para conferência.
  - Aceite: os totais de uma base de teste conhecida batem com cálculo manual e não há dupla contagem.

- [ ] **BI-02 — Exportar informativo da diretoria** (P1, M)
  - Gerar PDF institucional e planilha (`.xlsx` ou `.csv`) com período, filtros, data de geração e responsável.
  - Aplicar permissão específica, trilha de auditoria e aviso de confidencialidade; exportações nominativas devem ser evitadas quando bastar dado agregado.
  - Aceite: a diretoria recebe o formato aprovado e os valores coincidem com o painel.

- [ ] **BI-03 — Criar controle de qualidade dos indicadores** (P2, M)
  - Mostrar registros sem classificação, incompletos, duplicados ou fora do período esperado.
  - Permitir fechamento mensal/anual e registrar reabertura/correção.
  - Aceite: nenhum relatório fechado muda sem evento auditável.

### Épico F — Agenda

- [ ] **AGD-01 — Implementar a agenda compartilhada aprovada** (P1, M; implementa `DEC-02`)
  - Informar que contas técnicas e administradora compartilham a agenda e o acesso funcional assistencial aprovado; não oferecer evento assistencial privado entre elas.
  - Adicionar filtros como “Minha agenda” e visão por responsável/setor/equipe sem tratá-los como escopo de autorização ou ocultação.
  - Paginar ou buscar eventos por intervalo visível; hoje todos os eventos são carregados de uma vez.
  - Aceite: dois usuários de setores diferentes enxergam/editam exatamente o que a matriz de acesso determina; calendário não degrada com grande histórico.

- [ ] **AGD-02 — Adicionar responsáveis, participantes e lembretes** (P2, M)
  - Separar criador, responsável principal e participantes.
  - Permitir lembretes configuráveis e vínculo com atendimento, exame, encaminhamento ou tarefa.
  - Aceite: férias/afastamentos podem ser cobertos por outro responsável sem mudar a autoria original.

### Épico G — Documentos e PDFs

- [ ] **DOC-01 — Validar e robustecer a foto no PIA** (P1, P; depende de `CAD-03`)
  - A foto é o retrato privado da criança/adolescente armazenado no perfil, conforme solicitação dos usuários. Confirmar posição, tamanho, proporção e se a inclusão será obrigatória, opcional por documento ou automática quando houver foto.
  - Decisão provisória para o protótipo em 27/09/2026: cadastro opcional e inclusão automática no PIA demonstrativo quando houver retrato privado válido; revisar com as técnicas na validação.
  - Fazer o gerador ler a imagem do armazenamento privado e tratar ausência/erro sem quebrar o PDF.
  - Aceite: testes com foto retrato/paisagem e sem foto; PDF correto em desenvolvimento e produção.

- [ ] **DOC-02 — Estruturar destinatário e procedimento judicial do Parecer do acolhido** (P1, M)
  - Campos candidatos: órgão/instituição, nome da pessoa, cargo/função, forma de tratamento, vara, comarca, processo/procedimento e complemento de endereçamento.
  - O destinatário pode representar juiz(a) ou outro ator autorizado do sistema de Justiça; a obrigatoriedade de cada subcampo permanece no checklist de `DEC-06`.
  - Salvar snapshot no documento para que mudanças cadastrais futuras não alterem o Parecer finalizado.
  - Aceite: dados de endereçamento e procedimento selecionados aparecem na posição aprovada do Parecer, sem redigitação quando já existirem em fonte canônica.

- [ ] **DOC-03 — Selecionar profissionais identificados por documento** (P1, G; implementa `DEC-04`)
  - Selecionar de 1 a 4 profissionais ativos, sem dupla fixa e com ordem configurável; uma pessoa pode constar sozinha durante férias/afastamentos.
  - Gravar como snapshot nome, cargo/função, conselho + registro quando aplicável, identificador do usuário e ordem.
  - Renderizar rodapés de identificação responsivos no fim do PDF, com quebra de página controlada e sem rótulo, linha ou alegação de assinatura eletrônica/digital.
  - Aceite: cenários com 1, 2, 3 e 4 identificações geram PDFs legíveis, preservam o snapshot após alteração do perfil e não alegam assinatura ou validade jurídica.

- [ ] **DOC-04 — Unificar metadados, UX e templates dos cinco documentos** (P1, G; primeiro corte depende da v0 de `DEC-06C`; homologação posterior em `DEC-06`)
  - Corte `PROT-01C`: reaproveitar rascunhos/telas/PDFs da POC, acrescentar o tipo e os campos faltantes para demonstração com dados fictícios e identificar claramente que não há emissão oficial. Concluir `DOC-04` e emitir documentos reais ainda depende do ciclo seguro de `DOC-05/06`, testes visuais e homologação documental.
  - Centralizar destinatário, profissionais identificados, numeração, situação (`rascunho`, `finalizado`, `cancelado`), versão e emissão.
  - Criar componentes Blade reutilizáveis de cabeçalho, destinatário, identificação profissional, rodapé e paginação.
  - Atender exatamente os cinco tipos aprovados em `DEC-06`: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais. Não criar Informação, Ofício genérico, Relatório Técnico genérico ou Ofício de encaminhamento do PIA como tipos funcionais.
  - UX documental: organizar em etapas curtas e linguagem de trabalho; priorizar seleção e autopreenchimento de fatos canônicos; revelar campos condicionais progressivamente; permitir rascunho incompleto; validar obrigatórios apenas na finalização; preservar o preenchimento ao navegar entre etapas.
  - O primeiro corte não aguarda a classificação definitiva: usa a v0 provisória e versionada de `DEC-06C`. Feedback/homologação em `DEC-06` gera versão posterior sem alterar documento finalizado.
  - Texto longo fica restrito a julgamento profissional ou narrativa indispensável. No Parecer do acolhido, oferecer estrutura guiada de contexto/início, evolução/meio, avaliação, pedido ao juiz e conclusão, com fatos canônicos referenciados e sem geração automática de afirmações.
  - Todo fluxo essencial funciona em celular e desktop, por teclado, com estados de carregamento, vazio, erro e acesso negado. Cada rascunho/detalhe mostra `Atualizado por [usuária] em [data/hora]`, usando o fuso `America/Sao_Paulo` na apresentação.
  - Aceite: os cinco documentos reutilizam metadados/componentes compatíveis, preservam finalidade/ciclo próprios e podem ser preenchidos sem redigitar dados canônicos; nenhum tipo excluído aparece como opção de criação.

- [ ] **DOC-05 — Corrigir concorrência na numeração documental** (P0, M; implementa `DEC-05`)
  - Substituir o cálculo “maior número + 1” distribuído entre tabelas por sequência central transacional, com unicidade por tipo documental/ano/unidade quando o dicionário aprovado exigir numeração.
  - Definir comportamento de número manual, cancelamento e reutilização.
  - Aceite: teste concorrente não gera números repetidos; todo número possui histórico.

- [ ] **DOC-06 — Versionar e finalizar documentos** (P0, G)
  - Separar rascunho editável de versão final imutável.
  - Registrar autor, revisores, snapshot dos profissionais identificados, hash, data de finalização e motivo de retificação/cancelamento.
  - Nunca alterar silenciosamente um PDF já emitido; correção gera nova versão ligada à anterior.
  - Aceite: o conteúdo de uma versão final pode ser comprovado e reproduzido.

### Épico H — Identidade, caixa institucional, prazos e PWA

> As funcionalidades deste épico são **P1 solicitadas pelos usuários**, mas não antecipam os bloqueadores P0. Ingestão Gmail, criação de agenda e Web Push só podem avançar depois de PostgreSQL, filas/workers, RBAC/ABAC, auditoria e gestão de segredos estarem operacionais e testados.

- [x] **IAM-01 — Analisar arquitetura operacional de identidade/MFA** (P0, M; alimenta `DEC-07`)
  - Análise documental concluída para Keycloak autogerido, IdP gerenciado e Laravel Fortify, considerando segurança, disponibilidade, integração, atualização, backup/restore, monitoramento, suporte, custo e saída/migração.
  - Com quatro usuários, uma aplicação, ausência de demanda de SSO por dois anos e orçamento total aproximado de R$ 60/mês, Fortify reduz componentes e carga operacional sem retirar a autorização por recurso/ação do backend.
  - A análise definiu TOTP obrigatório, passkeys desativadas, recuperação controlada, lifecycle, revogação e `break-glass` individual. Nenhum dado assistencial vai para um provedor de identidade e a caixa Gmail não é identidade de usuário.
  - Aceite concluído por análise e [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md). **Nenhuma PoC foi executada** e nenhuma implementação, dependência ou evidência de teste de login/MFA é alegada nesta tarefa.

- [ ] **IAM-02 — Implementar Fortify, TOTP obrigatório e lifecycle aprovado** (P0, G; depende de `DEC-07`, `IAM-01`, `SEG-02/03/04`; implementa em conjunto os requisitos de `SEG-01`)
  - [x] **IAM-02A — Primeiro login, enrolamento e sessão TOTP obrigatória** (P0, M; concluída em 25/09/2026, não conclui `IAM-02`)
    - Fortify fornece o motor TOTP/QR, mas todas as rotas padrão, passkeys, recuperação reversível e auto-desativação permanecem desabilitadas. Rotas próprias limitam a conta `pendente_mfa` ao QR corrente, confirmação e logout, e limitam `password_only` ao challenge/logout.
    - Segredo TOTP fica criptografado; geração é versionada e serializada no PostgreSQL. Confirmação e challenge usam lock e `last_accepted_time_step` durável para impedir replay do mesmo código. QR recebe `private, no-store` e deixa de existir após confirmação.
    - Tentativas TOTP inválidas acumulam strikes consecutivos duráveis por conta, serializados na mesma transação da verificação. A quinta falha inicia cooldown de 5 minutos; a décima eleva para 15 minutos; a décima quinta e seguintes usam o teto de 60 minutos por tentativa. Cache, logout, novo login, troca de IP, reset de senha ou passagem do tempo não zeram strikes; somente segundo fator válido os reinicia. Buckets curtos por conta/IP/sessão continuam como defesa complementar.
    - O início de cooldown gera auditoria atribuível e `warning` técnico estruturado sem PII, segredo ou código. A entrega proativa desse sinal a responsáveis e o teste do canal permanecem bloqueados por `OPS-01`; não há alegação de alerta recebido pela administradora neste corte.
    - Solicitação pública de reset devolve a mesma estrutura, status e mensagem para conta existente, inexistente ou inativa; limiter por IP e identificador HMAC do e-mail normalizado impede abuso sem expor o e-mail em chave/log, e a tentativa é auditada sem e-mail ou token. Antes de produção, o canal de envio precisa ser validado/assíncrono para que diferença de latência do provedor de e-mail não reintroduza enumeração temporal.
    - Troca de senha própria ou administrativa, mudança de estado/papel e as transições automáticas `ativa` sem fator → `pendente_mfa` → `ativa` revogam, na mesma transação e após lock da conta, qualquer link de recuperação pendente. O reset público revalida e consome o token sob lock antes de gravar a senha, impedindo que um link validado antes de uma intervenção concorrente sobrescreva a credencial nova. Testes PostgreSQL cobrem token obsoleto, rollback e corrida entre reset e cada transição.
    - O link gerado pela aplicação mantém token e e-mail somente no fragmento local do navegador: o primeiro GET usa `/reset-password` sem bearer em path/query, o bootstrap remove fragmentos reconhecidos antes do Inertia em qualquer caminho herdado por redirect e a captura ocorre por POST same-origin com CSRF para sessão criptografada. Fragmento parcial sensível falha fechado, âncora comum é preservada, a rota GET legada com token no caminho está ausente e o E2E verifica URL, histórico, redirects e access log sem os marcadores sintéticos.
    - Sessão intermediária expira em 5 minutos; sessão verificada aplica 15 minutos de inatividade, 8 horas absolutas, cookie encerrado com o navegador e `access_generation` durável para revogação entre file/database/Redis. Login sem fator, inclusive conta legada ativa, falha fechado e exige enrolamento.
    - Toda resposta Inertia usa histórico criptografado. Logout, timeout, inativação, revogação de geração e divergência remota do hash de senha recriam uma sessão anônima, limpam as chaves do histórico e obrigam nova autorização; snapshots autenticados do bfcache são desmontados antes de armazenar e recarregados ao restaurar. E2E desktop/mobile cobre dados assistenciais e QR/segredo após `Voltar`.
    - A gestão de equipe mostra `Pendente de MFA` explicitamente na lista e no formulário, inclusive para conta legada persistida como `ativa` sem fator antes do primeiro login, por meio de estado efetivo somente de apresentação. Consultar a conta não altera seu registro. Conta sem fator oferece apenas permanência pendente ou inativação, sem apresentar uma ativação que o backend não pode conceder; a explicação informa que nenhum dado assistencial fica acessível antes do vínculo TOTP.
    - Aceite: revisão Senior e QA independentes aprovaram o corte, sem `BLOCKER`/`HIGH`/`MEDIUM` aberto. PHPUnit em PostgreSQL: 172 aprovados, 2 skips esperados e 1.604 asserções; Playwright desktop/mobile: 32/32; Pint 186/186, build, auditorias Composer/npm, `git diff --check` e higiene de logs/artefatos aprovados. Este corte não inclui recovery codes hasheados, step-up, re-enrollment, logout global explícito, perda de dispositivo nem recuperação assistida de duplo controle.
  - [ ] **IAM-02B — Recuperação e gestão do fator TOTP** (P0, G; depende de `IAM-02A`, ainda não iniciada)
    - Próximo corte: códigos de recuperação hasheados e de uso único, step-up recente de senha + TOTP, re-enrollment seguro, revogação global por perda de dispositivo e recuperação assistida pela administradora mais segunda pessoa designada, com duas aprovações atribuíveis. Seguir os critérios de lifecycle, concorrência, auditoria e testes negativos descritos abaixo; e-mail não substitui o MFA primário.
  - Integrar Laravel Fortify preservando autorização por recurso/ação no backend. Desabilitar cadastro público e passkeys; não criar OIDC, Keycloak ou vínculo por e-mail neste incremento.
  - Exigir TOTP RFC 6238 para toda conta humana atual ou futura. A máquina de estados mínima é `pendente_mfa -> ativa`; enquanto pendente, a conta só pode acessar enrolamento/confirmar MFA e logout, sem qualquer dado ou ação assistencial.
  - Distinguir estado durável da conta do nível transitório da sessão. Uma conta `ativa` que acertou a senha, mas ainda não concluiu TOTP/recovery challenge, recebe sessão intermediária `password_only`, com timeout curto e rate limit; ela só acessa challenge e logout. Policies/middleware negam diretamente recursos assistenciais, QR/segredo, recovery codes, remoção/re-enrollment de MFA e qualquer endpoint administrativo.
  - Somente após segundo fator válido criar `mfa_verified` e rotacionar o session ID. Na fase inicial, remember-me/recaller fica integralmente desativado: campo, endpoint ou payload `remember` é ignorado/rejeitado e nenhum remember token/cookie persistente é emitido, mesmo após MFA. Todo novo login exige senha + TOTP; um recovery code de uso único só substitui TOTP no fluxo explícito de contingência.
  - Parâmetros aprovados para a fase inicial: challenge `password_only` expira em 5 minutos; cookie de autenticação é apenas de sessão do navegador, sem `Expires`/`Max-Age` persistente; sessão `mfa_verified` expira após 15 minutos de inatividade e possui duração absoluta máxima de 8 horas. Timeout, falha ou excesso de tentativas invalida o estado intermediário sem reaproveitar o ID anterior.
  - Ativar por QR em aplicativo compatível, como FreeOTP. QR/segredo são acessíveis somente durante o enrolamento pendente e até a confirmação válida; depois, desabilitar ou restringir o endpoint padrão de QR para negar qualquer consulta posterior. Re-enrollment exige fluxo controlado, autenticação reforçada e rotação/revogação do fator anterior.
  - Serializar geração e confirmação de enrolamento por usuário. Cada tentativa cria geração versionada; somente a geração pendente corrente pode fornecer QR e ser confirmada. Nova geração invalida QR/segredo pendente anterior, e duas gerações/confirmações simultâneas produzem exatamente uma geração corrente e no máximo um cutover válido.
  - Em re-enrollment, manter o fator atual válido até a confirmação da nova geração. O cutover troca o fator atomicamente, invalida a geração/QR anterior e revoga todas as sessões anteriores; falha antes do commit preserva o fator antigo, e falha após commit não pode reativá-lo nem deixar dois fatores correntes.
  - Para conta `ativa`, ver QR de re-enrollment, gerar/regenerar recovery codes, remover ou re-enrolar MFA exige step-up realizado nos últimos 5 minutos com senha e fator TOTP corrente; uma sessão `mfa_verified` antiga não basta. Primeiro enrolamento de `pendente_mfa`, que ainda não possui fator corrente, exige senha recém-validada na sessão restrita e confirmação pelo novo TOTP. Recuperação administrada por perda do fator segue verificação reforçada e duplo controle próprios em substituição ao fator indisponível.
  - Proteger segredo TOTP e nunca registrá-lo, junto com QR, senha, token ou código, em log ou auditoria. Testar confirmação, tentativa posterior de ler QR/segredo negada e re-enrollment autorizado e não autorizado.
  - Implementar criação/convite somente por administrador autorizado, ativação, inativação e revogação imediata de todas as sessões, tokens/recallers residuais e acessos derivados. Logout global, inativação, reset de senha, recuperação, cutover de re-enrollment e perda declarada de dispositivo revogam sessões e credenciais derivadas em todos os dispositivos. Estado local `inativo` sempre nega acesso.
  - O fluxo padrão documentado do Fortify para listar/regenerar recovery codes não satisfaz sozinho esta política. Substituir ou estender esse armazenamento/endpoint com conjuntos e códigos em registros individualmente hasheados, exibidos uma única vez, sem endpoint posterior de listagem em claro.
  - Garantir no PostgreSQL no máximo um conjunto ativo por usuário por constraint adequada. Consumir um código em transação com lock e atualização condicional de não usado/não revogado; uma rotação revoga/invalida atomicamente todo o conjunto anterior. Testar duas tentativas concorrentes com o mesmo código, replay, conjunto rotacionado e falha parcial: apenas uma tentativa pode autenticar.
  - Recuperação assistida exige administradora e uma segunda pessoa previamente designada, com aprovações atribuíveis registradas e motivo. A segunda pessoa só atesta essa recuperação, sem receber administração ampla. Sem pessoa designada ou sem ambas as aprovações, o fluxo falha fechado. A conclusão revoga sessões/códigos anteriores e exige novo vínculo TOTP.
  - Bloquear o endpoint padrão de auto-desativação do segundo fator. Remoção de MFA somente ocorre por recuperação/re-enrollment autorizado e auditado, revoga todas as sessões existentes e retorna a conta a `pendente_mfa` até nova confirmação.
  - E-mail OTP não é MFA primário. Eventual recuperação por e-mail usa TTL curto, token de uso único, rate limit, resposta que não enumera contas, verificação reforçada, auditoria e revogação; o reset de senha sozinho não remove ou substitui MFA.

- [ ] **IAM-03 — Administrar contas e o papel da equipe técnica** (P1, G; depende de `IAM-02`, `SEG-02/03/04`)
  - **Decisão humana atualizada em 10/09/2026:** na fase inicial existem somente contas `equipe_tecnica` e `administradora`. Ambas possuem o mesmo acesso funcional assistencial aprovado de leitura, criação, edição, finalização e retificação; nenhuma informação assistencial é privada entre elas. Somente a administradora gerencia contas e acessos.
  - Permitir que uma administradora autorizada convide/crie, ative/inative contas individuais e atribua/remova os papéis aprovados. Técnicas não administram contas, auditoria operacional, segredos ou infraestrutura.
  - Proibir que a administradora altere o próprio papel ou privilégios, inclusive por alteração indireta, payload adulterado ou troca do ID-alvo. Mudança de papel sempre aponta para conta terceira e usa Policy no backend.
  - Conceder/remover papel exige sessão `mfa_verified` e step-up realizado há no máximo 5 minutos com senha + TOTP corrente, consistente com `IAM-02/SEG-01`. Sessão sem step-up recente falha sem produzir alteração parcial.
  - Manter allowlist fechada dos papéis que o fluxo administrativo comum pode delegar. Papel fora da allowlist, alteração da própria allowlist/política e combinação de papéis não aprovada falham fechado. Sucesso e negação geram auditoria e alerta administrativo sem payload ou valor assistencial.
  - O bootstrap da primeira administradora ocorre uma única vez por procedimento operacional controlado e auditado fora do fluxo comum de gestão de contas. Exige identidade individual predefinida, ambiente autorizado e evidência de conclusão; não usa cadastro público, seed com credencial, conta compartilhada nem endpoint reutilizável, e fica desabilitado após concluir.
  - Toda autorização continua no backend com Policies e escopo fixo de organização/unidade. Conta inativa, ação administrativa/operacional não autorizada, ID trocado e contexto adulterado continuam negados; administradora e equipe técnica mantêm o mesmo acesso funcional assistencial aprovado, e esconder botão não é controle.
  - Mudanças de conta/papel são auditadas com ator, conta-alvo, ação, resultado e horário UTC, sem payload ou valores assistenciais. Inativação revoga imediatamente sessões, tokens e acessos derivados.
  - Administradora e técnicas não editam/apagam histórico funcional, auditoria ou controles de segurança. A administradora recebe o acesso assistencial uniforme aprovado por política desta fase, mas isso não concede auditoria operacional, segredos ou infraestrutura sem ação separada em `SEG-02/03/04`.
  - Garantir atomicamente que a organização não fique sem administradora efetiva: remover/inativar a última administradora exige sucessora ativa confirmada ou procedimento `break-glass` aprovado. Recuperação e `break-glass` permanecem individuais, atribuíveis, temporários e auditados.
  - Aceites positivos: duas técnicas ativas recebem o mesmo acesso assistencial aprovado; administradora com step-up recente ativa/inativa conta terceira e atribui/remove papel da allowlist com efeito imediato, alerta e auditoria; bootstrap inicial cria apenas a administradora individual predefinida e não pode ser repetido.
  - Aceites negativos: técnica não gerencia contas; usuário inativo, papel não aprovado, ID trocado, autoalteração de papel/privilégio, papel fora da allowlist, alteração da allowlist e sessão sem step-up recente são negados; duas alterações concorrentes não deixam zero administradoras efetivas.
  - Implementar `break-glass` sem conta compartilhada: identidade atribuível, menor escopo, TTL, duplo controle, alerta imediato, auditoria append-only, rotação/revogação pós-uso e exercício periódico.
  - Auditar sucesso/falha de login, ativação/inativação, cadastro/remoção de MFA, uso/invalidação de código de recuperação, recuperação assistida, revogação e contingência com correlation ID e resultado, sem segredo ou payload sensível.
  - Testes positivos/negativos: visitante e registro direto negados; conta humana nova/não enrolada fica em `pendente_mfa` e não acessa recursos; quatro contas iniciais e toda conta futura obrigadas a TOTP; sessão `password_only` de conta ativa recebe negação direta em recurso, QR, recovery, remoção/re-enrollment e admin, expira/sofre rate limit; TOTP válido rotaciona session ID; campo/endpoint/payload `remember` não cria recaller; cookie recaller fabricado, antigo, roubado ou revogado não autentica nem cria `password_only`/`mfa_verified`; idle de 15 minutos, absoluto de 8 horas e cookie de browser-session expiram conforme contrato; código TOTP inválido, expirado/reutilizado e sob rate limit; QR/segredo disponíveis apenas até confirmação e negados depois; operações MFA de conta ativa negam sessão antiga sem step-up e aceitam senha + fator corrente dentro de 5 minutos; primeiro enrolamento e recuperação administrada seguem suas exceções explícitas; gerações e confirmações simultâneas deixam exatamente uma geração/cutover válido, QR antigo falha e falhas antes/depois do cutover preservam um único fator corrente; `DELETE`/auto-desativação de MFA negado; re-enrollment indevido negado e sessões anteriores revogadas; logout global, inativação, reset, recovery, re-enrollment e perda de dispositivo revogam todos os dispositivos; recovery code válido uma única vez, concorrência/replay/rotação/falha parcial; e-mail não contorna MFA; usuário inativo e sessão revogada negados; admin sem permissão e ID trocado negados; `break-glass` sem duplo controle/expirado negado.
  - Aceite: cada pessoa usa conta própria e TOTP obrigatório; desligamento nega acesso imediatamente; recuperação não cria canal equivalente a MFA fraco; lifecycle, auditoria e contingência seguem o [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md).

- [ ] **EML-01 — Descobrir e provar sinteticamente a rota Google** (P0, M; alimenta `DEC-08` e respeita `DEC-11`; sem dependência de implantação)
  - Partir do tipo confirmado em `DEC-08A`: conta Gmail de consumidor `@gmail.com`, sem senha compartilhada. Registrar titularidade, acesso humano, limitações administrativas, retenção e saída.
  - Provar exclusivamente OAuth web consentido, com acesso offline/refresh token e menor escopo possível; service account e domain-wide delegation ficam fora deste modelo.
  - PoC usa somente conta/mensagens sintéticas e testa `users.watch/history.list` na mailbox de usuário, sem conexão à caixa real.
  - Avaliar elegibilidade do caso de uso e compatibilidade com Google Workspace API User Data and Developer Policy/Limited Use: dados armazenados/transmitidos, acesso humano, trechos/derivações, cópias persistentes, retenção/exclusão e eventual transferência a LLM.
  - Anexos, links e conteúdo remoto não são baixados, seguidos ou processados na PoC. Eventual parser é decisão separada por finalidade/RIPD e depende de storage privado, quarentena, validação de MIME/assinatura real, limites, antimalware e sandbox.
  - Avaliar remetente/domínio, DKIM/SPF/DMARC, encaminhamento e labels apenas como sinais de roteamento; versionar e auditar a configuração, sem tratar sinal técnico ou label como autorização/confiança.
  - Aceite: relatório e PoC reproduzível permitem decidir `DEC-08` sem segredo no repositório, acesso real ou suposição de credenciais administrativas incompatíveis com Gmail de consumidor.

- [ ] **EML-02 — Implantar principal e autorização Google aprovados** (P0, M; depende de `DEC-08`, `EML-01`, `LGPD-01/02`, `SEG-04/07`)
  - Implantar somente OAuth web consentido para a conta Gmail de consumidor aprovada, com acesso offline, refresh token revogável e menor escopo; não usar senha, service account ou domain-wide delegation.
  - Separar delegados humanos do principal técnico, aplicar menor escopo, ambientes/segredos separados, criptografia, rotação, revogação testada e limite de blast radius; nunca registrar credencial, token ou conteúdo.
  - Concluir verificação OAuth/avaliação de segurança exigível e gate Limited Use antes de produção, documentando consentimento/divulgação, transmissão, armazenamento, acesso humano e exclusão.
  - Testes: autorização/revogação, sujeito incorreto, escopo excessivo, principal de ambiente errado, operador sem permissão, rotação e inspeção de logs.
  - Aceite: staging sintético demonstra acesso mínimo e revogável apenas à mailbox/sujeito aprovado; qualquer modelo sem rota suportada falha fechado.

- [ ] **EML-03 — Ingerir mudanças com reconciliação idempotente** (P1, G; depende de `EML-02`, `ARQ-01/04/06`, `BE-02` no corte de idempotência, `SEG-02/03/04`)
  - Na primeira conexão, executar full sync/bootstrap inicial conforme [Synchronize clients with Gmail](https://developers.google.com/workspace/gmail/api/guides/sync), limitado à finalidade, labels e janela temporal aprovadas. Paginar `messages.list` até o fim, buscar somente os campos mínimos de cada mensagem elegível e obter cursor inicial seguro sem ampliar retenção.
  - Para a mailbox Gmail de consumidor aprovada, configurar `users.watch` com Cloud Pub/Sub autenticado, renovar diariamente dentro da validade máxima de 7 dias e alertar antes da expiração. Google Group/Collaborative Inbox está fora do escopo; qualquer consideração futura exige reabrir `DEC-08A`.
  - Executar reconciliação periódica por `users.history.list` **independente de push**, inclusive quando nenhuma notificação chega; consumir todas as páginas via `nextPageToken` e monitorar `last_successful_sync_at` contra SLO aprovado, com alerta de atraso.
  - No bootstrap, full sync de recuperação ou sync incremental, avançar checkpoint `historyId` somente depois de todas as páginas e efeitos idempotentes correspondentes estarem duráveis. A ordem é: enumerar/paginar o conjunto aprovado, persistir trabalho mínimo, confirmar efeitos/chaves naturais e outbox na transação e só então confirmar o checkpoint; falha antes do commit não avança cursor, e falha após o commit é retomada sem repetir efeito.
  - `HTTP 404`, histórico expirado ou checkpoint inválido aciona full sync controlado por janela/labels/finalidade, com paginação completa, limite, observabilidade e sem ampliar retenção.
  - Definir chave natural por caixa, mensagem/evento de origem e finalidade, protegida por constraint `UNIQUE` no PostgreSQL; transação/outbox confirma efeito e checkpoint, e retry/backoff trata duplicata, atraso e ordem invertida.
  - Labels/domínios filtram roteamento conforme configuração versionada/auditada, nunca autorização. Coletar o mínimo e não armazenar corpo/anexo por padrão; exceção segue `DEC-08`, Limited Use e retenção aprovada.
  - Auditar renovação, bootstrap, sync, faixa/páginas de mensagem/`historyId`, checkpoint, duplicata, full sync e reconciliação sem conteúdo/PII. Testar primeira conexão com múltiplas páginas, Pub/Sub forjado, notificação silenciosamente perdida, paginação incremental, retry, duplicata, ordem invertida, 404/expiração, rate limit, SLO vencido e falhas antes/depois do commit.
  - Aceite: bootstrap e full sync paginam todo o escopo aprovado sem omitir nem duplicar efeito; perda total de push é detectada pelo polling, checkpoint nunca ultrapassa efeito não confirmado, 404 é recuperado controladamente e a constraint impede duplicação concorrente.

- [ ] **EML-04 — Extrair propostas de prazo com evidência e revisão humana** (P1, G; depende de `DEC-09/11`, `EML-03`, `LGPD-02/03`, `SEG-02/03`)
  - Tratar remetente, assunto, corpo, HTML, link, anexo, label e resultados DKIM/SPF/DMARC como entrada não confiável. Sanitizar e impedir execução de ferramentas, chamadas externas, código ou ações de agenda; nunca seguir link, carregar imagem/conteúdo remoto ou baixar/processar anexo por padrão.
  - Eventual parser de anexo exige finalidade e `DEC-08` aprovadas, `ARQ-02`/`SEG-05`, quarentena, limites de tipo/tamanho/páginas/descompressão/CPU/memória/tempo, MIME e assinatura real, antimalware e sandbox sem rede. Testar PDF/MIME malformado, arquivo disfarçado, zip bomb/descompressão e timeout/resource exhaustion antes de liberar qualquer conteúdo ao extrator.
  - Conforme `DEC-09`, o primeiro corte é determinístico e limitado a datas literais, classificadas como `audiencia_compromisso`, `prazo` ou `mera_mencao`. Datas relativas ficam `ambigua` e não são calculadas. LLM permanece desligada por `DEC-11`; uso futuro exige reabertura formal e não terá autonomia para agir.
  - Criar somente proposta `pendente_de_revisao` ou `ambigua`, registrando referência mínima da origem, data literal, fuso e classificação sugerida. Jurisdição, termo inicial, calendário, dias úteis/corridos e cutoff não produzem data automática no MVP.
  - Evidência durável segue `DEC-08`: trecho mínimo apenas se permitido e com retenção/exclusão; caso contrário guardar referência/identificador e hash minimizado, sem alegar que hash substitui acesso à fonte.
  - Criação, correção, aprovação, rejeição, cancelamento e reclassificação são eventos append-only. Testes sintéticos cobrem data literal, audiência/compromisso, prazo, mera menção, data relativa ambígua, atualização/cancelamento, duplicata, HTML malicioso e prompt injection.
  - Aceite: nenhuma mensagem determina prazo jurídico, cria agenda ou notifica; proposta explica origem e incertezas, aceita correção autorizada e exige aprovação humana antes de qualquer uso operacional.

- [ ] **EML-05 — Revisar propostas, criar agenda e lembrar 1 dia antes** (P1, G; depende de `DEC-02/09/10`, `EML-04`, `AGD-01/02`, `PWA-02`)
  - Disponibilizar a fila às técnicas e administradora autorizadas por recurso e ação, preservando o acesso funcional assistencial uniforme. Setor, equipe e responsável são atribuições/filtros, não barreiras de acesso; label/remetente nunca concede acesso.
  - Antes de aprovar, a usuária pode corrigir data/hora, classificação, responsável, participantes/destinatários e lembrete. Proposta `ambigua` não avança até receber uma data explícita escolhida e justificativa humana; isso não transforma o sistema em calculadora jurídica.
  - Aprovação cria evento/tarefa vinculada à fonte sem afirmar validade jurídica e agenda o padrão de dia civil anterior às 09:00 em `America/Sao_Paulo`. Se o horário padrão já passou, exigir escolha explícita; não enviar retroativamente. Fim de semana/feriado não é ajustado automaticamente no MVP.
  - Após aprovação, qualquer mudança cria nova revisão append-only com justificativa, preserva a versão anterior e cancela/invalida jobs antigos antes de reagendar novos. Chave idempotente e transação/outbox impedem duplicação por retry, duplo clique ou reprocessamento.
  - Auditar criar, visualizar evidência, corrigir, aprovar, rejeitar, cancelar, reagendar e notificar, com ator, ação, alvo, resultado, UTC, campos alterados, justificativa, revision ID e correlation ID. Antes/depois sensível fica no histórico funcional autorizado; log técnico não copia corpo do e-mail, narrativa, PII ou valores sensíveis.
  - Testar cada ação aprovada/negada, contexto adulterado, ID trocado, ambiguidade, horário padrão já passado, fim de semana/feriado mantido ou alterado, nova revisão, cancelamento/reagendamento idempotente, concorrência e falha parcial.
  - Aceite: aprovação gera exatamente um item de agenda e um lembrete por destinatário/dispositivo elegível; correção posterior preserva versões e converge para somente os jobs da revisão corrente; o sistema continua apresentado como apoio, não cálculo jurídico autoritativo.

- [ ] **PWA-01 — Tornar a aplicação instalável sem cache de dados sensíveis** (P1, M; depende de `DEC-10`, `ARQ-01/04`, `SEG-02/03/04` e corte acessível de `FE-01`)
  - Adicionar manifest e service worker com instalação em desktop e mobile; documentar que, no iOS, notificações exigem adicionar o app à Home Screen.
  - Cachear somente app shell e assets estáticos públicos, imutáveis e versionados. Navegação autenticada, Inertia, APIs, HTML, fotos, anexos e agenda usam estratégia **network-only**, sem fallback a resposta anterior.
  - Enviar headers anti-cache apropriados nas respostas autenticadas/sensíveis, incluindo `Cache-Control: private, no-store`, e tratar restauração por histórico/bfcache com revalidação de sessão/autorização e remoção imediata do estado anterior.
  - Proibir persistência de props, respostas autenticadas, dados assistenciais e filas offline em Cache Storage, cache HTTP, IndexedDB, `localStorage` ou `sessionStorage`; logout, inativação e troca de usuário limpam estado cliente controlado.
  - Exibir estado offline/erro acessível sem dado antigo e manter autorização no servidor. Testes Playwright do lockfile inspecionam Cache Storage, cache HTTP, histórico/bfcache, IndexedDB, local/session storage, logout, inativação, troca de usuário, atualização e viewports desktop/mobile.
  - Aceite: instalação funciona nas plataformas aprovadas e nenhum mecanismo de cache/storage/histórico revela props, resposta autenticada, PII ou conteúdo assistencial após navegação, logout, inativação ou troca de usuário.

- [ ] **PWA-02 — Entregar notificações in-app e Web Push privadas** (P1, G; depende de `DEC-10`, `PWA-01`, `ARQ-04`, `SEG-02/03/04`)
  - Solicitar opt-in contextual, nunca no primeiro carregamento; manter subscription por usuário e dispositivo, com expiração/revogação em logout, inatividade, desligamento, mudança de vínculo e resposta `410/404` do push service.
  - Manter inventário minimizado dos dispositivos/subscriptions com rótulo reconhecível, criação, última atividade e situação, sem expor endpoint/chaves. Oferecer interface autenticada para o usuário revogar remotamente um aparelho específico perdido e para administrador autorizado agir conforme política.
  - Enviar push genérico, sem PII ou informação assistencial na lockscreen; abrir o aplicativo autenticado para buscar o detalhe autorizado. Aplicar quiet hours, timezone e fallback in-app/operacional aprovado.
  - Jobs usam chave de idempotência por evento, antecedência, destinatário e dispositivo, com retry/backoff e fila de falhas; revogação individual, cancelamento ou perda de autorização invalida/cancela jobs pendentes e faz retries revalidarem a subscription antes do envio.
  - Auditar opt-in/opt-out, criação/revogação de subscription e resultado agregado do envio, sem endpoint, chave, payload sensível ou conteúdo da agenda em logs comuns.
  - Testes: permissão concedida/negada por ação, inventário com múltiplos dispositivos, revogação remota de aparelho perdido, IDOR contra dispositivo de outro usuário, subscription expirada, logout/inatividade, conta inativa, job já enfileirado, retry/duplicata, fallback e payload sem PII; confirmar que aparelho revogado não recebe reenvio.
  - Aceite: usuário elegível recebe lembrete in-app e, se opt-in ativo, push genérico 1 dia antes; revogação individual remove o dispositivo elegível, cancela/invalida jobs e impede envio ou reenvio por retries.

## 6. Segurança, LGPD e governança

> Estas tarefas são requisitos de produto, não apenas infraestrutura. A validação final deve envolver o controlador dos dados, o encarregado e assessoria jurídica. Consentimento não deve ser assumido como base legal padrão: a finalidade e a hipótese legal precisam ser definidas para cada tratamento, sempre considerando o melhor interesse da criança/adolescente.

- [ ] **LGPD-01 — Inventariar dados, finalidades, bases legais e agentes** (P0, G)
  - Mapear cadastro, documentos, saúde, educação, agenda, relatórios, logs, backups, suporte e fornecedores.
  - Identificar controlador, operadores/suboperadores, encarregado e canal dos titulares.
  - Registrar finalidade, necessidade, compartilhamentos, localização, retenção e descarte de cada categoria.
  - Tratar `docs/formatacoes` como repositório local de dados reais fora do fluxo controlado. A inspeção estrutural excepcional autorizada em `DEC-06A/06B` não autoriza copiar, versionar, importar ou reutilizar valores; definir com o controlador procedimento de quarentena/sanitização, acesso mínimo, retenção e eventual descarte conforme `LGPD-02/03`.
  - Aceite: Registro das Operações de Tratamento aprovado; todo campo do dicionário possui finalidade e retenção.

- [ ] **LGPD-02 — Elaborar RIPD e política de privacidade/proteção de dados** (P0, G)
  - Avaliar riscos específicos de dados de menores, saúde, vida sexual/reprodutiva, documentos judiciais, fotografias e localização/agenda.
  - Documentar medidas de mitigação e decisões de privacy by design/default.
  - Aceite: riscos residuais têm responsável e aceite formal; avisos e procedimentos estão publicados para os públicos adequados.

- [ ] **LGPD-03 — Definir retenção, descarte, bloqueio e preservação legal** (P0, M)
  - Não apagar automaticamente antes de validar obrigações do ECA, Judiciário, Município e políticas arquivísticas.
  - Definir retenção por tipo, legal hold, anonimização quando aplicável e descarte verificável em produção, réplicas e backups.
  - A intenção de manter cópias locais por cinco anos não aprova retenção geral. Qualquer prazo de cinco anos depende de categoria, finalidade e validação do controlador/jurídico; cópia local exige criptografia, controle de acesso, inventário, descarte verificável e nunca pode ser a única cópia.
  - Aceite: matriz de retenção aprovada e jobs testados sem violar preservação obrigatória.

- [ ] **LGPD-04 — Implementar atendimento aos direitos dos titulares** (P1, G)
  - Fluxo autenticado para confirmação, acesso, correção, informação sobre compartilhamento e demais solicitações aplicáveis.
  - Tratar representação por responsável, capacidade progressiva e exceções legais com revisão humana.
  - Aceite: solicitações têm protocolo, prazo, responsável, evidências e trilha de decisão.

- [ ] **LGPD-05 — Criar plano e registro de incidentes** (P0, M)
  - Definir detecção, contenção, avaliação de risco/dano relevante, comunicação interna e modelos para ANPD/titulares.
  - Considerar o prazo regulatório vigente de 3 dias úteis quando a comunicação for obrigatória.
  - Manter registro de incidentes por pelo menos 5 anos e executar simulado periódico.
  - Aceite: tabletop test concluído, contatos e substitutos atualizados, evidências arquivadas.

- [ ] **SEG-01 — Fechar cadastro público e fortalecer contas** (P0, M)
  - Remover/desabilitar as rotas públicas de registro e criar usuários somente por administrador autorizado ou convite controlado.
  - [x] **SEG-01A — Fechar imediatamente o cadastro público** (P0, P; primeiro corte independente de `SEG-01`)
    - Remover/desabilitar GET e POST públicos de `/register`, alinhar links e testes e preservar o login de contas existentes.
    - Não inclui convite administrativo, Fortify/TOTP, RBAC ou refatoração geral da autenticação.
    - Aceite: visitante recebe falha fechada em GET e POST; submissão direta não cria usuário; login existente continua funcional; PHPUnit e E2E negativo aprovados pelo fluxo completo de agentes.
    - Contrato executado, risco, dados, autorização/auditoria, migração/rollback e plano de testes estão preservados no [blueprint](docs/architecture/project-blueprint.md#contrato-rtk-historico-executado-de-seg-01a).
    - Evidência: PHPUnit de registro/autenticação com 6 testes e 14 assertivas; nenhuma rota `/register`; E2E desktop/mobile com 6 de 6 cenários aprovados pelo QA.
  - Exigir MFA TOTP para toda conta humana atual/futura, incluindo os quatro usuários iniciais. Conta criada inicia `pendente_mfa`, com acesso limitado a enrolamento/confirmar MFA e logout; só a confirmação promove a `ativa`. Aplicar política de senha, rate limit, bloqueio/inativação e encerramento imediato de sessões e acessos derivados. Passkeys permanecem desativadas.
  - Oferecer recovery codes em registros individualmente hasheados, sem listagem posterior em claro, com consumo atômico transacional e conjunto anterior invalidado na rotação; o comportamento padrão do Fortify deve ser substituído/estendido para cumprir esta política. Recuperação assistida por administrador é atribuível.
  - E-mail não é MFA primário e eventual recuperação por esse canal exige verificação reforçada, TTL curto, uso único, rate limit, auditoria e revogação conforme `DEC-07`.
  - Bloquear auto-desativação padrão do segundo fator; remoção exige recuperação/re-enrollment auditado, revoga sessões e retorna a `pendente_mfa`.
  - Conta `ativa` pós-senha continua em sessão intermediária sem MFA: challenge/logout são as únicas rotas permitidas e o session ID é rotacionado ao concluir. Estado `pendente_mfa` é atributo durável de conta ainda sem fator confirmado, não sinônimo dessa sessão transitória.
  - Desativar integralmente remember-me/recaller na fase inicial; cada login novo exige senha + TOTP. Aprovar cookie apenas de browser-session, challenge de 5 minutos, idle timeout de 15 minutos e absolute timeout de 8 horas.
  - Operações MFA de conta ativa exigem step-up de no máximo 5 minutos com senha + fator corrente; sessão `mfa_verified` antiga não autoriza QR de re-enrollment, recovery codes, remoção ou re-enrollment. Primeiro enrolamento e recuperação administrada seguem exceções controladas próprias.
  - Aceite: visitante não cria conta por UI nem requisição direta; conta não enrolada não acessa dado assistencial; toda conta humana precisa de TOTP; sessão pós-senha não acessa recurso/QR/recovery/re-enrollment/admin; recaller fabricado/antigo/roubado/revogado não autentica nem cria nível de sessão; QR/segredo são negados depois da confirmação; testes cobrem timeouts, step-up recente, revogação global por logout/inativação/reset/recovery/re-enrollment/perda de dispositivo, rotação do session ID, convite, endpoint `DELETE` negado, geração/confirmação concorrente e QR velho, re-enrollment/cutover/falha parcial, TOTP inválido/replay/rate limit, concorrência/replay/rotação de recovery code, recuperação indevida e ausência de segredo/código em logs.

- [ ] **SEG-02 — Implementar RBAC/ABAC com menor privilégio** (P0, G; implementa `DEC-05`)
  - [ ] **SEG-02A — Fundação de acesso ativo e Policies por recurso/ação** (P0, M; implementada em 24/09/2026 e em revisão, não conclui `SEG-02`)
    - Papéis canônicos `administradora` e `equipe_tecnica`, com estados `ativa`, `inativa` e `pendente_mfa`, substituem os códigos provisórios. Somente conta ativa em papel aprovado atravessa as rotas protegidas; conta inativa é desconectada e papel desconhecido é rejeitado também por constraint PostgreSQL.
    - Policies cobrem criança/adolescente, familiar, anexo, PIA, visita, parecer/relatório, termo de pertences, agenda, setor, contas e auditoria. Técnica e administradora ativas possuem o mesmo acesso assistencial, sem barreira artificial por setor; contas e auditoria operacional continuam exclusivas da administradora.
    - Exclusão física assistencial, de contas, setores e auditoria foi negada no backend e retirada das interfaces alcançadas por este corte. Autoexclusão de conta foi desativada; inativação é o lifecycle provisório até `IAM-02/03`.
    - Evidências automatizadas cobrem visitante, conta inativa ou pendente de MFA, papel inválido no banco, igualdade entre setores, técnica sem gestão de contas/auditoria, troca de ID-alvo, exclusão negada, contexto institucional adulterado e toda ação atual de PIA, visita, parecer, pertences, agenda, setores, familiares/anexos, PDF/download, contas e auditoria. Downloads/PDFs receberam capacidade explícita; no corte demonstrativo `PROT-01A2`, uploads/downloads de anexos foram desativados. Arquivos legados não migrados e a reativação segura de anexos ainda dependem de `ARQ-02/SEG-05`.
    - A checagem de acesso ativo ocorre depois da autenticação e antes do route-model binding, evitando consulta ao alvo ou diferença `403/404` que permita enumerar IDs após revogação. A administradora não altera o próprio papel/estado, e atualizações concorrentes de administradoras bloqueiam as linhas relevantes antes de preservar pelo menos uma administradora ativa.
    - Permanecem para `SEG-02`: finalizar a matriz sobre ações futuras (finalizar, retificar, cancelar e exportar), autorização dos módulos ainda não implementados, scoping de busca/contagens quando houver novos níveis de sensibilidade e revisão E2E completa da matriz.
  - Substituir verificações dispersas por Laravel Policies/Gates para ver, criar, alterar, finalizar, excluir, baixar e exportar.
  - **Matriz inicial aprovada em 10/09/2026:** contas ativas `equipe_tecnica` e `administradora` recebem o mesmo acesso funcional assistencial de leitura, criação, edição, finalização e retificação nos recursos aprovados da organização/unidade únicas; não existe informação assistencial privada entre elas. Filtros são somente visão.
  - Apenas a administradora gerencia contas e acessos. Técnicas não recebem administração de usuários, auditoria operacional, segredos ou infraestrutura. Histórico/auditoria e controles de segurança são append-only e não editáveis/apagáveis por nenhum desses papéis; baixar, exportar e cancelar continuam ações separadas a provar.
  - A administradora recebe consulta somente leitura à auditoria funcional necessária para responder quem executou cada ação e quando, com filtros e minimização; isso não concede acesso a segredos, payloads, logs técnicos irrestritos, infraestrutura ou alteração da trilha. Técnicas veem autoria/data e histórico funcional autorizado na ficha e na linha do tempo, sem administrar a auditoria.
  - Não existem contas de cuidadoras, jurídico, administrativo, visitantes ou integrações nesta fase. Qualquer novo papel reabre a matriz antes de implementação; profissão ou vínculo institucional nunca concede acesso implicitamente.
  - Todo usuário pertence à unidade única, sem vínculo M:N ou seleção de unidade.
  - Aplicar o contexto fixo de organização/unidade no backend em todas as consultas e mutações, não apenas esconder botões. Rejeitar parâmetros ou payloads que tentem escolher ou trocar esse contexto.
  - Aceite: matriz automatizada prova igualdade do acesso funcional assistencial entre técnica e administradora ativas e cobre cada ação; administradora consulta auditoria funcional em modo read-only; testes negativos impedem IDOR trocando recurso/ID, negam visitante, conta inativa e papel não aprovado, impedem técnica de administrar contas/auditoria/segredos/infraestrutura, impedem qualquer papel de alterar/apagar a trilha e rejeitam contexto adulterado. `SEG-02` permanece pendente até Policies/Gates e testes serem aprovados.

- [ ] **SEG-03 — Criar auditoria inviolável e pesquisável** (P0, G)
  - [ ] **SEG-03A — Trilha append-only e primeiro corte vertical auditado** (P0, M; implementada em 24/09/2026 e em revisão, não conclui `SEG-03`)
    - PostgreSQL armazena ator, ação estável, alvo/ID, resultado, unidade, nomes dos campos alterados, horário UTC e correlation UUID gerado no servidor. Trigger bloqueia `UPDATE`, `DELETE` e `TRUNCATE`; Policies negam escrita pela aplicação.
    - Login bem-sucedido/falho (sem copiar o e-mail tentado), acesso negado, visualização da ficha, criação/alteração de criança/adolescente, criação/alteração de contas, atualização do perfil e troca/reset de senha formam a cobertura inicial. Mutações cobertas e seus eventos de auditoria confirmam na mesma transação; falha da auditoria reverte a mutação.
    - Eventos distintos `user.profile_updated`, `user.password_changed`, `user.password_reset` e `user.password_admin_reset` preservam a finalidade da ação sem guardar senha, token, e-mail tentado ou valores alterados.
    - A administradora ganhou consulta funcional somente leitura com filtros por ação, resultado e ator, paginação e horário apresentado em `America/Sao_Paulo`. A técnica vê `Atualizado por [usuária] em [data/hora]` na ficha, sem pesquisar a auditoria operacional.
    - A trilha guarda somente nomes de campos, nunca valores anteriores/novos, payload, narrativa, diagnóstico, documento, CPF/CNS, token, IP ou user-agent. Upload de foto/arquivo não é alegado como atomicamente coberto pelo banco e continua sob `ARQ-02/SEG-05`.
    - Permanecem para `SEG-03`: auditar busca, listagens sensíveis, demais documentos, agenda, PDF/download/exportação e futuros fluxos de finalizar/retificar/aprovar/rejeitar/cancelar/reagendar/notificar; criar histórico funcional versionado separado da trilha; definir retenção operacional com `LGPD-03`; ampliar E2E. Outbox/idempotência para efeitos externos continua em `BE-02` e não foi criada sem integração externa concreta.
  - Registrar login, falhas, visualização de ficha sensível, busca, criação, alteração, finalização, retificação, aprovação, rejeição, cancelamento, reagendamento, notificação, download, PDF, exportação e acesso negado.
  - Guardar ator, ação, alvo/ID, resultado, horário UTC, unidade, campos alterados, justification/revision ID quando aplicáveis, correlation ID e contexto técnico estritamente necessário. Apresentar datas em `America/Sao_Paulo` e mostrar `Atualizado por [usuária] em [data/hora]` na UI.
  - Não copiar payload, narrativa, diagnóstico, documento ou valores sensíveis anteriores/novos para log técnico ou trilha de auditoria. Em uma alteração de nome, a auditoria registra que o campo foi alterado, por quem e quando, sem duplicar o nome anterior/novo.
  - O histórico funcional/versionamento autorizado preserva o estado anterior e a retificação com autoria/data, sem overwrite silencioso; ele não se confunde com log técnico nem amplia acesso. Mutação e evento de auditoria/outbox confirmam atomicamente.
  - Exemplo de apresentação: a linha do tempo mostra `Nome alterado por [usuária] em [data/hora]`; somente ao abrir o histórico funcional com autorização o sistema apresenta a versão anterior e a atual. A trilha de auditoria e o log técnico não copiam nenhum dos dois nomes.
  - Proteger a trilha append-only contra alteração e definir acesso/retenção próprios. A administradora consulta a visão funcional minimizada em modo somente leitura; técnicas veem autoria/data nos recursos autorizados, mas não pesquisam a auditoria operacional.
  - Aceite: é possível responder quem criou, alterou, finalizou, retificou, aprovou, rejeitou, cancelou, reagendou, notificou, acessou ou exportou um registro e quando; o histórico funcional autorizado recupera a versão anterior, enquanto logs/auditoria não contêm os valores sensíveis modificados.

- [ ] **SEG-04 — Proteger configuração, sessão e comunicação** (P0, M)
  - Produção com `APP_DEBUG=false`, HTTPS obrigatório, cookies `Secure`, `HttpOnly`, `SameSite` adequado e sessão criptografada/centralizada.
  - Aplicar nível de autenticação no backend: sessão pós-senha/pre-TOTP tem timeout/rate limit, não cria remember token, não reutiliza o ID após MFA e não atravessa middleware/Policy de recursos ou administração. Recaller fica desativado, e cookies/sessões obedecem duração de browser-session, idle de 15 minutos e absoluto de 8 horas.
  - Gerenciar segredos fora do repositório, com rotação e separação por ambiente.
  - Aplicar headers de segurança, CSP compatível, rate limits e proteção contra abuso.
  - Aceite: checklist automatizado de configuração passa em staging e produção.

- [ ] **SEG-05 — Proteger uploads e downloads** (P0, G; depende de `ARQ-02`)
  - Validar assinatura/MIME real, tamanho, extensão, dimensões e quantidade; renomear arquivos e remover metadados quando cabível.
  - Executar varredura antimalware assíncrona e manter item em quarentena até aprovação.
  - Download somente por controller/policy ou URL assinada de curta duração, com auditoria.
  - Aceite: arquivo público, executável disfarçado, arquivo acima do limite e acesso de outro perfil são bloqueados em testes.

- [ ] **SEG-06 — Minimizar PII em logs, erros e ambientes não produtivos** (P0, M)
  - Redigir campos sensíveis de logs/traces; páginas de erro nunca exibem segredos ou payloads.
  - Proibir cópia de produção para desenvolvimento; usar dados sintéticos ou anonimização aprovada.
  - Aceite: inspeção de logs e banco de staging não encontra CPF, saúde ou documentos reais fora do fluxo autorizado.

- [ ] **SEG-07 — Revisar fornecedores e transferências** (P0, M)
  - Avaliar hospedagem, banco, objetos, e-mail, observabilidade, suporte e assinatura quanto a contrato, suboperadores, região dos dados, criptografia, exclusão e resposta a incidente.
  - Aceite: cada fornecedor possui avaliação, contrato/DPA quando aplicável e plano de saída/exportação.

## 7. Arquitetura, banco, backend, frontend e operação

### Tecnologias

#### Estado atual verificado

- Monólito PHP 8.3/Laravel 13 com Inertia 2, React 18, Vite e Tailwind CSS 3; autenticação e sessão são mantidas pela aplicação.
- PostgreSQL 17 já é canônico no Docker local, PHPUnit Feature e Playwright E2E por `ARQ-01A`; o SQLite sintético antigo não é importado nem usado operacionalmente. Filesystem local/público e infraestrutura PostgreSQL de produção continuam bloqueadores para dados reais em `ARQ-01/02/03/07`.
- Há workflow GitHub Actions e suíte Playwright fixada no `package-lock.json`; os gates continuam sendo os de `RTK.md`, e E2E deve rodar exclusivamente por `npm run test:e2e`.
- As decisões de [identidade](docs/adr/0002-identidade-laravel-fortify-totp.md) e [infraestrutura-alvo](docs/adr/0003-infraestrutura-contabo-swarm-redis.md) estão documentadas. PostgreSQL local/CI foi implantado em `ARQ-01A`; Fortify/TOTP, PostgreSQL na Contabo, Swarm, Redis, Horizon e backups fora da VPS **ainda não estão implantados**.
- A decisão de [organização e unidade únicas](docs/adr/0004-organizacao-e-unidade-unicas.md) aprovou um contexto explícito fixado no backend, sem SaaS multi-organização, suporte multiunidade ou seleção de unidade. O corte `ARQ-01B/ARQ-06A` concluiu modelo, backfill reconciliável, integridade e testes em 10/09/2026; `NOT NULL` em `ARQ-01C`, infraestrutura produtiva e autorização completa continuam pendentes.

#### Tecnologias recomendadas e aprovadas para implementação, ainda sujeitas aos gates

- Preservar o monólito modular Laravel + Inertia + React e implementar identidade local com Laravel Fortify, contas individuais e TOTP obrigatório, conforme `DEC-07`.
- Hospedar futuramente em Contabo VPS com Docker Swarm inicialmente single-node. O perfil **candidato**, ainda não validado por carga/capacidade, é 4 vCPU, 8 GB de RAM e 100 GB SSD; o auto backup pago foi aprovado para contratação como camada adicional, ainda não ativa/testada. Swarm oferece orquestração e roll-forward, não alta disponibilidade enquanto houver um único nó.
- Usar PostgreSQL autogerido inicialmente em container separado na mesma VPS da aplicação, por restrição orçamentária, e Redis obrigatório com separação de cache, sessões e filas, além de Laravel Queues + Horizon. Essa separação é lógica, não alta disponibilidade nem domínio de falha distinto. PostgreSQL e outbox são a fonte de verdade; cache é seletivo e descartável.
- Manter object storage privado como obrigação pendente. Centralizar a execução na VPS nunca transforma disco local ou uma cópia única em armazenamento durável.
- Manter GitHub Actions para CI e Playwright do lockfile para E2E desktop/mobile. Nenhuma nova dependência ou serviço entra sem avaliação de segurança, licença, operação, custo e plano de saída.

#### Decisões tecnológicas pendentes

- `DEC-08`: o tipo da conta já foi confirmado como Gmail de consumidor e a rota candidata exclui senha compartilhada, service account e domain-wide delegation; ADR, PoC sintética de OAuth web/offline, menor escopo, Limited Use, titularidade, revogação, retenção e blast radius continuam pendentes.
- `DEC-09`: taxonomia do MVP, revisão humana, versionamento e lembrete padrão no dia civil anterior às 09:00 foram decididos; implementação e testes permanecem em `EML-04/05`, `SEG-02/03` e `QA-01`.
- `DEC-10`: opt-in contextual e push genérico sem PII já foram decididos; navegadores, quiet hours, fallback e lifecycle/revogação por dispositivo continuam pendentes.
- `DEC-11` está concluída para a fase atual: não usar LLM. Qualquer avaliação futura, inclusive de Groq apenas como candidato de PoC, depende de reabertura formal e dos gates de privacidade, fornecedor e testes sintéticos.
- Fornecedor/região do object storage, Pub/Sub e push, bem como DPA, valores finais de RPO/RTO e eventual migração para PostgreSQL dedicado/gerenciado, dependem de `SEG-07`, `ARQ-01/02/07` e aprovação formal.

### Direção recomendada

Manter **Laravel + React/Inertia como monólito modular** nesta fase. A stack atende o domínio e a equipe ganha mais separando módulos e dados do que introduzindo microserviços. Escalar primeiro com aplicação stateless, PostgreSQL, object storage privado, filas e observabilidade; considerar serviços separados apenas diante de medição ou fronteira organizacional real.

- [x] **ARQ-01A — Tornar PostgreSQL canônico em desenvolvimento e CI** (P0, M; concluído em 31/08/2026; corte de `ARQ-01`)
  - PostgreSQL 17 no Docker Compose com healthcheck, rede interna, volume nomeado e sem porta pública; `pdo_pgsql` substitui a dependência operacional de SQLite no container PHP.
  - PHPUnit Feature, migrations e Playwright E2E usam PostgreSQL; Unit tests permanecem sem dependência de banco. CI valida banco vazio, seed exclusivamente sintético, refresh efêmero, relacionamentos essenciais e ausência de FK sem índice.
  - O bootstrap de testes e o comando `e2e:reset-database` falham antes de qualquer consulta destrutiva se ambiente, driver, host, `DB_URL`, banco exato ou configuração efetiva/cacheada não forem seguros. O E2E prepara assets, usa servidor concorrente e aguarda respostas/navegação reais, sem retries.
  - Agenda interpreta `datetime-local` em `America/Sao_Paulo`, persiste instantes em UTC e converte fronteiras de consulta para UTC; compromissos de dia inteiro preservam a data civil. Criação, edição e leitura têm cobertura Feature e E2E desktop/mobile.
  - Corrigida a migration histórica `2026_07_17_000014` para parsing PHP portátil e determinístico. Exceção autorizada antes de produção: o SQLite continha somente dados fictícios e não existe cadeia produtiva a preservar. Demais migrations históricas não foram reescritas.
  - Migration aditiva cria somente índices ausentes das chaves estrangeiras. SQLite antigo permanece intocado/ignorado, sem exportação ou backfill.
  - A compatibilidade Vercel foi mantida somente para a demo sintética, protegida por `VERCEL=true` e isolada do PostgreSQL canônico. Sua remoção depende do cutover/desativação do deploy. Guia local: [PostgreSQL local e testes](docs/development/postgresql.md).
  - Aceite concluído: gates PostgreSQL/CI, persistência local, isolamento do banco E2E e fluxos desktop/mobile foram evidenciados no handoff Implementer → Senior → QA/Security, sem achados bloqueantes. Este item não autoriza dados reais nem conclui `ARQ-01`.

- [x] **ARQ-01B — Introduzir o contexto institucional único e o backfill reconciliável** (P0, M; concluído em 10/09/2026; corte de `ARQ-01`, implementa `DEC-05`)
  - Implementado em `codex/institution-context-foundation`: tabelas explícitas de organização/unidade, FKs nullable, provisionamento idempotente e transacional, `--dry-run`, reconciliação, backfill em chunks e derivação backend sem seletor no cliente.
  - PostgreSQL impede segunda organização/unidade, serializa provisionamentos concorrentes por advisory lock transacional e valida unidade/setor por constraint composta; body/query/rota com ID de contexto adulterado falha em `422` antes da mutação. Configuração local/teste permanece exclusivamente sintética e produção exige confirmação explícita.
  - Senior e QA/Security concluídos sem achados bloqueantes. As colunas continuam nullable somente para rollout; `NOT NULL` será `ARQ-01C` após provisionamento/reconciliação comprovados no ambiente-alvo. Guia: [contexto institucional único](docs/development/institution-context.md).

- [ ] **ARQ-01C — Fechar nulabilidade das chaves de contexto após rollout** (P0, P; depende de `ARQ-01B`)
  - Executar `--dry-run`, provisionamento e `--reconcile-only` no ambiente-alvo; guardar evidência apenas de contagens e IDs técnicos mínimos.
  - Somente com zero órfãos/conflitos, criar migration aditiva para `NOT NULL`. Falha deve ser corrigida roll-forward, sem apagar contexto, vínculo ou histórico.

- [ ] **ARQ-01 — Migrar SQLite para PostgreSQL autogerido inicialmente na VPS** (P0, XG; implementa `DEC-05`)
  - Por restrição do orçamento total aproximado de R$ 60/mês, iniciar com PostgreSQL autogerido em container separado na mesma Contabo VPS da aplicação, em serviço isolado no Swarm, volume persistente dedicado, TLS e rede privada sem porta pública de banco. O isolamento é lógico e não cria alta disponibilidade nem domínio de falha separado.
  - Usar como ponto de partida sujeito a `ARQ-07` o perfil candidato de 4 vCPU, 8 GB de RAM e 100 GB SSD. Contratar o auto backup pago da Contabo está decidido, mas o serviço ainda precisa ser ativado e validado. Essas referências não comprovam capacidade, SLO, RPO/RTO, alertas ou restore e não autorizam dados reais.
  - A aplicação usa role sem `SUPERUSER`, sem criação de banco/role e sem DDL de rotina. Separar credenciais e privilégios mínimos para aplicação, migrations e backup/restore; restringir e auditar o uso das duas últimas.
  - Dimensionar `max_connections` dentro da memória da VPS e limitar pools de aplicação/workers. Adotar PgBouncer/pooling externo somente após medir concorrência e compatibilidade transacional; configurar `statement_timeout`, `idle_in_transaction_session_timeout` e limites de lock adequados por workload.
  - Monitorar e ajustar autovacuum/`ANALYZE`, bloat, locks, conexões e queries com `pg_stat_statements` de acesso restrito, sem parâmetros/payloads sensíveis em logs/métricas. Definir patching e janela de manutenção testada.
  - Configurar backup base + WAL/PITR criptografado com cópia obrigatória fora da VPS e credenciais separadas. Auto backup/snapshot da Contabo e cópia local são complementares, nunca a única cópia. O cofre/escrow criptografado e segregado fora da VPS preserva versões necessárias de `APP_KEY` e das chaves/segredos de backup, OAuth, push e TOTP para restaurar dados ainda vigentes; rotação e acesso são auditados e testados. Se houver backup do estado de manager do Swarm, sua unlock key fica separada desse backup. Nunca versionar segredo no repositório.
  - Executar restauração periódica integral em ambiente isolado e aprovar RPO/RTO antes de dados reais.
  - Remover a cópia do SQLite para `/tmp` e qualquer dependência de filesystem da instância.
  - Criar migração/exportação verificável, constraints, chaves, índices e plano de roll-forward/rollback sem `migrate:fresh`.
  - Introduzir organização cliente e unidade únicas por configuração controlada e migração aditiva; mapear registros e usuários explicitamente, validar órfãos e só aplicar `NOT NULL` após o backfill. Não hardcodear dados reais; índices e unicidades incluem as chaves de contexto apenas quando necessárias à integridade, auditoria ou numeração definidas no ADR 0004.
  - Registrar explicitamente que a VPS única é `single point of failure`. Migrar o banco para nó dedicado ou serviço gerenciado quando RPO/RTO não forem atingidos, houver contenção mensurável de CPU/I/O/memória, crescimento além da capacidade aprovada, necessidade de manutenção sem indisponibilidade ou orçamento/risco justificar menor carga operacional.
  - Aceite: múltiplas réplicas da aplicação no mesmo nó veem dados consistentes; reinício/deploy não perde dados; backup fora da VPS e restore/PITR são testados dentro do RPO/RTO aprovado. Alta disponibilidade não é alegada.

- [ ] **ARQ-02 — Migrar arquivos para object storage privado e durável** (P0, G)
  - Selecionar e usar storage S3-compatible privado **externo à VPS**, criptografado, com versionamento/lifecycle, URLs temporárias e cópia/replicação conforme RPO/RTO. Fornecedor/região/DPA permanecem pendentes de `SEG-07`.
  - Separar anexos sensíveis de assets públicos; migrar fotos/anexos existentes com checksum.
  - Centralização do processamento na VPS não autoriza filesystem local nem cópia única: banco, objetos e catálogo/checksums precisam de reconciliação e backup isolado.
  - Aceite: deploy, reinício ou perda da VPS não constitui a única cópia do arquivo; URL expirada falha; banco e objeto permanecem consistentes; restore conjunto é testado.

- [ ] **ARQ-03 — Corrigir pipeline de deploy e separar ambientes** (P0, G)
  - Remover `migrate:fresh --seed` do deploy de produção; migrations devem ser incrementais, revisadas e compatíveis com rollback/roll-forward.
  - Criar desenvolvimento, staging e produção com segredos, bancos, Redis e buckets separados. Produção futura usa Contabo VPS e Docker Swarm single-node; segredos entram por Docker secrets e nunca em imagem, stack file versionado ou log.
  - Fixar serviços stateful por placement constraint/label ao nó que possui o volume persistente correto. Antes de adicionar nós, definir binding/provisionamento dos volumes e migração/failover dos serviços stateful; o Swarm não pode reagendar PostgreSQL/Redis para volume local vazio nem alegar HA. Backup do manager não carrega a unlock key no mesmo artefato/local.
  - Pipeline mínimo: instalar com lockfile, lint/format, testes, build, análise de dependências, migration check, imagem imutável, deploy gradual no Swarm, healthcheck/smoke test e rollback de aplicação compatível com roll-forward de banco.
  - Aceite: deploy no ambiente-alvo não altera dados destrutivamente; seed fictício só roda por comando explícito fora de produção; Swarm recupera serviço/processo, sem alegação de HA do nó único.

- [ ] **ARQ-04 — Tornar a aplicação stateless e preparar workers** (P0, G)
  - Adotar Redis desde a fase 1 em serviços/processos e volumes realmente separados: cache seletivo descartável com TTL/eviction; sessões com `noeviction` e persistência; filas com `noeviction`, persistência e Laravel Horizon. Bancos lógicos na mesma instância Redis não isolam eviction, persistência ou falha e não atendem este contrato.
  - Definir persistência/fsync, backup/restore e alertas de memória, disco, latência e falha conforme RPO de sessões e filas. Restore de sessão nunca pode ressuscitar sessão expirada/revogada/antiga: na recuperação de desastre, validar geração/revogação contra o PostgreSQL ou invalidar sessões e exigir nova autenticação/TOTP. Restaurar filas exige reconciliação idempotente com outbox.
  - Processar Gmail/Pub/Sub, PDFs pesados, exports, miniaturas, antimalware e notificações em Laravel Queues com Horizon, retries/backoff, idempotência, timeout, failed jobs e reconciliação por outbox PostgreSQL.
  - Restringir `/horizon` por Gate/Policy no backend a operador ativo explicitamente autorizado com sessão `mfa_verified` e, preferencialmente, também por rede administrativa/VPN; não confiar em ocultação de menu. Testar visitante, sessão `password_only`, autenticado comum e ID/vínculo inadequado negados.
  - Jobs carregam somente IDs opacos, tipo de operação e metadados mínimos; nunca corpo de e-mail, documento/anexo, narrativa assistencial, PII, URL assinada ou segredo. O worker busca o mínimo autorizado no momento da execução. Configurar trim/retenção de jobs recentes, concluídos e failed jobs, sanitização de exceções/logs e descarte conforme política; acesso ao Redis/Horizon fica limitado a serviços e operadores autorizados.
  - Horizon não suporta Redis Cluster. Manter a fila Horizon em Redis não-cluster nesta fase; antes de adotar Redis Cluster, aprovar saída que mantenha Redis dedicado compatível para Horizon ou substitua Horizon/driver de filas por alternativa avaliada e testada.
  - PostgreSQL permanece fonte de verdade. Cache não guarda a única cópia de dado e seu uso depende de medição; com apenas quatro usuários, desempenho não justifica cache amplo.
  - Aceite: duas ou mais réplicas da aplicação no mesmo Swarm atendem o mesmo usuário por sessão compartilhada; backup/restore de sessões e filas cumpre RPO sem reativar sessão revogada; falha/reinício de worker retoma job sem duplicar efeito; `/horizon` nega usuário não autorizado; inspeção de payloads, Redis, failed jobs e logs sintéticos não encontra PII, narrativa, documento, e-mail ou segredo; retenção/trim funciona; queda/perda do cache não perde fato assistencial. Isso não prova HA do host.

- [ ] **ARQ-05 — Organizar o backend por domínios** (P2, G)
  - Módulos sugeridos: Acolhimento, Cadastro, Saúde, Educação, Atendimentos, Agenda, Documentos, Identidade/Acesso e Auditoria.
  - Extrair validações para Form Requests, autorização para Policies e regras transacionais para Actions/Services; controllers ficam finos.
  - Usar eventos de domínio/outbox somente onde houver integração ou processamento assíncrono real.
  - Aceite: módulos têm contratos claros e testes; regras não dependem de componentes de interface.

- [ ] **ARQ-06 — Fortalecer o modelo relacional e a concorrência** (P0, G)
  - Criar constraints de domínio, unicidade e integridade; timestamps com fuso consistente (armazenar UTC, apresentar `America/Sao_Paulo`).
  - Adicionar índices compostos guiados pelos filtros efetivos: situação, período, área, acolhido e setor; organização/unidade únicas entram apenas quando necessárias à integridade ou unicidade, não como filtros da interface.
  - Adotar transações e optimistic locking/versionamento para impedir sobrescrita simultânea.
  - Preferir IDs não enumeráveis em links externos/assinados quando necessário, sem tratar UUID como autorização.
  - Aceite: testes de concorrência, integridade e plano de queries críticas aprovados.

- [x] **ARQ-06A — Proteger integridade do contexto único no modelo relacional** (P0, M; concluído em 10/09/2026; corte de `ARQ-06`)
  - Chaves bigint, FKs e índices de contexto são aditivos; agregados com setor usam o par `(setor_id, unidade_id)` para impedir divergência. Familiares e anexos derivam do pai.
  - Novos writes recebem contexto por observer no backend; `organizacao_id`/`unidade_id` não são fillable nem props de seleção. Reabrir `DEC-05` antes de qualquer segunda organização/unidade.
  - Não inclui casas, episódios, RBAC, auditoria, outbox, sequência documental ou índices compostos além dos necessários às FKs deste corte. Gates independentes concluídos sem achados bloqueantes.

- [ ] **ARQ-07 — Aprovar capacidade mínima e continuidade antes de dados reais** (P0, M)
  - Partir da premissa de quatro usuários iniciais e uma aplicação e avaliar o perfil candidato Contabo de 4 vCPU, 8 GB de RAM e 100 GB SSD; estimar carga mínima, volume inicial de dados/anexos e recursos necessários dentro do orçamento total aproximado de R$ 60/mês.
  - **Decisão de contratação em 16/09/2026:** contratar também o auto backup pago da Contabo junto à futura VPS. O serviço é uma camada adicional aprovada para compra, ainda não está ativo/testado e não comprova capacidade, continuidade, consistência, RPO/RTO ou restore. Antes do go-live, confirmar documentalmente escopo, frequência, retenção, região, consistência de volumes/banco, criptografia, restauração e plano de saída do produto contratado.
  - Definir SLO mínimo, RPO, RTO, capacidade/limites de conexões e disco, alertas e responsáveis, registrando que single-node Swarm/uma VPS não oferece alta disponibilidade.
  - Executar restore integral PostgreSQL + WAL/PITR + objetos + chaves/segredos do cofre segregado, sem restaurar sessões revogadas, e smoke test dos fluxos críticos dentro do RPO/RTO.
  - Aceite bloqueante: orçamento, capacidade mínima, SLO/RPO/RTO, alertas e restore integral estão aprovados e evidenciados antes de qualquer dado real.

- [ ] **ARQ-08 — Testar crescimento, desempenho e gatilhos de escala** (P2, M; depende de `ARQ-07`)
  - Levantar concorrência observada, acolhidos, episódios, eventos por ano, tamanho/volume de anexos e crescimento esperado antes de ampliar cache ou serviços.
  - Testar login, busca, lista, agenda por intervalo, gravação, PDF e relatório anual com massa representativa; registrar plano de execução e gargalos.
  - Definir gatilhos mensuráveis para adicionar nó/replicar aplicação, separar Redis/worker, mover PostgreSQL para nó dedicado/gerenciado ou ampliar storage: saturação sustentada de CPU/memória/I/O, atraso de fila, falha de SLO/RPO/RTO, manutenção sem janela aceitável, crescimento de dados/anexos ou nova exigência organizacional.
  - Aceite: relatório de carga registra limites, otimizações e próximo gatilho de escala sem tratar cache amplo, réplica no mesmo nó ou Swarm single-node como alta disponibilidade.

- [ ] **BE-01 — Entregar busca global e contextual autorizada** (P1, G; corte funcional do Marco 2)
  - Corte `PROT-01B`: partir da busca da POC e localizar, no servidor, nome/processo e metadados de documento/fato já estruturados, agrupados pela ficha; não prometer busca em texto narrativo ou áreas que ainda são prévia. `BE-01` completa continua aberta até cobrir os módulos canônicos e todos os testes de autorização/escala abaixo.
  - Agenda deve consultar somente o intervalo visível; seletores de acolhidos precisam busca remota/paginada.
  - Criar busca global e contextual no PostgreSQL, normalizada para acentos e com índices apropriados, por nome, identificador autorizado, processo, tipo/título/número de documento, metadados e campos estruturados relevantes.
  - Aplicar autorização e sensibilidade no servidor **antes** de produzir resultados, totais e snippets. A fase inicial não indexa nem pesquisa o corpo narrativo sensível; futura busca em corpo exige finalidade aprovada, permissão específica, minimização e reavaliação de risco.
  - Agrupar resultados por acolhido e tipo, com filtros, paginação e snippets mínimos; localizar documentos pela localização lógica, nunca por caminho interno de storage. Evitar N+1 e limitar payloads Inertia.
  - Auditar busca e acesso sem registrar termos ou conteúdo sensível. Cache, índices auxiliares e contagens respeitam o mesmo escopo e não podem revelar existência, quantidade ou dado entre permissões.
  - Aceite: testes positivos e negativos cobrem IDOR, conta inativa, ação/recurso não autorizado, sensibilidade, acentos, filtros, paginação e cache entre usuários; administradora e técnica com a mesma ação assistencial aprovada encontram pessoa, fato estruturado ou documento pela ficha unificada. Download/export e outras ações especiais seguem Policies próprias quando definidas.

- [ ] **BE-01B — Otimizar busca, paginação e filtros para escala** (P2, M; depende de `BE-01` e `ARQ-08`)
  - Medir consultas com massa sintética representativa, ajustar índices PostgreSQL e planos de execução, eliminar N+1 e limitar payloads Inertia sem ampliar campos indexados ou permissões.
  - Aceite: queries críticas atendem SLO aprovado, com plano de execução e regressão de autorização/cache registrados.

- [ ] **BE-02 — Padronizar erros, idempotência e APIs internas** (P0, M)
  - Entregar antes das integrações o corte de correlation ID, chave natural/idempotência, constraints únicas PostgreSQL, transação/outbox e resposta consistente para retry/concorrência.
  - Respostas de validação consistentes; operações longas ou repetíveis com chave de idempotência quando necessário.
  - Versionar API somente se surgir cliente externo/mobile; não criar API paralela sem consumidor.
  - Aceite: duplo clique/retry/concorrência não duplica atendimento, encaminhamento, documento, mensagem ingerida ou notificação; falha parcial é reconciliável.

- [ ] **FE-01 — Consolidar design system e acessibilidade** (P2, G)
  - Manter uma fonte de verdade para tokens/componentes; hoje MUI e Tailwind coexistem e precisam regras claras.
  - Atingir WCAG 2.2 AA nos fluxos essenciais: teclado, foco, contraste, labels, leitores de tela, erros e alvos de toque.
  - Testar celular/tablet, pois os feedbacks mostram uso nesses formatos.
  - Aceite: auditoria automatizada + manual dos fluxos críticos sem bloqueadores.

- [ ] **FE-02 — Migrar JavaScript crítico gradualmente para TypeScript** (P2, G)
  - Tipar props Inertia, formulários, estados e contratos de Saúde/Educação/Documentos.
  - Gerar/compartilhar tipos quando possível, mantendo validação autoritativa no backend.
  - Aceite: novos módulos não usam `any` sem justificativa e falhas de contrato são detectadas no CI.

- [ ] **OPS-01 — Implementar observabilidade sem vazar dados** (P0, G)
  - Logs estruturados com correlation ID e redação de PII, métricas, alertas, health/readiness checks e rastreamento de erros.
  - A aplicação não gera mais `/reset-password/{token}` nem query de e-mail: o link oficial usa fragmento não enviado por HTTP e captura same-origin protegida por CSRF. Antes da produção, configurar o proxy/servidor para não registrar corpos e para sanitizar também caminhos/query arbitrários ou legados de recuperação, mantendo somente padrão da rota, status e correlation ID; clientes externos ainda podem fabricar URLs sensíveis que a aplicação não controla antes da borda.
  - Monitorar taxa de erro, latência, filas, banco, storage, login suspeito, exports e falhas de backup.
  - Proteger dashboard Horizon por Gate/Policy backend e restrição de rede quando disponível; definir trim/retenção e redação de jobs/failed jobs/exceções, sem payload assistencial ou credencial.
  - Aceite: runbooks e alertas testados; equipe consegue diagnosticar falha sem acessar conteúdo sensível indevido.

- [ ] **OPS-02 — Formalizar backup, continuidade e recuperação** (P0, G)
  - Backups criptografados de banco e objetos, cópia isolada fora da VPS, política de retenção, restauração periódica e responsáveis substitutos. O auto backup pago da Contabo foi aprovado para contratação em 16/09/2026 como camada adicional; ainda precisa ter escopo, frequência, retenção, região, consistência e restore verificados. Nunca será a única cópia nem substituirá base + WAL/PITR, backup de objetos e restore conjunto testado.
  - A intenção de guardar cópia local por cinco anos depende da matriz de retenção de `LGPD-03` e da aprovação do controlador/jurídico por categoria. Se aprovada, exige criptografia, acesso mínimo, inventário, descarte verificável e nunca será a única cópia.
  - Manter fora da VPS, em cofre/escrow criptografado e segregado, versões das chaves/segredos ainda necessários para restaurar backups e dados criptografados (`APP_KEY`, backup, OAuth, push e TOTP), com acesso mínimo, rotação, auditoria e restore test. Unlock key de eventual backup do manager Swarm fica separada do artefato.
  - Criar plano de indisponibilidade com procedimento operacional temporário e reconciliação posterior.
  - Aceite: simulado restaura banco + arquivos + chaves necessárias dentro do RPO/RTO aprovado, sem ressuscitar sessão revogada e sem segredo no repositório.

- [ ] **OPS-03 — Gerenciar dependências e vulnerabilidades** (P0, M)
  - Atualizações programadas de Composer/NPM, análise de dependências/SBOM, patches críticos e revisão do runtime PHP não oficial usado no deploy atual.
  - Fixar versões via lockfiles e testar atualização em staging.
  - Evidência em 26/08/2026: `composer audit --locked` encontrou advisories HIGH/MEDIUM em `league/commonmark` anterior a 2.9.0; `npm audit --audit-level=high` encontrou HIGH em `nanoid` anterior a 3.3.18 e MODERATE em `postcss` até 8.5.22. Atualizações dependem da avaliação de compatibilidade e do fluxo de dependência de `RTK.md`; este registro não autoriza alteração de pacote.
  - Patch autorizado em 29/09/2026: lockfile atualizou somente `laravel/framework` 13.20.0 → 13.30.0 e `league/flysystem` 3.35.2 → 3.35.3, sem pacote novo ou mudança de esquema. `composer audit --locked` e `npm audit --audit-level=high` ficaram sem avisos; suíte PostgreSQL integral 264 testes/3.420 asserções (7 skips esperados) e E2E 8/8 em desktop/celular passaram nas versões corrigidas. A limpeza do cache gerado de views foi necessária para testes locais após o upgrade; o ambiente E2E real subiu sem override. Este patch não conclui o trabalho recorrente de `OPS-03` nem substitui validação de staging/produção.
  - Aceite: não há vulnerabilidade crítica conhecida sem exceção formal, prazo e mitigação.

## 8. Estratégia de testes e definição de pronto

- [ ] **QA-01 — Criar pirâmide de testes do domínio** (P0, G)
  - [x] **ENG-test-runner-memory — Tornar o gate PHP integral reproduzível** (infraestrutura de engenharia): executar o PHPUnit diretamente com limite de 512 MB apenas no runner de testes local/CI. O comando anterior aplicava o limite ao `artisan test`, mas o processo filho do PHPUnit ainda podia ficar em 128 MB e abortar ao gerar PDF. Nenhum limite do runtime de produção foi alterado; o gate completo e o teste de PDF devem permanecer verdes.
  - Unitários: transições de acolhimento, contagem de indicadores, numeração, permissões, regras de medicação, semântica de prazos e idempotência de mensagens/notificações.
  - Feature/integration: CRUD assistencial uniforme para administradora/equipe técnica e autorização por recurso/ação, contexto fixo de organização/unidade no servidor, setores/responsáveis apenas como filtros/atribuições, uploads privados, gestão de contas/auditoria/segredos/infraestrutura e ações especiais como download/export/cancelamento separadamente protegidas, filas e PostgreSQL real; lifecycle de identidade cobre falhas parciais IdP/local, e Gmail cobre bootstrap paginado, checkpoint, push perdido e full sync com serviços falsos/dados sintéticos.
  - E2E: ingresso → PIA/Parecer do acolhido → Saúde/Educação → agenda → Relatório de visita técnica → relatório anual; acrescentar login/MFA, revisão humana de proposta e lembrete PWA quando os respectivos marcos entrarem.
  - Segurança/PWA: IDOR, usuário inativo/revogado, validações OIDC/JWKS/PKCE negativas quando aplicáveis, prompt injection, Pub/Sub forjado/perdido, escopo OAuth excessivo, anexo malformado/disfarçado/zip bomb/timeout, aparelho revogado com job pendente, payload push sem PII e ausência de props/respostas/filas autenticadas em Cache Storage, cache HTTP, histórico/bfcache, IndexedDB e local/session storage após logout, inativação e troca de usuário.
  - PDF visual/regressivo: foto, destinatário, 1–4 identificações profissionais sem alegação de assinatura, múltiplas páginas e caracteres portugueses.
  - Aceite: CI bloqueia merge em falha e cenários P0/P1 têm testes negativos e positivos.

- [ ] **QA-02 — Homologar com usuários usando cenários sintéticos representativos** (P1, M)
  - Sessões separadas com coordenação, serviço social, psicologia, pedagogia/educação e saúde.
  - Registrar evidência, problema, severidade e aceite por fluxo.
  - Em `PROT-01E/01F` e nos testes práticos posteriores, usar somente pessoas, casos, fotos, documentos e situações fictícias; anonimização informal de um caso real não o transforma em fixture permitida. Uso posterior de dados reais exige os gates de LGPD, segurança, implantação e go-live.
  - Aceite: representantes comentam o vídeo e depois exercitam a jornada do protótipo, registrando o que precisa mudar; a aprovação final de ficha, status, Saúde, Educação, documentos e informativo anual ocorre após implementação funcional e novos testes.

### Definição de pronto para qualquer tarefa

- Critérios de aceite automatizados quando viável e homologados pelo usuário responsável.
- Autorização aplicada e testada no backend.
- Auditoria, retenção e classificação de dados avaliadas.
- Migração de banco reversível/compatível e sem perda silenciosa.
- Interface responsiva, acessível e com estados de carregamento, vazio e erro.
- Documentação operacional e de usuário atualizada.
- Métricas/logs sem PII desnecessária.
- Deploy em staging, smoke test e plano de rollback concluídos.

## 9. Ordem recomendada de execução

### Decisões e limites que permanecem abertos

`DEC-03` e `DEC-09` foram concluídas em 16/09/2026. Permanecem abertas `DEC-01` somente para o histórico entre casas, `DEC-06` para homologação definitiva das técnicas, `DEC-08` para ADR/PoC/governança Google e `DEC-10` para plataformas, quiet hours, fallback e lifecycle dos dispositivos. `LGPD-01/02/03`, `SEG-07`, `ARQ-07` e `OPS-02` continuam gates humanos/operacionais para dados reais. A contratação futura do auto backup da Contabo está decidida, mas não equivale a serviço ativo, cópia externa independente ou restore comprovado.

### Próximos incrementos para o protótipo, com dados exclusivamente sintéticos

1. **`PROT-01A` — preparar o que já existe:** conferir o estado real da branch e da POC, criar jornada/dados fictícios, ambiente isolado, contas TOTP e smoke test móvel. Não habilitar foto/anexo público; executar corte mínimo privado de `ARQ-02/SEG-05` antes de demonstrar retrato no PIA. `SEG-02A/03A` e `IAM-02A` são fundações reutilizadas, não motivo para reiniciar a aplicação.
2. **`PROT-01B` — ficha e localização:** `ACO-01/02/03` + `CAD-01` + primeiro corte de `BE-01/CAD-04`. A técnica cria/acha o acolhido, vê escola e órgão/pessoa condutora, encontra informação/documento sem procurar em pastas e distingue acolhimento, evasão, internação, retorno e desacolhimento.
3. **`PROT-01C` — calendário e documentos:** começar por `AGD-01/02` sobre a agenda existente; depois cortes de `DOC-01/02/03/04` que tornam os cinco formulários/PDFs demonstráveis com menos digitação, destinatário, foto privada quando segura e identificação profissional flexível. Documento continua demonstrativo até `DOC-05/06` e demais gates de emissão oficial.
4. **`PROT-01D` — prévias visuais, sem falsa persistência:** apresentar à equipe os fluxos de Saúde, Educação e informativo anual baseados em `SAU-*`, `EDU-*`, `ATE-*` e `BI-*`, marcados como prévia. Coletar prioridade, rótulos e campos antes de construir o modelo completo; dado sensível sexual/reprodutivo não recebe permissão inferida.
5. **`PROT-01E` — vídeo de 5–10 minutos:** capturar com Playwright da versão fixada, narrar em linguagem simples e adicionar legendas, destaques e música suave licenciada em volume que preserve a voz. Mostrar desktop/celular, cinco documentos e prévias claramente rotuladas; revisar segurança e compartilhar privadamente.
6. **`PROT-01F` — retorno das técnicas:** colher comentários por fluxo, fazer sessão de dúvidas se necessário, registrar pedidos em `QA-02/DEC-06` e estimar alterações de escopo/prazo para aprovação. Vídeo não substitui teste prático nem conclui tarefa funcional.

### Após a apresentação, sem perder os bloqueadores de produção

7. Implementar as fatias funcionais aceitas de `ATE-01/02`, `SAU-01/02/03/04/05`, `EDU-01/02/03` e `BI-01/02/03`; relatório anual só usa fatos estruturados realizados, nunca evento agendado ou texto livre como única fonte.
8. Completar `IAM-02B/IAM-03`, `SEG-02/03/04/05/06/07`, `ARQ-01/02/03/04/06/07`, `DOC-05/06`, `BE-02`, `OPS-01/02` e `LGPD-01/02/03` conforme dependências e gates de dados reais. Testar restore, PDF visual, autorização e fluxo ponta a ponta antes de produção; o protótipo não os dispensa.
9. Priorizar Gmail e PWA (`DEC-08/10`, `EML-*`, `PWA-*`) após validar com o cliente a rotina técnica e o escopo aprovado. Proposta de prazo extraída de e-mail nunca cria compromisso antes de revisão humana (`DEC-09`).

`ARQ-01C` não é o próximo corte local: fechar `NOT NULL` somente após rollout, provisionamento, dry-run e reconciliação comprovados no ambiente-alvo.

### Gates adicionais antes de qualquer dado real

- concluir e aprovar `LGPD-01/02/03`, incluindo retenção por categoria; resolver com procedimento autorizado qualquer material real fora do fluxo controlado;
- concluir `SEG-04/05/06/07`, fornecedor/região/DPA/saída do storage e Google, gestão de segredos e hardening;
- implantar a VPS/Swarm/PostgreSQL/Redis/storage com TLS, roles mínimas, monitoramento, limites e patching;
- contratar e validar o auto backup da Contabo **e**, independentemente, manter base + WAL/PITR, objetos e chaves criptografados fora da VPS;
- aprovar SLO/RPO/RTO, medir capacidade/disco e executar restore integral + smoke test por `ARQ-07/OPS-02`;
- executar os gates de QA/Security, homologação e go-live formal. Nenhuma decisão desta seção autoriza dados reais antes dessas evidências.

## 10. Matriz de rastreabilidade dos feedbacks

| Feedback do usuário | Tarefas que atendem |
|---|---|
| Foto do acolhido no PIA | `DOC-01`, `CAD-03`, `ARQ-02` |
| Administradora gerenciar contas; técnicas ativas compartilham o mesmo acesso assistencial aprovado | `IAM-03`, `SEG-02/03/04` |
| Ficha unificada com situação, pendências, responsáveis e linha do tempo | `CAD-04`, `ACO-01/03`, `SEG-02/03` |
| Passagem de caso durante férias/afastamentos sem perder autoria ou histórico | `CAD-04`, `AGD-02`, `SEG-03` |
| Busca global autorizada para localizar informações e documentos | `BE-01`, `CAD-04`, `SEG-02/03` |
| Agenda pessoal ou compartilhada | `DEC-02`, `AGD-01`, `AGD-02` |
| Abas específicas de Saúde e Educação | `SAU-01` a `SAU-05`, `EDU-01` a `EDU-03` |
| Nova criança com todos os dados da ficha de ingresso | `DEC-06`, `CAD-01`, `CAD-02`, `ACO-01` |
| Adotar o PIA mais completo fornecido pelas técnicas sem copiar dados reais nem versionar o PDF preenchido | `DEC-06/06A/06B/06C/06D`, `LGPD-01/02/03`, `CAD-01`, `DOC-01/03/04/06`, [matriz sanitizada](docs/document-field-matrix.md) |
| Informações escolares no ingresso | `CAD-01`, `EDU-01` |
| Quem/qual órgão conduziu ao acolhimento | `CAD-01`, `ACO-01` |
| Filtros Acolhidos, Desacolhidos, Evadidos e Internados | `DEC-01`, `ACO-01`, `ACO-02`, `ACO-03` |
| Operar uma organização e uma unidade por implantação | `DEC-05`, `ARQ-01/06`, `SEG-02/03`, `ACO-01`, `DOC-05`, [ADR 0004](docs/adr/0004-organizacao-e-unidade-unicas.md) |
| Uma unidade/local com várias casas de moradia internas | `DEC-01`, `DEC-05`, `ACO-01`, `SEG-02`, [ADR 0004](docs/adr/0004-organizacao-e-unidade-unicas.md) |
| Equipe de cuidado cotidiano, equipe técnica e jurídico/administrativo com papéis distintos | `IAM-03`, `SEG-02/03`, `DOC-03/04` |
| Exatamente cinco documentos funcionais: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega | `DEC-06`, `DOC-01` a `DOC-06`, [matriz sanitizada](docs/document-field-matrix.md) |
| Parecer do acolhido relata a situação e formula pedido ao juiz | `DEC-06`, `DOC-02/03/04/06` |
| Levantamento anual de atendimentos e encaminhamentos | `DEC-03`, `ATE-01`, `ATE-02`, `BI-01`, `BI-02`, `BI-03` |
| Números por Saúde, Escola e Reaproximação Familiar | `DEC-03`, `ATE-01`, `BI-01` |
| Destinatário e procedimento judicial estruturados no Parecer do acolhido | `DOC-02`, `DOC-04` |
| Identificar de um a quatro profissionais no rodapé, sem assinatura eletrônica/digital | `DEC-04`, `DOC-03`, `DOC-04` |
| Atendimento em UBS/UPA/Hospital/CAPS, motivo e acompanhante | `SAU-01` |
| Encaminhamentos, exames e realização do exame | `SAU-03`, `ATE-02` |
| Medicações de uso contínuo e atualização após reavaliação | `SAU-02` |
| Medicação de tratamento único | `SAU-04` |
| Anticoncepcionais | `SAU-05` |
| Idas, reuniões, ligações e outras situações escolares | `EDU-02` |
| Avaliar Keycloak e adotar MFA adequado ao cenário | `DEC-07`, `IAM-01`, `IAM-02`, `SEG-01/02/03/04`, [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md) |
| Hospedar com orçamento limitado sem alegar alta disponibilidade | `ARQ-01/02/03/04/07/08`, `OPS-02`, [ADR 0003](docs/adr/0003-infraestrutura-contabo-swarm-redis.md), [arquitetura-alvo](docs/architecture/target-architecture.md) |
| Conta Gmail de consumidor sem senha compartilhada, com OAuth web futuro | `DEC-08/08A`, `EML-01`, `EML-02`, `EML-03`, `SEG-04/07` |
| Extrair prazos de e-mail com revisão humana | `DEC-09`, `DEC-11`, `EML-03`, `EML-04`, `EML-05` |
| Criar propostas de agenda e lembrar 1 dia antes | `DEC-09`, `DEC-10`, `EML-05`, `AGD-01/02`, `PWA-02` |
| Instalar no desktop/mobile como PWA | `DEC-10`, `PWA-01`, `FE-01` |
| Notificação in-app/Web Push sem PII | `DEC-10`, `PWA-02`, `SEG-02/03/04` |
| Não usar LLM nesta fase; eventual reabertura formal e PoC apenas sintética | `DEC-11`, `EML-04`, `LGPD-02`, `SEG-07` |

## 11. Referências normativas oficiais

- [Lei Geral de Proteção de Dados Pessoais — Lei nº 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm), em especial princípios, direitos, art. 14 (crianças e adolescentes) e arts. 46–49 (segurança e sigilo).
- [Enunciado da ANPD sobre tratamento de dados de crianças e adolescentes](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-divulga-enunciado-sobre-o-tratamento-de-dados-pessoais-de-criancas-e-adolescentes): qualquer hipótese legal aplicável deve preservar o melhor interesse.
- [Guia de Segurança da Informação da ANPD](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-publica-guia-de-seguranca-para-agentes-de-tratamento-de-pequeno-porte): medidas administrativas, controle de acesso, proteção dos dados, vulnerabilidades, comunicações e nuvem.
- [Regulamento de Comunicação de Incidente de Segurança — Resolução CD/ANPD nº 15/2024](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-aprova-o-regulamento-de-comunicacao-de-incidente-de-seguranca) e [canal oficial de comunicação](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca-cis).

## 12. Referências técnicas e jurídicas específicas

### Identidade e alternativas OIDC

- [Laravel Fortify 13.x](https://laravel.com/docs/13.x/fortify): backend nativo escolhido e referência dos endpoints TOTP/QR/recovery; a política de QR pós-confirmação e recovery codes individualmente hasheados exige restrição/substituição conforme [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md).
- [Keycloak — Server Administration Guide](https://www.keycloak.org/docs/latest/server_admin/) e [Configuring Keycloak for production](https://www.keycloak.org/server/configuration-production): referência para reavaliação futura; Keycloak não foi selecionado nem implantado.
- [OpenID Connect Core 1.0](https://openid.net/specs/openid-connect-core-1_0.html) e [RFC 9700 — OAuth 2.0 Security Best Current Practice](https://www.rfc-editor.org/rfc/rfc9700.html): referência somente se os gatilhos futuros reabrirem a decisão de OIDC.

### Arquitetura e operação

- [ADR 0003 — Infraestrutura Contabo, Swarm e Redis](docs/adr/0003-infraestrutura-contabo-swarm-redis.md): decisão de fase 1, limitações de nó único, classificação dos três usos de Redis e gatilhos de saída.
- [ADR 0004 — Organização e unidade únicas](docs/adr/0004-organizacao-e-unidade-unicas.md): um contexto explícito por implantação, fixado no backend, com migração aditiva e gatilho de reavaliação antes de qualquer expansão.
- [Arquitetura-alvo](docs/architecture/target-architecture.md): fases, responsabilidades, fluxos, classificação de dados, falhas, segurança/LGPD, deploy e premissas de capacidade.
- [Laravel Horizon 13.x](https://laravel.com/docs/13.x/horizon): operação das filas Redis e limitação oficial de incompatibilidade com Redis Cluster.

### Gmail, Google Workspace e autorização

- [Gmail API — Configure push notifications](https://developers.google.com/workspace/gmail/api/guides/push), [`users.history.list`](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.history/list) e [Synchronize clients](https://developers.google.com/workspace/gmail/api/guides/sync): `watch`, Pub/Sub, bootstrap/full sync inicial, paginação, reconciliação, 404 e full sync de mailbox de usuário.
- [Choose Gmail API scopes](https://developers.google.com/workspace/gmail/api/auth/scopes) e [Google Workspace API User Data and Developer Policy](https://developers.google.com/workspace/workspace-api-user-data-developer-policy): menor escopo, `gmail.readonly` restrito, verificação/avaliação e Limited Use.
- [OAuth 2.0 for server-to-server applications](https://developers.google.com/identity/protocols/oauth2/service-account) e [OAuth 2.0 policies](https://developers.google.com/identity/protocols/oauth2/policies): service account, domain-wide delegation, impersonação, credenciais e revogação.
- [OAuth 2.0 for Web Server Applications](https://developers.google.com/identity/protocols/oauth2/web-server): consentimento web, acesso offline e refresh token revogável para a futura PoC sintética da conta Gmail de consumidor.

### PWA, cache e Web Push

- [web.dev — Service workers](https://web.dev/learn/pwa/service-workers), [Serving/caching strategies](https://web.dev/learn/pwa/serving), [Offline data](https://web.dev/learn/pwa/offline-data) e [Back/forward cache](https://web.dev/articles/bfcache): network-only, Cache Storage, IndexedDB, cache HTTP e bfcache.
- [WebKit — Web Push for Web Apps on iOS and iPadOS](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/): suporte de Web Push para web apps adicionadas à Home Screen.

### Prazos processuais

- [Código de Processo Civil — Lei nº 13.105/2015](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2015/lei/l13105.htm), especialmente arts. 213 e 219–224: horário, dias úteis, suspensão, termo e prorrogação; não autoriza cálculo automático sem definir jurisdição e fonte.
- [CNJ — Resolução nº 244/2016](https://atos.cnj.jus.br/atos/detalhar/2349) e [Recomendação nº 44/2020](https://atos.cnj.jus.br/atos/detalhar/4838): suspensão nacional e publicação/versionamento de calendários de feriados locais. Atos do tribunal/jurisdição competente também precisam ser fonte oficial versionada.

## 13. Fora de escopo até decisão explícita

- Prontuário médico completo, prescrição clínica, cálculo de dose ou recomendação automatizada.
- Assinatura eletrônica/digital e cadeia externa de assinatura não fazem parte da fase atual definida em `DEC-04`; eventual adoção futura exige nova decisão jurídica e técnica.
- Aplicativo mobile nativo; web responsiva e PWA estão no escopo de `DEC-10` e `PWA-01/02`.
- Microserviços, data lake ou analytics complexo antes de medir volume e concluir `ARQ-08`.
- Integração automática com Judiciário, Saúde ou Educação sem base legal, contrato, segurança e especificação oficiais.
- Compartilhamento de senha, login do sistema pela caixa Gmail ou acesso coletivo sem atribuição individual.
- Uso de Gmail API `users.watch/history.list` diretamente sobre Google Group/Collaborative Inbox sem rota oficialmente suportada e comprovada na PoC.
- Criação automática de agenda, execução de ferramentas ou envio externo a partir do conteúdo do e-mail sem revisão humana autorizada.
- Determinação automática de prazo jurídico; o sistema apenas propõe dados para conferência profissional da fonte, termo inicial, jurisdição e calendário.
- Armazenamento indiscriminado de corpos/anexos de e-mail, cache offline de dados pessoais/sensíveis ou push com PII na tela bloqueada.
- Uso de LLM nesta fase. Eventual reabertura futura exige decisão formal, RIPD, fornecedor/DPA, garantia de `no-training`, Limited Use, minimização, revisão humana e avaliação exclusivamente sintética.
