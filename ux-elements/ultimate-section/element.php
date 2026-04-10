<?php
/**
 * Ultimate Section — Container element with slot-based template system.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the [ux_ultimate_section] shortcode.
 *
 * Flow:
 * 1. Parse child elements from $content shortcode to get [slot_name => rendered_html]
 * 2. Replace each {{slot}} in template with rendered child HTML
 * 3. Remaining {{slot}} → replace with default_content from child elements
 * 4. Slots still left → replace with empty string
 * 5. Wrap in the chosen tag with css_class
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode inner content (child elements).
 * @return string Rendered HTML.
 */
function stu_render_ultimate_section( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'tag'           => 'div',
            'css_class'     => '',
            'visibility'    => '',
        ),
        $atts,
        'ux_ultimate_section'
    );

    // 1. Extract the Template from content
    // Remove all [ux_field_*] shortcodes to get the RAW HTML template
    $template = preg_replace( '/\[ux_field_(text|image|link)[\s\S]*?\]/i', '', $content );

    // 2. Parse child elements and get slot => rendered_html
    $slots = stu_parse_child_slots( $content );

    // 3. Replace each {{slot}} in template with child-rendered HTML
    foreach ( $slots as $slot_name => $html ) {
        $template = str_replace( '{{' . $slot_name . '}}', $html, $template );
    }

    // 4. Fallback: remaining {{slot}} → replace with default_content from child elements
    $template = stu_replace_remaining_slots_with_defaults( $template, $content );

    // 5. Slots with no default → replace with empty string
    $template = preg_replace( '/\{\{[a-z0-9_]+\}\}/', '', $template );

    // Allowed wrapper tags
    $allowed_tags = array( 'div', 'section', 'article' );
    $tag = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'div';

    // Build class attribute
    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    return '<' . $tag . $class_attr . '>' . $template . '</' . $tag . '>';
}
add_shortcode( 'ux_ultimate_section', 'stu_render_ultimate_section' );
