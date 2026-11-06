-- SOLO LECTURA: ejecutar en LOCAL
SELECT COUNT(*) AS barrios FROM db_barrios.bar_barrios;
SELECT COUNT(*) AS equivalencias_surc FROM db_barrios.bar_equivalencias_surc;
SELECT COUNT(*) AS cartografia_en_revision FROM db_barrios.bar_importacion_geometrias;
SELECT estado, COUNT(*) AS total FROM db_barrios.bar_importacion_geometrias GROUP BY estado;
SELECT ST_SRID(geometria) AS srid, COUNT(*) AS total FROM db_barrios.bar_importacion_geometrias GROUP BY ST_SRID(geometria);
