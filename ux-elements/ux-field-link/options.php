<?php
/**
 * UX Field Link — UX Builder options.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_ux_builder_shortcode( 'ux_field_link', array(
    'name'     => __( 'Field Link', 'stitch-to-uxbuilder' ),
    'category' => __( 'Stitch Elements', 'stitch-to-uxbuilder' ),
    'info'     => '{{ label }}',
    'icon'     => 'dashicons-admin-links',
    'wrap'     => true,
    'options'  => array(
        'slot' => array(
            'type'    => 'textfield',
            'heading' => __( 'Slot Name', 'stitch-to-uxbuilder' ),
            'description' => __( 'Must match {{slot_name}} in the template.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'href' => array(
            'type'    => 'textfield',
            'heading' => __( 'URL', 'stitch-to-uxbuilder' ),
            'description' => __( 'Static URL — also used as fallback.', 'stitch-to-uxbuilder' ),
            'default' => '#',
        ),
        'label' => array(
            'type'    => 'textfield',
            'heading' => __( 'Label', 'stitch-to-uxbuilder' ),
            'description' => __( 'Link text.', 'stitch-to-uxbuilder' ),
            'default' => '',
        ),
        'target' => array(
            'type'    => 'select',
            'heading' => __( 'Target', 'stitch-to-uxbuilder' ),
            'default' => '_self',
            'options' => array(
                '_self'  => __( 'Same Window', 'stitch-to-uxbuilder' ),
                '_blank' => __( 'New Tab', 'stitch-to-uxbuilder' ),
            ),
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
        'dynamic_href' => array(
            'type'    => 'textfield',
            'heading' => __( 'Dynamic URL Source', 'stitch-to-uxbuilder' ),
            'description' => __( 'e.g. post:permalink, acf:btn_url, woo:permalink', 'stitch-to-uxbuilder' ),
            'default' => '',
            'conditions' => 'dynamic_enabled === "1"',
        ),
        'dynamic_label' => array(
            'type'    => 'textfield',
            'heading' => __( 'Dynamic Label Source', 'stitch-to-uxbuilder' ),
            'description' => __( 'e.g. post:title, acf:btn_text', 'stitch-to-uxbuilder' ),
            'default' => '',
            'conditions' => 'dynamic_enabled === "1"',
        ),
    ),
) );
