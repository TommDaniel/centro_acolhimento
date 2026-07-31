# Instruções do repositório

Antes de atuar, leia `RTK.md`, o `AGENTS.md` mais próximo do arquivo alterado e as skills aplicáveis em `.agents/skills`. Para qualquer regra do produto, use `$centro-acolhimento-domain`.

## Produto e segurança

- Este sistema trata dados de crianças e adolescentes, inclusive saúde, documentos judiciais, fotografias e vínculos familiares. Trate todos os registros como altamente confidenciais.
- Nunca use dados reais em testes, seeds, prompts, logs, screenshots ou relatórios de CI. Use somente dados sintéticos explicitamente identificados como fictícios.
- `TODO.md` é a fonte de verdade do backlog. Toda mudança deve citar pelo menos um identificador de tarefa, uma decisão técnica ou declarar claramente que é infraestrutura de engenharia.
- Não autorize pelo frontend. A autorização deve existir no backend, por recurso e ação, e possuir teste negativo.
- Não publique fotos ou anexos em URLs permanentes. Não registre payloads, documentos, diagnósticos, CPF, CNS ou tokens em logs.
- Não execute `migrate:fresh`, seeds demonstrativos ou operações destrutivas em staging/produção.

## Fluxo obrigatório de agentes

Qualquer mudança de código de produção, banco, segurança, dependência ou CI passa, em sequência, por:

1. `implementer`: implementa o menor incremento completo, incluindo testes.
2. `senior-reviewer`: revisa o diff sem alterá-lo e decide `PASS` ou `CHANGES_REQUIRED`.
3. `implementer`: corrige todos os achados `BLOCKER` e `HIGH` e os `MEDIUM` aceitos.
4. `qa-security`: testa lógica, integração, E2E, autorização e segurança; não corrige código de produção.
5. `senior-reviewer`: revalida toda escrita feita pelo QA e qualquer correção que mude arquitetura, contrato, banco ou segurança.

O coordenador não deve delegar edições simultâneas no mesmo conjunto de arquivos. Nenhum agente aprova o próprio trabalho. Mudança somente documental pode dispensar o trio se não alterar contrato, operação, segurança ou regra de negócio.

O Playwright deve ser executado exclusivamente pela versão fixada no `package-lock.json`, via `npm run test:e2e`. Não use wrappers que executem `npx --yes --package ...` nem versões `@latest`.

## Padrões gerais

- Preserve o monólito modular Laravel + Inertia enquanto não houver evidência para outra arquitetura.
- Prefira recursos nativos da stack. Nova biblioteca exige a avaliação de dependências definida em `RTK.md`.
- Banco-alvo de produção: PostgreSQL. SQLite serve apenas para desenvolvimento/testes simples; regras dependentes do banco exigem teste em PostgreSQL.
- Migrações são aditivas e compatíveis com roll-forward. Dados históricos, documentos finalizados e movimentações não são sobrescritos ou apagados silenciosamente.
- Horários são persistidos em UTC e apresentados em `America/Sao_Paulo`.
- Interfaces essenciais precisam funcionar por teclado, em celular e desktop, e manter estados de carregamento, vazio, erro e acesso negado.
- Antes de concluir, execute os gates relevantes definidos em `RTK.md` e informe exatamente comandos, resultados e limitações.

## Bloqueio de entrega

Não considere uma tarefa pronta quando houver achado `BLOCKER`/`HIGH`, teste obrigatório falhando, ausência de teste de autorização, migração sem estratégia segura, vazamento potencial de dados ou critério de aceite não verificado.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v18
- tailwindcss (TAILWINDCSS) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
