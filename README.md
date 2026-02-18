# 🎓 Pressplay LMS

Plugin LMS enxuto para WordPress com foco em cursos online, cadastro de alunos e venda via WooCommerce.

## ✅ Funcionalidades atuais

- CPT de **Cursos** (`mlb_course`) e **Aulas** (`mlb_lesson`) para gestão no admin.
- Rotas customizadas:
  - `/cadastro`
  - `/meus-cursos`
  - `/curso/{slug}`
  - `/curso/{slug}/aula/{slug}`
- Cadastro de aluno via shortcode `[mlb_register]` com:
  - criação de usuário WordPress
  - role `malibu_student`
  - gravação de perfil em tabela custom (`mlb_students`)
  - e-mail para definição de senha
- Sincronização Curso → Produto WooCommerce:
  - cria/atualiza produto ao salvar curso publicado com preço
- Fluxo de compra WooCommerce → matrícula:
  - ao pedido concluído, ativa matrícula por 1 ano em `mlb_enrollments`
- Controle de acesso por matrícula ativa:
  - curso exibe vitrine (título/capa/trailer/descrição) para todos
  - lista de aulas e conteúdo de aula exigem matrícula ativa
- Configurações administrativas funcionais para:
  - `brand_name`
  - `email_logo_url`
  - `vimeo_token` (reservado)
  - `danger_allow_uninstall_cleanup`

## 🧱 Estrutura atual do plugin

```text
pressplay_lms/
├── assets/
│   └── css/
│       ├── admin.css
│       └── app.css
├── includes/
│   ├── Activator.php
│   ├── CPT.php
│   ├── Database.php
│   ├── Deactivator.php
│   ├── Dependencies.php
│   ├── Enrollments.php
│   ├── Frontend.php
│   ├── Helpers.php
│   ├── Mailer.php
│   ├── Metabox_Course.php
│   ├── Metabox_Lesson.php
│   ├── Rewrite.php
│   ├── Roles.php
│   ├── Settings.php
│   ├── Templates.php
│   └── Woo.php
├── templates/
│   └── single-mlb_course.php
├── malibu-lms.php
├── uninstall.php
└── README.md
```

## 🗃️ Tabelas customizadas

Criadas na ativação:

- `wp_mlb_students`
- `wp_mlb_enrollments`
- `wp_mlb_progress`

## 🛣️ Roadmap sugerido

- Tela real de listagem de alunos/matrículas/progresso.
- Relatórios de progresso por curso.
- Certificados automáticos por conclusão.
- Integração avançada com vídeo/progresso (ex.: Vimeo API).

## 📄 Licença

GPL v2 ou superior.
