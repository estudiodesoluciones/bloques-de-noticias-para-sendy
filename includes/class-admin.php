<?php
defined('ABSPATH') || exit;

final class ES_SN_Admin {
    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_enqueue_scripts', [$this,'assets']);
        add_action('wp_ajax_es_sn', [$this,'ajax']);
        add_action('es_sn_cleanup_lock', static function ($key) { if (str_starts_with($key, 'es_sn_lock_')) { delete_option($key); } });
    }
    public function menu(): void {
        add_menu_page(__('Bloques de noticias para Sendy', 'bloques-de-noticias-para-sendy'), __('Bloques de noticias para Sendy', 'bloques-de-noticias-para-sendy'), 'manage_options', 'bloques-de-noticias-para-sendy', [$this,'page'], 'dashicons-email-alt', 80);
    }
    public function assets(string $hook): void {
        if ($hook !== 'toplevel_page_bloques-de-noticias-para-sendy') { return; }
        wp_enqueue_style('es-sn', plugins_url('assets/admin.css', ES_SN_FILE), [], ES_SN_VERSION);
        wp_enqueue_script('es-sn', plugins_url('assets/admin.js', ES_SN_FILE), [], ES_SN_VERSION, true);
        wp_localize_script('es-sn', 'esSn', ['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('es_sn'),'design'=>(array) get_option('es_sn_design', [])]);
    }
    public function ajax(): void {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>__('No tienes permisos.', 'bloques-de-noticias-para-sendy')], 403); }
        check_ajax_referer('es_sn', 'nonce');
        $in = wp_unslash($_POST);
        try {
            switch ($in['operation'] ?? '') {
                case 'settings':
                    ES_SN_Settings::save($in); $result = ['message'=>__('Configuración guardada.', 'bloques-de-noticias-para-sendy'),'has_key'=>ES_SN_Settings::key() !== '']; break;
                case 'test':
                    $result = ES_SN_API::brands(); break;
                case 'design':
                    update_option('es_sn_design', ES_SN_Content::style($in), false);
                    $result = ['saved'=>true]; break;
                case 'search':
                    $result = ES_SN_Content::search($in); break;
                case 'preview':
                    $result = ES_SN_Content::build((array) ($in['ids'] ?? []), $in);
                    $token = wp_generate_uuid4();
                    set_transient('es_sn_preview_' . get_current_user_id() . '_' . $token, ['content'=>$result,'status'=>'ready'], HOUR_IN_SECONDS);
                    $result['token'] = $token; break;
                case 'send':
                    $result = $this->send($in); break;
                default: throw new InvalidArgumentException(esc_html__('Operación desconocida.', 'bloques-de-noticias-para-sendy'));
            }
            if (is_wp_error($result)) { wp_send_json_error(['message'=>$result->get_error_message()]); }
            wp_send_json_success($result);
        } catch (InvalidArgumentException | RuntimeException $e) {
            wp_send_json_error(['message'=>$e->getMessage()]);
        } catch (Throwable $e) {
            wp_send_json_error(['message'=>__('No se pudo completar la operación. Revisa la configuración y vuelve a cargar la página.', 'bloques-de-noticias-para-sendy')]);
        }
    }
    public function send(array $in) {
        $token = (string) ($in['token'] ?? '');
        if (!preg_match('/^[a-f0-9-]{36}$/D', $token)) { return new WP_Error('preview', __('Genera la vista previa antes de crear el borrador.', 'bloques-de-noticias-para-sendy')); }
        $key = 'es_sn_preview_' . get_current_user_id() . '_' . $token;
        $state = get_transient($key);
        if (!$state) { return new WP_Error('expired', __('La vista previa ha caducado (1 hora). Vuelve a generarla.', 'bloques-de-noticias-para-sendy')); }
        if ($state['status'] === 'created') { return $state['result']; }
        if ($state['status'] !== 'ready') { return new WP_Error('uncertain', __('Esta creación ya se intentó. Comprueba Sendy antes de generar otra vista previa y volver a intentarlo.', 'bloques-de-noticias-para-sendy')); }
        $subject = sanitize_text_field($in['subject'] ?? '');
        if ($subject === '' || mb_strlen($subject) > 200) { return new WP_Error('subject', __('Introduce un asunto de entre 1 y 200 caracteres.', 'bloques-de-noticias-para-sendy')); }
        $s = ES_SN_Settings::get();
        if (!$s['url'] || !ES_SN_Settings::key() || !$s['brand_id'] || !$s['from_name'] || !is_email($s['from_email']) || !is_email($s['reply_to'])) {
            return new WP_Error('configuration', __('Completa la configuración de Sendy o utiliza el HTML disponible.', 'bloques-de-noticias-para-sendy'));
        }
        $lock = 'es_sn_lock_' . $token;
        if (!add_option($lock, time(), '', false)) { return new WP_Error('busy', __('La creación ya está en curso.', 'bloques-de-noticias-para-sendy')); }
        wp_schedule_single_event(time() + 120, 'es_sn_cleanup_lock', [$lock]);
        try {
            // Leer después de adquirir el bloqueo para evitar dos envíos simultáneos.
            $state = get_transient($key);
            if (!$state || $state['status'] !== 'ready') { return new WP_Error('duplicate', __('Este borrador ya se ha procesado. Comprueba Sendy.', 'bloques-de-noticias-para-sendy')); }
            $state['status'] = 'attempted'; set_transient($key, $state, HOUR_IN_SECONDS);
            $result = ES_SN_API::create($state['content'], $subject);
            if (!is_wp_error($result)) {
                $state['status'] = 'created'; $state['result'] = $result; set_transient($key, $state, HOUR_IN_SECONDS);
            }
            return $result;
        } finally { delete_option($lock); wp_clear_scheduled_hook('es_sn_cleanup_lock', [$lock]); }
    }
    public function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $settings = ES_SN_Settings::get();
        require __DIR__ . '/view-admin.php';
    }
}
