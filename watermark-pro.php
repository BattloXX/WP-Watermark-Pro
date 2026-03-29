<?php
/**
 * Plugin Name: Watermark Pro
 * Description: PNG/EPS-Wasserzeichen und Text-Wasserzeichen auf Bilder anwenden – mit Positionierung, Größe, Vorlagen.
 * Version:     1.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Johannes Battlogg
 * Text Domain: watermark-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'WM_VERSION', '1.1.0' );
define( 'WM_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WM_URL',     plugin_dir_url( __FILE__ ) );

require_once WM_DIR . 'includes/class-wm-templates.php';
require_once WM_DIR . 'includes/class-wm-processor.php';
require_once WM_DIR . 'includes/class-wm-admin.php';

register_activation_hook( __FILE__, [ 'WM_Templates', 'create_table' ] );

add_action( 'plugins_loaded', static function () {
    WM_Templates::maybe_upgrade();
    WM_Admin::get_instance();
} );
