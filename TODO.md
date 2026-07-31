# Plano de evolução do Centro de Acolhimento

> PRD enxuto + backlog técnico. Documento vivo, criado em 31/07/2026 a partir dos feedbacks dos usuários e de uma auditoria do código atual.

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
6. possa crescer em usuários, registros, arquivos e unidades sem perda de dados ou reescrita prematura da aplicação.

## 3. Estado atual verificado no código

### Já existe, mas precisa ser confirmado pelos usuários

- A foto pode ser enviada no cadastro e o template atual do PDF do PIA tenta exibi-la em “Dados de identificação”. Isso funciona com arquivo local disponível, mas não é confiável no deploy atual e ainda precisa de teste visual/regressivo (`DOC-01`).
- A agenda é **compartilhada na visualização**: o backend carrega todos os eventos para todos os usuários autenticados. Admin pode alterar tudo; servidor altera eventos do próprio setor. A interface não explica essa regra nem oferece filtros de escopo (`AGD-01`).
- O PIA tem campos narrativos de Saúde e Educação, mas não há módulos/abas para registrar atendimentos, medicações, exames, escola ou contatos ao longo do tempo.

### Lacunas funcionais confirmadas

- O cadastro aceita apenas `acolhida` e `desligada`; não representa evasão, internação, retorno nem histórico de mudança.
- A ficha de ingresso não contém todos os dados escolares nem a pessoa/órgão que conduziu o acolhimento.
- Não existe levantamento anual estruturado de atendimentos e encaminhamentos.
- Os ofícios não têm destinatário estruturado nem seleção flexível de signatários.

### Bloqueadores para dados reais

- O README identifica a aplicação como POC sem segurança de produção e destinada apenas a dados fictícios.
- O deploy usa SQLite copiado para `/tmp`; em ambiente serverless isso é efêmero e pode gerar perda ou divergência de dados entre instâncias.
- O script de deploy executa `migrate:fresh --seed`, recriando o banco e inserindo usuários/dados fictícios.
- Fotos e documentos são gravados no disco `public` e expostos por URL; não há autorização no download, expiração de link, varredura antimalware ou armazenamento durável no deploy atual.
- As rotas públicas `POST /register` e `GET /register` continuam ativas, apesar de a documentação afirmar que não há cadastro público.
- O acesso é amplo: qualquer usuário autenticado visualiza toda a base; algumas alterações/exclusões de criança e familiar não passam por Policies específicas.
- Não há trilha de auditoria de leituras, downloads, exportações e mudanças; exclusões são definitivas e podem apagar registros relacionados em cascata.
- A suíte de testes cobre principalmente o esqueleto de autenticação/perfil, não os fluxos de negócio, permissões, PDFs ou relatórios.

**Regra de lançamento:** não inserir dados reais antes de concluir o marco P0 e aprovar formalmente o go-live.

## 4. Decisões de produto pendentes

- [ ] **DEC-01 — Definir o significado dos quatro estados** (P1, P)
  - Decidir se `internado` e `evadido` substituem `acolhido` ou representam uma ausência temporária enquanto a responsabilidade do acolhimento permanece.
  - Recomendação: manter um **episódio de acolhimento** aberto e registrar uma **situação operacional atual** (`na_unidade`, `evadido`, `internado`) até retorno ou desacolhimento.
  - Aceite: glossário assinado pela coordenação, incluindo transições permitidas e exemplos reais.

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
  - Aceite: modalidade, valor jurídico esperado e documentos abrangidos registrados em ADR.

- [ ] **DEC-05 — Decidir escopo por unidade/organização** (P0, P)
  - Confirmar se o sistema atenderá apenas uma casa ou várias unidades/municípios.
  - Se houver possibilidade de múltiplas unidades, introduzir `organizacao_id` e `unidade_id` desde a primeira migração de produção, com isolamento obrigatório de consultas.
  - Aceite: decisão de tenancy documentada antes do novo modelo de dados.

- [ ] **DEC-06 — Obter a ficha de ingresso oficial completa** (P1, P)
  - Anexar um modelo vazio aprovado; não inferir todos os campos a partir de conversas.
  - Classificar cada campo como obrigatório, opcional, sensível, fonte, responsável pela atualização e regra de retenção.
  - Aceite: dicionário de dados aprovado pela equipe técnica e coordenação.

## 5. Backlog funcional

### Épico A — Acolhimento, movimentações e filtros

- [ ] **ACO-01 — Criar episódios de acolhimento e histórico de movimentações** (P1, G; depende de `DEC-01`)
  - Criar `acolhimentos` com entrada, motivo, processo, unidade, órgão/pessoa condutora e eventual saída/motivo.
  - Criar histórico append-only de movimentações com tipo, início, fim/retorno, local, observação, autor e timestamps.
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
  - Armazenar foto de forma privada, com autorização de acesso e miniaturas geradas fora da requisição.
  - Definir finalidade/base legal, quem pode visualizar, prazo de retenção e comportamento após desacolhimento.
  - Remover metadados EXIF e validar conteúdo real do arquivo.
  - Aceite: não existe URL pública permanente; acesso negado é testado; foto continua disponível nos fluxos autorizados e no PIA.

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
  - Filtros: período (com atalho anual), unidade, setor, área, tipo, situação e acolhido.
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

### Épico G — Documentos, ofícios e PDFs

- [ ] **DOC-01 — Validar e robustecer a foto no PIA** (P1, P; depende de `CAD-03`)
  - Confirmar com usuários posição, tamanho, proporção e se a inclusão será obrigatória, opcional por documento ou automática quando houver foto.
  - Fazer o gerador ler a imagem do armazenamento privado e tratar ausência/erro sem quebrar o PDF.
  - Aceite: testes com foto retrato/paisagem e sem foto; PDF correto em desenvolvimento e produção.

- [ ] **DOC-02 — Adicionar destinatário opcional aos ofícios** (P1, M)
  - Campos sugeridos: órgão/instituição, nome da pessoa, cargo/função, forma de tratamento e linha livre complementar.
  - Permitir exemplos como juiz(a), promotor(a), secretaria, CAPS, psicólogo(a) ou coordenador(a).
  - Salvar um snapshot no documento para que futuras mudanças de cadastro não alterem ofícios antigos.
  - Aceite: campo não é obrigatório; quando preenchido aparece na posição aprovada abaixo/acima do número e data em todos os templates de ofício.

- [ ] **DOC-03 — Selecionar signatários por documento** (P1, G; depende de `DEC-04`)
  - Selecionar de 1 a 4 integrantes ativos da equipe, sem dupla fixa e com ordem configurável.
  - Permitir uma pessoa assinar sozinha durante férias/afastamentos.
  - Gravar nome, cargo/função, identificador do usuário e ordem como snapshot do documento.
  - Renderizar blocos de assinatura responsivos no fim do PDF, com quebra de página controlada.
  - Aceite: cenários com 1, 2, 3 e 4 signatários geram PDFs legíveis e preservam os nomes mesmo após alteração do perfil.

- [ ] **DOC-04 — Unificar metadados e templates de documentos** (P2, G)
  - Centralizar destinatário, signatários, numeração, situação (`rascunho`, `finalizado`, `cancelado`), versão e emissão.
  - Criar componentes Blade reutilizáveis de cabeçalho, destinatário, assinatura, rodapé e paginação.
  - Aceite: PIA, ocorrência, visita técnica e termo de pertences usam o mesmo contrato visual e de dados.

- [ ] **DOC-05 — Corrigir concorrência na numeração de ofícios** (P0, M)
  - Substituir o cálculo “maior número + 1” distribuído entre quatro tabelas por sequência central transacional com unicidade por ano/unidade.
  - Definir comportamento de número manual, cancelamento e reutilização.
  - Aceite: teste concorrente não gera números repetidos; todo número possui histórico.

- [ ] **DOC-06 — Versionar e finalizar documentos** (P0, G)
  - Separar rascunho editável de versão final imutável.
  - Registrar autor, revisores, signatários, hash, data de finalização e motivo de retificação/cancelamento.
  - Nunca alterar silenciosamente um PDF já emitido; correção gera nova versão ligada à anterior.
  - Aceite: o conteúdo de uma versão final pode ser comprovado e reproduzido.

## 6. Segurança, LGPD e governança

> Estas tarefas são requisitos de produto, não apenas infraestrutura. A validação final deve envolver o controlador dos dados, o encarregado e assessoria jurídica. Consentimento não deve ser assumido como base legal padrão: a finalidade e a hipótese legal precisam ser definidas para cada tratamento, sempre considerando o melhor interesse da criança/adolescente.

- [ ] **LGPD-01 — Inventariar dados, finalidades, bases legais e agentes** (P0, G)
  - Mapear cadastro, documentos, saúde, educação, agenda, relatórios, logs, backups, suporte e fornecedores.
  - Identificar controlador, operadores/suboperadores, encarregado e canal dos titulares.
  - Registrar finalidade, necessidade, compartilhamentos, localização, retenção e descarte de cada categoria.
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
  - Exigir e-mail verificado quando aplicável, MFA para perfis privilegiados, política de senha, bloqueio/inativação e encerramento de sessões.
  - Aceite: visitante não cria conta por UI nem requisição direta; testes cobrem convite, inativação, MFA e recuperação.

- [ ] **SEG-02 — Implementar RBAC/ABAC com menor privilégio** (P0, G)
  - Substituir verificações dispersas por Laravel Policies/Gates para ver, criar, alterar, finalizar, excluir, baixar e exportar.
  - Definir permissões por função, setor, unidade, vínculo com caso e sensibilidade; Saúde/reprodutiva requer regra granular.
  - Aplicar escopo no backend em todas as consultas, não apenas esconder botões.
  - Aceite: matriz automatizada cobre cada perfil e ação; teste negativo impede IDOR trocando IDs na URL.

- [ ] **SEG-03 — Criar auditoria inviolável e pesquisável** (P0, G)
  - Registrar login, falhas, visualização de ficha sensível, busca, criação, antes/depois de alteração, download, PDF, exportação, finalização e exclusão.
  - Guardar ator, ação, objeto, data/hora, unidade, IP/contexto necessário e justificativa quando exigida, sem duplicar conteúdo sensível em logs comuns.
  - Proteger logs contra alteração e definir acesso/retensão próprios.
  - Aceite: é possível responder quem acessou/alterou/exportou um registro e quando.

- [ ] **SEG-04 — Proteger configuração, sessão e comunicação** (P0, M)
  - Produção com `APP_DEBUG=false`, HTTPS obrigatório, cookies `Secure`, `HttpOnly`, `SameSite` adequado e sessão criptografada/centralizada.
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

### Direção recomendada

Manter **Laravel + React/Inertia como monólito modular** nesta fase. A stack atende o domínio e a equipe ganha mais separando módulos e dados do que introduzindo microserviços. Escalar primeiro com aplicação stateless, PostgreSQL, object storage privado, filas e observabilidade; considerar serviços separados apenas diante de medição ou fronteira organizacional real.

- [ ] **ARQ-01 — Migrar SQLite para PostgreSQL gerenciado** (P0, XG)
  - Provisionar ambientes isolados, TLS, criptografia em repouso, alta disponibilidade conforme risco, backups automáticos e point-in-time recovery.
  - Remover a cópia do SQLite para `/tmp` e qualquer dependência de filesystem da instância.
  - Criar migração/exportação verificável, constraints, chaves, índices e plano de rollback.
  - Executar teste real de restauração e registrar RPO/RTO.
  - Aceite: múltiplas instâncias veem dados consistentes; reinício/deploy não perde dados; restore testado.

- [ ] **ARQ-02 — Migrar arquivos para object storage privado e durável** (P0, G)
  - Usar storage S3-compatible privado, criptografado, com versionamento/lifecycle e URLs temporárias.
  - Separar anexos sensíveis de assets públicos; migrar fotos/anexos existentes com checksum.
  - Aceite: deploy/reinício não perde arquivo; URL expirada falha; banco e objeto permanecem consistentes.

- [ ] **ARQ-03 — Corrigir pipeline de deploy e separar ambientes** (P0, G)
  - Remover `migrate:fresh --seed` do deploy de produção; migrations devem ser incrementais, revisadas e compatíveis com rollback/roll-forward.
  - Criar desenvolvimento, staging e produção com segredos, bancos e buckets separados.
  - Pipeline mínimo: instalar com lockfile, lint/format, testes, build, análise de dependências, migration check, deploy e smoke test.
  - Aceite: deploy sem alteração destrutiva de dados; seed fictício só roda por comando explícito fora de produção.

- [ ] **ARQ-04 — Tornar a aplicação stateless e preparar workers** (P0, G)
  - Sessões/cache compartilhados quando houver múltiplas instâncias; Redis gerenciado é opção, não requisito sem medição.
  - Processar PDFs pesados, exports, miniaturas, antimalware e notificações em filas com retries, idempotência e dead-letter/failed jobs.
  - Aceite: duas ou mais instâncias atendem o mesmo usuário; falha de job pode ser reprocessada sem duplicação.

- [ ] **ARQ-05 — Organizar o backend por domínios** (P2, G)
  - Módulos sugeridos: Acolhimento, Cadastro, Saúde, Educação, Atendimentos, Agenda, Documentos, Identidade/Acesso e Auditoria.
  - Extrair validações para Form Requests, autorização para Policies e regras transacionais para Actions/Services; controllers ficam finos.
  - Usar eventos de domínio/outbox somente onde houver integração ou processamento assíncrono real.
  - Aceite: módulos têm contratos claros e testes; regras não dependem de componentes de interface.

- [ ] **ARQ-06 — Fortalecer o modelo relacional e a concorrência** (P0, G)
  - Criar constraints de domínio, unicidade e integridade; timestamps com fuso consistente (armazenar UTC, apresentar `America/Sao_Paulo`).
  - Adicionar índices compostos guiados por filtros: unidade, situação, período, área, acolhido e setor.
  - Adotar transações e optimistic locking/versionamento para impedir sobrescrita simultânea.
  - Preferir IDs não enumeráveis em links externos/assinados quando necessário, sem tratar UUID como autorização.
  - Aceite: testes de concorrência, integridade e plano de queries críticas aprovados.

- [ ] **ARQ-07 — Definir SLOs e testar capacidade** (P2, M)
  - Levantar usuários simultâneos, acolhidos/unidades, eventos por ano, tamanho/volume de anexos e crescimento esperado.
  - Definir SLOs de disponibilidade/latência, RPO, RTO e orçamento de custo.
  - Testar login, busca, lista, agenda por intervalo, gravação, PDF e relatório anual com massa representativa.
  - Aceite: relatório de carga registra limites, gargalos, capacidade e próximo gatilho de escala.

- [ ] **BE-01 — Implementar paginação, filtros e busca escaláveis** (P2, G)
  - Agenda deve consultar somente o intervalo visível; seletores de acolhidos precisam busca remota/paginada.
  - Criar busca PostgreSQL normalizada para acentos e índices apropriados; não retornar campos sensíveis desnecessários.
  - Evitar N+1 e limitar payloads Inertia.
  - Aceite: queries críticas atendem SLO com massa de carga e plano de execução registrado.

- [ ] **BE-02 — Padronizar erros, idempotência e APIs internas** (P2, M)
  - Respostas de validação consistentes; correlation ID; operações longas ou repetíveis com chave de idempotência quando necessário.
  - Versionar API somente se surgir cliente externo/mobile; não criar API paralela sem consumidor.
  - Aceite: duplo clique/retry não duplica atendimento, encaminhamento, documento ou ofício.

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
  - Aceite: runbooks e alertas testados; equipe consegue diagnosticar falha sem acessar conteúdo sensível indevido.

- [ ] **OPS-02 — Formalizar backup, continuidade e recuperação** (P0, G)
  - Backups criptografados de banco e objetos, cópia isolada, política de retenção, restauração periódica e responsáveis substitutos.
  - Criar plano de indisponibilidade com procedimento operacional temporário e reconciliação posterior.
  - Aceite: simulado restaura banco + arquivos dentro do RPO/RTO aprovado.

- [ ] **OPS-03 — Gerenciar dependências e vulnerabilidades** (P0, M)
  - Atualizações programadas de Composer/NPM, análise de dependências/SBOM, patches críticos e revisão do runtime PHP não oficial usado no deploy atual.
  - Fixar versões via lockfiles e testar atualização em staging.
  - Aceite: não há vulnerabilidade crítica conhecida sem exceção formal, prazo e mitigação.

## 8. Estratégia de testes e definição de pronto

- [ ] **QA-01 — Criar pirâmide de testes do domínio** (P0, G)
  - Unitários: transições de acolhimento, contagem de indicadores, numeração, permissões e regras de medicação.
  - Feature/integration: CRUD autorizado, isolamento por unidade/setor, uploads privados, auditoria, exports, filas e PostgreSQL real.
  - E2E: ingresso → PIA → Saúde/Educação → agenda → ofício → relatório anual.
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

`DEC-01` a `DEC-06`, `LGPD-01/02/03`, `SEG-01`, congelar uso de dados reais e retirar qualquer dado real já inserido em ambiente de demonstração seguindo procedimento aprovado.

### Marco 1 — Base segura de produção

`ARQ-01/02/03`, `SEG-02/03/04/05/06/07`, `OPS-01/02/03`, `DOC-05/06`, primeira parte de `QA-01`.

### Marco 2 — Núcleo operacional pedido pelos usuários

`ACO-01/02/03`, `CAD-01/02/03`, `AGD-01`, `DOC-01/02/03/04`.

### Marco 3 — Saúde, Educação e dados contabilizáveis

`ATE-01/02`, `SAU-01` a `SAU-05`, `EDU-01/02/03`, permissões granulares e auditoria correspondentes.

### Marco 4 — Informativos, qualidade e escala

`BI-01/02/03`, `ARQ-04/05/06/07`, `BE-01/02`, `FE-01/02`, conclusão de `QA-01/02`.

## 10. Matriz de rastreabilidade dos feedbacks

| Feedback do usuário | Tarefas que atendem |
|---|---|
| Foto do acolhido no PIA | `DOC-01`, `CAD-03`, `ARQ-02` |
| Agenda pessoal ou compartilhada | `DEC-02`, `AGD-01`, `AGD-02` |
| Abas específicas de Saúde e Educação | `SAU-01` a `SAU-05`, `EDU-01` a `EDU-03` |
| Nova criança com todos os dados da ficha de ingresso | `DEC-06`, `CAD-01`, `CAD-02`, `ACO-01` |
| Informações escolares no ingresso | `CAD-01`, `EDU-01` |
| Quem/qual órgão conduziu ao acolhimento | `CAD-01`, `ACO-01` |
| Filtros Acolhidos, Desacolhidos, Evadidos e Internados | `DEC-01`, `ACO-01`, `ACO-02`, `ACO-03` |
| Levantamento anual de atendimentos e encaminhamentos | `DEC-03`, `ATE-01`, `ATE-02`, `BI-01`, `BI-02`, `BI-03` |
| Números por Saúde, Escola e Reaproximação Familiar | `DEC-03`, `ATE-01`, `BI-01` |
| Campo opcional “para quem estamos oficiando” | `DOC-02`, `DOC-04` |
| Selecionar dupla, uma pessoa ou até quatro assinaturas | `DEC-04`, `DOC-03`, `DOC-04` |
| Atendimento em UBS/UPA/Hospital/CAPS, motivo e acompanhante | `SAU-01` |
| Encaminhamentos, exames e realização do exame | `SAU-03`, `ATE-02` |
| Medicações de uso contínuo e atualização após reavaliação | `SAU-02` |
| Medicação de tratamento único | `SAU-04` |
| Anticoncepcionais | `SAU-05` |
| Idas, reuniões, ligações e outras situações escolares | `EDU-02` |

## 11. Referências normativas oficiais

- [Lei Geral de Proteção de Dados Pessoais — Lei nº 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709compilado.htm), em especial princípios, direitos, art. 14 (crianças e adolescentes) e arts. 46–49 (segurança e sigilo).
- [Enunciado da ANPD sobre tratamento de dados de crianças e adolescentes](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-divulga-enunciado-sobre-o-tratamento-de-dados-pessoais-de-criancas-e-adolescentes): qualquer hipótese legal aplicável deve preservar o melhor interesse.
- [Guia de Segurança da Informação da ANPD](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-publica-guia-de-seguranca-para-agentes-de-tratamento-de-pequeno-porte): medidas administrativas, controle de acesso, proteção dos dados, vulnerabilidades, comunicações e nuvem.
- [Regulamento de Comunicação de Incidente de Segurança — Resolução CD/ANPD nº 15/2024](https://www.gov.br/anpd/pt-br/assuntos/noticias/anpd-aprova-o-regulamento-de-comunicacao-de-incidente-de-seguranca) e [canal oficial de comunicação](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca-cis).

## 12. Fora de escopo até decisão explícita

- Prontuário médico completo, prescrição clínica, cálculo de dose ou recomendação automatizada.
- Assinatura digital com validade jurídica, até concluir `DEC-04`.
- Aplicativo mobile nativo; a prioridade é web responsiva/PWA somente se houver benefício comprovado.
- Microserviços, data lake ou analytics complexo antes de medir volume e concluir `ARQ-07`.
- Integração automática com Judiciário, Saúde ou Educação sem base legal, contrato, segurança e especificação oficiais.
