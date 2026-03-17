# Diretrizes Oficiais do Projeto PachyBase

## 1. Propósito deste documento

Este documento define as regras técnicas e arquiteturais que devem orientar a evolução do **PachyBase**.

Ele existe para garantir que qualquer desenvolvimento realizado por humanos ou por agentes de IA preserve:

* a coerência arquitetural do projeto;
* a previsibilidade do comportamento da API;
* a legibilidade do código;
* a estabilidade do núcleo;
* a capacidade de evolução futura sem retrabalho estrutural.

Estas diretrizes não descrevem apenas o que o projeto é.
Elas definem **como o PachyBase deve ser desenvolvido**.

---

## 2. Definição do projeto

O **PachyBase** é uma plataforma backend **open-source**, **self-hosted**, construída em **PHP**, com foco em:

* arquitetura **API-first**;
* respostas JSON **previsíveis e padronizadas**;
* execução preferencial via **Docker**;
* suporte a múltiplos bancos de dados;
* geração e organização de backend com boa compatibilidade com ambientes assistidos por IA. 

O projeto existe para evitar que tarefas comuns de backend sejam reconstruídas do zero em cada novo sistema, como:

* CRUD;
* autenticação;
* conexão com banco;
* paginação;
* filtros;
* validação;
* documentação da API. 

---

## 3. Princípios inegociáveis

## 3.1 API-first

Toda decisão arquitetural deve partir da premissa de que o PachyBase é uma plataforma orientada a API.

O sistema não deve assumir um frontend específico como centro da arquitetura.
Ele deve servir de base para:

* aplicações web;
* mobile;
* ferramentas internas;
* microserviços;
* interfaces geradas ou assistidas por IA. 

### Regra obrigatória

Novas features devem ser pensadas primeiro como capacidade de backend e contrato HTTP, não como adaptação para interface.

---

## 3.2 Contrato JSON previsível

Toda resposta da API deve seguir obrigatoriamente a estrutura padrão abaixo:

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "error": null
}
```

Esse contrato é obrigatório em todos os endpoints, inclusive em casos de erro. 

### Regras obrigatórias

* `success` deve sempre existir.
* `data` deve sempre existir.
* `meta` deve sempre existir.
* `error` deve sempre existir.
* endpoints não podem retornar HTML ou texto puro como resposta final da API.
* erros não podem quebrar o contrato externo da resposta.

### Regra crítica

Nenhuma nova feature pode introduzir um formato alternativo de resposta.

---

## 3.3 Simplicidade estrutural

O núcleo do PachyBase deve permanecer leve.

O projeto não deve evoluir para um framework pesado, genérico ou inchado. A visão de longo prazo admite expansão, mas o core deve continuar simples, previsível e extensível. 

### Regra obrigatória

Toda nova abstração precisa se justificar por reutilização real, clareza ou estabilidade.
Abstração sem necessidade é complexidade disfarçada de organização.

---

## 3.4 Self-hosted por padrão

O PachyBase deve continuar sendo uma base backend que o usuário controla integralmente.

### Regra obrigatória

O projeto não pode depender estruturalmente de serviços cloud proprietários para funcionar em seu fluxo principal.

Integrações externas podem existir, mas sempre como opcionais.

---

## 3.5 AI-friendly por design

O PachyBase deve continuar compatível com ambientes de desenvolvimento assistidos por IA, com exposição de contexto útil para automação, leitura de schema e exploração da API. O contexto do projeto prevê endpoints legíveis por máquina, como `/ai/schema` e `/openapi.json`, justamente para facilitar geração de UI, SDKs e integrações automáticas. 

### Regra obrigatória

Novas capacidades do sistema devem considerar, quando fizer sentido:

* nomes claros;
* contratos previsíveis;
* metadados legíveis;
* estrutura fácil de inferir por máquina.

---

# 4. Escopo arquitetural

## 4.1 O que o PachyBase é

O PachyBase é:

* uma base backend;
* uma fundação para APIs REST;
* uma estrutura backend reutilizável;
* uma plataforma orientada a automação de tarefas repetitivas;
* uma base técnica para projetos self-hosted.

## 4.2 O que o PachyBase não é

O PachyBase não deve ser tratado como:

* construtor visual low-code;
* CMS completo;
* plataforma cloud-only;
* framework enterprise pesado. 

### Regra obrigatória

Não adicionar funcionalidades que mudem o posicionamento do projeto sem decisão arquitetural explícita.

---

# 5. Organização do projeto

A visão do projeto admite uma arquitetura modular com áreas como `core`, `api`, `database`, `auth`, `modules`, `services`, `utils`, `config`, `docker` e `docs`. 

Mas a base atual do repositório está mais enxuta. Portanto, a IA deve respeitar o que já existe hoje e expandir de forma incremental, sem forçar uma reorganização ampla desnecessária.

## 5.1 Estrutura atual a preservar como base

A IA deve considerar como núcleo vigente algo nesta linha:

* `core/`
* `core/Controllers/`
* `core/Http/`
* `core/Database/`
* `public/`
* `scripts/`
* `docker/`
* `tests/`
* `docs-site/`

## 5.2 Regra crítica de evolução estrutural

A IA **não deve reorganizar o projeto inteiro automaticamente** para aproximá-lo de uma visão futura idealizada.

### É proibido

* mover arquivos só por preferência pessoal;
* criar camadas vazias sem uso real;
* renomear namespaces em massa sem necessidade;
* refatorar estrutura ampla fora do escopo pedido.

### É permitido

* adicionar novos diretórios quando a feature exigir;
* criar novas camadas de forma incremental;
* preparar extensões futuras sem quebrar a base atual.

---

# 6. Padrões de código

## 6.1 Versão e estilo

O projeto deve usar:

* **PHP 8+**
* **PSR-1**
* **PSR-4**
* **PSR-12**

## 6.2 Regras obrigatórias de implementação

* usar `declare(strict_types=1);` quando aplicável ao padrão já adotado no arquivo;
* utilizar tipagem explícita sempre que possível;
* usar namespaces coerentes com PSR-4;
* manter métodos curtos e coesos;
* evitar duplicação de lógica;
* evitar classes com múltiplas responsabilidades;
* preferir composição a acoplamento implícito.

## 6.3 Regras de legibilidade

* nomes devem ser claros e completos;
* evitar abreviações confusas;
* comentários devem explicar intenção, não repetir código;
* código deve ser fácil de entender sem depender de contexto oculto.

---

# 7. Regras de atuação para IA

Esta seção é a mais importante para uso por agentes de IA.

## 7.1 Regra de escopo mínimo

A IA deve alterar **somente o que for diretamente necessário** para atender à solicitação.

### É proibido

* refatorar partes não relacionadas;
* ajustar estilo em arquivos fora do escopo;
* “aproveitar” a tarefa para reorganizar outras áreas;
* mudar comportamento não solicitado;
* alterar nomes públicos sem necessidade.

### Exemplo correto

Se a tarefa é ajustar o roteador, a IA deve atuar no roteador e no que for estritamente dependente dele.

### Exemplo incorreto

Alterar controllers, config, docker e testes sem que a tarefa exija isso.

---

## 7.2 Regra de compatibilidade

A IA deve preservar compatibilidade com o comportamento existente, salvo quando a mudança pedida exigir ruptura explícita.

### Regra obrigatória

Antes de modificar contrato público, a IA deve assumir que esse contrato é sensível.

Contratos públicos incluem:

* formato JSON da API;
* nomes de chaves;
* rotas expostas;
* nomes de variáveis de ambiente;
* estrutura básica de bootstrap.

---

## 7.3 Regra de coerência com o projeto real

A IA não deve implementar como se o PachyBase já tivesse todos os módulos da visão futura.

### Exemplo

Se a visão fala em autenticação JWT nativa, isso não significa que a IA deve assumir a existência atual de todo o módulo `auth`.

Ela deve:

* trabalhar com o que existe hoje;
* adicionar somente o que a tarefa exigir;
* preparar o terreno sem inventar dependências invisíveis.

---

## 7.4 Regra de honestidade estrutural

A IA não deve inventar componentes como se já fizessem parte da base atual.

### É proibido

* citar classes inexistentes como se fossem reais;
* criar integrações fictícias;
* assumir migrations, ORM, queue ou ACL se isso não existir ainda;
* gerar documentação enganosa sobre recursos não implementados.

---

# 8. Camadas e responsabilidades

## 8.1 `public/`

Responsável pelo ponto de entrada HTTP da aplicação.

### Deve conter

* bootstrap mínimo;
* carregamento do autoload;
* inicialização de configuração;
* registro do tratador de erros;
* captura da requisição;
* despacho de rotas.

### Não deve conter

* regra de negócio;
* SQL;
* validação de domínio;
* lógica espalhada.

---

## 8.2 `core/Http/`

Responsável pela infraestrutura HTTP.

Inclui responsabilidades como:

* `Request`
* `Router`
* `Route`
* `ApiResponse`
* `ErrorHandler`

### Deve conter

* abstrações de transporte;
* roteamento;
* pipeline de middleware;
* normalização de entrada e saída HTTP.

### Não deve conter

* regra de negócio de domínio;
* lógica específica de entidade.

---

## 8.3 `core/Controllers/`

Responsável por orquestrar a requisição e delegar o trabalho necessário.

### Controller deve

* receber a requisição;
* chamar serviço, adapter ou camada adequada;
* devolver resposta padronizada.

### Controller não deve

* concentrar regra de negócio complexa;
* conter SQL;
* fazer parsing excessivo;
* duplicar validação e transformação já resolvidas em outra camada.

---

## 8.4 `core/Database/`

Responsável pela infraestrutura de acesso ao banco.

### Deve conter

* conexão;
* futuras abstrações de adapter;
* comportamento técnico de persistência.

### Não deve conter

* resposta HTTP;
* lógica de controller;
* comportamento de apresentação.

---

## 8.5 `scripts/`

Responsável por automações de setup e suporte ao desenvolvimento.

### Regra obrigatória

Scripts devem ser idempotentes sempre que possível, claros nas mensagens e seguros em relação a erro de configuração.

---

## 8.6 `tests/`

Responsável pela validação automatizada do comportamento.

### Regra obrigatória

Toda alteração relevante no core deve considerar impacto nos testes.

---

# 9. Contrato da API

## 9.1 Formato obrigatório

Toda resposta da API deve manter esta estrutura:

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "error": null
}
```

## 9.2 Respostas de sucesso

* `success = true`
* `error = null`
* `data` contém o payload principal
* `meta` contém contexto adicional

## 9.3 Respostas de erro

* `success = false`
* `data = null`
* `error` deve conter informações estruturadas
* `meta` continua obrigatório

## 9.4 Metadados recomendados

Sempre que aplicável, `meta` deve incluir:

* `request_id`
* `timestamp`
* `method`
* `path`
* `pagination`, quando houver paginação
* `contract_version`, quando o core já estiver tratando isso

## 9.5 Paginação

Listagens paginadas devem manter seus dados em `data` e o bloco de paginação dentro de `meta.pagination`.

---

# 10. Tratamento de erros

O tratamento de erro deve ser centralizado.

## Regras obrigatórias

* exceções não devem vazar como HTML;
* mensagens técnicas sensíveis não devem aparecer em produção;
* erros devem ser transformados em resposta JSON padronizada;
* códigos HTTP devem ser coerentes com o tipo de falha;
* validação, autenticação, autorização, recurso ausente e erro interno devem ser distinguíveis.

## Regra crítica

A IA não deve criar atalhos de erro fora do sistema central só por conveniência local.

---

# 11. Banco de dados

A visão do projeto prevê suporte a **MySQL** e **PostgreSQL**, com escolha do driver durante a instalação. 

## Regras obrigatórias

* novas implementações devem respeitar a seleção por driver;
* não acoplar features a um banco específico sem necessidade;
* diferenças entre engines devem ser isoladas na camada apropriada;
* configurações de conexão devem vir do ambiente.

## É proibido

* espalhar detalhes de driver pelo código de aplicação;
* assumir SQL incompatível com todos os drivers sem tratar isso;
* codificar credenciais no fonte.

---

# 12. Docker e setup

A visão do projeto define Docker como forma principal de execução. 

## Regras obrigatórias

* novas features não devem dificultar o setup Docker existente;
* configuração por ambiente deve continuar clara;
* scripts de instalação devem continuar simples e previsíveis;
* a experiência local deve ser tratada como parte do produto.

## É proibido

* criar dependência manual complexa para rodar localmente;
* exigir ajustes obscuros de ambiente sem documentação.

---

# 13. Autenticação e segurança

A visão do projeto admite autenticação com JWT, tokens de API e, opcionalmente, provedores OAuth. 

Mesmo antes da implementação completa, estas regras já devem orientar a IA:

## Regras obrigatórias

* autenticação deve ser simples por padrão;
* mecanismos devem ser configuráveis;
* dados sensíveis nunca devem ser retornados em resposta pública;
* erros de autenticação devem seguir o contrato JSON;
* validação de entrada deve acontecer antes de persistência ou operação sensível.

## É proibido

* retornar senha, hash, segredo ou token bruto em payload indevido;
* vazar stack trace em produção;
* confiar em input sem validação mínima.

---

# 14. Geração automática e recursos futuros

A visão do PachyBase inclui geração automática de CRUD, schema legível por máquina, OpenAPI, autenticação e expansão modular. 

## Regra obrigatória

Esses recursos devem ser construídos como evolução do núcleo, não como camadas paralelas desconectadas.

### Toda nova automação deve:

* respeitar o contrato JSON;
* seguir a organização arquitetural do projeto;
* preservar legibilidade do código gerado;
* permitir customização posterior.

### Código gerado não deve:

* ser opaco;
* depender de convenções mágicas demais;
* quebrar facilmente quando o projeto crescer.

---

# 15. Testes

## Regras obrigatórias

* alterações no core devem ser acompanhadas de testes quando houver impacto comportamental;
* testes devem cobrir comportamento, não apenas implementação;
* correções de bug relevantes devem incluir prevenção de regressão;
* testes não devem depender de estado imprevisível.

## É proibido

* corrigir bug sem proteger o caso em teste quando isso for aplicável;
* alterar teste apenas para “fazer passar” sem corrigir a causa.

---

# 16. Documentação

A documentação faz parte do projeto, não é material secundário.

## Regras obrigatórias

* toda feature pública relevante deve ter documentação correspondente;
* variáveis de ambiente novas devem ser documentadas;
* mudanças em setup precisam refletir na documentação;
* exemplos devem seguir o contrato real da API.

## Regra crítica

A IA não deve documentar como concluído algo que ainda não foi implementado.

---

# 17. Convenções de nomenclatura

## Classes

Usar nomes claros, em singular quando representar conceito unitário.

Exemplos adequados:

* `Request`
* `Router`
* `ApiResponse`
* `Connection`
* `SystemController`

## Métodos

Devem expressar ação ou intenção real.

Exemplos:

* `load`
* `get`
* `dispatch`
* `capture`
* `success`
* `error`

## Variáveis

Devem ser claras e contextualizadas.

Evitar:

* `$obj`
* `$data2`
* `$temp`
* `$resp`

Preferir:

* `$request`
* `$statusCode`
* `$databaseDriver`
* `$paginationMeta`

---

# 18. Proibições explícitas para agentes de IA

A IA que atuar no PachyBase **não deve**:

* reescrever grandes partes do projeto sem pedido;
* alterar arquitetura por gosto pessoal;
* introduzir dependências pesadas sem justificativa;
* quebrar o contrato JSON;
* misturar regra de negócio com infraestrutura HTTP;
* inventar módulos inexistentes como se fossem oficiais;
* duplicar lógica em várias camadas;
* vazar informação sensível;
* trocar nomes públicos sem necessidade;
* modificar arquivos não relacionados só por padronização estética.

---

# 19. Regra de decisão arquitetural

Quando houver dúvida entre uma solução “rápida” e uma solução “coerente”, a IA deve preferir a solução coerente **desde que ela não amplie desnecessariamente o escopo da tarefa**.

Em termos diretos:

* não usar hack só para passar;
* não superengenheirar;
* não remendar o core com improviso;
* não transformar tarefa pequena em refatoração gigante.

---

# 20. Regra final para qualquer IA que trabalhe no PachyBase

Toda implementação deve responder corretamente a estas perguntas antes de ser considerada adequada:

1. Isso respeita a arquitetura API-first?
2. Isso preserva o contrato JSON padrão?
3. Isso altera apenas o necessário?
4. Isso mantém o núcleo simples?
5. Isso evita acoplamento desnecessário?
6. Isso continua compatível com Docker e configuração por ambiente?
7. Isso está alinhado ao projeto real, e não a uma versão imaginária dele?
8. Isso deixa o PachyBase mais previsível, e não mais confuso?

Se a resposta for “não” para qualquer item essencial, a implementação deve ser revista.

---

# Resumo executivo para IA

O PachyBase deve ser desenvolvido como uma base backend:

* **API-first**
* **self-hosted**
* **modular**
* **simples**
* **previsível**
* **AI-friendly**
* **orientada a Docker**
* **com contrato JSON rígido**
* **sem refatorações fora de escopo**
* **sem invenções estruturais**
* **sem quebras desnecessárias de compatibilidade**