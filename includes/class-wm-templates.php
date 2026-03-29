<?php
defined( 'ABSPATH' ) || exit;

class WM_Templates {

    /** Create the custom DB table (called on plugin activation). */
    public static function create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'wm_templates';

        $sql = "CREATE TABLE {$table} (
            id               mediumint(9)         NOT NULL AUTO_INCREMENT,
            name             varchar(200)         NOT NULL,
            wm_id            bigint(20) UNSIGNED  NOT NULL DEFAULT 0,
            position         varchar(30)          NOT NULL DEFAULT 'bottom-right',
            offset_x         smallint(6)          NOT NULL DEFAULT 10,
            offset_y         smallint(6)          NOT NULL DEFAULT 10,
            size_pct         tinyint(3) UNSIGNED  NOT NULL DEFAULT 20,
            opacity          tinyint(3) UNSIGNED  NOT NULL DEFAULT 80,
            text_enabled     tinyint(1)           NOT NULL DEFAULT 0,
            text_content     varchar(500)         NOT NULL DEFAULT '',
            text_position    varchar(30)          NOT NULL DEFAULT 'bottom-right',
            text_align       varchar(10)          NOT NULL DEFAULT 'center',
            text_font_family varchar(200)         NOT NULL DEFAULT 'auto',
            text_font_path   varchar(500)         NOT NULL DEFAULT '',
            text_font_size   smallint(5) UNSIGNED NOT NULL DEFAULT 36,
            text_color       varchar(7)           NOT NULL DEFAULT '#ffffff',
            text_opacity     tinyint(3) UNSIGNED  NOT NULL DEFAULT 80,
            text_offset_x    smallint(6)          NOT NULL DEFAULT 10,
            text_offset_y    smallint(6)          NOT NULL DEFAULT 10,
            PRIMARY KEY (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /** Add missing columns to existing installations (safe to run repeatedly). */
    public static function maybe_upgrade(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'wm_templates';

        // Table might not exist yet on a fresh install before activation
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $existing = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );

        $new_cols = [
            'text_enabled'     => "tinyint(1)           NOT NULL DEFAULT 0",
            'text_content'     => "varchar(500)         NOT NULL DEFAULT ''",
            'text_position'    => "varchar(30)          NOT NULL DEFAULT 'bottom-right'",
            'text_align'       => "varchar(10)          NOT NULL DEFAULT 'center'",
            'text_font_family' => "varchar(200)         NOT NULL DEFAULT 'auto'",
            'text_font_path'   => "varchar(500)         NOT NULL DEFAULT ''",
            'text_font_size'   => "smallint(5) UNSIGNED NOT NULL DEFAULT 36",
            'text_color'       => "varchar(7)           NOT NULL DEFAULT '#ffffff'",
            'text_opacity'     => "tinyint(3) UNSIGNED  NOT NULL DEFAULT 80",
            'text_offset_x'    => "smallint(6)          NOT NULL DEFAULT 10",
            'text_offset_y'    => "smallint(6)          NOT NULL DEFAULT 10",
        ];

        foreach ( $new_cols as $col => $def ) {
            if ( ! in_array( $col, $existing, true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$col} {$def}" );
            }
        }
    }

    /** Return all templates ordered by name. */
    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wm_templates ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    /** Return one template by ID, or null. */
    public static function get( int $id ): ?array {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wm_templates WHERE id = %d", $id ),
            ARRAY_A
        ) ?: null;
    }

    /** Insert or update a template. Returns the row ID on success, false on failure. */
    public static function save( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'wm_templates';

        $row = [
            // Image watermark
            'name'             => sanitize_text_field( $data['name'] ),
            'wm_id'            => absint( $data['wm_id'] ?? 0 ),
            'position'         => self::sanitize_wm_position( $data['position'] ?? 'bottom-right' ),
            'offset_x'         => absint( $data['offset_x'] ?? 10 ),
            'offset_y'         => absint( $data['offset_y'] ?? 10 ),
            'size_pct'         => max( 1,  min( 100, absint( $data['size_pct']  ?? 20 ) ) ),
            'opacity'          => max( 10, min( 100, absint( $data['opacity']   ?? 80 ) ) ),
            // Text watermark
            'text_enabled'     => empty( $data['text_enabled'] ) ? 0 : 1,
            'text_content'     => sanitize_text_field( $data['text_content']     ?? '' ),
            'text_position'    => self::sanitize_wm_position( $data['text_position'] ?? 'bottom-right' ),
            'text_align'       => in_array( $data['text_align'] ?? '', [ 'left', 'center', 'right' ], true )
                                  ? $data['text_align'] : 'center',
            'text_font_family' => sanitize_text_field( $data['text_font_family'] ?? 'auto' ),
            'text_font_path'   => sanitize_text_field( $data['text_font_path']   ?? '' ),
            'text_font_size'   => max( 12, min( 200, absint( $data['text_font_size'] ?? 36 ) ) ),
            'text_color'       => self::sanitize_hex_color( $data['text_color']  ?? '#ffffff' ),
            'text_opacity'     => max( 10, min( 100, absint( $data['text_opacity'] ?? 80 ) ) ),
            'text_offset_x'    => absint( $data['text_offset_x'] ?? 10 ),
            'text_offset_y'    => absint( $data['text_offset_y'] ?? 10 ),
        ];

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $table, $row, [ 'id' => absint( $data['id'] ) ], null, [ '%d' ] );
            return absint( $data['id'] );
        }

        $wpdb->insert( $table, $row );
        return $wpdb->insert_id ?: false;
    }

    /** Delete a template by ID. */
    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            $wpdb->prefix . 'wm_templates',
            [ 'id' => $id ],
            [ '%d' ]
        );
    }

    // -------------------------------------------------------------------------
    // Sanitizers
    // -------------------------------------------------------------------------

    public static function sanitize_wm_position( string $pos ): string {
        $allowed = [
            'top-left',    'top-center',    'top-right',
            'middle-left', 'middle-center', 'middle-right',
            'bottom-left', 'bottom-center', 'bottom-right',
            'edge-top',    'edge-bottom',   'edge-left',   'edge-right',
        ];
        return in_array( $pos, $allowed, true ) ? $pos : 'bottom-right';
    }

    private static function sanitize_hex_color( string $color ): string {
        return preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ? $color : '#ffffff';
    }
}
