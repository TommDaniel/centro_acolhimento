# Matriz sanitizada de campos dos documentos oficiais

- Status: cinco documentos funcionais confirmados + estrutura funcional do PIA adotada + obrigatoriedade empírica v0; aguardando validação campo a campo das técnicas, finalidade, retenção e validade jurídica
- Atualizado em: 28/08/2026
- Rastreabilidade: `DEC-04`, `DEC-06/06A/06B/06C/06D`, `CAD-01`, `ACO-01`, `DOC-01` a `DOC-06`, `LGPD-01/02/03`, `SEG-02/03/05`
- Fonte de verdade do backlog: [`TODO.md`](../TODO.md)

## 1. Escopo, fonte e proteção

Este catálogo cruza os rótulos e a estrutura de seis PDFs locais, totalizando 28 páginas, com models, migrations, formulários, controllers e templates PDF existentes. A leitura foi autorizada explicitamente pelo responsável do projeto e limitada à descoberta de tipos, áreas e campos. A sexta evidência é um PDF escaneado de sete páginas, sem camada de texto ou campos AcroForm: seis páginas contêm o corpo de um PIA preenchido e a última é uma página externa de verificação de assinaturas digitais. Esse arquivo não representa um documento adicional. Por decisão humana confirmada em 28/08/2026, sua estrutura é o PIA funcional adotado por ser o exemplo mais completo fornecido pelas técnicas, identificado somente pela procedência sanitizada como modelo da Prefeitura Municipal de Capão da Canoa.

As tabelas abaixo são um **modelo canônico recomendado**, construído a partir do PIA adotado, das demais evidências, feedbacks, regras de domínio e estado do código. Os outros exemplos servem para compatibilidade e conferência de lacunas, não como templates concorrentes. As tabelas não são transcrição literal nem inventário exaustivo dos PDFs. A decisão de estrutura não autoriza copiar dados reais nem transformar o PDF preenchido em template versionado. `DEC-06C` mantém obrigatoriedade empírica v0 apenas para orientar o projeto; o dicionário definitivo, a finalidade, a fonte autorizada, a retenção e a regra de atualização de cada campo permanecem em `DEC-06` e nas decisões LGPD relacionadas.

Os PDFs preenchidos contêm dados pessoais, judiciais, familiares e de saúde reais. Por isso:

- nenhum valor preenchido, nome de pessoa, número de processo, endereço, contato, narrativa, diagnóstico, medicamento, assinatura ou credencial profissional foi transcrito;
- os nomes originais dos arquivos não são usados como títulos ou identificadores;
- os PDFs preenchidos permanecem fora do Git e não podem virar fixture, seed, prompt, screenshot, log ou artefato de CI;
- um modelo em branco só pode entrar no produto depois de cópia sanitizada, aprovação institucional, classificação LGPD e armazenamento privado;
- esta matriz descreve evidência observada, não transforma um exemplo preenchido em regra oficial.

Inventário de evidências sanitizadas, sem transformar cada arquivo observado em tipo funcional:

| Tipo | Evidência local | Uso deste catálogo |
|---|---:|---|
| A. Comunicação narrativa histórica | 1 documento, 4 páginas | Evidência estrutural incorporada ao **Parecer do acolhido**; não cria Informação ou Ofício genérico |
| B. Ficha de ingresso | 1 documento em branco, 2 páginas | Candidato a modelo, ainda não aprovado em `DEC-06` |
| C. PIA e pacotes históricos de PIA | 2 pacotes preenchidos: 1 página de encaminhamento + corpo PIA de 5 ou 7 páginas, totalizando 6 e 8 páginas; 1 PIA autônomo escaneado com corpo de 6 páginas + 1 página externa de verificação de assinaturas digitais | O PIA escaneado mais completo é a estrutura funcional adotada. As páginas de encaminhamento permanecem evidência histórica/compatibilidade e não criam um sexto tipo documental |
| D. Termo de recebimento de documentos e pertences | 1 documento, 1 página | Estrutura de inventário, custódia e recebimento |

Os cinco documentos funcionais adotados são: **PIA; Relatório de visita técnica; Parecer do acolhido; Ficha de ingresso; e Termo de Recebimento/Entrega de documentos e pertences pessoais**. O Relatório de visita técnica foi confirmado pelo cliente mesmo sem corresponder a um tipo separado no inventário local inspecionado.

## 2. Áreas canônicas compartilhadas

Os documentos devem compor snapshots de entidades canônicas. Repetir os mesmos campos em tabelas isoladas por PDF criaria divergência.

| Área canônica | Conteúdo recomendado | Regra principal | Backlog |
|---|---|---|---|
| Configuração institucional | nome de exibição, razão/identificação institucional quando aplicável, logo, texto de cabeçalho, endereço/contatos oficiais, município/UF e preferências documentais | Centralizada e versionada; o template não deve hardcodear dados institucionais | `DEC-05`, `DOC-04`, `ARQ-06` |
| Pessoa acolhida | identificação civil e social, nascimento e identificadores necessários | Pessoa independe do episódio; minimizar retorno por tela/documento | `CAD-01/02`, `LGPD-01` |
| Processo judicial | número, comarca, vara/órgão, tipo/vínculo, situação, início/fim e referência externa | Uma pessoa pode ter vários processos; cada documento seleciona quais processos entram no snapshot | `CAD-01`, `ARQ-06`, `DOC-04/06` |
| Episódio de acolhimento | ingresso, fundamento/motivo, origem, órgão e pessoa condutora, solicitante, unidade, situação e desligamento | Ingresso e desligamento pertencem ao episódio; movimentações são append-only | `DEC-01`, `ACO-01/03`, `CAD-01` |
| Família e vínculos | pessoa relacionada, vínculo, contatos, endereço, vigência, fonte e observações necessárias | Relação, contato e endereço são temporais; nunca reconstruir documento passado com o valor atual | `CAD-01`, `ACO-01`, `LGPD-01` |
| Documentos recebidos | tipo, identificador necessário, original/cópia, meio físico/digital, recebimento, custodiante/local, devolução e estado | Inventário canônico com cadeia de custódia; anexo digital não prova posse do original | `CAD-01`, `ARQ-02`, `SEG-05`, `DOC-04/06` |
| Pertences recebidos | descrição, quantidade, condição, recebimento, custódia, devolução e observação | Ficha e termo reutilizam o mesmo inventário; correção preserva histórico | `CAD-01`, `DOC-04/06` |
| Saúde | fatos, fontes, atendimentos, encaminhamentos, exames e tratamentos | Narrativa do PIA referencia fatos; medicação contínua é versionada e nunca sobrescrita | `SAU-01` a `SAU-05`, `ATE-01/02` |
| Educação | vínculo escolar temporal, rede, escola, ano/série, turno, situação, contatos e pendências | Informação escolar não fica somente em narrativa; vínculo anterior é preservado | `CAD-01`, `EDU-01/02/03` |
| Rede e referências | serviço/órgão, área, profissional de referência, contato institucional, início/fim e fonte | Serviço e profissional são conceitos distintos e temporais | `ATE-01/02`, `SAU-01`, `EDU-01` |
| Benefícios | programa/benefício, titular, situação, vigência, fonte e responsável por confirmar | Não inferir renda ou elegibilidade; registrar fonte e vigência | `CAD-01`, `LGPD-01` |
| Visitação/convivência | pessoa, autorização/condição, frequência, local, vigência, decisão de origem e ocorrências | Regra planejada e visita realizada são registros distintos | `CAD-01`, `ACO-01`, `AGD-01/02` |
| Documento emitido | tipo, número, data, assunto, destinatário, autores, signatários, versão, status, hash e snapshots | Rascunho é editável; finalização é imutável; correção cria retificação ligada | `DEC-04`, `DOC-02` a `DOC-06` |

## 3. Documento — Parecer do acolhido

### Finalidade e composição

Relatar tecnicamente a situação do acolhido e formular uma solicitação ao juiz. Corresponde ao exemplo de Parecer fornecido pelo cliente, sem criar um tipo adicional de Ofício e sem persistir/versionar apelido, nome próprio ou correspondência informal usada durante a descoberta. O preenchimento combina metadados e fatos selecionáveis com narrativa profissional guiada em começo, meio e fim.

| Área | Campos estruturados | Campos narrativos | Origem/autoria | Sensibilidade | Estado atual |
|---|---|---|---|---|---|
| Cabeçalho | configuração institucional e logo; município/UF de emissão | texto institucional opcional | configuração institucional aprovada | restrito | **Parcial:** cabeçalho e local estão hardcoded nos templates |
| Destinatário | órgão/instituição, pessoa, cargo/função, tratamento, comarca/vara quando aplicável, meio de remessa | complemento de endereçamento e saudação | autor seleciona; cadastro de contatos pode sugerir | restrito | **Ausente** |
| Metadados | tipo, assunto, número, data/hora, unidade, setor, processos relacionados | título complementar | sistema + autor | restrito | **Parcial:** `Report` possui título, número e timestamps; só há um processo na pessoa |
| Identificação mínima | pessoa, episódio e processos selecionados | nenhuma como substituta do vínculo | cadastros canônicos | restrito | **Parcial:** snapshot não existe e `Crianca` possui processo único |
| Corpo técnico | referências a fatos/eventos estruturados | introdução/contexto, descrição, avaliação técnica, providências/solicitação e fechamento | um ou mais autores identificados | restrito ou sensível conforme conteúdo | **Parcial:** `Report` possui três textos livres, sem vínculo a fatos estruturados |
| Autoria e assinatura | autores; 1 a 4 signatários ativos; nome, cargo, registro profissional quando necessário e ordem como snapshot | fórmula de encerramento | equipe selecionada + configuração do usuário | restrito | **Ausente:** só o criador aparece no rodapé; não há signatários selecionáveis |
| Finalização | status, versão, finalizado por/em, hash, PDF, retificação/cancelamento | justificativa de retificação | sistema + ator autorizado | restrito/sensível | **Ausente:** registros podem ser atualizados ou excluídos fisicamente |

### Invariantes e critérios de geração

- Destinatário e procedimento judicial são estruturados e salvos como snapshot (`DOC-02`); a obrigatoriedade de cada subcampo depende da classificação das técnicas.
- A pessoa não pode ter o processo judicial reduzido a um único campo. O Parecer escolhe um ou mais vínculos processuais e preserva o que foi impresso.
- Narrativa não substitui ocorrência, atendimento, movimentação, encaminhamento, exame, medicação ou prazo estruturado. O documento pode citá-los e congelar um resumo autorizado.
- Dados institucionais vêm de uma configuração central versionada. Não duplicar cabeçalho/endereço em cada registro.
- Finalização reserva número com concorrência segura, congela conteúdo/metadados/signatários, gera hash e impede `UPDATE` destrutivo. Correção cria retificação (`DOC-05/06`).
- A narrativa oferece seções didáticas de contexto/início, evolução/meio, avaliação, pedido e conclusão; fatos canônicos são selecionados/referenciados e não redigitados.
- O PDF precisa suportar português, múltiplas páginas, quebra de parágrafo, endereçamento e 1–4 blocos de assinatura sem sobreposição.

### Dúvidas humanas

- Quais destinatários, tratamentos, procedimentos e meios de remessa são aceitos e quais subcampos são obrigatórios?
- O que cada assinatura significa juridicamente e quando registro profissional deve aparecer (`DEC-04`)?
- Quais perfis podem redigir, revisar, finalizar, retificar, cancelar e baixar cada tipo (`SEG-02`)?

## 4. Documento — Ficha de ingresso

### Finalidade e composição

Registrar a recepção inicial, a identificação disponível, a origem do acolhimento e os itens/documentos entregues. Existe uma ficha em branco, mas `DEC-06` permanece pendente: coordenação/equipe técnica precisam aprovar o modelo e o dicionário de cada campo.

| Área | Campos estruturados recomendados | Campo narrativo | Origem/autoria | Sensibilidade | Estado atual |
|---|---|---|---|---|---|
| Identificação | pessoa, nascimento, sexo/gênero quando necessário, raça/cor quando necessário e identificadores civis disponíveis | observação de divergência/ausência | documento apresentado + responsável pelo ingresso | restrito; alguns campos podem ser sensíveis | **Parcial:** muitos campos estão em `Crianca`, sem fonte/confirmação |
| Família/responsáveis | vínculos, responsáveis, contatos e endereços com vigência | observação familiar inicial | informante/documento/órgão + autor | restrito | **Parcial:** `Familiar` existe, porém sem vigência/fonte/histórico |
| Ingresso | episódio, data/hora, fundamento/motivo, origem, solicitante, órgão condutor, pessoa condutora, unidade e responsável pelo recebimento | circunstâncias complementares | decisão/comunicação de origem + ator receptor | restrito | **Parcial:** data/motivo ficam na pessoa; `encaminhado_por` agrega conceitos distintos |
| Processo | zero ou mais processos e seleção do fundamento do episódio | observação processual necessária | documento judicial/órgão | restrito | **Parcial:** apenas um processo, vara e comarca na pessoa |
| Saúde inicial | necessidades imediatas, fonte, informações confirmadas e encaminhamento inicial | relato estritamente necessário | informante/profissional/documento | sensível | **Ausente como estrutura:** hoje só há texto livre no PIA |
| Educação inicial | vínculo conhecido, escola/rede, matrícula, ano/série, turno e situação | pendência para confirmação | escola/família/órgão + autor | restrito | **Ausente:** solicitado pelos usuários; aparece de modo ausente ou inconsistente nos modelos observados |
| Visitação/convivência | pessoas autorizadas/restritas, condição, vigência e decisão de origem | observação justificada | decisão/orientação do órgão competente | restrito | **Parcial:** há visitas realizadas, não regra temporal de visitação |
| Documentos | inventário, original/cópia, físico/digital, recebimento, custodiante/local e devolução | ressalva de condição/divergência | conferente + entregador | restrito | **Parcial:** anexos digitais existem, sem tipo/custódia/original-cópia |
| Pertences | inventário canônico, quantidade, condição, custódia e recebimento | observação de condição | conferente + entregador | restrito | **Parcial:** `Pertence.itens` guarda apenas descrição/quantidade |
| Desligamento impresso | snapshot do evento de encerramento quando a ficha exigir esta seção | observação do encerramento | episódio/movimentação | restrito | **Modelagem incorreta hoje:** `status` atual na pessoa; deve pertencer ao episódio |
| Recebimento/assinaturas | entregador, recebedor, autor e signatários conforme regra | ressalvas | atores identificados | restrito | **Ausente/parcial:** textos de assinatura não são identidade, evidência ou snapshot |

### Invariantes e critérios de geração

- Criar pessoa e abrir episódio são operações relacionadas, mas distintas e transacionais. Duplicidade gera revisão humana; nunca merge automático.
- Órgão condutor, pessoa condutora e solicitante/requisitante são conceitos diferentes. “Outro” exige complemento.
- A ficha e o termo do tipo D leem o mesmo inventário canônico de documentos e pertences; não duplicar listas em JSON desconectadas.
- Documento físico original, cópia e anexo digital têm semânticas distintas. Custódia registra quem recebeu, quando e onde está, sem expor localização além do necessário.
- Desligamento não atualiza a pessoa para apagar a história: encerra o episódio e gera movimentação/evento append-only.
- Geração oficial exige snapshot dos dados disponíveis no ingresso, campos ausentes explicitáveis conforme decisão e assinatura de atores segundo `DEC-04`.

### Dúvidas humanas para concluir `DEC-06`

- A ficha em branco encontrada é a versão vigente e aprovada? Quem aprova alterações?
- Quais campos são obrigatórios no instante do ingresso e quais viram pendência com responsável/prazo?
- Quais fontes documentais podem confirmar cada dado e quem pode corrigi-lo?
- A área de Educação deve integrar a ficha, ser anexo ou apenas gerar pendência para `EDU-01`?
- Quais regras de visitação e desligamento devem aparecer na impressão sem duplicar a fonte canônica?
- Quem entrega, confere, recebe e assina documentos/pertences?

## 5. Documento — PIA

### Evidência e divergências entre variantes

Há três evidências físicas contendo corpo PIA. Duas são pacotes compostos históricos; a terceira é um PIA autônomo escaneado com corpo de seis páginas e uma página externa de verificação de assinaturas. Por decisão registrada em `DEC-06C`, a estrutura do exemplo mais completo fornecido pelas técnicas, da Prefeitura Municipal de Capão da Canoa, é o PIA funcional adotado. Ele não é um documento adicional. Os demais exemplos permanecem como evidência de cruzamento e compatibilidade. A escolha funcional não equivale à aprovação de obrigatoriedade definitiva, finalidade LGPD ou validade jurídica, e o PDF preenchido não pode ser versionado como template.

As páginas históricas de encaminhamento não constituem um tipo funcional nesta fase. Eventual necessidade futura de peça de encaminhamento reabre `DEC-06`; não deve ser antecipada pelo modelo.

| Aspecto | Dois pacotes compostos | PIA autônomo escaneado | Tratamento v0 e decisão pendente |
|---|---|---|---|
| Composição | 1 página histórica de encaminhamento + corpo PIA de 5 ou 7 páginas | Corpo PIA de 6 páginas + página externa de verificação de assinaturas digitais | O corpo mais completo orienta o PIA adotado; encaminhamento não é tipo funcional nesta fase |
| Organização | Uma estrutura mais condensada e outra mais extensa | Estrutura tabular inicial seguida de blocos narrativos e plano | Usar o escaneado como referência funcional v0; técnica ainda aprova o dicionário definitivo e a política de evolução |
| Família e território | Dados e análise distribuídos com graus diferentes de detalhe | Composição familiar, irmãos em outras configurações de cuidado e síntese familiar | Separar vínculo temporal de narrativa avaliativa |
| Rede/serviços e referências | Presentes | Saúde, educação/profissionalização e assistência social separados entre pessoa acolhida e familiares | Estruturar fatos, serviço, profissional, área, vigência e fonte |
| Benefícios | Evidência em seção/contexto | Não promovido a catálogo por esta inspeção | Definir campos, fonte, titular e vigência a partir de modelo aprovado |
| Saúde | Presente, majoritariamente narrativa | Blocos separados para pessoa acolhida e familiares | Referenciar fatos e medicação versionada, sem prescrição pelo sistema |
| Educação | Menções não uniformes | Blocos separados para pessoa acolhida e familiares | Usuários solicitaram área própria; estruturar vínculos e fatos fora do documento |
| Plano, responsáveis e prazos | Presente com graus diferentes de estrutura | Plano de ação em bloco narrativo | Criar itens estruturados sem eliminar fundamentação narrativa |
| Reavaliação | Presente | Não promovida a campo por esta inspeção | Data aprovada vira prazo/agenda, nunca apenas texto |
| Foto | Não observada | Não promovida a campo por esta inspeção | Foto foi solicitada pelos usuários; `DOC-01` decide inclusão e testes |
| Assinaturas | Blocos fixos nos exemplos | Autores/signatários no corpo e verificação por provedor externo | `DEC-04` define modalidade e composição; não inferir validade jurídica do exemplo |

### Estrutura sanitizada do PIA funcional adotado (`DEC-06B/06C`)

Somente rótulos, grupos e tipos de campo foram catalogados; nenhum valor preenchido foi copiado.

| Grupo | Estrutura observada | Modelagem recomendada para decisão em `DEC-06` |
|---|---|---|
| Identificação | pessoa; nascimento/idade; sexo; identidade de gênero; raça/cor; naturalidade; registro de nascimento; CPF; RG; cartão SUS; NIS; título; filiação/responsável com vínculo, contato/endereço e identificadores disponíveis | Reutilizar pessoa, documentos e vínculos canônicos; aplicar minimização e permissão conforme sensibilidade/finalidade |
| Família | composição com pessoa, nascimento/idade, parentesco e ocupação; irmãos em outra instituição com pessoa, nascimento, entidade e “não se aplica”; irmãos menores com terceiro com pessoa, nascimento, responsável, informação de guarda e “não se aplica” | Modelar relações temporais e configurações de cuidado sem duplicar pessoas nem sobrescrever histórico |
| Acolhimento e processos | acolhimentos anteriores; acolhimento atual; realizado por; motivos catalogados + “Outro”; perspectivas separadas da equipe, família e pessoa acolhida; conselho tutelar/procedimento/data; processo CNJ; outros processos com tipo e número | Derivar de episódios, movimentações e processos; preservar fonte, autoria e data; “Outro” exige complemento |
| Especificidades | seleção múltipla para deficiência física/sensorial, deficiência intelectual, saúde mental, dependência de álcool/drogas, doenças crônicas, gestação, filhos, trajetória de rua, refúgio/imigração, migração, outro e “não se aplica” | Tratar como evidência de rótulos usados, não como taxonomia aprovada nem inferência diagnóstica; “Outro” exige complemento |
| Blocos técnicos | informações familiares relevantes; saúde da pessoa acolhida; saúde de familiares; educação/profissionalização da pessoa acolhida; educação/profissionalização de familiares; assistência social da pessoa acolhida; assistência social de familiares; esporte/cultura/lazer; considerações técnicas; plano de ação; providências ao Judiciário | Compor de fatos canônicos referenciados, permitindo texto apenas nos pontos em que síntese, avaliação ou fundamentação profissional sejam necessárias |
| Fechamento | local/data; autores/signatários; cargo; registro profissional | Finalização preserva snapshot, autoria, ordem, cargo e registro conforme a modalidade aprovada em `DEC-04` |
| Verificação externa | página externa com classe estrutural de código/QR/link, signatários e datas do provedor | Tratar como evidência externa vinculada à versão, sem copiar valores, alegar validação ou escolher fornecedor/modalidade antes de `DEC-04` |

### Regra provisória v0 de obrigatoriedade (`DEC-06C`)

Esta classificação é empírica e orienta o desenho inicial até uma técnica revisar formalmente cada campo. Ela é validada **somente ao finalizar** o PIA; nunca impede salvar rascunho. Ausência de informação e decisão de não coleta por minimização são estados de qualidade com autoria e data, não permissão para inventar conteúdo ou forçar coleta de dado sensível apenas para “completar” o documento.

| Estado v0 | Regra de finalização |
|---|---|
| Obrigatório | Precisa existir como dado, conteúdo profissional ou seleção válida antes da finalização. |
| Obrigatório como decisão/estado | A seção precisa ser enfrentada, mas pode registrar informação presente, `não se aplica`, `não informado/não obtido` ou `não coletado por minimização/finalidade pendente`. Ausência registra motivo quando necessário e, somente se houver pendência real de obtenção, responsável por completar. O estado de minimização/finalidade pendente não cria pendência nem responsável por completar e não exige texto fictício. |
| Condicional | Só é exigido quando a condição descrita ocorre e a coleta é necessária para a finalidade aprovada. |
| Opcional | Pode ser omitido sem impedir a finalização; inclusão continua sujeita a necessidade, minimização e autorização. |

#### Obrigatórios na finalização

- identificação mínima: vínculo inequívoco à pessoa canônica, nome completo disponível e data de nascimento conhecida ou estado documentado de ausência/divergência; nenhum documento civil é obrigatório por padrão;
- episódio de acolhimento atual e respectiva data de ingresso;
- origem/realizado por, mantendo órgão e pessoa em campos separados; a pessoa pode constar como `não informada`, com estado de qualidade rastreável;
- motivo/fundamento do acolhimento e respectiva fonte;
- perspectiva da equipe;
- considerações técnicas;
- pelo menos um item estruturado do plano, contendo objetivo, ação, responsável e prazo ou condição de revisão;
- autoria e data/local de emissão, sendo a data derivada no ato da finalização e o local obtido da configuração institucional versionada; e
- seleção de 1 a 4 signatários conforme a regra provisória de `DEC-04`, somente como bloco nominal com nome, cargo aplicável e ordem. Esta seleção não alega assinatura eletrônica/digital nem validade jurídica.

#### Obrigatórios como decisão/estado

Cada seção abaixo precisa ter informação estruturada ou um estado explícito (`não se aplica`, `não informado/não obtido` ou `não coletado por minimização/finalidade pendente`). Quando necessário, a ausência inclui motivo e autoria/data; responsável por completar só existe para pendência real de obtenção, nunca para o estado de minimização/finalidade pendente. Esse gate registra a decisão, mas não autoriza coleta:

- sexo, identidade de gênero, raça/cor e naturalidade, somente conforme finalidade e política aprovadas;
- filiação/responsáveis;
- composição familiar;
- irmãos;
- acolhimentos anteriores;
- conselho tutelar e processos;
- especificidades;
- perspectiva da família;
- perspectiva da pessoa acolhida;
- Saúde da pessoa acolhida;
- Saúde familiar;
- Educação/profissionalização da pessoa acolhida;
- Educação/profissionalização familiar;
- Assistência Social da pessoa acolhida e da família;
- esporte, cultura e lazer; e
- providências ao Judiciário.

Para campos sensíveis sem finalidade aprovada — incluindo identidade de gênero, raça/cor, especificidades e Saúde familiar — deve ser possível usar `não coletado por minimização/finalidade pendente`. A finalização não transforma a classificação v0 em autorização para coletar ou ampliar acesso.

#### Condicionais

- identificadores civis e de Saúde: apenas quando disponíveis, necessários e autorizados para a finalidade do PIA;
- seleção `Outro`: exige complemento curto;
- motivo sensível ou especificidade: exige fonte e permissão compatível, sem inferência diagnóstica;
- procedimento do Conselho Tutelar selecionado: exige número e, somente quando existente na fonte, data de início ou do evento explicitamente identificado;
- processo judicial selecionado: exige número CNJ e tipo quando aplicável; data só é exigida para evento judicial explicitamente tipado e vinculado à fonte. `DEC-06` define o dicionário final, sem data genérica do processo;
- irmão que vive com terceiro: informação de guarda somente quando conhecida e necessária;
- providência ao Judiciário: exige destinatário/processo e fundamentação quando houver providência;
- registro profissional: exigido somente quando aplicável ao cargo/função; e
- verificação externa de assinatura: somente se a modalidade tiver sido aprovada em `DEC-04` e efetivamente usada na versão finalizada.

#### Opcionais

- idade calculada, sempre derivada da data de nascimento e da data de referência, sem persistência manual;
- ocupação familiar quando desconhecida;
- síntese familiar adicional;
- complementos narrativos de blocos pré-compilados;
- foto até decisão de `DOC-01`; e
- observações complementares estritamente necessárias.

#### Rascunho, ausência e finalização

- O rascunho pode permanecer incompleto e deve indicar pendências sem produzir afirmações automáticas.
- Dado sensível não pode ser forçado apenas para satisfazer completude documental. A necessidade e o acesso continuam dependentes de `LGPD-01/02` e `SEG-02`.
- Estados de ausência preservam autoria e data; quando houver pendência real de obtenção, preservam também motivo e responsável, sem copiar conteúdo sensível para log técnico. `Não coletado por minimização/finalidade pendente` registra a decisão de não coletar e não cria providência nem responsável por completar.
- A finalização congela o snapshot revisado, os estados de ausência e as fontes referenciadas. Alteração posterior do cadastro ou de fatos assistenciais não reescreve o PIA emitido; correção exige retificação/versionamento conforme `DOC-05/06`.

### Estratégia de menos digitação

- Identificação, família, episódios/processos, Saúde, Educação, rede e atividades devem vir de fontes canônicas e fatos referenciados, sem nova transcrição no PIA.
- Motivos e especificidades usam seleção simples/múltipla, com “Outro” e complemento curto apenas quando necessário. Os rótulos observados continuam sujeitos à aprovação e versionamento de taxonomia.
- O plano de ação deixa de ser uma narrativa única e passa a ter itens com objetivo, ação, responsável, prazo, status e evidência/desfecho.
- Saúde, Educação, assistência social e esporte/cultura/lazer são pré-preenchidos por fatos referenciados, com complemento opcional e revisão humana. A compilação é determinística; uso de LLM não está aprovado para produção.
- Texto longo permanece somente onde a voz ou o julgamento profissional é indispensável: perspectiva da equipe e considerações técnicas são obrigatórias; perspectivas da família e da pessoa acolhida aceitam texto ou estado `não informado/não obtido` com motivo quando necessário; fundamentação judicial é condicional à existência de providência; e síntese familiar adicional é opcional.
- Cada trecho compilado registra fontes e autoria. Ao finalizar, o documento congela o snapshot revisado; mudanças posteriores nos cadastros ou fatos não reescrevem a versão emitida.
- A compilação v0 é determinística e sujeita à revisão humana. Nenhum LLM está aprovado para gerar, completar ou inferir conteúdo do PIA.

### Áreas e campos recomendados

| Área | Campos estruturados | Campos narrativos | Origem/autoria | Sensibilidade | Estado atual |
|---|---|---|---|---|---|
| Identificação/contexto | pessoa, episódio e processos selecionados | resumo contextual opcional | cadastros canônicos | restrito | **Parcial:** `Crianca::identificacao()` e episódio/processos ainda inadequados |
| Família/território | vínculos temporais, contatos/endereço vigentes no período e referências territoriais necessárias | história, qualidade dos vínculos e avaliação técnica | fontes identificadas + equipe | restrito | **Parcial:** tabela atual + textos livres, sem temporalidade/snapshot |
| Documentação/custódia | itens canônicos relevantes e situação de obtenção | pendências documentais | inventário + autor | restrito | **Ausente/parcial:** anexos não substituem inventário |
| Saúde | fatos/encaminhamentos/exames/tratamentos referenciados; versão vigente selecionada da medicação | avaliação técnica e necessidades | módulo Saúde + autores | sensível | **Ausente como estrutura; parcial em texto livre** |
| Educação | vínculo escolar e contatos/pendências referenciados | avaliação educacional | módulo Educação + autores | restrito | **Ausente como estrutura; parcial em texto livre** |
| Rede/profissionais | serviço, área, profissional, papel, contato institucional, vigência e fonte | articulação e avaliação da rede | módulos/rede + autores | restrito/sensível conforme área | **Ausente como estrutura** |
| Benefícios | benefício, titular, situação, vigência e fonte | avaliação socioeconômica necessária | fonte confirmada + equipe | restrito | **Ausente como estrutura** |
| Objetivos/plano | objetivo, ação, responsável, prazo, status, evidência/desfecho e origem | fundamentação e avaliação | equipe técnica; participação registrada conforme política | restrito/sensível | **Parcial:** `plano_acao` é texto livre |
| Judiciário | providência, destinatário, processo e prazo/referência | demanda/fundamentação | equipe + decisão/documento fonte | restrito | **Parcial:** texto livre `providencias_judiciario` |
| Reavaliação | data-base, data limite, responsável, status, agenda e origem | justificativa | autor + regra aprovada | restrito | **Ausente como estrutura** |
| Autoria/signatários | autores e 1–4 signatários em ordem, com snapshots | considerações finais | equipe selecionada | restrito | **Ausente:** somente criador/rodapé atual |

### Invariantes e critérios de geração

- O PIA é versão de um documento, não a fonte exclusiva de saúde, educação, família, processos, benefícios ou agenda.
- Itens do plano e prazo de reavaliação são estruturados para acompanhamento. Narrativa fundamenta; não substitui estado, responsável, prazo ou desfecho.
- Alterar medicação cria nova versão no módulo Saúde. Um PIA finalizado preserva o snapshot escolhido e não muda retroativamente.
- A reavaliação aprovada cria tarefa/evento de agenda com responsável e lembrete; concluir o evento não altera o PIA automaticamente.
- Foto é opção explícita do documento/regra aprovada; ausência, arquivo corrompido ou acesso negado não quebra o PDF e não torna a foto pública.
- Finalização, hash, numeração, signatários, múltiplas páginas e retificação seguem `DOC-01/03/05/06`.

## 6. Documento — Relatório de visita técnica

### Finalidade e composição

Registrar uma visita técnica com contexto, participantes, fatos observados, avaliação profissional e encaminhamentos rastreáveis. Uma visita pode abranger uma ou mais crianças/adolescentes, como no atendimento conjunto de irmãos; o registro interno vincula cada pessoa/caso ao episódio aplicável. Dados já existentes são selecionados/autopreenchidos; texto longo é reservado a observações e avaliação profissional.

| Área | Campos estruturados | Campo narrativo | Origem/autoria | Sensibilidade |
|---|---|---|---|---|
| Metadados | tipo, número quando aplicável, data de emissão, uma ou mais pessoas/casos e respectivos episódios, processo(s) | assunto complementar | sistema + autor | restrito |
| Visita | data/hora de início e fim, tipo, local, objetivo catalogado | complemento do objetivo quando `Outro` | autor + agenda/fato vinculado | restrito |
| Participantes | profissionais da equipe; estado de participação de cada pessoa acolhida vinculada (`participou`, `não participou`, `não informado`); familiares e participantes externos com papel | observação de participação quando necessária | vínculos/cadastros selecionados + autor | restrito |
| Fatos/observações | fatos canônicos referenciados e fontes | observações da visita | autor identificado | restrito/sensível |
| Avaliação | categorias/estado quando aprovados | avaliação profissional | equipe técnica | restrito/sensível |
| Encaminhamentos | ação, destino, responsável, prazo, status e fato/tarefa vinculada | complemento estritamente necessário | autor + responsável | restrito |
| Fechamento | anexos privados, autores, signatários, ordem, cargo/registro, versão, hash | ressalva/conclusão quando necessária | sistema + equipe | restrito/sensível |

### Invariantes e critérios de geração

- A visita realizada é fato distinto da agenda e possui data/hora, local, objetivo, participantes e autoria próprios.
- O registro interno mantém vínculo canônico obrigatório com uma ou mais pessoas/casos e com o episódio aplicável a cada pessoa. Quando a visita envolver irmãos, os vínculos são repetíveis e inequívocos; a decisão sobre quais identificações aparecem no PDF é independente e permanece classificável no checklist.
- Cada pessoa acolhida vinculada recebe individualmente o estado `participou`, `não participou` ou `não informado`. O formulário oferece um seletor por pessoa já vinculada, inclusive para irmãos, sem booleano global nem redigitação de identidade.
- Encaminhamentos e prazos viram registros canônicos vinculados; não ficam apenas no texto do relatório.
- Participantes são selecionados com papel no evento; pessoa externa não vira usuária nem recebe acesso.
- Rascunho incompleto é permitido. Obrigatórios são validados na finalização, que congela snapshot e não reescreve fatos canônicos.

## 7. Documento — Termo de Recebimento/Entrega de documentos e pertences pessoais

### Finalidade e composição

Registrar o que foi entregue/recebido, por quem, quando e sob qual custódia. O evento interno mantém vínculo inequívoco com a pessoa acolhida e com o episódio/caso aplicável. O documento observado reúne identificação, lista de itens/documentos, recebimento e assinaturas.

| Área | Campos estruturados | Campo narrativo | Origem/autoria | Sensibilidade | Estado atual |
|---|---|---|---|---|---|
| Identificação/contexto | pessoa, episódio e processo(s) selecionado(s) se necessários | nenhuma como chave | cadastros canônicos | restrito | **Parcial:** pessoa e processo único |
| Documento recebido | tipo, descrição/identificador mínimo, original/cópia, físico/digital, quantidade, condição | ressalva | conferente + entregador | restrito | **Ausente:** `CriancaDocumento` representa upload, não documento físico/custódia |
| Pertence recebido | descrição, quantidade e condição | ressalva | conferente + entregador | restrito | **Parcial:** descrição e quantidade em JSON |
| Cadeia de custódia | recebido/entregue por, data/hora, local interno autorizado, custodiante e estado | observação de transferência | atores identificados | restrito | **Ausente** |
| Devolução/transferência | item, data/hora, de/para, motivo, conferente e resultado | observação | evento de custódia | restrito | **Parcial:** devolução é um booleano global do termo |
| Assinaturas | partes/signatários, papel e ordem como snapshot | ressalva | atores identificados | restrito | **Parcial/inadequado:** nomes livres não constituem fluxo de assinatura |
| Documento emitido | número, data, versão, status, hash e retificação | justificativa de retificação | sistema + ator autorizado | restrito | **Ausente/parcial** |

### Invariantes e critérios de geração

- Ficha de ingresso e termo não mantêm inventários concorrentes. Ambos selecionam itens do mesmo registro canônico.
- Cada evento de custódia mantém vínculo canônico obrigatório e inequívoco com a pessoa acolhida e com o episódio/caso aplicável. A decisão sobre como essas identificações aparecem no PDF é independente e permanece classificável no checklist.
- Original/cópia, quantidade, condição e cadeia de custódia são atributos/eventos próprios; upload digital é apenas um possível anexo privado.
- Devolução ou transferência é por item/lote explícito e append-only. Não usar um único booleano para apagar custódias parciais.
- O PDF final congela identificação, itens, custódia e signatários. Retificação preserva a emissão anterior.
- A modalidade e o valor jurídico das assinaturas continuam pendentes em `DEC-04`.

## 8. Matriz consolidada de gaps

| Capacidade | Estado no código atual | Gap/risco | Destino |
|---|---|---|---|
| Pessoa e identificação | **Parcial** em `Crianca` | mistura estado atual, acolhimento e processo; fonte/confirmação ausentes | `CAD-01/02`, `ACO-01`, `ARQ-06` |
| Múltiplos processos por pessoa | **Ausente** | um único `processo_numero/vara/comarca` | `CAD-01`, `ARQ-06`, `DOC-04/06` |
| Episódio, ingresso e desligamento | **Ausente/inadequado** | data, motivo e status estão na pessoa; sem movimentações | `DEC-01`, `ACO-01/03` |
| Família temporal | **Parcial** em `Familiar` | update/delete e ausência de vigência/fonte/snapshot | `CAD-01`, `ACO-01`, `DOC-06` |
| Configuração institucional | **Ausente/inadequada** | cabeçalho/local hardcoded | `DOC-04`, `ARQ-06` |
| Destinatário/procedimento judicial | **Ausente** | Parecer do acolhido não identifica destinatário e procedimento de forma estruturada | `DOC-02/04` |
| Autores e 1–4 signatários | **Ausente** | criador no rodapé não é composição de assinatura | `DEC-04`, `DOC-03/04/06` |
| Cinco tipos documentais | **Ausente/inconsistente** | código atual usa contratos antigos e não representa exatamente PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega | `DEC-06`, `DOC-04/06` |
| Relatório de visita técnica | **Ausente** | visita, participantes, objetivo, fatos, avaliação e encaminhamentos não possuem contrato documental integrado | `DOC-04/06`, `ATE-01/02`, `AGD-02` |
| Inventário de documentos e custódia | **Ausente** | upload público não registra original/cópia/custodiante | `CAD-01`, `ARQ-02`, `SEG-05`, `DOC-04/06` |
| Inventário de pertences | **Parcial** | JSON descrição/quantidade e devolução global mutável | `CAD-01`, `DOC-04/06` |
| Visitação temporal | **Parcial** | visitas realizadas existem; autorização/condição/vigência não | `CAD-01`, `ACO-01`, `AGD-01/02` |
| Saúde e medicação versionada | **Ausente** como fatos | PIA guarda narrativa mutável | `SAU-01/02/03/04/05` |
| Educação e vínculo temporal | **Ausente** como fatos | apenas campos narrativos do PIA | `EDU-01/02/03`, `CAD-01` |
| Rede/profissionais de referência | **Ausente** | nomes/serviços ficam em narrativas | `ATE-01/02`, `SAU-01`, `EDU-01` |
| Benefícios temporais | **Ausente** | informação pode ficar em narrativa sem fonte/vigência | `CAD-01`, `LGPD-01` |
| Plano/reavaliação e agenda | **Ausente** como fluxo | prazo fica em texto; agenda não deriva do PIA | `DEC-02`, `AGD-01/02`, `DOC-04` |
| Foto no PIA | **Parcial e insegura** | template tenta ler storage público; exemplos não a tornam regra | `CAD-03`, `ARQ-02`, `DOC-01` |
| Finalização/snapshot/versão | **Ausente** | documentos podem ser editados/excluídos e usam dados atuais | `DOC-05/06`, `SEG-03` |
| Arquivos privados | **Ausente** | URLs públicas, sem quarentena/Policy/auditoria | `ARQ-02`, `SEG-05` |

## 9. Ordem pequena de implementação

1. **`DEC-06A/06B/06C/06D` (tipos, estrutura funcional do PIA, catálogo e checklist):** manter esta matriz sanitizada e os cinco documentos aprovados; não promover PDFs preenchidos, o checklist ou a verificação externa a template versionado, fonte de valores ou prova de validade jurídica.
2. **`DEC-06` + `LGPD-01/02/03`:** aprovar o dicionário campo a campo dos cinco documentos: finalidade, obrigatoriedade, fonte, autoria, acesso, retenção e descarte.
3. **`ARQ-01/06` + `ACO-01` + `CAD-01`:** separar pessoa, múltiplos processos, episódio/ingresso/desligamento e família temporal; PostgreSQL e constraints antes dos PDFs oficiais.
4. **`ARQ-02` + `SEG-02/03/05`:** storage privado, Policies, auditoria e cadeia de custódia antes de importar/anexar qualquer modelo real.
5. **`DOC-02/03/04`:** configuração institucional, destinatário do Parecer, autores/signatários, UX e composição comum dos cinco tipos.
6. **`DOC-05/06`:** sequência concorrente, rascunho, finalização, snapshot, hash e retificação.
7. **`SAU-01/02/03`, `EDU-01/02` e `ATE-01/02`:** substituir referências puramente narrativas por fatos versionados/referenciáveis.
8. **`DOC-01` + `CAD-03`:** foto privada opcional no PIA, somente após regra aprovada e testes visuais/negativos.

Nenhuma etapa autoriza dados reais antes dos gates P0, restore integral e go-live formal.

## 10. Critérios de aceite para os futuros templates

- Dicionário aprovado para cada campo, incluindo finalidade, fonte, autor, sensibilidade, obrigatoriedade, visibilidade e retenção.
- Fixtures exclusivamente sintéticas e explicitamente fictícias para vazio, mínimo, completo e divergência de fonte.
- Policy testada para visitante, conta inativa, equipe técnica ativa e autenticado sem papel técnico; ID trocado continua negado e download/geração são auditados sem payload sensível.
- PDF conferido com português, múltiplas páginas, dados ausentes, conteúdo longo, destinatário opcional, processos múltiplos, 1–4 signatários e, quando aprovado, foto retrato/paisagem/ausente/corrompida.
- Finalização imutável com snapshots, hash e número transacional; retificação/cancelamento preservam autoria e emissão anterior.
- Catálogo de criação oferece exatamente os cinco tipos aprovados e rejeita tipo antigo/desconhecido; evidências históricas de encaminhamento não aparecem como sexto documento.
- Quando houver evidência externa de assinatura, modalidade, provedor, vínculo com a versão, valor jurídico, acesso, retenção e verificação devem estar aprovados em `DEC-04`; código/QR/link não pode virar URL pública permanente nem ser aceito como prova apenas por ter sido observado no exemplo.
- Inventário e custódia conciliáveis entre ficha de ingresso e termo; desligamento conciliável com o episódio; reavaliação conciliável com agenda.
- Nenhum dado institucional ou pessoal hardcoded em template, nome de arquivo, URL pública, log ou artefato de teste.

## 11. Checklist para validação pelas técnicas (`DEC-06D`)

Este checklist é copiável e imprimível e contém exatamente os cinco documentos funcionais aprovados. A classificação empírica v0 é apenas uma sugestão de trabalho: as técnicas devem marcar cada campo e acrescentar observações. A marcação não autoriza coleta ou retenção de dado sensível; essas decisões continuam em `DEC-06` e `LGPD-01/02/03`. Não escrever neste checklist nomes, números, narrativas, diagnósticos ou outros valores reais: classificar somente o **campo**.

Legenda por linha das tabelas classificatórias: **O** = obrigatório; **Op** = opcional; **C** = condicional; **Não usar** = retirar do modelo. Marcar uma opção: `[ ] Obrigatório  [ ] Opcional  [ ] Condicional  [ ] Não usar`. Se for condicional, explicar a condição na coluna “Observação”. Esta legenda **não se aplica** à tabela de invariantes funcionais a seguir.

#### Invariantes funcionais aprovados — não sujeitos a “Não usar”

As linhas desta tabela não reabrem a existência nem a semântica mínima dos documentos. A equipe confirma somente o **rótulo/exibição** e a **fonte/vínculo** de cada invariante. Campos ausentes continuam usando estado de qualidade autorizado; a confirmação não autoriza inventar conteúdo nem coletar dado sem finalidade.

| Documento | Invariante funcional aprovado | Rótulo/exibição confirmados | Fonte/vínculo confirmados | Observação |
|---|---|:---:|:---:|---|
| Ficha de ingresso | Vínculo inequívoco com a pessoa acolhida | [ ] | [ ] | Confirmar como a identidade aparece, sem retirar o vínculo canônico. |
| Ficha de ingresso | Vínculo com o episódio de acolhimento aberto | [ ] | [ ] | O episódio, não a pessoa, concentra o ingresso. |
| Ficha de ingresso | Data do ingresso | [ ] | [ ] | Fonte: evento de abertura do episódio. |
| Ficha de ingresso | Hora do ingresso | [ ] | [ ] | Fonte: evento de abertura do episódio. |
| Ficha de ingresso | Unidade do ingresso | [ ] | [ ] | Derivada do contexto fixo do servidor. |
| Ficha de ingresso | Motivo/fundamento do ingresso | [ ] | [ ] | Um único fato semântico interno, com fonte e estado de qualidade preservados; confirmar se a exibição usa um ou dois rótulos, sem retirar o fato. |
| Ficha de ingresso | Origem do encaminhamento | [ ] | [ ] | Não confundir com órgão ou pessoa condutora. |
| Ficha de ingresso | Autor do registro de ingresso | [ ] | [ ] | Conta individual e horário preservados. |
| Ficha de ingresso | Profissional que recebeu o ingresso | [ ] | [ ] | Confirmar rótulo e fonte; pode coincidir com o autor. |
| PIA | Vínculo inequívoco com a pessoa acolhida | [ ] | [ ] | Confirmar identificação exibida e fonte canônica. |
| PIA | Vínculo com o episódio de acolhimento vigente no documento | [ ] | [ ] | O snapshot preserva a versão selecionada. |
| PIA | Fatos essenciais referenciados | [ ] | [ ] | O PIA não substitui os módulos canônicos nem inventa fatos ausentes. |
| PIA | Fonte de cada fato referenciado | [ ] | [ ] | Confirmar exibição sem retirar a proveniência técnica. |
| PIA | Autoria de cada fato referenciado | [ ] | [ ] | Conta/ator e data permanecem rastreáveis. |
| Parecer do acolhido | Vínculo inequívoco com a pessoa acolhida | [ ] | [ ] | Confirmar identificação exibida e fonte canônica. |
| Parecer do acolhido | Vínculo com o episódio de acolhimento | [ ] | [ ] | O snapshot preserva o episódio selecionado. |
| Parecer do acolhido | Vínculo com o procedimento judicial | [ ] | [ ] | Confirmar fonte, rótulo e subcampos exibidos. |
| Parecer do acolhido | Destinatário judicial | [ ] | [ ] | O pedido é formulado ao juiz destinatário. |
| Parecer do acolhido | Fatos referenciados | [ ] | [ ] | Separar fato observado de avaliação profissional. |
| Parecer do acolhido | Fonte de cada fato referenciado | [ ] | [ ] | Preservar proveniência. |
| Parecer do acolhido | Data de cada fato referenciado | [ ] | [ ] | Não substituir por data genérica do documento. |
| Parecer do acolhido | Relato técnico da situação do acolhido | [ ] | [ ] | Confirmar os rótulos dos blocos de contexto/evolução/avaliação. |
| Parecer do acolhido | Pedido formulado ao juiz | [ ] | [ ] | Confirmar rótulo e destinatário/procedimento de origem. |
| Relatório de visita técnica | Identificação inequívoca da visita realizada | [ ] | [ ] | Não confundir com agendamento ou tarefa. |
| Relatório de visita técnica | Data da visita | [ ] | [ ] | Fonte: fato de visita realizado. |
| Relatório de visita técnica | Vínculo canônico interno com uma ou mais pessoas/casos visitados | [ ] | [ ] | Obrigatório no registro interno; admite múltiplos vínculos quando a visita envolver irmãos. A exibição no PDF é classificada separadamente. |
| Relatório de visita técnica | Vínculo canônico interno com o episódio aplicável a cada pessoa/caso | [ ] | [ ] | Obrigatório e repetível em correspondência inequívoca com cada pessoa/caso. A exibição no PDF é classificada separadamente. |
| Relatório de visita técnica | Estado de participação por pessoa/caso vinculado | [ ] | [ ] | Registrar individualmente `participou`, `não participou` ou `não informado` por seleção no vínculo canônico; não usar booleano global. A exibição no PDF é classificada separadamente. |
| Termo de Recebimento/Entrega | Vínculo canônico interno inequívoco com a pessoa acolhida | [ ] | [ ] | Obrigatório para a custódia; a exibição da identificação no PDF é classificada separadamente. |
| Termo de Recebimento/Entrega | Vínculo canônico interno com o episódio/caso aplicável | [ ] | [ ] | Obrigatório para contextualizar a custódia; a exibição no PDF é classificada separadamente. |
| Termo de Recebimento/Entrega | Item objeto do evento de custódia | [ ] | [ ] | Documento ou pertence pessoal canônico. |
| Termo de Recebimento/Entrega | Tipo do evento de custódia | [ ] | [ ] | Recebimento, entrega, devolução ou transferência. |
| Termo de Recebimento/Entrega | Parte que entrega | [ ] | [ ] | Identidade/papel no evento. |
| Termo de Recebimento/Entrega | Parte que recebe | [ ] | [ ] | Identidade/papel no evento. |
| Termo de Recebimento/Entrega | Data do evento | [ ] | [ ] | Derivada do evento de custódia. |
| Termo de Recebimento/Entrega | Hora do evento | [ ] | [ ] | Persistida em UTC e apresentada no fuso institucional. |
| Termo de Recebimento/Entrega | Cadeia de custódia preservada | [ ] | [ ] | Eventos append-only; estado atual é derivado, nunca sobrescrito. |

### Ficha de ingresso

#### Identificação

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Data de nascimento | [ ] | [ ] | [ ] | [ ] | |
| Sexo | [ ] | [ ] | [ ] | [ ] | |
| Identidade de gênero | [ ] | [ ] | [ ] | [ ] | |
| Raça/cor | [ ] | [ ] | [ ] | [ ] | |
| Naturalidade | [ ] | [ ] | [ ] | [ ] | |
| Registro de nascimento disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar a ficha. |
| CPF disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar a ficha. |
| RG disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar a ficha. |
| Cartão SUS/CNS disponível | [ ] | [ ] | [ ] | [ ] | Dado de Saúde; exigir necessidade e acesso compatível. |
| NIS disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar a ficha. |
| Título eleitoral disponível | [ ] | [ ] | [ ] | [ ] | Somente quando aplicável e necessário. |

#### Filiação, responsáveis e composição familiar

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Pessoa relacionada | [ ] | [ ] | [ ] | [ ] | |
| Vínculo/parentesco ou papel | [ ] | [ ] | [ ] | [ ] | |
| Contato necessário | [ ] | [ ] | [ ] | [ ] | Definir finalidade e vigência. |
| Endereço necessário | [ ] | [ ] | [ ] | [ ] | Definir finalidade e vigência. |
| Identificadores estritamente necessários do responsável | [ ] | [ ] | [ ] | [ ] | Não copiar documentos sem finalidade aprovada. |
| Pessoa da composição familiar | [ ] | [ ] | [ ] | [ ] | |
| Nascimento ou idade da pessoa familiar | [ ] | [ ] | [ ] | [ ] | Preferir nascimento conhecido e idade derivada. |
| Parentesco da pessoa familiar | [ ] | [ ] | [ ] | [ ] | |
| Ocupação da pessoa familiar | [ ] | [ ] | [ ] | [ ] | |

#### Ingresso

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Solicitante do acolhimento | [ ] | [ ] | [ ] | [ ] | |
| Órgão condutor | [ ] | [ ] | [ ] | [ ] | Separado da pessoa condutora. |
| Pessoa condutora | [ ] | [ ] | [ ] | [ ] | Separada do órgão condutor. |
| Motivo do ingresso exibido | [ ] | [ ] | [ ] | [ ] | Classificar somente rótulo/exibição; integra o fato semântico interno `motivo/fundamento do ingresso`. |
| Fundamento do ingresso exibido | [ ] | [ ] | [ ] | [ ] | Classificar somente rótulo/exibição; integra o mesmo fato semântico interno e pode ser apresentado separadamente se confirmado. |
| Acolhimentos anteriores: estado da informação | [ ] | [ ] | [ ] | [ ] | Há, não há ou não informado; não inferir. |
| Episódio anterior: data de ingresso | [ ] | [ ] | [ ] | [ ] | Repetível por episódio; somente quando conhecida. |
| Episódio anterior: data de encerramento | [ ] | [ ] | [ ] | [ ] | Somente quando conhecida. |
| Episódio anterior: organização/unidade | [ ] | [ ] | [ ] | [ ] | Somente quando conhecida e necessária. |
| Episódio anterior: motivo | [ ] | [ ] | [ ] | [ ] | Registrar fonte; não inferir. |
| Episódio anterior: fundamento | [ ] | [ ] | [ ] | [ ] | Somente quando conhecido. |
| Episódio anterior: fonte | [ ] | [ ] | [ ] | [ ] | Referenciar documento, órgão ou informante autorizado. |

#### Conselho Tutelar e processos

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Número do procedimento do Conselho Tutelar | [ ] | [ ] | [ ] | [ ] | Somente quando existente na fonte. |
| Evento do procedimento do Conselho Tutelar | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação como campo separado; identifica a que a data se refere. |
| Data do evento do Conselho Tutelar | [ ] | [ ] | [ ] | [ ] | Não criar “data do processo” genérica. |
| Número CNJ do processo | [ ] | [ ] | [ ] | [ ] | |
| Tipo do processo | [ ] | [ ] | [ ] | [ ] | |
| Vara | [ ] | [ ] | [ ] | [ ] | |
| Comarca | [ ] | [ ] | [ ] | [ ] | |
| Outro processo: número | [ ] | [ ] | [ ] | [ ] | Repetível por processo; somente quando existente. |
| Outro processo: tipo | [ ] | [ ] | [ ] | [ ] | |
| Outro processo: vara | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Outro processo: comarca | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |

#### Saúde e Educação iniciais

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Necessidade de Saúde imediata | [ ] | [ ] | [ ] | [ ] | Registrar estado, sem diagnóstico inferido. |
| Fonte da informação de Saúde | [ ] | [ ] | [ ] | [ ] | |
| Estado de confirmação da informação de Saúde | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação da granularidade exigida. |
| Data de confirmação da informação de Saúde | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação; preencher somente quando houver confirmação. |
| Encaminhamento inicial de Saúde | [ ] | [ ] | [ ] | [ ] | Não confundir encaminhado com realizado. |
| Escola | [ ] | [ ] | [ ] | [ ] | |
| Rede de ensino | [ ] | [ ] | [ ] | [ ] | |
| Matrícula | [ ] | [ ] | [ ] | [ ] | |
| Ano/série | [ ] | [ ] | [ ] | [ ] | |
| Turma | [ ] | [ ] | [ ] | [ ] | |
| Turno | [ ] | [ ] | [ ] | [ ] | |
| Situação escolar | [ ] | [ ] | [ ] | [ ] | |
| Pendência escolar | [ ] | [ ] | [ ] | [ ] | Preferir tipo, responsável e prazo estruturados. |

#### Visitação e convivência

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Pessoa relacionada à visita/convivência | [ ] | [ ] | [ ] | [ ] | |
| Situação autorizada ou restrita | [ ] | [ ] | [ ] | [ ] | |
| Condição da autorização/restrição | [ ] | [ ] | [ ] | [ ] | |
| Vigência | [ ] | [ ] | [ ] | [ ] | |
| Origem da regra de visitação/convivência | [ ] | [ ] | [ ] | [ ] | |
| Decisão que sustenta a regra de visitação/convivência | [ ] | [ ] | [ ] | [ ] | |

#### Documentos, pertences e recebimento

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Tipo de documento recebido | [ ] | [ ] | [ ] | [ ] | |
| Identificador mínimo necessário do documento | [ ] | [ ] | [ ] | [ ] | Minimizar; não usar como nome de arquivo. |
| Original ou cópia | [ ] | [ ] | [ ] | [ ] | |
| Físico ou digital | [ ] | [ ] | [ ] | [ ] | |
| Quantidade | [ ] | [ ] | [ ] | [ ] | |
| Condição do documento | [ ] | [ ] | [ ] | [ ] | |
| Custódia do documento | [ ] | [ ] | [ ] | [ ] | Localização lógica, não caminho de storage. |
| Descrição do pertence | [ ] | [ ] | [ ] | [ ] | |
| Quantidade do pertence | [ ] | [ ] | [ ] | [ ] | |
| Condição do pertence | [ ] | [ ] | [ ] | [ ] | |
| Custódia do pertence | [ ] | [ ] | [ ] | [ ] | |
| Pessoa que entregou | [ ] | [ ] | [ ] | [ ] | |
| Pessoa que recebeu | [ ] | [ ] | [ ] | [ ] | |
| Data do recebimento | [ ] | [ ] | [ ] | [ ] | |
| Local do recebimento | [ ] | [ ] | [ ] | [ ] | |
| Ressalvas | [ ] | [ ] | [ ] | [ ] | Texto somente quando necessário. |
| Signatários do recebimento | [ ] | [ ] | [ ] | [ ] | Modalidade depende de `DEC-04`. |

### PIA (estrutura funcional adotada do exemplo mais completo)

| Grupo/campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Identificação: data de nascimento | [ ] | [ ] | [ ] | [ ] | Permitir estado documentado de ausência/divergência. |
| Identificação: idade derivada | [ ] | [ ] | [ ] | [ ] | Calcular na data de referência; não digitar como fonte independente. |
| Identificação: sexo | [ ] | [ ] | [ ] | [ ] | Finalidade e fonte precisam ser aprovadas. |
| Identificação: identidade de gênero | [ ] | [ ] | [ ] | [ ] | Finalidade, fonte e acesso precisam ser aprovados. |
| Identificação: raça/cor | [ ] | [ ] | [ ] | [ ] | Finalidade e fonte precisam ser aprovadas. |
| Identificação: naturalidade | [ ] | [ ] | [ ] | [ ] | |
| Identificação: registro de nascimento disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar o PIA. |
| Identificação: CPF disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar o PIA. |
| Identificação: RG disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar o PIA. |
| Identificação: Cartão SUS/CNS disponível | [ ] | [ ] | [ ] | [ ] | Dado de Saúde; exigir necessidade e acesso compatível. |
| Identificação: NIS disponível | [ ] | [ ] | [ ] | [ ] | Não forçar obtenção apenas para completar o PIA. |
| Identificação: título eleitoral disponível | [ ] | [ ] | [ ] | [ ] | Somente quando aplicável e necessário. |
| Filiação/responsáveis: pessoa | [ ] | [ ] | [ ] | [ ] | Permitir estado explícito de ausência. |
| Filiação/responsáveis: vínculo/papel | [ ] | [ ] | [ ] | [ ] | |
| Filiação/responsáveis: contato necessário | [ ] | [ ] | [ ] | [ ] | Definir finalidade e vigência. |
| Filiação/responsáveis: endereço necessário | [ ] | [ ] | [ ] | [ ] | Definir finalidade e vigência. |
| Filiação/responsáveis: identificadores estritamente necessários | [ ] | [ ] | [ ] | [ ] | Não copiar documentos sem finalidade aprovada. |
| Composição familiar: pessoa | [ ] | [ ] | [ ] | [ ] | Permitir estado explícito de ausência. |
| Composição familiar: nascimento ou idade | [ ] | [ ] | [ ] | [ ] | Preferir nascimento conhecido e idade derivada. |
| Composição familiar: parentesco | [ ] | [ ] | [ ] | [ ] | |
| Composição familiar: ocupação | [ ] | [ ] | [ ] | [ ] | |
| Irmão em outra instituição: pessoa | [ ] | [ ] | [ ] | [ ] | |
| Irmão em outra instituição: nascimento | [ ] | [ ] | [ ] | [ ] | |
| Irmão em outra instituição: entidade | [ ] | [ ] | [ ] | [ ] | |
| Irmão menor com terceiro: pessoa | [ ] | [ ] | [ ] | [ ] | |
| Irmão menor com terceiro: nascimento | [ ] | [ ] | [ ] | [ ] | |
| Irmão menor com terceiro: responsável | [ ] | [ ] | [ ] | [ ] | |
| Informação de guarda | [ ] | [ ] | [ ] | [ ] | Somente quando conhecida e necessária. |
| Acolhimentos anteriores: estado da informação | [ ] | [ ] | [ ] | [ ] | Há, não há ou não informado; não inferir. |
| Episódio anterior: data de ingresso | [ ] | [ ] | [ ] | [ ] | Repetível por episódio; somente quando conhecida. |
| Episódio anterior: data de encerramento | [ ] | [ ] | [ ] | [ ] | Somente quando conhecida. |
| Episódio anterior: organização/unidade | [ ] | [ ] | [ ] | [ ] | Somente quando conhecida e necessária. |
| Episódio anterior: motivo | [ ] | [ ] | [ ] | [ ] | Registrar fonte; não inferir. |
| Episódio anterior: fundamento | [ ] | [ ] | [ ] | [ ] | Somente quando conhecido. |
| Episódio anterior: fonte | [ ] | [ ] | [ ] | [ ] | Referenciar documento, órgão ou informante autorizado. |
| Realizado por: órgão | [ ] | [ ] | [ ] | [ ] | Separado da pessoa. |
| Realizado por: pessoa | [ ] | [ ] | [ ] | [ ] | Separada do órgão. |
| Motivo catalogado | [ ] | [ ] | [ ] | [ ] | Taxonomia ainda precisa de aprovação. |
| Motivo “Outro” e complemento | [ ] | [ ] | [ ] | [ ] | Complemento curto somente quando selecionado. |
| Perspectiva da equipe | [ ] | [ ] | [ ] | [ ] | Texto profissional longo. |
| Perspectiva da família | [ ] | [ ] | [ ] | [ ] | Texto ou estado explícito de não obtenção. |
| Perspectiva da criança/adolescente | [ ] | [ ] | [ ] | [ ] | Texto ou estado explícito de não obtenção. |
| Conselho Tutelar: número do procedimento | [ ] | [ ] | [ ] | [ ] | Somente quando existente na fonte. |
| Conselho Tutelar: evento do procedimento | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação como campo separado; identifica a que a data se refere. |
| Conselho Tutelar: data do evento | [ ] | [ ] | [ ] | [ ] | Não criar data genérica. |
| Processo principal: número CNJ | [ ] | [ ] | [ ] | [ ] | Estrutura observada no catálogo sanitizado. |
| Processo principal: tipo | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação no PIA de referência. |
| Processo principal: vara | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação no PIA de referência. |
| Processo principal: comarca | [ ] | [ ] | [ ] | [ ] | Pendente de confirmação no PIA de referência. |
| Outro processo: tipo | [ ] | [ ] | [ ] | [ ] | Estrutura observada; confirmar vocabulário/taxonomia. |
| Outro processo: número | [ ] | [ ] | [ ] | [ ] | Estrutura observada; confirmar formato aplicável. |
| Outro processo: vara | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Outro processo: comarca | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Especificidade física/sensorial | [ ] | [ ] | [ ] | [ ] | Não inferir diagnóstico. |
| Especificidade intelectual | [ ] | [ ] | [ ] | [ ] | Não inferir diagnóstico. |
| Especificidade de saúde mental | [ ] | [ ] | [ ] | [ ] | Não inferir diagnóstico. |
| Uso de álcool/drogas | [ ] | [ ] | [ ] | [ ] | Sensível; finalidade e acesso específicos. |
| Condição crônica | [ ] | [ ] | [ ] | [ ] | Sensível; não inferir diagnóstico. |
| Gestação | [ ] | [ ] | [ ] | [ ] | Sensível; necessidade de conhecimento específica. |
| Filhos | [ ] | [ ] | [ ] | [ ] | Definir necessidade e granularidade. |
| Trajetória de rua | [ ] | [ ] | [ ] | [ ] | |
| Refúgio/imigração | [ ] | [ ] | [ ] | [ ] | |
| Migração | [ ] | [ ] | [ ] | [ ] | |
| Especificidade “Outro” | [ ] | [ ] | [ ] | [ ] | Complemento curto quando necessário. |
| Estado “não se aplica” para especificidades | [ ] | [ ] | [ ] | [ ] | |
| Informações familiares relevantes | [ ] | [ ] | [ ] | [ ] | Texto longo somente se os dados estruturados não bastarem. |
| Saúde da pessoa acolhida | [ ] | [ ] | [ ] | [ ] | Pré-compilar fatos referenciados. |
| Saúde da família | [ ] | [ ] | [ ] | [ ] | Coletar apenas se necessário para a finalidade do PIA. |
| Educação/profissionalização da pessoa acolhida | [ ] | [ ] | [ ] | [ ] | Pré-compilar fatos referenciados. |
| Educação/profissionalização da família | [ ] | [ ] | [ ] | [ ] | Coletar apenas se necessário. |
| Assistência Social da pessoa acolhida | [ ] | [ ] | [ ] | [ ] | Pré-compilar fatos referenciados. |
| Assistência Social da família | [ ] | [ ] | [ ] | [ ] | Coletar apenas se necessário. |
| Esporte, cultura e lazer | [ ] | [ ] | [ ] | [ ] | Pré-compilar fatos referenciados. |
| Considerações/avaliação técnica | [ ] | [ ] | [ ] | [ ] | Texto profissional longo. |
| Plano: objetivo | [ ] | [ ] | [ ] | [ ] | |
| Plano: ação | [ ] | [ ] | [ ] | [ ] | |
| Plano: responsável | [ ] | [ ] | [ ] | [ ] | |
| Plano: prazo ou condição de revisão | [ ] | [ ] | [ ] | [ ] | |
| Plano: status | [ ] | [ ] | [ ] | [ ] | |
| Plano: evidência | [ ] | [ ] | [ ] | [ ] | |
| Plano: desfecho | [ ] | [ ] | [ ] | [ ] | |
| Providência ao Judiciário: tipo | [ ] | [ ] | [ ] | [ ] | |
| Providência ao Judiciário: destinatário | [ ] | [ ] | [ ] | [ ] | |
| Providência ao Judiciário: processo | [ ] | [ ] | [ ] | [ ] | |
| Providência ao Judiciário: fundamentação | [ ] | [ ] | [ ] | [ ] | Texto longo somente quando houver providência. |
| Emissão: data | [ ] | [ ] | [ ] | [ ] | Derivar no ato de finalização. |
| Emissão: local | [ ] | [ ] | [ ] | [ ] | Obter da configuração institucional versionada. |
| Autores profissionais exibidos | [ ] | [ ] | [ ] | [ ] | O registro técnico de proveniência permanece invariante. |
| Signatários exibidos | [ ] | [ ] | [ ] | [ ] | Modalidade depende de `DEC-04`. |
| Ordem de exibição dos signatários | [ ] | [ ] | [ ] | [ ] | Modalidade depende de `DEC-04`. |
| Cargo/função exibido | [ ] | [ ] | [ ] | [ ] | |
| Registro profissional exibido | [ ] | [ ] | [ ] | [ ] | Somente quando aplicável. |
| Foto da criança/adolescente no PIA | [ ] | [ ] | [ ] | [ ] | Item separado: retrato privado do perfil; `DOC-01` decide. |
| Verificação externa da assinatura | [ ] | [ ] | [ ] | [ ] | Condicional à modalidade aprovada e efetivamente usada. |

### Parecer do acolhido

O Parecer do acolhido relata tecnicamente a situação e formula uma solicitação ao juiz. A existência e os rótulos dos blocos narrativos são confirmados na tabela de invariantes, sem opção de retirada. A classificação abaixo cobre apenas metadados e elementos de exibição adicionais, priorizando seleção/autopreenchimento.

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Instituição exibida | [ ] | [ ] | [ ] | [ ] | Obter da configuração institucional versionada. |
| Local de emissão | [ ] | [ ] | [ ] | [ ] | Obter da configuração institucional versionada. |
| Logo exibido | [ ] | [ ] | [ ] | [ ] | Obter da configuração institucional versionada. |
| Número | [ ] | [ ] | [ ] | [ ] | Sequência central quando aplicável. |
| Data de emissão | [ ] | [ ] | [ ] | [ ] | Derivar na finalização. |
| Assunto | [ ] | [ ] | [ ] | [ ] | Preferir seleção/título curto. |
| Destinatário: órgão/instituição | [ ] | [ ] | [ ] | [ ] | |
| Destinatário: pessoa | [ ] | [ ] | [ ] | [ ] | |
| Destinatário: cargo/função | [ ] | [ ] | [ ] | [ ] | |
| Destinatário: tratamento | [ ] | [ ] | [ ] | [ ] | |
| Destinatário: vara | [ ] | [ ] | [ ] | [ ] | |
| Destinatário: comarca | [ ] | [ ] | [ ] | [ ] | |
| Meio de remessa | [ ] | [ ] | [ ] | [ ] | |
| Identificação da pessoa exibida | [ ] | [ ] | [ ] | [ ] | O vínculo canônico permanece invariante; classificar somente a exibição. |
| Identificação do episódio exibida | [ ] | [ ] | [ ] | [ ] | O vínculo canônico permanece invariante; classificar somente a exibição. |
| Procedimento judicial: número exibido | [ ] | [ ] | [ ] | [ ] | O vínculo permanece invariante. |
| Procedimento judicial: tipo exibido | [ ] | [ ] | [ ] | [ ] | |
| Procedimento judicial: vara exibida | [ ] | [ ] | [ ] | [ ] | |
| Procedimento judicial: comarca exibida | [ ] | [ ] | [ ] | [ ] | |
| Providências já realizadas | [ ] | [ ] | [ ] | [ ] | Selecionar registros canônicos e complementar somente se necessário. |
| Anexos | [ ] | [ ] | [ ] | [ ] | Referenciar artefatos privados, nunca caminho de storage. |
| Peças/documentos vinculados | [ ] | [ ] | [ ] | [ ] | Referenciar versões, não nomes de arquivo. |
| Autores profissionais exibidos | [ ] | [ ] | [ ] | [ ] | Proveniência técnica permanece invariante. |
| Signatários exibidos | [ ] | [ ] | [ ] | [ ] | Modalidade depende de DEC-04. |
| Ordem de exibição dos signatários | [ ] | [ ] | [ ] | [ ] | |
| Cargo/função exibido | [ ] | [ ] | [ ] | [ ] | |
| Registro profissional exibido | [ ] | [ ] | [ ] | [ ] | Somente quando aplicável. |

### Relatório de visita técnica

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Instituição exibida | [ ] | [ ] | [ ] | [ ] | Obter da configuração institucional versionada. |
| Número | [ ] | [ ] | [ ] | [ ] | Quando a política documental exigir. |
| Data de emissão | [ ] | [ ] | [ ] | [ ] | Derivar na finalização. |
| Identificação da(s) pessoa(s) acolhida(s) exibida | [ ] | [ ] | [ ] | [ ] | Classificar somente a exibição no PDF; o vínculo interno admite uma ou mais pessoas, inclusive irmãos, e é invariante. |
| Identificação do(s) episódio(s)/caso(s) exibida | [ ] | [ ] | [ ] | [ ] | Classificar somente a exibição no PDF; cada pessoa mantém vínculo interno com o episódio aplicável. |
| Processo vinculado: número | [ ] | [ ] | [ ] | [ ] | Repetível por processo; somente quando necessário. |
| Processo vinculado: tipo | [ ] | [ ] | [ ] | [ ] | |
| Processo vinculado: vara | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Processo vinculado: comarca | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Hora de início | [ ] | [ ] | [ ] | [ ] | |
| Hora de término | [ ] | [ ] | [ ] | [ ] | |
| Tipo de visita | [ ] | [ ] | [ ] | [ ] | Seleção catalogada. |
| Local da visita | [ ] | [ ] | [ ] | [ ] | Seleção/cadastro institucional quando possível. |
| Objetivo da visita | [ ] | [ ] | [ ] | [ ] | Seleção catalogada; “Outro” exige complemento curto. |
| Profissionais da equipe participantes | [ ] | [ ] | [ ] | [ ] | Selecionar contas/pessoas ativas. |
| Estado de participação de cada pessoa acolhida exibido | [ ] | [ ] | [ ] | [ ] | Classificar somente a exibição no PDF; internamente selecionar `participou`, `não participou` ou `não informado` para cada pessoa já vinculada, repetível para irmãos. |
| Familiares participantes | [ ] | [ ] | [ ] | [ ] | Selecionar vínculos canônicos. |
| Participantes externos | [ ] | [ ] | [ ] | [ ] | Identificar pessoa/órgão e papel somente quando necessário. |
| Fatos referenciados | [ ] | [ ] | [ ] | [ ] | Selecionar fatos canônicos com fonte/data. |
| Observações/fatos da visita | [ ] | [ ] | [ ] | [ ] | Texto longo somente para o que não couber em dados estruturados. |
| Avaliação técnica | [ ] | [ ] | [ ] | [ ] | Texto profissional longo quando a finalidade exigir. |
| Encaminhamento: ação | [ ] | [ ] | [ ] | [ ] | Item estruturado repetível. |
| Encaminhamento: destino | [ ] | [ ] | [ ] | [ ] | |
| Encaminhamento: responsável | [ ] | [ ] | [ ] | [ ] | |
| Encaminhamento: prazo | [ ] | [ ] | [ ] | [ ] | Pode gerar tarefa/agenda sem marcar realização. |
| Encaminhamento: status | [ ] | [ ] | [ ] | [ ] | Derivar do registro canônico vinculado. |
| Encaminhamento: desfecho | [ ] | [ ] | [ ] | [ ] | Preencher somente quando houver desfecho. |
| Conclusão | [ ] | [ ] | [ ] | [ ] | Texto somente quando necessário. |
| Anexos | [ ] | [ ] | [ ] | [ ] | Referenciar artefatos privados. |
| Autores profissionais exibidos | [ ] | [ ] | [ ] | [ ] | Proveniência técnica permanece invariante. |
| Signatários exibidos | [ ] | [ ] | [ ] | [ ] | |
| Ordem de exibição dos signatários | [ ] | [ ] | [ ] | [ ] | |
| Cargo/função exibido | [ ] | [ ] | [ ] | [ ] | |
| Registro profissional exibido | [ ] | [ ] | [ ] | [ ] | Somente quando aplicável. |

### Termo de Recebimento/Entrega de documentos e pertences pessoais

| Campo | O | Op | C | Não usar | Observação |
|---|:---:|:---:|:---:|:---:|---|
| Identificação da pessoa acolhida exibida | [ ] | [ ] | [ ] | [ ] | Classificar somente a exibição no PDF; o vínculo interno inequívoco é invariante. |
| Identificação do episódio/caso exibida | [ ] | [ ] | [ ] | [ ] | Classificar somente a exibição no PDF; o vínculo interno aplicável é invariante. |
| Processo vinculado: número | [ ] | [ ] | [ ] | [ ] | Repetível por processo; somente quando necessário. |
| Processo vinculado: tipo | [ ] | [ ] | [ ] | [ ] | |
| Processo vinculado: vara | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Processo vinculado: comarca | [ ] | [ ] | [ ] | [ ] | Quando aplicável. |
| Tipo do item | [ ] | [ ] | [ ] | [ ] | Seleção catalogada. |
| Descrição | [ ] | [ ] | [ ] | [ ] | Curta e objetiva. |
| Identificador mínimo necessário | [ ] | [ ] | [ ] | [ ] | Minimizar; nunca usar em nome de arquivo. |
| Original ou cópia | [ ] | [ ] | [ ] | [ ] | Quando o item for documento. |
| Físico ou digital | [ ] | [ ] | [ ] | [ ] | |
| Quantidade | [ ] | [ ] | [ ] | [ ] | |
| Condição do item | [ ] | [ ] | [ ] | [ ] | Seleção catalogada + ressalva curta quando necessário. |
| Local interno | [ ] | [ ] | [ ] | [ ] | Localização lógica, nunca caminho de storage. |
| Origem da transferência | [ ] | [ ] | [ ] | [ ] | Condicional à transferência. |
| Destino da transferência | [ ] | [ ] | [ ] | [ ] | Condicional à transferência. |
| Motivo da entrega/devolução/transferência | [ ] | [ ] | [ ] | [ ] | Condicional conforme evento. |
| Conferente | [ ] | [ ] | [ ] | [ ] | |
| Resultado da conferência | [ ] | [ ] | [ ] | [ ] | |
| Ressalvas | [ ] | [ ] | [ ] | [ ] | Texto somente quando necessário. |
| Signatários exibidos | [ ] | [ ] | [ ] | [ ] | |
| Papel de cada signatário | [ ] | [ ] | [ ] | [ ] | |
| Ordem dos signatários | [ ] | [ ] | [ ] | [ ] | |
| Número do termo | [ ] | [ ] | [ ] | [ ] | Quando a política documental exigir. |
| Data de emissão | [ ] | [ ] | [ ] | [ ] | Derivar na finalização. |

### Metadados técnicos invariantes — não sujeitos à retirada

Os checklists anteriores decidem quais campos negociais são exigidos e quais elementos devem aparecer no PDF. Eles **não** podem remover os controles técnicos necessários à integridade e à rastreabilidade. Para todo tipo documental aprovado, o sistema mantém, conforme o estágio do documento: identificador interno; tipo; versão e estado técnico; snapshots do conteúdo e dos dados referenciados; fontes; autoria técnica/proveniência; horários; finalização; SHA-256 de cada artefato; vínculos de retificação/cancelamento/substituição; e auditoria append-only de criação, leitura, alteração, finalização, assinatura, validação, download e acesso negado.

No fluxo externo de assinatura, o PDF original e cada artefato assinado possuem SHA-256 próprio e vínculo ao artefato-pai esperado. As técnicas podem decidir **se e como** número, autores profissionais, signatários, cargo, registro, estado, versão, hash ou indicação de retificação aparecem no PDF; a ausência visual não elimina o registro técnico.

### Regras comuns de preenchimento e histórico

- Organizar cada documento em etapas didáticas, com linguagem de trabalho e revisão final antes da emissão.
- Priorizar seleção, taxonomia e autopreenchimento por fatos canônicos. Revelar campos condicionais somente quando a escolha correspondente os tornar necessários.
- Permitir rascunho incompleto e preservar o que foi preenchido ao navegar; validar obrigatórios apenas na finalização.
- Reservar texto longo para narrativa, síntese ou julgamento profissional indispensável. Não pedir que a técnica redigite fato já estruturado.
- Funcionar por teclado, em celular e desktop, com estados de carregamento, vazio, erro e acesso negado.
- Mostrar `Atualizado por [usuária] em [data/hora]` no rascunho e detalhe. Persistir eventos em UTC e apresentar em `America/Sao_Paulo`.
- Criação, alteração, finalização e retificação preservam ator/data. O histórico funcional autorizado mantém versões anteriores; log técnico e trilha de auditoria registram a ocorrência e o campo afetado sem copiar valores sensíveis anteriores/novos.

### Confirmações finais da equipe técnica

- [ ] A classificação de todos os campos foi revisada por pelo menos uma técnica responsável.
- [ ] Campos condicionais possuem condição objetiva registrada.
- [ ] Campos marcados como obrigatórios têm fonte e responsável definidos, sem exigir invenção de dado ausente.
- [ ] Campos sensíveis indispensáveis foram sinalizados para revisão de finalidade, acesso e retenção.
- [x] A equipe confirmou os cinco documentos funcionais: PIA, Relatório de visita técnica, Parecer do acolhido, Ficha de ingresso e Termo de Recebimento/Entrega de documentos e pertences pessoais.
- [x] A equipe confirmou que o exemplo de Parecer fornecido pelo cliente pertence ao tipo Parecer do acolhido e que as antigas hipóteses de Informação, Ofício genérico, Relatório Técnico genérico e Ofício de encaminhamento não são tipos funcionais nesta fase.
- [ ] A equipe registrou dúvidas que exigem coordenação, jurídico ou encarregado de dados.

Responsável(is) pela validação: ____________________________________  Data: ____/____/________

Observações gerais: ______________________________________________________________________________________________
