<?php
/**
 * Import Tool — Meta box registration and asset enqueue.
 *
 * Registers a meta box on all post types for HTML import.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the import tool meta box for all public post types.
 */
function stu_register_import_meta_box() {
    $post_types = get_post_types( array( 'public' => true ), 'names' );

    foreach ( $post_types as $post_type ) {
        add_meta_box(
            'stu_import_tool',
            __( '⚡ Stitch to UX Builder — Import HTML', 'stitch-to-uxbuilder' ),
            'stu_render_import_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'stu_register_import_meta_box' );

/**
 * Render the import tool meta box.
 *
 * @param WP_Post $post Current post object.
 */
function stu_render_import_meta_box( $post ) {
    wp_nonce_field( 'stu_import_nonce_action', 'stu_import_nonce' );
    ?>
    <div id="stu-import-tool" class="stu-import-wrapper">
        <!-- Tabs -->
        <div class="stu-tabs">
            <button type="button" class="stu-tab stu-tab--active" data-tab="paste">
                <?php esc_html_e( 'Paste HTML', 'stitch-to-uxbuilder' ); ?>
            </button>
            <button type="button" class="stu-tab" data-tab="upload">
                <?php esc_html_e( 'Upload .html File', 'stitch-to-uxbuilder' ); ?>
            </button>
        </div>

        <!-- Paste tab -->
        <div class="stu-tab-content stu-tab-content--active" data-tab-content="paste">
            <textarea id="stu-html-input" class="stu-html-input" rows="12"
                      placeholder="<?php esc_attr_e( 'Paste your HTML here...', 'stitch-to-uxbuilder' ); ?>"></textarea>
        </div>

        <!-- Upload tab -->
        <div class="stu-tab-content" data-tab-content="upload">
            <div class="stu-upload-zone" id="stu-upload-zone">
                <input type="file" id="stu-file-input" accept=".html,.htm,.zip" class="stu-file-input" />
                <div class="stu-upload-label">
                    <span class="dashicons dashicons-upload"></span>
                    <p><?php esc_html_e( 'Drop .html or .zip file here or click to select', 'stitch-to-uxbuilder' ); ?></p>
                </div>
                <div class="stu-file-name" id="stu-file-name" style="display:none;"></div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="stu-actions">
            <button type="button" id="stu-preview-btn" class="button button-secondary">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e( 'Preview', 'stitch-to-uxbuilder' ); ?>
            </button>
            <button type="button" id="stu-import-btn" class="button button-primary" disabled>
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Confirm Import', 'stitch-to-uxbuilder' ); ?>
            </button>
        </div>

        <!-- Preview area -->
        <div id="stu-preview-area" class="stu-preview-area" style="display:none;">
            <h4><?php esc_html_e( 'Import Preview', 'stitch-to-uxbuilder' ); ?></h4>
            <div id="stu-preview-warning" class="notice notice-warning inline" style="display:none;">
                <p></p>
            </div>
            <div id="stu-duplicate-warning" class="notice notice-error inline" style="display:none;">
                <p><?php esc_html_e( 'This HTML has already been imported to this post.', 'stitch-to-uxbuilder' ); ?></p>
            </div>
            <table class="widefat" id="stu-preview-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slot', 'stitch-to-uxbuilder' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'stitch-to-uxbuilder' ); ?></th>
                        <th><?php esc_html_e( 'Content / Src', 'stitch-to-uxbuilder' ); ?></th>
                        <th><?php esc_html_e( 'Dynamic Source', 'stitch-to-uxbuilder' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <h4><?php esc_html_e( 'Generated Shortcode', 'stitch-to-uxbuilder' ); ?></h4>
            <pre id="stu-shortcode-preview" class="stu-shortcode-preview"></pre>
        </div>

        <!-- Status messages -->
        <div id="stu-status" class="stu-status" style="display:none;"></div>

        <input type="hidden" id="stu-post-id" value="<?php echo esc_attr( $post->ID ); ?>" />
    </div>
    <?php
}

/**
 * Enqueue scripts and styles for the import tool meta box.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function stu_enqueue_import_assets( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    wp_enqueue_style(
        'stu-import-tool',
        STU_PLUGIN_URL . 'ux-elements/import-tool/assets/import-tool.css',
        array(),
        STU_VERSION
    );

    wp_enqueue_script(
        'stu-import-tool',
        STU_PLUGIN_URL . 'ux-elements/import-tool/assets/import-tool.js',
        array( 'jquery' ),
        STU_VERSION,
        true
    );

    wp_localize_script( 'stu-import-tool', 'stuImport', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'stu_import_ajax' ),
        'postId'   => get_the_ID(),
        'strings'  => array(
            'parsing'       => __( 'Parsing HTML...', 'stitch-to-uxbuilder' ),
            'importing'     => __( 'Importing...', 'stitch-to-uxbuilder' ),
            'success'       => __( 'Import successful! Shortcode appended to post content.', 'stitch-to-uxbuilder' ),
            'error'         => __( 'An error occurred. Please try again.', 'stitch-to-uxbuilder' ),
            'emptyHtml'     => __( 'Please paste HTML or upload a file first.', 'stitch-to-uxbuilder' ),
            'noElements'    => __( 'No parseable elements found in this HTML.', 'stitch-to-uxbuilder' ),
            'duplicate'     => __( 'This HTML has already been imported.', 'stitch-to-uxbuilder' ),
            'confirmImport' => __( 'This will append a new section to the post content. Continue?', 'stitch-to-uxbuilder' ),
            'existingSections' => __( 'This post already has %d existing section(s). New content will be appended.', 'stitch-to-uxbuilder' ),
        ),
        'dynamicSources' => array(
            'text' => array(
                ''               => __( '— None (static) —', 'stitch-to-uxbuilder' ),
                'post:title'     => __( 'Post Title', 'stitch-to-uxbuilder' ),
                'post:excerpt'   => __( 'Post Excerpt', 'stitch-to-uxbuilder' ),
                'post:date'      => __( 'Post Date', 'stitch-to-uxbuilder' ),
                'post:author'    => __( 'Post Author', 'stitch-to-uxbuilder' ),
                'woo:price'      => __( 'Product Price', 'stitch-to-uxbuilder' ),
                'woo:sku'        => __( 'Product SKU', 'stitch-to-uxbuilder' ),
                'woo:title'      => __( 'Product Title', 'stitch-to-uxbuilder' ),
                'woo:short_description' => __( 'Product Short Description', 'stitch-to-uxbuilder' ),
            ),
            'image' => array(
                ''               => __( '— None (static) —', 'stitch-to-uxbuilder' ),
                'post:thumbnail' => __( 'Post Thumbnail', 'stitch-to-uxbuilder' ),
                'woo:thumbnail'  => __( 'Product Image', 'stitch-to-uxbuilder' ),
                'woo:gallery'    => __( 'Product Gallery (first)', 'stitch-to-uxbuilder' ),
            ),
            'link' => array(
                ''               => __( '— None (static) —', 'stitch-to-uxbuilder' ),
                'post:permalink' => __( 'Post Permalink', 'stitch-to-uxbuilder' ),
                'woo:permalink'  => __( 'Product URL', 'stitch-to-uxbuilder' ),
                'woo:add_to_cart_url' => __( 'Add to Cart URL', 'stitch-to-uxbuilder' ),
            ),
        ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'stu_enqueue_import_assets' );
