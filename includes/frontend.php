<?php
/**
 * Frontend assets and logic for Stitch to UX Builder.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue frontend assets (Tailwind, Fonts, Icons).
 */
function stu_enqueue_frontend_assets() {
    // 1. Tailwind Play CDN (Required for AI generated layouts)
    wp_enqueue_script( 'stu-tailwind', 'https://cdn.tailwindcss.com', array(), null, false );

    // 2. Google Fonts: Inter (Commonly used by AI)
    wp_enqueue_style( 'stu-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap', array(), null );

    // 3. Material Symbols Outlined
    wp_enqueue_style( 'stu-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );

    // 4. Custom overrides for Flatsome compatibility
    wp_add_inline_style( 'stu-google-fonts', '
        .material-symbols-outlined {
            font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            display: inline-block;
            vertical-align: middle;
        }
        /* Fix for Tailwind vs Flatsome collisions */
        .ux-ultimate-section-wrapper { position: relative; width: 100%; }
        
        /* Glassmorphism support (Common in v0/Stitch) */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .dark .glass-card {
            background: rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.05);
        }
    ' );
}
add_action( 'wp_enqueue_scripts', 'stu_enqueue_frontend_assets' );

/**
 * Add Tailwind configuration to handle dark mode and custom colors.
 */
function stu_tailwind_config() {
    ?>
    <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#0066ff',
            'background-light': '#ffffff',
            'background-dark': '#0f172a',
          }
        }
      }
    }
    </script>
    <?php
}
add_action( 'wp_head', 'stu_tailwind_config' );
