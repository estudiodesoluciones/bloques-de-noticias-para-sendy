<?php defined('ABSPATH') || exit; ?>
<div class="wrap es-sn">
    <h1>Estudio de Soluciones – Bloques de noticias para Sendy <span class="es-sn-badge">1.0.0</span></h1>
    <p>De tus noticias a tu próxima newsletter. Selecciona, revisa y termina de maquetar en Sendy.</p>
    <p class="es-sn-credit">Creado por <a href="https://estudiodesoluciones.com" target="_blank" rel="noopener noreferrer">Estudio de Soluciones</a>.</p>
    <div id="es-sn-status" role="status" aria-live="polite"></div>
    <details class="es-sn-panel" <?php echo empty($settings['url']) ? 'open' : ''; ?>>
        <summary><strong>Configuración de Sendy</strong> <span id="es-sn-key-status"><?php echo ES_SN_Settings::key() ? 'Clave API configurada' : 'Sin clave API'; ?></span></summary>
        <p>Funciona con Sendy en este servidor o en otro mediante su URL HTTPS pública y API. No necesitas acceso a sus archivos.</p>
        <form id="es-sn-settings" class="es-sn-grid">
            <label class="es-sn-wide">URL de la instalación<input type="url" name="url" placeholder="https://newsletter.ejemplo.com/sendy" value="<?php echo esc_attr($settings['url']); ?>"><small>Usa la URL final, sin redirecciones. Déjala vacía para trabajar solo con HTML.</small></label>
            <label>Clave API<input type="password" name="api_key" autocomplete="new-password" <?php disabled(defined('ES_SN_API_KEY')); ?> placeholder="Dejar vacío para conservar"><small><?php echo defined('ES_SN_API_KEY') ? 'Definida en wp-config.php.' : 'Disponible en Settings de Sendy. No se muestra la clave guardada.'; ?></small></label>
            <label>ID de marca<input type="number" name="brand_id" min="1" step="1" value="<?php echo $settings['brand_id'] ? esc_attr($settings['brand_id']) : ''; ?>"><small>Brand ID, no el ID de una lista. «Probar conexión» muestra las marcas disponibles.</small></label>
            <label>Nombre del remitente<input name="from_name" maxlength="100" value="<?php echo esc_attr($settings['from_name']); ?>"></label>
            <label>Correo del remitente<input type="email" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>"><small>Debe estar autorizado en tu configuración de envío de Sendy.</small></label>
            <label>Responder a<input type="email" name="reply_to" value="<?php echo esc_attr($settings['reply_to']); ?>"></label>
            <label class="es-sn-check"><input type="checkbox" name="clear_key" value="1"> Borrar la clave guardada al guardar</label>
            <div class="es-sn-wide es-sn-actions"><button class="button button-primary">Guardar configuración</button><button type="button" class="button" id="es-sn-test">Probar conexión guardada</button></div>
        </form>
        <div id="es-sn-brands" aria-live="polite"></div>
    </details>
    <div class="es-sn-columns">
        <section class="es-sn-panel">
            <h2>1. Selecciona las noticias</h2>
            <form id="es-sn-filter" class="es-sn-grid">
                <label>Últimas X noticias<input type="number" name="limit" value="10" min="1" max="100" required><small>Entre 1 y 100, dentro de los filtros. Solo entradas públicas sin contraseña.</small></label>
                <label>Buscar texto<input type="search" name="search" placeholder="Título o contenido"></label>
                <label>Desde<input type="date" name="after"></label>
                <label>Hasta<input type="date" name="before"></label>
                <label class="es-sn-wide">Categorías<select name="categories[]" multiple size="5" aria-describedby="es-sn-cat-help">
                    <?php foreach (get_categories(['hide_empty'=>true]) as $es_sn_category): ?>
                    <option value="<?php echo (int) $es_sn_category->term_id; ?>"><?php echo esc_html($es_sn_category->name); ?></option>
                    <?php endforeach; ?>
                </select><small id="es-sn-cat-help">Sin selección: todas. Con varias: noticias de cualquiera de ellas. Usa Cmd/Ctrl para seleccionar varias.</small></label>
                <div class="es-sn-wide es-sn-actions"><button class="button button-primary">Buscar noticias</button><button type="reset" class="button">Restablecer filtros</button></div>
            </form>
            <p id="es-sn-count"></p>
            <div class="es-sn-actions"><button type="button" class="button" id="es-sn-all">Seleccionar todo el listado</button><button type="button" class="button" id="es-sn-none">Desmarcar todas</button></div>
            <p class="description">El orden de este listado será el orden del correo. Usa las flechas para cambiarlo.</p>
            <ol id="es-sn-posts" class="es-sn-posts"></ol>
        </section>
        <section class="es-sn-panel">
            <h2>2. Ajusta el diseño</h2>
            <form id="es-sn-design" class="es-sn-grid">
                <label>Encabezado del listado<input name="heading" value="Noticias" maxlength="200"></label>
                <label>Caracteres del resumen<input type="number" name="length" value="350" min="0" max="2000" required><small>0 para ocultarlo. Incluye la elipsis y respeta palabras cuando es posible.</small></label>
                <label class="es-sn-wide">Origen del resumen<select name="source"><option value="excerpt">Extracto de WordPress; si está vacío, contenido</option><option value="content">Siempre el comienzo del contenido</option></select></label>
                <label>Texto del botón<input name="button" value="Leer más" maxlength="80"><small>Vacío para ocultar el botón.</small></label>
                <label>Tipografía<select name="font"><option>Arial</option><option>Georgia</option><option>Verdana</option></select></label>
                <label>Ancho máximo (px)<input type="number" name="width" value="640" min="480" max="800" required></label>
                <label>Márgenes (px)<input type="number" name="spacing" value="24" min="8" max="48" required></label>
                <label>Tamaño del titular (px)<input type="number" name="title_size" value="25" min="18" max="36" required></label>
                <label>Tamaño del texto (px)<input type="number" name="font_size" value="18" min="12" max="24" required></label>
                <label>Botones y encabezado<input type="color" name="accent" value="#cc402c"></label>
                <label>Titulares<input type="color" name="title_color" value="#444444"></label>
                <label>Texto<input type="color" name="text_color" value="#171717"></label>
                <label>Fondo exterior<input type="color" name="background" value="#f5f5f5"></label>
                <label>Fondo del listado<input type="color" name="card" value="#ffffff"></label>
                <div><label class="es-sn-check"><input type="checkbox" name="images" value="1" checked> Imagen destacada</label><label class="es-sn-check"><input type="checkbox" name="separator" value="1" checked> Separador entre noticias</label></div>
                <div class="es-sn-wide es-sn-actions"><button class="button button-primary">Generar vista previa y HTML</button><button type="button" class="button" id="es-sn-save-design">Guardar diseño predeterminado</button></div>
            </form>
        </section>
    </div>
    <section class="es-sn-panel" id="es-sn-output" hidden>
        <h2>3. Revisa y pasa a Sendy</h2>
        <p id="es-sn-preview-info"></p><p id="es-sn-stale" hidden>Has cambiado el listado o el diseño. Genera de nuevo la vista previa antes de crear el borrador.</p>
        <ul id="es-sn-warnings"></ul>
        <div class="es-sn-actions"><button type="button" class="button" data-preview-width="100%">Vista escritorio</button><button type="button" class="button" data-preview-width="375px">Vista móvil</button></div>
        <div class="es-sn-preview-wrap"><iframe id="es-sn-preview" title="Vista previa del correo" sandbox="" referrerpolicy="no-referrer"></iframe></div>
        <form id="es-sn-send" class="es-sn-send"><label>Asunto y nombre del borrador<input name="subject" maxlength="200" required value="<?php echo esc_attr(get_bloginfo('name') . ' — ' . wp_date('d/m/Y')); ?>"></label><button class="button button-primary" id="es-sn-send-button">Crear borrador en Sendy</button></form>
        <p>Se crea solo un borrador, sin destinatarios ni programación. En Sendy, pulsa <strong>Arrastrar y soltar / Drag &amp; drop</strong> para continuar con el editor por bloques.</p>
        <div id="es-sn-result" role="status" aria-live="polite"></div>
        <details open><summary><strong>Copiar o descargar el HTML</strong></summary>
            <p>Siempre disponible, aunque Sendy no esté conectado. El listado sirve para insertarlo en una sección; el correo completo incluye enlaces de Sendy para ver en navegador y darse de baja.</p>
            <label>Contenido a copiar<select id="es-sn-export"><option value="fragment">Solo listado de noticias</option><option value="html">Correo completo para Sendy</option></select></label>
            <textarea id="es-sn-code" readonly rows="9" spellcheck="false" aria-label="Código HTML generado"></textarea>
            <div class="es-sn-actions"><button type="button" class="button" id="es-sn-copy">Copiar HTML</button><button type="button" class="button" id="es-sn-download">Descargar HTML</button></div>
            <p class="description">Pega el código en la opción de importar HTML del editor o en el editor HTML de Sendy, y después cambia a Arrastrar y soltar. Guarda una copia antes de reemplazar el contenido de una campaña existente.</p>
        </details>
    </section>
</div>
