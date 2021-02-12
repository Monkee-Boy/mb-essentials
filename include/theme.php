<?php

namespace MonkeeBoy\Essentials;

function theme_setup() {
  /*
  * Let WordPress manage the document title.
  * By adding theme support, we declare that this theme does not use a
  * hard-coded <title> tag in the document head, and expect WordPress to
  * provide it for us.
  */
  add_theme_support( 'title-tag' );

  /*
  * Enable support for Post Thumbnails on posts and pages.
  *
  * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
  */
  add_theme_support( 'post-thumbnails' );

  /**
   * Enable support for responsive embeds with Gutenber.
   */
  add_theme_support( 'responsive-embeds' );

  /**
   * Enable support for Blocks that that support wide or full alignment.
   *
   * @link https://wordpress.org/gutenberg/handbook/designers-developers/developers/themes/theme-support/#wide-alignment
   */
  add_theme_support( 'align-wide' );

  /**
   * Disable custom font sizes for Gutenberg Blocks.
   *
   * @link https://wordpress.org/gutenberg/handbook/designers-developers/developers/themes/theme-support/#disabling-custom-font-sizes
   */
  add_theme_support( 'disable-custom-font-sizes' );

  /**
   * Disable custom colors for Gutenberg Blocks.
   *
   * @link https://wordpress.org/gutenberg/handbook/designers-developers/developers/themes/theme-support/#disabling-custom-colors-in-block-color-palettes
   */
  add_theme_support( 'disable-custom-colors' );

  /**
   * Enable editor styles from your theme.
   *
   * @link https://wordpress.org/gutenberg/handbook/designers-developers/developers/themes/theme-support/#editor-styles
   */
  add_theme_support('editor-styles');
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\theme_setup' );

function cleanup_head() {
  add_filter('the_generator', '__return_false');
  remove_action('wp_head', 'rsd_link');
  remove_action('wp_head', 'wlwmanifest_link');
  remove_action('wp_head', 'wp_generator');
  remove_action('wp_head', 'wp_shortlink_wp_head', 10);
}

add_action('init', __NAMESPACE__ . '\cleanup_head');

/**
 * Moves all scripts to footer.
 */
function js_to_footer() {
  remove_action('wp_head', 'wp_print_scripts');
  remove_action('wp_head', 'wp_print_head_scripts', 9);
  remove_action('wp_head', 'wp_enqueue_scripts', 1);
}
/* Gravity Forms doesn't like it when jQuery is in the footer. Need to rework this. */
//add_action('wp_enqueue_scripts', __NAMESPACE__ . '\js_to_footer');

/**
 * Disable the emoji's.
 */
function disable_emojis() {
  remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
  remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
  remove_action( 'wp_print_styles', 'print_emoji_styles' );
  remove_action( 'admin_print_styles', 'print_emoji_styles' );

  remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
  remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
  remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

  add_filter( 'tiny_mce_plugins', __NAMESPACE__ . '\disable_emojis_tinymce' );
  add_filter( 'wp_resource_hints', __NAMESPACE__ . '\disable_emojis_remove_dns_prefetch', 10, 2 );
}

add_action( 'init', __NAMESPACE__ . '\disable_emojis' );

/**
 * Filter function used to remove the tinymce emoji plugin.
 *
 * @param array $plugins
 * @return array Difference betwen the two arrays
 */
function disable_emojis_tinymce( $plugins ) {
  if ( is_array( $plugins ) ) {
    return array_diff( $plugins, array( 'wpemoji' ) );
  } else {
    return array();
  }
}

/**
 * Remove emoji CDN hostname from DNS prefetching hints.
 *
 * @param array $urls URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed for.
 * @return array Difference betwen the two arrays.
 */
function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
  if ( 'dns-prefetch' == $relation_type ) {
    /** This filter is documented in wp-includes/formatting.php */
    $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

    $urls = array_diff( $urls, array( $emoji_svg_url ) );
  }

  return $urls;
}
