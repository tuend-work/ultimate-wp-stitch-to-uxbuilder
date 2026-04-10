<?php
/**
 * HTML Node — Renderer.
 * Supporting nesting by alternating between node and block tags.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Universal renderer for both ux_html_node and ux_html_block.
 */
function stu_render_html_node_universal( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'tag'        => 'div',
            'class'      => '',
            'id'         => '',
            'other_atts' => '',
            'slot'       => '', // For tracking
        ),
        $atts
    );

    $tag = sanitize_key( $atts['tag'] );
    if ( empty( $tag ) ) { $tag = 'div'; }

    // Attributes
    $class_attr = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    $id_attr    = $atts['id'] ? ' id="' . esc_attr( $atts['id'] ) . '"' : '';
    $extra      = $atts['other_atts'] ? ' ' . $atts['other_atts'] : '';

    // Process nested shortcodes
    $inner_html = ! is_null( $content ) ? do_shortcode( $content ) : '';

    return '<' . $tag . $class_attr . $id_attr . $extra . '>' . $inner_html . '</' . $tag . '>';
}

// Register both tags to the same renderer
add_shortcode( 'ux_html_node', 'stu_render_html_node_universal' );
add_shortcode( 'ux_html_block', 'stu_render_html_node_universal' );
