<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
// Conservar las campañas de Sendy: pertenecen a la instalación remota.
delete_option('es_sn_settings');
delete_option('es_sn_design');
global $wpdb;
foreach (['es_sn_lock_', '_transient_es_sn_preview_', '_transient_timeout_es_sn_preview_'] as $es_sn_prefix) {
    // WordPress 6.3 has no API to list option names by prefix. Uninstall needs the current
    // names once, including locks and previews created since any previous lookup.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $es_sn_names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($es_sn_prefix) . '%'));
    foreach ($es_sn_names as $es_sn_name) { delete_option($es_sn_name); }
}
wp_unschedule_hook('es_sn_cleanup_lock');
