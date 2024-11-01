<?php

/*
  Plugin Name: StorePep WooCommerce App Integration
  Plugin URI: https://www.storepep.com
  Description: Sends push notification to StorePep WooCommerce App whenever changes are made on your WooCommerce Store.
  Version: 2.1.3
  Author: StorePep
  Author URI: https://www.storepep.com
  License: GPL 3.0
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    class StorePep_App_Integration_Settings {
        function __construct() {
            add_action('rest_api_init', array($this, 'init_rest_api'));
            add_action('rest_api_init', array($this, 'init_orders_status_count'));
            add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
            add_action('woocommerce_settings_tabs_storepep_app', array($this, 'get_storepep_app_settings'));
            add_action('woocommerce_update_options_storepep_app', array($this, 'update_storepep_app_settings'));
        }

        function init_rest_api() {
            register_rest_route('storepep/v1', '/check/', array(
                'callback' => array($this, 'storepep_check'),
            ));
        }

        function init_orders_status_count(){
            require_once('includes/storepep-orders-status-count.php');
            $storepep_orders_status_count_obj = new Storepep_Orders_Status_Count();
            $storepep_orders_status_count_obj -> register_routes();
        }
        
        function storepep_check(){
            return array('storepep' => 'available');
        }


        function add_settings_tab($settings_tabs) {
            $settings_tabs['storepep_app'] = __('StorePep App Settings', 'storepep');
            return $settings_tabs;
        }

        function get_storepep_app_settings() {
            woocommerce_admin_fields($this->get_storepep_settings_data());
        }

        function update_storepep_app_settings() {
            woocommerce_update_options($this->get_storepep_settings_data());
        }

        function get_storepep_settings_data() {
            $settings = array(
                'general_section_title' => array(
                    'name' => __('StorePep App Integration Settings', 'storepep'),
                    'type' => 'title'
                ),
                'enable' => array(
                    'title' => __('Enable Notification', 'storepep'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => __('Enable this option to get notification to your integrated StorePep App.', 'storepep'),
                    'desc' => "Enable",
                    'id' => 'storepep_enable'
                ),
                'general_section_end' => array(
                    'type' => 'sectionend',
                ),
                'order_notification_section_title' => array(
                    'name' => __('Order Notification Settings', 'storepep'),
                    'type' => 'title'
                ),
                'new_order' => array(
                    'title' => __('New Order Notification', 'storepep'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => __('Enable this option to get new order notification to your integrated StorePep App.', 'storepep'),
                    'desc' => "Enable",
                    'id' => 'storepep_new_order'
                ),
                'order_status' => array(
                    'title' => __('Order Status Change', 'storepep'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select',
                    'desc_tip' => __('Select status(es) to which a change should trigger a notification.', 'storepep'),
                    'options' => array(
                        'pending' => 'Pending payment',
                        'processing' => 'Processing',
                        'on-hold' => 'On hold',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'failed' => 'Failed',
                    ),
                    'id' => 'storepep_order_status',
                ),
                'order_notification_section_end' => array(
                    'type' => 'sectionend',
                ),
                'product_notification_section_title' => array(
                    'name' => __('Product Notification Settings', 'storepep'),
                    'type' => 'title'
                ),
                'low_stock' => array(
                    'title' => __('Low Stock Alert', 'storepep'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => __('Enable this option to get low stock alert notification to your integrated StorePep App.', 'storepep'),
                    'desc' => "Enable",
                    'id' => 'storepep_low_stock'
                ),
                'no_stock' => array(
                    'title' => __('Out of Stock Alert', 'storepep'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => __('Enable this option to get out of stock alert notification to your integrated StorePep App.', 'storepep'),
                    'desc' => "Enable",
                    'id' => 'storepep_no_stock'
                ),
                'product_notification_section_end' => array(
                    'type' => 'sectionend',
                ),
                'customer_notification_section_title' => array(
                    'name' => __('Customer Notification Settings', 'storepep'),
                    'type' => 'title'
                ),
                'new_customer' => array(
                    'title' => __('New Customer Notification', 'storepep'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => __('Enable this option to get new customer notification to your integrated StorePep App.', 'storepep'),
                    'desc' => "Enable",
                    'id' => 'storepep_new_customer'
                ),
                'customer_notification_section_end' => array(
                    'type' => 'sectionend',
                ),
            );
            return apply_filters('wc_settings_tab_storepep_settings', $settings);
        }

    }

    new StorePep_App_Integration_Settings();

    class StorePep_App_Integration_Processor {

        protected $enable, $new_order,$no_stock, $order_status, $low_stock, $new_customer;

        function __construct() {
            $this->enable = (get_option("storepep_enable") ? ((get_option("storepep_enable") == 'yes') ? true : false) : true);
            $this->new_order = (get_option("storepep_new_order") ? ((get_option("storepep_new_order") == 'yes') ? true : false) : true);
            $this->low_stock = (get_option("storepep_low_stock") ? ((get_option("storepep_low_stock") == 'yes') ? true : false) : true);
            $this->no_stock = (get_option("storepep_no_stock") ? ((get_option("storepep_no_stock") == 'yes') ? true : false) : true);
            $this->new_customer = (get_option("storepep_new_customer") ? ((get_option("storepep_new_customer") == 'yes') ? true : false) : true);
            $this->order_status = (get_option("storepep_order_status") ? get_option("storepep_order_status") : array());
            add_action('woocommerce_checkout_order_processed', array($this, 'new_order_processed'));
            add_action('woocommerce_created_customer', array($this, 'new_customer_created'));
            add_action('woocommerce_low_stock', array($this, 'low_stock_alert'));
            add_action('woocommerce_no_stock', array($this, 'no_stock_alert'));
            add_action('woocommerce_order_status_changed',array($this, 'order_status_changed'),20,3);
        }

        function new_order_processed($order_id) 
        {
            if ($this->enable && $this->new_order) {
                $order = new WC_Order($order_id);
                $order_total = (float) $order->get_total();
                $site_currency = get_woocommerce_currency();
                $payment = ((wc()->version < '3.0.0')? $order->payment_method_title:$order->get_payment_method_title());
                $shop_url = site_url();
                wp_remote_get("http://54.213.176.37/storepep-send-notification-updated.php?type=new_order&payment=".$payment."&currency=" . $site_currency . "&total=" . $order_total . "&id=" . $order_id . "&shop_url=" . $shop_url);
            }
        }

        function new_customer_created($customer_id) 
        {
            if ($this->enable && $this->new_customer) {
                $customer = new WP_User($customer_id);
                $name = $customer->display_name;
                $username = $customer->user_login;
                $shop_url = site_url();
                wp_remote_get("http://54.213.176.37/storepep-send-notification-updated.php?type=new_customer&username=" . $username . "&name=" . $name . "&id=" . $customer_id . "&shop_url=" . $shop_url);
            }
        }

        function low_stock_alert($product)
        {
            if ($this->enable && $this->low_stock) {
                $name = ((wc()->version < '3.0.0')?$product->name:$product->get_name());
                $id = ((wc()->version < '3.0.0')?$product->id:$product->get_id());
                $quantity = ((wc()->version < '3.0.0')?$product->stock_quantity:$product->get_stock_quantity());
                $shop_url = site_url();
                wp_remote_get("http://54.213.176.37/storepep-send-notification-updated.php?type=low_stock&quantity=" . $quantity . "&name=" . $name . "&id=" . $id . "&shop_url=" . $shop_url);
            }
        }

        function no_stock_alert($product)
        {
            if ($this->enable && $this->no_stock) {
                $name = ((wc()->version < '3.0.0')?$product->name:$product->get_name());
                $id = ((wc()->version < '3.0.0')?$product->id:$product->get_id());
                $quantity = ((wc()->version < '3.0.0')?$product->stock_quantity:$product->get_stock_quantity());
                $shop_url = site_url();
                wp_remote_get("http://54.213.176.37/storepep-send-notification-updated.php?type=no_stock&quantity=" . $quantity . "&name=" . $name . "&id=" . $id . "&shop_url=" . $shop_url);
            }
        }

        function order_status_changed($id,$from,$to)
        {
            if ($this->enable && in_array($to, $this->order_status)) {
                $sfrom = wc_get_order_status_name($from);
                $sto = wc_get_order_status_name($to);
                $shop_url = site_url();
                wp_remote_get("http://54.213.176.37/storepep-send-notification-updated.php?type=order_status&from=" . $sfrom . "&to=" . $sto . "&id=" . $id . "&shop_url=" . $shop_url);
            }
        }
    }
    new StorePep_App_Integration_Processor();
    add_filter("woocommerce_api_query_args", "storepep_use_post_date", 99, 2);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'storepep_plugin_action_links');
    function storepep_use_post_date($args,$request_args)
    {
        if(isset($request_args['use_post_date']) && $request_args['use_post_date']=='yes')
        {
            $args['date_query'][0]['column']='post_date';
            $args['date_query'][1]['column']='post_date';
        }
        return $args;
    }
    function storepep_plugin_action_links($links)
    {
        $setting_link = admin_url('admin.php?page=wc-settings&tab=storepep_app');
        $plugin_links = array(
            '<a href="' . $setting_link . '">' . __('Settings', 'storepep') . '</a>',
            '<a href="https://storepep.com/storepep-woocommerce-app-documentation/" target="_blank">' . __('Documentation', 'storepep') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }
} else {
    // TODO: As discussed with Mirdas, this else condition should be handle properly.
}