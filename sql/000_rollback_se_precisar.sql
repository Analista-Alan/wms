/******************************************************************************/
/****  Script de limpeza - rode ANTES de reaplicar 001_wms_enderecos_qrcode ****/
/****                                                                       ****/
/****  Motivo: a primeira tentativa de migração falhou na criação da tabela ****/
/****  WMS_POSICAO (erro -607, por causa de FOREIGN KEY para tabela sem PK) ****/
/****  mas os GENERATORS já tinham sido criados e commitados antes do erro. ****/
/****  Este script remove tudo que possa ter sido criado, para você poder   ****/
/****  reaplicar o script corrigido do zero, sem conflito.                  ****/
/****                                                                       ****/
/****  Se algum destes objetos não existir no seu banco, o isql vai mostrar ****/
/****  um erro do tipo "object ... not found" nessa linha específica -      ****/
/****  pode ignorar e seguir para as próximas linhas normalmente.           ****/
/******************************************************************************/

DROP TRIGGER WMS_MOVIMENTACAO_BI;
DROP TRIGGER WMS_ESTOQUE_POSICAO_BU;
DROP TRIGGER WMS_ESTOQUE_POSICAO_BI;
DROP TRIGGER WMS_POSICAO_BU;
DROP TRIGGER WMS_POSICAO_BI;

DROP TABLE WMS_MOVIMENTACAO;
DROP TABLE WMS_ESTOQUE_POSICAO;
DROP TABLE WMS_POSICAO;

DROP GENERATOR GEN_WMS_MOVIMENTACAO_ID;
DROP GENERATOR GEN_WMS_ESTOQUE_POSICAO_ID;
DROP GENERATOR GEN_WMS_POSICAO_ID;

/******************************************************************************/
/****  Depois de rodar este arquivo, rode:                                 ****/
/****    isql -i 001_wms_enderecos_qrcode.sql -user SYSDBA -password ...   ****/
/******************************************************************************/
