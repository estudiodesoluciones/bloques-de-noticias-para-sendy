<?php
defined('ABSPATH') || exit;

final class ES_SN_Content {
    const MAX_POSTS = 100;

    public static function date(string $value): string {
        if ($value === '') { return ''; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new InvalidArgumentException(esc_html__('Fecha no válida.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        return $value;
    }

    public static function search(array $in): array {
        $after = self::date((string) ($in['after'] ?? '')); $before = self::date((string) ($in['before'] ?? ''));
        if ($after && $before && $after > $before) { throw new InvalidArgumentException(esc_html__('La fecha inicial debe ser anterior a la final.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        $limit = max(1, min(self::MAX_POSTS, absint($in['limit'] ?? 10)));
        $args = ['post_type'=>'post', 'post_status'=>'publish', 'has_password'=>false, 'posts_per_page'=>$limit,
            'orderby'=>['date'=>'DESC','ID'=>'DESC'], 'ignore_sticky_posts'=>true, 's'=>sanitize_text_field($in['search'] ?? '')];
        $cats = array_filter(array_map('absint', (array) ($in['categories'] ?? [])));
        if ($cats) { $args['category__in'] = $cats; }
        if ($after || $before) {
            $range = ['inclusive'=>true];
            if ($after) { $range['after'] = $after . ' 00:00:00'; }
            if ($before) { $range['before'] = $before . ' 23:59:59'; }
            $args['date_query'] = [$range];
        }
        $q = new WP_Query($args); $items = [];
        foreach ($q->posts as $p) {
            $items[] = ['id'=>$p->ID,'title'=>html_entity_decode(get_the_title($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'date'=>get_the_date('d/m/Y', $p), 'image'=>wp_get_attachment_image_url(get_post_thumbnail_id($p), 'thumbnail') ?: '',
                'categories'=>wp_get_post_categories($p->ID, ['fields'=>'names'])];
        }
        return ['items'=>$items, 'total'=>(int) $q->found_posts, 'limit'=>$limit];
    }

    public static function text(string $content): string {
        // No ejecutar bloques dinámicos, embeds ni shortcodes al preparar un correo.
        $content = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $content);
        $content = preg_replace('~</(?:p|div|li|h[1-6])>|<br\s*/?>~i', ' ', $content);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(strip_shortcodes($content)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function excerpt(string $text, int $length): string {
        if ($length === 0) { return ''; }
        $text = self::text($text);
        if (mb_strlen($text) <= $length) { return $text; }
        $cut = mb_substr($text, 0, $length - 1);
        // Si el carácter siguiente es espacio, la última palabra ya está completa.
        if (!preg_match('/\s/u', mb_substr($text, $length - 1, 1))) {
            $trimmed = preg_replace('/\s+\S*$/u', '', $cut);
            if ($trimmed !== $cut) { $cut = $trimmed; }
        }
        return rtrim($cut) . '…';
    }

    public static function style(array $in): array {
        $out = [];
        foreach (['accent'=>'#cc402c','title_color'=>'#444444','text_color'=>'#171717','background'=>'#f5f5f5','card'=>'#ffffff'] as $k=>$default) {
            $out[$k] = sanitize_hex_color($in[$k] ?? '') ?: $default;
        }
        foreach (['width'=>[640,480,800],'font_size'=>[18,12,24],'title_size'=>[25,18,36],'spacing'=>[24,8,48],'length'=>[350,0,2000]] as $k=>$bounds) {
            $out[$k] = max($bounds[1], min($bounds[2], isset($in[$k]) ? (int) $in[$k] : $bounds[0]));
        }
        $out['font'] = in_array($in['font'] ?? '', ['Arial','Georgia','Verdana'], true) ? $in['font'] : 'Arial';
        $out['heading'] = sanitize_text_field($in['heading'] ?? __('Noticias', 'estudio-de-soluciones-bloques-de-noticias-para-sendy'));
        $out['button'] = sanitize_text_field($in['button'] ?? __('Leer más', 'estudio-de-soluciones-bloques-de-noticias-para-sendy'));
        $out['images'] = !empty($in['images']); $out['separator'] = !empty($in['separator']);
        $out['source'] = ($in['source'] ?? '') === 'content' ? 'content' : 'excerpt';
        return $out;
    }

    public static function build(array $ids, array $input): array {
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids || count($ids) > self::MAX_POSTS) { throw new InvalidArgumentException(esc_html__('Selecciona entre 1 y 100 noticias.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
        $s = self::style($input); $rows = ''; $plain = $s['heading'] . "\n\n"; $warnings = [];
        $font = $s['font'] . ($s['font']==='Georgia' ? ',serif' : ',Helvetica,sans-serif');
        $inner = $s['width'] - 2 * $s['spacing'];
        foreach ($ids as $id) {
            $p = get_post($id);
            if (!$p || $p->post_type !== 'post' || $p->post_status !== 'publish' || $p->post_password) { throw new InvalidArgumentException(esc_html__('La selección contiene una noticia no pública. Actualiza el listado.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy')); }
            $title = html_entity_decode(get_the_title($p), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $t = esc_html($title); $url = get_permalink($p); $u = esc_url($url);
            $text = self::excerpt($s['source']==='excerpt' && $p->post_excerpt !== '' ? $p->post_excerpt : $p->post_content, $s['length']);
            $image = wp_get_attachment_image_url(get_post_thumbnail_id($p), 'large');
            $rows .= '<tr data-gjs-name="' . esc_attr($title) . '"><td style="padding:' . $s['spacing'] . 'px ' . $s['spacing'] . 'px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tbody>';
            $rows .= '<tr><td style="padding:0 0 20px;"><h2 style="margin:0;font-family:' . $font . ';font-size:' . $s['title_size'] . 'px;line-height:1.3;font-weight:normal;"><a href="' . $u . '" style="color:' . $s['title_color'] . ';text-decoration:none;">' . $t . '</a></h2></td></tr>';
            if ($s['images'] && $image) {
                $rows .= '<tr><td><a href="' . $u . '"><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '" width="' . $inner . '" style="display:block;width:100%;max-width:' . $inner . 'px;height:auto;border:0;"></a></td></tr>';
            } elseif ($s['images']) {
                /* translators: %s: title of the post without a featured image. */
                $warnings[] = sprintf(__('Sin imagen destacada: %s', 'estudio-de-soluciones-bloques-de-noticias-para-sendy'), $title); }
            if ($text !== '') { $rows .= '<tr><td style="padding:20px 0;font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;line-height:1.6;color:' . $s['text_color'] . ';"><p style="margin:0;">' . esc_html($text) . '</p></td></tr>'; }
            if ($s['button'] !== '') { $rows .= '<tr><td align="center" style="padding:16px 0 26px;"><a href="' . $u . '" style="display:inline-block;background-color:' . $s['accent'] . ';color:#ffffff;text-decoration:none;font-family:' . $font . ';font-size:' . $s['font_size'] . 'px;line-height:1.4;padding:12px 36px;border-radius:5px;">' . esc_html($s['button']) . '</a></td></tr>'; }
            if ($s['separator']) { $rows .= '<tr><td style="border-top:2px solid #b3b3b3;height:12px;font-size:1px;">&nbsp;</td></tr>'; }
            $rows .= '</tbody></table></td></tr>';
            $plain .= $title . "\n\n" . $text . "\n" . $url . "\n\n";
        }
        $heading = $s['heading'] === '' ? '' : '<tr><td align="center" style="padding:' . $s['spacing'] . 'px;font-family:' . $font . ';"><h1 style="margin:0;color:' . $s['accent'] . ';font-size:28px;">' . esc_html($s['heading']) . '</h1></td></tr>';
        $table = '<table role="presentation" align="center" width="' . $s['width'] . '" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:' . $s['width'] . 'px;background-color:' . $s['card'] . ';"><tbody>';
        $fragment = $table . $heading . $rows . '</tbody></table>';
        $footer = '<tr><td align="center" style="padding:24px;font-family:' . $font . ';font-size:12px;line-height:1.6;color:#666666;">' . esc_html(get_bloginfo('name')) . '<br><a href="[webversion]" style="color:#666666;">' . esc_html__('Ver en el navegador', 'estudio-de-soluciones-bloques-de-noticias-para-sendy') . '</a> · <a href="[unsubscribe]" style="color:#666666;">' . esc_html__('Darse de baja', 'estudio-de-soluciones-bloques-de-noticias-para-sendy') . '</a></td></tr>';
        $html = '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($s['heading']) . '</title></head><body style="margin:0;padding:0;background-color:' . $s['background'] . ';"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $s['background'] . ';"><tbody><tr><td align="center" style="padding:24px 0;">' . $table . $heading . $rows . $footer . '</tbody></table></td></tr></tbody></table></body></html>';
        if (strlen($html) > 95000) { $warnings[] = __('El HTML supera 95 KB: algunos clientes de correo podrían recortarlo. Reduce el número de noticias.', 'estudio-de-soluciones-bloques-de-noticias-para-sendy'); }
        return ['html'=>$html,'fragment'=>$fragment,'plain'=>$plain,'warnings'=>$warnings,'count'=>count($ids),'bytes'=>strlen($html)];
    }
}
