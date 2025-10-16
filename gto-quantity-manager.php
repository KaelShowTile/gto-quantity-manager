<?php
/*
Plugin Name: GTO Quantity
Description: A Plugin for setting up product's quantity step and suffix.Designed for GTO/CHT 
Version: 1.0
Author: Kael
*/

defined('ABSPATH') or die('Direct access not allowed');

class GTO_Quantity_Manager {
    
    public function __construct() 
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() 
    {
        $this->create_database_table();
    }
    
    public function deactivate() 
    {
        // Cleanup if needed
    }
    
    public function init() 
    {
        if (class_exists('WooCommerce')) {
            $this->setup_hooks();
        }
    }
    
    private function setup_hooks() 
    {
        // Add product tab
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_product_tab_content'));
        
        // Save product data
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        
        // Load product data
        add_action('woocommerce_product_options_general_product_data', array($this, 'load_product_data'));
    }
    
    public function add_product_tab($tabs) 
    {
        $tabs['gto_quantity'] = array(
            'label'    => 'GTO Quantity',
            'target'   => 'gto_quantity_options',
            'class'    => array(),
            'priority' => 80
        );
        return $tabs;
    }
    
    public function add_product_tab_content() 
    {
        global $post;
        ?>
        <div id="gto_quantity_options" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_text_input(array(
                    'id'          => '_glint_qty_step',
                    'label'       => 'Quantity Step',
                    'description' => 'Set the increment step for quantity inputs',
                    'desc_tip'    => true,
                    'type'        => 'number',
                    'custom_attributes' => array(
                        'step' => 'any',
                        'min'  => '0.01'
                    )
                ));
                
                woocommerce_wp_select(array(
                    'id'          => '_glint_qty_suffix',
                    'label'       => 'Quantity Suffix',
                    'description' => 'Select the unit/suffix for this product',
                    'desc_tip'    => true,
                    'options'     => array(
                        ''      => 'None',
                        'm2'    => 'm2',
                        'sheet' => 'Sheet',
                        'ea'    => 'EA',
                        'lm'  => 'LM',
                        'set'   => 'Set',
                        'bag'  => 'Bag'
                    )
                ));
                ?>
            </div>
        </div>
        <?php
    }
    
    private function create_database_table() 
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'glint_product_qty';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            glint_qty_suffix varchar(255) DEFAULT NULL,
            glint_qty_step decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (meta_id),
            UNIQUE KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function save_product_data($post_id) 
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'glint_product_qty';
        
        $step = isset($_POST['_glint_qty_step']) 
        ? round((float)sanitize_text_field($_POST['_glint_qty_step']), 2) 
        : null;

        $suffix = isset($_POST['_glint_qty_suffix']) ? sanitize_text_field($_POST['_glint_qty_suffix']) : null;
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d", 
            $post_id
        ));
        
        if ($exists) {
            $wpdb->update(
                $table_name,
                array(
                    'glint_qty_step' => $step,
                    'glint_qty_suffix' => $suffix
                ),
                array('post_id' => $post_id),
                array('%f', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'glint_qty_step' => $step,
                    'glint_qty_suffix' => $suffix
                ),
                array('%d', '%f', '%s')
            );
        }
    }
    
    public function load_product_data() {
        global $post, $wpdb;
        $table_name = $wpdb->prefix . 'glint_product_qty';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT glint_qty_step, glint_qty_suffix FROM $table_name WHERE post_id = %d", 
            $post->ID
        ));
        
        if ($data) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#_glint_qty_step').val('<?php echo esc_js($data->glint_qty_step); ?>');
                    $('#_glint_qty_suffix').val('<?php echo esc_js($data->glint_qty_suffix); ?>');
                });
            </script>
            <?php
        }
    }
}

new GTO_Quantity_Manager();


//get qty step by product id
function get_product_qty_data($product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'glint_product_qty';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT glint_qty_step FROM $table_name WHERE post_id = %d", 
        $product_id
    ));

    return ($result && isset($result->glint_qty_step)) ? $result->glint_qty_step : 1;
}

//get qty suffix by product id
function get_product_qty_suffix($product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'glint_product_qty';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT glint_qty_suffix FROM $table_name WHERE post_id = %d", 
        $product_id
    ));
    
    return ($result && isset($result->glint_qty_suffix)) 
        ? sanitize_text_field($result->glint_qty_suffix)
        : ''; // Default empty string
}