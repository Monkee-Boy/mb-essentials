<?php
/**
 * Plugin Name: Monkee-Boy WP-Essentials
 * Plugin URI: https://github.com/Monkee-Boy/wp-essentials
 * Description: WordPress plugin featuring basic tweaks common across our installs.
 * Version: 1.0
 * Author: Monkee-Boy
 * Author URI: https://www.monkee-boy.com
 * License: GPLv3
 */

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
  die;
}

define( 'MBOY_WPESSENTIALS_VERSION', '1.0' );

require_once __DIR__ . '/include/admin.php';
require_once __DIR__ . '/include/plugin-gravityforms.php';
require_once __DIR__ . '/include/plugin-acf.php';
require_once __DIR__ . '/include/theme.php';
require_once __DIR__ . '/include/search.php';
