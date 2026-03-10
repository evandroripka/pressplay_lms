# Pressplay LMS

Plugin LMS para WordPress com foco em cursos em video, controle de acesso, integracao com WooCommerce e fluxo de matricula automatizado.

O projeto foi construido para cenarios reais de venda de cursos no mercado brasileiro, com frontend proprio, progresso por aula, certificados e estrutura extensivel para continuar evoluindo.

## Estado atual

Versao atual do plugin: `1.0.0`

O plugin ja cobre o fluxo principal de:

- cadastro de cursos, aulas e professores
- venda do curso via WooCommerce
- criacao e ativacao de matriculas
- controle de acesso a curso e aula
- progresso por aula
- certificado ao concluir o curso
- area administrativa para configuracoes e alunos

## Funcionalidades principais

### Cursos e aulas

- CPT `press_course` para cursos
- CPT `press_lesson` para aulas
- CPT `press_teacher` para professores
- relacionamento curso -> aula por `post_parent` e meta `_press_lesson_course_id`
- pagina de curso em rota propria: `/curso/{slug}`
- pagina de aula em rota propria: `/curso/{curso}/aula/{aula}`
- criacao e edicao de aulas a partir da tela do curso
- ordenacao de aulas via `menu_order`

### Frontend proprio

- templates customizados para curso e aula
- breadcrumbs e navegacao entre curso e aulas
- layout proprio com CSS dedicado
- pagina de cadastro em `/cadastro`
- pagina de meus cursos em `/meus-cursos`
- renderizacao independente do tema para o fluxo principal do LMS

### Videos, materiais e duracao

- suporte a Vimeo e YouTube por URL
- validacao de Vimeo via API quando o token esta configurado
- sync de duracao da aula via Vimeo
- recalculo automatico da duracao total do curso
- materiais por aula com anexos ou links
- icones por tipo de material

### Matriculas e acesso

- role custom `press_student`
- bloqueio do wp-admin para alunos
- controle de acesso por matricula ativa
- expiracao de matricula
- bypass para administradores
- criacao de matricula `pending` no fluxo de checkout
- ativacao automatica ao pagamento/processamento do pedido

### WooCommerce

- criacao automatica de produto ao publicar o curso com preco
- sincronizacao do produto com o curso
- checkout direcionado pelo plugin
- suporte ao fluxo de login/registro antes da matricula
- ligacao produto -> curso por meta `_press_course_id`

### Alunos, progresso e certificado

- shortcode `[press_register]` para cadastro customizado
- perfil do aluno salvo em tabela propria
- e-mail com link para definir senha
- progresso salvo por aula em tabela propria
- percentual de conclusao do curso
- certificado com placeholders personalizados
- preview e download de certificado no admin

### Configuracoes e operacao

- menu administrativo do Pressplay LMS
- pagina de configuracoes da marca e Vimeo
- pagina administrativa de alunos
- aviso de dependencias obrigatorias e recomendadas
- opcao para apagar dados ao desinstalar

## Dependencias

Obrigatoria:

- WooCommerce

Recomendada:

- Mercado Pago for WooCommerce

Opcional:

- Vimeo Access Token para validacao de videos e duracao automatica

## Banco de dados

O plugin cria as tabelas:

- `wp_press_students`
- `wp_press_enrollments`
- `wp_press_progress`

Essas tabelas armazenam:

- perfil complementar do aluno
- matriculas e expiracao de acesso
- progresso por aula e conclusao

## Comportamento na ativacao

Ao ativar o plugin, ele:

- cria a role `press_student`
- registra os CPTs
- registra as rotas customizadas
- executa `flush_rewrite_rules()`
- habilita cadastro publico no WordPress
- define `press_student` como role padrao
- ajusta configuracoes importantes do WooCommerce para o fluxo de conta e checkout

## Estrutura atual

```text
pressplay-lms/
├── assets/
│   ├── css/
│   ├── js/
│   └── svg/
├── includes/
│   ├── Core/
│   │   ├── Activator.php
│   │   ├── Assets.php
│   │   ├── Deactivator.php
│   │   ├── Dependencies.php
│   │   ├── Plugin.php
│   │   ├── Rewrite.php
│   │   └── Templates.php
│   ├── Support/
│   │   └── Helpers.php
│   ├── Actions.php
│   ├── CPT.php
│   ├── CPT_Teacher.php
│   ├── Certificate.php
│   ├── Database.php
│   ├── Duration.php
│   ├── Enrollments.php
│   ├── Frontend.php
│   ├── Mailer.php
│   ├── Materials.php
│   ├── Metabox_Course.php
│   ├── Metabox_Lesson.php
│   ├── Metabox_Teacher.php
│   ├── Progress.php
│   ├── Roles.php
│   ├── Settings.php
│   ├── Vimeo.php
│   └── Woo.php
├── templates/
│   ├── certificado/
│   ├── frontend/
│   └── panel/
├── pressplay-lms.php
├── uninstall.php
└── README.md
```

## Organizacao do codigo

O plugin ainda nao segue MVC classico, de forma intencional.

A organizacao atual esta baseada em modulos por responsabilidade, o que conversa melhor com WordPress:

- `Core/`: bootstrap, ativacao, rotas, assets, templates e dependencias
- `Support/`: helpers compartilhados
- `includes/*.php`: regras de negocio e modulos principais
- `templates/`: renderizacao do frontend e certificado
- `assets/`: estilos, scripts e icones

Essa foi a primeira etapa de reorganizacao estrutural, sem alterar a logica de negocio central.

## Proximos passos sugeridos

- quebrar classes muito grandes como `Frontend`, `Actions` e `Settings`
- separar melhor `Admin`, `Frontend`, `Domain` e `Integrations`
- introduzir autoload e namespaces
- melhorar dashboard administrativo
- expandir relatorios de alunos, matriculas e progresso
- adicionar endpoints REST quando o dominio estiver mais desacoplado

## Licenca

GPL v2 ou superior
