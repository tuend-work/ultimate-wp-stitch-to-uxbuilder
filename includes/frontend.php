<?php
/**
 * Frontend assets and logic for Stitch to UX Builder.
 * 
 * Version 1.0.9: Individual Asset Fields
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue dynamic assets from individual custom fields.
 */
function stu_enqueue_dynamic_assets() {
    if ( ! is_singular() ) {
        return;
    }

    $post_id = get_the_ID();
    $all_meta = get_post_meta( $post_id );

    if ( empty( $all_meta ) || ! is_array( $all_meta ) ) {
        return;
    }

    foreach ( $all_meta as $key => $values ) {
        $content = $values[0]; // Post meta values are always in an array

        // 1. Process Styles
        if ( strpos( $key, 'stu-style-' ) === 0 ) {
            $handle = $key;
            if ( strpos( $key, 'stu-style-ext-' ) === 0 ) {
                wp_enqueue_style( $handle, $content, array(), null );
            } else {
                wp_register_style( $handle, false );
                wp_enqueue_style( $handle );
                wp_add_inline_style( $handle, $content );
            }
        }

        // 2. Process Scripts
        if ( strpos( $key, 'stu-script-' ) === 0 ) {
            $handle = $key;
            // Detect tailwind to use fixed handle if needed
            if ( strpos( $content, 'tailwindcss' ) !== false ) {
                $handle = 'stu-tailwind';
            }

            if ( strpos( $key, 'stu-script-ext-' ) === 0 ) {
                wp_enqueue_script( $handle, $content, array(), null, false );
            } else {
                add_action( 'wp_footer', function() use ($content) {
                    echo '<script>' . $content . '</script>';
                }, 20 );
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'stu_enqueue_dynamic_assets' );

/**
 * Global helper for icons.
 */
function stu_enqueue_icon_fix() {
    global $post;
    if ( ! is_singular() || ! isset( $post->post_content ) || strpos( $post->post_content, '[ux_ultimate_section' ) === false ) {
        return;
    }
    
    wp_add_inline_style( 'dashicons', '
        .material-symbols-outlined { font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24; display: inline-block; vertical-align: middle; }
    ' );
}
add_action( 'wp_enqueue_scripts', 'stu_enqueue_icon_fix' );
