<?php
/**
 * UX Field Image — Element registration and shortcode render.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the [ux_field_image] shortcode.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode inner content (unused).
 * @return string Rendered HTML.
 */
function stu_render_field_image( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'slot'            => '',
            'src'             => '',
            'alt'             => '',
            'css_class'       => '',
            'width'           => '',
            'height'          => '',
            'dynamic_enabled' => '0',
            'dynamic_source'  => '',
            'visibility'      => '',
        ),
        $atts,
        'ux_field_image'
    );

    // Determine image source
    $src = $atts['src'];

    // Dynamic source resolution
    if ( '1' === $atts['dynamic_enabled'] && ! empty( $atts['dynamic_source'] ) ) {
        $dynamic_src = STU_Dynamic_Resolver::resolve_image( $atts['dynamic_source'] );
        if ( ! empty( $dynamic_src ) ) {
            $src = $dynamic_src;
        }
        // Fallback to static src if dynamic returns empty
    }

    // If src is a numeric attachment ID, convert to URL
    if ( is_numeric( $src ) ) {
        $url = wp_get_attachment_url( intval( $src ) );
        $src = $url ?: $src;
    }

    // No source — return empty
    if ( empty( $src ) ) {
        return '';
    }

    // Build attributes
    $class = stu_sanitize_css_class( $atts['css_class'] );
    $img_attrs = array(
        'src'   => esc_url( $src ),
        'alt'   => esc_attr( $atts['alt'] ),
    );

    if ( $class ) {
        $img_attrs['class'] = esc_attr( $class );
    }
    if ( $atts['width'] ) {
        $img_attrs['width'] = intval( $atts['width'] );
    }
    if ( $atts['height'] ) {
        $img_attrs['height'] = intval( $atts['height'] );
    }

    // Build <img> tag
    $attrs_str = '';
    foreach ( $img_attrs as $attr_name => $attr_val ) {
        $attrs_str .= ' ' . $attr_name . '="' . $attr_val . '"';
    }

    return '<img' . $attrs_str . ' loading="lazy" />';
}
add_shortcode( 'ux_field_image', 'stu_render_field_image' );
