<?php
if (!defined('ABSPATH')) exit;

class PUBG_WooCommerce {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_fields'), 10, 2);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_player_field'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_data'), 10, 3);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_cart'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_meta'), 10, 4);
        add_action('woocommerce_order_status_completed', array($this, 'process_order'), 20, 1);
        add_action('woocommerce_order_status_processing', array($this, 'process_order'), 20, 1);
        add_action('woocommerce_order_item_meta_start', array($this, 'display_order_item_meta'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_filter('woocommerce_order_item_display_meta_key', array($this, 'hide_technical_meta_keys'), 10, 3);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_manual_process_button'));
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column_data'));
    }
    
    public function add_product_fields() {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'pubg_recharge_categories';
        $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY uc_amount ASC");
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => 'is_pubg_recharge',
            'label' => 'PUBG Recharge Product',
            'description' => 'Enable PUBG recharge system for this product'
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => 'is_pubg_multi_variation',
            'label' => 'Multi-Variation PUBG Product',
            'description' => 'Enable for variable products with different UC amounts per variation'
        ));
        
        if (!empty($categories)) {
            $options = array('' => 'Select Category');
            foreach ($categories as $category) {
                $options[$category->id] = $category->name . ' (' . $category->uc_amount . ' UC)';
            }
            
            woocommerce_wp_select(array(
                'id' => 'pubg_category_id',
                'label' => 'Recharge Category',
                'options' => $options,
                'description' => 'Select category for simple products (hidden for multi-variation products)'
            ));
        }
        
        echo '</div>';
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            function togglePubgFields() {
                var isMultiVariation = $('#is_pubg_multi_variation').is(':checked');
                var categoryField = $('#pubg_category_id').closest('.form-field');
                
                if (isMultiVariation) {
                    categoryField.hide();
                } else {
                    categoryField.show();
                }
            }
            
            $('#is_pubg_multi_variation').change(togglePubgFields);
            togglePubgFields();
        });
        </script>
        <?php
    }
    
    public function save_product_fields($post_id) {
        $is_pubg_recharge = isset($_POST['is_pubg_recharge']) ? 'yes' : 'no';
        $is_pubg_multi_variation = isset($_POST['is_pubg_multi_variation']) ? 'yes' : 'no';
        
        update_post_meta($post_id, 'is_pubg_recharge', $is_pubg_recharge);
        update_post_meta($post_id, 'is_pubg_multi_variation', $is_pubg_multi_variation);
        
        if (isset($_POST['pubg_category_id']) && !empty($_POST['pubg_category_id'])) {
            update_post_meta($post_id, 'pubg_category_id', sanitize_text_field($_POST['pubg_category_id']));
        }
    }
    
    public function add_variation_fields($loop, $variation_data, $variation) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'pubg_recharge_categories';
        $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY uc_amount ASC");
        
        $is_pubg_enabled = get_post_meta($variation->ID, 'is_pubg_variation_enabled', true);
        $category_id = get_post_meta($variation->ID, 'pubg_variation_category_id', true);
        
        if (!empty($categories)) {
            echo '<div class="options_group">';
            
            woocommerce_wp_checkbox(array(
                'id' => 'is_pubg_variation_enabled[' . $loop . ']',
                'name' => 'is_pubg_variation_enabled[' . $loop . ']',
                'value' => $is_pubg_enabled,
                'label' => 'Enable PUBG for this variation',
                'wrapper_class' => 'form-row form-row-full'
            ));
            
            $options = array('' => 'Select Category');
            foreach ($categories as $category) {
                $options[$category->id] = $category->name . ' (' . $category->uc_amount . ' UC)';
            }
            
            woocommerce_wp_select(array(
                'id' => 'pubg_variation_category_id[' . $loop . ']',
                'name' => 'pubg_variation_category_id[' . $loop . ']',
                'value' => $category_id,
                'label' => 'PUBG Category',
                'options' => $options,
                'wrapper_class' => 'form-row form-row-full'
            ));
            
            echo '</div>';
        }
    }
    
    public function save_variation_fields($variation_id, $loop) {
        if (isset($_POST['is_pubg_variation_enabled'][$loop])) {
            update_post_meta($variation_id, 'is_pubg_variation_enabled', 'yes');
        } else {
            update_post_meta($variation_id, 'is_pubg_variation_enabled', 'no');
        }
        
        if (isset($_POST['pubg_variation_category_id'][$loop])) {
            update_post_meta($variation_id, 'pubg_variation_category_id', sanitize_text_field($_POST['pubg_variation_category_id'][$loop]));
        }
    }
    
  public function add_player_field() {
        global $product;
        
        $is_pubg = get_post_meta($product->get_id(), 'is_pubg_recharge', true);
        
        if ($is_pubg == 'yes') {
            $unique_id = 'player_id_' . $product->get_id();
            $label = get_option('pubg_player_id_label', 'Player ID');
            ?>
            <div class="pubg-player-field-container" id="pubg-container-<?php echo $product->get_id(); ?>">
                <div class="pubg-player-field">
                    <label for="<?php echo $unique_id; ?>"><?php echo esc_html($label); ?> <span style="color: red;">*</span></label>
                    <div class="pubg-input-wrapper">
                        <input type="text" name="player_id" id="<?php echo $unique_id; ?>" 
                               pattern="[0-9]{6,12}" required 
                               placeholder="Enter 6-12 digit numeric Player ID"
                               data-product-id="<?php echo $product->get_id(); ?>"
                               class="pubg-player-input">
                        <button type="button" class="pubg-verify-btn" data-product-id="<?php echo $product->get_id(); ?>">
                            VERIFY
                        </button>
                    </div>
                    <div id="player-result-<?php echo $product->get_id(); ?>" class="player-validation"></div>
                </div>
            </div>
            
            <style>
            .pubg-player-field-container {
                margin-bottom: 25px;
                width: 100%;
                clear: both;
                display: block;
            }
            .pubg-player-field label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }
            .pubg-input-wrapper {
                position: relative;
                display: block;
                width: 100%;
                max-width: 400px;
            }
            .pubg-player-input {
                width: 100%;
                padding: 12px 85px 12px 16px; /* مساحة للزرار من اليمين */
                border: 2px solid #ddd;
                border-radius: 6px;
                font-size: 16px;
                transition: all 0.3s ease;
                background: #fff;
                box-sizing: border-box;
            }
            .pubg-player-input:focus {
                border-color: #007cba;
                outline: none;
                box-shadow: 0 0 0 3px rgba(0,124,186,0.1);
            }
            .pubg-player-input.valid {
                border-color: #28a745;
                background-color: #f8fff9;
            }
            .pubg-player-input.invalid {
                border-color: #dc3545;
                background-color: #fff8f8;
            }
            .pubg-player-input.loading {
                border-color: #ffc107;
                background-color: #fffbf0;
            }
            .pubg-verify-btn {
                position: absolute;
                right: 4px;
                top: 50%;
                transform: translateY(-50%);
                padding: 8px 12px;
                background: #007cba;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                font-size: 12px;
                transition: background 0.3s ease;
                height: calc(100% - 8px);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            /* RTL Support for Arabic Sites */
            [dir="rtl"] .pubg-player-input {
                padding: 12px 16px 12px 85px; /* مساحة للزرار من الشمال */
                text-align: right;
            }
            [dir="rtl"] .pubg-verify-btn {
                right: auto;
                left: 4px; /* الزرار في الشمال للعربي */
            }
            .pubg-verify-btn:hover {
                background: #005a87;
            }
            .pubg-verify-btn:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }
            .player-validation {
                margin-top: 10px;
                padding: 10px 15px;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                display: none;
                animation: fadeIn 0.3s ease;
                max-width: 400px;
            }
            .player-validation.success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
                display: block;
            }
            .player-validation.error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                display: block;
            }
            .player-validation.loading {
                background-color: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
                display: block;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            /* Responsive Design */
            @media (max-width: 768px) {
                .pubg-input-wrapper {
                    max-width: 100%;
                }
                .pubg-player-input {
                    padding-right: 70px; /* مساحة أقل للزرار في الموبايل */
                }
                .pubg-verify-btn {
                    padding: 6px 8px;
                    font-size: 11px;
                }
            }
            </style>
            <script>
(function($) {
    $(document).ready(function() {
        const productId = <?php echo $product->get_id(); ?>;
        const $input = $('#<?php echo $unique_id; ?>');
        const $verifyBtn = $('.pubg-verify-btn[data-product-id="' + productId + '"]');
        const $result = $('#player-result-' + productId);
        let validationTimeout;
        let isValidating = false;
        
        // تأكد من وجود متغير AJAX
        if (typeof pubg_ajax === 'undefined') {
            window.pubg_ajax = {
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('pubg-frontend-nonce'); ?>'
            };
        }
        
        function validatePlayerID(playerId, showLoading = true) {
            if (isValidating) return; // منع التكرار
            
            if (!/^[0-9]{6,12}$/.test(playerId)) {
                $input.removeClass('valid loading').addClass('invalid');
                $result.removeClass('success loading').addClass('error').text('Player ID must be 6-12 digits').show();
                return;
            }
            
            isValidating = true;
            
            if (showLoading) {
                $input.removeClass('valid invalid').addClass('loading');
                $result.removeClass('success error').addClass('loading').text('Validating Player ID...').show();
                $verifyBtn.prop('disabled', true).text('Checking...');
            }
            
            $.ajax({
                url: pubg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pubg_validate_player',
                    player_id: playerId,
                    nonce: pubg_ajax.nonce
                },
                success: function(response) {
                    $input.removeClass('loading');
                    $result.removeClass('loading');
                    $verifyBtn.prop('disabled', false).text('Verify');
                    
                    if (response.success) {
                        $input.addClass('valid');
                        var playerName = 'Valid Player';
                        if (response.data && response.data.player_name) {
                            playerName = response.data.player_name;
                        }
                        $result.addClass('success')
                              .html('✓ Valid Player: <strong>' + playerName + '</strong>')
                              .show();
                    } else {
                        $input.addClass('invalid');
                        var errorMsg = 'Player not found';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        $result.addClass('error')
                              .text('✗ ' + errorMsg)
                              .show();
                    }
                },
                error: function() {
                    $input.removeClass('loading').addClass('invalid');
                    $result.removeClass('loading').addClass('error')
                          .text('✗ Validation failed. Please try again.')
                          .show();
                    $verifyBtn.prop('disabled', false).text('Verify');
                },
                complete: function() {
                    isValidating = false; // السماح بالتحقق مرة أخرى
                }
            });
        }
        
        $input.on('input', function() {
            const playerId = $(this).val().trim();
            
            clearTimeout(validationTimeout);
            $input.removeClass('valid invalid loading');
            $result.removeClass('success error loading').hide();
            
            if (playerId.length >= 6 && /^[0-9]+$/.test(playerId)) {
                $verifyBtn.prop('disabled', false);
            } else {
                $verifyBtn.prop('disabled', true);
            }
            
            // إلغاء الـ automatic validation
            // if (playerId.length === 0) return;
        });
        
        $verifyBtn.on('click', function() {
            const playerId = $input.val().trim();
            if (playerId.length > 0 && !isValidating) {
                validatePlayerID(playerId, true);
            }
        });
        
        $('form.cart').on('submit', function(e) {
            const playerId = $input.val().trim();
            if (!playerId || !$input.hasClass('valid')) {
                e.preventDefault();
                alert('Please enter and verify a valid Player ID before adding to cart.');
                $input.focus();
                return false;
            }
        });
    });
})(jQuery);
</script>
            <?php
        }
    }
    
    public function add_cart_data($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['player_id'])) {
            $player_id = sanitize_text_field($_POST['player_id']);
            if (preg_match('/^[0-9]{6,12}$/', $player_id)) {
                $cart_item_data['player_id'] = $player_id;
                $cart_item_data['_pubg_unique_key'] = md5($product_id . $variation_id . $player_id . time() . wp_rand());
                
                pubg_debug_log("Adding to cart", array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'player_id' => $player_id
                ));
            }
        }
        return $cart_item_data;
    }
    
public function validate_cart($passed, $product_id, $quantity) {
    $is_pubg = get_post_meta($product_id, 'is_pubg_recharge', true);
    
    if ($is_pubg == 'yes') {
        if (empty($_POST['player_id'])) {
            wc_add_notice('Please enter your Player ID', 'error');
            return false;
        }
        
        $player_id = sanitize_text_field($_POST['player_id']);
        if (!preg_match('/^[0-9]{6,12}$/', $player_id)) {
            wc_add_notice('Invalid Player ID format. Must be 6-12 digits.', 'error');
            return false;
        }
        
        // فحص حالة الـ Fallback mode
        $fallback_active = get_option('pubg_fallback_mode_active', false);
        $api_status = get_option('pubg_api_last_status', 'unknown');
        
        // لو الـ Fallback نشط أو الـ API مش شغال - نخلي العميل يكمل
        if ($fallback_active || $api_status !== 'healthy') {
            return $passed; // يكمل عادي
        }
        
        // لو الـ API شغال - نفحص زي العادة
        $result = pubg_get_player_info($player_id);
        if (!$result['success'] || !isset($result['data']['status']) || $result['data']['status'] !== 'success') {
            // لو فشل، نفحص لو السبب مشاكل اتصال
            $error_keywords = array('connection', 'timeout', 'expired', 'unavailable', 'failed');
            $is_connection_error = false;
            
            foreach ($error_keywords as $keyword) {
                if (stripos($result['message'], $keyword) !== false) {
                    $is_connection_error = true;
                    break;
                }
            }
            
            if ($is_connection_error) {
                // مشكلة اتصال - نخلي العميل يكمل
                update_option('pubg_fallback_mode_active', true);
                return $passed;
            } else {
                // خطأ في الـ Player ID نفسه
                wc_add_notice('Player ID not found or invalid. Please verify your Player ID.', 'error');
                return false;
            }
        }
    }
    
    return $passed;
}
    
    public function add_order_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['player_id'])) {
            $player_id = $values['player_id'];
            
            $item->add_meta_data('Player ID', $player_id, true);
            $item->add_meta_data('_pubg_player_id', $player_id, false);
            $item->add_meta_data('_pubg_unique_key', $values['_pubg_unique_key'] ?? '', false);
            $item->add_meta_data('_pubg_timestamp', current_time('timestamp'), false);
            
            pubg_debug_log("Order meta added", array(
                'order_id' => $order->get_id(),
                'player_id' => $player_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id()
            ));
        }
    }
    
    public function process_order($order_id) {
        pubg_debug_log("Order status changed, checking for PUBG products", array('order_id' => $order_id));
        
        $order = wc_get_order($order_id);
        if (!$order) {
            pubg_debug_log("Order not found", array('order_id' => $order_id));
            return;
        }
        
        $processed = get_post_meta($order_id, '_pubg_processed', true);
        if ($processed === 'yes') {
            pubg_debug_log("Order already processed", array('order_id' => $order_id));
            return;
        }
        
        $has_pubg_items = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $is_pubg = get_post_meta($product_id, 'is_pubg_recharge', true);
            
            if ($is_pubg == 'yes') {
                $has_pubg_items = true;
                
                $player_id = $item->get_meta('_pubg_player_id');
                if (empty($player_id)) {
                    $player_id = $item->get_meta('Player ID');
                }
                
                if ($player_id) {
                    $this->allocate_code($order_id, $product_id, $variation_id, $player_id, $item_id);
                } else {
                    pubg_debug_log("No Player ID found for PUBG product", array(
                        'order_id' => $order_id,
                        'product_id' => $product_id,
                        'item_id' => $item_id
                    ));
                }
            }
        }
        
    if ($has_pubg_items) {
    global $wpdb;
    $log_table = $wpdb->prefix . 'pubg_recharge_logs';
    
    $failed_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $log_table WHERE order_id = %d AND status = 'failed'",
        $order_id
    ));
    
    if ($failed_count == 0) {
        update_post_meta($order_id, '_pubg_processed', 'yes');
        pubg_debug_log("PUBG order processing completed successfully", array('order_id' => $order_id));
    } else {
        pubg_debug_log("PUBG order has failed items, keeping for retry", array(
            'order_id' => $order_id,
            'failed_count' => $failed_count
        ));
    }
}
    }
    
    private function allocate_code($order_id, $product_id, $variation_id, $player_id, $item_id) {
        global $wpdb;
        
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $category_id = $this->get_category_id($product_id, $variation_id);
        if (!$category_id) {
            pubg_debug_log("No category found", array('product_id' => $product_id, 'variation_id' => $variation_id));
            return;
        }
        
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $log_table WHERE order_id = %d AND player_id = %s AND status != 'failed'",
            $order_id, $player_id
        ));
        
        if ($existing_log) {
            pubg_debug_log("Already processed", array('order_id' => $order_id, 'player_id' => $player_id));
            return;
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $wpdb->query("LOCK TABLES $codes_table WRITE");
            
            $code = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $codes_table WHERE category_id = %d AND status = 'available' ORDER BY id ASC LIMIT 1",
                $category_id
            ));
            
            if (!$code) {
                $wpdb->query("UNLOCK TABLES");
                $wpdb->query('ROLLBACK');
                
                $this->log_operation(0, $order_id, $player_id, 'failed', 'No codes available for this category');
                return;
            }
            
            $updated = $wpdb->update($codes_table, array(
                'status' => 'used',
                'order_id' => $order_id,
                'player_id' => $player_id,
                'date_used' => current_time('mysql')
            ), array('id' => $code->id));
            
            $wpdb->query("UNLOCK TABLES");
            
            if ($updated) {
                $wpdb->query('COMMIT');
                
                $this->log_operation($code->id, $order_id, $player_id, 'pending', 'Code allocated, processing...');
                
                if (get_option('pubg_enable_auto_recharge', 1)) {
                    $this->send_to_api($code->id, $code->code, $player_id, $order_id);
                }
            } else {
                $wpdb->query('ROLLBACK');
                pubg_debug_log("Failed to update code status", array('code_id' => $code->id));
            }
            
        } catch (Exception $e) {
            $wpdb->query("UNLOCK TABLES");
            $wpdb->query('ROLLBACK');
            pubg_debug_log("Transaction failed", array('error' => $e->getMessage()));
        }
    }
    
    private function get_category_id($product_id, $variation_id) {
        $is_multi_variation = get_post_meta($product_id, 'is_pubg_multi_variation', true);
        
        if ($is_multi_variation == 'yes' && $variation_id) {
            $is_variation_enabled = get_post_meta($variation_id, 'is_pubg_variation_enabled', true);
            if ($is_variation_enabled == 'yes') {
                return get_post_meta($variation_id, 'pubg_variation_category_id', true);
            }
        } else {
            return get_post_meta($product_id, 'pubg_category_id', true);
        }
        
        return false;
    }
    
    private function send_to_api($code_id, $code, $player_id, $order_id) {
        $result = pubg_activate_uc_code($player_id, $code);
        
        if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
            $this->update_operation_status($code_id, $order_id, 'success', 'UC activated successfully');
            
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('completed', 'PUBG UC activated successfully for Player ID: ' . $player_id);
                $this->add_success_message_to_order($order, $player_id);
            }
            
        } else {
            $error = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
            $this->update_operation_status($code_id, $order_id, 'failed', 'Activation failed: ' . $error);
            $this->mark_code_as_failed($code_id);
            
            $order = wc_get_order($order_id);
            if ($order) {
                $this->add_processing_message_to_order($order, $player_id);
            }
        }
    }
    
    private function log_operation($code_id, $order_id, $player_id, $status, $message) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $wpdb->insert($log_table, array(
            'code_id' => $code_id,
            'order_id' => $order_id,
            'player_id' => $player_id,
            'status' => $status,
            'message' => $message,
            'date_created' => current_time('mysql')
        ));
    }
    
    private function update_operation_status($code_id, $order_id, $status, $message) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $wpdb->update($log_table, array(
            'status' => $status,
            'message' => $message
        ), array(
            'code_id' => $code_id,
            'order_id' => $order_id
        ));
    }
    
    private function mark_code_as_failed($code_id) {
        global $wpdb;
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        
        $wpdb->update($codes_table, array(
            'status' => 'failed'
        ), array('id' => $code_id));
    }
    
    private function add_success_message_to_order($order, $player_id) {
        $message = get_option('pubg_success_message', 'Your PUBG UC has been successfully recharged!');
        $order->add_order_note(sprintf($message . ' (Player ID: %s)', $player_id));
    }
    
    private function add_processing_message_to_order($order, $player_id) {
        $message = get_option('pubg_processing_message', 'Your order is being processed. UC will be delivered shortly due to high server load.');
        $order->add_order_note(sprintf($message . ' (Player ID: %s)', $player_id));
    }
    
    public function display_order_item_meta($item_id, $item, $order) {
        $player_id = $item->get_meta('Player ID');
        if (!$player_id) return;
        
        echo '<div style="margin: 8px 0; padding: 8px; background: #f8f9fa; border-left: 3px solid #007cba; border-radius: 4px;">
           <strong style="color: #007cba;">Player ID:</strong> 
           <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace;">' . esc_html($player_id) . '</code>
       </div>';
       
       $this->display_recharge_status($order->get_id(), $player_id);
   }
   
   private function display_recharge_status($order_id, $player_id) {
       global $wpdb;
       $log_table = $wpdb->prefix . 'pubg_recharge_logs';
       
       $log = $wpdb->get_row($wpdb->prepare(
           "SELECT l.* FROM $log_table l 
            WHERE l.order_id = %d AND l.player_id = %s 
            ORDER BY l.id DESC LIMIT 1",
           $order_id, $player_id
       ));
       
       if (!$log) return;
       
       $status_colors = array(
           'success' => array('color' => '#28a745', 'bg' => '#d4edda', 'icon' => '✓'),
           'failed' => array('color' => '#dc3545', 'bg' => '#f8d7da', 'icon' => '✗'),
           'pending' => array('color' => '#ffc107', 'bg' => '#fff3cd', 'icon' => '⏳')
       );
       
       $style = $status_colors[$log->status] ?? $status_colors['pending'];
       
       if ($log->status === 'success') {
           $message = get_option('pubg_success_message', 'Your PUBG UC has been successfully recharged!');
           echo '<div style="margin: 8px 0; padding: 8px; background: ' . $style['bg'] . '; border-left: 3px solid ' . $style['color'] . '; border-radius: 4px;">
               <strong style="color: ' . $style['color'] . ';">' . $style['icon'] . ' ' . esc_html($message) . '</strong>
           </div>';
       } elseif ($log->status === 'failed' || $log->status === 'pending') {
           $message = get_option('pubg_processing_message', 'Your order is being processed. UC will be delivered shortly due to high server load.');
           echo '<div style="margin: 8px 0; padding: 8px; background: ' . $status_colors['pending']['bg'] . '; border-left: 3px solid ' . $status_colors['pending']['color'] . '; border-radius: 4px;">
               <strong style="color: ' . $status_colors['pending']['color'] . ';">' . $status_colors['pending']['icon'] . ' ' . esc_html($message) . '</strong>
           </div>';
       }
   }
   
   public function display_cart_item_data($cart_data, $cart_item) {
       if (isset($cart_item['player_id'])) {
           $cart_data[] = array(
               'name' => get_option('pubg_player_id_label', 'Player ID'),
               'value' => '<code style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-family: monospace;">' . esc_html($cart_item['player_id']) . '</code>'
           );
       }
       return $cart_data;
   }
   
   public function hide_technical_meta_keys($display_key, $meta, $item) {
       $hidden_keys = array(
           '_pubg_player_id',
           '_pubg_unique_key', 
           '_pubg_timestamp',
           'pubg_player_id',
           'pubg_unique_key'
       );
       
       if (in_array($meta->key, $hidden_keys)) {
           return '';
       }
       
       return $display_key;
   }
   
   public function add_manual_process_button($order) {
       $order_id = $order->get_id();
       
       $has_pubg = false;
       foreach ($order->get_items() as $item) {
           $product_id = $item->get_product_id();
           if (get_post_meta($product_id, 'is_pubg_recharge', true) == 'yes') {
               $has_pubg = true;
               break;
           }
       }
       
       if (!$has_pubg) return;
       
       global $wpdb;
       $log_table = $wpdb->prefix . 'pubg_recharge_logs';
       $logs = $wpdb->get_results($wpdb->prepare(
           "SELECT * FROM $log_table WHERE order_id = %d ORDER BY id DESC",
           $order_id
       ));
       
       ?>
       <div class="postbox">
           <h3 class="hndle"><span>PUBG UC Recharge System</span></h3>
           <div class="inside">
               <?php if (empty($logs)): ?>
                   <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                       <p style="margin: 0; color: #856404;"><strong>⚠ This order has not been processed by PUBG system yet</strong></p>
                   </div>
                   <button type="button" class="button button-primary" id="pubg-manual-process" data-order-id="<?php echo $order_id; ?>">
                       Process Order Manually
                   </button>
               <?php else: ?>
                   <h4 style="margin-top: 0;">PUBG Processing Log:</h4>
                   <table class="widefat striped">
                       <thead>
                           <tr>
                               <th>Time</th>
                               <th>Player ID</th>
                               <th>Status</th>
                               <th>Message</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($logs as $log): ?>
                           <tr>
                               <td><?php echo date('Y-m-d H:i:s', strtotime($log->date_created)); ?></td>
                               <td><code><?php echo esc_html($log->player_id); ?></code></td>
                               <td>
                                   <span class="button button-small <?php echo $log->status == 'success' ? 'button-primary' : ''; ?>">
                                       <?php echo ucfirst($log->status); ?>
                                   </span>
                               </td>
                               <td><?php echo esc_html($log->message); ?></td>
                           </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
                   
                   <p style="margin-top: 15px;">
                       <button type="button" class="button button-secondary" id="pubg-manual-process" data-order-id="<?php echo $order_id; ?>">
                           Reprocess Order
                       </button>
                   </p>
               <?php endif; ?>
               
               <div id="pubg-process-result" style="margin-top: 15px;"></div>
           </div>
       </div>
       
       <script>
       jQuery(document).ready(function($) {
           $('#pubg-manual-process').on('click', function() {
               var orderId = $(this).data('order-id');
               var button = $(this);
               var resultDiv = $('#pubg-process-result');
               
               button.prop('disabled', true).text('Processing...');
               resultDiv.html('<div style="background: #fff3cd; padding: 12px; border-radius: 4px; border-left: 3px solid #ffc107;"><strong>Processing order...</strong><br>This may take a few seconds.</div>');
               
               $.ajax({
                   url: ajaxurl,
                   type: 'POST',
                   data: {
                       action: 'pubg_manual_process',
                       order_id: orderId,
                       nonce: '<?php echo wp_create_nonce('pubg-admin-nonce'); ?>'
                   },
                   success: function(response) {
                       if (response.success) {
                           resultDiv.html('<div style="background: #d4edda; padding: 12px; border-radius: 4px; border-left: 3px solid #28a745; color: #155724;"><strong>✓ ' + response.data.message + '</strong><br>Page will refresh in 2 seconds...</div>');
                           setTimeout(function() {
                               location.reload();
                           }, 2000);
                       } else {
                           resultDiv.html('<div style="background: #f8d7da; padding: 12px; border-radius: 4px; border-left: 3px solid #dc3545; color: #721c24;"><strong>✗ ' + response.data + '</strong></div>');
                       }
                   },
                   error: function() {
                       resultDiv.html('<div style="background: #f8d7da; padding: 12px; border-radius: 4px; border-left: 3px solid #dc3545; color: #721c24;"><strong>✗ Connection error</strong><br>Please try again.</div>');
                   },
                   complete: function() {
                       button.prop('disabled', false).text('Process Order Manually');
                   }
               });
           });
       });
       </script>
       <?php
   }
   
   public function add_order_columns($columns) {
       $new_columns = array();
       foreach ($columns as $key => $column) {
           $new_columns[$key] = $column;
           if ($key === 'order_status') {
               $new_columns['pubg_status'] = 'PUBG Status';
           }
       }
       return $new_columns;
   }
   
   public function display_order_column_data($column) {
       global $post, $wpdb;
       
       if ($column === 'pubg_status') {
           $order = wc_get_order($post->ID);
           $has_pubg = false;
           
           foreach ($order->get_items() as $item) {
               $product_id = $item->get_product_id();
               $is_pubg = get_post_meta($product_id, 'is_pubg_recharge', true);
               
               if ($is_pubg == 'yes') {
                   $has_pubg = true;
                   break;
               }
           }
           
           if ($has_pubg) {
               $log_table = $wpdb->prefix . 'pubg_recharge_logs';
               $status = $wpdb->get_var($wpdb->prepare(
                   "SELECT status FROM $log_table WHERE order_id = %d ORDER BY id DESC LIMIT 1",
                   $post->ID
               ));
               
               if ($status) {
                   $color = $status === 'success' ? '#28a745' : ($status === 'failed' ? '#dc3545' : '#ffc107');
                   echo '<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($status) . '</span>';
               } else {
                   echo '<span style="color: #6c757d;">Not Processed</span>';
               }
           } else {
               echo '-';
           }
       }
   }
}

new PUBG_WooCommerce();
?>
