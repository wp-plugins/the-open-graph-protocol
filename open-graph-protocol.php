<?php
/**
 * Plugin Name: The Open Graph Protocol
 * Plugin URI: http://niftytheme.com
 * Description: The Open Graph protocol enables any web page to become a rich object in a social graph.
 * Version: 0.2
 * Author: Luis Alberto Ochoa Esparza
 * Author URI: http://luisalberto.org
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write
 * to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package Open Graph Protocol
 * @version 0.2
 * @author Luis Alberto Ochoa Esparza <soy@luisalberto.org>
 * @copyright Copyright (C) 2011-2012, Luis Alberto Ochoa Esparza
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/* Add Open Graph Metadata to the <head> area. */
add_action( 'wp_head', 'ogp_head_metadata', 1 );

/* Generates an excerpt from the description. */
add_filter( 'ogp_get_the_description', 'ogp_trim_description', 15 );

/**
 * Display the HTML for the OGP metadata.
 *
 * @since 0.1
 */
function ogp_head_metadata() {

	/* Set some variables for use within the function */
	$meta     = array();
	$metadata = ogp_get_metadata();

	/* Create the HTML for the OGP metadata */
	foreach( $metadata as $property => $content )
		$meta[] = "<meta property='og:{$property}' content='{$content}' />";

	echo apply_filters( 'ogp_head_metadata', join( PHP_EOL, $meta ) ) . PHP_EOL;
}

/**
 * Generates the content metadata "og:$property" based on object.
 *
 * Users or developers can overwritten using the "ogp_get_metadata" filter hook.
 *
 * @since 0.1
 */
function ogp_get_metadata() {
	global $wp_query;

	/* Set some variables for use within the function */
	$metadata = array();
	$object   = $wp_query->get_queried_object();

	/* Front page of the site */
	if ( is_front_page() && !is_paged() ) {

		$metadata = apply_filters( 'ogp_get_home_metadata', array(
			'type'        => 'website',
			'description' => get_bloginfo( 'description' )
		) );
	}

	/* Blog page */
	if ( is_home() ) {

		$metadata = apply_filters( 'ogp_get_blog_metadata', array(
			'type'        => 'blog',
			'description' => get_bloginfo( 'description' )
		) );
	}

	/* Singular views */
	elseif ( is_singular() ) {

		$metadata = apply_filters( "ogp_get_{$object->post_type}_metadata", array(
			'title'          => get_the_title(),
			'type'           => 'article',
			'description'    => ogp_get_the_description(),
			'url'            => get_permalink(),
			'image'          => ogp_get_the_image_post( $object->ID ),
			'published_time' => get_post_time( 'l, F jS, Y, g:i a' ),
			'modified_time'  => get_the_modified_time( 'l, F jS, Y, g:i a' ),
			'author'         => get_the_author_meta( 'display_name', $object->post_author )
		) );
	}

	return apply_filters( 'ogp_get_metadata', $metadata, $object );
}

/**
 * The main function for retrieving an image into a post.
 *
 * @since 0.1
 * @param int $post_id
 * @return string Image URL
 */
function ogp_get_the_image_post( $post_id ) {

	/* Set $post_type variable for use within the cache */
	$post_type = get_post_type( $post_id );

	/* Get cache key based on $post_id and $post_type */
	$key = md5( serialize( array( 'post_id' => $post_id, 'post_type' => $post_type ) ) );

	/* Check for a cached image */
	$image_cache = wp_cache_get( $post_id, 'ogp_get_the_image_post' );

	if ( !is_array( $image_cache ) )
		$image_cache = array();

	/* If there is no cached image, let's see if one exists */
	if ( !isset( $image_cache[$key] ) ) {

		/* Check for a post image (WP feature) */
		$image = ogp_get_image_by_post_thumbnail( $post_id );

		/* If no image found, check for a post image (WP feature) */
		if ( empty( $image ) )
			$image = ogp_get_the_image_by_attachment( $post_id );

		/* If no image found, scan the post for images */
		if ( empty( $image ) )
			$image = ogp_get_the_image_by_scan( $post_id );

		/* If no image found, get the default image */
		if ( empty( $image ) )
			$image = ogp_get_the_image_by_default();

		/* Set the image cache for the specific post. */
		$image_cache[$key] = $image;
		wp_cache_set( $post_id, $image_cache, 'ogp_get_the_image_post' );
	}

	/* If an image was already cached for the post and arguments, use it */
	else {
		$image = $image_cache[$key];
	}

	return $image;
}

/**
 * Check if a post has a Post Thumbnail attached and return it.
 * If no post image ID is found, return false.
 *
 * @since 0.1
 * @param int $post_id
 * @return string|bool Image URL. False if no image is found
 */
function ogp_get_image_by_post_thumbnail( $post_id ) {

	/* Check for a post image ID */
	$post_thumbnail_id = get_post_thumbnail_id( $post_id );

	/* If no post image ID is found, return default image */
	if ( empty( $post_thumbnail_id ) )
		return false;

	/* Get the attachment image source, this should return an array */
	$image = wp_get_attachment_image_src( $post_thumbnail_id, 'thumbnail' );

	/* Return the image URL */
	return $image[0];
}

/**
 * Check for attachment images.
 *
 * @since 0.1
 * @param int $post_id
 * @return string|bool Image URL. False if no image is found
 */
function ogp_get_the_image_by_attachment( $post_id ) {

	/* Set $attachment_id variable */
	$attachment_id = 0;

	/* Get the post type of the current post. */
	$post_type = get_post_type( $post_id );

	/* Check if the post itself is an image attachment. */
	if ( 'attachment' == $post_type && wp_attachment_is_image( $post_id ) ) {
		$attachment_id = $post_id;
	}

	/* Check if we have an attachment ID before proceeding. */
	if ( !empty( $attachment_id ) ) {

		/* Get the attachment image. */
		$image = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

		/* Return the image URL. */
		return $image[0];
	}

	/* Return false for anything else. */
	return false;
}

/**
 * Scans the post for images within the content. Shouldn't use if using large images
 * within posts, better to use the other options.
 *
 * @since 0.1
 * @param int $post_id
 * @return string|bool Image URL. False if no image is found
 */
function ogp_get_the_image_by_scan( $post_id ) {

	/* Search the post's content for the <img /> tag and get its URL. */
	preg_match_all( '|<img.*?src=[\'"](.*?)[\'"].*?>|i', get_post_field( 'post_content', $post_id ), $matches );

	/* If there is a match for the image, return its URL. */
	if ( isset( $matches ) && !empty( $matches[1][0] ) )
		return $matches[1][0];

	return false;
}

/**
 * The function simply returns the image URL.
 *
 * @since 0.1
 * @return string Image URL
 */
function ogp_get_the_image_by_default() {
	return apply_filters( 'ogp_get_the_image_by_default', plugin_dir_url ( __FILE__ ) . 'default.png' );
}

/**
 * This function generates the content.
 *
 * @since 0.2
 * @return string Description
 */
function ogp_get_the_description() {
	global $wp_query;

	$description = '';

	/* Get the excerpt */
	$description = get_post_field( 'post_excerpt', $wp_query->post->ID );

	/* Get the content */
	if ( empty( $description ) )
		$description = get_post_field( 'post_content', $wp_query->post->ID );

	return apply_filters( 'ogp_get_the_description', $description );
}

/**
 * Generates an excerpt from the description.
 *
 * The 25 word limit can be modified by plugins/themes using the 'ogp_trim_description_length' filter
 *
 * @since 0.2
 *
 * @param string $description The excerpt. If set to empty, an excerpt is generated.
 * @return string The excerpt.
 */
function ogp_trim_description( $description ) {

	/* Prepare the description. */
	$description = strip_shortcodes( $description );
	$description = str_replace(']]>', ']]&gt;', $description);

	/* Description length */
	$length = apply_filters( 'ogp_trim_description_length', 25 );

	/* Limit the description */
	$description = wp_trim_words( $description, $length, '...' );

	return $description;
}
