# Plano de evolução do Centro de Acolhimento

> PRD enxuto + backlog técnico. Documento vivo, criado em 31/07/2026 e atualizado em 28/08/2026 para consolidar as decisões documentais de `DEC-06/DOC-04`, o acesso uniforme da equipe técnica em `IAM-03/SEG-02` e o histórico auditável de `SEG-03`, preservando as decisões arquiteturais anteriores.

## 1. Como usar este documento

- Marcar uma tarefa como concluída somente quando todos os seus critérios de aceite e a definição de pronto forem atendidos.
- Prioridade **P0**: bloqueia o uso com dados reais ou cria risco grave de segurança/perda de dados.
- Prioridade **P1**: necessidade operacional explícita dos usuários.
- Prioridade **P2**: melhora confiabilidade, escala, manutenção ou experiência.
- Tamanhos `P/M/G/XG` são relativos e devem ser reestimados após as decisões de produto e o desenho do banco.
- Os identificadores (`SEG-01`, `SAU-02` etc.) devem ser usados em issues, commits e pull requests.

## 2. Resultado esperado

Transformar a POC em um sistema de produção seguro e auditável que:

1. mantenha uma ficha única de cada criança/adolescente e o histórico de seus acolhimentos;
2. registre Saúde, Educação, atendimentos e encaminhamentos como dados estruturados;
3. gere documentos corretos, com destinatários e signatários flexíveis;
4. entregue indicadores anuais sem controles paralelos manuais;
5. proteja dados pessoais e dados sensíveis conforme a LGPD, com especial atenção ao melhor interesse de crianças e adolescentes;
6. possa crescer em usuários, registros e arquivos sem perda de dados ou reescrita prematura da aplicação; uma segunda unidade ou organização exige reabrir `DEC-05` antes da expansão;
7. use contas individuais, autenticação forte e ciclo de acesso revogável, sem transformar uma caixa de e-mail compartilhada em identidade de usuário;
8. transforme mensagens institucionais em **propostas** rastreáveis de prazos e agenda, sempre sujeitas a revisão humana antes de produzir compromisso operacional; e
9. ofereça instalação PWA e lembretes no aplicativo/Web Push com minimização de dados, sem expor PII ou informação assistencial na tela bloqueada.

### Contexto institucional confirmado

- A aplicação atende uma **organização de acolhimento institucional** — descrita pelo solicitante também como “orfanato” — que opera uma única unidade/local/complexo físico.
- Dentro desse complexo existem várias casas destinadas à moradia e ao cuidado cotidiano de crianças e adolescentes. Essas casas são localizações internas da unidade única; não são unidades operacionais, organizações ou tenants.
- O trabalho envolve equipe de cuidado cotidiano, equipe técnica e pessoal jurídico/administrativo. Papéis, profissões, atribuições legais e permissões exatas não são presumidos e permanecem sujeitos à matriz de `SEG-02`.
- A organização responde a juízes e demais atores/órgãos do sistema de Justiça por meio dos cinco documentos funcionais aprovados em `DEC-06`: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais. O sistema apoia elaboração, rastreabilidade e prazos, mas não substitui revisão profissional nem determina automaticamente efeitos jurídicos.
- Ainda é decisão de produto em `DEC-01` se a alocação e a transferência entre casas precisam de registro estruturado e histórico próprio. Até a aprovação, “casa” não deve virar apenas um campo atual sobrescrevível nem ser confundida com a unidade.
- O panorama consolidado de arquitetura, módulos, dependências, riscos, roadmap e primeiros cortes executáveis está no [blueprint do projeto](docs/architecture/project-blueprint.md). `TODO.md` continua sendo a fonte de verdade dos estados e prioridades.

## 3. Estado atual verificado no código

### Já existe, mas precisa ser confirmado pelos usuários

- A foto pode ser enviada no cadastro e o template atual do PDF do PIA tenta exibi-la em “Dados de identificação”. Isso funciona com arquivo local disponível, mas não é confiável no deploy atual e ainda precisa de teste visual/regressivo (`DOC-01`).
- A agenda é **compartilhada na visualização**: o backend carrega todos os eventos para todos os usuários autenticados. Admin pode alterar tudo; servidor altera eventos do próprio setor. A interface não explica essa regra nem oferece filtros de escopo (`AGD-01`).
- O PIA tem campos narrativos de Saúde e Educação, mas não há módulos/abas para registrar atendimentos, medicações, exames, escola ou contatos ao longo do tempo.

### Lacunas funcionais confirmadas

- O cadastro aceita apenas `acolhida` e `desligada`; não representa evasão, internação, retorno nem histórico de mudança.
- A ficha de ingresso não contém todos os dados escolares nem a pessoa/órgão que conduziu o acolhimento.
- Não existe levantamento anual estruturado de atendimentos e encaminhamentos.
- O Parecer do acolhido não tem destinatário/procedimento judicial estruturados nem seleção flexível de signatários.

### Novas necessidades em descoberta (`PRD-IAM-EMAIL-PWA`)

- A análise de identidade comparou Keycloak autogerido, IdP gerenciado e autenticação Laravel. `DEC-07` escolheu Laravel Fortify com TOTP para toda conta humana atual/futura na aplicação única, incluindo os quatro usuários iniciais; OIDC/Keycloak volta à decisão apenas pelos gatilhos do [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md).
- Foi solicitada integração com uma caixa Gmail institucional compartilhada para identificar possíveis prazos e propor eventos. Ainda é obrigatório confirmar se a conta é **Google Workspace ou `@gmail.com` de consumidor**, pois isso muda delegação, governança, autorização OAuth e viabilidade operacional.
- O código atual não contém integração Gmail/Google OAuth, processamento por Gmail API/Cloud Pub/Sub, PWA/service worker ou Web Push. Nenhuma dessas capacidades está autorizada para dados reais antes dos marcos e decisões deste documento.
- Uso de LLM para extração não está decidido. A primeira versão deve admitir regras determinísticas e revisão humana; qualquer LLM depende de RIPD, avaliação do fornecedor e controles previstos em `DEC-11`.

### Bloqueadores para dados reais

- O README identifica a aplicação como POC sem segurança de produção e destinada apenas a dados fictícios.
- `ARQ-01A` isolou o bootstrap SQLite efêmero e o reset sintético exclusivamente no runtime legado `VERCEL`; local/CI usam PostgreSQL. A remoção ocorre no cutover Contabo. A implantação PostgreSQL definitiva, com TLS, roles mínimas, backup/PITR e restore, continua bloqueada em `ARQ-01/03/07`.
- Fotos e documentos são gravados no disco `public` e expostos por URL; não há autorização no download, expiração de link, varredura antimalware ou armazenamento durável no deploy atual.
- Cadastro público GET/POST: **CONTROLADO por `SEG-01A` em 26/08/2026**; as rotas estão ausentes e PHPUnit/E2E desktop e mobile provaram a negação direta e a preservação do login. `SEG-01` permanece aberto para Fortify/TOTP, lifecycle, recuperação e revogação de sessões.
- O acesso é amplo: qualquer usuário autenticado visualiza toda a base; algumas alterações/exclusões de criança e familiar não passam por Policies específicas.
- Não há trilha de auditoria de leituras, downloads, exportações e mudanças; exclusões são definitivas e podem apagar registros relacionados em cascata.
- A suíte de testes cobre principalmente o esqueleto de autenticação/perfil, não os fluxos de negócio, permissões, PDFs ou relatórios.
- O diretório local não versionado `docs/formatacoes` contém seis PDFs oficiais/28 páginas, inclusive exemplos preenchidos com dados reais e um PIA escaneado sem camada de texto. Em 26/08/2026 houve autorização explícita para inspeção local e sanitizada de estrutura/campos; nenhum valor foi transcrito e o resultado está em [`docs/document-field-matrix.md`](docs/document-field-matrix.md). Os originais e renderizações não podem ser copiados, versionados nem usados em teste/prompt/CI; sanitização, retenção e eventual descarte continuam dependentes de `LGPD-01/02/03`.

**Regra de lançamento:** não inserir dados reais antes de concluir o marco P0, inclusive o corte bloqueante de capacidade/continuidade de `ARQ-07`, testar restauração integral e aprovar formalmente o go-live.

## 4. Decisões de produto

- [ ] **DEC-01 — Definir o significado dos quatro estados** (P1, P)
  - Decidir se `internado` e `evadido` substituem `acolhido` ou representam uma ausência temporária enquanto a responsabilidade do acolhimento permanece.
  - Recomendação: manter um **episódio de acolhimento** aberto e registrar uma **situação operacional atual** (`na_unidade`, `evadido`, `internado`) até retorno ou desacolhimento.
  - Decidir também se cada criança/adolescente tem uma alocação em uma das casas internas e se mudanças entre casas exigem histórico append-only com período, motivo e ator. Casa é localização interna, nunca unidade/tenant.
  - Aceite: glossário assinado pela coordenação, incluindo transições permitidas, relação entre situação operacional e casa, necessidade ou dispensa explícita do histórico entre casas e exemplos sintéticos.

- [ ] **DEC-02 — Definir agenda compartilhada, setorial e pessoal** (P1, P)
  - Recomendação: agenda de equipe como padrão, com filtros “Minha agenda”, “Meu setor” e “Toda a equipe”; evento pessoal somente se houver necessidade aprovada e nunca para esconder informação assistencial relevante.
  - Aceite: regra de visibilidade e edição aprovada por perfil de usuário.

- [ ] **DEC-03 — Fechar a taxonomia dos indicadores anuais** (P1, M)
  - Definir o que conta como `atendimento`, `encaminhamento`, `retorno` e `acompanhamento` e quais áreas entram no informativo: Saúde, Educação/Escola, Psicologia, Serviço Social, Reaproximação Familiar e outras.
  - Definir se os números representam ocorrências, pessoas únicas ou ambos e como evitar dupla contagem.
  - Aceite: planilha/dicionário de indicadores com exemplos e validação da diretoria.

- [ ] **DEC-04 — Definir o que “assinatura” significa** (P1, P)
  - Fase recomendada: seleção de 1 a 4 profissionais e blocos nominais/linhas de assinatura no PDF.
  - Decidir com jurídico se será necessário fluxo de assinatura eletrônica/digital com evidências, certificado ICP-Brasil ou integração externa; isso é um escopo separado de apenas imprimir nomes.
  - **Recomendação ainda não aprovada para a fase inicial:** o sistema preserva o PDF final original como artefato imutável com SHA-256 próprio. Cada rodada de assinatura externa recebe o artefato corrente, gera um **novo artefato assinado** sem substituir os anteriores e registra SHA-256 próprio, hash do artefato-pai esperado, ordem, signatário selecionado, horário, estado e resultado da validação. O último artefato validado é apenas o corrente da cadeia; o original e todas as etapas permanecem preservados.
  - As pessoas selecionadas assinariam externamente, em sequência, pelo [Assinador GOV.BR](https://www.gov.br/governodigital/pt-br/identidade/assinatura-eletronica), usando contas pessoais nível prata ou ouro. A reimportação vincularia o novo artefato ao pai esperado e registraria o resultado da conferência de integridade/autoria realizada no [VALIDAR do ITI](https://validar.iti.gov.br/). Upload com pai antigo, cadeia bifurcada, conteúdo adulterado, repetição ou ordem inesperada deve ser rejeitado ou colocado em reconciliação explícita, nunca substituir silenciosamente a cadeia oficial.
  - O serviço de assinatura GOV.BR é informado como gratuito para o cidadão elegível na [página oficial do serviço](https://www.gov.br/pt-br/servicos/assinatura-eletronica), mas isso não oferece à aplicação SLA, integração contratual, suporte dedicado nem garantia de manutenção futura da oferta. Não alegar integração automática com GOV.BR/VALIDAR, validade ou aceitação judicial apenas por esse fluxo.
  - Antes da adoção, executar piloto exclusivamente sintético com 1, 2, 3 e 4 assinaturas sequenciais, comprovando preservação das assinaturas anteriores e da cadeia; cobrir também upload fora de ordem, repetido, concorrente, adulterado e com resultado de validação indeterminado, sem substituir artefato oficial nem aceitar bifurcação silenciosa.
  - A [Lei nº 14.063/2020](https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2020/lei/l14063.htm) diferencia assinatura avançada e qualificada no art. 4º, mas o parágrafo único do art. 2º exclui os processos judiciais daquele capítulo. Portanto, o juízo/assessoria jurídica precisa confirmar se aceita assinatura avançada GOV.BR para cada classe de documento; se exigir assinatura qualificada, será necessário certificado ICP-Brasil ou plataforma expressamente aceita, provavelmente com custo.
  - Aceite: modalidade, valor jurídico esperado e documentos abrangidos registrados em ADR.

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
    - Esta decisão não aprova template jurídico, assinatura digital, retenção, acesso, finalidade nem obrigatoriedade definitiva. `DEC-06` permanece aberto até revisão formal de uma técnica e aprovação da equipe técnica/coordenação; `DEC-04` continua decidindo modalidade e valor jurídico das assinaturas.
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
  - Escolher explicitamente entre três modelos distintos: **(a)** mailbox licenciada de usuário Google Workspace, com acesso humano por contas individuais delegadas; **(b)** Google Group com Collaborative Inbox, que é um grupo e não uma mailbox de usuário; ou **(c)** conta `@gmail.com` de consumidor, sem recursos administrativos de domínio e com limitações documentadas. Não compartilhar senha em nenhum modelo.
  - Gmail API `users.watch` e `users.history.list` observam mailbox de usuário. Não assumir que funcionam em Collaborative Inbox: se o modelo for Group, aprovar outra ingestão suportada, como entrega governada de cópia a uma mailbox licenciada dedicada, ou bloquear a integração Gmail API até existir rota oficial validada.
  - Separar acesso humano do principal da aplicação. Decidir entre OAuth web com acesso offline/refresh token por mailbox e, somente em Workspace, service account com domain-wide delegation; registrar sujeito impersonado, administrador autorizador, escopos, armazenamento/rotação de credenciais, revogação e blast radius.
  - `gmail.readonly` é escopo restrito. Compatibilidade com Google Workspace API User Data and Developer Policy/Limited Use, verificação OAuth e eventual avaliação de segurança são gates de go-live, inclusive para armazenamento/transmissão, acesso humano e cópias persistentes.
  - Label/remetente servem apenas para roteamento e priorização, nunca para autorização ou confiança. Definir governança, versionamento e auditoria de labels/domínios; avaliar DKIM, SPF, DMARC, domínio/origem e encaminhamentos como sinais, mantendo todo conteúdo não confiável.
  - Decidir quais metadados/conteúdos mínimos serão necessários, se trecho durável é permitido, retenção/exclusão e alternativa por referência/hash. Corpo e anexo não serão armazenados por padrão.
  - Aceite: ADR e PoC exclusivamente sintética da rota escolhida comprovam modelo, principal humano/aplicação, OAuth offline ou delegação, sujeito/impersonação, escopos, revogação/blast radius, ingestão suportada e conformidade Limited Use.

- [ ] **DEC-09 — Definir semântica de prazos e revisão humana** (P1, M)
  - Definir jurisdição/tribunal, fonte e timestamp autoritativos da comunicação, evento que forma o termo inicial, regra de publicação/intimação e timezone `America/Sao_Paulo`; e-mail recebido é indício, não fonte jurídica automática.
  - Versionar o calendário oficial aplicável, com feriados nacionais/locais, suspensões extraordinárias, expediente, inclusões/exclusões, cutoff e regra de dias úteis/corridos. Preservar a versão usada em cada proposta.
  - Diferenciar data literal informada, data calculada, prazo, audiência/compromisso e mera menção. Usuário autorizado confirma explicitamente termo inicial, fonte, base de contagem e data proposta antes da agenda; na dúvida, marcar `ambigua` sem calcular.
  - Definir responsável, participantes, quem recebe lembrete e tratamento de mensagens encaminhadas, atualizadas ou canceladas. Label/remetente nunca substitui confirmação de autenticidade ou autorização.
  - O sistema apoia triagem e proposta; **não determina prazo jurídico nem substitui conferência profissional**. Fonte/evidência mínima, confiança, autoria, correção e cancelamento permanecem auditáveis e append-only.
  - Aceite: glossário e exemplos sintéticos cobrem termo inicial, jurisdições, fonte/timestamp, calendário versionado, suspensões, cutoff, datas literais/calculadas, ambiguidade, atualização/cancelamento e aprovação/rejeição.

- [ ] **DEC-10 — Definir PWA, canais e política de notificações** (P1, M)
  - Aprovar navegadores/dispositivos suportados, instalação em desktop/mobile, experiência offline e fallback quando Web Push estiver indisponível. No iOS, Web Push exige instalação na Home Screen.
  - Adotar notificações no aplicativo e Web Push por opt-in. Push deve ser genérico e sem PII, nome, processo, diagnóstico, prazo sensível ou conteúdo assistencial na tela bloqueada.
  - Definir antecedência padrão de 1 dia, quiet hours, timezone, público receptor, subscriptions por dispositivo e revogação em logout, inativação, troca de vínculo ou perda do dispositivo.
  - Aceite: política aprovada cobre consentimento, conteúdo mínimo, expiração/revogação, fallback, acessibilidade e comportamento por plataforma.

- [ ] **DEC-11 — Decidir se IA pode apoiar a extração de prazos** (P1, M)
  - **LLM não está decidido nem autorizado para dados reais.** Antes de adotá-lo, concluir RIPD específico, avaliação de fornecedor/suboperadores/região, DPA, garantia contratual de `no-training` (não usar os dados para treinamento), minimização, retenção e plano de saída.
  - Tratar e-mail como entrada não confiável: conteúdo não pode alterar instruções, acionar ferramentas, chamar URLs, executar código ou contornar autorização. Prompt injection e exfiltração integram os testes de segurança.
  - Antes de transferir dado Gmail ou derivação a eventual LLM, demonstrar compatibilidade com Google Workspace API User Data and Developer Policy/Limited Use e decidir transmissão, acesso humano do fornecedor, persistência/cópias, localização, exclusão e evidência de descarte.
  - Comparar regras determinísticas e eventual modelo usando apenas corpus sintético, com métricas de precisão/recall, falsos positivos/negativos, custo e revisão humana obrigatória; reavaliar periodicamente fornecedor, política, modelo, finalidade e riscos.
  - Aceite: decisão formal registra se IA será usada, finalidade, dados permitidos, fornecedor/contrato, Limited Use, limiares, supervisão humana, periodicidade de reavaliação e critérios de suspensão.

## 5. Backlog funcional

### Épico A — Acolhimento, movimentações e filtros

- [ ] **ACO-01 — Criar episódios de acolhimento e histórico de movimentações** (P1, G; depende de `DEC-01` e implementa `DEC-05`)
  - Criar `acolhimentos` com entrada, motivo, processo, unidade, órgão/pessoa condutora e eventual saída/motivo.
  - Criar histórico append-only de movimentações com tipo, início, fim/retorno, local, observação, autor e timestamps.
  - Se `DEC-01` aprovar histórico de casa, modelar alocação/transferência interna sem transformar casa em unidade e sem sobrescrever a localização histórica; se dispensar, registrar a justificativa e não criar uma abstração prematura.
  - Migrar `data_acolhimento`, `motivo_acolhimento` e `status` atuais sem perder informação.
  - Não sobrescrever fatos antigos ao atualizar a situação atual.
  - Aceite: é possível registrar acolhimento → evasão → retorno → internação → retorno → desacolhimento, vendo toda a linha do tempo e o estado atual correto.

- [ ] **ACO-02 — Adicionar filtros Acolhidos, Desacolhidos, Evadidos e Internados** (P1, M; depende de `ACO-01`)
  - Exibir os quatro filtros pedidos, mais “Todos”, com contagem por situação.
  - Preservar busca, filtro e paginação na URL.
  - Definir rótulos inclusivos e consistentes em toda a interface.
  - Aceite: cada registro aparece em exatamente o filtro definido pelas regras de `DEC-01`; contagens batem com consulta de banco e há teste automatizado para todas as transições.

- [ ] **ACO-03 — Mostrar situação e histórico na ficha do acolhido** (P1, M)
  - Destacar situação atual, desde quando, local atual e último responsável pelo registro.
  - Exibir linha do tempo de entradas, saídas, evasões, internações e retornos.
  - Aceite: a equipe identifica a situação atual sem abrir observações de texto livre.

### Épico B — Ficha de ingresso e cadastro

- [ ] **CAD-01 — Completar a ficha de ingresso** (P1, G; depende de `DEC-06` e `ACO-01`)
  - Incluir, no mínimo, informações escolares vigentes e quem/qual órgão conduziu ao acolhimento.
  - Modelar escola/matrícula e órgão/pessoa como dados estruturados, com opção “Outro” e complemento; evitar um único campo narrativo para tudo.
  - Reaproveitar os dados no PIA e demais documentos, sem redigitação.
  - Aceite: todos os campos da ficha oficial estão mapeados; obrigatoriedade e validação coincidem com o documento aprovado; o cadastro gera uma ficha de ingresso conferível.

- [ ] **CAD-02 — Melhorar preenchimento, validação e qualidade dos dados** (P2, M)
  - Organizar o formulário em etapas: identificação, documentos, responsáveis, processo, ingresso, Educação e revisão.
  - Aplicar máscaras e validação coerente para CPF, CNS/Cartão SUS, datas e número de processo, sem rejeitar exceções legítimas.
  - Detectar possíveis duplicidades antes de criar cadastro.
  - Exibir indicador de completude e campos pendentes.
  - Aceite: formulário funciona em celular e desktop, é navegável por teclado e não perde dados ao retornar uma etapa.

- [ ] **CAD-03 — Tratar foto com privacidade e ciclo de vida** (P0, M; depende de `ARQ-02`)
  - A foto referida em `CAD-03/DOC-01` é o retrato da criança/adolescente solicitado no feedback: fica no perfil privado e pode, mediante decisão, ser incluído no PIA; não é foto de documento, assinatura nem imagem escaneada do formulário.
  - Recomendação ainda não aprovada: cadastro da foto opcional e inclusão no PIA por escolha explícita a cada geração, desativada por padrão até `DOC-01` ser decidido.
  - Armazenar foto de forma privada, com autorização de acesso e miniaturas geradas fora da requisição.
  - Definir finalidade/base legal, quem pode visualizar, prazo de retenção e comportamento após desacolhimento.
  - Remover metadados EXIF e validar conteúdo real do arquivo.
  - Aceite: não existe URL pública permanente; acesso negado é testado; foto continua disponível nos fluxos autorizados e no PIA.

- [ ] **CAD-04 — Criar ficha unificada e passagem de caso** (P1, G; depende de `ACO-01/03`, `SEG-02/03` e módulos canônicos)
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

- [ ] **AGD-01 — Tornar a regra de compartilhamento explícita** (P1, M; depende de `DEC-02`)
  - Informar na tela quem vê o evento e quem pode alterá-lo.
  - Adicionar escopo/visibilidade e filtros aprovados: pessoal, setor e equipe.
  - Paginar ou buscar eventos por intervalo visível; hoje todos os eventos são carregados de uma vez.
  - Aceite: dois usuários de setores diferentes enxergam/editam exatamente o que a matriz de acesso determina; calendário não degrada com grande histórico.

- [ ] **AGD-02 — Adicionar responsáveis, participantes e lembretes** (P2, M)
  - Separar criador, responsável principal e participantes.
  - Permitir lembretes configuráveis e vínculo com atendimento, exame, encaminhamento ou tarefa.
  - Aceite: férias/afastamentos podem ser cobertos por outro responsável sem mudar a autoria original.

### Épico G — Documentos e PDFs

- [ ] **DOC-01 — Validar e robustecer a foto no PIA** (P1, P; depende de `CAD-03`)
  - A foto é o retrato privado da criança/adolescente armazenado no perfil, conforme solicitação dos usuários. Confirmar posição, tamanho, proporção e se a inclusão será obrigatória, opcional por documento ou automática quando houver foto.
  - Recomendação ainda não aprovada: cadastro opcional; a cada geração do PIA, inclusão por escolha explícita e desativada por padrão.
  - Fazer o gerador ler a imagem do armazenamento privado e tratar ausência/erro sem quebrar o PDF.
  - Aceite: testes com foto retrato/paisagem e sem foto; PDF correto em desenvolvimento e produção.

- [ ] **DOC-02 — Estruturar destinatário e procedimento judicial do Parecer do acolhido** (P1, M)
  - Campos candidatos: órgão/instituição, nome da pessoa, cargo/função, forma de tratamento, vara, comarca, processo/procedimento e complemento de endereçamento.
  - O destinatário pode representar juiz(a) ou outro ator autorizado do sistema de Justiça; a obrigatoriedade de cada subcampo permanece no checklist de `DEC-06`.
  - Salvar snapshot no documento para que mudanças cadastrais futuras não alterem o Parecer finalizado.
  - Aceite: dados de endereçamento e procedimento selecionados aparecem na posição aprovada do Parecer, sem redigitação quando já existirem em fonte canônica.

- [ ] **DOC-03 — Selecionar signatários por documento** (P1, G; depende de `DEC-04`)
  - Selecionar de 1 a 4 integrantes ativos da equipe, sem dupla fixa e com ordem configurável.
  - Permitir uma pessoa assinar sozinha durante férias/afastamentos.
  - Gravar nome, cargo/função, identificador do usuário e ordem como snapshot do documento.
  - Renderizar blocos de assinatura responsivos no fim do PDF, com quebra de página controlada.
  - Aceite: cenários com 1, 2, 3 e 4 signatários geram PDFs legíveis e preservam os nomes mesmo após alteração do perfil.

- [ ] **DOC-04 — Unificar metadados, UX e templates dos cinco documentos** (P1, G; depende do dicionário de campos de `DEC-06`)
  - Centralizar destinatário, signatários, numeração, situação (`rascunho`, `finalizado`, `cancelado`), versão e emissão.
  - Criar componentes Blade reutilizáveis de cabeçalho, destinatário, assinatura, rodapé e paginação.
  - Atender exatamente os cinco tipos aprovados em `DEC-06`: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais. Não criar Informação, Ofício genérico, Relatório Técnico genérico ou Ofício de encaminhamento do PIA como tipos funcionais.
  - UX documental: organizar em etapas curtas e linguagem de trabalho; priorizar seleção e autopreenchimento de fatos canônicos; revelar campos condicionais progressivamente; permitir rascunho incompleto; validar obrigatórios apenas na finalização; preservar o preenchimento ao navegar entre etapas.
  - Texto longo fica restrito a julgamento profissional ou narrativa indispensável. No Parecer do acolhido, oferecer estrutura guiada de contexto/início, evolução/meio, avaliação, pedido ao juiz e conclusão, com fatos canônicos referenciados e sem geração automática de afirmações.
  - Todo fluxo essencial funciona em celular e desktop, por teclado, com estados de carregamento, vazio, erro e acesso negado. Cada rascunho/detalhe mostra `Atualizado por [usuária] em [data/hora]`, usando o fuso `America/Sao_Paulo` na apresentação.
  - Aceite: os cinco documentos reutilizam metadados/componentes compatíveis, preservam finalidade/ciclo próprios e podem ser preenchidos sem redigitar dados canônicos; nenhum tipo excluído aparece como opção de criação.

- [ ] **DOC-05 — Corrigir concorrência na numeração documental** (P0, M; implementa `DEC-05`)
  - Substituir o cálculo “maior número + 1” distribuído entre tabelas por sequência central transacional, com unicidade por tipo documental/ano/unidade quando o dicionário aprovado exigir numeração.
  - Definir comportamento de número manual, cancelamento e reutilização.
  - Aceite: teste concorrente não gera números repetidos; todo número possui histórico.

- [ ] **DOC-06 — Versionar e finalizar documentos** (P0, G)
  - Separar rascunho editável de versão final imutável.
  - Registrar autor, revisores, signatários, hash, data de finalização e motivo de retificação/cancelamento.
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
  - Recuperação assistida por administrador exige ator atribuível, motivo, verificação reforçada, revogação de sessões/códigos anteriores e novo vínculo TOTP.
  - Bloquear o endpoint padrão de auto-desativação do segundo fator. Remoção de MFA somente ocorre por recuperação/re-enrollment autorizado e auditado, revoga todas as sessões existentes e retorna a conta a `pendente_mfa` até nova confirmação.
  - E-mail OTP não é MFA primário. Eventual recuperação por e-mail usa TTL curto, token de uso único, rate limit, resposta que não enumera contas, verificação reforçada, auditoria e revogação; o reset de senha sozinho não remove ou substitui MFA.

- [ ] **IAM-03 — Administrar contas e o papel da equipe técnica** (P1, G; depende de `IAM-02`, `SEG-02/03/04`)
  - **Decisão humana confirmada em 28/08/2026:** todas as contas ativas classificadas com o papel estável `equipe_tecnica` possuem o mesmo conjunto aprovado de acesso assistencial dentro da única organização/unidade, inclusive visualização e edição conforme as regras do recurso. A administradora gerencia contas e atribui/remove esse papel; não granulariza permissões individuais entre técnicas.
  - Permitir que uma administradora autorizada convide/crie, ative/inative contas individuais e atribua/remova papéis aprovados. A decisão de acesso igual vale somente para equipe técnica; não concede automaticamente o mesmo acesso a cuidadoras, jurídico, administrativo, visitante ou integrações.
  - Proibir que a administradora atribua ou remova de si mesma o papel `equipe_tecnica`, inclusive por alteração indireta, payload adulterado ou troca do ID-alvo. Mudança do papel técnico sempre aponta para conta terceira e usa Policy no backend.
  - Conceder/remover papel exige sessão `mfa_verified` e step-up realizado há no máximo 5 minutos com senha + TOTP corrente, consistente com `IAM-02/SEG-01`. Sessão sem step-up recente falha sem produzir alteração parcial.
  - Manter allowlist fechada dos papéis que o fluxo administrativo comum pode delegar. Papel fora da allowlist, alteração da própria allowlist/política e combinação de papéis não aprovada falham fechado. Sucesso e negação geram auditoria e alerta administrativo sem payload ou valor assistencial.
  - O bootstrap da primeira administradora ocorre uma única vez por procedimento operacional controlado e auditado fora do fluxo comum de gestão de contas. Exige identidade individual predefinida, ambiente autorizado e evidência de conclusão; não usa cadastro público, seed com credencial, conta compartilhada nem endpoint reutilizável, e fica desabilitado após concluir.
  - Toda autorização continua no backend com Policies e escopo fixo de organização/unidade. Conta inativa, usuário fora da equipe técnica, ID trocado e contexto adulterado continuam negados; esconder botão não é controle.
  - Mudanças de conta/papel são auditadas com ator, conta-alvo, ação, resultado e horário UTC, sem payload ou valores assistenciais. Inativação revoga imediatamente sessões, tokens e acessos derivados.
  - Administradora não apaga histórico e não herda acesso assistencial/sensível apenas por administrar contas. Se também atuar como técnica, precisa possuir explicitamente o papel `equipe_tecnica`.
  - Garantir atomicamente que a organização não fique sem administradora efetiva: remover/inativar a última administradora exige sucessora ativa confirmada ou procedimento `break-glass` aprovado. Recuperação e `break-glass` permanecem individuais, atribuíveis, temporários e auditados.
  - Aceites positivos: duas técnicas ativas recebem o mesmo acesso assistencial aprovado; administradora com step-up recente ativa/inativa conta terceira e atribui/remove papel da allowlist com efeito imediato, alerta e auditoria; bootstrap inicial cria apenas a administradora individual predefinida e não pode ser repetido.
  - Aceites negativos: não administradora não gerencia contas; administradora sem papel técnico não acessa conteúdo assistencial; usuário inativo, papel não técnico, ID trocado, autopromoção/autorrebaixamento, papel fora da allowlist, alteração da allowlist e sessão sem step-up recente são negados; duas alterações concorrentes não deixam zero administradoras efetivas.
  - Implementar `break-glass` sem conta compartilhada: identidade atribuível, menor escopo, TTL, duplo controle, alerta imediato, auditoria append-only, rotação/revogação pós-uso e exercício periódico.
  - Auditar sucesso/falha de login, ativação/inativação, cadastro/remoção de MFA, uso/invalidação de código de recuperação, recuperação assistida, revogação e contingência com correlation ID e resultado, sem segredo ou payload sensível.
  - Testes positivos/negativos: visitante e registro direto negados; conta humana nova/não enrolada fica em `pendente_mfa` e não acessa recursos; quatro contas iniciais e toda conta futura obrigadas a TOTP; sessão `password_only` de conta ativa recebe negação direta em recurso, QR, recovery, remoção/re-enrollment e admin, expira/sofre rate limit; TOTP válido rotaciona session ID; campo/endpoint/payload `remember` não cria recaller; cookie recaller fabricado, antigo, roubado ou revogado não autentica nem cria `password_only`/`mfa_verified`; idle de 15 minutos, absoluto de 8 horas e cookie de browser-session expiram conforme contrato; código TOTP inválido, expirado/reutilizado e sob rate limit; QR/segredo disponíveis apenas até confirmação e negados depois; operações MFA de conta ativa negam sessão antiga sem step-up e aceitam senha + fator corrente dentro de 5 minutos; primeiro enrolamento e recuperação administrada seguem suas exceções explícitas; gerações e confirmações simultâneas deixam exatamente uma geração/cutover válido, QR antigo falha e falhas antes/depois do cutover preservam um único fator corrente; `DELETE`/auto-desativação de MFA negado; re-enrollment indevido negado e sessões anteriores revogadas; logout global, inativação, reset, recovery, re-enrollment e perda de dispositivo revogam todos os dispositivos; recovery code válido uma única vez, concorrência/replay/rotação/falha parcial; e-mail não contorna MFA; usuário inativo e sessão revogada negados; admin sem permissão e ID trocado negados; `break-glass` sem duplo controle/expirado negado.
  - Aceite: cada pessoa usa conta própria e TOTP obrigatório; desligamento nega acesso imediatamente; recuperação não cria canal equivalente a MFA fraco; lifecycle, auditoria e contingência seguem o [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md).

- [ ] **EML-01 — Descobrir e provar sinteticamente a rota Google** (P0, M; alimenta `DEC-08/11`; sem dependência de implantação)
  - Inventariar separadamente mailbox licenciada/delegada Workspace, Google Group Collaborative Inbox e `@gmail.com` consumidor; registrar acesso humano, administração, licenciamento, retenção, limitações e saída de cada modelo.
  - Para cada opção, distinguir identidade dos operadores humanos do principal da aplicação. Comparar OAuth com acesso offline por mailbox e service account com domain-wide delegation somente para Workspace, incluindo sujeito impersonado, escopos, revogação e blast radius.
  - PoC usa somente contas/mensagens sintéticas e a rota exata candidata. `users.watch/history.list` só pode ser testado contra mailbox de usuário; Group exige ingestão oficialmente suportada ou cópia governada para mailbox licenciada, caso contrário a opção fica bloqueada.
  - Avaliar elegibilidade do caso de uso e compatibilidade com Google Workspace API User Data and Developer Policy/Limited Use: dados armazenados/transmitidos, acesso humano, trechos/derivações, cópias persistentes, retenção/exclusão e eventual transferência a LLM.
  - Anexos, links e conteúdo remoto não são baixados, seguidos ou processados na PoC. Eventual parser é decisão separada por finalidade/RIPD e depende de storage privado, quarentena, validação de MIME/assinatura real, limites, antimalware e sandbox.
  - Avaliar remetente/domínio, DKIM/SPF/DMARC, encaminhamento e labels apenas como sinais de roteamento; versionar e auditar a configuração, sem tratar sinal técnico ou label como autorização/confiança.
  - Aceite: relatório e PoC reproduzível permitem decidir `DEC-08/11` sem segredo no repositório, acesso real ou suposição de Gmail API para Collaborative Inbox.

- [ ] **EML-02 — Implantar principal e autorização Google aprovados** (P0, M; depende de `DEC-08`, `EML-01`, `LGPD-01/02`, `SEG-04/07`)
  - Implantar somente a rota aprovada: OAuth offline com refresh token revogável por mailbox, ou service account/domain-wide delegation Workspace com sujeito fixo e mínimo; Group sem mailbox suportada permanece bloqueado.
  - Separar delegados humanos do principal técnico, aplicar menor escopo, ambientes/segredos separados, criptografia, rotação, revogação testada e limite de blast radius; nunca registrar credencial, token ou conteúdo.
  - Concluir verificação OAuth/avaliação de segurança exigível e gate Limited Use antes de produção, documentando consentimento/divulgação, transmissão, armazenamento, acesso humano e exclusão.
  - Testes: autorização/revogação, sujeito incorreto, escopo excessivo, principal de ambiente errado, operador sem permissão, rotação e inspeção de logs.
  - Aceite: staging sintético demonstra acesso mínimo e revogável apenas à mailbox/sujeito aprovado; qualquer modelo sem rota suportada falha fechado.

- [ ] **EML-03 — Ingerir mudanças com reconciliação idempotente** (P1, G; depende de `EML-02`, `ARQ-01/04/06`, `BE-02` no corte de idempotência, `SEG-02/03/04`)
  - Na primeira conexão, executar full sync/bootstrap inicial conforme [Synchronize clients with Gmail](https://developers.google.com/workspace/gmail/api/guides/sync), limitado à finalidade, labels e janela temporal aprovadas. Paginar `messages.list` até o fim, buscar somente os campos mínimos de cada mensagem elegível e obter cursor inicial seguro sem ampliar retenção.
  - Para mailbox Gmail aprovada, configurar `users.watch` com Cloud Pub/Sub autenticado, renovar diariamente dentro da validade máxima de 7 dias e alertar antes da expiração. A rota de Group, se aprovada, usa contrato próprio e não simula `users.watch`.
  - Executar reconciliação periódica por `users.history.list` **independente de push**, inclusive quando nenhuma notificação chega; consumir todas as páginas via `nextPageToken` e monitorar `last_successful_sync_at` contra SLO aprovado, com alerta de atraso.
  - No bootstrap, full sync de recuperação ou sync incremental, avançar checkpoint `historyId` somente depois de todas as páginas e efeitos idempotentes correspondentes estarem duráveis. A ordem é: enumerar/paginar o conjunto aprovado, persistir trabalho mínimo, confirmar efeitos/chaves naturais e outbox na transação e só então confirmar o checkpoint; falha antes do commit não avança cursor, e falha após o commit é retomada sem repetir efeito.
  - `HTTP 404`, histórico expirado ou checkpoint inválido aciona full sync controlado por janela/labels/finalidade, com paginação completa, limite, observabilidade e sem ampliar retenção.
  - Definir chave natural por caixa, mensagem/evento de origem e finalidade, protegida por constraint `UNIQUE` no PostgreSQL; transação/outbox confirma efeito e checkpoint, e retry/backoff trata duplicata, atraso e ordem invertida.
  - Labels/domínios filtram roteamento conforme configuração versionada/auditada, nunca autorização. Coletar o mínimo e não armazenar corpo/anexo por padrão; exceção segue `DEC-08`, Limited Use e retenção aprovada.
  - Auditar renovação, bootstrap, sync, faixa/páginas de mensagem/`historyId`, checkpoint, duplicata, full sync e reconciliação sem conteúdo/PII. Testar primeira conexão com múltiplas páginas, Pub/Sub forjado, notificação silenciosamente perdida, paginação incremental, retry, duplicata, ordem invertida, 404/expiração, rate limit, SLO vencido e falhas antes/depois do commit.
  - Aceite: bootstrap e full sync paginam todo o escopo aprovado sem omitir nem duplicar efeito; perda total de push é detectada pelo polling, checkpoint nunca ultrapassa efeito não confirmado, 404 é recuperado controladamente e a constraint impede duplicação concorrente.

- [ ] **EML-04 — Extrair propostas de prazo com evidência e revisão humana** (P1, G; depende de `DEC-09/11`, `EML-03`, `LGPD-02/03`, `SEG-02/03`)
  - Tratar remetente, assunto, corpo, HTML, link, anexo, label e resultados DKIM/SPF/DMARC como entrada não confiável. Sanitizar e impedir execução de ferramentas, chamadas externas, código ou ações de agenda; nunca seguir link, carregar imagem/conteúdo remoto ou baixar/processar anexo por padrão.
  - Eventual parser de anexo exige finalidade e `DEC-08/11` aprovadas, `ARQ-02`/`SEG-05`, quarentena, limites de tipo/tamanho/páginas/descompressão/CPU/memória/tempo, MIME e assinatura real, antimalware e sandbox sem rede. Testar PDF/MIME malformado, arquivo disfarçado, zip bomb/descompressão e timeout/resource exhaustion antes de liberar qualquer conteúdo ao extrator.
  - Começar com extração determinística. Eventual LLM só após `DEC-11`, gate Limited Use, minimização, contrato/no-training e reavaliação periódica, sem autonomia para agir.
  - Criar somente proposta `pendente_de_revisao` ou `ambigua`, distinguindo data literal de calculada e registrando jurisdição sugerida, fonte/timestamp autoritativos pendentes de confirmação, termo inicial, calendário/versionamento, fuso, confiança e responsável sugerido. Se faltar base, não calcular.
  - Evidência durável segue `DEC-08`: trecho mínimo apenas se permitido e com retenção/exclusão; caso contrário guardar referência/identificador e hash minimizado, sem alegar que hash substitui acesso à fonte.
  - Confirmação, correção, rejeição e cancelamento são eventos append-only. Testes sintéticos cobrem termo inicial, fonte, dias úteis/corridos, feriados/suspensões, cutoff, data literal/calculada, audiência, ambiguidade, atualização/cancelamento, duplicata, HTML malicioso e prompt injection.
  - Aceite: nenhuma mensagem determina prazo jurídico, cria agenda ou notifica; proposta explica incertezas/fonte/confiança e exige confirmação humana da base antes do cálculo/uso.

- [ ] **EML-05 — Revisar propostas, criar agenda e lembrar 1 dia antes** (P1, G; depende de `DEC-02/09/10`, `EML-04`, `AGD-01/02`, `PWA-02`)
  - Disponibilizar fila com escopo no backend por setor, papel e vínculo com o caso; separar extrator, revisor, responsável e destinatários. Label/remetente nunca concede acesso.
  - Antes de aprovar, usuário autorizado confirma explicitamente jurisdição, fonte/timestamp, termo inicial, calendário/versionamento, dias úteis/corridos e data literal ou calculada; proposta ambígua não pode avançar sem correção fundamentada.
  - Aprovação cria evento/tarefa vinculada à fonte sem afirmar validade jurídica. Alteração posterior da mensagem gera nova proposta e não muda agenda confirmada silenciosamente.
  - Agendar lembrete padrão 1 dia antes segundo `DEC-09/10`; chave idempotente e transação/outbox impedem duplicação por retry, duplo clique ou reprocessamento.
  - Auditar visualização de evidência, base confirmada, aprovação, correção, rejeição, evento, lembrete e cancelamento. Testar revisor autorizado, sem vínculo, contexto organizacional adulterado, ID trocado, ambiguidade, calendário alterado, correção/cancelamento, concorrência e falha parcial.
  - Aceite: confirmação fundamentada gera exatamente um item de agenda e um lembrete por destinatário/dispositivo elegível, preservando versão da base e histórico; sistema continua apresentado como apoio, não cálculo jurídico autoritativo.

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
  - Testes: permissão concedida/negada, inventário com múltiplos dispositivos, revogação remota de aparelho perdido, IDOR contra dispositivo de outro usuário, subscription expirada, logout/inatividade, usuário sem vínculo, job já enfileirado, retry/duplicata, fallback e payload sem PII; confirmar que aparelho revogado não recebe reenvio.
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
  - Substituir verificações dispersas por Laravel Policies/Gates para ver, criar, alterar, finalizar, excluir, baixar e exportar.
  - **Matriz aprovada para a equipe técnica:** toda conta ativa com papel `equipe_tecnica` recebe o mesmo conjunto assistencial aprovado de leitura e edição dentro da organização/unidade únicas; não criar exceção individual por técnica. Finalizar, retificar, cancelar, baixar, exportar, auditar e administrar contas continuam ações distintas conforme o recurso e a matriz aprovada.
  - A uniformidade vale somente para equipe técnica. Definir separadamente as permissões de cuidadoras, jurídico, administrativo, visitante, integrações e administração; dados de sensibilidade especial, downloads/exportações e auditoria podem exigir ação/finalidade adicional sem criar discricionariedade individual entre técnicas.
  - Todo usuário pertence à unidade única, sem vínculo M:N ou seleção de unidade.
  - Aplicar o contexto fixo de organização/unidade no backend em todas as consultas e mutações, não apenas esconder botões. Rejeitar parâmetros ou payloads que tentem escolher ou trocar esse contexto.
  - Aceite: matriz automatizada prova igualdade de acesso assistencial entre duas contas técnicas ativas e cobre cada perfil/ação; testes negativos impedem IDOR trocando recurso ou ID na URL, negam visitante, conta inativa e usuário sem papel aplicável, e rejeitam contexto organizacional adulterado.

- [ ] **SEG-03 — Criar auditoria inviolável e pesquisável** (P0, G)
  - Registrar login, falhas, visualização de ficha sensível, busca, criação, alteração, finalização, retificação, download, PDF, exportação e acesso negado.
  - Guardar ator, ação, alvo/ID, resultado, horário UTC, unidade, correlation ID e contexto técnico estritamente necessário. Apresentar datas em `America/Sao_Paulo` e mostrar `Atualizado por [usuária] em [data/hora]` na UI.
  - Não copiar payload, narrativa, diagnóstico, documento ou valores sensíveis anteriores/novos para log técnico ou trilha de auditoria. Em uma alteração de nome, a auditoria registra que o campo foi alterado, por quem e quando, sem duplicar o nome anterior/novo.
  - O histórico funcional/versionamento autorizado preserva o estado anterior e a retificação com autoria/data, sem overwrite silencioso; ele não se confunde com log técnico nem amplia acesso. Mutação e evento de auditoria/outbox confirmam atomicamente.
  - Exemplo de apresentação: a linha do tempo mostra `Nome alterado por [usuária] em [data/hora]`; somente ao abrir o histórico funcional com autorização o sistema apresenta a versão anterior e a atual. A trilha de auditoria e o log técnico não copiam nenhum dos dois nomes.
  - Proteger a trilha append-only contra alteração e definir acesso/retenção próprios.
  - Aceite: é possível responder quem criou, alterou, finalizou, retificou, acessou ou exportou um registro e quando; o histórico funcional autorizado recupera a versão anterior, enquanto logs/auditoria não contêm os valores sensíveis modificados.

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
- A decisão de [organização e unidade únicas](docs/adr/0004-organizacao-e-unidade-unicas.md) aprovou um contexto explícito fixado no backend, sem SaaS multi-organização, suporte multiunidade ou seleção de unidade. Modelo, backfill e testes correspondentes **ainda não estão implementados**.

#### Tecnologias recomendadas e aprovadas para implementação, ainda sujeitas aos gates

- Preservar o monólito modular Laravel + Inertia + React e implementar identidade local com Laravel Fortify, contas individuais e TOTP obrigatório, conforme `DEC-07`.
- Hospedar futuramente em Contabo VPS com Docker Swarm inicialmente single-node. Swarm oferece orquestração e roll-forward, não alta disponibilidade enquanto houver um único nó.
- Usar PostgreSQL autogerido inicialmente na VPS por restrição orçamentária, Redis obrigatório com separação de cache, sessões e filas, e Laravel Queues + Horizon. PostgreSQL e outbox são a fonte de verdade; cache é seletivo e descartável.
- Manter object storage privado como obrigação pendente. Centralizar a execução na VPS nunca transforma disco local ou uma cópia única em armazenamento durável.
- Manter GitHub Actions para CI e Playwright do lockfile para E2E desktop/mobile. Nenhuma nova dependência ou serviço entra sem avaliação de segurança, licença, operação, custo e plano de saída.

#### Decisões tecnológicas pendentes

- `DEC-08`: tipo/modelo da conta Google, delegação/Collaborative Inbox, OAuth, escopos e processo de verificação.
- `DEC-10`: suporte PWA/Web Push, navegadores, canais, fallback e lifecycle por dispositivo.
- `DEC-11`: regras determinísticas versus eventual LLM; LLM não está decidido nem autorizado para dados reais. Groq é somente candidato de PoC futura, sem aprovação de fornecedor, transmissão de dados ou dependência.
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

- [ ] **ARQ-01 — Migrar SQLite para PostgreSQL autogerido inicialmente na VPS** (P0, XG; implementa `DEC-05`)
  - Por restrição do orçamento total aproximado de R$ 60/mês, iniciar com PostgreSQL autogerido na Contabo VPS, em serviço isolado no Swarm, volume persistente dedicado, TLS e rede privada sem porta pública de banco.
  - A aplicação usa role sem `SUPERUSER`, sem criação de banco/role e sem DDL de rotina. Separar credenciais e privilégios mínimos para aplicação, migrations e backup/restore; restringir e auditar o uso das duas últimas.
  - Dimensionar `max_connections` dentro da memória da VPS e limitar pools de aplicação/workers. Adotar PgBouncer/pooling externo somente após medir concorrência e compatibilidade transacional; configurar `statement_timeout`, `idle_in_transaction_session_timeout` e limites de lock adequados por workload.
  - Monitorar e ajustar autovacuum/`ANALYZE`, bloat, locks, conexões e queries com `pg_stat_statements` de acesso restrito, sem parâmetros/payloads sensíveis em logs/métricas. Definir patching e janela de manutenção testada.
  - Configurar backup base + WAL/PITR criptografado com cópia obrigatória fora da VPS e credenciais separadas. O cofre/escrow criptografado e segregado fora da VPS preserva versões necessárias de `APP_KEY` e das chaves/segredos de backup, OAuth, push e TOTP para restaurar dados ainda vigentes; rotação e acesso são auditados e testados. Se houver backup do estado de manager do Swarm, sua unlock key fica separada desse backup. Nunca versionar segredo no repositório.
  - Executar restauração periódica integral em ambiente isolado e aprovar RPO/RTO antes de dados reais.
  - Remover a cópia do SQLite para `/tmp` e qualquer dependência de filesystem da instância.
  - Criar migração/exportação verificável, constraints, chaves, índices e plano de roll-forward/rollback sem `migrate:fresh`.
  - Introduzir organização cliente e unidade únicas por configuração controlada e migração aditiva; mapear registros e usuários explicitamente, validar órfãos e só aplicar `NOT NULL` após o backfill. Não hardcodear dados reais; índices e unicidades incluem as chaves de contexto apenas quando necessárias à integridade, auditoria ou numeração definidas no ADR 0004.
  - Registrar explicitamente que a VPS única é `single point of failure`. Migrar o banco para nó dedicado ou serviço gerenciado quando RPO/RTO não forem atingidos, houver contenção mensurável de CPU/I/O/memória, crescimento além da capacidade aprovada, necessidade de manutenção sem indisponibilidade ou orçamento/risco justificar menor carga operacional.
  - Aceite: múltiplas réplicas da aplicação no mesmo nó veem dados consistentes; reinício/deploy não perde dados; backup fora da VPS e restore/PITR são testados dentro do RPO/RTO aprovado. Alta disponibilidade não é alegada.

- [ ] **ARQ-02 — Migrar arquivos para object storage privado e durável** (P0, G)
  - Selecionar e usar storage S3-compatible privado, criptografado, com versionamento/lifecycle, URLs temporárias e cópia/replicação fora da VPS conforme RPO/RTO. Fornecedor/região/DPA permanecem pendentes de `SEG-07`.
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

- [ ] **ARQ-07 — Aprovar capacidade mínima e continuidade antes de dados reais** (P0, M)
  - Partir da premissa de quatro usuários iniciais e uma aplicação; estimar carga mínima, volume inicial de dados/anexos e recursos necessários da VPS dentro do orçamento total aproximado de R$ 60/mês.
  - Definir SLO mínimo, RPO, RTO, capacidade/limites de conexões e disco, alertas e responsáveis, registrando que single-node Swarm/uma VPS não oferece alta disponibilidade.
  - Executar restore integral PostgreSQL + WAL/PITR + objetos + chaves/segredos do cofre segregado, sem restaurar sessões revogadas, e smoke test dos fluxos críticos dentro do RPO/RTO.
  - Aceite bloqueante: orçamento, capacidade mínima, SLO/RPO/RTO, alertas e restore integral estão aprovados e evidenciados antes de qualquer dado real.

- [ ] **ARQ-08 — Testar crescimento, desempenho e gatilhos de escala** (P2, M; depende de `ARQ-07`)
  - Levantar concorrência observada, acolhidos, episódios, eventos por ano, tamanho/volume de anexos e crescimento esperado antes de ampliar cache ou serviços.
  - Testar login, busca, lista, agenda por intervalo, gravação, PDF e relatório anual com massa representativa; registrar plano de execução e gargalos.
  - Definir gatilhos mensuráveis para adicionar nó/replicar aplicação, separar Redis/worker, mover PostgreSQL para nó dedicado/gerenciado ou ampliar storage: saturação sustentada de CPU/memória/I/O, atraso de fila, falha de SLO/RPO/RTO, manutenção sem janela aceitável, crescimento de dados/anexos ou nova exigência organizacional.
  - Aceite: relatório de carga registra limites, otimizações e próximo gatilho de escala sem tratar cache amplo, réplica no mesmo nó ou Swarm single-node como alta disponibilidade.

- [ ] **BE-01 — Entregar busca global e contextual autorizada** (P1, G; corte funcional do Marco 2)
  - Agenda deve consultar somente o intervalo visível; seletores de acolhidos precisam busca remota/paginada.
  - Criar busca global e contextual no PostgreSQL, normalizada para acentos e com índices apropriados, por nome, identificador autorizado, processo, tipo/título/número de documento, metadados e campos estruturados relevantes.
  - Aplicar autorização e sensibilidade no servidor **antes** de produzir resultados, totais e snippets. A fase inicial não indexa nem pesquisa o corpo narrativo sensível; futura busca em corpo exige finalidade aprovada, permissão específica, minimização e reavaliação de risco.
  - Agrupar resultados por acolhido e tipo, com filtros, paginação e snippets mínimos; localizar documentos pela localização lógica, nunca por caminho interno de storage. Evitar N+1 e limitar payloads Inertia.
  - Auditar busca e acesso sem registrar termos ou conteúdo sensível. Cache, índices auxiliares e contagens respeitam o mesmo escopo e não podem revelar existência, quantidade ou dado entre permissões.
  - Aceite: testes positivos e negativos cobrem IDOR, usuário inativo/sem vínculo, sensibilidade, acentos, filtros, paginação e cache entre usuários; uma técnica autorizada encontra pessoa, fato estruturado ou documento pela ficha unificada sem receber conteúdo fora do seu escopo.

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
  - Monitorar taxa de erro, latência, filas, banco, storage, login suspeito, exports e falhas de backup.
  - Proteger dashboard Horizon por Gate/Policy backend e restrição de rede quando disponível; definir trim/retenção e redação de jobs/failed jobs/exceções, sem payload assistencial ou credencial.
  - Aceite: runbooks e alertas testados; equipe consegue diagnosticar falha sem acessar conteúdo sensível indevido.

- [ ] **OPS-02 — Formalizar backup, continuidade e recuperação** (P0, G)
  - Backups criptografados de banco e objetos, cópia isolada, política de retenção, restauração periódica e responsáveis substitutos.
  - Manter fora da VPS, em cofre/escrow criptografado e segregado, versões das chaves/segredos ainda necessários para restaurar backups e dados criptografados (`APP_KEY`, backup, OAuth, push e TOTP), com acesso mínimo, rotação, auditoria e restore test. Unlock key de eventual backup do manager Swarm fica separada do artefato.
  - Criar plano de indisponibilidade com procedimento operacional temporário e reconciliação posterior.
  - Aceite: simulado restaura banco + arquivos + chaves necessárias dentro do RPO/RTO aprovado, sem ressuscitar sessão revogada e sem segredo no repositório.

- [ ] **OPS-03 — Gerenciar dependências e vulnerabilidades** (P0, M)
  - Atualizações programadas de Composer/NPM, análise de dependências/SBOM, patches críticos e revisão do runtime PHP não oficial usado no deploy atual.
  - Fixar versões via lockfiles e testar atualização em staging.
  - Evidência em 26/08/2026: `composer audit --locked` encontrou advisories HIGH/MEDIUM em `league/commonmark` anterior a 2.9.0; `npm audit --audit-level=high` encontrou HIGH em `nanoid` anterior a 3.3.18 e MODERATE em `postcss` até 8.5.22. Atualizações dependem da avaliação de compatibilidade e do fluxo de dependência de `RTK.md`; este registro não autoriza alteração de pacote.
  - Aceite: não há vulnerabilidade crítica conhecida sem exceção formal, prazo e mitigação.

## 8. Estratégia de testes e definição de pronto

- [ ] **QA-01 — Criar pirâmide de testes do domínio** (P0, G)
  - Unitários: transições de acolhimento, contagem de indicadores, numeração, permissões, regras de medicação, semântica de prazos e idempotência de mensagens/notificações.
  - Feature/integration: CRUD autorizado, contexto fixo de organização/unidade no servidor, isolamento por setor/papel, uploads privados, auditoria, exports, filas e PostgreSQL real; lifecycle de identidade cobre falhas parciais IdP/local, e Gmail cobre bootstrap paginado, checkpoint, push perdido e full sync com serviços falsos/dados sintéticos.
  - E2E: ingresso → PIA/Parecer do acolhido → Saúde/Educação → agenda → Relatório de visita técnica → relatório anual; acrescentar login/MFA, revisão humana de proposta e lembrete PWA quando os respectivos marcos entrarem.
  - Segurança/PWA: IDOR, usuário inativo/revogado, validações OIDC/JWKS/PKCE negativas quando aplicáveis, prompt injection, Pub/Sub forjado/perdido, escopo OAuth excessivo, anexo malformado/disfarçado/zip bomb/timeout, aparelho revogado com job pendente, payload push sem PII e ausência de props/respostas/filas autenticadas em Cache Storage, cache HTTP, histórico/bfcache, IndexedDB e local/session storage após logout, inativação e troca de usuário.
  - PDF visual/regressivo: foto, destinatário, 1–4 signatários, múltiplas páginas e caracteres portugueses.
  - Aceite: CI bloqueia merge em falha e cenários P0/P1 têm testes negativos e positivos.

- [ ] **QA-02 — Homologar com usuários usando cenários reais anonimizados** (P1, M)
  - Sessões separadas com coordenação, serviço social, psicologia, pedagogia/educação e saúde.
  - Registrar evidência, problema, severidade e aceite por fluxo.
  - Aceite: representantes aprovam ficha, status, Saúde, Educação, documentos e informativo anual.

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

### Marco 0 — Descoberta e proteção imediata

`DEC-01/02/03/04/06` e `DEC-08` a `DEC-11`, `EML-01` (somente descoberta/PoC), `LGPD-01/02/03`, corte imediato de `SEG-01` para fechar cadastro público, confirmação do tipo/rota da conta Google e congelamento do uso de dados reais. `DEC-05`, `DEC-07`, `DEC-06A/06B/06C/06D` e `IAM-01` estão concluídos apenas como análise/ADR/catálogo, checklist ou decisão provisória, sem modelo, PoC ou infraestrutura implantados; o contexto único de organização/unidade entra em `ARQ-01/06`, `SEG-02/03`, `ACO-01` e `DOC-05`, enquanto MFA/lifecycle de `SEG-01` são concluídos em conjunto com `IAM-02` no Marco 1. A decisão de `DEC-01` também separa as casas internas da unidade única e define se sua alocação/transferência exige histórico. Retirar, sanitizar ou importar qualquer dado real — inclusive material em `docs/formatacoes` — somente pelo procedimento e autorização aprovados; as inspeções locais de `DEC-06A/06B/06C/06D` não autorizam versionamento ou reutilização dos valores, e contas, mensagens e documentos de desenvolvimento/teste continuam exclusivamente sintéticos.

### Marco 1 — Base segura de produção e identidade

1. Base primeiro: `ARQ-01/02/03/04/06/07`, `SEG-02/03/04/05/06/07`, corte de idempotência/constraints/outbox de `BE-02`, `OPS-01/02/03`, `DOC-05/06` e primeira parte de `QA-01`. O aceite P0 de `ARQ-07`, incluindo restore integral, bloqueia dados reais.
2. Depois dos respectivos gates: `IAM-02` implementa em conjunto os requisitos pendentes de `SEG-01`; `EML-02` implanta o principal Google aprovado somente após `SEG-04/07`, `LGPD-01/02` e `DEC-08`.

Este marco entrega PostgreSQL, storage privado, filas/workers, constraints, RBAC/ABAC, auditoria, segredos, identidade e autorização Google revogável antes de qualquer ingestão com dados reais.

### Marco 2 — Núcleo operacional pedido pelos usuários

`ACO-01/02/03`, `CAD-01/02/03/04`, `IAM-03`, `BE-01`, `AGD-01/02`, `DOC-01/02/03/04` e corte responsivo/acessível de `FE-01` exigido pela PWA.

### Marco 3 — Saúde, Educação e dados contabilizáveis

`ATE-01/02`, `SAU-01` a `SAU-05`, `EDU-01/02/03`, Policies da matriz aprovada e auditoria correspondentes.

### Marco 4 — Integração Gmail e PWA solicitadas (P1)

Somente após aprovação e evidência dos Marcos 1 e 2, nesta ordem:

1. `EML-03` (ingestão/reconciliação) e `PWA-01` (instalação/network-only);
2. `EML-04` (propostas sem efeito automático) e `PWA-02` (canais revogáveis); e
3. `EML-05` (revisão humana → agenda → lembrete 1 dia antes).

### Marco 5 — Informativos, qualidade e escala

`BI-01/02/03`, `ARQ-05/08`, `BE-01B`, conclusão de `BE-02`, `FE-01/02` e conclusão de `QA-01/02`.

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
| Selecionar dupla, uma pessoa ou até quatro assinaturas | `DEC-04`, `DOC-03`, `DOC-04` |
| Atendimento em UBS/UPA/Hospital/CAPS, motivo e acompanhante | `SAU-01` |
| Encaminhamentos, exames e realização do exame | `SAU-03`, `ATE-02` |
| Medicações de uso contínuo e atualização após reavaliação | `SAU-02` |
| Medicação de tratamento único | `SAU-04` |
| Anticoncepcionais | `SAU-05` |
| Idas, reuniões, ligações e outras situações escolares | `EDU-02` |
| Avaliar Keycloak e adotar MFA adequado ao cenário | `DEC-07`, `IAM-01`, `IAM-02`, `SEG-01/02/03/04`, [ADR 0002](docs/adr/0002-identidade-laravel-fortify-totp.md) |
| Hospedar com orçamento limitado sem alegar alta disponibilidade | `ARQ-01/02/03/04/07/08`, `OPS-02`, [ADR 0003](docs/adr/0003-infraestrutura-contabo-swarm-redis.md), [arquitetura-alvo](docs/architecture/target-architecture.md) |
| Caixa/Group Gmail sem senha compartilhada | `DEC-08`, `EML-01`, `EML-02`, `EML-03`, `SEG-04/07` |
| Extrair prazos de e-mail com revisão humana | `DEC-09`, `DEC-11`, `EML-03`, `EML-04`, `EML-05` |
| Criar propostas de agenda e lembrar 1 dia antes | `DEC-09`, `DEC-10`, `EML-05`, `AGD-01/02`, `PWA-02` |
| Instalar no desktop/mobile como PWA | `DEC-10`, `PWA-01`, `FE-01` |
| Notificação in-app/Web Push sem PII | `DEC-10`, `PWA-02`, `SEG-02/03/04` |
| Avaliar IA sem autorizar LLM antecipadamente | `DEC-11`, `EML-01`, `EML-04`, `LGPD-02`, `SEG-07` |

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
- [Gmail — Delegate and collaborate on email](https://support.google.com/mail/answer/138350?hl=pt-br), [Make a group a Collaborative Inbox](https://support.google.com/a/users/answer/10375787?hl=en) e [Use a group as a Collaborative Inbox](https://support.google.com/a/users/answer/167430?hl=en): distinção entre mailbox delegada e Google Group.

### PWA, cache e Web Push

- [web.dev — Service workers](https://web.dev/learn/pwa/service-workers), [Serving/caching strategies](https://web.dev/learn/pwa/serving), [Offline data](https://web.dev/learn/pwa/offline-data) e [Back/forward cache](https://web.dev/articles/bfcache): network-only, Cache Storage, IndexedDB, cache HTTP e bfcache.
- [WebKit — Web Push for Web Apps on iOS and iPadOS](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/): suporte de Web Push para web apps adicionadas à Home Screen.

### Prazos processuais

- [Código de Processo Civil — Lei nº 13.105/2015](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2015/lei/l13105.htm), especialmente arts. 213 e 219–224: horário, dias úteis, suspensão, termo e prorrogação; não autoriza cálculo automático sem definir jurisdição e fonte.
- [CNJ — Resolução nº 244/2016](https://atos.cnj.jus.br/atos/detalhar/2349) e [Recomendação nº 44/2020](https://atos.cnj.jus.br/atos/detalhar/4838): suspensão nacional e publicação/versionamento de calendários de feriados locais. Atos do tribunal/jurisdição competente também precisam ser fonte oficial versionada.

## 13. Fora de escopo até decisão explícita

- Prontuário médico completo, prescrição clínica, cálculo de dose ou recomendação automatizada.
- Assinatura digital com validade jurídica, até concluir `DEC-04`.
- Aplicativo mobile nativo; web responsiva e PWA estão no escopo de `DEC-10` e `PWA-01/02`.
- Microserviços, data lake ou analytics complexo antes de medir volume e concluir `ARQ-08`.
- Integração automática com Judiciário, Saúde ou Educação sem base legal, contrato, segurança e especificação oficiais.
- Compartilhamento de senha, login do sistema pela caixa Gmail ou acesso coletivo sem atribuição individual.
- Uso de Gmail API `users.watch/history.list` diretamente sobre Google Group/Collaborative Inbox sem rota oficialmente suportada e comprovada na PoC.
- Criação automática de agenda, execução de ferramentas ou envio externo a partir do conteúdo do e-mail sem revisão humana autorizada.
- Determinação automática de prazo jurídico; o sistema apenas propõe dados para conferência profissional da fonte, termo inicial, jurisdição e calendário.
- Armazenamento indiscriminado de corpos/anexos de e-mail, cache offline de dados pessoais/sensíveis ou push com PII na tela bloqueada.
- Uso de LLM com dados reais antes de `DEC-11`, RIPD, fornecedor/DPA, garantia de `no-training`, minimização e avaliação sintética aprovados.
