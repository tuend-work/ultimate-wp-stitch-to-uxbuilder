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

    // Get HTML input - use wp_unslash to handle WordPress magic quotes
    $html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    if ( empty( $html ) ) {
        wp_send_json_error( array( 'message' => __( 'No HTML provided.', 'stitch-to-uxbuilder' ) ) );
    }

    // Parse HTML
    $parsed = STU_Import_Tool::parse_html( $html );

    // Check for duplicate
    $is_duplicate = false;
    if ( $post_id > 0 ) {
        $is_duplicate = STU_Import_Tool::is_duplicate( $post_id, $html );
    }

    // Count existing sections in the post
    $existing_sections = 0;
    if ( $post_id > 0 ) {
        $current_content = get_post_field( 'post_content', $post_id );
        $existing_sections = substr_count( $current_content, '[ux_ultimate_section' );
    }

    // Generate shortcode
    $shortcode = STU_Import_Tool::generate_shortcode( $parsed );

    wp_send_json_success( array(
        'elements'          => $parsed['elements'],
        'template'          => $parsed['template'],
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

    // Get HTML and dynamic_sources overrides
    $html = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
    $dynamic_overrides = isset( $_POST['dynamic_sources'] ) ? json_decode( wp_unslash( $_POST['dynamic_sources'] ), true ) : array();

    if ( empty( $html ) ) {
        wp_send_json_error( array( 'message' => __( 'No HTML provided.', 'stitch-to-uxbuilder' ) ) );
    }

    // Check duplicate
    if ( STU_Import_Tool::is_duplicate( $post_id, $html ) ) {
        wp_send_json_error( array( 'message' => __( 'This HTML has already been imported to this post.', 'stitch-to-uxbuilder' ) ) );
    }

    // Parse HTML
    $parsed = STU_Import_Tool::parse_html( $html );

    // Apply dynamic source overrides from the preview UI
    if ( is_array( $dynamic_overrides ) && ! empty( $dynamic_overrides ) ) {
        foreach ( $parsed['elements'] as &$element ) {
            $slot = $element['slot'];
            if ( isset( $dynamic_overrides[ $slot ] ) && ! empty( $dynamic_overrides[ $slot ] ) ) {
                $element['dynamic_enabled'] = '1';
                $element['dynamic_source'] = sanitize_text_field( $dynamic_overrides[ $slot ] );
            }
        }
        unset( $element );
    }

    // Generate shortcode with dynamic overrides
    $shortcode = stu_generate_shortcode_with_overrides( $parsed );

    // Append to post content
    $current_content = get_post_field( 'post_content', $post_id );
    $updated_content = $current_content . "\n" . $shortcode;

    $result = wp_update_post( array(
        'ID'           => $post_id,
        'post_content' => $updated_content,
    ), true );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // Record import hash
    STU_Import_Tool::record_import( $post_id, $html );

    wp_send_json_success( array(
        'message'   => __( 'Import successful! Shortcode appended to post content.', 'stitch-to-uxbuilder' ),
        'shortcode' => $shortcode,
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

    // Escape template for shortcode attribute
    $template_escaped = str_replace( '"', '&quot;', $template );

    $shortcode = '[ux_ultimate_section html_template="' . $template_escaped . '" tag="div" css_class=""]' . "\n";

    foreach ( $elements as $el ) {
        $type = $el['type'];
        $dynamic_enabled = isset( $el['dynamic_enabled'] ) && '1' === $el['dynamic_enabled'] ? '1' : '0';
        $dynamic_source = isset( $el['dynamic_source'] ) ? $el['dynamic_source'] : '';

        switch ( $type ) {
            case 'ux_field_image':
                $attrs = 'slot="' . esc_attr( $el['slot'] ) . '"';
                $attrs .= ' src="' . esc_attr( $el['src'] ?? '' ) . '"';
                $attrs .= ' alt="' . esc_attr( $el['alt'] ?? '' ) . '"';
                if ( ! empty( $el['css_class'] ) ) {
                    $attrs .= ' css_class="' . esc_attr( $el['css_class'] ) . '"';
                }
                $attrs .= ' dynamic_enabled="' . $dynamic_enabled . '"';
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    $attrs .= ' dynamic_source="' . esc_attr( $dynamic_source ) . '"';
                }
                $shortcode .= '  [ux_field_image ' . $attrs . ']' . "\n";
                break;

            case 'ux_field_text':
                $value = str_replace( '"', '&quot;', $el['value'] ?? '' );
                $attrs = 'slot="' . esc_attr( $el['slot'] ) . '"';
                $attrs .= ' tag="' . esc_attr( $el['tag'] ?? 'p' ) . '"';
                $attrs .= ' value="' . $value . '"';
                if ( ! empty( $el['css_class'] ) ) {
                    $attrs .= ' css_class="' . esc_attr( $el['css_class'] ) . '"';
                }
                $attrs .= ' dynamic_enabled="' . $dynamic_enabled . '"';
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    $attrs .= ' dynamic_source="' . esc_attr( $dynamic_source ) . '"';
                }
                $shortcode .= '  [ux_field_text ' . $attrs . ']' . "\n";
                break;

            case 'ux_field_link':
                $attrs = 'slot="' . esc_attr( $el['slot'] ) . '"';
                $attrs .= ' href="' . esc_attr( $el['href'] ?? '#' ) . '"';
                $label = str_replace( '"', '&quot;', $el['label'] ?? '' );
                $attrs .= ' label="' . $label . '"';
                $attrs .= ' target="' . esc_attr( $el['target'] ?? '_self' ) . '"';
                if ( ! empty( $el['css_class'] ) ) {
                    $attrs .= ' css_class="' . esc_attr( $el['css_class'] ) . '"';
                }
                $attrs .= ' dynamic_enabled="' . $dynamic_enabled . '"';
                if ( '1' === $dynamic_enabled && $dynamic_source ) {
                    // For links, the dynamic source maps to dynamic_href
                    $attrs .= ' dynamic_href="' . esc_attr( $dynamic_source ) . '"';
                }
                $shortcode .= '  [ux_field_link ' . $attrs . ']' . "\n";
                break;
        }
    }

    $shortcode .= '[/ux_ultimate_section]';

    return $shortcode;
}
