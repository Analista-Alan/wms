/******************************************************************************/
/****  Limpeza dos módulos 002-005 (Recebimento, Separação, Expedição,      ****/
/****  Inventário) — rode ANTES de reaplicar qualquer um deles, se algum    ****/
/****  tiver falhado no meio do caminho.                                    ****/
/****                                                                       ****/
/****  Erros de "objeto não existe" em linhas individuais podem ser         ****/
/****  ignorados — só rode as linhas seguintes normalmente.                 ****/
/****                                                                       ****/
/****  A ordem importa (dependentes antes dos que eles referenciam).        ****/
/******************************************************************************/

/* 005 - Inventário */
DROP TRIGGER WMS_INVENTARIO_ITEMS_BI;
DROP TRIGGER WMS_INVENTARIO_BI;
DROP TABLE WMS_INVENTARIO_ITEMS;
DROP TABLE WMS_INVENTARIO;
DROP GENERATOR GEN_WMS_INVENTARIO_ITEM_ID;
DROP GENERATOR GEN_WMS_INVENTARIO_ID;

/* 004 - Expedição */
DROP TRIGGER WMS_EXPEDICAO_BI;
DROP TABLE WMS_EXPEDICAO;
DROP GENERATOR GEN_WMS_EXPEDICAO_ID;

/* 003 - Separação */
DROP TRIGGER WMS_SEPARACAO_RETIRADAS_BI;
DROP TRIGGER WMS_SEPARACAO_ITEMS_BI;
DROP TRIGGER WMS_SEPARACAO_BI;
DROP TABLE WMS_SEPARACAO_RETIRADAS;
DROP TABLE WMS_SEPARACAO_ITEMS;
DROP TABLE WMS_SEPARACAO;
DROP GENERATOR GEN_WMS_SEPARACAO_RETIRADA_ID;
DROP GENERATOR GEN_WMS_SEPARACAO_ITEM_ID;
DROP GENERATOR GEN_WMS_SEPARACAO_ID;

/* 002 - Recebimento */
DROP TRIGGER WMS_RECEBIMENTO_ITEMS_BI;
DROP TRIGGER WMS_RECEBIMENTO_BI;
DROP TABLE WMS_RECEBIMENTO_ITEMS;
DROP TABLE WMS_RECEBIMENTO;
DROP GENERATOR GEN_WMS_RECEBIMENTO_ITEM_ID;
DROP GENERATOR GEN_WMS_RECEBIMENTO_ID;
