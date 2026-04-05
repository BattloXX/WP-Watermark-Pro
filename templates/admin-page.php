<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap wm-pro-wrap">

    <h1 class="wm-pro-title">
        <span class="dashicons dashicons-art" aria-hidden="true"></span>
        <?php esc_html_e( 'Watermark Pro', 'watermark-pro' ); ?>
    </h1>

    <div class="wm-tabs">

        <nav class="wm-tab-nav" role="tablist">
            <button class="wm-tab-btn active" data-tab="apply"     role="tab" aria-selected="true"><?php  esc_html_e( 'Wasserzeichen anwenden', 'watermark-pro' ); ?></button>
            <button class="wm-tab-btn"         data-tab="templates" role="tab" aria-selected="false"><?php esc_html_e( 'Vorlagen verwalten',     'watermark-pro' ); ?></button>
        </nav>

        <!-- ================================================================
             TAB: Apply
             ================================================================ -->
        <div class="wm-tab-panel active" id="wm-tab-apply" role="tabpanel">
            <div class="wm-layout">

                <!-- ── Column 1 · Selection ─────────────────────────────── -->
                <div class="wm-col wm-col-selection">

                    <!-- Bilder -->
                    <div class="wm-box">
                        <h3><?php esc_html_e( 'Bilder', 'watermark-pro' ); ?></h3>
                        <div id="wm-images-preview" class="wm-thumb-grid" aria-live="polite"></div>
                        <button id="wm-btn-select-images" class="button button-secondary wm-btn-select">
                            <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                            <?php esc_html_e( 'Bilder aus Mediathek wählen', 'watermark-pro' ); ?>
                        </button>
                        <p class="wm-hint"><?php esc_html_e( 'Mehrfachauswahl möglich (Strg/Cmd).', 'watermark-pro' ); ?></p>
                    </div>

                    <!-- Bild-Wasserzeichen -->
                    <div class="wm-box">
                        <h3>
                            <label class="wm-toggle-label">
                                <input type="checkbox" id="wm-image-wm-enabled" checked>
                                <?php esc_html_e( 'Bild-Wasserzeichen', 'watermark-pro' ); ?>
                            </label>
                        </h3>
                        <div id="wm-image-wm-settings">
                            <div id="wm-watermark-preview" class="wm-watermark-thumb" aria-live="polite"></div>
                            <button id="wm-btn-select-wm" class="button button-secondary wm-btn-select">
                                <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                                <?php esc_html_e( 'Wasserzeichen wählen', 'watermark-pro' ); ?>
                            </button>
                            <p class="wm-hint">PNG, JPG, WebP, GIF<?php if ( extension_loaded( 'imagick' ) ) : ?>, EPS<?php else : ?> <em style="color:#d63638">(EPS: Imagick fehlt)</em><?php endif; ?></p>
                        </div>
                    </div>

                </div><!-- .wm-col-selection -->

                <!-- ── Column 2 · Settings ──────────────────────────────── -->
                <div class="wm-col wm-col-settings">
                    <div class="wm-box">
                        <h3><?php esc_html_e( 'Einstellungen', 'watermark-pro' ); ?></h3>

                        <!-- Template loader -->
                        <div class="wm-field">
                            <label for="wm-template-select"><?php esc_html_e( 'Vorlage laden', 'watermark-pro' ); ?></label>
                            <div class="wm-tpl-row">
                                <select id="wm-template-select">
                                    <option value=""><?php esc_html_e( '– Vorlage wählen –', 'watermark-pro' ); ?></option>
                                </select>
                                <button id="wm-btn-load-template" class="button"><?php esc_html_e( 'Laden', 'watermark-pro' ); ?></button>
                            </div>
                            <div id="wm-tpl-load-msg" class="wm-inline-msg"></div>
                        </div>

                        <hr class="wm-divider">

                        <!-- ── Bild-Wasserzeichen block ──────────────────── -->
                        <div class="wm-section-block wm-section-image">
                            <div class="wm-section-header has-content">
                                <span class="wm-section-label"><?php esc_html_e( 'Bild-Wasserzeichen', 'watermark-pro' ); ?></span>
                            </div>
                            <div id="wm-image-wm-controls">

                                <div class="wm-field">
                                    <label><?php esc_html_e( 'Position', 'watermark-pro' ); ?></label>
                                    <div class="wm-position-grid" role="group">
                                        <?php
                                        $positions = [
                                            'top-left'      => [ '↖', 'Oben links'   ],
                                            'top-center'    => [ '↑', 'Oben mittig'  ],
                                            'top-right'     => [ '↗', 'Oben rechts'  ],
                                            'middle-left'   => [ '←', 'Mitte links'  ],
                                            'middle-center' => [ '●', 'Mitte'        ],
                                            'middle-right'  => [ '→', 'Mitte rechts' ],
                                            'bottom-left'   => [ '↙', 'Unten links'  ],
                                            'bottom-center' => [ '↓', 'Unten mittig' ],
                                            'bottom-right'  => [ '↘', 'Unten rechts' ],
                                        ];
                                        foreach ( $positions as $pos => [ $icon, $label ] ) :
                                            $active = 'bottom-right' === $pos ? ' active' : '';
                                        ?>
                                        <button type="button" class="wm-pos-btn<?php echo $active; ?>" data-pos="<?php echo esc_attr( $pos ); ?>" title="<?php echo esc_attr( $label ); ?>" aria-pressed="<?php echo $active ? 'true' : 'false'; ?>"><?php echo $icon; ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" id="wm-position" value="bottom-right">
                                </div>

                                <div class="wm-field">
                                    <label><?php esc_html_e( 'Abstand vom Rand', 'watermark-pro' ); ?></label>
                                    <div class="wm-row wm-offset-row">
                                        <div class="wm-offset-field">
                                            <span class="wm-offset-label">X</span>
                                            <input type="number" id="wm-offset-x" value="10" min="0" max="500" class="small-text">
                                            <span class="wm-unit">px</span>
                                        </div>
                                        <div class="wm-offset-field">
                                            <span class="wm-offset-label">Y</span>
                                            <input type="number" id="wm-offset-y" value="10" min="0" max="500" class="small-text">
                                            <span class="wm-unit">px</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="wm-field">
                                    <label for="wm-size"><?php esc_html_e( 'Größe:', 'watermark-pro' ); ?> <strong><span id="wm-size-val">20</span>%</strong> <span class="wm-hint-inline"><?php esc_html_e( 'der Bildbreite', 'watermark-pro' ); ?></span></label>
                                    <input type="range" id="wm-size" min="1" max="100" value="20" class="wm-slider">
                                </div>

                                <div class="wm-field">
                                    <label for="wm-opacity"><?php esc_html_e( 'Deckkraft:', 'watermark-pro' ); ?> <strong><span id="wm-opacity-val">80</span>%</strong></label>
                                    <input type="range" id="wm-opacity" min="10" max="100" value="80" class="wm-slider">
                                </div>

                            </div><!-- #wm-image-wm-controls -->
                        </div><!-- .wm-section-block.wm-section-image -->

                        <!-- ── Text-Wasserzeichen block ──────────────────── -->
                        <div class="wm-section-block wm-section-text">
                            <div class="wm-section-header" id="wm-text-section-header">
                                <label class="wm-toggle-label">
                                    <input type="checkbox" id="wm-text-enabled">
                                    <?php esc_html_e( 'Text-Wasserzeichen', 'watermark-pro' ); ?>
                                </label>
                                <?php if ( ! function_exists( 'imagettftext' ) ) : ?>
                                    <span class="wm-hint" style="color:#d63638;margin:0"><?php esc_html_e( 'FreeType nicht verfügbar.', 'watermark-pro' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div id="wm-no-font-warning" class="notice notice-warning inline" style="display:none;margin:8px 0 0;padding:6px 12px;font-size:12px"></div>

                            <div id="wm-text-settings" style="display:none;margin-top:12px;padding-top:10px;border-top:1px dashed #b8e6c1">

                                <div class="wm-field">
                                    <label for="wm-text-content"><?php esc_html_e( 'Text', 'watermark-pro' ); ?></label>
                                    <input type="text" id="wm-text-content" placeholder="© <?php echo esc_attr( date('Y') ); ?> Ihr Name" class="widefat">
                                </div>

                                <div class="wm-field">
                                    <label for="wm-text-position"><?php esc_html_e( 'Position', 'watermark-pro' ); ?></label>
                                    <select id="wm-text-position" class="widefat">
                                        <optgroup label="<?php esc_attr_e( 'Raster', 'watermark-pro' ); ?>">
                                            <option value="top-left"><?php      esc_html_e( 'Oben links',    'watermark-pro' ); ?></option>
                                            <option value="top-center"><?php    esc_html_e( 'Oben mittig',   'watermark-pro' ); ?></option>
                                            <option value="top-right"><?php     esc_html_e( 'Oben rechts',   'watermark-pro' ); ?></option>
                                            <option value="middle-left"><?php   esc_html_e( 'Mitte links',   'watermark-pro' ); ?></option>
                                            <option value="middle-center"><?php esc_html_e( 'Mitte',         'watermark-pro' ); ?></option>
                                            <option value="middle-right"><?php  esc_html_e( 'Mitte rechts',  'watermark-pro' ); ?></option>
                                            <option value="bottom-left"><?php   esc_html_e( 'Unten links',   'watermark-pro' ); ?></option>
                                            <option value="bottom-center"><?php esc_html_e( 'Unten mittig',  'watermark-pro' ); ?></option>
                                            <option value="bottom-right" selected><?php esc_html_e( 'Unten rechts', 'watermark-pro' ); ?></option>
                                        </optgroup>
                                        <optgroup label="<?php esc_attr_e( 'Bildrand', 'watermark-pro' ); ?>">
                                            <option value="edge-top"><?php    esc_html_e( 'Oberer Rand (horizontal)',  'watermark-pro' ); ?></option>
                                            <option value="edge-bottom"><?php esc_html_e( 'Unterer Rand (horizontal)', 'watermark-pro' ); ?></option>
                                            <option value="edge-left"><?php   esc_html_e( 'Linker Rand (↺ CCW)',       'watermark-pro' ); ?></option>
                                            <option value="edge-right"><?php  esc_html_e( 'Rechter Rand (↻ CW)',       'watermark-pro' ); ?></option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="wm-field">
                                    <label><?php esc_html_e( 'Ausrichtung', 'watermark-pro' ); ?></label>
                                    <div class="wm-align-btns" role="group">
                                        <button type="button" class="wm-align-btn"        data-align="left"   title="Links">&#8676;</button>
                                        <button type="button" class="wm-align-btn active" data-align="center" title="Mitte">&#8596;</button>
                                        <button type="button" class="wm-align-btn"        data-align="right"  title="Rechts">&#8677;</button>
                                    </div>
                                    <input type="hidden" id="wm-text-align" value="center">
                                </div>

                                <div class="wm-field">
                                    <label for="wm-text-font-family"><?php esc_html_e( 'Schriftart', 'watermark-pro' ); ?></label>
                                    <select id="wm-text-font-family" class="widefat">
                                        <!-- Populated by JS from wmPro.fonts -->
                                    </select>
                                    <div id="wm-text-font-custom-wrap" style="display:none;margin-top:6px">
                                        <input type="text" id="wm-text-font-path" class="widefat" placeholder="/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf">
                                        <p class="wm-hint"><?php esc_html_e( 'Absoluter Pfad zur TTF-Datei auf dem Server.', 'watermark-pro' ); ?></p>
                                    </div>
                                </div>

                                <div class="wm-field">
                                    <label for="wm-text-size"><?php esc_html_e( 'Schriftgröße:', 'watermark-pro' ); ?> <strong><span id="wm-text-size-val">36</span> px</strong></label>
                                    <input type="range" id="wm-text-size" min="12" max="200" value="36" class="wm-slider">
                                </div>

                                <div class="wm-field">
                                    <label><?php esc_html_e( 'Farbe &amp; Deckkraft', 'watermark-pro' ); ?></label>
                                    <div class="wm-row">
                                        <input type="color" id="wm-text-color" value="#ffffff" style="width:40px;height:32px;padding:2px;cursor:pointer;flex-shrink:0">
                                        <input type="range" id="wm-text-opacity" min="10" max="100" value="80" class="wm-slider" style="margin-top:0">
                                        <strong style="white-space:nowrap"><span id="wm-text-opacity-val">80</span>%</strong>
                                    </div>
                                </div>

                                <div class="wm-field">
                                    <label><?php esc_html_e( 'Abstand vom Rand', 'watermark-pro' ); ?></label>
                                    <div class="wm-row wm-offset-row">
                                        <div class="wm-offset-field">
                                            <span class="wm-offset-label">X</span>
                                            <input type="number" id="wm-text-offset-x" value="10" min="0" max="500" class="small-text">
                                            <span class="wm-unit">px</span>
                                        </div>
                                        <div class="wm-offset-field">
                                            <span class="wm-offset-label">Y</span>
                                            <input type="number" id="wm-text-offset-y" value="10" min="0" max="500" class="small-text">
                                            <span class="wm-unit">px</span>
                                        </div>
                                    </div>
                                </div>

                                <p class="wm-hint"><?php esc_html_e( 'Vorschau nutzt Systemschrift; Server verwendet die gewählte TTF-Schrift.', 'watermark-pro' ); ?></p>

                            </div><!-- #wm-text-settings -->
                        </div><!-- .wm-section-block.wm-section-text -->

                        <hr class="wm-divider">

                        <!-- Save mode -->
                        <div class="wm-field">
                            <label for="wm-save-mode"><?php esc_html_e( 'Ausgabe', 'watermark-pro' ); ?></label>
                            <select id="wm-save-mode" class="widefat">
                                <option value="new"><?php       esc_html_e( 'Neue Datei erstellen (Original behalten)', 'watermark-pro' ); ?></option>
                                <option value="overwrite"><?php esc_html_e( 'Original überschreiben',                   'watermark-pro' ); ?></option>
                            </select>
                        </div>

                        <hr class="wm-divider">

                        <!-- Save as template -->
                        <div class="wm-field">
                            <label for="wm-tpl-name"><?php esc_html_e( 'Als Vorlage speichern', 'watermark-pro' ); ?></label>
                            <div class="wm-tpl-row">
                                <input type="text" id="wm-tpl-name" placeholder="<?php esc_attr_e( 'Vorlagenname…', 'watermark-pro' ); ?>" style="flex:1">
                                <button id="wm-btn-save-tpl" class="button"><?php esc_html_e( 'Speichern', 'watermark-pro' ); ?></button>
                            </div>
                            <div id="wm-tpl-save-msg" class="wm-inline-msg"></div>
                        </div>

                    </div><!-- .wm-box settings -->
                </div><!-- .wm-col-settings -->

                <!-- ── Column 3 · Preview ───────────────────────────────── -->
                <div class="wm-col wm-col-preview">
                    <div class="wm-box">
                        <h3><?php esc_html_e( 'Vorschau', 'watermark-pro' ); ?></h3>
                        <div class="wm-canvas-wrap">
                            <canvas id="wm-canvas" width="520" height="390"></canvas>
                            <div id="wm-canvas-placeholder" class="wm-canvas-placeholder">
                                <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                <p><?php esc_html_e( 'Bitte Bild auswählen', 'watermark-pro' ); ?></p>
                            </div>
                        </div>
                        <p class="wm-hint" style="margin-top:6px"><?php esc_html_e( 'Erstes gewähltes Bild wird angezeigt.', 'watermark-pro' ); ?></p>
                    </div>
                </div>

            </div><!-- .wm-layout -->

            <!-- Apply bar -->
            <div class="wm-apply-bar">
                <button id="wm-btn-apply" class="button button-primary button-hero">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <?php esc_html_e( 'Wasserzeichen anwenden', 'watermark-pro' ); ?>
                </button>
                <div id="wm-progress-wrap" style="display:none" aria-live="polite">
                    <div class="wm-progress-bar-outer"><div id="wm-progress-bar" class="wm-progress-bar-inner" style="width:0%"></div></div>
                    <span id="wm-progress-text" class="wm-progress-text"></span>
                </div>
                <div id="wm-apply-result" class="wm-apply-result" aria-live="polite"></div>
            </div>

        </div><!-- #wm-tab-apply -->

        <!-- ================================================================
             TAB: Templates
             ================================================================ -->
        <div class="wm-tab-panel" id="wm-tab-templates" role="tabpanel">
            <div class="wm-box">
                <h3><?php esc_html_e( 'Gespeicherte Vorlagen', 'watermark-pro' ); ?></h3>
                <div id="wm-templates-table-wrap" aria-live="polite">
                    <p class="wm-loading"><?php esc_html_e( 'Lädt…', 'watermark-pro' ); ?></p>
                </div>
            </div>
        </div>

    </div><!-- .wm-tabs -->
</div><!-- .wrap -->
