<?php
/**
 * Plugin installation logic.
 *
 * @package Syntax_Highlighting_Code_Block
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'syntax_highlighting' );
