# Regras do frontend React/Inertia

- Leia `../../AGENTS.md`, `../../RTK.md` e use `$centro-acolhimento-domain` nos fluxos assistenciais.
- O servidor é a autoridade de autorização e validação. Ocultar botão melhora UX, mas nunca é controle de acesso.
- Não salve prontuário, CPF, CNS, fotos, tokens ou documentos em `localStorage`, logs, analytics, query strings ou nomes de arquivo.
- Prefira componentes pequenos, contratos explícitos e estado local/Inertia. Introduza biblioteca de estado somente após necessidade demonstrada.
- Novos módulos críticos devem preferir TypeScript e tipos compartilháveis; não use `any` sem justificativa.
- MUI é a base de componentes complexos e Tailwind auxilia layout/utilitários. Não duplique o mesmo componente nas duas abordagens.
- Garanta labels, foco visível, ordem de teclado, erros associados ao campo, contraste, alvos de toque e semântica. Valide desktop, tablet e celular.
- Listas, buscas e calendários usam paginação/intervalo no servidor; não carregue toda a base no navegador.
- Todo fluxo possui estados de carregamento, vazio, erro, acesso negado e confirmação para ações de impacto.
- Testes E2E usam papéis/labels/test IDs estáveis; não dependem de classes visuais nem de esperas fixas.
