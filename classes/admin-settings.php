<?php

/**
 * @author Shahadat Hossain <raselsha@gmail.com>
 * @package invoice-builder
 * @version 1.0.1
 */

namespace INVB;

defined('ABSPATH')  || exit;

class INVB_Admin_Settings{
    
    public function __construct() {
        
        add_action( 'admin_menu', array($this,'create_admin_menu') ); 
        add_action( 'init', array($this,'custom_rewrite_flush') ); 
        add_filter( 'plugin_action_links_invoice-builder/invoice-builder.php',[$this,'add_settings_link']);
    }   

    public function custom_rewrite_flush(){
        flush_rewrite_rules();
    }

    public function create_admin_menu(){
        add_submenu_page(
            'edit.php?post_type=invb_invoice_builder',
            __('PDF Embed - Viewer','invoice-builder'),
            __('Settings','invoice-builder'),
            'manage_options',
            'index', // page url 
            array($this,'settings_index_page'),
        ); 
    }

    public function add_settings_link($links){
        $url = esc_url( add_query_arg( array( 'post_type' => 'invb_invoice_builder', 'page' => 'index', ), get_admin_url() . 'edit.php' ) );
        
        $settings_link = "<a href='$url'>" . __( 'Settings','invoice-builder') . '</a>';
        // Adds the link to the end of the array.
        array_push(
            $links,
            $settings_link
        );
        return $links;
    }
    public function settings_index_page(){
        if( isset( $_POST['pdfev_emd_vwr_options_nonce'] ) ){
            if( ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['pdfev_emd_vwr_options_nonce'] ) ) , 'pdfev_emd_vwr_options_nonce' ) ){
                return;
            }
            if( ! current_user_can('manage_options')){
                return;
            }
            add_settings_error('pdfev_emd_vwr_options_nonce','save-data',__('Settings Saved!','invoice-builder'),'success');
            settings_errors('pdfev_emd_vwr_options_nonce');
            
        }
        $this->settings_html_layout();
    
    }

    public function settings_html_layout(){
        ?>
        <div class="wrap admin-wrap pdfev-embed-viewer">
            <h2><?php get_admin_page_title(); ?></h2>
            <h2 class="nav-tab-wrapper">
                <?php do_action('pdfev_settings_tabs'); ?>                
            </h2>
            <?php do_action('pdfev_settings_tabs_content'); ?>
        </div>
        <?php
    }
}
    
new \INVB\INVB_Admin_Settings();
