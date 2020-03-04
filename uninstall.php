<?php

if ( !defined('WP_UNINSTALL_PLUGIN') ) {
	die;
}

delete_option('syntax_highlighting');
