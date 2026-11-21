# Bloques de noticias para Sendy

Plugin independiente de WordPress creado por [Estudio de Soluciones](https://estudiodesoluciones.com), distribuido bajo GPL-2.0-or-later.

No hay dominios, credenciales, marcas ni rutas de ese sitio en el código distribuible. No requiere MailPoet ni modificar Sendy.

## Estructura

- `bloques-de-noticias-para-sendy.php`: carga y metadatos.
- `includes/`: configuración cifrada, selección/renderizado, API y administración.
- `assets/`: interfaz de administración, sin dependencias externas.
- `tests/integration.php`: pruebas ejecutables con WP-CLI; fixtures temporales y API simulada.
- `readme.txt`: manual de instalación, uso, privacidad y limitaciones.
- `dist/`: ZIP instalable; no incluye pruebas ni datos de la web.

## Desarrollo y comprobaciones

PHP 8.1+, WordPress 6.3+, OpenSSL para guardar la API key. También se puede proporcionar `ES_SN_API_KEY` desde `wp-config.php`. La interfaz inicial está en español; la publicación en un directorio público requerirá completar la internacionalización de la interfaz y revisar los requisitos vigentes del directorio.

Ejecutar en un WordPress de pruebas:

```sh
wp eval-file /ruta/bloques-de-noticias-para-sendy/tests/integration.php
```

Las pruebas crean y eliminan entradas/categorías temporales y restauran las opciones del plugin. Interceptan las llamadas HTTP: no crean campañas reales ni envían correos. No ejecutarlas simultáneamente con usuarios que estén configurando el plugin.

La integración real se comprueba con Probar conexión y un borrador marcado PRUEBA. No se debe enviar una campaña para comprobar este plugin.

## Arquitectura y límites

La API estándar crea solo borradores (`send_campaign=0`), sin fechas ni listas. El editor por bloques se elige en Sendy después de abrir el borrador. No hay accesos directos a su base de datos ni extensiones de su API. Se conserva una instantánea de la vista previa durante una hora por administrador y se impiden creaciones repetidas de la misma instantánea. Un resultado incierto no se reintenta automáticamente.

El HTML es una columna con tablas y estilos inline. La vista previa no sustituye pruebas en los clientes de correo de los destinatarios. La selección admite hasta 100 entradas y no ejecuta shortcodes ni bloques dinámicos. Una entrada sin imagen genera un aviso y se compone sin esa imagen.

La primera versión soporta instalación por sitio. No está preparada para activación global multisitio. No añade atribución al contenido de las newsletters; la autoría figura en la ficha del plugin y su panel.

## Empaquetado

Ejecutar `python3 build.py` desde el proyecto para regenerar el ZIP de la versión indicada en la cabecera del plugin.

Incluir únicamente `bloques-de-noticias-para-sendy.php`, `uninstall.php`, `includes/`, `assets/`, `readme.txt` y `LICENSE` en una carpeta `bloques-de-noticias-para-sendy/` dentro del ZIP. Ningún ajuste del banco de pruebas forma parte del paquete.

## Migración desde Sendy News

Desactiva la versión con carpeta `sendy-news` antes de activar la nueva. Ambas utilizan las mismas opciones. Conserva una copia de seguridad y evita el botón Borrar de la versión anterior, ya que su desinstalador elimina los ajustes compartidos.
