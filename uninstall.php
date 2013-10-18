<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   TH_Multisite_Publishing
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/thuijssoon
 * @copyright 2013 Thijs Huijssoon
 */

// If uninstall, not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// TODO: Define uninstall functionality here