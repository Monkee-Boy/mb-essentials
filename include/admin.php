<?php

namespace MonkeeBoy\Essentials;

/**
 * Disable plugin/theme editor
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Allow svg's to be uploaded through the Media Manager.
 */
function add_file_types_to_uploads($file_types){
  $new_filetypes = array();
  $new_filetypes['svg'] = 'image/svg+xml';
  $file_types = array_merge($file_types, $new_filetypes );

  return $file_types;
}

add_action('upload_mimes', __NAMESPACE__ . '\add_file_types_to_uploads');

/**
 * Filter admin footer text "Thank you for creating..."
 */
function filter_admin_footer_text() {
	$new_text = sprintf( __( 'Thank you for creating with <a href="https://wordpress.org">WordPress</a> and <a href="https://www.monkee-boy.com">Monkee-Boy</a>.', 'mboy' ) );
	return $new_text;
}

add_filter( 'admin_footer_text', __NAMESPACE__ . '\filter_admin_footer_text' );

/**
 * Remove additional CSS under appearance.
 */
function customize_register( $wp_customize ) {
   $wp_customize->remove_section('custom_css');
}

add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );
