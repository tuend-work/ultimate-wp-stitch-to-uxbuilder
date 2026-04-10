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
 * Handle Pure Mode: Dequeue other assets if enabled.
 */
function stu_handle_pure_mode() {
    if ( ! is_singular() ) {
        return;
    }

    $post_id = get_the_ID();
    $pure_mode = get_post_meta( $post_id, 'stu_pure_mode', true );
    if ( '1' !== $pure_mode ) {
        return;
    }

    // Dequeue styles
    global $wp_styles;
    if ( ! empty( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $handle ) {
            // Keep our plugin assets and core dashicons
            if ( strpos( $handle, 'stu-' ) !== 0 && $handle !== 'dashicons' ) {
                wp_dequeue_style( $handle );
            }
        }
    }

    // Dequeue scripts
    global $wp_scripts;
    if ( ! empty( $wp_scripts->queue ) ) {
        foreach ( $wp_scripts->queue as $handle ) {
            // Keep our plugin assets and critical core stuff
            if ( strpos( $handle, 'stu-' ) !== 0 && ! in_array( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) ) {
                wp_dequeue_script( $handle );
            }
        }
    }
}
add_action( 'wp_enqueue_scripts', 'stu_handle_pure_mode', 9999 );

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
