<?php
/**
 * Plugin Name: Stitch to UX Builder
 * Plugin URI: https://github.com/tuend-work/ultimate-wp-stitch-to-uxbuilder
 * Description: Import HTML from AI tools (Stitch, v0, Bolt…) into Flatsome UX Builder with dynamic data support. Adds ultimate_section container and child elements (text, image, link) with slot-based template system.
 * Version: 1.0.1
 * Author: Tuend Work
 * Author URI: https://tuend.work
 * License: GPL-2.0-or-later
 * Text Domain: stitch-to-uxbuilder
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'STU_VERSION', '1.1.3' );
define( 'STU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Flatsome theme (or child) is active.
 */
function stu_is_flatsome_active() {
    $theme = wp_get_theme();
    $parent = $theme->parent();
    $theme_name = $theme->get( 'Name' );
    $parent_name = $parent ? $parent->get( 'Name' ) : '';
    return ( 'Flatsome' === $theme_name || 'Flatsome' === $parent_name );
}

/**
 * Initialize plugin.
 */
function stu_init() {
    // Load helpers
    require_once STU_PLUGIN_DIR . 'includes/helpers.php';
    require_once STU_PLUGIN_DIR . 'includes/frontend.php';
    require_once STU_PLUGIN_DIR . 'includes/class-dynamic-resolver.php';

    // Register shortcodes (always, so content renders on frontend)
    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-text/element.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-image/element.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-link/element.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ultimate-section/element.php';

    // Import tool (admin only)
    if ( is_admin() ) {
        require_once STU_PLUGIN_DIR . 'ux-elements/import-tool/meta-box.php';
        require_once STU_PLUGIN_DIR . 'ux-elements/import-tool/ajax-handler.php';
        require_once STU_PLUGIN_DIR . 'includes/updater.php';
    }
}
add_action( 'init', 'stu_init', 20 );

/**
 * Register UX Builder elements (only when UX Builder is active).
 */
function stu_register_ux_elements() {
    // Check if UX Builder functions exist
    if ( ! function_exists( 'add_ux_builder_shortcode' ) ) {
        return;
    }

    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-text/options.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-image/options.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ux-field-link/options.php';
    require_once STU_PLUGIN_DIR . 'ux-elements/ultimate-section/options.php';
}
add_action( 'ux_builder_setup', 'stu_register_ux_elements' );

/**
 * Show admin notice if Flatsome is not active.
 */
function stu_admin_notice_no_flatsome() {
    if ( stu_is_flatsome_active() ) {
        return;
    }
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong><?php esc_html_e( 'Stitch to UX Builder', 'stitch-to-uxbuilder' ); ?>:</strong>
            <?php esc_html_e( 'This plugin requires the Flatsome theme to be active. UX Builder elements will not be available in the visual editor, but shortcodes will still render on the frontend.', 'stitch-to-uxbuilder' ); ?>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'stu_admin_notice_no_flatsome' );
