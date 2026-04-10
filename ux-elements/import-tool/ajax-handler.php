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
            $sections = STU_Import_Tool::parse_zip( $file_path );
            if ( isset( $sections['error'] ) ) {
                wp_send_json_error( array( 'message' => $sections['error'] ) );
            }
        } else {
            $html = file_get_contents( $file_path );
            $sections = STU_Import_Tool::parse_multi_sections( $html );
        }
    } elseif ( ! empty( $html ) ) {
        $sections = STU_Import_Tool::parse_multi_sections( $html );
    }

    if ( empty( $sections ) ) {
        wp_send_json_error( array( 'message' => __( 'No content found to parse.', 'stitch-to-uxbuilder' ) ) );
    }

    // Check for duplicate (based on cumulative HTML if multi-section)
    $is_duplicate = false;
    if ( $post_id > 0 ) {
        $cumulative_html = '';
        foreach ( $sections as $sec ) {
            $cumulative_html .= $sec['template'];
        }
        $is_duplicate = STU_Import_Tool::is_duplicate( $post_id, $cumulative_html );
    }

    // Count existing sections in the post
    $existing_sections = 0;
    if ( $post_id > 0 ) {
        $current_content = get_post_field( 'post_content', $post_id );
        $existing_sections = substr_count( $current_content, '[ux_ultimate_section' );
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
            $sections = STU_Import_Tool::parse_zip( $file_path );
        } else {
            $html = file_get_contents( $file_path );
            $sections = STU_Import_Tool::parse_multi_sections( $html );
        }
    } elseif ( ! empty( $html ) ) {
        $sections = STU_Import_Tool::parse_multi_sections( $html );
    }

    if ( empty( $sections ) || isset( $sections['error'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No content found to parse.', 'stitch-to-uxbuilder' ) ) );
    }

    // Apply dynamic source overrides
    if ( is_array( $dynamic_overrides ) && ! empty( $dynamic_overrides ) ) {
        foreach ( $sections as &$section ) {
            foreach ( $section['elements'] as &$element ) {
                $slot = $element['slot'];
                if ( isset( $dynamic_overrides[ $slot ] ) && ! empty( $dynamic_overrides[ $slot ] ) ) {
                    $element['dynamic_enabled'] = '1';
                    $element['dynamic_source'] = sanitize_text_field( $dynamic_overrides[ $slot ] );
                }
            }
        }
        unset( $section, $element );
    }

    // Generate multi-shortcode
    $final_shortcode = '';
    foreach ( $sections as $section ) {
        $final_shortcode .= stu_generate_shortcode_with_overrides( $section ) . "\n";
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

    // Record import hash
    $cumulative_html = '';
    foreach ( $sections as $sec ) { $cumulative_html .= $sec['template']; }
    STU_Import_Tool::record_import( $post_id, $cumulative_html );

    wp_send_json_success( array(
        'message'   => __( 'Import successful! Sections appended to post content.', 'stitch-to-uxbuilder' ),
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
