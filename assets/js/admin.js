/* global wmPro, wp */
(function ($) {
    'use strict';

    // =========================================================================
    // State
    // =========================================================================
    var state = {
        // Images to process
        images:    [],      // [{id, url, thumb, title}, …]

        // Image watermark
        imageWmEnabled: true,
        watermark: null,    // {id, url, title, isEps}
        position:  'bottom-right',
        offsetX:   10,
        offsetY:   10,
        sizePct:   20,
        opacity:   80,

        // Text watermark
        textEnabled:    false,
        textContent:    '',
        textPosition:   'bottom-right',
        textAlign:      'center',
        textFontFamily: 'auto',
        textFontPath:   '',
        textFontSize:   36,
        textColor:      '#ffffff',
        textOpacity:    80,
        textOffsetX:    10,
        textOffsetY:    10,

        saveMode: 'new'
    };

    // =========================================================================
    // Canvas preview
    // =========================================================================
    var canvas      = document.getElementById('wm-canvas');
    var ctx         = canvas ? canvas.getContext('2d') : null;
    var placeholder = document.getElementById('wm-canvas-placeholder');
    var bgImg       = null;
    var wmImg       = null;

    function loadImg(url) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload  = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Failed: ' + url)); };
            img.src = url;
        });
    }

    function updatePreview() {
        if (!ctx) { return; }

        var hasVisual = state.images.length > 0 &&
                        ( (state.imageWmEnabled && state.watermark && !state.watermark.isEps)
                          || state.textEnabled );

        if (!state.images.length) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            if (placeholder) { placeholder.classList.remove('hidden'); }
            return;
        }

        if (placeholder) { placeholder.classList.add('hidden'); }

        var imgUrl = state.images[0].url;
        var wmUrl  = (state.imageWmEnabled && state.watermark && !state.watermark.isEps)
                     ? state.watermark.url : null;

        var promises = [];
        if (!bgImg || bgImg._src !== imgUrl) {
            promises.push(loadImg(imgUrl).then(function (i) { bgImg = i; bgImg._src = imgUrl; }));
        }
        if (wmUrl && (!wmImg || wmImg._src !== wmUrl)) {
            promises.push(loadImg(wmUrl).then(function (i) { wmImg = i; wmImg._src = wmUrl; }));
        }
        if (!wmUrl) { wmImg = null; }

        Promise.all(promises).then(drawAll).catch(drawFallback);
    }

    function drawAll() {
        if (!bgImg) { return; }

        var W = canvas.width;
        var H = canvas.height;

        // Fit background
        var scale = Math.min(W / bgImg.naturalWidth, H / bgImg.naturalHeight);
        var bw = bgImg.naturalWidth  * scale;
        var bh = bgImg.naturalHeight * scale;
        var bx = (W - bw) / 2;
        var by = (H - bh) / 2;

        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#1d2327';
        ctx.fillRect(0, 0, W, H);
        ctx.drawImage(bgImg, bx, by, bw, bh);

        // Image watermark
        if (wmImg && state.imageWmEnabled) {
            var ww = bw * (state.sizePct / 100);
            var wh = ww * (wmImg.naturalHeight / Math.max(1, wmImg.naturalWidth));
            var pos = calcImgWmPos(bx, by, bw, bh, ww, wh);
            ctx.save();
            ctx.globalAlpha = state.opacity / 100;
            ctx.drawImage(wmImg, pos.x, pos.y, ww, wh);
            ctx.restore();
        }

        // EPS placeholder
        if (state.imageWmEnabled && state.watermark && state.watermark.isEps) {
            drawEpsPlaceholder(bx, by, bw, bh);
        }

        // Text watermark
        if (state.textEnabled && state.textContent) {
            drawTextWatermark(bx, by, bw, bh);
        }
    }

    function calcImgWmPos(bx, by, bw, bh, ww, wh) {
        var pos = state.position;
        var sc  = bgImg ? bw / bgImg.naturalWidth : 1;
        var ox  = state.offsetX * sc;
        var oy  = state.offsetY * sc;
        var x   = pos.indexOf('right')  !== -1 ? bx + bw - ww - ox
                : pos.indexOf('left')   !== -1 ? bx + ox
                : bx + (bw - ww) / 2;
        var y   = pos.indexOf('bottom') !== -1 ? by + bh - wh - oy
                : pos.indexOf('top')    !== -1 ? by + oy
                : by + (bh - wh) / 2;
        return { x: x, y: y };
    }

    function drawEpsPlaceholder(bx, by, bw, bh) {
        ctx.save();
        ctx.globalAlpha = 0.6;
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 13px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('EPS – Vorschau n. verfügbar', bx + bw / 2, by + bh / 2);
        ctx.restore();
    }

    // ---- Text watermark canvas rendering ----

    function hexToRgba(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha.toFixed(2) + ')';
    }

    function canvasFontFamily() {
        var map = {
            'auto':              'Arial, sans-serif',
            'dejavu':            '"DejaVu Sans", Arial, sans-serif',
            'liberation':        'Arial, "Liberation Sans", sans-serif',
            'liberation-serif':  'Georgia, "Liberation Serif", serif',
            'liberation-mono':   '"Courier New", "Liberation Mono", monospace',
            'freefont':          'Arial, sans-serif',
            'custom':            'Arial, sans-serif'
        };
        return map[state.textFontFamily] || 'Arial, sans-serif';
    }

    function drawTextWatermark(bx, by, bw, bh) {
        if (!bgImg) { return; }

        var sc       = bw / bgImg.naturalWidth;
        var fontSize = Math.max(8, Math.round(state.textFontSize * sc));
        var text     = state.textContent;
        var color    = hexToRgba(state.textColor, state.textOpacity / 100);
        var pos      = state.textPosition;
        var align    = state.textAlign;
        var ox       = state.textOffsetX * sc;
        var oy       = state.textOffsetY * sc;

        ctx.save();
        ctx.font         = 'bold ' + fontSize + 'px ' + canvasFontFamily();
        ctx.fillStyle    = color;
        ctx.textBaseline = 'alphabetic';

        var m     = ctx.measureText(text);
        var textW = m.width;
        var asc   = m.actualBoundingBoxAscent  || fontSize * 0.8;
        var desc  = m.actualBoundingBoxDescent || fontSize * 0.2;
        var textH = asc + desc;

        var isEdgeLeft  = (pos === 'edge-left');
        var isEdgeRight = (pos === 'edge-right');

        if (isEdgeLeft) {
            // Rotate 90° CCW around draw origin
            // "align" controls along-edge position:
            //   left=near-top, center=middle, right=near-bottom
            var edgeY;
            if      (align === 'left')   { edgeY = by + oy + textW; }
            else if (align === 'right')  { edgeY = by + bh - oy;    }
            else                         { edgeY = by + (bh + textW) / 2; }

            var edgeX = bx + ox + textH;

            ctx.translate(edgeX, edgeY);
            ctx.rotate(-Math.PI / 2);
            ctx.textAlign = 'left';
            ctx.fillText(text, 0, 0);

        } else if (isEdgeRight) {
            // Rotate 90° CW
            var edgeY2;
            if      (align === 'left')   { edgeY2 = by + oy; }
            else if (align === 'right')  { edgeY2 = by + bh - oy - textW; }
            else                         { edgeY2 = by + (bh - textW) / 2; }

            var edgeX2 = bx + bw - ox - textH;

            ctx.translate(edgeX2, edgeY2);
            ctx.rotate(Math.PI / 2);
            ctx.textAlign = 'left';
            ctx.fillText(text, 0, 0);

        } else {
            // Horizontal (grid positions + edge-top / edge-bottom)
            var isEdgeH = (pos === 'edge-top' || pos === 'edge-bottom');

            // Horizontal position
            var drawX;
            if (isEdgeH || pos.indexOf('center') !== -1 || pos === 'middle-center') {
                ctx.textAlign = align === 'right' ? 'right'
                              : align === 'left'  ? 'left' : 'center';
                drawX = align === 'right' ? bx + bw - ox
                      : align === 'left'  ? bx + ox
                      : bx + bw / 2;
            } else if (pos.indexOf('right') !== -1) {
                ctx.textAlign = 'right';
                drawX = bx + bw - ox;
            } else if (pos.indexOf('left') !== -1) {
                ctx.textAlign = 'left';
                drawX = bx + ox;
            } else {
                ctx.textAlign = 'center';
                drawX = bx + bw / 2;
            }

            // Vertical position (top of glyph block → baseline)
            var topY;
            if (pos.indexOf('top') !== -1 || pos === 'edge-top') {
                topY = by + oy;
            } else if (pos.indexOf('bottom') !== -1 || pos === 'edge-bottom') {
                topY = by + bh - oy - textH;
            } else {
                topY = by + (bh - textH) / 2;
            }
            var drawY = topY + asc; // baseline

            ctx.fillText(text, drawX, drawY);
        }

        ctx.restore();
    }

    function drawFallback() {
        if (!ctx) { return; }
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#2c3338';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#8c8f94';
        ctx.font = '13px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Vorschau nicht verfügbar', canvas.width / 2, canvas.height / 2);
    }

    // =========================================================================
    // Media library frames
    // =========================================================================
    var imgFrame = null;
    var wmFrame  = null;

    $('#wm-btn-select-images').on('click', function () {
        if (!imgFrame) {
            imgFrame = wp.media({
                title:    wmPro.i18n.selectImages,
                button:   { text: wmPro.i18n.useSelected },
                library:  { type: 'image' },
                multiple: true
            });
            imgFrame.on('select', function () {
                state.images = [];
                imgFrame.state().get('selection').each(function (a) {
                    var d = a.toJSON();
                    state.images.push({
                        id:    d.id,
                        url:   d.url,
                        thumb: (d.sizes && d.sizes.medium) ? d.sizes.medium.url
                             : (d.sizes && d.sizes.thumbnail) ? d.sizes.thumbnail.url : d.url,
                        title: d.title || d.filename
                    });
                });
                bgImg = null;
                renderImageThumbs();
                updatePreview();
            });
        }
        imgFrame.open();
    });

    $('#wm-btn-select-wm').on('click', function () {
        if (!wmFrame) {
            wmFrame = wp.media({
                title:    wmPro.i18n.selectWatermark,
                button:   { text: wmPro.i18n.useSelected },
                multiple: false
            });
            wmFrame.on('select', function () {
                var d   = wmFrame.state().get('selection').first().toJSON();
                var ext = ((d.filename || d.url || '').split('.').pop() || '').toLowerCase();
                var isEps = (ext === 'eps');
                if (isEps && wmPro.hasImagick !== '1') {
                    alert('EPS-Wasserzeichen benötigen Imagick + Ghostscript auf dem Server.');
                    return;
                }
                state.watermark = { id: d.id, url: d.url, title: d.title || d.filename, isEps: isEps };
                wmImg = null;
                renderWatermarkThumb();
                updatePreview();
            });
        }
        wmFrame.open();
    });

    function renderImageThumbs() {
        var $grid = $('#wm-images-preview').empty();
        $.each(state.images, function (idx, img) {
            (function (i) {
                var $item = $('<div class="wm-thumb-item">').appendTo($grid);
                $('<img>').attr({ src: img.thumb, alt: img.title, title: img.title }).appendTo($item);
                $('<button type="button" class="wm-remove-img" title="Entfernen">×</button>')
                    .on('click', function () {
                        state.images.splice(i, 1);
                        if (i === 0) { bgImg = null; }
                        renderImageThumbs();
                        updatePreview();
                    }).appendTo($item);
            }(idx));
        });
    }

    function renderWatermarkThumb() {
        var $wrap = $('#wm-watermark-preview').empty();
        if (state.watermark) {
            $('<img>').attr({ src: state.watermark.url, alt: state.watermark.title }).appendTo($wrap);
            $('<p>').text(state.watermark.title).appendTo($wrap);
        }
    }

    // =========================================================================
    // Image watermark controls
    // =========================================================================

    $('#wm-image-wm-enabled').on('change', function () {
        state.imageWmEnabled = this.checked;
        $('#wm-image-wm-settings, #wm-image-wm-controls').toggle(this.checked);
        updatePreview();
    });

    $(document).on('click', '.wm-pos-btn', function () {
        $('.wm-pos-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        state.position = $(this).data('pos');
        $('#wm-position').val(state.position);
        updatePreview();
    });

    $('#wm-size').on('input', function () {
        state.sizePct = +this.value;
        $('#wm-size-val').text(state.sizePct);
        updatePreview();
    });

    $('#wm-opacity').on('input', function () {
        state.opacity = +this.value;
        $('#wm-opacity-val').text(state.opacity);
        updatePreview();
    });

    $('#wm-offset-x, #wm-offset-y').on('input', function () {
        state.offsetX = +$('#wm-offset-x').val();
        state.offsetY = +$('#wm-offset-y').val();
        updatePreview();
    });

    $('#wm-save-mode').on('change', function () { state.saveMode = this.value; });

    // =========================================================================
    // Text watermark controls
    // =========================================================================

    // Populate font family select from server data
    (function () {
        var $sel = $('#wm-text-font-family').empty();
        $.each(wmPro.fonts, function (key, label) {
            $sel.append($('<option>').val(key).text(label));
        });
    }());

    $('#wm-text-enabled').on('change', function () {
        state.textEnabled = this.checked;
        $('#wm-text-settings').toggle(this.checked);
        updatePreview();
    });

    $('#wm-text-content').on('input', function () {
        state.textContent = this.value;
        updatePreview();
    });

    $('#wm-text-position').on('change', function () {
        state.textPosition = this.value;
        updatePreview();
    });

    // Alignment buttons
    $(document).on('click', '.wm-align-btn', function () {
        $('.wm-align-btn').removeClass('active');
        $(this).addClass('active');
        state.textAlign = $(this).data('align');
        $('#wm-text-align').val(state.textAlign);
        updatePreview();
    });

    $('#wm-text-font-family').on('change', function () {
        state.textFontFamily = this.value;
        $('#wm-text-font-custom-wrap').toggle(this.value === 'custom');
        updatePreview();
    });

    $('#wm-text-font-path').on('input', function () {
        state.textFontPath = this.value;
        updatePreview();
    });

    $('#wm-text-size').on('input', function () {
        state.textFontSize = +this.value;
        $('#wm-text-size-val').text(this.value);
        updatePreview();
    });

    $('#wm-text-color').on('input', function () {
        state.textColor = this.value;
        updatePreview();
    });

    $('#wm-text-opacity').on('input', function () {
        state.textOpacity = +this.value;
        $('#wm-text-opacity-val').text(this.value);
        updatePreview();
    });

    $('#wm-text-offset-x, #wm-text-offset-y').on('input', function () {
        state.textOffsetX = +$('#wm-text-offset-x').val();
        state.textOffsetY = +$('#wm-text-offset-y').val();
        updatePreview();
    });

    // =========================================================================
    // Templates
    // =========================================================================

    function loadTemplates(callback) {
        $.post(wmPro.ajaxUrl, { action: 'wm_get_templates', nonce: wmPro.nonce }, function (res) {
            if (res.success) {
                populateTemplateSelect(res.data.templates);
                if (typeof callback === 'function') { callback(res.data.templates); }
            }
        });
    }

    function populateTemplateSelect(templates) {
        var $sel = $('#wm-template-select').empty().append('<option value="">– Vorlage wählen –</option>');
        $.each(templates, function (i, t) {
            $sel.append($('<option>').val(t.id).text(t.name));
        });
    }

    $('#wm-btn-load-template').on('click', function () {
        var id = $('#wm-template-select').val();
        if (!id) { return; }

        $.post(wmPro.ajaxUrl, { action: 'wm_get_template', nonce: wmPro.nonce, id: id }, function (res) {
            if (!res.success) { return; }
            var t = res.data;

            // Image watermark settings
            state.position = t.position;
            state.offsetX  = +t.offset_x;
            state.offsetY  = +t.offset_y;
            state.sizePct  = +t.size_pct;
            state.opacity  = +t.opacity;

            $('#wm-offset-x').val(state.offsetX);
            $('#wm-offset-y').val(state.offsetY);
            $('#wm-size').val(state.sizePct);      $('#wm-size-val').text(state.sizePct);
            $('#wm-opacity').val(state.opacity);   $('#wm-opacity-val').text(state.opacity);
            $('#wm-position').val(state.position);
            $('.wm-pos-btn').removeClass('active').attr('aria-pressed', 'false');
            $('.wm-pos-btn[data-pos="' + state.position + '"]').addClass('active').attr('aria-pressed', 'true');

            if (t.wm_id && t.wm_url) {
                state.watermark = { id: +t.wm_id, url: t.wm_url, title: t.wm_title || '', isEps: false };
                wmImg = null;
                renderWatermarkThumb();
            }

            // Text watermark settings
            state.textEnabled    = !!+t.text_enabled;
            state.textContent    = t.text_content    || '';
            state.textPosition   = t.text_position   || 'bottom-right';
            state.textAlign      = t.text_align      || 'center';
            state.textFontFamily = t.text_font_family|| 'auto';
            state.textFontPath   = t.text_font_path  || '';
            state.textFontSize   = +t.text_font_size  || 36;
            state.textColor      = t.text_color      || '#ffffff';
            state.textOpacity    = +t.text_opacity    || 80;
            state.textOffsetX    = +t.text_offset_x   || 10;
            state.textOffsetY    = +t.text_offset_y   || 10;

            $('#wm-text-enabled').prop('checked', state.textEnabled);
            $('#wm-text-settings').toggle(state.textEnabled);
            $('#wm-text-content').val(state.textContent);
            $('#wm-text-position').val(state.textPosition);
            $('#wm-text-font-family').val(state.textFontFamily);
            $('#wm-text-font-custom-wrap').toggle(state.textFontFamily === 'custom');
            $('#wm-text-font-path').val(state.textFontPath);
            $('#wm-text-size').val(state.textFontSize);   $('#wm-text-size-val').text(state.textFontSize);
            $('#wm-text-color').val(state.textColor);
            $('#wm-text-opacity').val(state.textOpacity); $('#wm-text-opacity-val').text(state.textOpacity);
            $('#wm-text-offset-x').val(state.textOffsetX);
            $('#wm-text-offset-y').val(state.textOffsetY);
            $('.wm-align-btn').removeClass('active');
            $('.wm-align-btn[data-align="' + state.textAlign + '"]').addClass('active');

            updatePreview();
            showInlineMsg('wm-tpl-load-msg', wmPro.i18n.tplLoaded, 'success');
        });
    });

    $('#wm-btn-save-tpl').on('click', function () {
        var name = $('#wm-tpl-name').val().trim();
        if (!name) { showInlineMsg('wm-tpl-save-msg', wmPro.i18n.noTemplateName, 'error'); return; }

        var $btn = $(this).prop('disabled', true);
        var data = buildTemplatePayload();
        data.action = 'wm_save_template';
        data.name   = name;
        data.id     = 0;

        $.post(wmPro.ajaxUrl, data, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                populateTemplateSelect(res.data.templates);
                $('#wm-tpl-name').val('');
                showInlineMsg('wm-tpl-save-msg', wmPro.i18n.tplSaved, 'success');
            } else {
                showInlineMsg('wm-tpl-save-msg', (res.data && res.data.message) || 'Fehler', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            showInlineMsg('wm-tpl-save-msg', 'Serverfehler', 'error');
        });
    });

    function buildTemplatePayload() {
        return {
            nonce:           wmPro.nonce,
            wm_id:           state.watermark ? state.watermark.id : 0,
            position:        state.position,
            offset_x:        state.offsetX,
            offset_y:        state.offsetY,
            size_pct:        state.sizePct,
            opacity:         state.opacity,
            text_enabled:    state.textEnabled ? 1 : 0,
            text_content:    state.textContent,
            text_position:   state.textPosition,
            text_align:      state.textAlign,
            text_font_family:state.textFontFamily,
            text_font_path:  state.textFontPath,
            text_font_size:  state.textFontSize,
            text_color:      state.textColor,
            text_opacity:    state.textOpacity,
            text_offset_x:   state.textOffsetX,
            text_offset_y:   state.textOffsetY
        };
    }

    // =========================================================================
    // Apply watermark (batch)
    // =========================================================================

    $('#wm-btn-apply').on('click', function () {
        if (!state.images.length) { alert(wmPro.i18n.noImages); return; }
        if (!state.imageWmEnabled && !state.textEnabled) {
            alert(wmPro.i18n.noWatermark); return;
        }
        if (state.imageWmEnabled && !state.watermark) {
            alert('Bitte ein Bild-Wasserzeichen auswählen oder Bild-Wasserzeichen deaktivieren.');
            return;
        }

        var $btn    = $(this).prop('disabled', true);
        var $wrap   = $('#wm-progress-wrap').show();
        var $bar    = $('#wm-progress-bar');
        var $text   = $('#wm-progress-text');
        var $result = $('#wm-apply-result').empty();
        var total   = state.images.length;
        var done    = 0;
        var results = [];

        function processNext(index) {
            if (index >= total) {
                $wrap.hide();
                $btn.prop('disabled', false);
                renderResults(results);
                return;
            }
            var img = state.images[index];
            $text.text(wmPro.i18n.processing + ' ' + (index + 1) + ' / ' + total + ': ' + img.title);
            $bar.css('width', Math.round((index / total) * 100) + '%');

            var data = $.extend({
                action:           'wm_apply',
                nonce:            wmPro.nonce,
                image_id:         img.id,
                wm_id:            (state.imageWmEnabled && state.watermark) ? state.watermark.id : 0,
                image_wm_enabled: state.imageWmEnabled ? 1 : 0,
                save_mode:        state.saveMode
            }, buildTemplatePayload());
            // override nonce (already included) and add image-specific fields
            delete data.name;

            $.post(wmPro.ajaxUrl, data, function (res) {
                results.push(res.success
                    ? { ok: true,  title: img.title, data: res.data }
                    : { ok: false, title: img.title, message: (res.data && res.data.message) || wmPro.i18n.errorApply }
                );
                done++;
                $bar.css('width', Math.round((done / total) * 100) + '%');
                processNext(index + 1);
            }).fail(function () {
                results.push({ ok: false, title: img.title, message: wmPro.i18n.errorApply });
                done++;
                processNext(index + 1);
            });
        }
        processNext(0);
    });

    function renderResults(results) {
        var $res      = $('#wm-apply-result').empty();
        var successes = $.grep(results, function (r) { return  r.ok; });
        var errors    = $.grep(results, function (r) { return !r.ok; });

        if (successes.length) {
            var $ok = $('<div class="wm-success">').appendTo($res);
            $ok.append('✓ ' + successes.length + ' Bild' + (successes.length > 1 ? 'er' : '') + ' bearbeitet. ');
            $.each(successes, function (i, r) {
                if (r.data && r.data.view_url) {
                    $('<a target="_blank" rel="noopener noreferrer">').attr('href', r.data.view_url).text(r.title).appendTo($ok);
                    $ok.append(' ');
                }
            });
        }
        if (errors.length) {
            var $err = $('<div class="wm-error">').appendTo($res);
            $err.append('✗ ' + errors.length + ' Fehler:');
            var $ul = $('<ul>').appendTo($err);
            $.each(errors, function (i, r) { $('<li>').text(r.title + ': ' + r.message).appendTo($ul); });
        }
    }

    // =========================================================================
    // Templates tab – table view
    // =========================================================================

    function renderTemplatesTable(templates) {
        var $wrap = $('#wm-templates-table-wrap').empty();
        if (!templates || !templates.length) {
            $wrap.append('<p class="wm-no-templates">' + wmPro.i18n.noTemplates + '</p>');
            return;
        }

        var $table = $('<table class="wm-templates-table widefat striped">').appendTo($wrap);
        $table.append(
            '<thead><tr>' +
            '<th>Bild-WZ</th><th>Name</th><th>Position</th><th>Größe</th><th>Deckkraft</th>' +
            '<th>Text-WZ</th><th>Aktionen</th>' +
            '</tr></thead>'
        );
        var $body = $('<tbody>').appendTo($table);

        $.each(templates, function (i, t) {
            var $row = $('<tr>').appendTo($body);

            // Watermark thumb
            $('<td>').append(
                t.wm_thumb ? $('<img class="wm-tpl-thumb">').attr({ src: t.wm_thumb, alt: t.wm_title || '' }) : '–'
            ).appendTo($row);

            $('<td>').text(t.name).appendTo($row);
            $('<td>').append($('<span class="wm-badge">').text(t.position)).appendTo($row);
            $('<td>').text(t.size_pct + '%').appendTo($row);
            $('<td>').text(t.opacity + '%').appendTo($row);

            // Text watermark info
            var $textCell = $('<td>').appendTo($row);
            if (+t.text_enabled && t.text_content) {
                $('<span class="wm-text-badge">').attr('title', t.text_content).text(t.text_content).appendTo($textCell);
            } else {
                $textCell.text('–');
            }

            // Actions
            var $actions = $('<td class="wm-tpl-actions">').appendTo($row);
            $('<button type="button" class="button button-small">Laden & Anwenden</button>')
                .on('click', function () {
                    activateTab('apply');
                    $('#wm-template-select').val(t.id);
                    $('#wm-btn-load-template').trigger('click');
                }).appendTo($actions);

            $('<button type="button" class="button button-small" style="color:#d63638;border-color:#d63638">Löschen</button>')
                .on('click', function () {
                    if (!window.confirm(wmPro.i18n.confirmDelete)) { return; }
                    $.post(wmPro.ajaxUrl, { action: 'wm_delete_template', nonce: wmPro.nonce, id: t.id }, function (res) {
                        if (res.success) {
                            populateTemplateSelect(res.data.templates);
                            renderTemplatesTable(res.data.templates);
                        }
                    });
                }).appendTo($actions);
        });
    }

    // =========================================================================
    // Tabs
    // =========================================================================

    function activateTab(tab) {
        $('.wm-tab-btn').removeClass('active').attr('aria-selected', 'false')
            .filter('[data-tab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');
        $('.wm-tab-panel').removeClass('active').filter('#wm-tab-' + tab).addClass('active');
        if (tab === 'templates') { loadTemplates(renderTemplatesTable); }
    }

    $(document).on('click', '.wm-tab-btn', function () { activateTab($(this).data('tab')); });

    // =========================================================================
    // Helpers
    // =========================================================================

    function showInlineMsg(id, text, type) {
        var $el = $('#' + id).text(text).removeClass('success error').addClass(type);
        setTimeout(function () { $el.text('').removeClass('success error'); }, 3500);
    }

    // =========================================================================
    // Init
    // =========================================================================
    loadTemplates();

}(jQuery));
