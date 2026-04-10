<?php
/**
 * UX Field Text — Element registration and shortcode render.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the [ux_field_text] shortcode.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode inner content (unused).
 * @return string Rendered HTML.
 */
function stu_render_field_text( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'slot'            => '',
            'tag'             => 'p',
            'value'           => '',
            'css_class'       => '',
            'dynamic_enabled' => '0',
            'dynamic_source'  => '',
            'visibility'      => '',
        ),
        $atts,
        'ux_field_text'
    );

    // Determine output value
    $output = $atts['value'];

    // Dynamic source resolution
    if ( '1' === $atts['dynamic_enabled'] && ! empty( $atts['dynamic_source'] ) ) {
        $dynamic_value = STU_Dynamic_Resolver::resolve( $atts['dynamic_source'] );
        if ( ! empty( $dynamic_value ) ) {
            $output = $dynamic_value;
        }
        // Fallback to static value if dynamic returns empty
    }

    // If no output at all, ensure we return something for UX Builder to grab onto
    if ( '' === $output && empty( $content ) ) {
        if ( function_exists( 'is_ux_builder' ) && is_ux_builder() ) {
            $output = '&nbsp;'; // Placeholder for editor
        } else {
            return '';
        }
    }

    // Allow inner content as fallback
    if ( '' === $output && ! empty( $content ) ) {
        $output = $content;
    }

    // Allowed tags for the wrapper
    $allowed_tags = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div' );
    $tag = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'p';

    // Build class attribute
    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    // Render - Decode entities to allow <img> tags from SVGs to render properly
    $decoded_output = html_entity_decode( $output );
    return '<' . $tag . $class_attr . '>' . wp_kses( $decoded_output, stu_get_allowed_slot_html() ) . '</' . $tag . '>';
}
add_shortcode( 'ux_field_text', 'stu_render_field_text' );
