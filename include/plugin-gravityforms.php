<?php

namespace MonkeeBoy\Essentials;

if ( class_exists( 'GFCommon' ) ) {
  /**
   * Scroll page to confirmation after form submission when using Gravity Forms.
   */
  add_filter( 'gform_confirmation_anchor', '__return_true' );
}
