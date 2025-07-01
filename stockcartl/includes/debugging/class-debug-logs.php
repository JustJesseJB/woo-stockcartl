<?php
/**
 * Debug Logs Admin Page for StockCartl
 *
 * @package StockCartl
 * @subpackage Debugging
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Debug Logs Class
 * 
 * Handles the debug logs admin page
 */
class StockCartl_Debug_Logs {

    /**
     * Debug instance
     *
     * @var StockCartl_Debug
     */
    private $debug;

    /**
     * License instance
     *
     * @var StockCartl_License
     */
    private $license;

    /**
     * Constructor
     */
    public function __construct() {
        // Get debug instance
        $this->debug = stockcartl_debug();
        
        // Initialize license
        $this->init_license();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle AJAX actions
        add_action('wp_ajax_stockcartl_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_stockcartl_archive_log', array($this, 'ajax_archive_log'));
        add_action('wp_ajax_stockcartl_delete_log', array($this, 'ajax_delete_log'));
        add_action('wp_ajax_stockcartl_export_log', array($this, 'ajax_export_log'));
        add_action('wp_ajax_stockcartl_export_system_info', array($this, 'ajax_export_system_info'));
    }

    /**
     * Initialize the license
     */
    private function init_license() {
        // Check if license class exists
        if (class_exists('StockCartl_License')) {
            $this->license = new StockCartl_License();
        } else {
            // Use a simple implementation for now
            $this->license = new stdClass();
            $this->license->has_feature = function($feature) {
                // During development, all features are available
                if (defined('STOCKCARTL_DEV_MODE') && STOCKCARTL_DEV_MODE) {
                    return true;
                }
                
                // Basic features always available
                $basic_features = ['basic_logging', 'simple_debug_toggle', 'system_info'];
                if (in_array($feature, $basic_features)) {
                    return true;
                }
                
                return false;
            };
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'stockcartl',
            __('Debug Logs', 'stockcartl'),
            __('Debug Logs', 'stockcartl'),
            'manage_woocommerce',
            'stockcartl-debug-logs',
            array($this, 'render_debug_logs_page')
        );
    }

    /**
     * Render debug logs page
     */
    public function render_debug_logs_page() {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'stockcartl'));
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'logs';
        
        // Define tabs
        $tabs = array(
            'logs' => __('Debug Logs', 'stockcartl'),
            'system' => __('System Info', 'stockcartl')
        );
        
        // Premium tabs
        if ($this->license->has_feature('log_filtering')) {
            $tabs['archive'] = __('Log Archive', 'stockcartl');
        }
        
        ?>
        <div class="wrap stockcartl-debug-logs-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <?php foreach ($tabs as $tab_id => $tab_name) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=stockcartl-debug-logs&tab=' . $tab_id)); ?>" class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tab_name); ?></a>
                <?php endforeach; ?>
            </nav>
            
            <div class="stockcartl-debug-content">
                <?php
                switch ($current_tab) {
                    case 'system':
                        $this->render_system_info_tab();
                        break;
                    case 'archive':
                        $this->render_archive_tab();
                        break;
                    case 'logs':
                    default:
                        $this->render_logs_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
            .stockcartl-debug-content {
                margin-top: 20px;
                background: #fff;
                padding: 20px;
                border: 1px solid #e5e5e5;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            
            .stockcartl-log-viewer {
                background: #f5f5f5;
                padding: 10px;
                margin-top: 15px;
                border: 1px solid #e1e1e1;
                border-radius: 3px;
                overflow: auto;
                max-height: 500px;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.5;
                color: #333;
            }
            
            .stockcartl-log-viewer.empty {
                color: #999;
                font-style: italic;
                text-align: center;
                padding: 20px;
            }
            
            .stockcartl-log-toolbar {
                margin-bottom: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .stockcartl-log-toolbar .button {
                margin-left: 5px;
            }
            
            .stockcartl-log-toolbar-left {
                display: flex;
                align-items: center;
            }
            
            .stockcartl-log-toolbar-right {
                display: flex;
                align-items: center;
            }
            
            .stockcartl-log-line {
                padding: 2px 0;
            }
            
            .stockcartl-log-line.info {
                color: #3498db;
            }
            
            .stockcartl-log-line.warning {
                color: #f39c12;
            }
            
            .stockcartl-log-line.error {
                color: #e74c3c;
            }
            
            .stockcartl-log-line.critical {
                color: #c0392b;
                font-weight: bold;
            }
            
            .stockcartl-system-info-section {
                margin-bottom: 30px;
            }
            
            .stockcartl-system-info-section h3 {
                margin-top: 0;
                padding-bottom: 5px;
                border-bottom: 1px solid #eee;
            }
            
            .stockcartl-system-info-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .stockcartl-system-info-table th,
            .stockcartl-system-info-table td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            .stockcartl-system-info-table th {
                width: 30%;
                font-weight: 600;
            }
            
            .stockcartl-archive-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            
            .stockcartl-archive-table th,
            .stockcartl-archive-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }
            
            .stockcartl-archive-table th {
                background: #f9f9f9;
            }
            
            .stockcartl-archive-table .actions {
                width: 100px;
                text-align: right;
            }
            
            .stockcartl-premium-notice {
                background: #f7f7f7;
                border-left: 4px solid #d4af37;
                padding: 10px 12px;
                margin-bottom: 15px;
            }
            
            .stockcartl-loading {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid rgba(0, 0, 0, 0.3);
                border-radius: 50%;
                border-top-color: #000;
                animation: stockcartl-spin 1s ease-in-out infinite;
                margin-left: 5px;
            }
            
            @keyframes stockcartl-spin {
                to { transform: rotate(360deg); }
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Clear log
            $('#stockcartl-clear-log').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php esc_attr_e('Are you sure you want to clear the log?', 'stockcartl'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true);
                button.after('<span class="stockcartl-loading"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'stockcartl_clear_log',
                        nonce: '<?php echo wp_create_nonce('stockcartl_debug_actions'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.stockcartl-log-viewer').html('').addClass('empty').text('<?php esc_attr_e('Log is empty.', 'stockcartl'); ?>');
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_attr_e('An error occurred. Please try again.', 'stockcartl'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        $('.stockcartl-loading').remove();
                    }
                });
            });
            
            // Archive log
            $('#stockcartl-archive-log').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                button.prop('disabled', true);
                button.after('<span class="stockcartl-loading"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'stockcartl_archive_log',
                        nonce: '<?php echo wp_create_nonce('stockcartl_debug_actions'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.stockcartl-log-viewer').html('').addClass('empty').text('<?php esc_attr_e('Log is empty. Previous log has been archived.', 'stockcartl'); ?>');
                            
                            // Refresh archive tab if on that page
                            if ('<?php echo $current_tab; ?>' === 'archive') {
                                window.location.reload();
                            }
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_attr_e('An error occurred. Please try again.', 'stockcartl'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        $('.stockcartl-loading').remove();
                    }
                });
            });
            
            // Delete archive log
            $('.stockcartl-delete-log').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php esc_attr_e('Are you sure you want to delete this log file?', 'stockcartl'); ?>')) {
                    return;
                }
                
                var button = $(this);
                var row = button.closest('tr');
                var file = button.data('file');
                
                button.prop('disabled', true);
                button.after('<span class="stockcartl-loading"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'stockcartl_delete_log',
                        file: file,
                        nonce: '<?php echo wp_create_nonce('stockcartl_debug_actions'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Show message if table is now empty
                                var table = $('.stockcartl-archive-table');
                                if (table.find('tbody tr').length === 0) {
                                    table.after('<p><?php esc_attr_e('No archived logs found.', 'stockcartl'); ?></p>');
                                    table.hide();
                                }
                            });
                        } else {
                            alert(response.data.message);
                            button.prop('disabled', false);
                            $('.stockcartl-loading').remove();
                        }
                    },
                    error: function() {
                        alert('<?php esc_attr_e('An error occurred. Please try again.', 'stockcartl'); ?>');
                        button.prop('disabled', false);
                        $('.stockcartl-loading').remove();
                    }
                });
            });
            
            // Export system info
            $('#stockcartl-export-system-info').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                button.prop('disabled', true);
                button.after('<span class="stockcartl-loading"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'stockcartl_export_system_info',
                        nonce: '<?php echo wp_create_nonce('stockcartl_debug_actions'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create and download file
                            var blob = new Blob([response.data.content], { type: 'text/plain' });
                            var link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = response.data.filename;
                            link.click();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_attr_e('An error occurred. Please try again.', 'stockcartl'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        $('.stockcartl-loading').remove();
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render logs tab
     */
    private function render_logs_tab() {
        // Get log content
        $log_content = $this->debug->get_log_content();
        $is_empty = empty($log_content);
        
        // Advanced filtering options
        $has_filtering = $this->license->has_feature('log_filtering');
        $filter_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
        $filter_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $filter_lines = isset($_GET['lines']) ? absint($_GET['lines']) : 100;
        
        // Apply filters if available
        if ($has_filtering && !$is_empty) {
            // Filter by level
            if (!empty($filter_level)) {
                $filtered_lines = array();
                $lines = explode(PHP_EOL, $log_content);
                
                foreach ($lines as $line) {
                    if (strpos($line, "[{$filter_level}]") !== false) {
                        $filtered_lines[] = $line;
                    }
                }
                
                $log_content = implode(PHP_EOL, $filtered_lines);
            }
            
            // Filter by search
            if (!empty($filter_search)) {
                $filtered_lines = array();
                $lines = explode(PHP_EOL, $log_content);
                
                foreach ($lines as $line) {
                    if (stripos($line, $filter_search) !== false) {
                        $filtered_lines[] = $line;
                    }
                }
                
                $log_content = implode(PHP_EOL, $filtered_lines);
            }
            
            // Limit lines
            if ($filter_lines > 0) {
                $lines = explode(PHP_EOL, $log_content);
                $lines = array_filter($lines); // Remove empty lines
                $lines = array_slice($lines, -$filter_lines);
                $log_content = implode(PHP_EOL, $lines);
            }
        }
        
        // Format log content for display
        if (!$is_empty) {
            $formatted_content = '';
            $lines = explode(PHP_EOL, $log_content);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $class = '';
                
                // Detect log level
                if (strpos($line, '[info]') !== false) {
                    $class = 'info';
                } elseif (strpos($line, '[warning]') !== false) {
                    $class = 'warning';
                } elseif (strpos($line, '[error]') !== false) {
                    $class = 'error';
                } elseif (strpos($line, '[critical]') !== false) {
                    $class = 'critical';
                }
                
                $formatted_content .= '<div class="stockcartl-log-line ' . $class . '">' . esc_html($line) . '</div>';
            }
            
            $log_content = $formatted_content;
        }
        
        ?>
        <div class="stockcartl-log-section">
            <div class="stockcartl-log-toolbar">
                <div class="stockcartl-log-toolbar-left">
                    <?php if ($has_filtering) : ?>
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="stockcartl-log-filter-form" style="display: flex; align-items: center;">
                            <input type="hidden" name="page" value="stockcartl-debug-logs">
                            <input type="hidden" name="tab" value="logs">
                            
                            <select name="level" style="margin-right: 10px;">
                                <option value=""><?php esc_html_e('All Levels', 'stockcartl'); ?></option>
                                <option value="info" <?php selected($filter_level, 'info'); ?>><?php esc_html_e('Info', 'stockcartl'); ?></option>
                                <option value="warning" <?php selected($filter_level, 'warning'); ?>><?php esc_html_e('Warning', 'stockcartl'); ?></option>
                                <option value="error" <?php selected($filter_level, 'error'); ?>><?php esc_html_e('Error', 'stockcartl'); ?></option>
                                <option value="critical" <?php selected($filter_level, 'critical'); ?>><?php esc_html_e('Critical', 'stockcartl'); ?></option>
                            </select>
                            
                            <input type="text" name="search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php esc_attr_e('Search logs...', 'stockcartl'); ?>" style="margin-right: 10px;">
                            
                            <select name="lines" style="margin-right: 10px;">
                                <option value="100" <?php selected($filter_lines, 100); ?>><?php esc_html_e('Last 100 lines', 'stockcartl'); ?></option>
                                <option value="250" <?php selected($filter_lines, 250); ?>><?php esc_html_e('Last 250 lines', 'stockcartl'); ?></option>
                                <option value="500" <?php selected($filter_lines, 500); ?>><?php esc_html_e('Last 500 lines', 'stockcartl'); ?></option>
                                <option value="1000" <?php selected($filter_lines, 1000); ?>><?php esc_html_e('Last 1000 lines', 'stockcartl'); ?></option>
                                <option value="0" <?php selected($filter_lines, 0); ?>><?php esc_html_e('All lines', 'stockcartl'); ?></option>
                            </select>
                            
                            <button type="submit" class="button"><?php esc_html_e('Filter', 'stockcartl'); ?></button>
                            
                            <?php if (!empty($filter_level) || !empty($filter_search) || $filter_lines != 100) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=stockcartl-debug-logs&tab=logs')); ?>" class="button"><?php esc_html_e('Reset', 'stockcartl'); ?></a>
                            <?php endif; ?>
                        </form>
                    <?php else : ?>
                        <h3 style="margin: 0;"><?php esc_html_e('Debug Log', 'stockcartl'); ?></h3>
                    <?php endif; ?>
                </div>
                
                <div class="stockcartl-log-toolbar-right">
                    <button id="stockcartl-clear-log" class="button button-secondary"><?php esc_html_e('Clear Log', 'stockcartl'); ?></button>
                    <button id="stockcartl-archive-log" class="button button-secondary"><?php esc_html_e('Archive Log', 'stockcartl'); ?></button>
                    
                    <?php if ($this->license->has_feature('log_export') && !$is_empty) : ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=stockcartl_export_log'), 'stockcartl_debug_actions', 'nonce')); ?>" class="button button-primary"><?php esc_html_e('Export Log', 'stockcartl'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stockcartl-log-viewer <?php echo $is_empty ? 'empty' : ''; ?>">
                <?php if ($is_empty) : ?>
                    <?php esc_html_e('Log is empty.', 'stockcartl'); ?>
                <?php else : ?>
                    <?php echo $log_content; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!$this->license->has_feature('advanced_logging')) : ?>
                <div class="stockcartl-premium-notice" style="margin-top: 15px;">
                    <p><strong><?php esc_html_e('Upgrade to StockCartl Pro for advanced logging features:', 'stockcartl'); ?></strong></p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <li><?php esc_html_e('Filter logs by level and keyword', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Export logs for support requests', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Advanced log formatting and highlighting', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Email notifications for critical errors', 'stockcartl'); ?></li>
                    </ul>
                    <p><a href="https://stockcartl.com/pricing" class="button button-primary" target="_blank"><?php esc_html_e('Upgrade Now', 'stockcartl'); ?></a></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render system info tab
     */
    private function render_system_info_tab() {
        // Get system info
        $info = $this->debug->get_system_info();
        
        ?>
        <div class="stockcartl-system-info-section">
            <div class="stockcartl-log-toolbar">
                <h3 style="margin: 0;"><?php esc_html_e('System Information', 'stockcartl'); ?></h3>
                <button id="stockcartl-export-system-info" class="button button-primary"><?php esc_html_e('Export Info', 'stockcartl'); ?></button>
            </div>
            
            <p><?php esc_html_e('This information can help when troubleshooting issues with StockCartl.', 'stockcartl'); ?></p>
            
            <!-- WordPress Information -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('WordPress', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <tbody>
                        <?php foreach ($info['wordpress'] as $key => $value) : ?>
                            <tr>
                                <th><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- WooCommerce Information -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('WooCommerce', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <tbody>
                        <?php foreach ($info['woocommerce'] as $key => $value) : ?>
                            <tr>
                                <th><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- StockCartl Information -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('StockCartl', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <tbody>
                        <?php foreach ($info['stockcartl'] as $key => $value) : ?>
                            <tr>
                                <th><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Server Information -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('Server', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <tbody>
                        <?php foreach ($info['server'] as $key => $value) : ?>
                            <tr>
                                <th><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Theme Information -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('Theme', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <tbody>
                        <?php foreach ($info['theme'] as $key => $value) : ?>
                            <tr>
                                <th><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></th>
                                <td><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Active Plugins -->
            <div class="stockcartl-system-info-section">
                <h3><?php esc_html_e('Active Plugins', 'stockcartl'); ?></h3>
                <table class="stockcartl-system-info-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'stockcartl'); ?></th>
                            <th><?php esc_html_e('Version', 'stockcartl'); ?></th>
                            <th><?php esc_html_e('Author', 'stockcartl'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($info['active_plugins'] as $plugin) : ?>
                            <tr>
                                <td><?php echo esc_html($plugin['name']); ?></td>
                                <td><?php echo esc_html($plugin['version']); ?></td>
                                <td><?php echo esc_html($plugin['author']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!$this->license->has_feature('conflict_detection')) : ?>
                <div class="stockcartl-premium-notice" style="margin-top: 15px;">
                    <p><strong><?php esc_html_e('Upgrade to StockCartl Enterprise for advanced diagnostic features:', 'stockcartl'); ?></strong></p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <li><?php esc_html_e('Conflict detection system', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Pre-update compatibility check', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Post-update monitoring', 'stockcartl'); ?></li>
                        <li><?php esc_html_e('Automated issue reporting', 'stockcartl'); ?></li>
                    </ul>
                    <p><a href="https://stockcartl.com/pricing" class="button button-primary" target="_blank"><?php esc_html_e('Upgrade Now', 'stockcartl'); ?></a></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render archive tab
     */
    private function render_archive_tab() {
        // Get archived log files
        $log_files = $this->debug->get_log_files();
        
        ?>
        <div class="stockcartl-archive-section">
            <h3><?php esc_html_e('Log Archive', 'stockcartl'); ?></h3>
            
            <?php if (empty($log_files)) : ?>
                <p><?php esc_html_e('No archived logs found.', 'stockcartl'); ?></p>
            <?php else : ?>
                <table class="stockcartl-archive-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('File', 'stockcartl'); ?></th>
                            <th><?php esc_html_e('Date', 'stockcartl'); ?></th>
                            <th><?php esc_html_e('Size', 'stockcartl'); ?></th>
                            <th class="actions"><?php esc_html_e('Actions', 'stockcartl'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_files as $file) : ?>
                            <tr>
                                <td><?php echo esc_html($file['name']); ?></td>
                                <td><?php echo esc_html($file['date']); ?></td>
                                <td><?php echo esc_html($file['size']); ?></td>
                                <td class="actions">
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=stockcartl_export_log&file=' . urlencode($file['path'])), 'stockcartl_debug_actions', 'nonce')); ?>" class="button button-small"><?php esc_html_e('Download', 'stockcartl'); ?></a>
                                    <a href="#" class="button button-small stockcartl-delete-log" data-file="<?php echo esc_attr($file['path']); ?>"><?php esc_html_e('Delete', 'stockcartl'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for clearing log
     */
    public function ajax_clear_log() {
        // Security check
        check_ajax_referer('stockcartl_debug_actions', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'stockcartl')));
        }
        
        $result = $this->debug->clear_log();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Log cleared successfully.', 'stockcartl')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear log.', 'stockcartl')));
        }
    }

    /**
     * AJAX handler for archiving log
     */
    public function ajax_archive_log() {
        // Security check
        check_ajax_referer('stockcartl_debug_actions', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'stockcartl')));
        }
        
        $result = $this->debug->archive_log();
        
        if ($result) {
            wp_send_json_success(array('message' => __('Log archived successfully.', 'stockcartl')));
        } else {
            wp_send_json_error(array('message' => __('Failed to archive log. Log may be empty.', 'stockcartl')));
        }
    }

    /**
     * AJAX handler for deleting log file
     */
    public function ajax_delete_log() {
        // Security check
        check_ajax_referer('stockcartl_debug_actions', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'stockcartl')));
        }
        
        if (!isset($_POST['file'])) {
            wp_send_json_error(array('message' => __('No file specified.', 'stockcartl')));
        }
        
        $file = sanitize_text_field($_POST['file']);
        $result = $this->debug->delete_log_file($file);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Log file deleted successfully.', 'stockcartl')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete log file.', 'stockcartl')));
        }
    }

    /**
     * AJAX handler for exporting log
     */
    public function ajax_export_log() {
        // Security check
        check_ajax_referer('stockcartl_debug_actions', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have permission to perform this action.', 'stockcartl'));
        }
        
        // Check if feature is available
        if (!$this->license->has_feature('log_export')) {
            wp_die(__('This feature is only available in StockCartl Pro.', 'stockcartl'));
        }
        
        // Get file to export
        $file = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : $this->debug->get_log_file();
        
        // Check if file exists
        if (!file_exists($file)) {
            wp_die(__('Log file not found.', 'stockcartl'));
        }
        
        // Get file content
        $content = file_get_contents($file);
        
        // Send file headers
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . strlen($content));
        
        // Output file content
        echo $content;
        exit;
    }

    /**
     * AJAX handler for exporting system info
     */
    public function ajax_export_system_info() {
        // Security check
        check_ajax_referer('stockcartl_debug_actions', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'stockcartl')));
        }
        
        // Get system info
        $content = $this->debug->export_system_info();
        $filename = 'stockcartl-system-info-' . date('Y-m-d') . '.txt';
        
        wp_send_json_success(array(
            'content' => $content,
            'filename' => $filename
        ));
    }
}

// Initialize the debug logs class
add_action('plugins_loaded', function() {
    new StockCartl_Debug_Logs();
}, 12);