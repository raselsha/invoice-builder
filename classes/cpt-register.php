<?php
/**
 * Register a custom post type
 * 
 * this class generate a custom post type into wordpress.
 * 
 * @author Shahadat Hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */

if( ! defined('ABSPATH') ) { die( "Don't access directly" ); }

if( ! class_exists('INVB_Invoice_Builder_CPT') ){
    class INVB_Invoice_Builder_CPT{
        public function __construct() {
            add_action( 'init', array($this,'create_post_type'));          
        }

        public function create_post_type(){
            $labels = [
                "name" => __( "Invoice Builder", 'invoice-builder' ),
                "singular_name" => __( "Invoice Builder", 'invoice-builder' ),
                "menu_name" => __( "Invoice Builder", 'invoice-builder' ),
                "all_items" => __( "All Invoice", 'invoice-builder' ),
                "add_new" => __( "New Invoice", 'invoice-builder' ),
                "add_new_item" => __("New Invoice", 'invoice-builder' ), 
            ];

            $args = [
                "label" => __( "Invoice Builder", 'invoice-builder' ),
                "description" => __( "Invoice Builder", 'invoice-builder' ),
                "labels" => $labels, 
                "public" => true,
                "supports" => [''], // post support ui elements
                "hierarchical" => true, //parent child relation post type
                "show_ui" => true, // post type show ui to add, edit
                "show_in_menu" => true, // show menu into admin sidebar
                "menu_position" => 5, // menu position into admin sidebar
                "show_in_admin_bar" => true, // show into admin bar
                "show_in_nav_menus" => true, // show in nav menu
                "can_export" => true, // can export
                "has_archive" => true, // show into archive page template
                "exclude_from_search" => false, // exclude from search 
                "publicly_queryable" => true, // query custom post into page
                "show_in_rest" => false, // show into API

                "rest_base" => "", // API base endpoint
                "rest_controller_class" => "WP_REST_Posts_Controller",
                "delete_with_user" => true,
                "capability_type" => "post",
                "map_meta_cap" => true,
                "rewrite" => [ "slug" => "invoice-builder", "with_front" => true ],
                "query_var" => true,
                "menu_icon" => 'dashicons-welcome-widgets-menus',
                // "menu_icon" => INVB_Invoice_Builder_URL.'assets/images/invb-invoice-builder-icon.png',
                "show_in_graphql" => false,
                //"register_metabox_cb" => array($this,'add_meta_boxes'),
            ];
            register_post_type( "invb_invoice_builder", $args );
            
        }
    }

   new INVB_Invoice_Builder_CPT();
}