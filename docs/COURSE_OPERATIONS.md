# Operacao dos Cursos

Guia dos recursos adicionados na versao 1.1.0. Consulte [QA.md](QA.md) para os
limites dos testes e os passos pendentes antes de aceitar vendas.

## Conteudo e ordem

No editor do curso, a aba **Aulas** mostra a numeracao usada no curriculo.
Defina a coluna **Ordem** e atualize o curso para reorganizar as aulas.
Posicoes positivas aparecem primeiro; `0` significa ordem automatica por ID
de cadastro, nunca por titulo. Empates usam o ID para manter a ordem estavel.
Aulas em rascunho nao entram na duracao publica nem ficam acessiveis ao visitante.

## Aula gratis

No editor da aula, marque **Aula gratuita de amostra** e publique a alteracao.
A etiqueta **Aula gratis** aparece nas listas e essa aula abre sem conta ou
matricula. O restante do curso continua protegido. A opcao vem desmarcada.

Visitantes de amostras nao recebem controles de progresso, certificados ou
a lista de materiais de alunos. Nao coloque conteudo confidencial no texto ou
no video de uma aula que sera publicada como amostra.

## Materiais

Use **Materiais gerais do curso** para apostilas e links comuns a todas as aulas.
A lista aparece para quem tem acesso, na pagina do curso; o painel **Meus cursos**
oferece um atalho. O editor da aula continua aceitando materiais especificos.

Importante: a lista tem controle de acesso, mas arquivos da biblioteca WordPress
e URLs externas nao se tornam downloads privados. Quem conhecer a URL direta
pode conseguir abrir o arquivo. Documentos sigilosos exigem armazenamento e
entrega protegidos, nao apenas a ocultacao do link.

## Liberacao manual

Em **Pressplay LMS > Matriculas > Liberar acesso manual**, procure uma conta
existente por nome, login ou e-mail. Escolha o curso, a validade em dias, meses
ou anos, ou **Ilimitado**, e confirme. O prazo comeca no momento da liberacao,
no fuso configurado no WordPress. E-mail de aviso e opcional, desmarcado por padrao.

Nao e criado pedido ou pagamento. O usuario mantem seus cargos e usa seu login
habitual em `/meus-cursos/` e `/perfil/`. Motivo, operador, data e validade ficam
registrados. Uma matricula ativa existente nao e duplicada nem encurtada; use
as acoes dessa matricula. Bloquear uma linha nao bloqueia outras compras.

Administradores continuam com permissoes administrativas de previsualizacao;
isso nao equivale a uma matricula vitalicia. A matricula manual respeita sua
propria validade no painel do aluno. Contas ainda precisam existir no WordPress.

## Video, duracao e progresso

O plugin tenta a API do Vimeo e, quando necessario, o oEmbed oficial. A falha
temporaria da API nao apaga uma duracao ja obtida para o mesmo video. Alterar a
URL para outro video invalida a duracao antiga. Videos privados ainda precisam
permitir incorporacao no dominio correto e manter o hash da URL, quando houver.

O progresso soma intervalos unicos assistidos e considera a duracao de cada aula.
Avancar a barra ou assistir novamente o mesmo trecho nao soma tempo duplicado.
A posicao para retomar a reproducao e salva separadamente. Ha salvamento periodico,
na pausa e na saida, sujeito a conexao e ao ciclo de vida do navegador.

Dados antigos nao permitem reconstruir trechos que nunca foram registrados.
Aulas sem duracao conhecida usam o fallback de conclusao por aula. Conclusao
manual e dados enviados pelo navegador nao constituem prova de presenca.

O trailer usa a tela cheia nativa do Vimeo. O plugin solicita ocultacao da marca,
mas o plano e as configuracoes do proprietario do video determinam o resultado.

## Termos da compra

Preencha **Termos de compra e contrato** no curso. Vazio significa que o curso nao
exige contrato adicional. O comprador pode ler os termos e deve marcar um aceite
nao preselecionado no checkout. Cada curso do carrinho exige seu proprio aceite.
Editar o texto muda sua versao e exige um novo aceite antes da compra.

O pedido armazena o texto aceito, hash da versao, data UTC e dados da requisicao
e do comprador. Edicoes posteriores nao substituem o documento ja aceito.
O registro e aceite eletronico, nao uma assinatura digital certificada nem uma
garantia juridica. Liberacao manual nao fabrica aceite em nome do aluno.

Revise o contrato com assessoria juridica, mantenha consentimentos opcionais de
imagem/marketing separados e estabeleca politica de privacidade e retencao para
os registros. Homologue checkout classico, Blocks e retomada de pagamento em
sandbox antes de liberar vendas. Nao altere valores da loja durante testes.
