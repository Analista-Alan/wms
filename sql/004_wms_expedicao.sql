/******************************************************************************/
/****  Módulo: EXPEDIÇÃO                                                   ****/
/****                                                                       ****/
/****  Fecha uma separação já concluída, vinculando transportadora,        ****/
/****  veículo e motorista JÁ CADASTRADOS no Geagro (tabelas               ****/
/****  TRANSPORTADORA, VEICULO, MOTORISTA). Não duplicamos cadastro nenhum, ****/
/****  só referenciamos os IDs já existentes.                              ****/
/******************************************************************************/

CREATE GENERATOR GEN_WMS_EXPEDICAO_ID;
SET GENERATOR GEN_WMS_EXPEDICAO_ID TO 0;

CREATE TABLE WMS_EXPEDICAO (
    ID_EXPEDICAO       INTEGER NOT NULL,
    ID_SEPARACAO       INTEGER NOT NULL,
    ID_TRANSPORTADORA  INTEGER,
    ID_VEICULO         INTEGER,
    ID_MOTORISTA       INTEGER,
    NUMERO_LACRE       VARCHAR(50),
    PESO_TOTAL         NUMERIC(18,4),
    STATUS             VARCHAR(15) DEFAULT 'AGUARDANDO' NOT NULL, /* AGUARDANDO, EXPEDIDO, CANCELADO */
    OBSERVACAO         VARCHAR(300),
    ID_USUARIO         INTEGER,
    DATA_CRIACAO       TIMESTAMP,
    DATA_EXPEDICAO     TIMESTAMP,
    CONSTRAINT PK_WMS_EXPEDICAO PRIMARY KEY (ID_EXPEDICAO),
    CONSTRAINT FK_WMS_EXPED_SEPARACAO FOREIGN KEY (ID_SEPARACAO) REFERENCES WMS_SEPARACAO (ID_SEPARACAO)
);
/* Sem FK para TRANSPORTADORA/VEICULO/MOTORISTA: nenhuma das três tem
   PRIMARY KEY/UNIQUE declarada neste banco (padrão legado do Geagro). */

CREATE INDEX IDX_WMS_EXPED_SEPARACAO ON WMS_EXPEDICAO (ID_SEPARACAO);
CREATE INDEX IDX_WMS_EXPED_STATUS ON WMS_EXPEDICAO (STATUS);

SET TERM ^ ;

CREATE TRIGGER WMS_EXPEDICAO_BI FOR WMS_EXPEDICAO
ACTIVE BEFORE INSERT POSITION 0
AS BEGIN
  IF (NEW.ID_EXPEDICAO IS NULL) THEN NEW.ID_EXPEDICAO = GEN_ID(GEN_WMS_EXPEDICAO_ID, 1);
  IF (NEW.DATA_CRIACAO IS NULL) THEN NEW.DATA_CRIACAO = CURRENT_TIMESTAMP;
  IF (NEW.STATUS IS NULL) THEN NEW.STATUS = 'AGUARDANDO';
END
^

SET TERM ; ^
