/* Bloques de noticias para Sendy — Estudio de Soluciones. No third-party scripts. */
(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    let preview = null, stale = true, creating = false, revision = 0;
    const status = (message, kind = '') => {
        $('es-sn-status').className = 'es-sn-notice ' + kind;
        $('es-sn-status').textContent = message;
    };
    const formData = form => new FormData(form);
    async function request(operation, data = new FormData()) {
        data.set('action', 'es_sn'); data.set('nonce', esSn.nonce); data.set('operation', operation);
        const r = await fetch(esSn.url, {method:'POST', body:data, credentials:'same-origin'});
        let body;
        try { body = await r.json(); } catch (e) { throw new Error('No se pudo interpretar la respuesta. Si estabas creando un borrador, comprueba Sendy antes de repetir.'); }
        if (!r.ok || !body.success) throw new Error(body.data?.message || 'La sesión puede haber caducado. Recarga la página.');
        return body.data;
    }
    async function busy(button, fn) {
        button.disabled = true;
        try { await fn(); } catch (e) { status(e.message, 'error'); }
        finally { button.disabled = false; }
    }
    function invalidate() {
        revision++;
        stale = true;
        if (preview) { $('es-sn-stale').hidden = false; $('es-sn-send-button').disabled = true; }
    }
    function selectedCount() {
        const checked = $('es-sn-posts').querySelectorAll('input:checked').length;
        $('es-sn-count').textContent = $('es-sn-count').dataset.base + ' · ' + checked + ' seleccionadas.';
    }
    $('es-sn-settings').addEventListener('submit', e => {
        e.preventDefault(); busy(e.submitter, async () => {
            const r = await request('settings', formData(e.target));
            e.target.elements.api_key.value = ''; e.target.elements.clear_key.checked = false;
            $('es-sn-key-status').textContent = r.has_key ? 'Clave API configurada' : 'Sin clave API';
            status(r.message, 'success');
        });
    });
    $('es-sn-test').addEventListener('click', e => busy(e.target, async () => {
        const brands = await request('test');
        $('es-sn-brands').textContent = 'Marcas disponibles: ' + brands.map(b => b.name + ' (ID ' + b.id + ')').join(' · ');
        status('Conexión correcta. Usa el ID de la marca correspondiente y guarda la configuración.', 'success');
    }));
    $('es-sn-filter').addEventListener('submit', e => {
        e.preventDefault(); busy(e.submitter, async () => {
            const r = await request('search', formData(e.target));
            const list = $('es-sn-posts'); list.replaceChildren();
            for (const p of r.items) {
                const li = document.createElement('li'); li.className = 'es-sn-post';
                const label = document.createElement('label');
                const check = document.createElement('input'); check.type = 'checkbox'; check.value = p.id; check.checked = true;
                label.append(check);
                if (p.image) { const img = document.createElement('img'); img.src = p.image; img.alt = ''; img.loading = 'lazy'; label.append(img); }
                const title = document.createElement('span'); title.textContent = p.title;
                const meta = document.createElement('small'); meta.textContent = p.date + ' · ' + p.categories.join(', '); title.append(meta); label.append(title); li.append(label);
                const move = document.createElement('div'); move.className = 'es-sn-move';
                for (const [text, direction] of [['↑', -1], ['↓', 1]]) {
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.textContent = text;
                    button.setAttribute('aria-label', (direction < 0 ? 'Subir: ' : 'Bajar: ') + p.title);
                    button.addEventListener('click', () => {
                        if (direction < 0 && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
                        if (direction > 0 && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
                        invalidate(); button.focus();
                    }); move.append(button);
                }
                li.append(move); list.append(li);
            }
            $('es-sn-count').dataset.base = r.items.length + ' mostradas de ' + r.total + ' coincidentes (las más recientes primero)';
            selectedCount(); invalidate();
            status(r.items.length ? 'Listado actualizado. Puedes desmarcar noticias y cambiar su orden.' : 'No hay noticias con estos filtros.');
        });
    });
    $('es-sn-filter').addEventListener('change', invalidate);
    $('es-sn-filter').addEventListener('reset', () => { invalidate(); status('Filtros restablecidos. Pulsa Buscar noticias para actualizar el listado.'); });
    $('es-sn-posts').addEventListener('change', () => { selectedCount(); invalidate(); });
    for (const [id, checked] of [['es-sn-all', true], ['es-sn-none', false]]) $(id).addEventListener('click', () => {
        $('es-sn-posts').querySelectorAll('input').forEach(c => { c.checked = checked; }); selectedCount(); invalidate();
    });
    $('es-sn-design').addEventListener('input', invalidate);
    $('es-sn-design').addEventListener('submit', e => {
        e.preventDefault(); busy(e.submitter, async () => {
            const data = formData(e.target);
            const currentRevision = revision;
            $('es-sn-posts').querySelectorAll('input:checked').forEach(c => data.append('ids[]', c.value));
            preview = await request('preview', data); stale = revision !== currentRevision;
            $('es-sn-output').hidden = false; $('es-sn-stale').hidden = !stale;
            $('es-sn-preview').srcdoc = preview.html;
            $('es-sn-preview-info').textContent = preview.count + ' noticias · ' + Math.ceil(preview.bytes / 1024) + ' KB de HTML · Vista previa válida durante 1 hora.';
            $('es-sn-warnings').replaceChildren();
            preview.warnings.forEach(w => { const li = document.createElement('li'); li.textContent = w; $('es-sn-warnings').append(li); });
            $('es-sn-result').replaceChildren(); $('es-sn-send-button').disabled = stale;
            exportCode(); status('Vista previa preparada. Revisa el contenido antes de crear el borrador.', 'success');
            $('es-sn-output').scrollIntoView({behavior:'smooth', block:'start'});
        });
    });
    $('es-sn-save-design').addEventListener('click', e => busy(e.target, async () => {
        await request('design', formData($('es-sn-design'))); status('Diseño predeterminado guardado para esta web.', 'success');
    }));
    document.querySelectorAll('[data-preview-width]').forEach(button => button.addEventListener('click', () => { $('es-sn-preview').style.width = button.dataset.previewWidth; }));
    function exportCode() { if (preview) $('es-sn-code').value = preview[$('es-sn-export').value]; }
    $('es-sn-export').addEventListener('change', exportCode);
    $('es-sn-copy').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText($('es-sn-code').value); status('HTML copiado.', 'success'); }
        catch (e) { $('es-sn-code').focus(); $('es-sn-code').select(); status('Código seleccionado. Pulsa Cmd+C o Ctrl+C para copiarlo.'); }
    });
    $('es-sn-download').addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([$ ('es-sn-code').value], {type:'text/html;charset=utf-8'}));
        const a = document.createElement('a'); a.href = url; a.download = 'bloques-de-noticias-para-sendy-' + $('es-sn-export').value + '.html'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    $('es-sn-send').addEventListener('submit', async e => {
        e.preventDefault(); if (!preview || stale || creating) return;
        creating = true; let created = false; const button = $('es-sn-send-button'); button.disabled = true;
        try {
            const data = formData(e.target); data.set('token', preview.token);
            const r = await request('send', data);
            created = true;
            const link = document.createElement('a'); link.href = r.url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'Abrir borrador en Sendy'; link.className = 'button button-primary';
            $('es-sn-result').replaceChildren(document.createTextNode('Borrador creado. '), link);
            status('Borrador creado, sin envío ni programación. En Sendy pulsa Arrastrar y soltar para editar por bloques.', 'success');
        } catch (err) { status(err.message, 'error'); $('es-sn-result').textContent = err.message + ' El HTML se conserva debajo.'; }
        finally { creating = false; button.disabled = created || stale; }
    });
    // Los valores iniciales del diseño se imprimen como JSON seguro por WordPress.
    if (esSn.design) for (const [name, value] of Object.entries(esSn.design)) {
        const el = $('es-sn-design').elements.namedItem(name);
        if (el) { if (el.type === 'checkbox') el.checked = !!value; else el.value = value; }
    }
    $('es-sn-filter').requestSubmit($('es-sn-filter').querySelector('button'));
})();
