/******************************************************************************/
/****  Módulo: RECEBIMENTO DE MERCADORIA                                   ****/
/****                                                                       ****/
/****  Fluxo: o comprovante/nota já existe como rascunho na tabela ESBOCO   ****/
/****  (sincronizada do SAP B1) com um TIPO_ESBOCO específico. O operador   ****/
/****  do WMS importa esse rascunho para WMS_RECEBIMENTO, confere          ****/
/****  fisicamente as quantidades (WMS_RECEBIMENTO_ITEMS) e, depois de     ****/
/****  concluída a conferência, os itens ficam disponíveis para           ****/
/****  endereçamento (tela "Endereçar" já existente).                      ****/
/******************************************************************************/

CREATE GENERATOR GEN_WMS_RECEBIMENTO_ID;
SET GENERATOR GEN_WMS_RECEBIMENTO_ID TO 0;

CREATE GENERATOR GEN_WMS_RECEBIMENTO_ITEM_ID;
SET GENERATOR GEN_WMS_RECEBIMENTO_ITEM_ID TO 0;

CREATE TABLE WMS_RECEBIMENTO (
    ID_RECEBIMENTO       INTEGER NOT NULL,
    ID_ESBOCO_ORIGEM     INTEGER,               /* ESBOCO.ID_ESBOCO de onde foi importado */
    NUMERO_DOCUMENTO     INTEGER,
    CHAVE_NFE            VARCHAR(44),
    FORNECEDOR           VARCHAR(20),           /* espelha ESBOCO.ID_CLIENTE (fornecedor, no contexto de compra) */
    DATA_EMISSAO         DATE,
    DATA_NOTA            DATE,
    VALOR                NUMERIC(18,4),
    STATUS               VARCHAR(15) DEFAULT 'AGUARDANDO' NOT NULL, /* AGUARDANDO, CONFERINDO, CONCLUIDO, CANCELADO */
    OBSERVACAO           VARCHAR(300),
    ID_USUARIO_IMPORTACAO INTEGER,
    DATA_IMPORTACAO      TIMESTAMP,
    ID_USUARIO_CONCLUSAO INTEGER,
    DATA_CONCLUSAO       TIMESTAMP,
    CONSTRAINT PK_WMS_RECEBIMENTO PRIMARY KEY (ID_RECEBIMENTO)
);

CREATE INDEX IDX_WMS_RECEB_ESBOCO ON WMS_RECEBIMENTO (ID_ESBOCO_ORIGEM);
CREATE INDEX IDX_WMS_RECEB_STATUS ON WMS_RECEBIMENTO (STATUS);

CREATE TABLE WMS_RECEBIMENTO_ITEMS (
    ID_ITEM               INTEGER NOT NULL,
    ID_RECEBIMENTO        INTEGER NOT NULL,
    ID_PRODUTO            INTEGER NOT NULL,
    LOTE                  VARCHAR(50),
    VENCIMENTO            DATE,
    QUANTIDADE_NOTA       NUMERIC(18,4) NOT NULL,
    QUANTIDADE_RECEBIDA   NUMERIC(18,4) DEFAULT 0,
    QUANTIDADE_ENDERECADA NUMERIC(18,4) DEFAULT 0,
    PRECO_UNITARIO        NUMERIC(18,4),
    OBSERVACAO            VARCHAR(200), /* ex: avaria, divergência de quantidade */
    CONSTRAINT PK_WMS_RECEBIMENTO_ITEMS PRIMARY KEY (ID_ITEM),
    CONSTRAINT FK_WMS_RECEBITEM_RECEB FOREIGN KEY (ID_RECEBIMENTO) REFERENCES WMS_RECEBIMENTO (ID_RECEBIMENTO)
);
/* Sem FK para PRODUTO: PRODUTO.ID_PRODUTO não tem PRIMARY KEY/UNIQUE neste banco. */

CREATE INDEX IDX_WMS_RECEBITEM_RECEB ON WMS_RECEBIMENTO_ITEMS (ID_RECEBIMENTO);
CREATE INDEX IDX_WMS_RECEBITEM_PRODUTO ON WMS_RECEBIMENTO_ITEMS (ID_PRODUTO);

SET TERM ^ ;

CREATE TRIGGER WMS_RECEBIMENTO_BI FOR WMS_RECEBIMENTO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_RECEBIMENTO IS NULL) THEN NEW.ID_RECEBIMENTO = GEN_ID(GEN_WMS_RECEBIMENTO_ID, 1);
  IF (NEW.DATA_IMPORTACAO IS NULL) THEN NEW.DATA_IMPORTACAO = CURRENT_TIMESTAMP;
  IF (NEW.STATUS IS NULL) THEN NEW.STATUS = 'AGUARDANDO';
END
^

CREATE TRIGGER WMS_RECEBIMENTO_ITEMS_BI FOR WMS_RECEBIMENTO_ITEMS
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_ITEM IS NULL) THEN NEW.ID_ITEM = GEN_ID(GEN_WMS_RECEBIMENTO_ITEM_ID, 1);
  IF (NEW.QUANTIDADE_RECEBIDA IS NULL) THEN NEW.QUANTIDADE_RECEBIDA = 0;
  IF (NEW.QUANTIDADE_ENDERECADA IS NULL) THEN NEW.QUANTIDADE_ENDERECADA = 0;
END
^

SET TERM ; ^

/******************************************************************************/
/****  IMPORTANTE: ajuste o valor de TIPO_ESBOCO que representa "compra/    ****/
/****  recebimento" em config/config.php (constante TIPO_ESBOCO_RECEBIMENTO)****/
/****  O campo ESBOCO.TIPO_ESBOCO é CHAR(1) e o significado de cada valor   ****/
/****  depende de como o seu SAP B1/Geagro está configurado.                ****/
/******************************************************************************/
