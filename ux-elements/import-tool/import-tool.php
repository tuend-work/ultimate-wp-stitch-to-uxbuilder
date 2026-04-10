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
            return array(
                'template' => '',
                'elements' => array(),
            );
        }

        // Clean up HTML
        $html = trim( $html );

        // Use DOMDocument to parse
        $dom = new DOMDocument( '1.0', 'UTF-8' );

        // Suppress warnings for invalid HTML
        libxml_use_internal_errors( true );

        // Wrap in a root element so DOMDocument doesn't add html/body
        $wrapped = '<div id="stu-root">' . $html . '</div>';
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        // Find our root wrapper
        $root = $dom->getElementById( 'stu-root' );
        if ( ! $root ) {
            return array(
                'template' => $html,
                'elements' => array(),
            );
        }

        $elements = array();
        $counters = array(
            'img'     => 0,
            'heading' => 0,
            'text'    => 0,
            'link'    => 0,
            'span'    => 0,
        );

        $template = '';

        // Process direct children of root (depth 1)
        foreach ( $root->childNodes as $node ) {
            if ( $node->nodeType === XML_TEXT_NODE ) {
                $text = trim( $node->textContent );
                if ( ! empty( $text ) ) {
                    $template .= $node->textContent;
                }
                continue;
            }

            if ( $node->nodeType === XML_COMMENT_NODE ) {
                $template .= '<!--' . $node->textContent . '-->';
                continue;
            }

            if ( $node->nodeType !== XML_ELEMENT_NODE ) {
                continue;
            }

            $tag_name = strtolower( $node->tagName );

            // Check if this is a parseable tag
            $parsed = self::try_parse_element( $node, $tag_name, $counters, $dom );

            if ( $parsed ) {
                $template .= '{{' . $parsed['slot'] . '}}';
                $elements[] = $parsed;
            } else {
                // Keep the tag as-is in the template, but process its depth-1 children
                $inner_result = self::process_container_node( $node, $counters, $dom );
                $template .= self::rebuild_tag_with_template( $node, $inner_result['template'], $dom );
                $elements = array_merge( $elements, $inner_result['elements'] );
            }
        }

        return array(
            'template' => $template,
            'elements' => $elements,
        );
    }

    /**
     * Process children of a container node at depth 1.
     *
     * @param DOMNode     $node     Container node.
     * @param array       $counters Reference to counters array.
     * @param DOMDocument $dom      DOM document.
     * @return array With 'template' and 'elements' keys.
     */
    private static function process_container_node( $node, &$counters, $dom ) {
        $elements = array();
        $template = '';

        foreach ( $node->childNodes as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $template .= $child->textContent;
                continue;
            }

            if ( $child->nodeType === XML_COMMENT_NODE ) {
                $template .= '<!--' . $child->textContent . '-->';
                continue;
            }

            if ( $child->nodeType !== XML_ELEMENT_NODE ) {
                continue;
            }

            $tag_name = strtolower( $child->tagName );
            $parsed = self::try_parse_element( $child, $tag_name, $counters, $dom );

            if ( $parsed ) {
                $template .= '{{' . $parsed['slot'] . '}}';
                $elements[] = $parsed;
            } else {
                // Not a parseable tag — keep it as raw HTML in the template
                $template .= $dom->saveHTML( $child );
            }
        }

        return array(
            'template' => $template,
            'elements' => $elements,
        );
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
     * Generate shortcode string from parsed data.
     *
     * @param array  $parsed   Parsed data from parse_html().
     * @param string $tag      Wrapper tag (div/section/article).
     * @param string $css_class CSS class for the section.
     * @return string Complete shortcode string.
     */
    public static function generate_shortcode( $parsed, $tag = 'div', $css_class = '' ) {
        $template = $parsed['template'];
        $elements = $parsed['elements'];

        // Escape double quotes in template for shortcode attribute
        $template_escaped = str_replace( '"', '&quot;', $template );

        $shortcode = '[ux_ultimate_section html_template="' . $template_escaped . '" tag="' . esc_attr( $tag ) . '" css_class="' . esc_attr( $css_class ) . '"]' . "\n";

        foreach ( $elements as $el ) {
            $shortcode .= '  ' . self::element_to_shortcode( $el ) . "\n";
        }

        $shortcode .= '[/ux_ultimate_section]';

        return $shortcode;
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
                $val_escaped = str_replace( '"', '&quot;', $val );
                $parts[] = $key . '="' . $val_escaped . '"';
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
}
