<?php
defined('ABSPATH') || exit;

final class ES_SN_API {
    public static function request(string $path, array $body) {
        $s = ES_SN_Settings::get(); $key = ES_SN_Settings::key();
        if (!$s['url'] || !$key) { return new WP_Error('configuration', __('Configura la URL de Sendy y su clave API. Puedes copiar el HTML sin conexión.', 'bloques-de-noticias-para-sendy')); }
        // TLS verificado, sin redirecciones de la clave ni acceso a redes privadas.
        $response = wp_safe_remote_post($s['url'] . '/api/' . $path, [
            'timeout'=>30,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>262144,
            'body'=>array_merge($body, ['api_key'=>$key]),
        ]);
        if (is_wp_error($response)) { return new WP_Error('connection', __('No se ha podido confirmar la respuesta de Sendy. Comprueba sus borradores antes de repetir, para evitar duplicados. El HTML sigue disponible.', 'bloques-de-noticias-para-sendy')); }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            /* translators: %d: HTTP response status from Sendy. */
            return new WP_Error('http', sprintf(__('Sendy respondió con HTTP %d. Comprueba la URL, el certificado y el cortafuegos. El HTML sigue disponible.', 'bloques-de-noticias-para-sendy'), wp_remote_retrieve_response_code($response))); }
        $raw = trim(wp_remote_retrieve_body($response));
        $known = [
            'Invalid API key'=>__('La clave API no es válida.', 'bloques-de-noticias-para-sendy'),
            'Brand ID not passed.'=>__('Falta el ID de marca.', 'bloques-de-noticias-para-sendy'),
            'Brand does not exist'=>__('La marca no existe en Sendy.', 'bloques-de-noticias-para-sendy'),
            'Unable to create campaign'=>__('Sendy no pudo crear el borrador.', 'bloques-de-noticias-para-sendy'),
            'No brands found'=>__('Sendy no tiene marcas configuradas.', 'bloques-de-noticias-para-sendy'),
        ];
        if (isset($known[$raw])) { return new WP_Error('api', $known[$raw]); }
        return $raw;
    }

    public static function brands() {
        $raw = self::request('brands/get-brands.php', []);
        if (is_wp_error($raw)) { return $raw; }
        $data = json_decode($raw, true); $brands = [];
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item) && isset($item['id'], $item['name'])) { $brands[] = ['id'=>absint($item['id']),'name'=>sanitize_text_field($item['name'])]; }
            }
        }
        return $brands ?: new WP_Error('response', __('No se pudo interpretar la lista de marcas de Sendy. Revisa la URL y la clave.', 'bloques-de-noticias-para-sendy'));
    }

    public static function create(array $preview, string $subject) {
        $s = ES_SN_Settings::get();
        if (!$s['brand_id'] || !$s['from_name'] || !is_email($s['from_email']) || !is_email($s['reply_to'])) {
            return new WP_Error('configuration', __('Completa marca, nombre del remitente, correo y dirección de respuesta en Configuración.', 'bloques-de-noticias-para-sendy'));
        }
        // Lista cerrada de parámetros: jamás aceptar listas, fechas o send_campaign del navegador.
        $raw = self::request('campaigns/create.php', [
            'brand_id'=>$s['brand_id'],'from_name'=>$s['from_name'],'from_email'=>$s['from_email'],'reply_to'=>$s['reply_to'],
            'title'=>$subject,'subject'=>$subject,'html_text'=>$preview['html'],'plain_text'=>$preview['plain'],
            'send_campaign'=>0,'json'=>1,
        ]);
        if (is_wp_error($raw)) { return $raw; }
        $data = json_decode($raw, true);
        if (is_array($data) && ($data['status'] ?? '') === 'Campaign created' && !empty($data['campaign_id']) && ctype_digit((string) $data['campaign_id'])) {
            return ['id'=>(int) $data['campaign_id'],'url'=>$s['url'] . '/edit?i=' . $s['brand_id'] . '&c=' . (int) $data['campaign_id']];
        }
        if ($raw === 'Campaign created') { return ['id'=>0,'url'=>$s['url'] . '/app?i=' . $s['brand_id']]; }
        return new WP_Error('response', __('La respuesta no confirma la creación. Comprueba los borradores de Sendy antes de repetir. Puedes copiar el HTML.', 'bloques-de-noticias-para-sendy'));
    }
}
