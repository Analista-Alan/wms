# WMS Geagro — Endereçamento com QR Code

Sistema web em PHP para endereçamento de estoque (ruas/posições) com geração e
leitura de QR Code, construído sobre o banco de dados **Geagro** (Firebird)
que você enviou.

⚠️ **Antes de tudo**: o arquivo `BancoDados1508.sql` que você me enviou tem, em
texto puro, a senha do usuário `SYSDBA` do Firebird. Troque essa senha antes
de colocar qualquer coisa em produção — ela já deve ser considerada exposta.

## O que foi entregue

1. **`sql/001_wms_enderecos_qrcode.sql`** — script de migração que cria, em
   cima do seu banco atual, as tabelas que faltavam para endereçamento físico:
   - `WMS_POSICAO` — o endereço final (nível + posição dentro de um prédio),
     com o campo `CODIGO` que é o texto gravado no QR (ex.:
     `AR01-CR02-PR03-N01-P05`).
   - `WMS_ESTOQUE_POSICAO` — quanto de cada produto/lote está em cada posição.
   - `WMS_MOVIMENTACAO` — log de toda entrada, saída e transferência (auditoria).

   As tabelas `WMS_ARMAZEM`, `WMS_CORREDOR` (rua) e `WMS_PREDIO` (prédio/bloco)
   **já existiam** no seu banco e foram reaproveitadas.

2. **Sistema PHP completo** (pasta `public/`), com:
   - Login usando a tabela `USUARIO` já existente.
   - Cadastro de Armazém → Rua/Corredor → Prédio → geração em lote das Posições.
   - Impressão de etiquetas com QR Code das posições e dos produtos
     (`etiquetas_posicoes.php` / `etiquetas_produtos.php`).
   - **Recebimento** (`recebimento.php`): importa rascunhos da tabela `ESBOCO`
     (sincronizada do SAP B1) para `WMS_RECEBIMENTO`, permite conferir a
     quantidade fisicamente recebida, e depois de concluído os itens aparecem
     automaticamente na tela de Endereçar para guardar no WMS.
   - Endereçamento e movimentação por leitura de câmera (sem precisar de
     coletor dedicado).
   - **Separação** (`separacao.php`): importa itens de um Pedido de Venda ou
     de uma Nota Fiscal já existente no Geagro, e permite retirar o produto
     de uma ou mais posições/lotes até completar a quantidade pedida.
   - **Expedição** (`expedicao.php`): fecha uma separação concluída,
     vinculando transportadora/veículo/motorista já cadastrados no Geagro.
   - **Inventário** (`inventario.php`): abre uma contagem geral (todo o
     armazém) ou cíclica (um prédio específico), no modo cego (não mostra
     saldo do sistema) ou visível, permite contar bipando a posição, registra
     divergências e aplica os ajustes de volta ao estoque endereçado.
   - Consulta de estoque por posição ou por produto (`consulta_estoque.php`).

A leitura de QR usa a câmera do próprio celular/tablet pelo navegador (não
precisa de leitor de código de barras dedicado). A geração dos QR Codes também
acontece no navegador, pronta para impressão em folha de etiquetas.

## Estrutura de pastas

```
wms-geagro/
├── config/
│   └── config.php          <- dados de conexão com o Firebird (edite aqui)
├── includes/                <- classes PHP (Database, Auth, Endereco, Produto, EstoquePosicao)
├── sql/
│   └── 001_wms_enderecos_qrcode.sql
└── public/                   <- ESTA pasta é o DocumentRoot do Apache/Nginx
    ├── index.php, login.php, enderecos.php, enderecar.php, movimentar.php, ...
    ├── api/                  <- endpoints AJAX (JSON)
    └── assets/               <- css/js
```

**Importante:** aponte o DocumentRoot do Apache/Nginx para a pasta `public/`.
As pastas `config/`, `includes/` e `sql/` devem ficar **fora** da área
acessível pelo navegador (elas já são referenciadas com caminho relativo
`__DIR__ . '/../...'`, então funcionam normalmente estando um nível acima).

## Passo a passo de instalação

### 1. Requisitos do servidor
- PHP 8.1+ com a extensão **`pdo_firebird`** habilitada (ou, como alternativa,
  a extensão **`interbase`**/`ibase`, mais comum em instalações Windows
  antigas). O código detecta automaticamente qual está disponível.
- Acesso de rede do servidor web até o Firebird (`172.16.10.90:3050` conforme
  visto no seu dump — ajuste se for diferente).
- Um servidor web (Apache ou Nginx) com PHP-FPM ou mod_php.

Para confirmar se a extensão está ativa:
```bash
php -m | grep -i fire
php -m | grep -i interbase
```

### 2. Rodar a migração no banco

Rode os scripts **na ordem numérica** (001 depois 002, 003, 004, 005):
```bash
isql -i sql/001_wms_enderecos_qrcode.sql -user SYSDBA -password "SUA_SENHA" "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB"
isql -i sql/002_wms_recebimento.sql      -user SYSDBA -password "SUA_SENHA" "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB"
isql -i sql/003_wms_separacao.sql        -user SYSDBA -password "SUA_SENHA" "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB"
isql -i sql/004_wms_expedicao.sql        -user SYSDBA -password "SUA_SENHA" "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB"
isql -i sql/005_wms_inventario.sql       -user SYSDBA -password "SUA_SENHA" "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB"
```

Se algum deles falhar no meio do caminho, rode o rollback correspondente antes
de tentar de novo: `sql/000_rollback_se_precisar.sql` (para o 001) ou
`sql/002-005_rollback_se_precisar.sql` (para qualquer um dos módulos novos).

⚠️ **Ajuste obrigatório antes de usar o Recebimento**: abra
`includes/Recebimento.php` e confira a constante `TIPO_OBJETO_RECEBIMENTO`
(hoje está `20`, o código-padrão do SAP B1 para Recebimento de Mercadoria/
Goods Receipt PO — mas confirme, pois pode variar conforme customizações do
seu SAP). Para confirmar com certeza, rode no seu Firebird pegando o número
de um documento que você já sabe que é um recebimento:
```sql
SELECT ID_ESBOCO, NUMERO_DOCUMENTO, TIPO_OBJETO, TIPO_ESBOCO
FROM ESBOCO
WHERE NUMERO_DOCUMENTO = <número do documento>;
```
e veja o valor retornado em `TIPO_OBJETO`.

### 3. Configurar a conexão
Edite `config/config.php`:
```php
define('DB_HOST', '172.16.10.90');
define('DB_PORT', '3050');
define('DB_PATH', 'C:\\Sync\\Banco\\Geagro\\BANCO.FDB');
define('DB_USER', 'SYSDBA');
define('DB_PASS', 'sua-senha-nova-aqui');
```

### 4. Subir o servidor web
Exemplo rápido para testar localmente (servidor embutido do PHP):
```bash
cd wms-geagro/public
php -S 0.0.0.0:8080
```
Acesse `http://localhost:8080`. Em produção, use Apache/Nginx apontando o
DocumentRoot para `public/`.

### 5. Login
O login usa a tabela `USUARIO` já existente, comparando com o campo
`SENHA_MD5`. Se algum usuário ainda não tiver esse campo preenchido, gere com:
```sql
UPDATE USUARIO SET SENHA_MD5 = UPPER('COLOQUE_AQUI_O_MD5_DA_SENHA') WHERE ID_USUARIO = 1;
```
(Recomendo migrar para um hash mais forte como `password_hash()`/`bcrypt` assim
que possível — o MD5 aqui só existe porque já era o padrão do campo legado.)

## Fluxo de uso no dia a dia

1. **Cadastrar a estrutura física** (`Endereços`): crie o armazém, depois as
   ruas, depois os prédios/blocos informando quantos níveis eles têm. Clique
   em "Gerar" para criar automaticamente todas as posições daquele prédio.
2. **Imprimir etiquetas** (`Etiquetas de posições`): filtre por
   armazém/rua/prédio e clique em imprimir — sai uma folha com QR + código
   legível para cada posição, pronta para colar na prateleira.
3. **Imprimir etiquetas de produto** (`Etiquetas de produtos`): busque o
   produto e imprima o QR dele (mesma lógica).
4. **Endereçar estoque** (`Endereçar`): no celular, abra essa tela, aponte a
   câmera pro QR da posição e depois pro QR do produto (ou busque manualmente),
   informe quantidade/lote/validade e confirme.
5. **Movimentar** (`Movimentar`): mesma lógica, mas lendo posição de origem +
   destino (ou só origem, para saída/expedição).
6. **Consultar** (`Consultar estoque`): veja o conteúdo de uma posição
   (bipando o QR) ou todas as posições onde um produto está guardado.

## Enviando este projeto para o GitHub

O `.gitignore` já vem configurado para **não** versionar o `config/config.php`
real (só o `config/config.example.php`, sem senha). Rode estes comandos a
partir da pasta `wms-geagro` no seu computador (Prompt de Comando/PowerShell,
com o [Git for Windows](https://git-scm.com/download/win) instalado):

```powershell
cd C:\xampp\htdocs\wms-geagro
git init
git add .
git commit -m "Sistema WMS Geagro - versão inicial"
```

Depois, crie um repositório vazio em https://github.com/new (sem README, sem
.gitignore — só o nome) e conecte:

```powershell
git branch -M main
git remote add origin https://github.com/SEU_USUARIO/NOME_DO_REPOSITORIO.git
git push -u origin main
```

Na primeira vez, o Git vai pedir login — use um **Personal Access Token**
(não a senha da conta): gere um em
https://github.com/settings/tokens → "Generate new token (classic)" → marque
o escopo `repo` → copie o token e cole quando o Git pedir a senha.

Se marcar o repositório como **privado** (recomendado, já que o código
menciona a estrutura do seu ERP), só quem você convidar consegue ver.

⚠️ Antes de subir, confira se `config/config.php` realmente não aparece em
`git status` (deve aparecer como ignorado) — ele tem a senha do Firebird.

## Limitações e próximos passos sugeridos

- Este pacote cobre o **fluxo físico de endereçamento** (onde cada produto
  está guardado). Ele não substitui os módulos de pedido/faturamento/estoque
  fiscal já existentes no Geagro (`ESTOQUE`, `PEDIDO_VENDA`, `NOTA_*` etc.) —
  ele complementa, respondendo "em qual posição física está o produto X".
- Não testei contra um Firebird real (não tenho acesso de rede a
  `172.16.10.90` a partir deste ambiente) — testei apenas a sintaxe PHP de
  todos os arquivos (`php -l`, sem erros). Recomendo rodar a migração num
  banco de homologação/cópia antes de aplicar em produção.
- Ideias de evolução: exigir conferência dupla (bipar posição + bipar produto
  + bipar de novo para confirmar), suporte a múltiplos usuários simultâneos
  com filas de tarefas, dashboard de ocupação por armazém, exportar mapa de
  ruas em PDF.
#   w m s  
 