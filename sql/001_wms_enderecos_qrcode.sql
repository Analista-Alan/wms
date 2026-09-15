/******************************************************************************/
/****  Migração WMS - Endereçamento com QR Code (Ruas / Posições)          ****/
/****  Compatível com banco Geagro (Firebird, dialect 3)                   ****/
/****                                                                       ****/
/****  Aproveita as tabelas já existentes:                                 ****/
/****    WMS_ARMAZEM   -> Armazém                                          ****/
/****    WMS_CORREDOR  -> Rua / Corredor                                   ****/
/****    WMS_PREDIO    -> Prédio / Bloco dentro do corredor (tem QTD_NIVEIS)****/
/****                                                                       ****/
/****  Cria:                                                               ****/
/****    WMS_POSICAO         -> endereço final (nível+posição) com QR      ****/
/****    WMS_ESTOQUE_POSICAO -> saldo de produto/lote em cada posição      ****/
/****    WMS_MOVIMENTACAO    -> histórico de toda movimentação/auditoria   ****/
/******************************************************************************/

/* ---------------------------------------------------------------------- */
/* Generators                                                             */
/* ---------------------------------------------------------------------- */

CREATE GENERATOR GEN_WMS_POSICAO_ID;
SET GENERATOR GEN_WMS_POSICAO_ID TO 0;

CREATE GENERATOR GEN_WMS_ESTOQUE_POSICAO_ID;
SET GENERATOR GEN_WMS_ESTOQUE_POSICAO_ID TO 0;

CREATE GENERATOR GEN_WMS_MOVIMENTACAO_ID;
SET GENERATOR GEN_WMS_MOVIMENTACAO_ID TO 0;

/* ---------------------------------------------------------------------- */
/* WMS_POSICAO - endereço físico (rua > prédio > nível > posição)         */
/* CODIGO é o texto gravado no QR Code, ex: AR01-CR02-PR03-N01-P05        */
/* ---------------------------------------------------------------------- */

CREATE TABLE WMS_POSICAO (
    ID_POSICAO         INTEGER NOT NULL,
    ID_PREDIO          INTEGER NOT NULL,
    NIVEL              INTEGER NOT NULL,
    POSICAO            INTEGER NOT NULL,
    CODIGO             VARCHAR(50) NOT NULL,
    CAPACIDADE_MAXIMA  NUMERIC(18,4),
    STATUS             CHAR(1) DEFAULT 'A' NOT NULL, /* A=Ativa, B=Bloqueada, I=Inativa */
    OBSERVACAO         VARCHAR(200),
    DATA_INCLUSAO      TIMESTAMP,
    DATA_ATUALIZACAO   TIMESTAMP,
    CONSTRAINT PK_WMS_POSICAO PRIMARY KEY (ID_POSICAO),
    CONSTRAINT UK_WMS_POSICAO_CODIGO UNIQUE (CODIGO)
);
/* Observação: NÃO criamos FOREIGN KEY para WMS_PREDIO porque, neste banco,
   WMS_PREDIO.ID_PREDIO não tem PRIMARY KEY/UNIQUE (padrão do Geagro: a
   maioria das tabelas legadas não declara chave primária no banco, apenas
   na aplicação). O Firebird exige unicidade na coluna referenciada para
   permitir uma FK. A integridade ID_PREDIO -> WMS_PREDIO é garantida pela
   aplicação (ver includes/Endereco.php). Fica só o índice normal abaixo,
   para performance de busca/join. */

CREATE INDEX IDX_WMS_POSICAO_PREDIO ON WMS_POSICAO (ID_PREDIO);
CREATE INDEX IDX_WMS_POSICAO_STATUS ON WMS_POSICAO (STATUS);

/* ---------------------------------------------------------------------- */
/* WMS_ESTOQUE_POSICAO - saldo de produto/lote endereçado numa posição    */
/* ---------------------------------------------------------------------- */

CREATE TABLE WMS_ESTOQUE_POSICAO (
    ID_ESTOQUE_POSICAO   INTEGER NOT NULL,
    ID_POSICAO           INTEGER NOT NULL,
    ID_PRODUTO           INTEGER NOT NULL,
    LOTE                 VARCHAR(50),
    QUANTIDADE           NUMERIC(18,4) NOT NULL,
    DATA_VALIDADE        DATE,
    ID_FILIAL            INTEGER,
    ID_USUARIO_INCLUSAO  INTEGER,
    DATA_INCLUSAO        TIMESTAMP,
    DATA_ATUALIZACAO     TIMESTAMP,
    CONSTRAINT PK_WMS_ESTOQUE_POSICAO PRIMARY KEY (ID_ESTOQUE_POSICAO),
    CONSTRAINT FK_WMS_ESTPOS_POSICAO FOREIGN KEY (ID_POSICAO) REFERENCES WMS_POSICAO (ID_POSICAO)
);
/* Sem FK para PRODUTO pelo mesmo motivo: PRODUTO.ID_PRODUTO não tem
   PRIMARY KEY/UNIQUE neste banco (só existe UNIQUE em ID_SAP). A FK para
   WMS_POSICAO funciona normalmente porque WMS_POSICAO é nossa tabela nova,
   já criada acima com PRIMARY KEY. */

CREATE INDEX IDX_WMS_ESTPOS_POSICAO ON WMS_ESTOQUE_POSICAO (ID_POSICAO);
CREATE INDEX IDX_WMS_ESTPOS_PRODUTO ON WMS_ESTOQUE_POSICAO (ID_PRODUTO);
CREATE INDEX IDX_WMS_ESTPOS_LOTE ON WMS_ESTOQUE_POSICAO (ID_PRODUTO, LOTE);

/* Evita duas linhas para o mesmo produto+lote na mesma posição:
   a aplicação sempre deve fazer UPDATE se já existir, e só faz INSERT
   quando não existir (ver includes/EstoquePosicao.php) */
CREATE UNIQUE INDEX UK_WMS_ESTPOS_POS_PROD_LOTE
    ON WMS_ESTOQUE_POSICAO (ID_POSICAO, ID_PRODUTO, LOTE);

/* ---------------------------------------------------------------------- */
/* WMS_MOVIMENTACAO - log de toda entrada/saída/transferência/ajuste      */
/* ---------------------------------------------------------------------- */

CREATE TABLE WMS_MOVIMENTACAO (
    ID_MOVIMENTACAO     INTEGER NOT NULL,
    TIPO                CHAR(1) NOT NULL, /* E=Entrada/Endereçamento, S=Saída, T=Transferência, A=Ajuste */
    ID_PRODUTO          INTEGER NOT NULL,
    LOTE                VARCHAR(50),
    QUANTIDADE          NUMERIC(18,4) NOT NULL,
    ID_POSICAO_ORIGEM   INTEGER,
    ID_POSICAO_DESTINO  INTEGER,
    ID_USUARIO          INTEGER,
    OBSERVACAO          VARCHAR(200),
    DATA_MOVIMENTACAO   TIMESTAMP,
    CONSTRAINT PK_WMS_MOVIMENTACAO PRIMARY KEY (ID_MOVIMENTACAO),
    CONSTRAINT FK_WMS_MOV_POS_ORIGEM FOREIGN KEY (ID_POSICAO_ORIGEM) REFERENCES WMS_POSICAO (ID_POSICAO),
    CONSTRAINT FK_WMS_MOV_POS_DESTINO FOREIGN KEY (ID_POSICAO_DESTINO) REFERENCES WMS_POSICAO (ID_POSICAO)
);
/* Sem FK para PRODUTO, mesmo motivo das tabelas acima. */

CREATE INDEX IDX_WMS_MOV_DATA ON WMS_MOVIMENTACAO (DATA_MOVIMENTACAO);
CREATE INDEX IDX_WMS_MOV_PRODUTO ON WMS_MOVIMENTACAO (ID_PRODUTO);

/* ---------------------------------------------------------------------- */
/* Triggers - auto incremento de ID no padrão já usado no banco Geagro    */
/* ---------------------------------------------------------------------- */

SET TERM ^ ;

CREATE TRIGGER WMS_POSICAO_BI FOR WMS_POSICAO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_POSICAO IS NULL) THEN NEW.ID_POSICAO = GEN_ID(GEN_WMS_POSICAO_ID, 1);
  IF (NEW.DATA_INCLUSAO IS NULL) THEN NEW.DATA_INCLUSAO = CURRENT_TIMESTAMP;
  IF (NEW.STATUS IS NULL) THEN NEW.STATUS = 'A';
END
^

CREATE TRIGGER WMS_POSICAO_BU FOR WMS_POSICAO
ACTIVE BEFORE UPDATE POSITION 0
AS BEGIN
  NEW.DATA_ATUALIZACAO = CURRENT_TIMESTAMP;
END
^

CREATE TRIGGER WMS_ESTOQUE_POSICAO_BI FOR WMS_ESTOQUE_POSICAO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_ESTOQUE_POSICAO IS NULL) THEN NEW.ID_ESTOQUE_POSICAO = GEN_ID(GEN_WMS_ESTOQUE_POSICAO_ID, 1);
  IF (NEW.DATA_INCLUSAO IS NULL) THEN NEW.DATA_INCLUSAO = CURRENT_TIMESTAMP;
END
^

CREATE TRIGGER WMS_ESTOQUE_POSICAO_BU FOR WMS_ESTOQUE_POSICAO
ACTIVE BEFORE UPDATE POSITION 0
AS BEGIN
  NEW.DATA_ATUALIZACAO = CURRENT_TIMESTAMP;
END
^

CREATE TRIGGER WMS_MOVIMENTACAO_BI FOR WMS_MOVIMENTACAO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_MOVIMENTACAO IS NULL) THEN NEW.ID_MOVIMENTACAO = GEN_ID(GEN_WMS_MOVIMENTACAO_ID, 1);
  IF (NEW.DATA_MOVIMENTACAO IS NULL) THEN NEW.DATA_MOVIMENTACAO = CURRENT_TIMESTAMP;
END
^

SET TERM ; ^

/******************************************************************************/
/****  Fim da migração                                                     ****/
/****  Rodar com: isql -i 001_wms_enderecos_qrcode.sql -user SYSDBA        ****/
/****             -password ... "172.16.10.90:C:\Sync\Banco\Geagro\BANCO.FDB" ****/
/******************************************************************************/
