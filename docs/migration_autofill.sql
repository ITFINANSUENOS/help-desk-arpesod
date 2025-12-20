ALTER TABLE tm_campo_plantilla
ADD COLUMN campo_trigger INT DEFAULT 0;

ALTER TABLE tm_campo_plantilla
ADD COLUMN campo_query TEXT DEFAULT NULL;