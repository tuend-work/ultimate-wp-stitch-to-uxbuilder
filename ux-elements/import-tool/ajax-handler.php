<?php
/**
 * Import Tool — AJAX handler for preview and import.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once STU_PLUGIN_DIR . 'ux-elements/import-tool/import-tool.php';

/**
 * AJAX handler: Preview parsed HTML.
 *
 * Receives HTML, parses it, and returns the list of elements + generated shortcode.
 */
function stu_ajax_preview_import() {
    // Verify nonce
    if ( ! check_ajax_referer( 'stu_import_ajax', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stitch-to-uxbuilder' ) ), 403 );
    }

    // Check permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'stitch-to-uxbuilder' ) ), 403 );
    }

    // Get HTML input or ZIP file
    $html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $sections = array();

    if ( ! empty( $_FILES['file'] ) && $_FILES['file']['error'] === UPLOAD_ERR_OK ) {
        $file_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];

        if ( preg_match( '/\.zip$/i', $file_name ) ) {
            $result = STU_Import_Tool::parse_zip( $file_path );
            if ( isset( $result['error'] ) ) {
                wp_send_json_error( array( 'message' => $result['error'] ) );
            }
            $sections = $result['sections'];
            $assets = $result['assets'];
        } else {
            $html = file_get_contents( $file_path );
            $result = STU_Import_Tool::parse_multi_sections( $html );
            $sections = $result['sections'];
        }
    } elseif ( ! empty( $html ) ) {
        $result = STU_Import_Tool::parse_multi_sections( $html );
        $sections = $result['sections'];
    }

    if ( empty( $sections ) ) {
        wp_send_json_error( array( 'message' => __( 'No content found to parse.', 'stitch-to-uxbuilder' ) ) );
    }

    // Check for duplicate (Disabled strictly for testing/resubmission)
    $is_duplicate = false;

    // Count existing HTML nodes in the post
    $existing_sections = 0;
    if ( $post_id > 0 ) {
        $current_content = get_post_field( 'post_content', $post_id );
        $existing_sections = substr_count( $current_content, '[ux_html_node' );
    }

    // Generate shortcode
    $shortcode = STU_Import_Tool::generate_multi_shortcode( $sections );

    wp_send_json_success( array(
        'sections'          => $sections,
        'shortcode'         => $shortcode,
        'is_duplicate'      => $is_duplicate,
        'existing_sections' => $existing_sections,
    ) );
}
add_action( 'wp_ajax_stu_preview_import', 'stu_ajax_preview_import' );

/**
 * AJAX handler: Confirm import — append shortcode to post content.
 */
function stu_ajax_confirm_import() {
    // Verify nonce
    if ( ! check_ajax_referer( 'stu_import_ajax', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stitch-to-uxbuilder' ) ), 403 );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    // Check permissions
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'stitch-to-uxbuilder' ) ), 403 );
    }

    // Use the same parsing logic found in preview but for confirmation
    $sections = array();
    $html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';

    if ( ! empty( $_FILES['file'] ) && $_FILES['file']['error'] === UPLOAD_ERR_OK ) {
        $file_path = $_FILES['file']['tmp_name'];
        if ( preg_match( '/\.zip$/i', $_FILES['file']['name'] ) ) {
            $result = STU_Import_Tool::parse_zip( $file_path );
            if ( isset( $result['error'] ) ) {
                wp_send_json_error( array( 'message' => $result['error'] ) );
            }
            $sections = $result['sections'];
            $assets = $result['assets'];
        } else {
            $html = file_get_contents( $file_path );
            $result = STU_Import_Tool::parse_multi_sections( $html );
            $sections = $result['sections'];
            $assets = $result['assets'];
        }
    } elseif ( ! empty( $html ) ) {
        $result = STU_Import_Tool::parse_multi_sections( $html );
        $sections = $result['sections'];
        $assets = $result['assets'];
    }

    if ( empty( $sections ) || isset( $sections['error'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No content found to parse.', 'stitch-to-uxbuilder' ) ) );
    }

    // Apply dynamic source overrides
    $dynamic_overrides = isset( $_POST['dynamic_sources'] ) ? json_decode( wp_unslash( $_POST['dynamic_sources'] ), true ) : array();

    if ( is_array( $dynamic_overrides ) && ! empty( $dynamic_overrides ) ) {
        foreach ( $sections as &$section ) {
            foreach ( $section['elements'] as &$element ) {
                $slot = $element['slot'];
                if ( isset( $dynamic_overrides[ $slot ] ) && ! empty( $dynamic_overrides[ $slot ] ) ) {
                    $element['dynamic_enabled'] = '1';
                    $val = sanitize_text_field( $dynamic_overrides[ $slot ] );
                    if ( 'ux_field_link' === $element['type'] ) {
                        $element['dynamic_href'] = $val;
                    } else {
                        $element['dynamic_source'] = $val;
                    }
                }
            }
        }
        unset( $section, $element );
    }

    // 3. Localize Images if requested
    $download_images = isset( $_POST['download_images'] ) && '1' === $_POST['download_images'];
    if ( $download_images ) {
        STU_Import_Tool::localize_images( $sections );
    }

    // Generate final shortcodes using the already parsed data
    $final_shortcode = '';
    foreach ( $sections as $section_data ) {
        $final_shortcode .= STU_Import_Tool::generate_shortcode( $section_data ) . "\n";
    }

    // Append to post content
    $current_content = get_post_field( 'post_content', $post_id );
    $updated_content = trim( $current_content ) . "\n" . trim( $final_shortcode );

    $result = wp_update_post( array(
        'ID'           => $post_id,
        'post_content' => $updated_content,
    ), true );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // 1. Process and save Styles
    if ( ! empty( $assets['styles'] ) ) {
        foreach ( $assets['styles'] as $style ) {
            $is_ext = ( 'external' === $style['type'] );
            $content = $is_ext ? $style['src'] : $style['content'];
            $hash = substr( md5( $content ), 0, 8 );
            $type_tag = $is_ext ? 'ext' : 'inline';
            
            $meta_key = sprintf( 'stu-style-%s-%s', $type_tag, $hash );
            update_post_meta( $post_id, $meta_key, $content );
        }
    }

    // 2. Process and save Scripts
    if ( ! empty( $assets['scripts'] ) ) {
        foreach ( $assets['scripts'] as $script ) {
            $is_ext = ( 'external' === $script['type'] );
            $content = $is_ext ? $script['src'] : $script['content'];
            $hash = substr( md5( $content ), 0, 8 );
            $type_tag = $is_ext ? 'ext' : 'inline';

            $meta_key = sprintf( 'stu-script-%s-%s', $type_tag, $hash );
            update_post_meta( $post_id, $meta_key, $content );
        }
    }

    // Record import hash for the overall content
    $cumulative_html = '';
    foreach ( $sections as $sec ) { $cumulative_html .= $sec['template']; }
    STU_Import_Tool::record_import( $post_id, $cumulative_html );

    wp_send_json_success( array(
        'message'   => __( 'Import successful! Assets deduplicated by content hash and saved to custom fields.', 'stitch-to-uxbuilder' ),
        'shortcode' => $final_shortcode,
    ) );
}
add_action( 'wp_ajax_stu_confirm_import', 'stu_ajax_confirm_import' );

/**
 * Generate shortcode with dynamic source overrides applied.
 *
 * @param array $parsed Parsed data with potential dynamic overrides.
 * @return string Complete shortcode string.
 */
function stu_generate_shortcode_with_overrides( $parsed ) {
    $template = $parsed['template'];
    $elements = $parsed['elements'];

    // Start opening tag (new content system)
    $shortcode = '[ux_ultimate_section tag="div" css_class=""]' . "\n";

    // 1. Add the HTML template as raw content
    $shortcode .= $template . "\n";

    // 2. Add child shortcodes with overrides
    foreach ( $elements as $el ) {
        $type = $el['type'];
        $dynamic_enabled = isset( $el['dynamic_enabled'] ) && '1' === $el['dynamic_enabled'] ? '1' : '0';
        $dynamic_source = isset( $el['dynamic_source'] ) ? $el['dynamic_source'] : '';

        $attrs = array(
            'slot'            => $el['slot'],
            'dynamic_enabled' => $dynamic_enabled,
        );

        switch ( $type ) {
            case 'ux_field_image':
                $attrs['src'] = $el['src'] ?? '';
                $attrs['alt'] = $el['alt'] ?? '';
                if ( ! empty( $el['css_class'] ) ) { $attrs['css_class'] = $el['css_class']; }
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    $attrs['dynamic_source'] = $dynamic_source;
                }
                $shortcode .= stu_build_shortcode_tag_helper( 'ux_field_image', $attrs ) . "\n";
                break;

            case 'ux_field_text':
                $attrs['tag'] = $el['tag'] ?? 'p';
                $attrs['value'] = $el['value'] ?? '';
                if ( ! empty( $el['css_class'] ) ) { $attrs['css_class'] = $el['css_class']; }
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    $attrs['dynamic_source'] = $dynamic_source;
                }
                $shortcode .= stu_build_shortcode_tag_helper( 'ux_field_text', $attrs ) . "\n";
                break;

            case 'ux_field_link':
                $attrs['href'] = $el['href'] ?? '#';
                $attrs['label'] = $el['label'] ?? '';
                $attrs['target'] = $el['target'] ?? '_self';
                if ( ! empty( $el['css_class'] ) ) { $attrs['css_class'] = $el['css_class']; }
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    $attrs['dynamic_href'] = $dynamic_source;
                }
                $shortcode .= stu_build_shortcode_tag_helper( 'ux_field_link', $attrs ) . "\n";
                break;
        }
    }

    $shortcode .= '[/ux_ultimate_section]';

    return $shortcode;
}

/**
 * Helper to build shortcode tags in AJAX handler.
 */
function stu_build_shortcode_tag_helper( $tag, $attrs ) {
    $parts = array();
    foreach ( $attrs as $key => $val ) {
        if ( '' !== $val ) {
            $parts[] = $key . '="' . esc_attr( $val ) . '"';
        }
    }
    return '[' . $tag . ' ' . implode( ' ', $parts ) . ']';
}
