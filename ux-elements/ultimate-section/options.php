<?php
/**
 * Ultimate Section — UX Builder options.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_ux_builder_shortcode( 'ux_ultimate_section', array(
    'name'     => __( 'Ultimate Section', 'stitch-to-uxbuilder' ),
    'category' => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'icon'     => 'dashicons-layout',
    'type'     => 'container',
    'wrap'     => true,
    'allow'    => array( 'ux_field_text', 'ux_field_image', 'ux_field_link' ),
    'options'  => array(
        'html_template' => array(
            'type'    => 'textarea',
            'heading' => __( 'HTML Template', 'stitch-to-uxbuilder' ),
            'description' => __( 'Paste your HTML with {{slot_name}} placeholders. Child elements will fill these slots.', 'stitch-to-uxbuilder' ),
            'default' => '',
            'rows'    => 12,
        ),
        'tag' => array(
            'type'    => 'select',
            'heading' => __( 'Wrapper Tag', 'stitch-to-uxbuilder' ),
            'default' => 'div',
            'options' => array(
                'div'     => 'div',
                'section' => 'section',
                'article' => 'article',
            ),
        ),
        'css_class' => array(
            'type'    => 'textfield',
            'heading' => __( 'CSS Class', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
    ),
) );
