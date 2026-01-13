-- Migration: Agregar columna mostrar_dias_transcurridos a tm_campo_plantilla
-- Fecha: 2026-01-13

ALTER TABLE tm_campo_plantilla
ADD COLUMN mostrar_dias_transcurridos TINYINT(1) NOT NULL DEFAULT 0;