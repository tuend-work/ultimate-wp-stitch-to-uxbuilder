<?php
/**
 * Shared helper functions for Stitch to UX Builder.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Parse child element shortcodes from $content and extract slot => rendered_html pairs.
 *
 * @param string $content The shortcode content containing child elements.
 * @return array Associative array of [slot_name => rendered_html].
 */
function stu_parse_child_slots( $content ) {
    $slots = array();

    if ( empty( $content ) ) {
        return $slots;
    }

    // Match all ux_field_* shortcodes with a slot attribute
    $pattern = '/\[ux_field_(text|image|link)\s+([^\]]*slot\s*=\s*"([^"]*)"[^\]]*)\]/s';

    if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            $slot_name = $match[3];
            $full_shortcode = $match[0];

            // Render the shortcode to get its HTML output
            $rendered = do_shortcode( $full_shortcode );

            // Last child with same slot wins (override, not concatenate)
            $slots[ $slot_name ] = $rendered;
        }
    }

    return $slots;
}

/**
 * Extract default content from child elements for slots that were not filled.
 *
 * @param string $template The HTML template with remaining {{slot}} placeholders.
 * @param string $content  The raw shortcode content containing child elements.
 * @return string Template with remaining slots replaced by default content where available.
 */
function stu_replace_remaining_slots_with_defaults( $template, $content ) {
    // Find remaining {{slot}} placeholders
    if ( ! preg_match_all( '/\{\{([a-z0-9_]+)\}\}/', $template, $slot_matches ) ) {
        return $template;
    }

    foreach ( $slot_matches[1] as $slot_name ) {
        // Look for a child element with this slot name to get its default rendering
        $pattern = '/\[ux_field_(text|image|link)\s+[^\]]*slot\s*=\s*"' . preg_quote( $slot_name, '/' ) . '"[^\]]*\]/s';

        if ( preg_match( $pattern, $content, $match ) ) {
            // Render the child element shortcode — it will use its default/static value
            $default_html = do_shortcode( $match[0] );
            $template = str_replace( '{{' . $slot_name . '}}', $default_html, $template );
        }
    }

    return $template;
}

/**
 * Sanitize a CSS class string.
 *
 * @param string $class CSS class string.
 * @return string Sanitized CSS classes.
 */
function stu_sanitize_css_class( $class ) {
    $classes = explode( ' ', $class );
    $classes = array_map( 'sanitize_html_class', $classes );
    return implode( ' ', array_filter( $classes ) );
}

/**
 * Get allowed HTML tags for slot content rendering.
 *
 * @return array Allowed tags array for wp_kses.
 */
function stu_get_allowed_slot_html() {
    return array_merge(
        wp_kses_allowed_html( 'post' ),
        array(
            'img'    => array(
                'src'      => true,
                'alt'      => true,
                'class'    => true,
                'width'    => true,
                'height'   => true,
                'loading'  => true,
                'decoding' => true,
                'srcset'   => true,
                'sizes'    => true,
                'style'    => true,
                'id'       => true,
            ),
            'picture' => array(),
            'source'  => array(
                'srcset' => true,
                'media'  => true,
                'type'   => true,
            ),
            'svg'     => array(
                'class'       => true,
                'width'       => true,
                'height'      => true,
                'viewbox'     => true,
                'fill'        => true,
                'xmlns'       => true,
                'aria-hidden' => true,
                'role'        => true,
                'style'       => true,
            ),
            'path'    => array(
                'd'              => true,
                'fill'           => true,
                'stroke'         => true,
                'stroke-width'   => true,
                'stroke-linecap' => true,
                'stroke-linejoin'=> true,
            ),
            'iframe'  => array(
                'src'             => true,
                'width'           => true,
                'height'          => true,
                'frameborder'     => true,
                'allow'           => true,
                'allowfullscreen' => true,
                'class'           => true,
                'style'           => true,
                'loading'         => true,
            ),
        )
    );
}
