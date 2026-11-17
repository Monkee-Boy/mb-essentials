<?php

namespace MonkeeBoy\Essentials;

/**
 * Safe SVG uploads.
 *
 * SVG files are XML documents that can carry JavaScript (<script>, on* event
 * attributes, javascript: links, etc). Allowing every user with the
 * `upload_files` capability (Authors and up) to upload them unchecked is a
 * stored XSS vector. This module:
 *
 *  1. Only allows SVG uploads for users who are already trusted with raw HTML
 *     (the `unfiltered_html` capability: Administrators and Editors on single
 *     site installs, Super Admins only on multisite). Filterable via
 *     `mboy_essentials_svg_upload_capability`.
 *  2. Sanitizes every uploaded SVG with enshrined/svg-sanitize (allowlist of
 *     tags/attributes, strips scripts, event handlers and remote references),
 *     and rejects files that cannot be parsed.
 *  3. Provides `wp mboy-svg scan [--fix]` to audit SVGs uploaded before this fix.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Autoloader for the bundled enshrined/svg-sanitize library.
 * If another plugin (e.g. Safe SVG) already loaded it, that copy is used.
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'enshrined\\svgSanitize\\';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}
	$file = dirname( __DIR__ ) . '/vendor/svg-sanitizer/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
} );

/**
 * Whether the current user may upload SVG files.
 *
 * @return bool
 */
function current_user_can_upload_svg() {
	$capability = apply_filters( 'mboy_essentials_svg_upload_capability', 'unfiltered_html' );
	return (bool) current_user_can( $capability );
}

/**
 * Add SVG to the allowed upload types for trusted users only.
 *
 * @param array $mimes Allowed mime types.
 * @return array
 */
function svg_upload_mimes( $mimes ) {
	if ( current_user_can_upload_svg() ) {
		$mimes['svg'] = 'image/svg+xml';
	} else {
		unset( $mimes['svg'], $mimes['svgz'] );
	}
	return $mimes;
}
add_filter( 'upload_mimes', __NAMESPACE__ . '\svg_upload_mimes' );

/**
 * Sanitize an SVG string.
 *
 * @param string     $dirty  Raw SVG markup.
 * @param array|null $issues Populated with what the sanitizer removed.
 * @return string|false Clean markup, or false if it could not be parsed.
 */
function sanitize_svg_markup( $dirty, &$issues = null ) {
	$issues = array();
	if ( ! is_string( $dirty ) || $dirty === '' ) {
		return false;
	}

	// Compressed SVG (svgz) is not supported; reject rather than store unchecked.
	if ( substr( $dirty, 0, 2 ) === "\x1f\x8b" ) {
		return false;
	}

	// Fail closed (reject the upload) if the bundled library is missing, e.g. an
	// incomplete copy of the plugin without the vendor/ folder.
	if ( ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
		$issues = array( array( 'message' => 'SVG sanitizer library missing (vendor/svg-sanitizer)' ) );
		return false;
	}

	$sanitizer = new \enshrined\svgSanitize\Sanitizer();
	$sanitizer->removeRemoteReferences( true );

	$clean  = $sanitizer->sanitize( $dirty );
	$issues = (array) $sanitizer->getXmlIssues();

	if ( ! is_string( $clean ) || trim( $clean ) === '' ) {
		return false;
	}
	return strip_dangerous_css( $clean, $issues );
}

/**
 * Defense in depth: the library keeps <style> blocks and style attributes as-is.
 * Remove any that contain legacy script-capable CSS (javascript:/vbscript: URLs,
 * IE expression(), behavior, -moz-binding), including CSS-escaped variants.
 *
 * @param string $svg    Already-sanitized SVG markup.
 * @param array  $issues Appended with anything removed.
 * @return string|false
 */
function strip_dangerous_css( $svg, &$issues = array() ) {
	if ( stripos( $svg, 'style' ) === false ) {
		return $svg;
	}

	$is_dangerous = function ( $css ) {
		// Decode CSS escapes (\6a, \j), drop comments and whitespace before matching.
		$css = preg_replace_callback( '/\\\\([0-9a-f]{1,6})\s?/i', function ( $m ) {
			$cp = hexdec( $m[1] );
			if ( $cp <= 0 || $cp >= 0x110000 ) {
				return '';
			}
			return function_exists( 'mb_chr' ) ? (string) mb_chr( $cp, 'UTF-8' ) : ( $cp < 128 ? chr( $cp ) : '' );
		}, $css );
		$css = preg_replace( '/\\\\(.)/s', '$1', $css );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = preg_replace( '/[\s\x00-\x1f]+/', '', $css );
		return (bool) preg_match( '/(java|vb)script:|expression\(|behavior:|-moz-binding|data:text\/html/i', $css );
	};

	$dom = new \DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$ok = $dom->loadXML( $svg, LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok ) {
		return false;
	}

	$xpath = new \DOMXPath( $dom );
	foreach ( iterator_to_array( $xpath->query( '//*[local-name()="style"]' ) ) as $node ) {
		if ( $is_dangerous( $node->textContent ) ) {
			$node->parentNode->removeChild( $node );
			$issues[] = array( 'message' => 'Removed <style> with script-capable CSS' );
		}
	}
	foreach ( iterator_to_array( $xpath->query( '//@style' ) ) as $attr ) {
		if ( $is_dangerous( $attr->value ) ) {
			$attr->ownerElement->removeAttributeNode( $attr );
			$issues[] = array( 'message' => 'Removed style attribute with script-capable CSS' );
		}
	}

	return $dom->saveXML( $dom->documentElement );
}

/**
 * Is this upload an SVG (by name or reported type)?
 *
 * @param array $file Upload array.
 * @return bool
 */
function is_svg_upload( $file ) {
	$name = isset( $file['name'] ) ? strtolower( $file['name'] ) : '';
	$type = isset( $file['type'] ) ? strtolower( $file['type'] ) : '';
	return (bool) preg_match( '/\.svgz?$/', $name ) || strpos( $type, 'svg' ) !== false;
}

/**
 * Sanitize SVGs in place before WordPress stores them. Runs for normal uploads
 * and sideloads (e.g. media imported from a URL / REST API).
 *
 * @param array $file Upload array.
 * @return array
 */
function svg_upload_prefilter( $file ) {
	if ( ! is_svg_upload( $file ) ) {
		return $file;
	}

	if ( ! current_user_can_upload_svg() ) {
		$file['error'] = __( 'Sorry, you are not allowed to upload SVG files.', 'mboy' );
		return $file;
	}

	if ( ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
		$file['error'] = __( 'SVG uploads are unavailable: the Monkee-Boy Essentials plugin is missing its vendor/svg-sanitizer folder. Reinstall the full plugin.', 'mboy' );
		return $file;
	}

	$path  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	$dirty = ( $path && is_readable( $path ) ) ? file_get_contents( $path ) : false;
	$clean = sanitize_svg_markup( $dirty );

	if ( $clean === false || file_put_contents( $path, $clean ) === false ) {
		$file['error'] = __( 'This SVG file could not be sanitized and was not uploaded.', 'mboy' );
		return $file;
	}

	$file['size'] = strlen( $clean );
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', __NAMESPACE__ . '\svg_upload_prefilter' );
add_filter( 'wp_handle_sideload_prefilter', __NAMESPACE__ . '\svg_upload_prefilter' );

/**
 * Some servers report SVGs as text/plain or image/svg, which makes WordPress
 * reject them. Correct the type for trusted users only (the file has already
 * been sanitized by the prefilter at this point).
 *
 * @param array       $data     Values for the extension, mime type, and corrected filename.
 * @param string      $file     Full path to the file.
 * @param string      $filename The name of the file.
 * @param string[]    $mimes    Allowed mime types.
 * @return array
 */
function svg_check_filetype( $data, $file, $filename, $mimes ) {
	if ( empty( $data['ext'] ) && preg_match( '/\.svg$/i', $filename ) && current_user_can_upload_svg() ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}
add_filter( 'wp_check_filetype_and_ext', __NAMESPACE__ . '\svg_check_filetype', 10, 4 );

/**
 * WP-CLI: audit SVGs uploaded before this fix.
 *
 *   wp mboy-svg scan         List SVG attachments the sanitizer would change.
 *   wp mboy-svg scan --fix   Overwrite them with the sanitized version. Files that
 *                            cannot be parsed are reported for manual review.
 *
 * No backup is written into uploads (a backup there would keep the malicious
 * file publicly reachable). Take a site backup (e.g. WP Engine) before --fix.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mboy-svg scan', function ( $args, $assoc_args ) {
		$fix = ! empty( $assoc_args['fix'] );
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'post_mime_type' => 'image/svg+xml',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$checked = 0;
		$flagged = 0;
		$fixed   = 0;
		foreach ( $ids as $id ) {
			$path = get_attached_file( $id );
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}
			$checked++;
			$clean = sanitize_svg_markup( file_get_contents( $path ), $issues );

			// Ignore harmless findings (e.g. Illustrator "Generator" comments).
			$issues = array_values( array_filter( $issues, function ( $issue ) {
				return ! ( isset( $issue['message'] ) && stripos( $issue['message'], "'#comment'" ) !== false );
			} ) );

			if ( $clean !== false && empty( $issues ) ) {
				continue;
			}

			$flagged++;
			$author = (int) get_post_field( 'post_author', $id );
			$user   = get_userdata( $author );
			\WP_CLI::warning( sprintf( '#%d %s (uploaded by %s)', $id, $path, $user ? $user->user_login : "user $author" ) );
			if ( $clean === false ) {
				\WP_CLI::log( '  Could not be parsed as SVG; review or delete it manually.' );
				continue;
			}
			foreach ( array_slice( $issues, 0, 5 ) as $issue ) {
				\WP_CLI::log( '  - ' . ( isset( $issue['message'] ) ? $issue['message'] : 'issue' ) . ( ! empty( $issue['line'] ) ? ' (line ' . $issue['line'] . ')' : '' ) );
			}
			if ( $fix && file_put_contents( $path, $clean ) !== false ) {
				$fixed++;
				\WP_CLI::log( '  Sanitized.' );
			}
		}

		\WP_CLI::success( sprintf( 'Checked %d SVG(s); %d flagged%s.', $checked, $flagged, $fix ? ", $fixed sanitized" : '' ) );
	} );
}
