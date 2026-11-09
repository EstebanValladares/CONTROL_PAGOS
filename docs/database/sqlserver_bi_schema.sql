/*
    Proposed BI payments table for Control Pagos OC.
    Run this script in the Aryve2 database using an authorized SQL Server login.
    It creates schema only; it does not connect to a server or copy local data.
*/

IF OBJECT_ID(N'dbo._pagos_programados', N'U') IS NULL
BEGIN
    CREATE TABLE dbo._pagos_programados (
        id_bi BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK__pagos_programados PRIMARY KEY,
        origen_sistema NVARCHAR(100) NOT NULL,
        id_pago_local BIGINT NOT NULL,
        empresa NVARCHAR(20) NULL,
        centro_costos NVARCHAR(100) NULL,
        numero_oc BIGINT NULL,
        id_ek10 NVARCHAR(100) NULL,
        proveedor NVARCHAR(500) NULL,
        total_oc DECIMAL(19, 4) NULL,
        monto_programado DECIMAL(19, 4) NULL,
        semana NVARCHAR(100) NULL,
        rubro NVARCHAR(500) NULL,
        tipo NVARCHAR(100) NULL,
        concepto NVARCHAR(MAX) NULL,
        creado_por NVARCHAR(255) NULL,
        creado_en DATETIME2(0) NULL,
        CONSTRAINT UQ__pagos_programados_origen_pago
            UNIQUE (origen_sistema, id_pago_local)
    );

    CREATE INDEX IX__pagos_programados_orden
        ON dbo._pagos_programados (empresa, centro_costos, numero_oc);
END;
GO
