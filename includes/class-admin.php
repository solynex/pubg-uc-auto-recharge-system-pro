<?php

class PUBG_Admin {
    
    public function __construct() {
        // Constructor intentionally empty
    }
    
    public function dashboard_page() {
        $stats = pubg_get_system_stats();
        $api_configured = !empty(get_option('pubg_api_key')) && !empty(get_option('pubg_api_url'));
        $low_stock_threshold = get_option('pubg_low_stock_threshold', 10);
        
        ?>
        <div class="wrap">
            <h1>PUBG Recharge System Dashboard</h1>
            
            <?php if (!$api_configured): ?>
            <div class="notice notice-warning">
                <p><strong>Warning:</strong> API not configured yet. Please go to <a href="?page=pubg-settings">Settings page</a> to configure the API.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['available_codes'] <= $low_stock_threshold): ?>
            <div class="notice notice-error">
                <p><strong>Low Stock Alert:</strong> You have only <?php echo $stats['available_codes']; ?> codes available. Please add more codes.</p>
            </div>
            <?php endif; ?>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="3" style="text-align: center; background: #f1f1f1; padding: 10px;">Code Statistics</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="pubg-stats-card">
                            <h3>Total Codes</h3>
                            <div class="pubg-stats-number pubg-default"><?php echo $stats['total_codes']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h3>Available Codes</h3>
                            <div class="pubg-stats-number pubg-success"><?php echo $stats['available_codes']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h3>Used Codes</h3>
                            <div class="pubg-stats-number pubg-info"><?php echo $stats['used_codes']; ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <br>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="3" style="text-align: center; background: #f1f1f1; padding: 10px;">Operation Statistics</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="pubg-stats-card">
                            <h3>Successful Operations</h3>
                            <div class="pubg-stats-number pubg-success"><?php echo $stats['success_operations']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h3>Failed Operations</h3>
                            <div class="pubg-stats-number pubg-failed"><?php echo $stats['failed_operations']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h3>Pending Operations</h3>
                            <div class="pubg-stats-number pubg-pending"><?php echo $stats['pending_operations']; ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <br>
            
            <div class="postbox">
                <h3 class="hndle">System Status</h3>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th>API Status</th>
                            <td>
                                <?php if ($api_configured): ?>
                                    <span class="pubg-success" style="font-weight: bold;">✓ Configured</span>
                                <?php else: ?>
                                    <span class="pubg-failed" style="font-weight: bold;">✗ Not Configured</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Auto Recharge</th>
                            <td>
                                <?php if (get_option('pubg_enable_auto_recharge', 1)): ?>
                                    <span class="pubg-success" style="font-weight: bold;">✓ Enabled</span>
                                <?php else: ?>
                                    <span class="pubg-pending" style="font-weight: bold;">⚠ Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Debug Mode</th>
                            <td>
                                <?php if (get_option('pubg_debug_mode', 0)): ?>
                                    <span class="pubg-info" style="font-weight: bold;">On</span>
                                <?php else: ?>
                                    <span style="color: #6c757d;">Off</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function categories_page() {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'pubg_recharge_categories';
        
        if (isset($_POST['action']) && $_POST['action'] == 'add') {
            // ✅ تم حذف Bundle code من هنا
            $wpdb->insert($categories_table, array(
                'name' => sanitize_text_field($_POST['name']),
                'uc_amount' => intval($_POST['uc_amount']),
                'description' => sanitize_textarea_field($_POST['description'])
            ));
            echo '<div class="notice notice-success"><p>Category added successfully!</p></div>';
        }
        
        if (isset($_GET['delete'])) {
            $wpdb->delete($categories_table, array('id' => intval($_GET['delete'])));
            echo '<div class="notice notice-success"><p>Category deleted successfully!</p></div>';
        }
        
        $categories = pubg_get_categories();
        
        ?>
        <div class="wrap">
            <h1>Manage Categories</h1>
            
            <div class="postbox">
                <h3 class="hndle">Add New Category</h3>
                <div class="inside">
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Category Name</th>
                                <td><input type="text" name="name" required class="regular-text" placeholder="e.g., PUBG Mobile 60 UC"></td>
                            </tr>
                            <tr>
                                <th scope="row">UC Amount</th>
                                <td><input type="number" name="uc_amount" required class="regular-text" placeholder="e.g., 60"></td>
                            </tr>
                            <tr>
                                <th scope="row">Description</th>
                                <td><textarea name="description" class="large-text" placeholder="Optional description..."></textarea></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Add Category">
                        </p>
                    </form>
                </div>
            </div>
            
            <h2>Existing Categories</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>UC Amount</th>
                        <th>Total Codes</th>
                        <th>Available Codes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category) : 
                        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pubg_recharge_codes WHERE category_id = %d", $category->id));
                        $available = pubg_get_available_codes_count($category->id);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($category->name); ?></strong></td>
                        <td><span class="button button-small"><?php echo $category->uc_amount; ?> UC</span></td>
                        <td><?php echo $total; ?></td>
                        <td>
                            <span style="color: <?php echo $available > 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                <?php echo $available; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=pubg-categories&delete=<?php echo $category->id; ?>" 
                               class="button button-small" 
                               onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)) : ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            <em>No categories found. Add a new category to get started!</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function codes_page() {
        global $wpdb;
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        
        if (isset($_POST['action']) && $_POST['action'] == 'add_codes') {
            $category_id = intval($_POST['category_id']);
            $codes = explode("\n", trim($_POST['codes']));
            $added_count = 0;
            
            // ✅ تم إزالة Bundle processing code
            foreach ($codes as $code) {
                $code = trim($code);
                if (!empty($code)) {
                    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $codes_table WHERE code = %s", $code));
                    if (!$existing) {
                        $wpdb->insert($codes_table, array(
                            'category_id' => $category_id,
                            'code' => $code,
                            'status' => 'available'
                        ));
                        $added_count++;
                    }
                }
            }
            echo '<div class="notice notice-success"><p>' . $added_count . ' codes added successfully!</p></div>';
        }
        
        $categories = pubg_get_categories();
        $filter_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $where_conditions = array();
        if ($filter_category > 0) {
            $where_conditions[] = $wpdb->prepare("c.category_id = %d", $filter_category);
        }
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("c.status = %s", $filter_status);
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $codes = $wpdb->get_results("SELECT c.*, cat.name as category_name, cat.uc_amount 
                                    FROM $codes_table c 
                                    JOIN {$wpdb->prefix}pubg_recharge_categories cat ON c.category_id = cat.id 
                                    $where_clause 
                                    ORDER BY c.id DESC LIMIT 100");
        
        ?>
        <div class="wrap">
            <h1>Manage Codes</h1>
            
            <div class="postbox">
                <h3 class="hndle">Add New Codes</h3>
                <div class="inside">
                    <form method="post">
                        <input type="hidden" name="action" value="add_codes">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Category</th>
                                <td>
                                    <select name="category_id" required class="regular-text">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category) : ?>
                                        <option value="<?php echo $category->id; ?>">
                                            <?php echo esc_html($category->name); ?> (<?php echo $category->uc_amount; ?> UC)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Codes</th>
                                <td>
                                    <textarea name="codes" required class="large-text" rows="10" 
                                             placeholder="One code per line&#10;ABCD-EFGH-IJKL&#10;MNOP-QRST-UVWX"></textarea>
                                    <p class="description">Enter each code on a separate line. Duplicate codes will be automatically ignored.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Add Codes">
                        </p>
                    </form>
                </div>
            </div>
            
            <h2>Filter Codes</h2>
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="pubg-codes">
                <select name="category_id" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo $category->id; ?>" <?php selected($filter_category, $category->id); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="available" <?php selected($filter_status, 'available'); ?>>Available</option>
                    <option value="used" <?php selected($filter_status, 'used'); ?>>Used</option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                </select>
                <input type="submit" class="button" value="Filter">
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Order ID</th>
                        <th>Player ID</th>
                        <th>Date Used</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code) : ?>
                    <tr id="code-row-<?php echo $code->id; ?>">
                        <td><?php echo $code->id; ?></td>
                        <td><code><?php echo pubg_truncate_code($code->code); ?></code></td>
                        <td>
                            <?php echo esc_html($code->category_name); ?> 
                            <span class="button button-small"><?php echo $code->uc_amount; ?> UC</span>
                        </td>
                        <td><?php echo pubg_format_status($code->status); ?></td>
                        <td><?php echo $code->order_id ? '#' . $code->order_id : '-'; ?></td>
                        <td><?php echo $code->player_id ? '<code>' . esc_html($code->player_id) . '</code>' : '-'; ?></td>
                        <td><?php echo $code->date_used ? date('Y-m-d H:i', strtotime($code->date_used)) : '-'; ?></td>
                        <td>
                            <?php if ($code->status === 'available') : ?>
                                <button type="button" class="button button-small button-link-delete delete-code-btn" 
                                        data-code-id="<?php echo $code->id; ?>" 
                                        data-code="<?php echo esc_attr(pubg_truncate_code($code->code)); ?>">
                                    Delete
                                </button>
                            <?php else : ?>
                                <span style="color: #999;">Cannot delete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($codes)) : ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            <em>No codes found. Add codes to get started!</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.delete-code-btn').on('click', function() {
                var codeId = $(this).data('code-id');
                var code = $(this).data('code');
                var row = $('#code-row-' + codeId);
                var button = $(this);
                
                if (confirm('Are you sure you want to delete code: ' + code + '?')) {
                    button.prop('disabled', true).text('Deleting...');
                    
                    $.ajax({
                        url: pubg_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'pubg_delete_code',
                            code_id: codeId,
                            nonce: pubg_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                row.fadeOut(function() {
                                    row.remove();
                                });
                            } else {
                                alert('Error: ' + response.data);
                                button.prop('disabled', false).text('Delete');
                            }
                        },
                        error: function() {
                            alert('Connection error. Please try again.');
                            button.prop('disabled', false).text('Delete');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    public function pending_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        
        if (isset($_POST['action']) && $_POST['action'] == 'reprocess') {
            $log_id = intval($_POST['log_id']);
            $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $log_table WHERE id = %d", $log_id));
            
            if ($log) {
                delete_post_meta($log->order_id, '_pubg_processed');
                $woo = new PUBG_WooCommerce();
                $woo->process_order($log->order_id);
                echo '<div class="notice notice-success"><p>Order reprocessing initiated!</p></div>';
            }
        }
        
        if (isset($_POST['action']) && $_POST['action'] == 'mark_complete') {
            $log_id = intval($_POST['log_id']);
            $wpdb->update($log_table, 
                array('status' => 'success', 'message' => 'Manually marked as complete'),
                array('id' => $log_id)
            );
            echo '<div class="notice notice-success"><p>Operation marked as complete!</p></div>';
        }
        
        $pending_ops = $wpdb->get_results("SELECT l.*, c.code, cat.name as category_name, cat.uc_amount 
                                          FROM $log_table l 
                                          LEFT JOIN $codes_table c ON l.code_id = c.id 
                                          LEFT JOIN {$wpdb->prefix}pubg_recharge_categories cat ON c.category_id = cat.id
                                          WHERE l.status IN ('pending', 'failed') 
                                          ORDER BY l.id DESC LIMIT 100");
        
        ?>
        <div class="wrap">
            <h1>Pending Operations</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Order ID</th>
                        <th>Player ID</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_ops as $op) : ?>
                    <tr>
                        <td><?php echo $op->id; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($op->date_created)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $op->order_id . '&action=edit'); ?>" target="_blank">
                                #<?php echo $op->order_id; ?>
                            </a>
                        </td>
                        <td><code><?php echo esc_html($op->player_id); ?></code></td>
                        <td>
                            <?php if ($op->code) : ?>
                                <code><?php echo esc_html($op->code); ?></code>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($op->category_name) : ?>
                                <?php echo esc_html($op->category_name); ?> 
                                <span class="button button-small"><?php echo $op->uc_amount; ?> UC</span>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo pubg_format_status($op->status); ?></td>
                        <td style="max-width: 200px;"><?php echo esc_html($op->message); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="reprocess">
                                <input type="hidden" name="log_id" value="<?php echo $op->id; ?>">
                                <input type="submit" class="button button-small" value="Reprocess">
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="mark_complete">
                                <input type="hidden" name="log_id" value="<?php echo $op->id; ?>">
                                <input type="submit" class="button button-small button-primary" value="Mark Complete">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_ops)) : ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            <em>No pending operations found.</em>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function logs_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_days = isset($_GET['days']) ? intval($_GET['days']) : 7;
        
        $where_conditions = array();
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("l.status = %s", $filter_status);
        }
        if ($filter_days > 0) {
            $where_conditions[] = $wpdb->prepare("l.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)", $filter_days);
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $logs = $wpdb->get_results("SELECT l.*, c.code, cat.name as category_name, cat.uc_amount 
                                   FROM $log_table l 
                                   LEFT JOIN $codes_table c ON l.code_id = c.id 
                                   LEFT JOIN {$wpdb->prefix}pubg_recharge_categories cat ON c.category_id = cat.id
                                   $where_clause 
                                   ORDER BY l.id DESC LIMIT 200");
        
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table l $where_clause"),
            'success' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table l $where_clause AND l.status = 'success'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table l $where_clause AND l.status = 'failed'"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $log_table l $where_clause AND l.status = 'pending'")
        );
        
        ?>
        <div class="wrap">
            <h1>System Logs (Read Only)</h1>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th colspan="4" style="text-align: center; background: #f1f1f1; padding: 10px;">Log Statistics</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="pubg-stats-card">
                            <h4>Total Operations</h4>
                            <div class="pubg-stats-number pubg-default" style="font-size: 24px;"><?php echo $stats['total']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h4>Successful</h4>
                            <div class="pubg-stats-number pubg-success" style="font-size: 24px;"><?php echo $stats['success']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h4>Failed</h4>
                            <div class="pubg-stats-number pubg-failed" style="font-size: 24px;"><?php echo $stats['failed']; ?></div>
                        </td>
                        <td class="pubg-stats-card">
                            <h4>Pending</h4>
                            <div class="pubg-stats-number pubg-pending" style="font-size: 24px;"><?php echo $stats['pending']; ?></div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <br>
            
            <h2>Filter Logs</h2>
            <form method="get" style="margin-bottom: 20px;">
                <input type="hidden" name="page" value="pubg-logs">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="success" <?php selected($filter_status, 'success'); ?>>Success</option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                </select>
                <select name="days" onchange="this.form.submit()">
                    <option value="1" <?php selected($filter_days, 1); ?>>Last Day</option>
                    <option value="7" <?php selected($filter_days, 7); ?>>Last Week</option>
                    <option value="30" <?php selected($filter_days, 30); ?>>Last Month</option>
                    <option value="0" <?php selected($filter_days, 0); ?>>All Records</option>
                </select>
                <input type="submit" class="button" value="Filter">
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Order ID</th>
                        <th>Player ID</th>
                        <th>Code Used</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo $log->id; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log->date_created)); ?></td>
                        <td>
                            <?php if ($log->order_id) : ?>
                                <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>" target="_blank">
                                    #<?php echo $log->order_id; ?>
                                </a>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($log->player_id); ?></code></td>
                        <td>
                            <?php if ($log->code) : ?>
                                <code style="font-weight: bold; background: #f1f1f1; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo esc_html($log->code); ?>
                                </code>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->category_name) : ?>
                                <?php echo esc_html($log->category_name); ?> 
                                <span class="button button-small"><?php echo $log->uc_amount; ?> UC</span>
                           <?php else : ?>
                               -
                           <?php endif; ?>
                       </td>
                       <td><?php echo pubg_format_status($log->status); ?></td>
                       <td style="max-width: 300px;">
                           <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                title="<?php echo esc_attr($log->message); ?>">
                               <?php echo esc_html($log->message); ?>
                           </div>
                       </td>
                   </tr>
                   <?php endforeach; ?>
                   <?php if (empty($logs)) : ?>
                   <tr>
                       <td colspan="8" style="text-align: center; padding: 20px;">
                           <em>No logs found.</em>
                       </td>
                   </tr>
                   <?php endif; ?>
               </tbody>
           </table>
       </div>
       <?php
   }
   
   public function import_export_page() {
       global $wpdb;
       
       if (isset($_POST['action']) && $_POST['action'] == 'export_codes') {
           $this->export_codes();
       }
       
       if (isset($_POST['action']) && $_POST['action'] == 'import_codes') {
           $this->import_codes();
       }
       
       if (isset($_POST['action']) && $_POST['action'] == 'export_settings') {
           $this->export_settings();
       }
       
       if (isset($_POST['action']) && $_POST['action'] == 'import_settings') {
           $this->import_settings();
       }
       
       ?>
       <div class="wrap">
           <h1>Import/Export</h1>
           
           <div class="postbox">
               <h3 class="hndle">Export Data</h3>
               <div class="inside">
                   <form method="post">
                       <input type="hidden" name="action" value="export_codes">
                       <p>Export all codes and categories to CSV file.</p>
                       <p class="submit">
                           <input type="submit" class="button button-primary" value="Export Codes">
                       </p>
                   </form>
                   
                   <form method="post">
                       <input type="hidden" name="action" value="export_settings">
                       <p>Export plugin settings to JSON file.</p>
                       <p class="submit">
                           <input type="submit" class="button button-secondary" value="Export Settings">
                       </p>
                   </form>
               </div>
           </div>
           
           <div class="postbox">
               <h3 class="hndle">Import Data</h3>
               <div class="inside">
                   <form method="post" enctype="multipart/form-data">
                       <input type="hidden" name="action" value="import_codes">
                       <table class="form-table">
                           <tr>
                               <th scope="row">Import Codes CSV</th>
                               <td>
                                   <input type="file" name="codes_file" accept=".csv" required>
                                   <p class="description">CSV format: category_id,code,status</p>
                               </td>
                           </tr>
                       </table>
                       <p class="submit">
                           <input type="submit" class="button button-primary" value="Import Codes">
                       </p>
                   </form>
                   
                   <form method="post" enctype="multipart/form-data">
                       <input type="hidden" name="action" value="import_settings">
                       <table class="form-table">
                           <tr>
                               <th scope="row">Import Settings JSON</th>
                               <td>
                                   <input type="file" name="settings_file" accept=".json" required>
                                   <p class="description">JSON file exported from this plugin</p>
                               </td>
                           </tr>
                       </table>
                       <p class="submit">
                           <input type="submit" class="button button-secondary" value="Import Settings">
                       </p>
                   </form>
               </div>
           </div>
       </div>
       <?php
   }
   
   public function settings_page() {
       $message = '';
       
       if (isset($_POST['save_settings'])) {
           if (!wp_verify_nonce($_POST['pubg_settings_nonce'], 'pubg_save_settings')) {
               wp_die('Security check failed');
           }
           
           $settings = array(
               'pubg_api_url' => esc_url_raw($_POST['api_url']),
               'pubg_api_key' => sanitize_text_field($_POST['api_key']),
               'pubg_enable_auto_recharge' => isset($_POST['enable_auto_recharge']) ? 1 : 0,
               'pubg_notification_email' => sanitize_email($_POST['notification_email']),
               'pubg_low_stock_threshold' => intval($_POST['low_stock_threshold']),
               'pubg_debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
               'pubg_player_id_label' => sanitize_text_field($_POST['player_id_label']),
               'pubg_success_message' => sanitize_textarea_field($_POST['success_message']),
               'pubg_processing_message' => sanitize_textarea_field($_POST['processing_message'])
           );
           
           foreach ($settings as $key => $value) {
               update_option($key, $value);
           }
           
           $message = '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
       }
       
       if (isset($_POST['test_api'])) {
           if (!wp_verify_nonce($_POST['pubg_settings_nonce'], 'pubg_save_settings')) {
               wp_die('Security check failed');
           }
           
           $api = new PUBG_API();
           $test_result = $api->get_player_info('5555511111');
           if ($test_result['success']) {
               $message = '<div class="notice notice-success"><p>API test successful!</p></div>';
           } else {
               $message = '<div class="notice notice-error"><p>API test failed: ' . esc_html($test_result['message']) . '</p></div>';
           }
       }
       
       $api_key = get_option('pubg_api_key');
       $api_url = get_option('pubg_api_url');
       $api_configured = !empty($api_key) && !empty($api_url);
       
       ?>
       <div class="wrap">
           <h1>PUBG Recharge Settings</h1>
           
           <?php echo $message; ?>
           
           <form method="post" action="" autocomplete="off">
               <?php wp_nonce_field('pubg_save_settings', 'pubg_settings_nonce'); ?>
               
               <div class="postbox">
                   <h3 class="hndle">API Configuration</h3>
                   <div class="inside">
                       <table class="form-table">
                           <tr>
                               <th scope="row">API Status</th>
                               <td>
                                   <?php if ($api_configured): ?>
                                       <span style="color: #28a745; font-weight: bold; font-size: 16px;">✓ Configured</span>
                                   <?php else: ?>
                                       <span style="color: #dc3545; font-weight: bold; font-size: 16px;">✗ Not Configured</span>
                                   <?php endif; ?>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">API Key</th>
                               <td>
                                   <input type="password" name="api_key" value="<?php echo esc_attr($api_key); ?>" 
                                          placeholder="Enter API Key" class="regular-text">
                                   <p class="description">Enter your API key for PUBG recharge service.</p>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">API URL</th>
                               <td>
                                   <input type="password" name="api_url" value="<?php echo esc_attr($api_url); ?>" 
                                          placeholder="Enter API URL" class="regular-text">
                                   <p class="description">Enter your API endpoint URL.</p>
                               </td>
                           </tr>
                       </table>
                   </div>
               </div>
               
               <div class="postbox">
                   <h3 class="hndle">Customer Experience Settings</h3>
                   <div class="inside">
                       <table class="form-table">
                           <tr>
                               <th scope="row">Player ID Label</th>
                               <td>
                                   <input type="text" name="player_id_label" value="<?php echo esc_attr(get_option('pubg_player_id_label', 'Player ID')); ?>" class="regular-text">
                                   <p class="description">Custom label for the Player ID field shown to customers.</p>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">Success Message</th>
                               <td>
                                   <textarea name="success_message" class="large-text" rows="3"><?php echo esc_textarea(get_option('pubg_success_message', 'Your PUBG UC has been successfully recharged!')); ?></textarea>
                                   <p class="description">Message shown to customers when recharge is successful.</p>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">Processing Message</th>
                               <td>
                                   <textarea name="processing_message" class="large-text" rows="3"><?php echo esc_textarea(get_option('pubg_processing_message', 'Your order is being processed. UC will be delivered shortly due to high server load.')); ?></textarea>
                                   <p class="description">Message shown when order is processing (instead of showing failure to customer).</p>
                               </td>
                           </tr>
                       </table>
                   </div>
               </div>
               
               <div class="postbox">
                   <h3 class="hndle">System Settings</h3>
                   <div class="inside">
                       <table class="form-table">
                           <tr>
                               <th scope="row">Auto Recharge</th>
                               <td>
                                   <label>
                                       <input type="checkbox" name="enable_auto_recharge" value="1" <?php checked(get_option('pubg_enable_auto_recharge'), 1); ?>>
                                       Enable automatic recharge when order is completed
                                   </label>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">Notification Email</th>
                               <td>
                                   <input type="email" name="notification_email" value="<?php echo esc_attr(get_option('pubg_notification_email')); ?>" class="regular-text">
                                   <p class="description">Email address for system notifications.</p>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">Low Stock Threshold</th>
                               <td>
                                   <input type="number" name="low_stock_threshold" value="<?php echo esc_attr(get_option('pubg_low_stock_threshold', 10)); ?>" class="small-text" min="1">
                                   <p class="description">Send alert when available codes drop below this number.</p>
                               </td>
                           </tr>
                           
                           <tr>
                               <th scope="row">Debug Mode</th>
                               <td>
                                   <label>
                                       <input type="checkbox" name="debug_mode" value="1" <?php checked(get_option('pubg_debug_mode'), 1); ?>>
                                       Enable debug mode for additional logging
                                   </label>
                               </td>
                           </tr>
                       </table>
                   </div>
               </div>
               
               <div class="postbox">
                   <h3 class="hndle">API Test</h3>
                   <div class="inside">
                       <p>Test your API connection to ensure settings are correct.</p>
                       <?php if ($api_configured): ?>
                           <input type="submit" name="test_api" class="button button-secondary" value="Test API Connection">
                       <?php else: ?>
                           <p style="color: #dc3545;">Please configure API settings first to run the test.</p>
                       <?php endif; ?>
                   </div>
               </div>
               
               <p class="submit">
                   <input type="submit" name="save_settings" class="button-primary" value="Save Settings">
               </p>
           </form>
           
           <div class="postbox">
               <h3 class="hndle">System Information</h3>
               <div class="inside">
                   <table class="form-table">
                       <tr>
                           <th>Plugin Version</th>
                           <td><?php echo PUBG_RECHARGE_VERSION; ?></td>
                       </tr>
                       <tr>
                           <th>API Key Status</th>
                           <td><?php echo $api_key ? 'Set (' . strlen($api_key) . ' characters)' : 'Not set'; ?></td>
                       </tr>
                       <tr>
                           <th>API URL Status</th>
                           <td><?php echo $api_url ? 'Set (' . strlen($api_url) . ' characters)' : 'Not set'; ?></td>
                       </tr>
                       <tr>
                           <th>WordPress Version</th>
                           <td><?php echo get_bloginfo('version'); ?></td>
                       </tr>
                       <tr>
                           <th>WooCommerce Status</th>
                           <td>
                               <?php if (class_exists('WooCommerce')): ?>
                                   <span style="color: #28a745;">✓ Active (v<?php echo WC()->version; ?>)</span>
                               <?php else: ?>
                                   <span style="color: #dc3545;">✗ Not Active</span>
                               <?php endif; ?>
                           </td>
                       </tr>
                   </table>
               </div>
           </div>
       </div>
       
       <script>
       jQuery(document).ready(function($) {
           $('input[name="api_url"], input[name="api_key"]').on('copy paste cut contextmenu', function(e) {
               e.preventDefault();
               return false;
           });
       });
       </script>
       
       <style>
       input[name="api_url"], input[name="api_key"] {
           -webkit-user-select: none;
           -moz-user-select: none;
           -ms-user-select: none;
           user-select: none;
       }
       @media print {
           input[name="api_url"], input[name="api_key"] {
               visibility: hidden;
           }
       }
       </style>
       <?php
   }
   
   private function export_codes() {
       global $wpdb;
       
       $codes = $wpdb->get_results("SELECT c.*, cat.name as category_name 
                                   FROM {$wpdb->prefix}pubg_recharge_codes c 
                                   JOIN {$wpdb->prefix}pubg_recharge_categories cat ON c.category_id = cat.id 
                                   ORDER BY c.id");
       
       header('Content-Type: text/csv');
       header('Content-Disposition: attachment; filename="pubg_codes_' . date('Y-m-d') . '.csv"');
       
       $output = fopen('php://output', 'w');
       fputcsv($output, array('ID', 'Category', 'Code', 'Status', 'Order ID', 'Player ID', 'Date Added', 'Date Used'));
       
       foreach ($codes as $code) {
           fputcsv($output, array(
               $code->id,
               $code->category_name,
               $code->code,
               $code->status,
               $code->order_id,
               $code->player_id,
               $code->date_added,
               $code->date_used
           ));
       }
       
       fclose($output);
       exit;
   }
   
   private function export_settings() {
       $settings = array(
           'pubg_api_url' => get_option('pubg_api_url'),
           'pubg_enable_auto_recharge' => get_option('pubg_enable_auto_recharge'),
           'pubg_notification_email' => get_option('pubg_notification_email'),
           'pubg_low_stock_threshold' => get_option('pubg_low_stock_threshold'),
           'pubg_debug_mode' => get_option('pubg_debug_mode'),
           'pubg_player_id_label' => get_option('pubg_player_id_label'),
           'pubg_success_message' => get_option('pubg_success_message'),
           'pubg_processing_message' => get_option('pubg_processing_message')
       );
       
       header('Content-Type: application/json');
       header('Content-Disposition: attachment; filename="pubg_settings_' . date('Y-m-d') . '.json"');
       
       echo json_encode($settings, JSON_PRETTY_PRINT);
       exit;
   }
   
   private function import_codes() {
       if (!isset($_FILES['codes_file']) || $_FILES['codes_file']['error'] !== UPLOAD_ERR_OK) {
           echo '<div class="notice notice-error"><p>File upload failed!</p></div>';
           return;
       }
       
       $file = $_FILES['codes_file']['tmp_name'];
       $handle = fopen($file, 'r');
       
       if ($handle === false) {
           echo '<div class="notice notice-error"><p>Could not read file!</p></div>';
           return;
       }
       
       global $wpdb;
       $imported = 0;
       $line = 0;
       
       while (($data = fgetcsv($handle)) !== false) {
           $line++;
           if ($line === 1) continue; // Skip header
           
           if (count($data) >= 3) {
               $category_id = intval($data[0]);
               $code = sanitize_text_field($data[1]);
               $status = sanitize_text_field($data[2]);
               
               if (!empty($code) && in_array($status, array('available', 'used', 'failed'))) {
                   $existing = $wpdb->get_var($wpdb->prepare(
                       "SELECT id FROM {$wpdb->prefix}pubg_recharge_codes WHERE code = %s", $code
                   ));
                   
                   if (!$existing) {
                       $wpdb->insert(
                           $wpdb->prefix . 'pubg_recharge_codes',
                           array(
                               'category_id' => $category_id,
                               'code' => $code,
                               'status' => $status
                           )
                       );
                       $imported++;
                   }
               }
           }
       }
       
       fclose($handle);
       echo '<div class="notice notice-success"><p>' . $imported . ' codes imported successfully!</p></div>';
   }
   
   private function import_settings() {
       if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
           echo '<div class="notice notice-error"><p>File upload failed!</p></div>';
           return;
       }
       
       $file = $_FILES['settings_file']['tmp_name'];
       $content = file_get_contents($file);
       $settings = json_decode($content, true);
       
       if ($settings === null) {
           echo '<div class="notice notice-error"><p>Invalid JSON file!</p></div>';
           return;
       }
       
       $imported = 0;
       foreach ($settings as $key => $value) {
           if (strpos($key, 'pubg_') === 0 && $key !== 'pubg_api_key') {
               update_option($key, $value);
               $imported++;
           }
       }
       
       echo '<div class="notice notice-success"><p>' . $imported . ' settings imported successfully! (API key excluded for security)</p></div>';
   }
}
