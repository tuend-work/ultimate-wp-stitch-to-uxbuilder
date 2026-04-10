<?php
/**
 * HTML Node — UX Builder options.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'add_ux_builder_shortcode' ) ) {
    return;
}

$html_node_options = array(
    'tag' => array(
        'type'    => 'select',
        'heading' => __( 'Tag', 'stitch-to-uxbuilder' ),
        'default' => 'div',
        'options' => array(
            'div'     => 'DIV',
            'span'    => 'SPAN',
            'section' => 'SECTION',
            'header'  => 'HEADER',
            'footer'  => 'FOOTER',
            'nav'     => 'NAV',
            'article' => 'ARTICLE',
            'aside'   => 'ASIDE',
            'main'    => 'MAIN',
            'button'  => 'BUTTON',
            'ul'      => 'UL',
            'li'      => 'LI',
            'i'       => 'I',
        ),
    ),
    'class' => array(
        'type'    => 'textfield',
        'heading' => __( 'CSS Class', 'stitch-to-uxbuilder' ),
        'default' => '',
    ),
    'id' => array(
        'type'    => 'textfield',
        'heading' => __( 'ID', 'stitch-to-uxbuilder' ),
        'default' => '',
    ),
    'other_atts' => array(
        'type'    => 'textfield',
        'heading' => __( 'Extra Attributes', 'stitch-to-uxbuilder' ),
        'description' => __( 'e.g. data-aos="fade-up" aria-label="test"', 'stitch-to-uxbuilder' ),
        'default' => '',
    ),
);

// Allow mutual nesting to support deep hierarchies
$allowed_children = array( 'ux_html_node', 'ux_html_block', 'ux_field_text', 'ux_field_image', 'ux_field_link' );

add_ux_builder_shortcode( 'ux_html_node', array(
    'name'      => __( 'HTML Node', 'stitch-to-uxbuilder' ),
    'category'  => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'type'      => 'container',
    'wrap'      => true,
    'allow'     => $allowed_children,
    'info'      => '{{ tag }} . {{ class }}',
    'options'   => $html_node_options,
) );

add_ux_builder_shortcode( 'ux_html_block', array(
    'name'      => __( 'HTML Node', 'stitch-to-uxbuilder' ),
    'category'  => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'type'      => 'container',
    'wrap'      => true,
    'allow'     => $allowed_children,
    'info'      => '{{ tag }} . {{ class }}',
    'options'   => $html_node_options,
) );
