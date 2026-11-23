<?php
/**
 * Plugin Name: Estudio de Soluciones – Bloques de noticias para Sendy
 * Description: Selecciona noticias, diseña su listado y crea borradores en Sendy o copia su HTML.
 * Version: 1.0.0
 * Author: Estudio de Soluciones
 * Author URI: https://estudiodesoluciones.com
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: estudio-de-soluciones-bloques-de-noticias-para-sendy
 */
defined('ABSPATH') || exit;
define('ES_SN_VERSION', '1.0.0');
define('ES_SN_FILE', __FILE__);
foreach (['settings', 'content', 'api', 'admin'] as $es_sn_file) {
    require_once __DIR__ . '/includes/class-' . $es_sn_file . '.php';
}
add_action('plugins_loaded', static function () { new ES_SN_Admin(); });

