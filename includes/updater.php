<?php
/**
 * GitHub Auto-Updater for Stitch to UX Builder
 * 
 * Provides a manual update trigger from GitHub main branch.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add an Update button to the admin notices area
 */
add_action( 'admin_notices', 'stu_render_update_button' );

function stu_render_update_button() {
    $screen = get_current_screen();
    // Only show on post editor screen
    if ( ! $screen || 'post' !== $screen->base ) {
        return;
    }

    $update_url = wp_nonce_url( admin_url( 'admin-post.php?action=stu_github_update&post_id=' . get_the_ID() ), 'stu_update_nonce' );
    ?>
    <div class="notice notice-warning is-dismissible" style="border-left-color: #0073aa; display: flex; align-items: center; justify-content: space-between; padding: 10px 20px;">
        <div style="font-weight: 600;">
            🚀 Stitch to UX Builder — GitHub Auto-Updater
        </div>
        <div>
            <a href="<?php echo esc_url( $update_url ); ?>" class="button button-primary" style="background: #0073aa; border-color: #0073aa;">
                <span class="dashicons dashicons-cloud-download" style="margin-top: 4px;"></span> 
                UPDATE FROM GITHUB NOW
            </a>
        </div>
    </div>
    <?php
}

/**
 * Handle the update request
 */
add_action( 'admin_post_stu_github_update', 'stu_handle_update' );

function stu_handle_update() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized action.' );
    }

    check_admin_referer( 'stu_update_nonce' );

    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    // ZIP URL from GitHub main branch
    //$github_zip_url = 'https://github.com/tuend-work/ultimate-wp-stitch-to-uxbuilder/archive/refs/heads/main.zip';
    $github_zip_url = 'https://github.com/tuend-work/ultimate-wp-stitch-to-uxbuilder/archive/refs/heads/2.0-alpha.zip';
  
    $temp_file = download_url( $github_zip_url );

    if ( is_wp_error( $temp_file ) ) {
        wp_die( 'Download failed: ' . $temp_file->get_error_message() );
    }

    // Initialize Filesystem
    WP_Filesystem();
    global $wp_filesystem;

    $destination = STU_PLUGIN_DIR;
    $temp_dir = STU_PLUGIN_DIR . 'temp_update_dir/';
    
    // Ensure temp dir exists and is clean
    $wp_filesystem->delete( $temp_dir, true );
    if ( ! $wp_filesystem->mkdir( $temp_dir ) ) {
        wp_die( 'Cannot create temp directory.' );
    }

    // Unzip to temp folder
    $unzipped = unzip_file( $temp_file, $temp_dir );
    if ( file_exists( $temp_file ) ) {
        unlink( $temp_file );
    }

    if ( is_wp_error( $unzipped ) ) {
        $wp_filesystem->delete( $temp_dir, true );
        wp_die( 'Unzip failed: ' . $unzipped->get_error_message() );
    }

    // GitHub zips have a subfolder like 'ultimate-wp-stitch-to-uxbuilder-main'
    $contents = $wp_filesystem->dirlist( $temp_dir );
    if ( ! empty( $contents ) ) {
        $inner_dir_name = key( $contents );
        $inner_dir_path = $temp_dir . $inner_dir_name . '/';
        
        // Move contents from inner dir to our plugin root
        if ( ! copy_dir( $inner_dir_path, $destination ) ) {
             wp_die( 'Failed to copy updated files.' );
        }
    }

    // Cleanup
    $wp_filesystem->delete( $temp_dir, true );

    // Redirect back to the post editor with success flag
    $redirect_url = admin_url( 'post.php?post=' . $post_id . '&action=edit&stu_updated=1' );
    wp_safe_redirect( $redirect_url );
    exit;
}

/**
 * Success Notice
 */
add_action( 'admin_notices', 'stu_update_success_notice' );
function stu_update_success_notice() {
    if ( isset( $_GET['stu_updated'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Stitch to UX Builder updated successfully from GitHub!</p></div>';
    }
}
