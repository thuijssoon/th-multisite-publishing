<?php
/**
 * TH Multisite Publishing
 *
 * @package   TH_Multisite_Publishing
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/thuijssoon
 * @copyright 2013 Your Name or Company Name
 *
 * @wordpress-plugin
 * Plugin Name: TH Multisite Publishing
 * Plugin URI:  https://github.com/thuijssoon
 * Description: Plugin enables you to publish posts, terms from one blog to another within a network.
 * Version:     0.1.0
 * Author:      Thijs Huijssoon
 * Author URI:  https://github.com/thuijssoon
 * Text Domain: th-multisite-publishing
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once( plugin_dir_path( __FILE__ ) . 'class-th-multisite-publishing.php' );

// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
register_activation_hook( __FILE__, array( 'TH_Multisite_Publishing', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TH_Multisite_Publishing', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'TH_Multisite_Publishing', 'get_instance' ) );
