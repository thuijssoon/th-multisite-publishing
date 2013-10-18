<?php
/**
 * TH Sites CPT List Table.
 *
 * @package   TH_Multisite_Publishing
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/thuijssoon
 * @copyright 2013 Thijs Huijssoon
 */

if ( !class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TH_Sites_CPT_List_Table extends WP_List_Table {

    private $post_types = null;
    private $option     = null;

    function __construct( $option = array(), $post_types = array() ) {
        global $status, $page;

        $this->option     = $option;
        $this->post_types = $post_types;

        //Set parent defaults
        parent::__construct( array(
                'singular'  => 'blog',     //singular name of the listed records
                'plural'    => 'blogs',    //plural name of the listed records
                'ajax'      => false        //does this table support ajax?
            ) );

    }

    function column_default( $item, $column_name ) {
        if ( 'domain' === $column_name ) {
            return $item[$column_name];
        } elseif ( in_array( $column_name, array_keys( $this->post_types ) ) ) {
            $checked = isset( $this->option[$item['blog_id']] ) ? checked( in_array( $column_name, $this->option[$item['blog_id']] ), true, false ) : '';
            return sprintf(
                '<input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s />',
                /*$1%s*/ 'cpt',
                /*$2%s*/ $item['blog_id'],
                /*$3%s*/ $column_name,
                /*$4%s*/ $checked
            );
        } else {
            return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    function column_domain( $item ) {
        $tab = isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : 'cpt';
        //Build row actions
        $nonce = wp_create_nonce( 'TH_Multisite_Publishing' );
        $actions = array(
            'publish-all'  => sprintf( '<a href="?page=%s&tab=%s&_th_nonce_field=%s&action=%s&blog_id=%s">Publish all</a>', $_REQUEST['page'], $tab, $nonce, 'publish-all', $item['blog_id'] ),
            'publish-none' => sprintf( '<a href="?page=%s&tab=%s&_th_nonce_field=%s&action=%s&blog_id=%s">Publish none</a>', $_REQUEST['page'], $tab, $nonce, 'publish-none', $item['blog_id'] ),
        );
        $hidden = sprintf( '<input type="hidden" name="%1$s" value="%2$s" />',
            /*$1%s*/ 'cpt_blog_id[]',
            /*$2%s*/ $item['blog_id']
        );

        //Return the title contents
        return sprintf( '<strong><a href="%1$s">%2$s</a> - <span class="post-state">blog_id:%3$s</span></strong>%4$s %5$s',
            /*$1%s*/ get_blog_option( $item['blog_id'], 'siteurl' ),
            /*$2%s*/ $item['domain'],
            /*$3%s*/ $item['blog_id'],
            /*$4%s*/ $this->row_actions( $actions ),
            /*$5%s*/ $hidden
        );
    }


    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ 'blog_id',
            /*$2%s*/ $item['blog_id']
        );
    }


    function get_columns() {
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'domain'     => 'Site',
        );
        $columns = array_merge( $columns, $this->post_types );
        return $columns;
    }


    function get_sortable_columns() {
        $sortable_columns = array(
            'domain'     => array( 'site', false ),     //true means it's already sorted
        );
        return $sortable_columns;
    }


    function get_bulk_actions() {
        $actions = array(
            'publish-all'    => 'Publish all',
            'publish-none'   => 'Publish none'
        );
        return $actions;
    }

    function current_action() {
        if( ! $current_action = parent::current_action() ) {
            if ( isset( $_POST['th-update-site-cpt-list-table-action'] ) ) {
                $current_action = 'publish-some';
            }
        }
        return $current_action;
    }

    function extra_tablenav( $which ) {
        if ( 'bottom' === $which ) {
            echo '<div class="alignleft actions">';
            submit_button( 'Update Mapping', 'primary', 'th-update-site-cpt-list-table-action', false, null );
            echo '</div>';
        }
    }

    function prepare_items() {

        global $wpdb;
        $table_name = $wpdb->prefix . 'blogs'; // do not forget about tables prefix

        $per_page = 20; // constant, how much records will be shown per page

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        // here we configure table headers, defined in our methods
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // will be used in pagination settings
        $total_items = $wpdb->get_var( "SELECT COUNT(blog_id) FROM $table_name" );

        // prepare query params, as usual current page, order by and order direction
        $paged = isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;
        $orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? $_REQUEST['orderby'] : 'domain';
        $order = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) ? $_REQUEST['order'] : 'asc';

        // [REQUIRED] define $items array
        // notice that last argument is ARRAY_A, so we will retrieve array
        $this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged ), ARRAY_A );

        // [REQUIRED] configure pagination
        $this->set_pagination_args( 
            array(
                'total_items' => $total_items, // total items defined above
                'per_page' => $per_page, // per page constant defined at top of method
                'total_pages' => ceil( $total_items / $per_page ) // calculate pages count
            )
        );
    }

    function update_some_action() {
        if ( !isset( $_POST['th-update-site-cpt-list-table-action'] ) ) {
            return;
        }
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
            $this->option[$cpt_blog_id] = array_intersect( $cpts[$cpt_blog_id], array_keys( $this->post_types ) );
        }
        update_site_option( 'th-multisite-publishing-site-cpt-mapping', $this->option );
        $location = add_query_arg( 'updated', true, $_SERVER['PHP_SELF'] );
        wp_redirect( $location, $status = 302 );
    }

}
