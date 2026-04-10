<?php
/**
 * HTML Node — Universal element for any HTML tag.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_ux_builder_element( 'ux_html_node', array(
    'name'      => __( 'HTML Node', 'stitch-to-uxbuilder' ),
    'category'  => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'type'      => 'container',
    'info'      => '{{ tag }} . {{ class }}',
    'options'   => array(
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
    ),
) );
