/******************************************************************************/
/****  Módulo: INVENTÁRIO                                                  ****/
/****                                                                       ****/
/****  Suporta os três modos pedidos:                                      ****/
/****    TIPO  = 'G' (Geral - todo o armazém) ou 'C' (Cíclico - só um       ****/
/****            prédio/posição específica)                                ****/
/****    MODO  = 'C' (Cego - operador não vê o saldo do sistema) ou         ****/
/****            'V' (Visível - saldo do sistema aparece na contagem)       ****/
/******************************************************************************/

CREATE GENERATOR GEN_WMS_INVENTARIO_ID;
SET GENERATOR GEN_WMS_INVENTARIO_ID TO 0;

CREATE GENERATOR GEN_WMS_INVENTARIO_ITEM_ID;
SET GENERATOR GEN_WMS_INVENTARIO_ITEM_ID TO 0;

CREATE TABLE WMS_INVENTARIO (
    ID_INVENTARIO         INTEGER NOT NULL,
    TIPO                  CHAR(1) NOT NULL,  /* G = Geral, C = Cíclico (por posição/prédio) */
    MODO                  CHAR(1) NOT NULL,  /* C = Cego, V = Visível */
    ID_ARMAZEM            INTEGER,
    ID_PREDIO             INTEGER,           /* preenchido só quando TIPO = 'C' */
    DESCRICAO             VARCHAR(200),
    STATUS                VARCHAR(15) DEFAULT 'ABERTO' NOT NULL, /* ABERTO, EM_CONTAGEM, FINALIZADO, CANCELADO */
    ID_USUARIO_ABERTURA   INTEGER,
    DATA_ABERTURA         TIMESTAMP,
    ID_USUARIO_FINALIZACAO INTEGER,
    DATA_FINALIZACAO      TIMESTAMP,
    OBSERVACAO            VARCHAR(300),
    CONSTRAINT PK_WMS_INVENTARIO PRIMARY KEY (ID_INVENTARIO)
);

CREATE INDEX IDX_WMS_INVENTARIO_STATUS ON WMS_INVENTARIO (STATUS);

CREATE TABLE WMS_INVENTARIO_ITEMS (
    ID_ITEM              INTEGER NOT NULL,
    ID_INVENTARIO        INTEGER NOT NULL,
    ID_POSICAO           INTEGER NOT NULL,
    ID_PRODUTO           INTEGER NOT NULL,
    LOTE                 VARCHAR(50),
    QUANTIDADE_SISTEMA   NUMERIC(18,4) NOT NULL, /* snapshot no momento da abertura */
    QUANTIDADE_CONTADA   NUMERIC(18,4),          /* NULL enquanto não contado */
    DIVERGENCIA          NUMERIC(18,4),          /* contada - sistema, calculado ao salvar */
    ID_USUARIO_CONTAGEM  INTEGER,
    DATA_CONTAGEM        TIMESTAMP,
    AJUSTADO             CHAR(1) DEFAULT 'N',    /* Y = já refletido em WMS_ESTOQUE_POSICAO */
    CONSTRAINT PK_WMS_INVENTARIO_ITEMS PRIMARY KEY (ID_ITEM),
    CONSTRAINT FK_WMS_INVITEM_INVENTARIO FOREIGN KEY (ID_INVENTARIO) REFERENCES WMS_INVENTARIO (ID_INVENTARIO),
    CONSTRAINT FK_WMS_INVITEM_POSICAO FOREIGN KEY (ID_POSICAO) REFERENCES WMS_POSICAO (ID_POSICAO)
);

CREATE INDEX IDX_WMS_INVITEM_INVENTARIO ON WMS_INVENTARIO_ITEMS (ID_INVENTARIO);
CREATE INDEX IDX_WMS_INVITEM_POSICAO ON WMS_INVENTARIO_ITEMS (ID_POSICAO);

SET TERM ^ ;

CREATE TRIGGER WMS_INVENTARIO_BI FOR WMS_INVENTARIO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_INVENTARIO IS NULL) THEN NEW.ID_INVENTARIO = GEN_ID(GEN_WMS_INVENTARIO_ID, 1);
  IF (NEW.DATA_ABERTURA IS NULL) THEN NEW.DATA_ABERTURA = CURRENT_TIMESTAMP;
  IF (NEW.STATUS IS NULL) THEN NEW.STATUS = 'ABERTO';
  IF (NEW.AJUSTADO IS NULL) THEN NEW.AJUSTADO = 'N';
END
^

CREATE TRIGGER WMS_INVENTARIO_ITEMS_BI FOR WMS_INVENTARIO_ITEMS
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_ITEM IS NULL) THEN NEW.ID_ITEM = GEN_ID(GEN_WMS_INVENTARIO_ITEM_ID, 1);
  IF (NEW.AJUSTADO IS NULL) THEN NEW.AJUSTADO = 'N';
END
^

SET TERM ; ^
