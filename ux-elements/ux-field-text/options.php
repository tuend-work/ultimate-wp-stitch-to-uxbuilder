<?php
/**
 * UX Field Text — UX Builder options.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_ux_builder_shortcode( 'ux_field_text', array(
    'name'     => __( 'Field Text', 'stitch-to-uxbuilder' ),
    'category' => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'info'     => '{{ value }}',
    'icon'     => 'dashicons-editor-textcolor',
    'parent'   => array( 'ux_ultimate_section' ),
    'wrap'     => false,
    'options'  => array(
        'slot' => array(
            'type'    => 'textfield',
            'heading' => __( 'Slot Name', 'stitch-to-uxbuilder' ),
            'description' => __( 'Must match {{slot_name}} in the template.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'tag' => array(
            'type'    => 'select',
            'heading' => __( 'HTML Tag', 'stitch-to-uxbuilder' ),
            'default' => 'p',
            'options' => array(
                'p'    => 'p',
                'h1'   => 'h1',
                'h2'   => 'h2',
                'h3'   => 'h3',
                'h4'   => 'h4',
                'h5'   => 'h5',
                'h6'   => 'h6',
                'span' => 'span',
                'div'  => 'div',
            ),
        ),
        'value' => array(
            'type'    => 'textarea',
            'heading' => __( 'Text Content', 'stitch-to-uxbuilder' ),
            'description' => __( 'Static content — also used as fallback when dynamic source is empty.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'css_class' => array(
            'type'    => 'textfield',
            'heading' => __( 'CSS Class', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'dynamic_enabled' => array(
            'type'    => 'checkbox',
            'heading' => __( 'Use Dynamic Data', 'stitch-to-uxbuilder' ),
            'default' => '0',
        ),
        'dynamic_source' => array(
            'type'    => 'textfield',
            'heading' => __( 'Dynamic Source', 'stitch-to-uxbuilder' ),
            'description' => __( 'e.g. post:title, acf:summary, woo:price, meta:_key', 'stitch-to-uxbuilder' ),
            'default' => '',
            'conditions' => 'dynamic_enabled === "1"',
        ),
    ),
) );
