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
