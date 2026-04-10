<?php
/**
 * HTML Node — Renderer.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function stu_render_html_node( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'tag'        => 'div',
            'class'      => '',
            'id'         => '',
            'other_atts' => '',
        ),
        $atts,
        'ux_html_node'
    );

    $tag = sanitize_key( $atts['tag'] );
    if ( empty( $tag ) ) { $tag = 'div'; }

    // Attributes
    $class_attr = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    $id_attr    = $atts['id'] ? ' id="' . esc_attr( $atts['id'] ) . '"' : '';
    $extra      = $atts['other_atts'] ? ' ' . $atts['other_atts'] : '';

    return '<' . $tag . $class_attr . $id_attr . $extra . '>' . do_shortcode( $content ) . '</' . $tag . '>';
}
add_shortcode( 'ux_html_node', 'stu_render_html_node' );
