<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
// Conservar las campañas de Sendy: pertenecen a la instalación remota.
delete_option('es_sn_settings');
delete_option('es_sn_design');
global $wpdb;
foreach (['es_sn_lock_', '_transient_es_sn_preview_', '_transient_timeout_es_sn_preview_'] as $es_sn_prefix) {
    $es_sn_names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($es_sn_prefix) . '%'));
    foreach ($es_sn_names as $es_sn_name) { delete_option($es_sn_name); }
}
wp_unschedule_hook('es_sn_cleanup_lock');
