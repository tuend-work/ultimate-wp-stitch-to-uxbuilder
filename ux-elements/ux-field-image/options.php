<?php
/**
 * UX Field Image — UX Builder options.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_ux_builder_shortcode( 'ux_field_image', array(
    'name'     => __( 'Field Image', 'stitch-to-uxbuilder' ),
    'category' => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'info'     => '{{ alt }}',
    'icon'     => 'dashicons-format-image',
    'wrap'     => false,
    'options'  => array(
        'slot' => array(
            'type'    => 'textfield',
            'heading' => __( 'Slot Name', 'stitch-to-uxbuilder' ),
            'description' => __( 'Must match {{slot_name}} in the template.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'src' => array(
            'type'    => 'image',
            'heading' => __( 'Image', 'stitch-to-uxbuilder' ),
            'description' => __( 'Static image — also used as fallback.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'alt' => array(
            'type'    => 'textfield',
            'heading' => __( 'Alt Text', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'css_class' => array(
            'type'    => 'textfield',
            'heading' => __( 'CSS Class', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'width' => array(
            'type'    => 'textfield',
            'heading' => __( 'Width (px)', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'height' => array(
            'type'    => 'textfield',
            'heading' => __( 'Height (px)', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'dynamic_enabled' => array(
            'type'    => 'checkbox',
            'heading' => __( 'Use Dynamic Data', 'stitch-to-uxbuilder' ),
            'default' => '0',
        ),
        'dynamic_source' => array(
            'type'    => 'select',
            'heading' => __( 'Dynamic Source', 'stitch-to-uxbuilder' ),
            'default' => '',
            'options' => array(
                ''               => __( '— Select —', 'stitch-to-uxbuilder' ),
                'post:thumbnail' => __( 'Post Thumbnail', 'stitch-to-uxbuilder' ),
                'woo:thumbnail'  => __( 'Product Image', 'stitch-to-uxbuilder' ),
                'woo:gallery'    => __( 'Product Gallery (first)', 'stitch-to-uxbuilder' ),
                'acf:field'      => __( 'ACF Field (enter name below)', 'stitch-to-uxbuilder' ),
            ),
            'conditions' => 'dynamic_enabled === "1"',
        ),
    ),
) );
