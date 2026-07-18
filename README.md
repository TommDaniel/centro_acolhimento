# Centro de Acolhimento — POC

POC navegável de um sistema para instituição de acolhimento de crianças e
adolescentes: cadastro de menores, emissão de documentos (PIA, visitas
técnicas, reports, termo de pertences), equipe organizada por setores e busca
rápida ("o processo X é de que criança?").

> **Aviso:** esta POC existe apenas para demonstrar a experiência de uso.
> Ela **não possui segurança de produção** e deve ser utilizada **somente com
> informações fictícias**. Todos os dados do seed são inventados (nomes marcados
> como "Fictício").

## Stack

- **Backend**: Laravel 13 (PHP 8.3, em Docker) + SQLite (`database/database.sqlite`)
- **Frontend**: SPA com Inertia.js v2 + React 18 + MUI v9 + Tailwind v3 +
  Framer Motion (mobile-first, menu lateral drawer, animações de transição)
- **PDFs**: barryvdh/laravel-dompdf (templates Blade em `resources/views/pdf`)
- Ziggy para rotas nomeadas no React

## Como rodar

Pré-requisitos: Docker + Docker Compose; Node 20+ apenas para rebuild do front.

```bash
docker compose up -d app                                     # sobe em http://localhost:8000
docker compose run --rm app php artisan migrate:fresh --seed # banco + dados fictícios
docker compose run --rm app php artisan storage:link         # link p/ fotos e anexos
```

Acesse <http://localhost:8000> e entre com um dos usuários fictícios
(senha de todos: `password`):

| E-mail | Papel | Setor |
|---|---|---|
| `admin@poc.local` | admin | Coordenação |
| `bruno@poc.local` | servidor | Serviço Social |
| `carla@poc.local` | servidor | Psicologia |
| `diego@poc.local` | servidor | Pedagogia |

### Desenvolvimento do front

Após alterar arquivos em `resources/js`, rode `npm run build` para regerar os
assets, ou suba o Vite em modo dev com hot reload:

```bash
docker compose --profile dev up vite   # ou: npm run dev (local)
```

## Navegação (o que está onde)

- **Menu lateral**: Painel · Agenda · Crianças · (Documentos) PIA, Visitas técnicas,
  Ocorrências, Pertences · (Organização) Setores, Equipe.
- **Header**: logo + nome, busca global (nome, processo, RG, CPF, pais) e o
  nome do usuário (abre menu com perfil e sair).
- **Fora do menu, de propósito**: busca (header), perfil/sair (menu do
  usuário), criação de registros (contextual — botões "Novo" nas listas e na
  ficha da criança), ações de PDF/exclusão (tela de visualização).

## Regras de acesso

- **admin**: pode preencher, editar e excluir tudo; gerencia setores e equipe.
- **servidor**: vê todos os setores e documentos, mas só **altera** (edita/exclui)
  documentos do próprio setor; não gerencia setores nem equipe.
- Remover cadastro de criança: só admin. Anexos: só admin ou quem enviou.

## Funcionalidades

- **Agenda**: calendário mensal/semanal/lista (FullCalendar) com compromissos
  por tipo (visita, audiência, atendimento, tarefa, outro) e cores próprias,
  vínculo opcional com a criança, "dia inteiro", marcar como concluído e painel
  de próximos compromissos (também no Painel). Clique num dia para criar.
- **Crianças/adolescentes**: ficha completa com foto, identificação (RG, CPF,
  RN, SUS, NIS, título de eleitor...), **familiares** (genitores, responsáveis
  e demais membros — alimentam automaticamente a filiação e a composição
  familiar do PIA) e anexos.
- **PIA**: identificação, filiação e composição familiar montadas sozinhas a
  partir do cadastro; "dados do acolhimento" pré-preenchidos ao selecionar a
  criança; campos estruturados (encaminhado por, acolhimento anterior com
  detalhes condicionais); demais seções em texto livre.
- **Relatórios de ocorrência** (antigo "report"): introdução ao ocorrido,
  desenvolvimento e considerações/solicitações ao Juízo.
- **Visitas técnicas**: data/hora, tipo, visitante, relato e encaminhamentos.
- **Pertences pessoais**: lista de itens com assinatura do menor na entrega e
  na devolução; observação para itens não devolvidos (ex.: perda).
- **PDF**: todo documento tem botão "PDF". **Campos vazios não aparecem** no
  documento. Rodapé sempre com autor, data e hora (America/Sao_Paulo, formato
  brasileiro).
- **Setores e equipe**: usuários por setor; página do setor com membros e
  documentos por subtópico. Não há cadastro público.

## Banco de dados

`setores` 1—N `users` · `criancas` 1—N `pias` / `visitas_tecnicas` / `reports`
/ `pertences` / `crianca_documentos`. Todo registro guarda `created_by`
(quem fez) e `created_at` (quando). Documentos herdam o `setor_id` de quem
registrou. Campos opcionais ficam `NULL` e são omitidos na renderização.

## Resetar a demonstração

```bash
docker compose run --rm app php artisan migrate:fresh --seed
```
