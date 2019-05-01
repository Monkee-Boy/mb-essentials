<?php

namespace MonkeeBoy\Essentials;

if ( class_exists( 'ACF' ) ) {
  /**
   * Remove the WP Custom Fields meta box (basic custom field inputs).
   *
   * @link https://www.advancedcustomfields.com/blog/acf-pro-5-5-13-update/
   */
  add_filter('acf/settings/remove_wp_meta_box', '__return_true');
}

if( function_exists('acf_add_options_page') ) {
  /**
   * Add default theme options page.
   *
   * This should house general settings like address, phone number, footer text, etc.
   */
	acf_add_options_page(array(
		'page_title' 	=> 'Theme Options',
		'menu_title'	=> 'Theme Options',
		'menu_slug' 	=> 'theme-options',
		'capability'	=> 'edit_posts',
		'redirect'		=> false
	));
}
