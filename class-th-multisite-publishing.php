<?php
/**
 * TH Multisite Publishing.
 *
 * @package   TH_Multisite_Publishing
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/thuijssoon
 * @copyright 2013 Thijs Huijssoon
 */

/**
 * TH_Multisite_Publishing
 *
 * @package TH_Multisite_Publishing
 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
 */
class TH_Multisite_Publishing {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   0.1.0
	 *
	 * @var     string
	 */
	const VERSION = '0.1.0';

	/**
	 * Unique identifier for your plugin.
	 *
	 * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	 * match the Text Domain file header in the main plugin file.
	 *
	 * @since    0.1.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'th-multisite-publishing';

	/**
	 * Instance of this class.
	 *
	 * @since    0.1.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    0.1.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * The List Table for the settings page.
	 *
	 * @since    0.1.0
	 *
	 * @var      WP_List_Table
	 */
	private $list_table = null;

	/**
	 * Has the database table already been created?
	 *
	 * @since    0.1.0
	 *
	 * @var      bool
	 */
	private static $table_created = false;

	private $suppress_from_actions = false;

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 *
	 * @since     0.1.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Add network settings page:
		add_action( 'network_admin_menu', array( $this, 'add_network_settings_page' ) );

		// Add the options page and menu item.
		// add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __FILE__ ) . 'th-multisite-publishing.php' );
		add_filter( 'network_admin_plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Load public-facing style sheet and JavaScript.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Define custom functionality. Read more about actions and filters: http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		add_action( 'current_screen', array( $this, 'pre_network_settings_page' ) );
		add_action( 'admin_post_th-msp-copy-terms',  array( $this, 'process_copy_terms_request' ) );
		add_action( 'init', array( $this, 'register_table' ) );

		// Add checkboxes to taxonomy screens
		$tax_mappings = get_site_option( 'th-multisite-publishing-site-tax-mapping', array() );
		$taxonomy_names = $tax_mappings[get_current_blog_id()];
		if ( ! empty( $taxonomy_names ) ) {
			add_action( 'admin_head', array( $this, 'acb_admin_head' ) );
			foreach ( $taxonomy_names as $taxonomy_name ) {
				add_action( $taxonomy_name . '_add_form_fields', array( $this, 'acb_add_publishing_options_form_create' ), 100 );
				add_action( $taxonomy_name . '_edit_form_fields', array( $this, 'acb_add_publishing_options_form_edit' ), 100, 2 );
				add_filter( $taxonomy_name . '_row_actions', array( $this, 'acb_row_actions' ), 10, 2 );
				add_action( 'created_' . $taxonomy_name, array( $this, 'acb_handle_publishing_options_form_create' ), 1000, 2 );
				add_action( 'edited_' . $taxonomy_name, array( $this, 'acb_handle_publishing_options_form_edit' ), 1000, 2 );  
				add_action( 'delete_' . $taxonomy_name, array( $this, 'acb_handle_delete_term' ), 1000, 3 );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.1.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    0.1.0
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide  ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}
				restore_current_blog();
			} else {
				self::single_activate();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    0.1.0
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Deactivate" action, false if WPMU is disabled or plugin is deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_deactivate();
				}
				restore_current_blog();
			} else {
				self::single_deactivate();
			}
		} else {
			self::single_deactivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    0.1.0
	 *
	 * @param int     $blog_id ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {
		if ( 1 !== did_action( 'wpmu_new_blog' ) )
			return;

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    0.1.0
	 *
	 * @return array|false The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {
		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
		return $wpdb->get_col( $sql );
	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    0.1.0
	 */
	private static function single_activate() {
		// Create the table only once
		if( !self::$table_created ) {
			if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
				require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
			}
			$bapi = TH_Multisite_Broadcast_API::get_instance();
			$bapi->create_table();
			self::$table_created = true;
		}
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    0.1.0
	 */
	private static function single_deactivate() {
		// TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.1.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     0.1.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen->id == $this->plugin_screen_hook_suffix ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), self::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     0.1.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen->id == $this->plugin_screen_hook_suffix ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		}

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    0.1.0
	 */
	public function add_network_settings_page() {
		$this->plugin_screen_hook_suffix = add_submenu_page(
			'settings.php',
			__( 'TH Multisite Publishing Options', $this->plugin_slug ),
			__( 'TH MU Pub', $this->plugin_slug ),
			'manage_network_options',
			$this->plugin_slug,
			array( $this, 'display_network_settings_page' )
		);
	}

	/**
	 * Setup the list table and handle the actions for the
	 * settings page.
	 *
	 * @since    0.1.0
	 */
	public function pre_network_settings_page() {
		// Only continue if the request is for the settings page
		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix . '-network' !== $screen->base ) {
			return;
		}

		// Prepare the options and post types
		$tab        = isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : false;
		$option_key = ( 'tax' === $tab ) ? 'th-multisite-publishing-site-tax-mapping' : 'th-multisite-publishing-site-cpt-mapping';
		$option     = get_site_option( $option_key, array() );
		$pts        = ( 'tax' === $tab ) ? get_taxonomies( '', 'objects' ) : get_post_types( array( 'public' => true ), 'objects' );
		$post_types = array();
		$qa         = '';

		foreach ( $pts as $key => $value ) {
			$post_types[$key] = $value->labels->name;
		}

		// Create the list table
		if ( !class_exists( 'TH_Sites_CPT_List_Table' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-sites-cpt-list-table.php';
		}
		$this->list_table = new TH_Sites_CPT_List_Table( $option, $post_types );
		//Fetch, prepare, sort, and filter our data...
		$this->list_table->prepare_items();

		// Only continue if there is an action
		if ( ! $this->list_table->current_action() ) {
			return;
		}

		if ( !check_admin_referer( 'TH_Multisite_Publishing', '_th_nonce_field' ) ) {
			return;
		}

		if( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$blog_ids = isset( $_REQUEST['blog_id'] ) ? $_REQUEST['blog_id'] : array();
		if ( !is_array( $blog_ids ) ) {
			$blog_ids = explode( ',', $blog_ids );
		}
		$blog_ids = array_map( 'intval', $blog_ids );

		//Detect when a bulk action is being triggered...
		if ( 'publish-all' === $this->list_table->current_action() ) {
			foreach ( $blog_ids as $blog_id ) {
				$option[$blog_id] = array_keys( $post_types );
			}
			$qa = 'published-all';
		} elseif ( 'publish-none' === $this->list_table->current_action() ) {
			foreach ( $blog_ids as $blog_id ) {
				$option[$blog_id] = array();
			}
			$qa = 'published-none';
		} elseif ( 'publish-some' === $this->list_table->current_action() ) {
	        $cpts = isset( $_POST['cpt'] ) ? $_POST['cpt'] : array();
	        if ( !is_array( $cpts ) ) {
	            $cpts = explode( ',', $cpts );
	        }
	        $cpt_blog_ids = isset( $_POST['cpt_blog_id'] ) ? $_POST['cpt_blog_id'] : array();
	        if ( !is_array( $cpt_blog_ids ) ) {
	            $cpt_blog_ids = explode( ',', $cpt_blog_ids );
	        }
	        foreach ( $cpt_blog_ids as $cpt_blog_id ) {
	            $cpt_blog_id = intval( $cpt_blog_id );
	            $option[$cpt_blog_id] = array_intersect( $cpts[$cpt_blog_id], array_keys( $post_types ) );
	        }
			$qa = 'published-some';
		}

		update_site_option( $option_key, $option );

		$location = add_query_arg( 'page', 'th-multisite-publishing', $_SERVER['PHP_SELF'] );
		if ( $tab ) {
			$location = add_query_arg( 'tab', $tab, $location );
		}
		$location = add_query_arg( $qa, true, $location );
		wp_redirect( $location );
		exit();
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    0.1.0
	 */
	public function display_network_settings_page() {
		$list_table = $this->list_table;
		$tab        = isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : false;

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'You don\'t have permission.', 'Permission denied' );
		}

		include_once 'views/network-settings-page-tabs.php';

		if( !$tab || 'copy' === $tab ) {
			if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
				require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
			}
			$bapi = TH_Multisite_Broadcast_API::get_instance();
			$blog_ids = $bapi->get_blog_ids();
			include_once 'views/network-settings-page-copy.php';
		} else {
			include_once 'views/network-settings-page-mapping.php';
		}
	}

	public function process_copy_terms_request() {
		check_admin_referer( 'TH_Multisite_Publishing' );
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( 'You don\'t have permission.', 'Permission denied' );
		}

		if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
		}
		$bapi = TH_Multisite_Broadcast_API::get_instance();
		$location = admin_url( 'network/settings.php?page=' . $this->plugin_slug );
		$location = add_query_arg( 'tab', 'copy', $location );

		$blog_ids = $bapi->get_blog_ids();

		$copy_from     = $_POST['copy_from'];
		$copy_from_ids = $blog_ids;
		$copy_to       = $_POST['copy_to'];
		$copy_to_ids   = array_diff( $blog_ids, array( $copy_from ) );

		if ( !isset( $copy_from ) || !in_array( $copy_from, $copy_from_ids ) ) {
			$location = add_query_arg( 'copy-from-error', true, $location );
			wp_redirect( $location );
			exit;
		}
		if ( !isset( $copy_to ) || !in_array( $copy_to, $copy_to_ids ) ) {
			$location = add_query_arg( 'copy-to-error', true, $location );
			wp_redirect( $location );
			exit;
		}

		$mappings    = get_site_option( 'th-multisite-publishing-site-tax-mapping', array() );
		$tax_to_copy = isset( $mappings[$copy_to] ) ? $mappings[$copy_to] : array();

		switch_to_blog( $copy_from );
		$terms = get_terms( $tax_to_copy, array( 'hide_empty' => false ) );

		$this->suppress_from_actions = true;
		$bapi->publish_terms($terms, $mappings );
		$this->suppress_from_actions = false;

		$location = add_query_arg( 'copied', true, $location );
		wp_redirect( $location );
		exit;
	} // end of function copy_terms

	/**
	 * Register the custom table
	 *
	 * @since    0.1.0
	 */
	public function register_table() {
		if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
		}
		$bapi = TH_Multisite_Broadcast_API::get_instance();
		$bapi->register_tables();
	}

	public function acb_add_publishing_options_form_create() {
		if( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		wp_nonce_field('th_msp_mapping_nonce_value','th_msp_mapping_nonce');
?>
		<div class="form-table" >
			<fieldset><legend class="screen-reader-text"><span> Publish term to network </span></legend>
				<label for="th_msp_publish_term"><input name="th_msp_publish_term" type="checkbox" id="th_msp_publish_term" value="0" checked="checked">
				&nbsp;Publish this term to your network of sites</label>
				<p class="description">This term can automatically be added to all the sites in your network.</p>
			</fieldset>
			<fieldset><legend class="screen-reader-text"><span> Synchronize across network </span></legend>
				<label for="th_msp_synchronize_term"><input name="th_msp_synchronize_term" type="checkbox" id="th_msp_synchronize_term" value="0" checked="checked">
				&nbsp;Synchronize across your network</label>
				<p class="description">You can propagate changes you make to this term to your entire network and visa versa.</p>
			</fieldset>
		</div>
<?php
	}

	public function acb_add_publishing_options_form_edit( $tag, $taxonomy ) {
		if( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		wp_nonce_field('th_msp_mapping_nonce_value','th_msp_mapping_nonce');
		if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
		}
		$bapi = TH_Multisite_Broadcast_API::get_instance();
		$synchronized = $bapi->is_publishing_synchronized(
			array(
				'blog_id'       => get_current_blog_id(),
				'object_id'     => $tag->term_id,
				'object_type'   => 'term',
			)
		);
?>
	<tr class="form-field">
		<th scope="row" valign="top">
			Publishing Options
		</th>
		<td>
			<fieldset><legend class="screen-reader-text"><span> Publish term to network </span></legend>
				<label for="th_msp_publish_term"><input name="th_msp_publish_term" type="checkbox" id="th_msp_publish_term" value="0" checked="checked">
				&nbsp;Publish this term to your network of sites</label>
				<p class="description">This term can automatically be added to all the sites in your network.</p>
			</fieldset>
			<fieldset><legend class="screen-reader-text"><span> Synchronize across network </span></legend>
				<label for="th_msp_synchronize_term"><input name="th_msp_synchronize_term" type="checkbox" id="th_msp_synchronize_term" value="0" <?php checked( $synchronized, true ); ?> >
				&nbsp;Synchronize across your network</label>
				<p class="description">You can propagate changes you make to this term to your entire network and visa versa.</p>
			</fieldset>
		</td>
	<tr>
<?php
	}

	public function acb_admin_head() {
		$screen = get_current_screen();
		if( 'edit-tags' === $screen->base ) {
?>
	<style type="text/css">
		.form-table input[type="checkbox"] { width: auto; }
	</style>
<?php			
		}
	}

	public function acb_handle_publishing_options_form_create( $term_id, $tt_id ) {
		if( $this->suppress_from_actions ) {
			return $term_id;
		}
		if( ! current_user_can( 'manage_network_options' ) ) {
			return $term_id;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$tax_name = $_POST['taxonomy'];
		} else {
			$tax_name = get_current_screen()->taxonomy;
		}

		// Check precondition: nonce
		// this will also filter out the posts with no post meta
		$nonce_action  = 'th_msp_mapping_nonce_value';
		$nonce_name = 'th_msp_mapping_nonce';

		if ( !isset( $_POST[$nonce_name] ) || !wp_verify_nonce( $_POST[$nonce_name], $nonce_action ) )
			return $term_id;

		if ( isset( $_POST['th_msp_publish_term'] ) ) {
			if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
				require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
			}
			$bapi = TH_Multisite_Broadcast_API::get_instance();
			$term = get_term( $term_id, $tax_name );
			$terms = array();
			$terms[] = $term;
			$taxonomy_mappings = get_site_option( 'th-multisite-publishing-site-tax-mapping', array() );
			$synchronized = isset($_POST['th_msp_synchronize_term']);
			$this->suppress_from_actions = true;
			$bapi->publish_terms($terms, $taxonomy_mappings, $synchronized );
			$this->suppress_from_actions = false;
		}
	}

	public function acb_handle_publishing_options_form_edit( $term_id, $tt_id ) {
		if( $this->suppress_from_actions ) {
			return $term_id;
		}

		if( ! current_user_can( 'manage_network_options' ) ) {
			return $term_id;
		}

		// Check precondition: nonce
		// this will also filter out the posts with no post meta
		$nonce_action  = 'th_msp_mapping_nonce_value';
		$nonce_name = 'th_msp_mapping_nonce';

		if ( !isset( $_POST[$nonce_name] ) || !wp_verify_nonce( $_POST[$nonce_name], $nonce_action ) ) {
			return $term_id;
		}

		if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
		}
		$bapi = TH_Multisite_Broadcast_API::get_instance();
		$tax_name = get_current_screen()->taxonomy;
		$term = get_term( $term_id, $tax_name );
		if ( isset( $_POST['th_msp_synchronize_term'] ) ) {
			$this->suppress_from_actions = true;
			$bapi->set_publishing_synchronized(
				array(
					'blog_id'       => get_current_blog_id(),
					'object_id'     => $term->term_id,
					'object_type'   => 'term',
				),
				true
			);
			$bapi->update_term( $term, true );
			$this->suppress_from_actions = false;
		} else {
			$bapi->set_publishing_synchronized(
				array(
					'blog_id'       => get_current_blog_id(),
					'object_id'     => $term->term_id,
					'object_type'   => 'term',
				),
				false
			);
		}
	}

	public function acb_handle_delete_term($term, $tt_id, $deleted_term ) {
		if( $this->suppress_from_actions ) {
			return $term;
		}

		if( ! current_user_can( 'manage_network_options' ) ) {
			return $term;
		}

		if ( !class_exists( 'TH_Multisite_Broadcast_API' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'class-th-multisite-broadcast-api.php';
		}
		$bapi = TH_Multisite_Broadcast_API::get_instance();

		$this->suppress_from_actions = true;
		$bapi->delete_term( $deleted_term );
		$this->suppress_from_actions = false;
	}

	public function acb_row_actions( $actions, $term ) {
		if(isset($actions['delete'])) {
			$actions['delete'] = str_replace(__( 'Delete' ), 'Delete from entire network', $actions['delete'] );
		}
		return $actions;
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    0.1.0
	 */
	public function add_action_links( $links ) {
		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'network/settings.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);
	}


}
