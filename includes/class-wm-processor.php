<?php
defined( 'ABSPATH' ) || exit;

/**
 * GD-based watermark processor.
 * Supports PNG/JPG/WebP/GIF image watermarks and TTF text watermarks.
 * EPS watermarks require Imagick + Ghostscript.
 */
class WM_Processor {

    private const EPS_EXTENSIONS = [ 'eps', 'ai' ];

    /** Candidate TTF font paths per family, checked in order. */
    private const FONT_CANDIDATES = [
        'auto'       => [],   // resolved dynamically to first available family
        'dejavu'     => [
            // plugin-bundled font is prepended at runtime in resolve_font() / detect_available_fonts()
            // FreeBSD (ports)
            '/usr/local/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/local/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/local/share/fonts/truetype/DejaVuSans.ttf',
            '/usr/local/lib/X11/fonts/dejavu/DejaVuSans.ttf',
            // Linux (Debian/Ubuntu/RHEL)
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/ttf-dejavu/DejaVuSans.ttf',
            // Windows
            'C:/Windows/Fonts/DejaVuSans.ttf',
        ],
        'liberation' => [
            // FreeBSD
            '/usr/local/share/fonts/liberation/LiberationSans-Regular.ttf',
            '/usr/local/share/fonts/TTF/LiberationSans-Regular.ttf',
            // Linux
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
            // Windows fallback
            'C:/Windows/Fonts/arial.ttf',
        ],
        'liberation-serif' => [
            '/usr/local/share/fonts/liberation/LiberationSerif-Regular.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            '/usr/share/fonts/liberation/LiberationSerif-Regular.ttf',
            'C:/Windows/Fonts/times.ttf',
            'C:/Windows/Fonts/georgia.ttf',
        ],
        'liberation-mono' => [
            '/usr/local/share/fonts/liberation/LiberationMono-Regular.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
            '/usr/share/fonts/liberation/LiberationMono-Regular.ttf',
            'C:/Windows/Fonts/cour.ttf',
            'C:/Windows/Fonts/consola.ttf',
        ],
        'freefont' => [
            '/usr/local/share/fonts/freefont/FreeSans.ttf',
            '/usr/local/share/fonts/TTF/FreeSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/freefont/FreeSans.ttf',
        ],
    ];

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Apply image and/or text watermark to an attachment.
     *
     * @param  int   $image_id   Base image attachment ID.
     * @param  int   $wm_id      Image watermark attachment ID (0 = text only).
     * @param  array $settings   See inline docs.
     * @return int|true|WP_Error New attachment ID, true (overwrite), or error.
     */
    public function apply( int $image_id, int $wm_id, array $settings ): int|bool|WP_Error {
        if ( ! extension_loaded( 'gd' ) ) {
            return new WP_Error( 'no_gd', __( 'Die PHP GD-Erweiterung ist nicht verfügbar.', 'watermark-pro' ) );
        }

        @ini_set( 'max_execution_time', '120' );
        @ini_set( 'memory_limit', '256M' );

        $has_image_wm = ! empty( $settings['image_wm_enabled'] ) && $wm_id > 0;
        $has_text_wm  = ! empty( $settings['text_enabled'] )     && ! empty( $settings['text_content'] );

        if ( ! $has_image_wm && ! $has_text_wm ) {
            return new WP_Error( 'nothing_to_do', __( 'Kein Wasserzeichen konfiguriert.', 'watermark-pro' ) );
        }

        // Load base image
        $base_path = get_attached_file( $image_id );
        if ( ! $base_path || ! file_exists( $base_path ) ) {
            return new WP_Error( 'no_image', __( 'Bild nicht gefunden.', 'watermark-pro' ) );
        }
        $ext  = strtolower( pathinfo( $base_path, PATHINFO_EXTENSION ) );
        $base = $this->load_image( $base_path, $ext );
        if ( ! $base ) {
            return new WP_Error( 'load_fail', sprintf(
                __( 'Bild konnte nicht geladen werden (Typ: %s).', 'watermark-pro' ), $ext
            ) );
        }

        $base_w = imagesx( $base );
        $base_h = imagesy( $base );

        // ---- Image watermark ----
        if ( $has_image_wm ) {
            $wm_path = get_attached_file( $wm_id );
            if ( ! $wm_path || ! file_exists( $wm_path ) ) {
                imagedestroy( $base );
                return new WP_Error( 'no_wm', __( 'Wasserzeichen nicht gefunden.', 'watermark-pro' ) );
            }

            $wm_ext = strtolower( pathinfo( $wm_path, PATHINFO_EXTENSION ) );
            $wm     = in_array( $wm_ext, self::EPS_EXTENSIONS, true )
                      ? $this->load_watermark_eps( $wm_path )
                      : $this->load_image( $wm_path, $wm_ext );

            if ( ! $wm ) {
                imagedestroy( $base );
                $hint = in_array( $wm_ext, self::EPS_EXTENSIONS, true )
                    ? __( 'EPS-Wasserzeichen erfordert Imagick mit Ghostscript.', 'watermark-pro' )
                    : __( 'Wasserzeichen konnte nicht geladen werden.', 'watermark-pro' );
                return new WP_Error( 'load_wm_fail', $hint );
            }

            // Keep raw alpha values from the source (critical for indexed PNGs
            // with tRNS transparency – blending=true would pre-composite against
            // the black GD background and destroy semi-transparent pixels).
            imagealphablending( $wm, false );
            imagesavealpha( $wm, true );

            $wm_ow = imagesx( $wm );
            $wm_oh = imagesy( $wm );

            $wm_w = (int) round( $base_w * ( absint( $settings['size_pct'] ) / 100 ) );
            $wm_h = (int) round( $wm_w * ( $wm_oh / max( 1, $wm_ow ) ) );

            $wm_r = imagecreatetruecolor( $wm_w, $wm_h );
            imagealphablending( $wm_r, false );
            imagesavealpha( $wm_r, true );
            imagefill( $wm_r, 0, 0, imagecolorallocatealpha( $wm_r, 0, 0, 0, 127 ) );
            imagecopyresampled( $wm_r, $wm, 0, 0, 0, 0, $wm_w, $wm_h, $wm_ow, $wm_oh );
            imagedestroy( $wm );

            $opacity = max( 0, min( 100, (int) $settings['opacity'] ) );
            if ( $opacity < 100 ) {
                $this->adjust_opacity( $wm_r, $opacity );
            }

            [ $dest_x, $dest_y ] = $this->calculate_position(
                $base_w, $base_h, $wm_w, $wm_h,
                $settings['position'],
                (int) $settings['offset_x'],
                (int) $settings['offset_y']
            );

            imagealphablending( $base, true );
            imagecopy( $base, $wm_r, $dest_x, $dest_y, 0, 0, $wm_w, $wm_h );
            imagedestroy( $wm_r );
        }

        // ---- Text watermark ----
        if ( $has_text_wm ) {
            // Try GD/FreeType first; fall back to Imagick if unavailable or broken
            // (FreeBSD + PHP-FPM symlink path issue can cause GD/FreeType to fail
            //  even when the function and font file both exist).
            $text_ok = false;

            $font = $this->resolve_font(
                $settings['text_font_family'] ?? 'auto',
                $settings['text_font_path']   ?? ''
            );

            if ( $font && function_exists( 'imagettftext' ) ) {
                $text_ok = $this->apply_text_watermark( $base, $base_w, $base_h, $settings );
            }

            if ( ! $text_ok ) {
                // GD failed (or wasn't available) — try Imagick text renderer
                $text_ok = $this->apply_text_watermark_imagick( $base, $base_w, $base_h, $settings );
            }

            if ( ! $text_ok ) {
                imagedestroy( $base );
                return new WP_Error(
                    'text_wm_failed',
                    __( 'Text-Wasserzeichen konnte nicht gerendert werden. Weder GD/FreeType noch Imagick stehen zur Verfügung.', 'watermark-pro' )
                );
            }
        }

        // ---- Save ----
        $save_mode = $settings['save_mode'] === 'overwrite' ? 'overwrite' : 'new';
        $out_path  = $save_mode === 'overwrite' ? $base_path : $this->generate_new_path( $base_path );

        $saved = $this->save_image( $base, $out_path, $ext );
        imagedestroy( $base );

        if ( ! $saved ) {
            return new WP_Error( 'save_fail', __( 'Datei konnte nicht gespeichert werden.', 'watermark-pro' ) );
        }

        if ( $save_mode === 'overwrite' ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata(
                $image_id,
                wp_generate_attachment_metadata( $image_id, $base_path )
            );
            return true;
        }

        return $this->register_attachment( $out_path, $image_id );
    }

    /**
     * Test whether GD's FreeType support actually works by calling imagettfbbox()
     * with the bundled font. Returns the working font path, or false.
     */
    public static function test_freetype(): string|false {
        static $cached = null;
        if ( $cached !== null ) { return $cached; }

        if ( ! function_exists( 'imagettfbbox' ) ) { return $cached = false; }

        $plugin_font = defined( 'WM_DIR' ) ? WM_DIR . 'fonts/DejaVuSans.ttf' : '';
        $upload_dir  = wp_upload_dir();
        $cached_font = $upload_dir['basedir'] . '/wm-fonts/DejaVuSans.ttf';

        // Ensure uploads copy exists
        if ( $plugin_font && file_exists( $plugin_font ) && ! file_exists( $cached_font ) ) {
            wp_mkdir_p( dirname( $cached_font ) );
            @copy( $plugin_font, $cached_font );
            @chmod( $cached_font, 0644 );
            $htaccess = dirname( $cached_font ) . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) { file_put_contents( $htaccess, "Deny from all\n" ); }
        }

        foreach ( array_filter( [ $cached_font, $plugin_font ], 'file_exists' ) as $path ) {
            set_error_handler( static function() {} );
            $ok = @imagettfbbox( 12, 0, $path, 'x' );
            restore_error_handler();
            if ( $ok !== false ) { return $cached = $path; }
        }

        // GD/FreeType unavailable – Imagick can render text as fallback
        if ( extension_loaded( 'imagick' ) ) {
            return $cached = 'imagick';
        }

        return $cached = false;
    }

    /**
     * Return available font families as [ slug => label ] for the admin dropdown.
     * Only includes families where at least one candidate path exists.
     */
    public static function detect_available_fonts(): array {
        $labels = [
            'dejavu'           => 'DejaVu Sans (empfohlen)',
            'liberation'       => 'Liberation Sans / Arial',
            'liberation-serif' => 'Liberation Serif / Times',
            'liberation-mono'  => 'Liberation Mono / Courier',
            'freefont'         => 'FreeSans',
        ];

        $available = [ 'auto' => 'Auto (beste verfügbare)' ];

        // Plugin-bundled DejaVu Sans is always available when the file exists
        $plugin_font = defined( 'WM_DIR' ) ? WM_DIR . 'fonts/DejaVuSans.ttf' : '';
        if ( $plugin_font && file_exists( $plugin_font ) ) {
            $available['dejavu'] = 'DejaVu Sans (empfohlen)';
        }

        foreach ( $labels as $slug => $label ) {
            if ( isset( $available[ $slug ] ) ) { continue; } // already found via plugin font
            foreach ( self::FONT_CANDIDATES[ $slug ] as $path ) {
                if ( file_exists( $path ) ) {
                    $available[ $slug ] = $label;
                    break;
                }
            }
        }

        $available['custom'] = 'Benutzerdefinierter Pfad…';
        return $available;
    }

    // =========================================================================
    // Text watermark
    // =========================================================================

    /**
     * Draw text onto $base using imagettftext().
     *
     * Position values:
     *   Grid:  top-left / top-center / top-right /
     *          middle-left / middle-center / middle-right /
     *          bottom-left / bottom-center / bottom-right
     *   Edges: edge-top    – horizontal, along top edge
     *          edge-bottom – horizontal, along bottom edge
     *          edge-left   – rotated 90° CCW, along left edge
     *          edge-right  – rotated 90° CW,  along right edge
     *
     * Alignment (left/center/right):
     *   Horizontal positions → standard text alignment.
     *   edge-left / edge-right → along-edge alignment:
     *     left = start near image top, right = start near image bottom.
     */
    private function apply_text_watermark( $base, int $bw, int $bh, array $s ): bool {
        $font = $this->resolve_font(
            $s['text_font_family'] ?? 'auto',
            $s['text_font_path']   ?? ''
        );

        if ( ! $font || ! function_exists( 'imagettftext' ) ) {
            return false;
        }

        $size    = max( 8, (int) ( $s['text_font_size'] ?? 36 ) );
        $text    = $s['text_content'];
        $pos     = $s['text_position']  ?? 'bottom-right';
        $align   = $s['text_align']     ?? 'center';
        $ox      = max( 0, (int) ( $s['text_offset_x'] ?? 10 ) );
        $oy      = max( 0, (int) ( $s['text_offset_y'] ?? 10 ) );
        $opacity = max( 0, min( 100, (int) ( $s['text_opacity'] ?? 80 ) ) );

        // Color
        $hex   = ltrim( $s['text_color'] ?? '#ffffff', '#' );
        $r     = hexdec( substr( $hex, 0, 2 ) );
        $g     = hexdec( substr( $hex, 2, 2 ) );
        $b     = hexdec( substr( $hex, 4, 2 ) );
        $alpha = (int) round( 127 * ( 1 - $opacity / 100 ) );

        imagealphablending( $base, true );
        $color = imagecolorallocatealpha( $base, $r, $g, $b, $alpha );

        $is_edge_left  = $pos === 'edge-left';
        $is_edge_right = $pos === 'edge-right';

        // GD angle: CCW degrees
        $angle = 0;
        if ( $is_edge_left  ) { $angle = 90;  }
        if ( $is_edge_right ) { $angle = 270; }

        // Measure text – wrap in error handler; false means GD cannot read the font
        set_error_handler( static function() {} );
        $bbox  = imagettfbbox( $size, $angle, $font, $text );
        $bbox0 = imagettfbbox( $size, 0,      $font, $text );
        restore_error_handler();

        if ( $bbox === false || $bbox0 === false ) {
            error_log( 'WM_Processor: imagettfbbox() failed — font: ' . $font );
            return false;
        }

        $text_w = abs( $bbox[4] - $bbox[0] ); // axis-aligned width
        $text_h = abs( $bbox[5] - $bbox[1] ); // axis-aligned height

        // line-height and ascent for edge-flush placement
        $line_h  = abs( $bbox0[7] - $bbox0[1] );
        $ascent  = abs( $bbox0[7] );

        if ( $is_edge_left ) {
            // Text runs vertically (bottom→top). text_w = string length in px; text_h = line height.
            // Baseline origin: x = flush to left margin, y = along-edge start of text
            $draw_x = $ox + $line_h;

            $draw_y = match ( $align ) {
                'right'  => $oy + $text_w,                     // text ends near top
                'center' => (int) round( ( $bh + $text_w ) / 2 ),
                default  => $bh - $oy,                          // text starts near bottom
            };

        } elseif ( $is_edge_right ) {
            // Text runs vertically (top→bottom, angle=270).
            $draw_x = $bw - $ox - $line_h + $ascent;

            $draw_y = match ( $align ) {
                'right'  => $bh - $oy - $text_w,
                'center' => (int) round( ( $bh - $text_w ) / 2 ),
                default  => $oy,
            };

        } else {
            // Horizontal text (including edge-top, edge-bottom, all 9-point grid)

            // --- Horizontal placement ---
            $is_edge_h = ( $pos === 'edge-top' || $pos === 'edge-bottom' );

            if ( $is_edge_h ) {
                $draw_x = match ( $align ) {
                    'right'  => $bw - $text_w - $ox - $bbox[6],
                    'center' => (int) round( ( $bw - $text_w ) / 2 ) - $bbox[6],
                    default  => $ox - $bbox[6],
                };
            } elseif ( str_contains( $pos, 'right' ) ) {
                $draw_x = $bw - $text_w - $ox - $bbox[6];
            } elseif ( str_contains( $pos, 'left' ) ) {
                $draw_x = $ox - $bbox[6];
            } else {
                $draw_x = (int) round( ( $bw - $text_w ) / 2 ) - $bbox[6];
            }

            // --- Vertical placement ---
            // We place the top of the bounding box at py, then offset to baseline
            if ( str_contains( $pos, 'top' ) || $pos === 'edge-top' ) {
                $py = $oy;
            } elseif ( str_contains( $pos, 'bottom' ) || $pos === 'edge-bottom' ) {
                $py = $bh - $text_h - $oy;
            } else {
                $py = (int) round( ( $bh - $text_h ) / 2 );
            }

            // Convert top-left to baseline origin
            $draw_y = $py - $bbox[7];
        }

        // Suppress PHP warnings, check return value – imagettftext() returns false
        // if the font cannot be read (e.g. open_basedir restriction).
        set_error_handler( static function() {} );
        $result = imagettftext( $base, $size, $angle, $draw_x, $draw_y, $color, $font, $text );
        restore_error_handler();

        if ( $result === false ) {
            error_log( 'WM_Processor: imagettftext() failed — font: ' . $font );
            return false;
        }

        return true;
    }

    // =========================================================================
    // Text watermark – Imagick fallback
    // (Used when GD/FreeType fails, e.g. FreeBSD symlink path issues)
    // =========================================================================

    private function apply_text_watermark_imagick( $base_gd, int $bw, int $bh, array $s ): bool {
        if ( ! extension_loaded( 'imagick' ) ) { return false; }

        // Resolve font once; we'll try with it first, then without (Imagick default)
        $resolved_font = $this->resolve_font_for_imagick(
            $s['text_font_family'] ?? 'auto',
            $s['text_font_path']   ?? ''
        );
        $font_attempts = $resolved_font ? [ $resolved_font, null ] : [ null ];

        foreach ( $font_attempts as $font_path ) {
            try {
                // Transparent overlay canvas – same size as base image
                $overlay = new \Imagick();
                $overlay->newImage( $bw, $bh, new \ImagickPixel( 'none' ) );
                $overlay->setImageFormat( 'png32' );

                $draw = new \ImagickDraw();

                // ---- Font ----
                if ( $font_path ) {
                    $draw->setFont( $font_path );
                }

                $size = max( 8, (int) ( $s['text_font_size'] ?? 36 ) );
                $draw->setFontSize( $size );
                $draw->setStrokeWidth( 0 );

                // ---- Color + opacity ----
                $hex     = ltrim( $s['text_color'] ?? '#ffffff', '#' );
                $r       = hexdec( substr( $hex, 0, 2 ) );
                $g       = hexdec( substr( $hex, 2, 2 ) );
                $b       = hexdec( substr( $hex, 4, 2 ) );
                $opacity = max( 0, min( 100, (int) ( $s['text_opacity'] ?? 80 ) ) );
                $draw->setFillColor( new \ImagickPixel( "rgba($r,$g,$b," . round( $opacity / 100, 4 ) . ')' ) );
                $draw->setFillOpacity( $opacity / 100 );

                $text  = $s['text_content'];
                $pos   = $s['text_position']  ?? 'bottom-right';
                $align = $s['text_align']     ?? 'center';
                $ox    = max( 0, (int) ( $s['text_offset_x'] ?? 10 ) );
                $oy    = max( 0, (int) ( $s['text_offset_y'] ?? 10 ) );

                // ---- Measure text ----
                $metrics = $overlay->queryFontMetrics( $draw, $text );
                $text_w  = (float) ( $metrics['textWidth']  ?? $size * mb_strlen( $text ) * 0.6 );
                $text_h  = (float) ( $metrics['textHeight'] ?? $size * 1.2 );
                $asc     = (float) ( $metrics['ascender']   ?? $size * 0.8 );

                $is_edge_left  = ( $pos === 'edge-left'  );
                $is_edge_right = ( $pos === 'edge-right' );

                // ---- Position calculation ----
                // Imagick annotateImage() uses BASELINE (x, y); angle is clockwise degrees.
                $angle  = 0;
                $draw_x = 0.0;
                $draw_y = 0.0;

                if ( $is_edge_left ) {
                    $angle  = -90.0;   // CCW in Imagick
                    $draw_x = (float) ( $ox + $asc );
                    $draw_y = (float) match ( $align ) {
                        'right'  => $oy + $text_w,
                        'center' => ( $bh + $text_w ) / 2,
                        default  => $bh - $oy,
                    };
                    $draw->setTextAlignment( \Imagick::ALIGN_LEFT );

                } elseif ( $is_edge_right ) {
                    $angle  = 90.0;    // CW
                    $draw_x = (float) ( $bw - $ox - $asc + $text_h );
                    $draw_y = (float) match ( $align ) {
                        'right'  => $bh - $oy - $text_w,
                        'center' => ( $bh - $text_w ) / 2,
                        default  => $oy,
                    };
                    $draw->setTextAlignment( \Imagick::ALIGN_LEFT );

                } else {
                    // Horizontal positions (grid + edge-top / edge-bottom)
                    $is_edge_h = ( $pos === 'edge-top' || $pos === 'edge-bottom' );
                    $eff_align = $is_edge_h ? $align
                               : ( str_contains( $pos, 'right' ) ? 'right'
                                 : ( str_contains( $pos, 'left' ) ? 'left' : 'center' ) );

                    switch ( $eff_align ) {
                        case 'right':
                            $draw->setTextAlignment( \Imagick::ALIGN_RIGHT );
                            $draw_x = (float) ( $bw - $ox );
                            break;
                        case 'left':
                            $draw->setTextAlignment( \Imagick::ALIGN_LEFT );
                            $draw_x = (float) $ox;
                            break;
                        default:
                            $draw->setTextAlignment( \Imagick::ALIGN_CENTER );
                            $draw_x = (float) ( $bw / 2 );
                    }

                    // Baseline y
                    if ( str_contains( $pos, 'top' ) || $pos === 'edge-top' ) {
                        $draw_y = (float) ( $oy + $asc );
                    } elseif ( str_contains( $pos, 'bottom' ) || $pos === 'edge-bottom' ) {
                        $draw_y = (float) ( $bh - $oy );
                    } else {
                        $draw_y = (float) round( ( $bh + $asc ) / 2 );
                    }
                }

                $overlay->annotateImage( $draw, $draw_x, $draw_y, $angle, $text );

                // ---- Composite overlay onto GD base ----
                $overlay->setImageFormat( 'png32' );
                $blob = $overlay->getImageBlob();
                $overlay->destroy();

                $gd_overlay = @imagecreatefromstring( $blob );
                if ( ! $gd_overlay ) { return false; }

                imagealphablending( $base_gd, true );
                imagecopy( $base_gd, $gd_overlay, 0, 0, 0, 0, $bw, $bh );
                imagedestroy( $gd_overlay );

                return true;

            } catch ( \Exception $e ) {
                if ( $font_path !== null ) {
                    // Font caused the failure – retry without font (Imagick default)
                    error_log(
                        'WM_Processor: Imagick failed with font ' . $font_path . ': ' . $e->getMessage()
                        . ' — retrying without font'
                    );
                    continue;
                }
                $version = extension_loaded( 'imagick' )
                    ? ( \Imagick::getVersion()['versionString'] ?? 'unknown' )
                    : 'not loaded';
                error_log(
                    'WM_Processor: Imagick text rendering failed: ' . $e->getMessage()
                    . ' | font_path=none (default)'
                    . ' | imagick=' . $version
                );
                return false;
            }
        }
        return false;
    }

    /**
     * Find a font file readable by Imagick/ImageMagick.
     * Adds FreeBSD-specific paths that GD/FreeType may not access.
     */
    private function resolve_font_for_imagick( string $family, string $custom_path ): string|false {
        if ( $custom_path && file_exists( $custom_path ) ) {
            return realpath( $custom_path ) ?: $custom_path;
        }

        $candidates = [];

        // 1. Uploads-dir copy (guaranteed no symlink, no open_basedir issues)
        $upload_dir  = wp_upload_dir();
        $cached_font = $upload_dir['basedir'] . '/wm-fonts/DejaVuSans.ttf';

        // Ensure the copy exists before trying it
        $plugin_font = defined( 'WM_DIR' ) ? WM_DIR . 'fonts/DejaVuSans.ttf' : '';
        if ( $plugin_font && file_exists( $plugin_font ) && ! file_exists( $cached_font ) ) {
            $real_src = realpath( $plugin_font ) ?: $plugin_font;
            wp_mkdir_p( dirname( $cached_font ) );
            @copy( $real_src, $cached_font );
            @chmod( $cached_font, 0644 );
            $ht = dirname( $cached_font ) . '/.htaccess';
            if ( ! file_exists( $ht ) ) { file_put_contents( $ht, "Deny from all\n" ); }
        }

        if ( file_exists( $cached_font ) ) { $candidates[] = $cached_font; }

        // 2. Plugin font with realpath() applied
        if ( $plugin_font ) {
            $real = realpath( $plugin_font );
            $p    = ( $real && file_exists( $real ) ) ? $real : ( file_exists( $plugin_font ) ? $plugin_font : '' );
            if ( $p ) { $candidates[] = $p; }
        }

        // 3. FreeBSD system font paths
        foreach ( [
            '/usr/local/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/local/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/local/share/fonts/truetype/DejaVuSans.ttf',
            '/usr/local/lib/X11/fonts/dejavu/DejaVuSans.ttf',
        ] as $p ) {
            if ( file_exists( $p ) ) { $candidates[] = $p; }
        }

        foreach ( $candidates as $path ) {
            try {
                $t_draw = new \ImagickDraw();
                $t_draw->setFont( $path );
                $t_draw->setFontSize( 12 );
                $t_im = new \Imagick();
                $t_im->newImage( 10, 10, new \ImagickPixel( 'white' ) );
                $t_im->queryFontMetrics( $t_draw, 'x' );
                $t_im->destroy();
                return $path; // font actually works with Imagick
            } catch ( \Exception $e ) {
                // font not usable by Imagick – try next candidate
            }
        }

        // No working path found – Imagick will use its default/built-in font
        return false;
    }

    // =========================================================================
    // Font resolution
    // =========================================================================

    private function resolve_font( string $family, string $custom_path ): string|false {
        // Custom absolute path wins
        if ( $custom_path && file_exists( $custom_path ) ) {
            return realpath( $custom_path ) ?: $custom_path;
        }

        // Plugin-bundled DejaVu Sans: try first for 'auto' or 'dejavu'
        $plugin_font = defined( 'WM_DIR' ) ? WM_DIR . 'fonts/DejaVuSans.ttf' : '';
        // Resolve symlinks (critical on FreeBSD where /home → /usr/home;
        // PHP's __FILE__ returns the realpath, but GD/FreeType may only accept the
        // symlink-free path or the uploads-dir copy).
        if ( $plugin_font ) {
            $real = realpath( $plugin_font );
            if ( $real ) { $plugin_font = $real; }
        }

        if ( $plugin_font && file_exists( $plugin_font ) ) {
            if ( $family === 'auto' || $family === 'dejavu' ) {
                $resolved = $this->ensure_font_in_uploads( $plugin_font, 'DejaVuSans.ttf' );
                return $resolved ?: $plugin_font;
            }
        }

        $candidates = self::FONT_CANDIDATES;

        // Prepend plugin font to dejavu candidate list (covers named 'dejavu' lookup below)
        if ( $plugin_font ) {
            array_unshift( $candidates['dejavu'], $plugin_font );
        }

        // 'auto' → try all families, return first hit
        if ( $family === 'auto' || ! isset( $candidates[ $family ] ) ) {
            foreach ( $candidates as $slug => $paths ) {
                if ( $slug === 'auto' ) { continue; }
                foreach ( $paths as $path ) {
                    if ( file_exists( $path ) ) { return $path; }
                }
            }
            return false;
        }

        foreach ( $candidates[ $family ] as $path ) {
            if ( file_exists( $path ) ) { return $path; }
        }
        return false;
    }

    /**
     * Copy a font file to the uploads directory so imagettftext() can read it
     * even under open_basedir restrictions that block the plugin directory.
     *
     * Returns the uploads-dir path on success, or false if the copy failed.
     */
    private function ensure_font_in_uploads( string $src_path, string $filename ): string|false {
        // Resolve symlinks on the SOURCE to get the true filesystem path.
        // On FreeBSD /home is a symlink to /usr/home; copy() from the
        // symlinked path may silently fail in some FPM configurations.
        $real_src = realpath( $src_path );
        if ( $real_src && file_exists( $real_src ) ) {
            $src_path = $real_src;
        }

        $upload_dir  = wp_upload_dir();
        $font_dir    = $upload_dir['basedir'] . '/wm-fonts';
        $cached_path = $font_dir . '/' . $filename;

        // Re-copy if cached file is missing or suspiciously small (< 1 KB)
        $needs_copy = ! file_exists( $cached_path ) || filesize( $cached_path ) < 1024;

        if ( $needs_copy ) {
            if ( ! wp_mkdir_p( $font_dir ) ) {
                return false;
            }

            // Block direct web access to the font directory
            $htaccess = $font_dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, "Deny from all\n" );
            }

            if ( ! @copy( $src_path, $cached_path ) || filesize( $cached_path ) < 1024 ) {
                error_log( 'WM_Processor: failed to copy font to uploads: ' . $cached_path . ' (src: ' . $src_path . ')' );
                return false;
            }

            // Ensure the web server can read the file
            @chmod( $cached_path, 0644 );
        }

        // Quick readability test: imagettfbbox() must succeed with this font
        set_error_handler( static function() {} );
        $test = @imagettfbbox( 12, 0, $cached_path, 'x' );
        restore_error_handler();

        if ( $test === false ) {
            error_log( 'WM_Processor: imagettfbbox() failed for cached font: ' . $cached_path );
            return false;
        }

        return $cached_path;
    }

    // =========================================================================
    // Image helpers
    // =========================================================================

    private function load_image( string $path, string $ext ) {
        return match ( $ext ) {
            'jpg', 'jpeg' => @imagecreatefromjpeg( $path ),
            'png'         => @imagecreatefrompng( $path ),
            'webp'        => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false,
            'gif'         => @imagecreatefromgif( $path ),
            default       => false,
        };
    }

    private function save_image( $image, string $path, string $ext ): bool {
        return match ( $ext ) {
            'jpg', 'jpeg' => imagejpeg( $image, $path, 92 ),
            'png'         => ( imagesavealpha( $image, true ) && imagepng( $image, $path, 9 ) ),
            'webp'        => function_exists( 'imagewebp' ) ? imagewebp( $image, $path, 92 ) : false,
            'gif'         => imagegif( $image, $path ),
            default       => false,
        };
    }

    private function load_watermark_eps( string $path ) {
        if ( ! extension_loaded( 'imagick' ) ) { return false; }
        try {
            $im = new \Imagick();
            $im->setResolution( 300, 300 );
            $im->readImage( $path . '[0]' );
            $im->setImageFormat( 'png32' );
            $im->setImageBackgroundColor( 'transparent' );
            $im->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );
            $blob = $im->getImageBlob();
            $im->destroy();
            return @imagecreatefromstring( $blob ) ?: false;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    private function adjust_opacity( $image, int $opacity ): void {
        imagealphablending( $image, false );
        $w   = imagesx( $image );
        $h   = imagesy( $image );
        $pct = $opacity / 100;
        for ( $x = 0; $x < $w; $x++ ) {
            for ( $y = 0; $y < $h; $y++ ) {
                $c     = imagecolorat( $image, $x, $y );
                $a     = ( $c >> 24 ) & 0x7F;
                $r_val = ( $c >> 16 ) & 0xFF;
                $g_val = ( $c >> 8  ) & 0xFF;
                $b_val =   $c         & 0xFF;
                $new_a = (int) round( $a + ( 127 - $a ) * ( 1 - $pct ) );
                imagesetpixel( $image, $x, $y, imagecolorallocatealpha( $image, $r_val, $g_val, $b_val, $new_a ) );
            }
        }
        imagesavealpha( $image, true );
    }

    /** @return array{0:int, 1:int} */
    private function calculate_position( int $bw, int $bh, int $ww, int $wh, string $pos, int $ox, int $oy ): array {
        $x = str_contains( $pos, 'right' ) ? $bw - $ww - $ox
           : ( str_contains( $pos, 'left' ) ? $ox : (int) round( ( $bw - $ww ) / 2 ) );
        $y = str_contains( $pos, 'bottom' ) ? $bh - $wh - $oy
           : ( str_contains( $pos, 'top' )  ? $oy : (int) round( ( $bh - $wh ) / 2 ) );
        return [ max( 0, $x ), max( 0, $y ) ];
    }

    private function generate_new_path( string $original ): string {
        $dir  = dirname( $original );
        $name = pathinfo( $original, PATHINFO_FILENAME );
        $ext  = pathinfo( $original, PATHINFO_EXTENSION );
        $i    = 1;
        do {
            $suffix    = $i > 1 ? "-{$i}" : '';
            $candidate = "{$dir}/{$name}-watermark{$suffix}.{$ext}";
            $i++;
        } while ( file_exists( $candidate ) );
        return $candidate;
    }

    private function register_attachment( string $file, int $parent_id ): int|WP_Error {
        $upload_dir = wp_upload_dir();
        $url        = str_replace(
            wp_normalize_path( $upload_dir['basedir'] ),
            $upload_dir['baseurl'],
            wp_normalize_path( $file )
        );
        $attachment = [
            'guid'           => $url,
            'post_mime_type' => wp_check_filetype( $file )['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment( $attachment, $file, $parent_id );
        if ( is_wp_error( $attach_id ) ) { return $attach_id; }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $file ) );
        return $attach_id;
    }
}
