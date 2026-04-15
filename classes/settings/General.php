<?php
/**
 * @author shahadat hossain <raselsha@gmail.com>
 * @version 1.0.0
 * @since 1.1.0
 */

namespace PDFEV;

defined('ABSPATH') || exit;

class INVB_General_Settings{
    public function __construct() {
        add_action( 'init', array($this,'save_options_data') );
        add_action('pdfev_settings_tabs',array($this,'tabs'),10,0);
        add_action('pdfev_settings_tabs_content',array($this,'tabs_content'));
    
    }
    
    public function tabs(){
        ?>
        <button class="nav-tab active" data-tab-target="pdfev_emd_vwr_admin_tabs_settings"> <?php echo esc_html__('General Settings','pdf-embed-viewer'); ?> </button>
        <?php
    }
    public function tabs_content(){
        ?>
        <div class="pdfev-tab-content active" data-tab="pdfev_emd_vwr_admin_tabs_settings">
            <form action="" method="post">
                <?php wp_nonce_field( 'pdfev_emd_vwr_options_nonce', 'pdfev_emd_vwr_options_nonce' ); ?>
                <?php $this->options_fields(); ?>
                <?php submit_button(); ?>
            </form>
        </div> 
        <?php
    }

    public function get_all_pages(){
        $pages = get_pages();
        $page_array = [];
        foreach ( $pages as $page ) {
            $page_array[] = [
                'ID' => $page->ID,
                'title' => $page->post_title,
                'slug' => $page->post_name,
                'url' => get_permalink( $page->ID ),
            ];
        }
        return $page_array;
    }
    
    public function options_fields(){
        $archive_title  = get_option('pdfev_archive_title'); 
        $archive_slug  = get_option('pdfev_archive_slug')??'pdf-embed-viewer'; 
            
        $template = get_option('pdfev_archive_template');
        $template_lists =  get_option('pdfev_archive_template_lists');
        $shortcode_page_url  = get_option('pdfev_shortcode_page_url');
        $shortcode_page_url  = $shortcode_page_url?$shortcode_page_url:'';
        $page_lists = $this->get_all_pages();
        
        $flipbook =  get_option('pdfev_flipbook_status');
        $flipbook = $flipbook ? $flipbook : 'yes';

        $archive_read =  get_option('pdfev_archive_read');
        $archive_read = $archive_read ? $archive_read : 'no';

        $reading_counter =  get_option('pdfev_reading_counter');
        $reading_counter = $reading_counter ? $reading_counter : 'yes';
        
        $archive_download =  get_option('pdfev_archive_download');
        $archive_download = $archive_download ? $archive_download : 'no';

        $download_counter =  get_option('pdfev_download_counter');
        $download_counter = $download_counter ? $download_counter : 'yes';

        $colors         = get_option('pdfev_css_colors');         
        $primary        = $colors['primary'] ? $colors['primary']:'#c79f62';
        $secondary      = $colors['secondary']?$colors['secondary']:'#666666';
        $dark           = $colors['dark']?$colors['dark']:'#333333';
        $light          = $colors['light']?$colors['light']:'#e5e5e5';

        ?>
            <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Archive Title','pdf-embed-viewer') ?></th>
                    <td>
                        <input type="text" name="pdfev_archive_title" placeholder="Pdf Embed Viewer" value="<?php echo esc_attr($archive_title); ?>">
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Archive Slug', 'pdf-embed-viewer' )?></th>
                    <td>
                        <input type="text" name="pdfev_archive_slug" placeholder="pdf-embed-viewer" value="<?php echo esc_attr($archive_slug); ?>">
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Back to Overview Page', 'pdf-embed-viewer' )?></th>
                    <td>
                        <select name="pdfev_shortcode_page_url">
                            <option value=""><?php echo esc_html__('Select Page','pdf-embed-viewer'); ?></option>
                            <?php foreach($page_lists as $value): ?>
                            <option value="<?php echo esc_attr($value['slug']); ?>" <?php selected($value['slug'], $shortcode_page_url); ?>><?php echo esc_html($value['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Archive Template', 'pdf-embed-viewer' )?></th>
                    <td>
                        <select name="pdfev_archive_template">
                            <?php foreach($template_lists as $key => $value): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php echo $key==$template? esc_attr('selected'):'' ?>><?php echo esc_attr($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Default View 3D Flipbook', 'pdf-embed-viewer' )?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="pdfev_flipbook_status" value="<?php echo esc_attr($flipbook); ?>" <?php echo esc_attr(($flipbook=='yes')?'checked':''); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Show Read Button', 'pdf-embed-viewer' )?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="pdfev_archive_read" value="<?php echo esc_attr($archive_read); ?>" <?php echo esc_attr(($archive_read=='yes')?'checked':''); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Show Reading Counter', 'pdf-embed-viewer' )?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="pdfev_reading_counter" value="<?php echo esc_attr($reading_counter); ?>" <?php echo esc_attr(($reading_counter=='yes')?'checked':''); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Show Download Button', 'pdf-embed-viewer' )?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="pdfev_archive_download" value="<?php echo esc_attr($archive_download); ?>" <?php echo esc_attr(($archive_download=='yes')?'checked':''); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php echo esc_html__( 'Show Download Counter', 'pdf-embed-viewer' )?></th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="pdfev_download_counter" value="<?php echo esc_attr($download_counter); ?>" <?php echo esc_attr(($download_counter=='yes')?'checked':''); ?>>
                            <span class="slider"></span>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Primary Color','pdf-embed-viewer') ?></th>
                    <td>
                        <input class="pdfev-color-field" type="text" name="pdfev_css_colors[primary]" value="<?php echo esc_attr($primary); ?>">     
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Secondary Color','pdf-embed-viewer') ?></th>
                    <td>
                        <input class="pdfev-color-field" type="text"  name="pdfev_css_colors[secondary]"  value="<?php echo esc_attr($secondary); ?>">         
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Dark Color','pdf-embed-viewer') ?></th>
                    <td>
                        <input  class="pdfev-color-field" type="text"  name="pdfev_css_colors[dark]" value="<?php echo esc_attr($dark); ?>">
                            
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Light Color','pdf-embed-viewer') ?></th>
                    <td> <input class="pdfev-color-field" type="text"  name="pdfev_css_colors[light]" value="<?php echo esc_attr($light); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Import Demo Content','pdf-embed-viewer') ?></th>
                    <td> 
                        <input type="button" class="button-primary pdfev-import-demo-content" value="<?php _e('Import Demo','pdf-embed-viewer') ?>">
                        
                    </td>
                </tr>
                <tr>
                    <td class="demo-import-success" colspan="2"></td>
                </tr>
            </tbody>
            </table>

        <?php
    }

    public function save_options_data() {
        if( isset( $_POST['pdfev_emd_vwr_options_nonce'] ) ){
            if( ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['pdfev_emd_vwr_options_nonce'] ) ) , 'pdfev_emd_vwr_options_nonce' ) ){
                return;
            }
            
            $archive_title      = isset( $_POST['pdfev_archive_title'] ) ? sanitize_text_field($_POST['pdfev_archive_title']): 'Pdf Embed Viewer';
            $archive_slug       = isset( $_POST['pdfev_archive_slug'] ) ? sanitize_text_field(sanitize_title($_POST['pdfev_archive_slug'])): 'pdf-embed-viewer';
            $shortcode_page_url = isset( $_POST['pdfev_shortcode_page_url'] ) ? sanitize_text_field($_POST['pdfev_shortcode_page_url']): '';
            $archive_template   = isset( $_POST['pdfev_archive_template'] ) ? sanitize_text_field($_POST['pdfev_archive_template']): 'list';
            $flipbook           = isset( $_POST['pdfev_flipbook_status'] ) ? sanitize_text_field($_POST['pdfev_flipbook_status']): 'no';
            $archive_read       = isset( $_POST['pdfev_archive_read'] ) ? sanitize_text_field($_POST['pdfev_archive_read']): 'no';
            $reading_counter    = isset( $_POST['pdfev_reading_counter'] ) ? sanitize_text_field($_POST['pdfev_reading_counter']): 'no';
            $archive_download   = isset( $_POST['pdfev_archive_download'] ) ? sanitize_text_field($_POST['pdfev_archive_download']): 'no';
            $download_counter   = isset( $_POST['pdfev_download_counter'] ) ? sanitize_text_field($_POST['pdfev_download_counter']): 'no';
            $primary            = isset( $_POST['pdfev_css_colors']['primary'] ) ? sanitize_hex_color($_POST['pdfev_css_colors']['primary']) : '';
            $secondary          = isset( $_POST['pdfev_css_colors']['secondary'] ) ? sanitize_hex_color($_POST['pdfev_css_colors']['secondary']) : '';
            $dark               = isset( $_POST['pdfev_css_colors']['dark'] ) ? sanitize_hex_color($_POST['pdfev_css_colors']['dark'])  : '';
            $light              = isset( $_POST['pdfev_css_colors']['light'] ) ? sanitize_hex_color($_POST['pdfev_css_colors']['light'])    :'';
            $colors = [
                'primary'   => $primary,
                'secondary' => $secondary,
                'dark'      => $dark,
                'light'     => $light,
            ];
            update_option('pdfev_archive_title',$archive_title);
            update_option('pdfev_archive_slug',$archive_slug);
            update_option('pdfev_shortcode_page_url',$shortcode_page_url);
            update_option('pdfev_archive_template',$archive_template);
            update_option('pdfev_flipbook_status',$flipbook);
            update_option('pdfev_archive_read',$archive_read);
            update_option('pdfev_reading_counter',$reading_counter);
            update_option('pdfev_archive_download',$archive_download);
            update_option('pdfev_download_counter',$download_counter);
            update_option('pdfev_css_colors',$colors );                
        }
    }
}

new \PDFEV\INVB_General_Settings();
