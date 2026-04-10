<?php
/**
 * Ultimate Section — Container element with slot-based template system.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function stu_render_ultimate_section( $atts, $content = null ) {
    $atts = shortcode_atts(
        array(
            'template_id'   => '',
            'tag'           => 'div',
            'css_class'     => '',
            'visibility'    => '',
        ),
        $atts,
        'ux_ultimate_section'
    );

    $template = '';
    
    // 1. Get Template from Meta (Primary) or content (Fallback)
    if ( ! empty( $atts['template_id'] ) ) {
        $post_id = get_the_ID();
        $template = get_post_meta( $post_id, 'stu-template-' . $atts['template_id'], true );
    }

    if ( empty( $template ) ) {
        // Fallback to searching in the content if meta is missing
        $template = preg_replace( '/\[ux_field_(text|image|link)[\s\S]*?\]/i', '', $content );
        $template = trim( $template );
    }

    if ( empty( $template ) ) {
        return do_shortcode( $content );
    }

    // 2. Parse child elements and get slot => rendered_html
    $slots = stu_parse_child_slots( $content );

    // 3. Replace each {{slot}} in template with child-rendered HTML
    foreach ( $slots as $slot_name => $html ) {
        $template = str_replace( '{{' . $slot_name . '}}', $html, $template );
    }

    // 4. Fallback: remaining {{slot}} → replace with default_content
    $template = stu_replace_remaining_slots_with_defaults( $template, $content );

    // 5. Cleanup
    $template = preg_replace( '/\{\{[a-z0-9_]+\}\}/', '', $template );

    $allowed_tags = array( 'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main' );
    $tag = in_array( $atts['tag'], $allowed_tags, true ) ? $atts['tag'] : 'div';

    $class = stu_sanitize_css_class( $atts['css_class'] );
    $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

    if ( function_exists( 'is_ux_builder' ) && is_ux_builder() ) {
        $template = preg_replace( '/<script\b[^>]*>([\s\S]*?)<\/script>/i', '<!-- Script removed in editor -->', $template );
    }

    $output = '<' . $tag . $class_attr . '>' . wp_kses( $template, stu_get_allowed_slot_html() ) . '</' . $tag . '>';
    
    return $output;
}
add_shortcode( 'ux_ultimate_section', 'stu_render_ultimate_section' );
