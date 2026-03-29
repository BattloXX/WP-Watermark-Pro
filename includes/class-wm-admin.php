<?php
defined( 'ABSPATH' ) || exit;

class WM_Admin {

    private static ?self $instance = null;
    private WM_Processor $processor;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->processor = new WM_Processor();

        add_action( 'admin_menu',            [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue'  ] );

        add_filter( 'upload_mimes',              [ $this, 'allow_extra_mimes'  ] );
        add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_eps_filetype'  ], 10, 4 );

        $ajax_actions = [ 'apply', 'save_template', 'get_templates', 'get_template', 'delete_template' ];
        foreach ( $ajax_actions as $action ) {
            add_action( "wp_ajax_wm_{$action}", [ $this, "ajax_{$action}" ] );
        }
    }

    // -------------------------------------------------------------------------
    // Menu & assets
    // -------------------------------------------------------------------------

    public function add_menu(): void {
        add_menu_page(
            __( 'Watermark Pro', 'watermark-pro' ),
            __( 'Watermark Pro', 'watermark-pro' ),
            'upload_files',
            'watermark-pro',
            [ $this, 'render_page' ],
            'dashicons-art',
            60
        );
    }

    public function enqueue( string $hook ): void {
        if ( 'toplevel_page_watermark-pro' !== $hook ) { return; }

        wp_enqueue_media();
        wp_enqueue_style(  'wm-admin', WM_URL . 'assets/css/admin.css', [],              WM_VERSION );
        wp_enqueue_script( 'wm-admin', WM_URL . 'assets/js/admin.js',   [ 'jquery', 'media-views' ], WM_VERSION, true );

        wp_localize_script( 'wm-admin', 'wmPro', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'wm_nonce' ),
            'hasImagick'  => extension_loaded( 'imagick' ) ? '1' : '0',
            'hasFreetype' => function_exists( 'imagettftext' ) ? '1' : '0',
            'fonts'       => WM_Processor::detect_available_fonts(),
            'i18n'        => [
                'selectImages'    => __( 'Bilder auswählen',                    'watermark-pro' ),
                'selectWatermark' => __( 'Wasserzeichen auswählen',             'watermark-pro' ),
                'useSelected'     => __( 'Auswahl übernehmen',                  'watermark-pro' ),
                'noImages'        => __( 'Bitte mindestens ein Bild auswählen.','watermark-pro' ),
                'noWatermark'     => __( 'Kein Wasserzeichen konfiguriert.',    'watermark-pro' ),
                'noTemplateName'  => __( 'Bitte einen Vorlagennamen eingeben.', 'watermark-pro' ),
                'confirmDelete'   => __( 'Vorlage wirklich löschen?',           'watermark-pro' ),
                'processing'      => __( 'Verarbeite',                          'watermark-pro' ),
                'errorApply'      => __( 'Fehler beim Verarbeiten.',            'watermark-pro' ),
                'noTemplates'     => __( 'Noch keine Vorlagen gespeichert.',    'watermark-pro' ),
                'tplLoaded'       => __( 'Vorlage geladen.',                    'watermark-pro' ),
                'tplSaved'        => __( 'Vorlage gespeichert!',                'watermark-pro' ),
                'noFont'          => __( 'Kein TTF-Font auf dem Server gefunden. Text-Wasserzeichen nicht verfügbar.', 'watermark-pro' ),
            ],
        ] );
    }

    public function render_page(): void {
        require WM_DIR . 'templates/admin-page.php';
    }

    // -------------------------------------------------------------------------
    // Upload mime filters
    // -------------------------------------------------------------------------

    public function allow_extra_mimes( array $mimes ): array {
        $mimes['eps'] = 'application/postscript';
        return $mimes;
    }

    public function fix_eps_filetype( array $data, string $file, string $filename, array $mimes ): array {
        if ( empty( $data['type'] ) && strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) === 'eps' ) {
            $data['ext']  = 'eps';
            $data['type'] = 'application/postscript';
        }
        return $data;
    }

    // -------------------------------------------------------------------------
    // AJAX – apply watermark
    // -------------------------------------------------------------------------

    public function ajax_apply(): void {
        $this->verify_nonce();
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'watermark-pro' ) ] );
        }

        $image_id = absint( $_POST['image_id'] ?? 0 );
        $wm_id    = absint( $_POST['wm_id']    ?? 0 );

        if ( ! $image_id ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Bild-ID.', 'watermark-pro' ) ] );
        }

        $settings = [
            // Image watermark
            'image_wm_enabled' => ! empty( $_POST['image_wm_enabled'] ),
            'position'         => WM_Templates::sanitize_wm_position( $_POST['position']  ?? 'bottom-right' ),
            'offset_x'         => absint( $_POST['offset_x']  ?? 10 ),
            'offset_y'         => absint( $_POST['offset_y']  ?? 10 ),
            'size_pct'         => max( 1,  min( 100, absint( $_POST['size_pct'] ?? 20 ) ) ),
            'opacity'          => max( 10, min( 100, absint( $_POST['opacity']  ?? 80 ) ) ),
            'save_mode'        => in_array( $_POST['save_mode'] ?? '', [ 'new', 'overwrite' ], true )
                                  ? sanitize_key( $_POST['save_mode'] ) : 'new',
            // Text watermark
            'text_enabled'     => ! empty( $_POST['text_enabled'] ),
            'text_content'     => sanitize_text_field( $_POST['text_content']      ?? '' ),
            'text_position'    => WM_Templates::sanitize_wm_position( $_POST['text_position'] ?? 'bottom-right' ),
            'text_align'       => in_array( $_POST['text_align'] ?? '', [ 'left', 'center', 'right' ], true )
                                  ? sanitize_key( $_POST['text_align'] ) : 'center',
            'text_font_family' => sanitize_text_field( $_POST['text_font_family']  ?? 'auto' ),
            'text_font_path'   => sanitize_text_field( $_POST['text_font_path']    ?? '' ),
            'text_font_size'   => max( 12, min( 200, absint( $_POST['text_font_size'] ?? 36 ) ) ),
            'text_color'       => preg_match( '/^#[0-9a-fA-F]{6}$/', $_POST['text_color'] ?? '' )
                                  ? $_POST['text_color'] : '#ffffff',
            'text_opacity'     => max( 10, min( 100, absint( $_POST['text_opacity']   ?? 80 ) ) ),
            'text_offset_x'    => absint( $_POST['text_offset_x'] ?? 10 ),
            'text_offset_y'    => absint( $_POST['text_offset_y'] ?? 10 ),
        ];

        $result = $this->processor->apply( $image_id, $wm_id, $settings );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $response = [ 'success_msg' => __( 'Wasserzeichen erfolgreich angewendet.', 'watermark-pro' ) ];
        if ( is_int( $result ) ) {
            $response['new_id']   = $result;
            $response['edit_url'] = get_edit_post_link( $result, 'raw' );
            $response['view_url'] = wp_get_attachment_url( $result );
        }
        wp_send_json_success( $response );
    }

    // -------------------------------------------------------------------------
    // AJAX – templates
    // -------------------------------------------------------------------------

    public function ajax_save_template(): void {
        $this->verify_nonce();
        if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error(); }

        $data = [
            'id'               => absint( $_POST['id'] ?? 0 ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
            'wm_id'            => absint( $_POST['wm_id'] ?? 0 ),
            'position'         => WM_Templates::sanitize_wm_position( $_POST['position']  ?? 'bottom-right' ),
            'offset_x'         => absint( $_POST['offset_x']  ?? 10 ),
            'offset_y'         => absint( $_POST['offset_y']  ?? 10 ),
            'size_pct'         => max( 1,  min( 100, absint( $_POST['size_pct'] ?? 20 ) ) ),
            'opacity'          => max( 10, min( 100, absint( $_POST['opacity']  ?? 80 ) ) ),
            'text_enabled'     => ! empty( $_POST['text_enabled'] ),
            'text_content'     => sanitize_text_field( $_POST['text_content']      ?? '' ),
            'text_position'    => WM_Templates::sanitize_wm_position( $_POST['text_position'] ?? 'bottom-right' ),
            'text_align'       => in_array( $_POST['text_align'] ?? '', [ 'left', 'center', 'right' ], true )
                                  ? sanitize_key( $_POST['text_align'] ) : 'center',
            'text_font_family' => sanitize_text_field( $_POST['text_font_family']  ?? 'auto' ),
            'text_font_path'   => sanitize_text_field( $_POST['text_font_path']    ?? '' ),
            'text_font_size'   => max( 12, min( 200, absint( $_POST['text_font_size'] ?? 36 ) ) ),
            'text_color'       => preg_match( '/^#[0-9a-fA-F]{6}$/', $_POST['text_color'] ?? '' )
                                  ? $_POST['text_color'] : '#ffffff',
            'text_opacity'     => max( 10, min( 100, absint( $_POST['text_opacity']   ?? 80 ) ) ),
            'text_offset_x'    => absint( $_POST['text_offset_x'] ?? 10 ),
            'text_offset_y'    => absint( $_POST['text_offset_y'] ?? 10 ),
        ];

        if ( empty( $data['name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Name erforderlich.', 'watermark-pro' ) ] );
        }
        $id = WM_Templates::save( $data );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => __( 'Fehler beim Speichern.', 'watermark-pro' ) ] );
        }
        wp_send_json_success( [ 'id' => $id, 'templates' => $this->templates_for_js() ] );
    }

    public function ajax_get_templates(): void {
        $this->verify_nonce();
        wp_send_json_success( [ 'templates' => $this->templates_for_js() ] );
    }

    public function ajax_get_template(): void {
        $this->verify_nonce();
        $id  = absint( $_POST['id'] ?? 0 );
        $tpl = WM_Templates::get( $id );
        if ( ! $tpl ) { wp_send_json_error(); }
        if ( $tpl['wm_id'] ) {
            $tpl['wm_url']   = wp_get_attachment_url( $tpl['wm_id'] );
            $tpl['wm_thumb'] = wp_get_attachment_image_url( $tpl['wm_id'], 'thumbnail' ) ?: $tpl['wm_url'];
            $tpl['wm_title'] = get_the_title( $tpl['wm_id'] );
        }
        wp_send_json_success( $tpl );
    }

    public function ajax_delete_template(): void {
        $this->verify_nonce();
        if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error(); }
        WM_Templates::delete( absint( $_POST['id'] ?? 0 ) );
        wp_send_json_success( [ 'templates' => $this->templates_for_js() ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verify_nonce(): void {
        if ( ! check_ajax_referer( 'wm_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
        }
    }

    private function templates_for_js(): array {
        $templates = WM_Templates::get_all();
        foreach ( $templates as &$t ) {
            if ( $t['wm_id'] ) {
                $t['wm_url']   = wp_get_attachment_url( $t['wm_id'] );
                $t['wm_thumb'] = wp_get_attachment_image_url( $t['wm_id'], 'thumbnail' ) ?: $t['wm_url'];
                $t['wm_title'] = get_the_title( $t['wm_id'] );
            }
        }
        unset( $t );
        return $templates;
    }
}
