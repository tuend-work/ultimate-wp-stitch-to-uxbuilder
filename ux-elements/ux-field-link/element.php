<?php
/**
 * UX Field Link — Element registration and shortcode render.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the [ux_field_link] shortcode.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode inner content (unused).
 * @return string Rendered HTML.
 */
function stu_render_field_link( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'slot'            => '',
            'href'            => '#',
            'label'           => '',
            'target'          => '_self',
            'css_class'       => '',
            'dynamic_enabled' => '0',
            'dynamic_href'    => '',
            'dynamic_label'   => '',
            'visibility'      => '',
        ),
        $atts,
        'ux_field_link'
    );

    // Determine href
    $href = $atts['href'];
    $label = $atts['label'];

    // Dynamic href resolution
    if ( '1' === $atts['dynamic_enabled'] && ! empty( $atts['dynamic_href'] ) ) {
        $dynamic_href = STU_Dynamic_Resolver::resolve( $atts['dynamic_href'] );
        if ( ! empty( $dynamic_href ) ) {
            $href = $dynamic_href;
        }
    }

    // Dynamic label resolution
    if ( '1' === $atts['dynamic_enabled'] && ! empty( $atts['dynamic_label'] ) ) {
        $dynamic_label = STU_Dynamic_Resolver::resolve( $atts['dynamic_label'] );
        if ( ! empty( $dynamic_label ) ) {
            $label = $dynamic_label;
        }
    }

    // No label = no link
    if ( empty( $label ) && empty( $content ) ) {
        return '';
    }

    if ( empty( $label ) && ! empty( $content ) ) {
        $label = $content;
    }

    // Allowed targets
    $allowed_targets = array( '_self', '_blank' );
    $target = in_array( $atts['target'], $allowed_targets, true ) ? $atts['target'] : '_self';

    // Build class
    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    // Build rel for _blank
    $rel = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';

    // Decode entities to allow <img> tags from SVGs to render properly
    $decoded_label = html_entity_decode( $label );

    return '<a href="' . esc_url( $href ) . '" target="' . esc_attr( $target ) . '"' . $class_attr . $rel . '>'
        . wp_kses( $decoded_label, stu_get_allowed_slot_html() )
        . '</a>';
}
add_shortcode( 'ux_field_link', 'stu_render_field_link' );
