# PostgreSQL local e testes — ARQ-01A

Status: fundação local/CI concluída em 31/08/2026. A infraestrutura produtiva
continua pendente nos itens indicados abaixo.

Este guia cobre somente a fundação local/CI. A implantação futura na Contabo,
TLS, roles mínimas separadas, backups, PITR, restore e RPO/RTO continuam em
`ARQ-01` e `ARQ-07`. Nenhum procedimento desta página autoriza dados reais.

## Iniciar e parar

As credenciais versionadas são deliberadamente locais e não produtivas. O
serviço PostgreSQL não publica porta para o host e usa o volume nomeado
`centro_acolhimento_postgres_data`.

```bash
docker compose up -d postgres app
docker compose ps postgres
docker compose stop app postgres
```

`docker compose stop` preserva o volume. Não remova o volume para corrigir
falhas ou atualizar a aplicação; isso descartaria o banco local.

## Migrações e dados sintéticos

Aplicar migrations preservando os dados existentes:

```bash
docker compose run --rm app php artisan migrate
```

Inserir o seed demonstrativo, que contém somente pessoas marcadas como
fictícias:

```bash
docker compose run --rm app php artisan db:seed
```

Reset destrutivo é permitido apenas na base local nomeada e nos bancos
efêmeros de teste. Antes de usar o comando abaixo, confirme `APP_ENV=local` e
`DB_DATABASE=centro_acolhimento`; ele nunca deve ser usado em staging ou
produção:

```bash
docker compose run --rm -e APP_ENV=local -e DB_DATABASE=centro_acolhimento app php artisan migrate:fresh --seed
```

O SQLite da POC não é importado nem apagado. Ele permanece como exceção
temporária e explícita apenas no runtime `VERCEL` da demonstração sintética;
fora desse runtime não existe fallback para SQLite.

## Testes

O init local cria `centro_acolhimento_test`, reservado às suítes automatizadas.
O `phpunit.xml` força esse nome para impedir que um teste limpe o banco de
desenvolvimento. Feature tests e E2E exigem PostgreSQL 17; Unit tests não
dependem de banco.

Antes de qualquer `RefreshDatabase`, o bootstrap dos testes valida duas vezes
o ambiente: primeiro as variáveis do processo e, depois, a configuração efetiva
do Laravel. A execução falha sem consultar o banco se `APP_ENV`, driver, host,
nome da base ou `DB_URL` não corresponderem ao ambiente de teste. Um cache de
configuração com valor diferente também é rejeitado; não use `config:cache` nas
suítes locais ou na CI.

```bash
docker compose run --rm app php artisan test --testsuite=Unit
docker compose run --rm app php artisan test --testsuite=Feature
docker compose --profile e2e up --build --force-recreate --abort-on-container-exit --exit-code-from playwright-e2e playwright-e2e
```

O perfil `e2e` inicia uma aplicação isolada, executa `migrate:fresh --seed`
por meio do comando guardado `e2e:reset-database`, somente em
`centro_acolhimento_test`, prepara os assets e roda `npm run test:e2e` na
imagem oficial Playwright 1.62.1. `--force-recreate` garante um reset novo em
cada execução, inclusive depois de uma falha anterior. O banco
`centro_acolhimento` não é resetado por esse fluxo e a porta do PostgreSQL
continua sem publicação. O servidor de teste usa workers concorrentes com
`--no-reload`, e a suíte sincroniza as gravações pela resposta HTTP e pela
navegação Inertia resultante, sem depender de pausas arbitrárias ou retries.

O E2E mantém a sessão em arquivos somente como exceção local, mas usa o cache
`database` do PostgreSQL para o bloqueio atômico e compartilhado entre os quatro
workers. O bloqueio é global: toda requisição stateful da mesma sessão adquire o
lock antes de ler e salvar a sessão. Isso impede que uma resposta lenta, como um
retrato privado, sobrescreva um handle de busca criado em paralelo. Usuários e
sessões diferentes continuam executando concorrentemente.

`SESSION_BLOCK_WAIT_SECONDS=10` limita a espera; esgotá-la responde `503` com
`no-store`, sem carregar ou salvar a sessão. O lease padrão é de 300 segundos.
No E2E, esse valor mantém margem sobre o limite de 120 segundos da suíte. Em
staging e produção, o timeout efetivo do servidor web/PHP deve ser configurado
em no máximo 240 segundos e permanecer menor que
`SESSION_BLOCK_LOCK_SECONDS`; o servidor PHP local tem
`max_execution_time=0` e não serve como referência operacional. Se o timeout
web aumentar, o lease deve ser aumentado antes do deploy.

O store de lock precisa ser compartilhado e atômico. `file` e `array` não são
válidos em implantação com múltiplos processos ou réplicas. O PostgreSQL
`database` atende ao protótipo; a implantação futura pode usar Redis definindo
`SESSION_BLOCK_STORE=redis`, desde que todos os workers usem a mesma instância e
a indisponibilidade do store continue falhando de modo fechado.

Atrito local não bloqueante: como os containers usam identidades distintas,
artefatos de `public/build` criados pelo Playwright podem não ser substituíveis
pelo container Node comum em alguns hosts. O build validado deve usar o perfil
E2E documentado, que mantém a mesma identidade responsável pelos artefatos;
isso não afeta dados, banco ou o resultado dos gates.

## Horários da Agenda

Campos `datetime-local` da Agenda representam o relógio civil da unidade em
`America/Sao_Paulo`. O backend faz essa interpretação e persiste o instante em
UTC; as consultas calculam o início do dia em São Paulo e convertem a fronteira
para UTC antes de consultar o PostgreSQL. Compromissos de dia inteiro continuam
como uma data civil e não mudam de dia ao serem criados, editados ou exibidos.
Os testes Feature e E2E cobrem criação, edição, leitura e dia inteiro.

## Episódios e movimentações — PROT-01B1

O cadastro da pessoa permanece separado do episódio de acolhimento. Um ingresso
explícito cria o episódio e a primeira movimentação `ingresso`; evasão,
retorno, internação e desacolhimento sempre inserem novas movimentações. Evasão
e internação não encerram o episódio. Somente a movimentação de
`desacolhimento` preenche, na mesma transação, a projeção técnica monotônica de
encerramento do episódio.

O PostgreSQL protege no máximo um episódio aberto por pessoa/unidade com índice
único parcial. Movimentações não aceitam `UPDATE`, `DELETE` ou `TRUNCATE`, e os
campos factuais do episódio não podem ser reescritos; o único `UPDATE` permitido
é o fechamento uma vez, ligado à movimentação canônica correspondente. A
aplicação também bloqueia a pessoa/episódio sob transação, valida a máquina de
estados e rejeita uma chave idempotente repetida com conteúdo diferente.
Origem e órgão condutor usam códigos v0 separados, com complemento obrigatório
para `outro`; a pessoa condutora é outro campo curto. Organização, unidade,
autoria, situação resultante e horário de registro são derivados no servidor.
Datas e horas digitadas representam `America/Sao_Paulo` e são persistidas em
UTC.

As colunas antigas `criancas.data_acolhimento`,
`criancas.motivo_acolhimento` e `criancas.status` são preservadas sem backfill e
não aceitam novas mutações pelo formulário de cadastro. Quando ainda não existe
episódio, a ficha distingue `Ingresso ainda não registrado` de `Dados anteriores
a conferir`; uma data antiga continua sendo somente data civil, sem horário ou
saída inferidos. A reconciliação desses registros exige corte posterior e não
deve criar evasão, internação, retorno ou desacolhimento presumido.

O bloco `Dados anteriores a conferir` permanece visível mesmo depois de um
ingresso confirmado: ele é histórico separado e nunca vira a situação atual.
Listagens, Dashboard, Agenda e seletores de documentos usam a projeção dos
episódios; episódio aberto pode estar `na_unidade`, `evadido` ou `internado`, o
último episódio encerrado fica `desacolhido`, e pessoa sem episódio fica sem
ingresso ou com legado explicitamente pendente. A busca iniciada na listagem
envia o termo por `POST` ao fluxo protegido, mantém apenas uma referência opaca
na URL e responde com `no-store`; o termo não é colocado na query string.

Cada episódio guarda, no ingresso, snapshots nullables e imutáveis do número do
processo, vara e comarca obtidos do cadastro pelo servidor sob o mesmo lock.
Alterações posteriores no cadastro não reescrevem nem são combinadas com esse
contexto. PIAs novos são vinculados pelo servidor somente ao episódio aberto
atual; sem episódio aberto, o vínculo permanece `NULL` com
aviso explícito. PIAs legados `NULL` não são associados por inferência, e um
vínculo existente não pode ser trocado. Tela e PDF de PIA vinculado usam a data
e os snapshots do episódio específico, preservando o documento após
reingressos. Episódios criados antes dos snapshots adicionais permanecem com
vara/comarca históricas nulas: não há backfill a partir do cadastro atual. PIAs
legados sem vínculo continuam usando o contexto não reconciliado já existente;
uma política futura de retificação/reconciliação deve ser definida antes de
alterar esses documentos.

Na criação do PIA, o formulário envia uma precondição
`expected_acolhimento_id` correspondente ao episódio exibido, inclusive `NULL`
explícito quando não havia episódio aberto. Sob lock da pessoa, o servidor
resolve novamente o episódio aberto e rejeita a criação se o estado mudou; a
usuária deve atualizar o formulário antes de reenviar. Essa precondição nunca é
usada como vínculo autoritativo e o cliente continua proibido de enviar
`acolhimento_id`. A rejeição não cria PIA nem auditoria de sucesso.

Na ficha autorizada, cada entrada da linha do tempo identifica seu episódio e
mostra motivo/fundamento, origem, órgão e pessoa condutora daquele ingresso; os
fundamentos das demais movimentações permanecem junto ao respectivo fato. Nos
formulários, ao trocar `Outro` por um código padrão, o complemento oculto é
limpo no cliente e continua proibido pela validação do servidor.

As migrations são aditivas e o rollback operacional seguro é voltar a versão
da aplicação mantendo as novas tabelas e fazer correções roll-forward. O método
`down()` existe para bancos efêmeros vazios de CI/desenvolvimento e remove
primeiro triggers/FKs; não o execute depois que houver fatos registrados, pois
isso descartaria o novo histórico. Nunca use `migrate:fresh` para esse fluxo em
staging ou produção.

Testes focados, incluindo concorrência multiprocesso no PostgreSQL 17:

```bash
docker compose run --rm -e APP_ENV=testing app php artisan test --compact tests/Feature/AcolhimentoFlowTest.php
docker compose run --rm -e APP_ENV=testing app php artisan test --compact tests/Feature/PiaAcolhimentoHistoryTest.php tests/Feature/AcolhimentoProjectionConsumersTest.php
docker compose run --rm -e APP_ENV=testing -e RUN_ACOLHIMENTO_CONCURRENCY_TEST=true app php artisan test --compact tests/Feature/AcolhimentoConcurrencyTest.php
```

Se o volume tiver sido inicializado antes da criação do banco de teste,
crie somente esse banco local explicitamente:

```bash
docker compose exec postgres createdb -U centro_local centro_acolhimento_test
```

## Diagnóstico

Verificar saúde e versão sem exibir senha:

```bash
docker compose ps postgres
docker compose exec postgres pg_isready -U centro_local -d centro_acolhimento
docker compose exec postgres psql -U centro_local -d centro_acolhimento -c "show server_version;"
docker compose run --rm app php artisan migrate:status
```

Se o banco não ficar saudável, consulte somente os logs técnicos do serviço:

```bash
docker compose logs --tail=100 postgres
```

Não cole logs, `.env`, strings de conexão ou dumps em chamados. Desenvolvimento
e CI usam exclusivamente fixtures sintéticas.

## Persistência e produção futura

Reiniciar o serviço deve manter dados no volume nomeado. A CI comprova
migrations em banco vazio, seed sintético, refresh efêmero e índices de chaves
estrangeiras. Essa evidência não substitui os controles de produção.

Antes de dados reais, a Contabo deverá usar secrets próprios, TLS, rede
privada, role de aplicação sem DDL, role controlada de migration, backup
externo criptografado e restore/PITR testado. O PostgreSQL local usa uma única
role proprietária apenas para simplificar desenvolvimento.

## Exceção temporária da demonstração Vercel

Enquanto o cutover para Contabo não for concluído, o build efêmero da Vercel
recria exclusivamente o SQLite com seed sintético e o copia para `/tmp` no
runtime. Esse fluxo não é produção real, não aceita dados reais e só é ativado
quando `VERCEL=true`. A remoção de `api/index.php`, `vercel.json`, do script de
build e do bootstrap SQLite deve ocorrer junto da desativação desse deploy no
cutover, nunca antes.
