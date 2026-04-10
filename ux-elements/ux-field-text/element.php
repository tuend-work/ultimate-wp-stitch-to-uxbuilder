<?php
/**
 * UX Field Text — Element registration and shortcode render.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    $output = $atts['value'];

    if ( '1' === $atts['dynamic_enabled'] && ! empty( $atts['dynamic_source'] ) ) {
        $dynamic_value = STU_Dynamic_Resolver::resolve( $atts['dynamic_source'] );
        if ( ! empty( $dynamic_value ) ) {
            $output = $dynamic_value;
        }
    }

    if ( '' === $output && ! empty( $content ) ) {
        $output = $content;
    }

    if ( '' === $output && empty( $content ) ) {
        return '';
    }

    $allowed_tags = array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div' );
    $tag = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'p';

    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    $decoded_output = html_entity_decode( $output );
    return '<' . $tag . $class_attr . '>' . wp_kses( $decoded_output, stu_get_allowed_slot_html() ) . '</' . $tag . '>';
}
add_shortcode( 'ux_field_text', 'stu_render_field_text' );
