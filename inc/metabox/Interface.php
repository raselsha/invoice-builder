<?php
/**
 * Represents the main page for invoice builder
 * 
 * this class will show the invoice builder main section into metabox.
 * 
 * @author Shahadat Hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */

if( ! defined('ABSPATH') ) { die( "Don't access directly" ); }

if( ! class_exists('INVB_Invoice_Builder_Interface') ){
    class INVB_Invoice_Builder_Interface{
        public function __construct()
        {
            add_action('invb_invoice_builder_interface',array($this,'interface'));
            add_action( 'save_post' , array( $this, 'save_post') );
        }

        public function interface($post_id){
            $embed_file = get_post_meta($post_id,'pdfev_emd_vwr_file_url',true);
            $embed_file =  $embed_file ? $embed_file :'';
            
            $check_download  = get_post_meta( $post_id, 'pdfev_emd_vwr_check_download', true );
            $check_download  = $check_download ? $check_download : 'yes';
            ?>
            <div>
                <?php wp_nonce_field( 'invb_invoice_builder_nonce', 'invb_invoice_builder_nonce' ); ?>  
                <h2>Invoice No: <?php echo get_the_ID(); ?></h2>
                <?php
                    if ( function_exists( 'the_custom_logo' ) ) {
                        the_custom_logo();
                    }
                ?>
            </div>

            <?php
        }

        public function save_post($post_id){

                if( isset( $_POST['invb_invoice_builder_nonce'] ) ){
                    if( ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['invb_invoice_builder_nonce'] ) ) , 'invb_invoice_builder_nonce' ) ){
                        return;
                    }
                }

                if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){
                    return;
                }

                if( isset($_POST['post_type']) && $_POST['post_type'] === 'pdfev_embed_viewer' ){
                    if( ! current_user_can('edit_page',$post_id) ){
                        return;
                    }
                    elseif( ! current_user_can('edit_post',$post_id) ){
                        return;
                    }
                }   

                if( isset($_POST['action']) and $_POST['action']=='editpost' ){                    

                    $file_url  = isset( $_POST['pdfev_emd_vwr_file_url'] ) ? sanitize_url($_POST['pdfev_emd_vwr_file_url']) : '';
					update_post_meta( $post_id, 'pdfev_emd_vwr_file_url', $file_url );
                    
                    $check_download  = isset( $_POST['pdfev_emd_vwr_check_download'] ) ? sanitize_text_field($_POST['pdfev_emd_vwr_check_download']) : 'no';
					update_post_meta( $post_id, 'pdfev_emd_vwr_check_download', $check_download );
                }
            }
    }
    
    new INVB_Invoice_Builder_Interface();
}