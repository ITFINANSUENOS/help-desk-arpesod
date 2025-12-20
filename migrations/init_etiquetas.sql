-- Tabla Maestra de Etiquetas (Personalizadas por Usuario)
CREATE TABLE `tm_etiqueta` (
    `eti_id` int(11) NOT NULL AUTO_INCREMENT,
    `usu_id` int(11) NOT NULL COMMENT 'ID del usuario propietario de la etiqueta',
    `eti_nom` varchar(150) COLLATE utf8_spanish_ci NOT NULL,
    `eti_color` varchar(50) COLLATE utf8_spanish_ci NOT NULL,
    `fech_crea` datetime DEFAULT NULL,
    `est` int(11) NOT NULL DEFAULT '1',
    PRIMARY KEY (`eti_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_spanish_ci;

-- Tabla de Detalle (Relación Ticket - Etiqueta)
CREATE TABLE `td_ticket_etiqueta` (
    `tick_eti_id` int(11) NOT NULL AUTO_INCREMENT,
    `tick_id` int(11) NOT NULL,
    `eti_id` int(11) NOT NULL,
    `usu_id` int(11) NOT NULL COMMENT 'ID del usuario que asignó',
    `fech_crea` datetime DEFAULT NULL,
    `est` int(11) NOT NULL DEFAULT '1',
    PRIMARY KEY (`tick_eti_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_spanish_ci;