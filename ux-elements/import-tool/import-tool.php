<?php
/**
 * Import Tool — HTML parser and shortcode generator.
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STU_Import_Tool {

    /**
     * Extract HTML from a ZIP file and parse it.
     */
    public static function parse_zip( $zip_path ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return array( 'error' => __( 'ZipArchive class not found.', 'stitch-to-uxbuilder' ) );
        }
        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return array( 'error' => __( 'Failed to open ZIP file.', 'stitch-to-uxbuilder' ) );
        }
        $html_content = '';
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = $zip->getNameIndex( $i );
            if ( preg_match( '/\.(html?)$/i', $name ) ) {
                if ( 'index.html' === $name || 'code.html' === $name || empty( $html_content ) ) {
                    $html_content = $zip->getFromIndex( $i );
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
     * Identify assets (images, styles, scripts) in HTML.
     */
    public static function identify_assets( $html ) {
        $assets = array( 'images' => array(), 'styles' => array(), 'scripts' => array() );
        if ( empty( $html ) ) return $assets;

        preg_match_all( '/src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp)(?:\?[^"\']*)?)["\']/i', $html, $img_matches );
        if ( ! empty( $img_matches[1] ) ) { $assets['images'] = array_unique( $img_matches[1] ); }

        preg_match_all( '/<style\b[^>]*>([\s\S]*?)<\/style>/i', $html, $style_matches );
        if ( ! empty( $style_matches[1] ) ) {
            foreach ( $style_matches[1] as $c ) { $assets['styles'][] = array( 'type' => 'inline', 'content' => trim( $c ) ); }
        }

        preg_match_all( '/<script\b[^>]*>([\s\S]*?)<\/script>/i', $html, $script_matches );
        if ( ! empty( $script_matches[1] ) ) {
            foreach ( $script_matches[1] as $c ) { $assets['scripts'][] = array( 'type' => 'inline', 'content' => trim( $c ) ); }
        }
        return $assets;
    }

    /**
     * Parse HTML and split it into multiple ultimate_section blocks.
     */
    public static function parse_multi_sections( $html ) {
        if ( empty( $html ) ) return array( 'sections' => array(), 'assets' => array() );
        $html = self::process_svg_localization( $html );

        $dom = new DOMDocument( '1.0', 'UTF-8' );
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $sections = array();
        $body = $dom->getElementsByTagName( 'body' )->item( 0 ) ?: $dom;
        $counters = array( 'text' => 0, 'img' => 0, 'link' => 0, 'heading' => 0, 'span' => 0 );
        $sections_data = self::process_node( $body, $counters, $dom );
        
        $sections[] = array(
            'template'      => $sections_data['template'],
            'elements'      => $sections_data['elements'],
            'wrapper_tag'   => 'div',
            'wrapper_class' => 'ultimate-section',
        );

        return array( 'sections' => $sections, 'assets' => self::identify_assets( $html ) );
    }

    /**
     * Recursive processor to find elements and build template.
     */
    private static function process_node( $node, &$counters, $dom ) {
        $elements = array();
        $template = '';
        foreach ( $node->childNodes as $child ) {
            if ( $child->nodeType === XML_TEXT_NODE ) { $template .= $child->textContent; continue; }
            if ( $child->nodeType === XML_COMMENT_NODE ) { $template .= '<!--' . $child->textContent . '-->'; continue; }
            if ( $child->nodeType !== XML_ELEMENT_NODE ) continue;

            $tag_name = strtolower( $child->tagName );
            if ( in_array( $tag_name, array( 'style', 'script', 'svg' ) ) ) {
                $template .= $dom->saveHTML( $child );
                continue;
            }

            $parsed = self::try_parse_element( $child, $tag_name, $counters, $dom );
            if ( $parsed ) {
                $template .= '{{' . $parsed['slot'] . '}}';
                $elements[] = $parsed;
            } else {
                $inner = self::process_node( $child, $counters, $dom );
                $template .= self::rebuild_tag_with_template( $child, $inner['template'], $dom );
                $elements = array_merge( $elements, $inner['elements'] );
            }
        }
        return array( 'template' => $template, 'elements' => $elements );
    }

    private static function try_parse_element( $node, $tag_name, &$counters, $dom ) {
        $heading_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
        if ( 'img' === $tag_name ) {
            $counters['img']++;
            return array( 'type' => 'ux_field_image', 'slot' => 'img_' . $counters['img'], 'src' => $node->getAttribute( 'src' ), 'alt' => $node->getAttribute( 'alt' ), 'css_class' => $node->getAttribute( 'class' ) );
        }
        if ( in_array( $tag_name, $heading_tags ) ) {
            $counters['heading']++;
            return array( 'type' => 'ux_field_text', 'slot' => 'heading_' . $counters['heading'], 'tag' => $tag_name, 'value' => self::get_inner_html( $node, $dom ), 'css_class' => $node->getAttribute( 'class' ) );
        }
        if ( 'p' === $tag_name ) {
            $counters['text']++;
            return array( 'type' => 'ux_field_text', 'slot' => 'text_' . $counters['text'], 'tag' => 'p', 'value' => self::get_inner_html( $node, $dom ), 'css_class' => $node->getAttribute( 'class' ) );
        }
        if ( 'a' === $tag_name ) {
            $counters['link']++;
            return array( 'type' => 'ux_field_link', 'slot' => 'link_' . $counters['link'], 'href' => $node->getAttribute( 'href' ), 'label' => self::get_inner_html( $node, $dom ), 'target' => $node->getAttribute( 'target' ) ?: '_self', 'css_class' => $node->getAttribute( 'class' ) );
        }
        return null;
    }

    private static function get_inner_html( $node, $dom ) {
        $inner_html = '';
        foreach ( $node->childNodes as $child ) { $inner_html .= $dom->saveHTML( $child ); }
        return preg_replace( '/<(path|rect|circle|ellipse|line|polyline|polygon|use)([^>]*)\s*><\/\1>/i', '<\1\2 />', $inner_html );
    }

    private static function rebuild_tag_with_template( $node, $inner_template, $dom ) {
        $tag = strtolower( $node->tagName );
        $attrs = '';
        if ( $node->hasAttributes() ) {
            foreach ( $node->attributes as $attr ) { $attrs .= ' ' . $attr->name . '="' . htmlspecialchars( $attr->value, ENT_QUOTES, 'UTF-8' ) . '"'; }
        }
        $void = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' );
        if ( in_array( $tag, $void ) ) return '<' . $tag . $attrs . ' />';
        return '<' . $tag . $attrs . '>' . $inner_template . '</' . $tag . '>';
    }

    public static function generate_multi_shortcode( $sections ) {
        $output = '';
        foreach ( $sections as $s ) { $output .= self::generate_shortcode( $s, $s['wrapper_tag'], $s['wrapper_class'] ); }
        return $output;
    }

    public static function generate_shortcode( $parsed, $tag = 'div', $css_class = '' ) {
        $shortcode = '[ux_ultimate_section tag="' . esc_attr( $tag ) . '" css_class="' . esc_attr( $css_class ) . '"]' . "\n";
        $shortcode .= $parsed['template'] . "\n";
        foreach ( $parsed['elements'] as $el ) { $shortcode .= self::element_to_shortcode( $el ) . "\n"; }
        return $shortcode . '[/ux_ultimate_section]';
    }

    private static function element_to_shortcode( $el ) {
        $attrs = array( 'slot' => $el['slot'], 'dynamic_enabled' => '0' );
        switch ( $el['type'] ) {
            case 'ux_field_image': $attrs['src'] = $el['src']; $attrs['alt'] = $el['alt']; $attrs['css_class'] = $el['css_class']; return self::build_shortcode_tag( 'ux_field_image', $attrs );
            case 'ux_field_text': $attrs['tag'] = $el['tag']; $attrs['value'] = $el['value']; $attrs['css_class'] = $el['css_class']; return self::build_shortcode_tag( 'ux_field_text', $attrs );
            case 'ux_field_link': $attrs['href'] = $el['href']; $attrs['label'] = $el['label']; $attrs['target'] = $el['target']; $attrs['css_class'] = $el['css_class']; return self::build_shortcode_tag( 'ux_field_link', $attrs );
        }
        return '';
    }

    private static function build_shortcode_tag( $tag, $attrs ) {
        $parts = array();
        foreach ( $attrs as $k => $v ) { if ( '' !== $v ) $parts[] = $k . '="' . esc_attr( $v ) . '"'; }
        return '[' . $tag . ' ' . implode( ' ', $parts ) . ']';
    }

    public static function record_import( $post_id, $html ) {
        $hash = md5( preg_replace( '/\s+/', ' ', trim( $html ) ) );
        $imported = get_post_meta( $post_id, '_stu_imported_hashes', true ) ?: array();
        if ( ! in_array( $hash, (array)$imported ) ) { $imported[] = $hash; update_post_meta( $post_id, '_stu_imported_hashes', $imported ); }
    }

    public static function localize_images( &$sections ) {
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $map = array();
        foreach ( $sections as &$s ) {
            foreach ( $s['elements'] as &$el ) {
                if ( 'ux_field_image' === $el['type'] && ! empty( $el['src'] ) ) {
                    if ( $new = self::maybe_download_image( $el['src'], $map ) ) $el['src'] = $new;
                }
            }
            $s['template'] = preg_replace_callback( '/(src|url)=["\']?([^"\')\s>]+)["\']?/i', function( $m ) use ( &$map ) {
                if ( strpos( $m[2], 'http' ) !== 0 && strpos( $m[2], 'data:image' ) !== 0 ) return $m[0];
                if ( $new = self::maybe_download_image( $m[2], $map ) ) return $m[1] . '="' . $new . '"';
                return $m[0];
            }, $s['template'] );
            $s['template'] = self::process_svg_localization( $s['template'], $map );
            foreach ( $s['elements'] as &$el ) { if ( ! empty( $el['value'] ) ) $el['value'] = self::process_svg_localization( $el['value'], $map ); }
        }
    }

    private static function process_svg_localization( $content, &$map = array() ) {
        return preg_replace_callback( '/<svg\b[^>]*>([\s\S]*?)<\/svg>/i', function ( $m ) {
            $svg = preg_replace( '/\s+/', ' ', $m[0] );
            $svg = str_replace( array( "\r", "\n", "\t" ), '', $svg );
            return trim( preg_replace( '/>\s+</', '><', $svg ) );
        }, $content );
    }

    private static function maybe_download_image( $url, &$map ) {
        if ( isset( $map[ $url ] ) ) return $map[ $url ];
        $is_b64 = ( strpos( $url, 'data:image' ) === 0 );
        if ( $is_b64 ) {
            if ( ! preg_match( '/data:image\/([a-z+]+);base64,(.*)/i', $url, $m ) ) return false;
            $ext = ( 'svg+xml' === $m[1] ) ? 'svg' : $m[1];
            $data = base64_decode( $m[2] ); $filename = md5( $url ) . '.' . $ext;
            $tmp = wp_tempnam( $filename ); file_put_contents( $tmp, $data );
        } else {
            $ext = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ?: 'jpg';
            $filename = md5( $url ) . '.' . $ext;
            $tmp = download_url( $url ); if ( is_wp_error( $tmp ) ) return false;
        }
        add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg' ) );
        $id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $tmp ), 0 );
        remove_filter( 'upload_mimes', array( __CLASS__, 'allow_svg' ) );
        if ( is_wp_error( $id ) ) { @unlink( $tmp ); return false; }
        return $map[ $url ] = wp_get_attachment_url( $id );
    }

    public static function allow_svg( $m ) { $m['svg'] = 'image/svg+xml'; return $m; }

    public static function scope_css( $css, $scope ) {
        $scope = '.' . $scope; $css = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
        $result = ''; $buffer = ''; $depth = 0; $at = '';
        for ( $i = 0; $i < strlen( $css ); $i++ ) {
            $c = $css[ $i ];
            if ( '{' === $c ) {
                $depth++; $chunk = trim( $buffer ); $buffer = '';
                if ( 1 === $depth ) {
                    if ( stripos( $chunk, '@keyframes' ) !== false ) { $at = 'k'; $result .= $chunk . '{'; }
                    elseif ( stripos( $chunk, '@media' ) !== false || stripos( $chunk, '@supports' ) !== false ) { $at = 'm'; $result .= $chunk . '{'; }
                    elseif ( 0 === strpos( $chunk, '@' ) ) { $at = 'o'; $result .= $chunk . '{'; }
                    else { $at = ''; $result .= self::prefix_sel( $chunk, $scope ) . '{'; }
                } elseif ( 2 === $depth && 'm' === $at ) { $result .= self::prefix_sel( $chunk, $scope ) . '{'; }
                else { $result .= $chunk . '{'; }
            } elseif ( '}' === $c ) { $result .= $buffer . '}'; $buffer = ''; $depth--; if ( 0 === $depth ) $at = ''; }
            else { $buffer .= $c; }
        }
        return $result . $buffer;
    }

    private static function prefix_sel( $str, $scope ) {
        $prefix = ltrim( $scope, '.' ); $res = array();
        foreach ( explode( ',', $str ) as $sel ) {
            $sel = trim( preg_replace( '/^(html|body|:root)\b\s*/', '', trim( $sel ) ) );
            if ( empty( $sel ) ) continue;
            $sc = preg_replace( '/\.([a-zA-Z0-9_-]+)/', '.' . $prefix . '-$1', $sel );
            $sc = preg_replace( '/#([a-zA-Z0-9_-]+)/', '#' . $prefix . '-$1', $sc );
            $res[] = ( strpos( $sc, '.' . $prefix ) !== false || strpos( $sc, '#' . $prefix ) !== false ) ? $sc : $scope . ' ' . $sc;
        }
        return implode( ', ', $res );
    }

    public static function scope_template_styles( $html, $scope ) {
        return preg_replace_callback( '/<style\b[^>]*>([\s\S]*?)<\/style>/i', function ( $m ) use ( $scope ) { return '<style>' . self::scope_css( $m[1], $scope ) . '</style>'; }, $html );
    }

    public static function prefix_html_content( $html, $prefix ) {
        $html = preg_replace_callback( '/\b(class|id)=["\']([^"\']+)["\']/i', function( $m ) use ( $prefix ) {
            $vals = explode( ' ', $m[2] );
            $pref = array_map( function( $v ) use ( $prefix ) {
                if ( empty( trim( $v ) ) || strpos( $v, '{{' ) !== false ) return $v;
                return $prefix . '-' . trim( $v );
            }, $vals );
            return $m[1] . '="' . implode( ' ', $pref ) . '"';
        }, $html );
        return $html;
    }
}
