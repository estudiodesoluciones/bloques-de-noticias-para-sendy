<?php
defined('ABSPATH') || exit;

final class ES_SN_Settings {
    const OPTION = 'es_sn_settings';

    public static function get(): array {
        return wp_parse_args((array) get_option(self::OPTION, []), [
            'url'=>'', 'brand_id'=>0, 'from_name'=>get_bloginfo('name'),
            'from_email'=>'', 'reply_to'=>'', 'secret'=>'',
        ]);
    }

    public static function key(): string {
        if (defined('ES_SN_API_KEY')) { return (string) ES_SN_API_KEY; }
        $value = self::get()['secret'];
        if (!$value || !function_exists('openssl_decrypt')) { return ''; }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) { return ''; }
        $key = hash('sha256', wp_salt('auth'), true);
        return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    }

    public static function encrypt(string $value): string {
        if (!function_exists('openssl_encrypt')) { throw new RuntimeException(esc_html__('OpenSSL es necesario para guardar la clave. También puedes definir ES_SN_API_KEY en wp-config.php.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        $iv = random_bytes(12); $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) { throw new RuntimeException(esc_html__('No se pudo proteger la clave API.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function save(array $input): void {
        $old = self::get();
        $url = untrailingslashit(esc_url_raw(trim((string) ($input['url'] ?? '')), ['https']));
        if (!empty($input['url']) && (!$url || !wp_http_validate_url($url) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || wp_parse_url($url, PHP_URL_QUERY) || wp_parse_url($url, PHP_URL_FRAGMENT) || wp_parse_url($url, PHP_URL_USER))) {
            throw new InvalidArgumentException(esc_html__('Introduce la URL HTTPS pública de Sendy, sin parámetros ni credenciales.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy'));
        }
        $new = [
            'url'=>$url, 'brand_id'=>absint($input['brand_id'] ?? 0),
            'from_name'=>sanitize_text_field($input['from_name'] ?? ''),
            'from_email'=>sanitize_email($input['from_email'] ?? ''),
            'reply_to'=>sanitize_email($input['reply_to'] ?? ''), 'secret'=>$old['secret'],
        ];
        foreach (['from_email','reply_to'] as $field) {
            if (!empty($input[$field]) && !is_email($input[$field])) { throw new InvalidArgumentException(esc_html__('Revisa las direcciones de correo del remitente.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        }
        // Nunca reutilizar automáticamente una clave contra otro servidor.
        if ($url !== $old['url'] || !empty($input['clear_key'])) { $new['secret'] = ''; }
        if (!defined('ES_SN_API_KEY') && !empty($input['api_key'])) { $new['secret'] = self::encrypt(trim((string) $input['api_key'])); }
        update_option(self::OPTION, $new, false);
    }
}
