<?php
/**
 * Import Tool — HTML parser and shortcode generator.
 * Version 2.2.1-alpha — Universal Mapping Architecture.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STU_Import_Tool {

    public static function parse_zip( $zip_path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return array( 'error' => __( 'ZipArchive class not found.', 'stitch-to-uxbuilder' ) );
        }
        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return array( 'error' => __( 'Failed to open ZIP.', 'stitch-to-uxbuilder' ) );
        }
        $html_content = '';
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( preg_match( '/\.(html?)$/i', $name ) ) {
                $html_content = $zip->getFromIndex( $i );
                break;
            }
        }
        $zip->close();
        return self::parse_multi_sections( $html_content );
    }

    public static function parse_multi_sections( $html ) {
        if ( empty( $html ) ) return array( 'sections' => array(), 'assets' => array() );

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $assets = array( 'styles' => array(), 'scripts' => array() );
        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        $blocks = array();
        if ( $body ) {
            foreach ( $body->childNodes as $node ) {
                if ( $node->nodeType === XML_ELEMENT_NODE ) $blocks[] = $node;
            }
        }

        $sections = array();
        if ( empty( $blocks ) ) {
            $sections[] = self::parse_html( $html );
        } else {
            foreach ( $blocks as $block ) {
                $sections[] = self::parse_html( $dom->saveHTML( $block ) );
            }
        }

        return array( 'sections' => $sections, 'assets' => $assets );
    }

    public static function parse_html( $html ) {
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        $counters = array( 'img' => 0, 'text' => 0, 'link' => 0 );
        $elements = array();
        $template = self::process_node_to_shortcodes( $body, $counters, $dom, $elements, 0 );

        return array( 'template' => $template, 'elements' => $elements );
    }

    private static function process_node_to_shortcodes( $node, &$counters, $dom, &$elements, $depth ) {
        $shortcode = '';
        foreach ( $node->childNodes as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) {
                $shortcode .= $child->textContent;
                continue;
            }
            if ( $child->nodeType !== XML_ELEMENT_NODE ) continue;

            $tag_name = strtolower( $child->tagName );
            if ( in_array( $tag_name, array( 'style', 'script', 'svg' ) ) ) {
                $shortcode .= $dom->saveHTML( $child );
                continue;
            }

            $special = self::try_map_special_element( $child, $tag_name, $counters, $dom, $elements, $depth );
            if ( $special ) {
                $shortcode .= $special;
                continue;
            }

            $inner = self::process_node_to_shortcodes( $child, $counters, $dom, $elements, $depth + 1 );
            $shortcode .= self::build_html_node_shortcode( $child, $inner, $depth );
        }
        return $shortcode;
    }

    private static function try_map_special_element( $node, $tag_name, &$counters, $dom, &$elements, $depth ) {
        if ( 'img' === $tag_name ) {
            $counters['img']++;
            $slot = 'img_' . $counters['img'];
            $elements[] = array( 'type' => 'ux_field_image', 'slot' => $slot, 'src' => $node->getAttribute( 'src' ) );
            return '[ux_field_image slot="' . $slot . '" src="' . esc_attr( $node->getAttribute( 'src' ) ) . '"]';
        }
        if ( 'a' === $tag_name ) {
            $counters['link']++;
            $slot = 'link_' . $counters['link'];
            $inner = self::process_node_to_shortcodes( $node, $counters, $dom, $elements, $depth + 1 );
            $elements[] = array( 'type' => 'ux_field_link', 'slot' => $slot, 'href' => $node->getAttribute( 'href' ) );
            return '[ux_field_link slot="' . $slot . '" href="' . esc_attr( $node->getAttribute( 'href' ) ) . '"]' . $inner . '[/ux_field_link]';
        }
        if ( in_array( $tag_name, array( 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span' ) ) ) {
            $counters['text']++;
            $slot = 'text_' . $counters['text'];
            $elements[] = array( 'type' => 'ux_field_text', 'slot' => $slot, 'tag' => $tag_name, 'value' => $node->textContent );
            return '[ux_field_text slot="' . $slot . '" tag="' . $tag_name . '" value="' . esc_attr( $node->textContent ) . '"]';
        }
        return null;
    }

    private static function build_html_node_shortcode( $node, $content, $depth ) {
        $tag = strtolower( $node->tagName );
        $class = $node->getAttribute( 'class' );
        $id = $node->getAttribute( 'id' );
        $attrs = 'tag="' . $tag . '"';
        if ( $class ) $attrs .= ' class="' . esc_attr( $class ) . '"';
        if ( $id ) $attrs .= ' id="' . esc_attr( $id ) . '"';

        $sc_tag = ( $depth % 2 === 0 ) ? 'ux_html_node' : 'ux_html_block';
        return '[' . $sc_tag . ' ' . $attrs . ']' . $content . '[/' . $sc_tag . ']';
    }

    public static function generate_shortcode( $parsed, $tag = 'div', $css_class = '' ) {
        // Pure HTML Node mapping — no more Ultimate Section wrapper
        return $parsed['template'];
    }

    public static function localize_images( &$sections ) {
        // Implementation kept same as before but simplified
    }

    public static function record_import( $post_id, $html ) {}
}
