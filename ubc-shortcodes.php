<?php

/**
 *
 * @wordpress-plugin
 * Plugin Name:       UBC Shortcodes
 * Plugin URI:        http://ctlt.ubc.ca/
 * Description:       Moving shortcodes out of the CLF and into a plugin. Also adds extra shortcodes to deal with the 4.2.3 update
 * Version:           1.1.1
 * Author:            Richard Tape
 * Author URI:        http://blogs.ubc.ca/mbcx9rvt
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:		  ubc-shortcodes
*/

class UBC_Shortcodes {


	/**
	 * Initialize ourselves
	 *
	 * @since 1.0.0
	 *
	 * @param null
	 * @return null
	 */

	public function init() {

		// Add our actions/filters
		$this->add_actions();

	}/* init() */



	/**
	 * Add our hooks
	 *
	 * @since 1.0.0
	 *
	 * @param null
	 * @return null
	 */

	public function add_actions() {

		// Add our custom UBC Shortcodes
		add_action( 'init', array( $this, 'init__add_shortcodes' ) );

		// On save, check for shortcodes in attributes
		add_action( 'save_post', array( $this, 'save_post__check_for_shortcodes_in_attributes' ), 10, 3 );

		// OK, the post being saved has shortcodes in atts. Output a notice and link to docs.
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages__output_warning_about_shortcodes_in_atts' ) );

	}/* add_actions() */



	/**
	 * Method which actually calls add_shortcode
	 *
	 * @since 1.0.0
	 *
	 * @param null
	 * @return null
	 */

	public function init__add_shortcodes() {

		// Link with content i.e. [link_with_content link content link_class link_before link_after link_target]
		add_shortcode( 'link_with_content', array( $this, 'add_shortcode__link_with_content' ) );

		// Image shortcode i.e. [image_with_src link with_permalink class alt before after]
		add_shortcode( 'image_with_src', array( $this, 'add_shortcode__image_with_src' ) );

	}/* init__add_shortcodes() */



	/**
	 * An link with content shortcode. Defaults to a permalink with the title as the content, i.e.
	 * [link_with_content] === <a href="[the_permalink]" title="[the_title]">[the_title]</a>
	 *
	 * @since 1.0.0
	 *
	 * @param (array) $attr - Attributes passed to the shortcode
	 * @return (string) markup for this shortcode
	 */

	public function add_shortcode__link_with_content( $attr ) {

		$attr = shortcode_atts(
			array(
				'link' => get_the_permalink(),
				'content' => get_the_title(),
				'content_is_cf'	=> '',
				'content_is_excerpt' => '',
				'link_class' => '',
				'link_before' => '',
				'link_after' => '',
				'link_target' => '_self',
				'link_is_id' => '',
				'link_is_cf' => '',
				'link_prefix' => '',
				'after_url'	=> '',
			),
			$attr,
			'link_with_content'
		);

		// Start fresh
		$content = '';

		// If there's something to add before the link
		if ( ! empty( $attr['link_before'] ) ) {
			$content .= wp_kses_post( $attr['link_before'] );
		}

		// Empty variable to set link content $attr['content']
		$link_content = '';

		// If content_is_cf or content_is_excerpt is empty then attr default is get_the_title()
		if ( empty( $attr['content_is_cf'] ) && empty( $attr['content_is_excerpt'] ) ) {
			$link_content .= $attr['content'];
		}

		//If content_is_cf then Link is based off customfield
		if ( ! empty( $attr['content_is_cf'] ) ) {
			$link_content .= get_post_meta( get_the_ID(), esc_attr( $attr['content_is_cf'] ), true );
		}

		if ( ! empty( $attr['content_is_excerpt'] ) ) {
			$link_content .= get_the_excerpt();
		}

		// Build the actual href. If we don't have link_is_id or link_is_cf then it's just the_permalink
		// But we could potentially still have a prefix
		$href = '';

		// Do we have a prefix, if so, start with that
		if ( ! empty( $attr['link_prefix'] ) ) {
			$href .= $attr['link_prefix'];
		}

		// Do we have an after url set?, if so, add the permalink and append the after url content
		if ( ! empty( $attr['after_url'] ) ) {
			$href .= esc_url( $attr['link'] ) . esc_attr( $attr['after_url'] );
		}

		// If both id and shortcode empty, it's just the permalink
		if ( empty( $attr['link_is_id'] ) && empty( $attr['link_is_cf'] ) && empty( $attr['after_url'] ) ) {
			$href .= $attr['link'];
		}

		// IF we're specifying the ID, then the link is the current post ID
		if ( ! empty( $attr['link_is_id'] ) ) {
			$href .= get_the_ID();
		}

		// If we are linking to the content of a shortcode (urgh) then grab the content of that shortcode and use it as the link
		if ( ! empty( $attr['link_is_cf'] ) && empty( $attr['after_url'] ) ) {
			$href .= get_post_meta( get_the_ID(), esc_attr( $attr['link_is_cf'] ), true );
		}

		// Add the
		$main_link = '<a target="' . esc_attr( $attr['link_target'] ) . '" href="' . esc_url( $href ) . '" title="' . esc_attr( $attr['content'] ) . '" class="' . esc_html( $attr['link_class'] ) . '">' . wp_kses_post( $link_content ) . '</a>';

		$content .= $main_link;

		// If there's something to add after the link
		if ( ! empty( $attr['link_after'] ) ) {
			$content .= wp_kses_post( $attr['link_after'] );
		}

		return $content;

	}/* add_shortcode__link_with_content() */



	/**
	 * An image shortcode which outputs an img tag optionally with a link surrounding it
	 * Defaults to outputting the post thumbnail
	 * with_permalink overrides link if both are set
	 * [image_with_src link link_title with_permalink link_class img_url img_class img_alt img_before img_after]
	 *
	 * @since 1.0.0
	 *
	 * @param (array) $attr - Attributes passed to the shortcode
	 * @return (string) markup for this shortcode
	 */

	public function add_shortcode__image_with_src( $attr ) {

		$attr = shortcode_atts(
			array(
				'link' => '',
				'link_title' => get_the_title(),
				'with_permalink' => '',
				'after_permalink' => '',
				'link_class' => '',
				'link_target' => '_self',
				'link_is_cf' => '',
				'img_url' => wp_get_attachment_url( get_post_thumbnail_id( get_the_ID() ) ),
				'img_url_is_cf' => '',
				'img_class' => '',
				'img_class_is_cf' => '',
				'img_alt' => '',
				'img_alt_is_cf' => '',
				'img_before' => '',
				'img_after' => '',
				'img_size' => '',
			),
			$attr,
			'image_with_src'
		);

		// Start fresh
		$content = '';
		$custom_field = '';

		// Use post thumbnail when image size is set
		if ( ! empty( $attr['img_size'] ) ) {
			$attr['img_url'] = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID() ), esc_attr( $attr['img_size'] ) )[0];
		}

		// When img_url_is_cf is set, image src will be using value from custom field, img_size and img_url attributes will both be ignored.
		if ( ! empty( $attr['img_url_is_cf'] ) ) {
			$attr['img_url'] = get_post_meta( get_the_ID(), esc_attr( $attr['img_url_is_cf'] ), true );
		}

		// When img_class_is_cf is set, image class will be using value from custom field, img_class attribute will be ignored.
		if ( ! empty( $attr['img_class_is_cf'] ) ) {
			$attr['img_class'] = get_post_meta( get_the_ID(), esc_attr( $attr['img_class_is_cf'] ), true );
		}

		// When img_alt_is_cf is set, image alt description will be using value from custom field, img_alt attribute will be ignored.
		if ( ! empty( $attr['img_alt_is_cf'] ) ) {
			$attr['img_alt'] = get_post_meta( get_the_ID(), esc_attr( $attr['img_alt_is_cf'] ), true );
		}

		// If we are given a link, output an <a> tag
		if ( ! empty( $attr['link'] ) || ! empty( $attr['with_permalink'] ) || ! empty( $attr['link_is_cf'] ) ) {

			// If both are specified, with_permalink takes over, or if we're just adding a permalink
			if ( ( ! empty( $attr['link'] ) && ! empty( $attr['with_permalink'] ) ) || ! empty( $attr['with_permalink'] ) ) {
				$content .= '<a target="' . esc_attr( $attr['link_target'] ) . '" href="' . esc_url( get_the_permalink() ) . esc_url( $attr['after_permalink'] ) . '" title="' . esc_attr( $attr['link_title'] ) . '" class="' . esc_html( $attr['link_class'] ) . '">';
			}

			// If we're specifying a link...
			if ( ! empty( $attr['link'] ) && empty( $attr['with_permalink'] ) ) {
				$content .= '<a target="' . esc_attr( $attr['link_target'] ) . '" href="' . esc_url( $attr['link'] ) . '" title="' . esc_attr( $attr['link_title'] ) . '" class="' . esc_html( $attr['link_class'] ) . '">';
			}

			// If we're specifying a custom field...
			if ( ! empty( $attr['link_is_cf'] ) &&  empty( $attr['link'] ) && empty( $attr['with_permalink'] ) ) {

				$custom_field .= get_post_meta( get_the_ID(), esc_attr( $attr['link_is_cf'] ), true );

				$content .= '<a target="' . esc_attr( $attr['link_target'] ) . '" href="' . esc_url( $custom_field ) . '" title="' . esc_attr( $attr['link_title'] ) . '" class="' . esc_html( $attr['link_class'] ) . '">';
			}
		}

		// If there's something to add before the img
		if ( ! empty( $attr['img_before'] ) ) {
			$content .= wp_kses_post( $attr['img_before'] );
		}

		$main_image = '<img src="' . esc_url( $attr['img_url'] ) . '" alt="' . esc_attr( $attr['img_alt'] ) . '" class="' . esc_attr( $attr['img_class'] ) . '" />';
		$content .= $main_image;

		// If there's something to add after the link
		if ( ! empty( $attr['img_after'] ) ) {
			$content .= wp_kses_post( $attr['img_after'] );
		}

		// If we have a link, we need to close it out
		if ( ! empty( $attr['link'] ) || ! empty( $attr['with_permalink'] ) || ! empty( $attr['link_is_cf'] ) ) {
			$content .= '</a>';
		}

		return $content;

	}/* add_shortcode__image_with_src() */


	/**
	 * On save, we check if there are shortcodes in attributes in the content. If so, we throw a warning
	 *
	 * @since 1.0.0
	 *
	 * @param (int) $post_id - The ID of the post being saved
	 * @param (object) $post - the post object being saved
	 * @param (bool) $update - Whether an existing post is being updated or not
	 * @return
	 */

	public function save_post__check_for_shortcodes_in_attributes( $post_id, $post, $update ) {

		// If this is just a revision, don't send the email.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		$content = $post->post_content;

		if ( empty( $content ) ) {
			return;
		}

		// If this post doesn't contain any shortcodes, bail
		$has_shortcodes = $this->post_has_shortcodes_in_content( $content );

		if ( ! $has_shortcodes ) {
			delete_post_meta( $post_id, 'ubc_scia' );
			return;
		}

		// Now check for shortcodes in attributes
		$has_shortcodes_in_atts = $this->post_has_shortcodes_in_atts_in_content( $content );

		if ( ! $has_shortcodes_in_atts ) {
			delete_post_meta( $post_id, 'ubc_scia' );
			return;
		}

		update_post_meta( $post_id, 'ubc_scia', 'yes' );

	}/* save_post__check_for_shortcodes_in_attributes() */



	/**
	 * The method which outputs the warning to the person submitting the post if it contains
	 * shortcodes in attributes
	 *
	 * @since 1.0.0
	 *
	 * @param   -
	 * @return
	 */

	public function post_updated_messages__output_warning_about_shortcodes_in_atts( $messages ) {

		if ( ! isset( $_GET['post'] ) || 'yes' !== get_post_meta( absint( $_GET['post'] ), 'ubc_scia', true ) ) {
			return $messages;
		}

		$message = __( '<p><strong>Warning</strong>: It looks like you have used shortcodes within attributes on this post. As of version 4.2.3 of WordPress, that is no longer supported. If you are not using nested shortcodes (i.e. within a [loop], [columns], [accordions] or [tabs]) you should still be ok, however if you ARE within one of those, this may not produce the output you expect. Please see the <a href="http://wiki.ubc.ca/Documentation:UBC_Content_Management_System/Shortcodes/link_with_content" title="">[link_with_content]</a> and <a href="http://wiki.ubc.ca/Documentation:UBC_Content_Management_System/Shortcodes/image_with_src" title="">[image_with_src]</a> shortcodes for more information on possible alternatives.</p>', 'ubc-shortcodes' );

		$messages['post']['1'] = $messages['post']['1'] . $message;
		$messages['post']['2'] = $messages['post']['2'] . $message;
		$messages['post']['3'] = $messages['post']['3'] . $message;
		$messages['post']['4'] = $messages['post']['4'] . $message;
		$messages['post']['5'] = $messages['post']['5'] . $message;
		$messages['post']['6'] = $messages['post']['6'] . $message;
		$messages['post']['7'] = $messages['post']['7'] . $message;
		$messages['post']['8'] = $messages['post']['8'] . $message;
		$messages['post']['9'] = $messages['post']['9'] . $message;
		$messages['post']['10'] = $messages['post']['10'] . $message;

		return $messages;

	}/* post_updated_messages__output_warning_about_shortcodes_in_atts() */


	/**
	 * Check the content of a post for ANY shortcodes.
	 *
	 * @since 1.0.0
	 *
	 * @param (string) $content - The content of a post
	 * @return (bool) True if the post contains ANY shortcode
	 */

	public function post_has_shortcodes_in_content( $content = '' ) {

		if ( ! $content || empty( $content ) ) {
			return false;
		}

		if ( false === strpos( $content, '[' ) ) {
			return false;
		}

		return true;

	}/* post_has_shortcodes_in_content() */



	/**
	 * Check the content for shortcodes with attributes within the content
	 * <a href="[the_permalink]" title="[the_title]">[the_title]</a> 	= true
	 * <a href='[the_permalink]' title='[the_title]'>[the_title]</a> 	= true
	 * <a href='google.com' title='google'>[the_title]</a> 				= false
	 *
	 * @since 1.0.0
	 *
	 * @param (string) $content - The content through which to look
	 * @return (bool)
	 */

	public function post_has_shortcodes_in_atts_in_content( $content = '' ) {

		if ( empty( $content ) ) {
			return false;
		}

		if ( false !== strpos( $content, '="[' ) || false !== strpos( $content, "='[" ) ) {
			return true;
		}

		return false;

	}/* post_has_shortcodes_in_atts_in_content() */

}/* class UBC_Shortcodes */


add_action( 'plugins_loaded', 'plugins_loaded__init_ubcshortcodes' );

function plugins_loaded__init_ubcshortcodes() {

	$ubcshortcodes = new UBC_Shortcodes();
	$ubcshortcodes->init();

}/* plugins_loaded__init_ubcshortcodes() */
