<?php
/**
 * Import Tool — HTML parser and shortcode generator.
 *
 * Parses pasted HTML, extracts depth-1 elements, and generates
 * ultimate_section shortcode with child elements.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STU_Import_Tool {

    /**
     * Extract HTML from a ZIP file and parse it.
     *
     * @param string $zip_path Path to the ZIP file.
     * @return array Array of sections data or error array.
     */
    public static function parse_zip( $zip_path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return array( 'error' => __( 'ZipArchive class not found. Please enable ZIP extension on your server.', 'stitch-to-uxbuilder' ) );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return array( 'error' => __( 'Failed to open ZIP file.', 'stitch-to-uxbuilder' ) );
        }

        $html_content = '';
        $html_file_name = '';

        // Look for index.html, code.html or any .html file
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( preg_match( '/\.(html?)$/i', $name ) ) {
                // Priority to index.html or code.html
                if ( 'index.html' === $name || 'code.html' === $name || empty( $html_content ) ) {
                    $html_content = $zip->getFromIndex( $i );
                    $html_file_name = $name;
                }
            }
        }

        $zip->close();

        if ( empty( $html_content ) ) {
            return array( 'error' => __( 'No HTML file found in ZIP.', 'stitch-to-uxbuilder' ) );
        }

        return self::parse_multi_sections( $html_content );
    }

    /**
     * Parse HTML and split it into multiple ultimate_section blocks.
     *
     * @param string $html Raw HTML content.
     * @return array Array of sections, each containing 'template' and 'elements'.
     */
    public static function parse_multi_sections( $html ) {
        if ( empty( $html ) ) {
            return array( 'sections' => array(), 'assets' => array() );
        }

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        // Wrap for fragmented HTML but handle full HTML too
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $assets = array( 'styles' => array(), 'scripts' => array() );

        // 1. Collect all styles
        $style_nodes = $dom->getElementsByTagName( 'style' );
        foreach ( $style_nodes as $node ) {
            $assets['styles'][] = array( 'type' => 'inline', 'content' => $node->textContent );
        }

        // 2. Collect all link[rel=stylesheet]
        $link_nodes = $dom->getElementsByTagName( 'link' );
        foreach ( $link_nodes as $node ) {
            if ( 'stylesheet' === strtolower( $node->getAttribute( 'rel' ) ) ) {
                $assets['styles'][] = array( 'type' => 'external', 'src' => $node->getAttribute( 'href' ) );
            }
        }

        // 3. Collect all scripts
        $script_nodes = $dom->getElementsByTagName( 'script' );
        foreach ( $script_nodes as $node ) {
            if ( $node->hasAttribute( 'src' ) ) {
                $assets['scripts'][] = array( 'type' => 'external', 'src' => $node->getAttribute( 'src' ) );
            } else {
                $assets['scripts'][] = array( 'type' => 'inline', 'content' => $node->textContent );
            }
        }

        // 4. Identify blocks from body
        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        $blocks = array();
        
        if ( $body ) {
            foreach ( $body->childNodes as $node ) {
                if ( $node->nodeType !== XML_ELEMENT_NODE ) { continue; }
                $tag = strtolower( $node->tagName );
                if ( in_array( $tag, array( 'style', 'script', 'link', 'meta', 'title', 'head' ), true ) ) { continue; }

                if ( 'main' === $tag ) {
                    foreach ( $node->childNodes as $main_child ) {
                        if ( $main_child->nodeType === XML_ELEMENT_NODE ) { $blocks[] = $main_child; }
                    }
                } else {
                    $blocks[] = $node;
                }
            }
        }

        // 5. Build sections
        $sections = array();
        if ( empty( $blocks ) ) {
            // Case where body is missing or single element
            $sections[] = self::parse_html( $html );
        } else {
            foreach ( $blocks as $block ) {
                $block_html = $dom->saveHTML( $block );
                $parsed = self::parse_html( $block_html );
                if ( ! empty( $parsed['elements'] ) || ! empty( trim( $parsed['template'] ) ) ) {
                    $sections[] = $parsed;
                }
            }
        }

        return array(
            'sections' => $sections,
            'assets'   => $assets,
        );
    }

    /**
     * Parse HTML string and extract elements at depth 1.
     *
     * Only parses tags directly at depth 1 within each top-level block.
     * Nested tags (e.g., <a> inside <p>) are kept in the template, not
     * extracted as separate child elements.
     *
     * @param string $html Raw HTML string.
     * @return array Parsed data with 'template' and 'elements' keys.
     */
    public static function parse_html( $html ) {
        if ( empty( $html ) ) {
            return array( 'template' => '', 'elements' => array() );
        }

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        if ( ! $body ) {
            return array( 'template' => $html, 'elements' => array() );
        }

        $counters = array( 'img' => 0, 'text' => 0, 'link' => 0, 'heading' => 0, 'span' => 0 );
        $elements = array();
        $template = self::process_node_to_shortcodes( $body, $counters, $dom, $elements );

        return array(
            'template' => $template,
            'elements' => $elements,
        );
    }

    /**
     * Recursive processor to convert ALL nodes to shortcodes.
     */
    private static function process_node_to_shortcodes( $node, &$counters, $dom, &$elements ) {
        $shortcode = '';

        foreach ( $node->childNodes as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $shortcode .= $child->textContent;
                continue;
            }

            if ( $child->nodeType === XML_COMMENT_NODE ) {
                continue;
            }

            if ( $child->nodeType !== XML_ELEMENT_NODE ) {
                continue;
            }

            $tag_name = strtolower( $child->tagName );

            // Keep style, script and svg tags as raw HTML (Still useful as blocks)
            if ( in_array( $tag_name, array( 'style', 'script', 'svg' ), true ) ) {
                $shortcode .= $dom->saveHTML( $child );
                continue;
            }

            // Map standard elements to specialized shortcodes
            $special = self::try_map_special_element( $child, $tag_name, $counters, $dom, $elements );
            if ( $special ) {
                $shortcode .= $special;
                continue;
            }

            // Generic mapping to ux_html_node
            $inner = self::process_node_to_shortcodes( $child, $counters, $dom, $elements );
            $shortcode .= self::build_html_node_shortcode( $child, $inner, $counters, $elements );
        }

        return $shortcode;
    }

    /**
     * Try to map an element to a specialized shortcode.
     */
    private static function try_map_special_element( $node, $tag_name, &$counters, $dom, &$elements ) {
        if ( 'img' === $tag_name ) {
            $counters['img']++;
            $slot = 'image_' . $counters['img'];
            
            $attrs = array(
                'slot'      => $slot,
                'src'       => $node->getAttribute( 'src' ),
                'alt'       => $node->getAttribute( 'alt' ),
                'css_class' => $node->getAttribute( 'class' ),
            );

            $elements[] = array(
                'type' => 'ux_field_image',
                'slot' => $slot,
                'src'  => $attrs['src'],
                'alt'  => $attrs['alt'],
            );

            return self::build_shortcode_tag( 'ux_field_image', $attrs );
        }

        if ( 'a' === $tag_name ) {
            $counters['link']++;
            $slot = 'link_' . $counters['link'];
            $inner = self::process_node_to_shortcodes( $node, $counters, $dom, $elements );
            
            $attrs = array(
                'slot'      => $slot,
                'href'      => $node->getAttribute( 'href' ),
                'target'    => $node->getAttribute( 'target' ) ?: '_self',
                'css_class' => $node->getAttribute( 'class' ),
            );

            $elements[] = array(
                'type'  => 'ux_field_link',
                'slot'  => $slot,
                'href'  => $attrs['href'],
                'label' => $node->textContent, // Simplified for UI display
            );

            return '[ux_field_link ' . self::attributes_to_string( $attrs ) . ']' . $inner . '[/ux_field_link]';
        }

        // Handle Text tags (p, h1-h6) as specialized ux_field_text for editing
        if ( in_array( $tag_name, array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span' ), true ) ) {
            $counters['text']++;
            $slot = 'text_' . $counters['text'];
            
            $attrs = array(
                'slot'      => $slot,
                'tag'       => $tag_name,
                'value'     => $node->textContent,
                'css_class' => $node->getAttribute( 'class' ),
            );

            $elements[] = array(
                'type'  => 'ux_field_text',
                'slot'  => $slot,
                'tag'   => $tag_name,
                'value' => $attrs['value'],
            );

            return self::build_shortcode_tag( 'ux_field_text', $attrs );
        }

        return null;
    }

    /**
     * Build [ux_html_node] shortcode for any element.
     */
    private static function build_html_node_shortcode( $node, $content, &$counters, &$elements ) {
        $tag = strtolower( $node->tagName );
        $class = $node->getAttribute( 'class' );
        $id = $node->getAttribute( 'id' );
        
        // Gather other attributes
        $others = array();
        if ( $node->hasAttributes() ) {
            foreach ( $node->attributes as $attr ) {
                if ( in_array( $attr->name, array( 'class', 'id' ), true ) ) continue;
                $others[] = $attr->name . '="' . esc_attr( $attr->value ) . '"';
            }
        }
        $other_atts = implode( ' ', $others );

        $attrs = array(
            'tag'        => $tag,
            'class'      => $class,
            'id'         => $id,
            'other_atts' => $other_atts,
        );

        return '[ux_html_node ' . self::attributes_to_string( $attrs ) . ']' . $content . '[/ux_html_node]';
    }

    /**
     * Helper to convert array to attribute string.
     */
    private static function attributes_to_string( $attrs ) {
        $parts = array();
        foreach ( $attrs as $key => $val ) {
            if ( $val !== '' ) {
                $parts[] = $key . '="' . esc_attr( $val ) . '"';
            }
        }
        return implode( ' ', $parts );
    }


    /**
     * Try to parse a DOM element into a child element definition.
     *
     * @param DOMElement  $node     The DOM element.
     * @param string      $tag_name Lowercase tag name.
     * @param array       $counters Reference to counters.
     * @param DOMDocument $dom      DOM document.
     * @return array|null Parsed element data or null if not parseable.
     */
    private static function try_parse_element( $node, $tag_name, &$counters, $dom ) {
        $heading_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

        if ( 'img' === $tag_name ) {
            $counters['img']++;
            $slot = 'img_' . $counters['img'];
            return array(
                'type'      => 'ux_field_image',
                'slot'      => $slot,
                'src'       => $node->getAttribute( 'src' ),
                'alt'       => $node->getAttribute( 'alt' ),
                'css_class' => $node->getAttribute( 'class' ),
                'width'     => $node->getAttribute( 'width' ),
                'height'    => $node->getAttribute( 'height' ),
            );
        }

        if ( in_array( $tag_name, $heading_tags, true ) ) {
            $counters['heading']++;
            $slot = 'heading_' . $counters['heading'];
            return array(
                'type'      => 'ux_field_text',
                'slot'      => $slot,
                'tag'       => $tag_name,
                'value'     => self::get_inner_html( $node, $dom ),
                'css_class' => $node->getAttribute( 'class' ),
            );
        }

        if ( 'p' === $tag_name ) {
            $counters['text']++;
            $slot = 'text_' . $counters['text'];
            return array(
                'type'      => 'ux_field_text',
                'slot'      => $slot,
                'tag'       => 'p',
                'value'     => self::get_inner_html( $node, $dom ),
                'css_class' => $node->getAttribute( 'class' ),
            );
        }

        if ( 'a' === $tag_name ) {
            $counters['link']++;
            $slot = 'link_' . $counters['link'];
            return array(
                'type'      => 'ux_field_link',
                'slot'      => $slot,
                'href'      => $node->getAttribute( 'href' ),
                'label'     => self::get_inner_html( $node, $dom ),
                'target'    => $node->getAttribute( 'target' ) ?: '_self',
                'css_class' => $node->getAttribute( 'class' ),
            );
        }

        if ( 'span' === $tag_name ) {
            $counters['span']++;
            $slot = 'span_' . $counters['span'];
            return array(
                'type'      => 'ux_field_text',
                'slot'      => $slot,
                'tag'       => 'span',
                'value'     => self::get_inner_html( $node, $dom ),
                'css_class' => $node->getAttribute( 'class' ),
            );
        }

        return null;
    }

    /**
     * Get inner HTML of a DOM node.
     *
     * @param DOMNode     $node Node.
     * @param DOMDocument $dom  Document.
     * @return string Inner HTML.
     */
    private static function get_inner_html( $node, $dom ) {
        $inner = '';
        foreach ( $node->childNodes as $child ) {
            $inner .= $dom->saveHTML( $child );
        }
        return trim( $inner );
    }

    /**
     * Rebuild a tag with a new inner template.
     *
     * @param DOMElement  $node          Original node.
     * @param string      $inner_template New inner content.
     * @param DOMDocument $dom           Document.
     * @return string Rebuilt HTML tag.
     */
    private static function rebuild_tag_with_template( $node, $inner_template, $dom ) {
        $tag = strtolower( $node->tagName );
        $attrs = '';

        if ( $node->hasAttributes() ) {
            foreach ( $node->attributes as $attr ) {
                $attrs .= ' ' . $attr->name . '="' . htmlspecialchars( $attr->value, ENT_QUOTES, 'UTF-8' ) . '"';
            }
        }

        // Self-closing tags
        $void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );
        if ( in_array( $tag, $void, true ) ) {
            return '<' . $tag . $attrs . ' />';
        }

        return '<' . $tag . $attrs . '>' . $inner_template . '</' . $tag . '>';
    }

    /**
     * Generate shortcode string for multiple sections.
     *
     * @param array $sections Array of parsed sections.
     * @return string Multi-shortcode string.
     */
    public static function generate_multi_shortcode( $sections ) {
        $output = '';
        foreach ( $sections as $section ) {
            $output .= self::generate_shortcode( $section );
        }
        return $output;
    }

    /**
     * Generate shortcode string from parsed data.
     *
     * @param array  $parsed   Parsed data from parse_html().
     * @param string $tag      Wrapper tag (div/section/article).
     * @param string $css_class CSS class for the section.
     * @return string Complete shortcode string.
     */
    public static function generate_shortcode( $parsed, $tag = 'div', $css_class = '' ) {
        $content = $parsed['template'];
        $elements = isset( $parsed['elements'] ) ? $parsed['elements'] : array();

        // Apply dynamic overrides to the content string
        foreach ( $elements as $el ) {
            if ( isset( $el['dynamic_enabled'] ) && '1' === $el['dynamic_enabled'] ) {
                $slot_attr = 'slot="' . $el['slot'] . '"';
                $dynamic_attrs = ' dynamic_enabled="1"';
                
                if ( ! empty( $el['dynamic_source'] ) ) {
                    $dynamic_attrs .= ' dynamic_source="' . esc_attr( $el['dynamic_source'] ) . '"';
                }
                if ( ! empty( $el['dynamic_href'] ) ) {
                    $dynamic_attrs .= ' dynamic_href="' . esc_attr( $el['dynamic_href'] ) . '"';
                }

                $content = str_replace( $slot_attr, $slot_attr . $dynamic_attrs, $content );
            }
        }

        // Return the content wrapped in an Ultimate Section for Builder stability
        return '[ux_ultimate_section tag="' . esc_attr( $tag ) . '" css_class="' . esc_attr( $css_class ) . '"]' . "\n" . $content . "\n" . '[/ux_ultimate_section]';
    }

    /**
     * Convert a single parsed element to its shortcode string.
     *
     * @param array $el Element data.
     * @return string Shortcode string.
     */
    private static function element_to_shortcode( $el ) {
        $type = $el['type'];

        switch ( $type ) {
            case 'ux_field_image':
                $attrs = array(
                    'slot'            => $el['slot'],
                    'src'             => $el['src'] ?? '',
                    'alt'             => $el['alt'] ?? '',
                    'css_class'       => $el['css_class'] ?? '',
                    'dynamic_enabled' => '0',
                );
                if ( ! empty( $el['width'] ) ) {
                    $attrs['width'] = $el['width'];
                }
                if ( ! empty( $el['height'] ) ) {
                    $attrs['height'] = $el['height'];
                }
                return self::build_shortcode_tag( 'ux_field_image', $attrs );

            case 'ux_field_text':
                $attrs = array(
                    'slot'            => $el['slot'],
                    'tag'             => $el['tag'] ?? 'p',
                    'value'           => $el['value'] ?? '',
                    'css_class'       => $el['css_class'] ?? '',
                    'dynamic_enabled' => '0',
                );
                return self::build_shortcode_tag( 'ux_field_text', $attrs );

            case 'ux_field_link':
                $attrs = array(
                    'slot'            => $el['slot'],
                    'href'            => $el['href'] ?? '#',
                    'label'           => $el['label'] ?? '',
                    'target'          => $el['target'] ?? '_self',
                    'css_class'       => $el['css_class'] ?? '',
                    'dynamic_enabled' => '0',
                );
                return self::build_shortcode_tag( 'ux_field_link', $attrs );

            default:
                return '';
        }
    }

    /**
     * Build a shortcode tag string from tag name and attributes.
     *
     * @param string $tag   Shortcode tag name.
     * @param array  $attrs Attributes.
     * @return string Shortcode string.
     */
    private static function build_shortcode_tag( $tag, $attrs ) {
        $parts = array();
        foreach ( $attrs as $key => $val ) {
            if ( '' !== $val ) {
                // Use esc_attr for standard attributes
                $parts[] = $key . '="' . esc_attr( $val ) . '"';
            }
        }
        return '[' . $tag . ' ' . implode( ' ', $parts ) . ']';
    }

    /**
     * Generate a hash of the HTML for duplicate detection.
     *
     * @param string $html HTML content.
     * @return string MD5 hash.
     */
    public static function generate_html_hash( $html ) {
        // Normalize whitespace for consistent hashing
        $normalized = preg_replace( '/\s+/', ' ', trim( $html ) );
        return md5( $normalized );
    }

    /**
     * Check if this HTML has already been imported to the post.
     *
     * @param int    $post_id Post ID.
     * @param string $html    HTML content.
     * @return bool True if already imported.
     */
    public static function is_duplicate( $post_id, $html ) {
        $hash = self::generate_html_hash( $html );
        $imported_hashes = get_post_meta( $post_id, '_stu_imported_hashes', true );

        if ( ! is_array( $imported_hashes ) ) {
            $imported_hashes = array();
        }

        return in_array( $hash, $imported_hashes, true );
    }

    /**
     * Record an import hash for duplicate detection.
     *
     * @param int    $post_id Post ID.
     * @param string $html    HTML content.
     */
    public static function record_import( $post_id, $html ) {
        $hash = self::generate_html_hash( $html );
        $imported_hashes = get_post_meta( $post_id, '_stu_imported_hashes', true );

        if ( ! is_array( $imported_hashes ) ) {
            $imported_hashes = array();
        }

        if ( ! in_array( $hash, $imported_hashes, true ) ) {
            $imported_hashes[] = $hash;
            update_post_meta( $post_id, '_stu_imported_hashes', $imported_hashes );
        }
    }

    /**
     * Localize images from sections and template.
     *
     * @param array $sections   Array of sections (by reference).
     */
    public static function localize_images( &$sections ) {
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Keep track of downloaded URLs to avoid duplicates in the same import
        $download_map = array();

        foreach ( $sections as &$section ) {
            // 1. Process elements (Images, primarily)
            foreach ( $section['elements'] as &$el ) {
                if ( 'ux_field_image' === $el['type'] && ! empty( $el['src'] ) ) {
                    $new_url = self::maybe_download_image( $el['src'], $download_map );
                    if ( $new_url ) {
                        $el['src'] = $new_url;
                    }
                }
            }

            // 2. Process Template (for background images or inline <img> not parsed)
            // Regex to find src="..." or url(...)
            $section['template'] = preg_replace_callback(
                '/(src|url)=["\']?([^"\')\s>]+)["\']?/i',
                function( $matches ) use ( &$download_map ) {
                    $attr = $matches[1];
                    $url = $matches[2];
                    
                    // Skip if it's already a local URL or a placeholder
                    if ( strpos( $url, 'http' ) !== 0 && strpos( $url, 'data:image' ) !== 0 ) {
                        return $matches[0];
                    }

                    $new_url = self::maybe_download_image( $url, $download_map );
                    if ( $new_url ) {
                        $quote = substr( $matches[0], strlen( $attr . '=' ), 1 );
                        if ( $quote !== '"' && $quote !== "'" ) { $quote = '"'; }
                        return $attr . '=' . $quote . $new_url . $quote;
                    }

                    return $matches[0];
                },
                $section['template']
            );
        }
    }

    /**
     * Download or process an image to Media Library.
     *
     * @param string $url          Remote URL or Base64 string.
     * @param array  $download_map Cache of downloads.
     * @return string|bool New URL or false.
     */
    private static function maybe_download_image( $url, &$download_map ) {
        if ( isset( $download_map[ $url ] ) ) {
            return $download_map[ $url ];
        }

        $is_base64 = ( strpos( $url, 'data:image' ) === 0 );
        $filename = '';
        $temp_file = '';

        if ( $is_base64 ) {
            // data:image/jpeg;base64,...
            if ( ! preg_match( '/data:image\/([a-z+]+);base64,(.*)/i', $url, $matches ) ) {
                return false;
            }
            $ext = $matches[1];
            if ( 'svg+xml' === $ext ) { $ext = 'svg'; }
            $data = base64_decode( $matches[2] );
            $filename = md5( $url ) . '.' . $ext;
            
            $temp_file = wp_tempnam( $filename );
            file_put_contents( $temp_file, $data );
        } else {
            // External URL
            $ext = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
            if ( ! $ext ) { $ext = 'jpg'; }
            $filename = md5( $url ) . '.' . $ext;

            // Use WordPress download helper
            $temp_file = download_url( $url );
            if ( is_wp_error( $temp_file ) ) {
                return false;
            }
        }

        // Upload to Media Library
        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $temp_file,
        );

        // Allow SVG uploads temporarily
        add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_upload' ) );
        
        $id = media_handle_sideload( $file_array, 0 );
        
        remove_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_upload' ) );

        if ( is_wp_error( $id ) ) {
            @unlink( $temp_file );
            return false;
        }

        $new_url = wp_get_attachment_url( $id );
        $download_map[ $url ] = $new_url;
        
        return $new_url;
    }

    /**
     * Filter to allow SVG uploads.
     */
    public static function allow_svg_upload( $mimes ) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
}
