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
            'html_template' => '',
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
    $template = trim( $template );

    // BUG FIX & FALLBACK: If template is empty, check the legacy 'html_template' attribute
    if ( empty( $template ) && ! empty( $atts['html_template'] ) ) {
        $template = rawurldecode( $atts['html_template'] );
    }

    // Still empty? Show children directly as a last resort
    if ( empty( $template ) ) {
        if ( function_exists( 'is_ux_builder' ) && is_ux_builder() ) {
            return '<div class="stu-empty-placeholder">' . __( 'Ultimate Section: Template is empty. Please check your HTML import.', 'stitch-to-uxbuilder' ) . '</div>';
        }
        return do_shortcode( $content );
    }

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
    $allowed_tags = array( 'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main' );
    $tag = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'div';

    // Build class attribute
    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    // If we are in UX Builder, strip <script> tags to prevent execution errors in editor
    // This fixed the "mobileBtn is null" and "isVisible" crashes.
    if ( function_exists( 'is_ux_builder' ) && is_ux_builder() ) {
        $template = preg_replace( '/<script\b[^>]*>([\s\S]*?)<\/script>/i', '<!-- Script removed in editor to prevent crash -->', $template );
    }

    // Final render - Avoid wpautop or any content filters that might break SVG/HTML structure
    $output = '<' . $tag . $class_attr . '>' . wp_kses( $template, stu_get_allowed_slot_html() ) . '</' . $tag . '>';
    
    return $output;
}
add_shortcode( 'ux_ultimate_section', 'stu_render_ultimate_section' );
