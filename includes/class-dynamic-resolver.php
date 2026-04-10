<?php
/**
 * Dynamic data resolver for Stitch to UX Builder.
 *
 * Resolves dynamic sources with prefix syntax:
 *   post:title, post:excerpt, post:thumbnail
 *   acf:field_name
 *   woo:price, woo:thumbnail, woo:sku
 *   meta:_custom_key
 *
 * @package StitchToUXBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class STU_Dynamic_Resolver {

    /**
     * Resolve a dynamic source string to its value.
     *
     * Uses get_the_ID() to ensure correct context inside loops.
     *
     * @param string $source Dynamic source string (e.g., "post:title", "acf:summary").
     * @return string|null Resolved value or null if source is invalid/empty.
     */
    public static function resolve( $source ) {
        if ( empty( $source ) ) {
            return null;
        }

        $source = trim( $source );
        $parts  = explode( ':', $source, 2 );

        if ( count( $parts ) !== 2 ) {
            return null;
        }

        $prefix = strtolower( $parts[0] );
        $key    = $parts[1];
        $post_id = get_the_ID();

        switch ( $prefix ) {
            case 'post':
                return self::resolve_post( $key, $post_id );

            case 'acf':
                return self::resolve_acf( $key, $post_id );

            case 'woo':
                return self::resolve_woo( $key, $post_id );

            case 'meta':
                return self::resolve_meta( $key, $post_id );

            default:
                return null;
        }
    }

    /**
     * Resolve post built-in fields.
     *
     * @param string $key     Field key (title, excerpt, thumbnail, date, author, permalink, id, content).
     * @param int    $post_id Post ID.
     * @return string|null
     */
    private static function resolve_post( $key, $post_id ) {
        if ( ! $post_id ) {
            return null;
        }

        switch ( $key ) {
            case 'title':
                return get_the_title( $post_id );

            case 'excerpt':
                $post = get_post( $post_id );
                return $post ? wp_trim_words( $post->post_excerpt ?: $post->post_content, 55, '...' ) : null;

            case 'thumbnail':
                return get_the_post_thumbnail_url( $post_id, 'full' );

            case 'thumbnail_id':
                return get_post_thumbnail_id( $post_id );

            case 'date':
                return get_the_date( '', $post_id );

            case 'author':
                $post = get_post( $post_id );
                return $post ? get_the_author_meta( 'display_name', $post->post_author ) : null;

            case 'permalink':
                return get_permalink( $post_id );

            case 'id':
                return (string) $post_id;

            case 'content':
                $post = get_post( $post_id );
                return $post ? apply_filters( 'the_content', $post->post_content ) : null;

            default:
                return null;
        }
    }

    /**
     * Resolve ACF field.
     *
     * @param string $key     ACF field name.
     * @param int    $post_id Post ID.
     * @return string|null
     */
    private static function resolve_acf( $key, $post_id ) {
        if ( ! function_exists( 'get_field' ) ) {
            return null;
        }

        $value = get_field( $key, $post_id );

        if ( is_array( $value ) ) {
            // ACF image field returns array — get URL
            if ( isset( $value['url'] ) ) {
                return $value['url'];
            }
            // ACF link field returns array
            if ( isset( $value['title'] ) && isset( $value['url'] ) ) {
                return $value['url'];
            }
            return null;
        }

        return is_string( $value ) || is_numeric( $value ) ? (string) $value : null;
    }

    /**
     * Resolve WooCommerce product data.
     *
     * @param string $key     Product field (price, thumbnail, sku, regular_price, sale_price, etc.).
     * @param int    $post_id Post ID.
     * @return string|null
     */
    private static function resolve_woo( $key, $post_id ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return null;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return null;
        }

        switch ( $key ) {
            case 'price':
                return $product->get_price_html();

            case 'regular_price':
                return wc_price( $product->get_regular_price() );

            case 'sale_price':
                $sale = $product->get_sale_price();
                return $sale ? wc_price( $sale ) : null;

            case 'raw_price':
                return $product->get_price();

            case 'sku':
                return $product->get_sku();

            case 'thumbnail':
                $image_id = $product->get_image_id();
                return $image_id ? wp_get_attachment_url( $image_id ) : null;

            case 'title':
                return $product->get_name();

            case 'short_description':
                return $product->get_short_description();

            case 'description':
                return $product->get_description();

            case 'permalink':
                return $product->get_permalink();

            case 'stock_status':
                return $product->get_stock_status();

            case 'stock_quantity':
                $qty = $product->get_stock_quantity();
                return $qty !== null ? (string) $qty : null;

            case 'weight':
                return $product->get_weight();

            case 'rating':
                return (string) $product->get_average_rating();

            case 'review_count':
                return (string) $product->get_review_count();

            case 'add_to_cart_url':
                return $product->add_to_cart_url();

            case 'gallery':
                $ids = $product->get_gallery_image_ids();
                if ( empty( $ids ) ) {
                    return null;
                }
                return wp_get_attachment_url( $ids[0] );

            default:
                return null;
        }
    }

    /**
     * Resolve raw post meta.
     *
     * @param string $key     Meta key.
     * @param int    $post_id Post ID.
     * @return string|null
     */
    private static function resolve_meta( $key, $post_id ) {
        if ( ! $post_id ) {
            return null;
        }

        $value = get_post_meta( $post_id, $key, true );

        if ( is_string( $value ) || is_numeric( $value ) ) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Resolve a dynamic source for an image element.
     * Returns the image URL.
     *
     * @param string $source Dynamic source string.
     * @return string|null Image URL or null.
     */
    public static function resolve_image( $source ) {
        $value = self::resolve( $source );

        // If value is a numeric ID, convert to URL
        if ( $value && is_numeric( $value ) ) {
            $url = wp_get_attachment_url( intval( $value ) );
            return $url ?: null;
        }

        return $value;
    }

    /**
     * Resolve ACF link field — returns array [url, title, target].
     *
     * @param string $source ACF field source (e.g., "acf:cta_link").
     * @return array|null Link data or null.
     */
    public static function resolve_link( $source ) {
        if ( empty( $source ) ) {
            return null;
        }

        $parts = explode( ':', $source, 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }

        $prefix = strtolower( $parts[0] );
        $key    = $parts[1];

        if ( 'acf' === $prefix && function_exists( 'get_field' ) ) {
            $value = get_field( $key, get_the_ID() );
            if ( is_array( $value ) && isset( $value['url'] ) ) {
                return $value;
            }
        }

        // For other prefixes, resolve as plain URL
        $resolved = self::resolve( $source );
        if ( $resolved ) {
            return array( 'url' => $resolved );
        }

        return null;
    }
}
