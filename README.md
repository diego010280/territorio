# TERRITORIO — aplicación independiente

**NO se instala dentro de SIAD.** Directorio raíz `territorio/` (hermano de `siad/`), con su propio índice, menú lateral AdminLTE 4, clases, AJAX, JS, CSS, config, páginas, herramientas y SQL.

## Requisitos
- PHP 8.1 o superior, PDO MySQL, extensión `mbstring` para el importador.
- MariaDB 10.11 local o MySQL 8 en producción.
- Base `db_barrios` ya creada. El visor consulta `bar_importacion_geometrias`, sin publicación de geometrías.
- Acceso a CDNs para Bootstrap 5.3.7, AdminLTE 4 RC4, Bootstrap Icons 1.13.1 y Leaflet 1.9.4. Para red interna, copiar librerías a `assets/vendor/` y cambiar rutas.

## Despliegue
```
/var/www/html/siad/       # sin modificaciones
/var/www/html/territorio/  # proyecto autónomo
```
1. Copiar `config/config.example.php` a `config/config.local.php`, completar los valores de MariaDB local. Este archivo está ignorado por Git y no debe exponerse a Internet.
2. Configurar permisos de lectura del usuario del servidor web; negar acceso HTTP a `config/`, `clases/`, `scripts/` y `sql/` desde el servidor web (el .htaccess solo funciona bajo Apache con AllowOverride).
3. Integrar `clases/AccesoTerritorio.php` con autenticación y perfiles institucionales. La versión de base requiere `$_SESSION['logged']` y `$_SESSION['usuario_id']`. **No proporciona pantalla de login y no permite acceso anónimo**. No supone que el módulo dependa de SIAD.
4. Abrir `http://localhost/territorio/index.php?pagina=visor` luego de autenticar la sesión. Sin datos en tabla intermedia, el mapa aparecerá vacío y mostrará mensaje; eso no es un fallo.
5. El mapa consulta `ajax/territorio.php` de solo lectura. El resto de las páginas son placeholders, no ABM implementados.

## Importación municipal (solo local)
`php scripts/importar_barrios_municipales.php /ruta/barrio3.js` hace validación. `--importar` escribe a `bar_importacion_geometrias` con variables DB_HOST/DB_PORT/DB_USER/DB_PASS definidas; no lo ejecutes en producción sin revisar. El archivo de cartografía original debe ser suministrado por separado.

## Reglas funcionales
- db_gral, SURC, dbseg y RRHH **solo lectura**, sin ALTER ni UPDATE.
- Sistema autónomo Territorio, compatible con el estilo SIAD (AdminLTE 4 + Bootstrap 5.3), sin copiar su sesión, rutas ni navegación.
- Barrios, jurisdicciones, escuelas y aprobaciones son secciones progresivas.
- Altas, KML/KMZ, edición gráfica, auditoría de cada operación y aprobación por administrador: **pendientes** de implementar, no simuladas como terminadas.
- Nunca considerar oficial una geometría de staging o una equivalencia SURC PENDIENTE.
- Antes de habilitar escrituras: autorización por perfil en cada endpoint, CSRF, auditoría, limitación de uploads, validación de geometrías y bloqueo de aprobación concurrente.
# territorio
