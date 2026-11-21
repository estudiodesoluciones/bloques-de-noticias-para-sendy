<?php
// wp eval-file /path/to/tests/integration.php — API simulada, sin envíos.
if (!defined('ABSPATH')) { exit("Usa WP-CLI.\n"); }
if (!class_exists('ES_SN_Content')) { require dirname(__DIR__) . '/bloques-de-noticias-para-sendy.php'; }
$saved = get_option('es_sn_settings', null); $created = []; $category = 0; $checks = 0; $calls = [];
$original_user = get_current_user_id();
$assert = static function ($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException('FAIL: ' . $message); } $checks++; };
$http_result = ['response'=>['code'=>200], 'body'=>'{"status":"Campaign created","campaign_id":"987654"}'];
$mock = static function ($pre, $args, $url) use (&$calls, &$http_result) {
    if (str_starts_with($url, 'https://example.com/sendy/api/')) { $calls[] = [$url,$args]; return $http_result; }
    return $pre;
};
add_filter('pre_http_request', $mock, 10, 3);
$token = wp_generate_uuid4(); $state_key = '';
global $wpdb;
$wpdb->query('START TRANSACTION');
try {
    $name = 'es-sn-test-' . wp_generate_uuid4();
    $term = wp_insert_term($name, 'category'); if (is_wp_error($term)) { throw new RuntimeException('No se pudo crear el fixture.'); } $category = $term['term_id'];
    foreach (['publish','publish','private','draft','publish'] as $i=>$status) {
        $id = wp_insert_post(['post_title'=>$name . ' ' . $i,'post_content'=>'<p>Primera frase con acentos áéíóú y emoji 🍎.</p><p>Segunda frase y más contenido para recortar correctamente.</p><script>alert(1)</script>',
            'post_excerpt'=>$i === 0 ? 'Resumen editorial independiente.' : '', 'post_status'=>$status,'post_type'=>'post',
            'post_date'=>'2020-01-' . (10+$i) . ' 12:00:00','post_password'=>$i===4 ? 'test' : '','post_category'=>[$category]], true);
        if (is_wp_error($id)) { throw new RuntimeException('No se pudo insertar fixture.'); } $created[] = $id;
    }
    $q = ES_SN_Content::search(['categories'=>[$category],'limit'=>10,'after'=>'2020-01-10','before'=>'2020-01-11']);
    $assert(count($q['items'])===2 && $q['total']===2, 'Filtros, fechas inclusivas y exclusión de no públicos');
    $assert($q['items'][0]['id']===$created[1], 'Orden más reciente');
    $q = ES_SN_Content::search(['categories'=>[$category],'limit'=>1]);
    $assert(count($q['items'])===1 && $q['total']===2, 'Límite y total de coincidencias');
    try { ES_SN_Content::search(['after'=>'2020-02-31']); $assert(false,'Fecha inválida'); } catch (InvalidArgumentException $e) { $checks++; }
    try { ES_SN_Content::search(['after'=>'2020-02-01','before'=>'2020-01-01']); $assert(false,'Rango inválido'); } catch (InvalidArgumentException $e) { $checks++; }
    $html = ES_SN_Content::build([$created[0],$created[1]], ['images'=>1,'separator'=>1,'length'=>50,'heading'=>'<img src=x onerror=alert(1)>','accent'=>'bad']);
    $assert($html['count']===2 && count($html['warnings'])===2, 'Selección y aviso por imágenes ausentes');
    $assert(strpos($html['html'], 'Resumen editorial independiente.')!==false, 'Preferencia por extracto');
    $assert(strpos($html['html'], '<script')===false && strpos($html['html'], 'onerror=')===false, 'Contenido activo eliminado');
    $assert(strpos($html['html'], '[unsubscribe]')!==false && strpos($html['fragment'], '[unsubscribe]')===false, 'Correo completo frente a fragmento');
    $assert(strpos($html['html'], '#cc402c')!==false, 'Color inválido sustituido');
    $assert(strpos($html['html'], esc_html($name.' 0')) < strpos($html['html'], esc_html($name.' 1')), 'Orden manual respetado');
    foreach ([$created[2],$created[3],$created[4],999999999] as $id) {
        try { ES_SN_Content::build([$id], []); $assert(false,'No permitir ID privado o inexistente'); } catch (InvalidArgumentException $e) { $checks++; }
    }
    $assert(ES_SN_Content::excerpt('Uno dos tres cuatro', 8)==='Uno dos…', 'Corte por palabras');
    $assert(mb_strlen(ES_SN_Content::excerpt('áéíóú 🍎 palabra palabra', 12))<=12, 'Límite Unicode');
    $assert(ES_SN_Content::excerpt('Texto',0)==='', 'Ocultar resumen');
    $assert(ES_SN_Content::text('<p>Uno</p><p>Dos</p>')==='Uno Dos', 'Separación de párrafos');
    ES_SN_Settings::save(['url'=>'https://example.com/sendy','api_key'=>'fake-test-key','brand_id'=>4,'from_name'=>'Test','from_email'=>'test@example.com','reply_to'=>'test@example.com']);
    $assert(ES_SN_Settings::key()==='fake-test-key' && strpos(ES_SN_Settings::get()['secret'],'fake-test-key')===false, 'Cifrado y descifrado de clave');
    $s=ES_SN_Settings::get(); ES_SN_Settings::save($s); $assert(ES_SN_Settings::key()==='fake-test-key', 'Guardar vacío conserva secreto');
    $admins = get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']); wp_set_current_user((int)$admins[0]);
    $state_key = 'es_sn_preview_' . get_current_user_id() . '_' . $token;
    set_transient($state_key, ['content'=>$html,'status'=>'ready'], HOUR_IN_SECONDS);
    $admin = new ES_SN_Admin();
    $r = $admin->send(['token'=>$token,'subject'=>'PRUEBA SIMULADA','send_campaign'=>1,'list_ids'=>'malicious','schedule_date_time'=>'tomorrow']);
    $assert(!is_wp_error($r) && $r['id']===987654, 'Creación interpretada');
    $body = $calls[0][1]['body'];
    $assert($body['send_campaign']===0 && !isset($body['list_ids']) && !isset($body['schedule_date_time']), 'No envío ni programación aunque el cliente los solicite');
    $assert($calls[0][1]['redirection']===0 && $calls[0][1]['sslverify']===true, 'TLS y redirecciones');
    $admin->send(['token'=>$token,'subject'=>'PRUEBA SIMULADA']); $assert(count($calls)===1, 'Idempotencia de doble clic');
    delete_transient($state_key);
    $r=$admin->send(['token'=>$token,'subject'=>'Prueba']); $assert(is_wp_error($r), 'Vista previa caducada');
    $http_result = new WP_Error('http_request_failed', 'timeout');
    set_transient($state_key,['content'=>$html,'status'=>'ready'],HOUR_IN_SECONDS);
    $r=$admin->send(['token'=>$token,'subject'=>'Prueba']); $assert(is_wp_error($r), 'Timeout devuelto');
    $assert(get_transient($state_key)['content']['html']===$html['html'], 'HTML conservado tras fallo');
    $count=count($calls); $admin->send(['token'=>$token,'subject'=>'Prueba']); $assert(count($calls)===$count, 'No reintento automático ante resultado incierto');
    $http_result=['response'=>['code'=>200],'body'=>'Invalid API key']; $r=ES_SN_API::brands(); $assert(is_wp_error($r), 'Clave inválida');
    $http_result=['response'=>['code'=>200],'body'=>'{"brand1":{"id":"4","name":"Example"}}']; $r=ES_SN_API::brands(); $assert($r[0]['id']===4, 'Marcas Sendy');
    ES_SN_Settings::save(array_merge($s,['url'=>'https://example.com/other'])); $assert(ES_SN_Settings::key()==='', 'Cambio de destino elimina clave');
    echo 'OK: ' . $checks . " comprobaciones; API simulada, sin campañas ni correos.\n";
} finally {
    remove_filter('pre_http_request', $mock, 10);
    if ($saved===null) { delete_option('es_sn_settings'); } else { update_option('es_sn_settings',$saved,false); }
    if ($state_key) { delete_transient($state_key); }
    delete_option('es_sn_lock_'.$token); wp_clear_scheduled_hook('es_sn_cleanup_lock',['es_sn_lock_'.$token]);
    foreach ($created as $id) { wp_delete_post($id, true); }
    if ($category) { wp_delete_term($category, 'category'); }
    wp_set_current_user($original_user);
    $wpdb->query('ROLLBACK');
    wp_cache_delete('es_sn_settings', 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
}
