<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap instapulse-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="instapulse-dashboard">

        <?php
        // Show sampling info if we have data and sample rate < 100%
        $current_sample_rate = isset($plugin_times['sample_rate']) ? $plugin_times['sample_rate'] : 2;
        $has_profiles = isset($plugin_times['total_profiles']) && $plugin_times['total_profiles'] > 0;

        if ($has_profiles && $current_sample_rate < 100):
        ?>
        <div class="instapulse-sampling-notice">
            <div class="instapulse-sampling-icon">📊</div>
            <div class="instapulse-sampling-content">
                <div class="instapulse-sampling-title">Sampling Active</div>
                <div class="instapulse-sampling-text">
                    Tracking <strong><?php echo $current_sample_rate; ?>%</strong> of requests
                    (<strong><?php echo $current_sample_rate; ?></strong> out of every 100 page loads) for optimal performance.
                </div>
                <div class="instapulse-sampling-action">
                    <a href="#instapulse-settings-form" class="instapulse-sampling-link">⚙️ Adjust in Settings</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="instapulse-section">
            <h2>Performance Summary</h2>
            <div class="instapulse-card">
                <?php
                // Calculate comprehensive performance metrics
                $total_plugins = !empty($plugin_times['plugins']) ? count($plugin_times['plugins']) : 0;
                $avg_request_time = 0;
                $total_requests = 0;
                if (!empty($recent_requests)) {
                    $total_request_time = array_sum(array_column($recent_requests, 'load_time'));
                    $avg_request_time = $total_request_time / count($recent_requests);
                    $total_requests = count($recent_requests);
                }

                $total_memory = isset($plugin_times['total_memory']) ? $plugin_times['total_memory'] : 0;
                $total_profiles = isset($plugin_times['total_profiles']) ? $plugin_times['total_profiles'] : 0;

                // Asset metrics
                $total_assets = isset($assets_summary['total_assets']) ? $assets_summary['total_assets'] : 0;
                $avg_css_count = isset($assets_summary['avg_css_count']) ? $assets_summary['avg_css_count'] : 0;
                $avg_js_count = isset($assets_summary['avg_js_count']) ? $assets_summary['avg_js_count'] : 0;
                $avg_asset_size = isset($assets_summary['avg_size']) ? $assets_summary['avg_size'] : 0;

                // Slow query metrics
                $total_slow_queries = 0;
                $avg_query_time = 0;
                if (!empty($slow_query_stats['basic_stats']) && is_array($slow_query_stats['basic_stats'])) {
                    $total_slow_queries = (int) ($slow_query_stats['basic_stats']['total_slow_queries'] ?? 0);
                    $avg_query_time = (float) ($slow_query_stats['basic_stats']['avg_execution_time'] ?? 0);
                }

                // Performance indicators
                $performance_score = 'Good';
                $score_class = 'good';
                if ($avg_request_time > 2000 || $total_slow_queries > 50) {
                    $performance_score = 'Poor';
                    $score_class = 'poor';
                } elseif ($avg_request_time > 1000 || $total_slow_queries > 20) {
                    $performance_score = 'Fair';
                    $score_class = 'fair';
                }
                ?>

                <div class="instapulse-summary-grid">
                    <div class="instapulse-summary-item">
                        <h3>Performance Score</h3>
                        <span class="instapulse-big-number instapulse-score-<?php echo $score_class; ?>"><?php echo $performance_score; ?></span>
                    </div>
                    <div class="instapulse-summary-item">
                        <h3>Avg Page Load</h3>
                        <span class="instapulse-big-number"><?php echo number_format($avg_request_time, 0); ?>ms</span>
                        <small><?php echo $total_requests; ?> requests tracked</small>
                    </div>
                    <div class="instapulse-summary-item">
                        <h3>Peak Memory</h3>
                        <span class="instapulse-big-number"><?php echo $total_memory > 0 ? size_format($total_memory) : 'N/A'; ?></span>
                    </div>
                    <div class="instapulse-summary-item">
                        <h3>Total Assets</h3>
                        <span class="instapulse-big-number"><?php echo number_format($total_assets); ?></span>
                        <small><?php echo number_format($avg_css_count, 1); ?> CSS, <?php echo number_format($avg_js_count, 1); ?> JS avg</small>
                    </div>
                    <div class="instapulse-summary-item">
                        <h3>Slow Queries</h3>
                        <span class="instapulse-big-number <?php echo $total_slow_queries > 20 ? 'instapulse-warning' : ''; ?>"><?php echo number_format($total_slow_queries); ?></span>
                        <small><?php echo $avg_query_time > 0 ? number_format($avg_query_time, 0) . 'ms avg' : 'No data'; ?></small>
                    </div>
                </div>

                <?php if ($performance_score !== 'Good'): ?>
                <div class="instapulse-performance-alerts">
                    <h4>⚠️ Performance Recommendations:</h4>
                    <ul>
                        <?php if ($avg_request_time > 1500): ?>
                        <li>High average load time (<?php echo number_format($avg_request_time, 0); ?>ms) - consider optimizing slow plugins</li>
                        <?php endif; ?>
                        <?php if ($total_slow_queries > 20): ?>
                        <li>Many slow database queries detected (<?php echo $total_slow_queries; ?>) - check query optimization</li>
                        <?php endif; ?>
                        <?php if ($avg_css_count + $avg_js_count > 15): ?>
                        <li>High asset count per page (<?php echo number_format($avg_css_count + $avg_js_count, 1); ?>) - consider asset optimization</li>
                        <?php endif; ?>
                        <?php if ($avg_asset_size > 100000): ?>
                        <li>Large average asset size (<?php echo size_format($avg_asset_size); ?>) - consider minification and compression</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($plugin_times) && isset($plugin_times['plugins']) && $plugin_times['total_profiles'] > 0): ?>
        <div class="instapulse-section">
            <h2>Load Times</h2>
            <div class="instapulse-card">
                    <div class="instapulse-summary">
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Average Load Time:</span>
                            <span class="instapulse-stat-value"><?php echo number_format($plugin_times['total_time'], 2); ?>ms</span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Peak Memory:</span>
                            <span class="instapulse-stat-value"><?php echo isset($plugin_times['total_memory']) ? size_format($plugin_times['total_memory']) : 'N/A'; ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Sample Rate:</span>
                            <span class="instapulse-stat-value"><?php
                                // Get the actual configured sample rate
                                $current_options = get_option('instapulse_options', array());
                                $display_sample_rate = isset($plugin_times['sample_rate']) ? $plugin_times['sample_rate'] : (isset($current_options['sample_rate']) ? $current_options['sample_rate'] : 2);
                                echo $display_sample_rate;
                            ?>%</span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Total Profiles:</span>
                            <span class="instapulse-stat-value"><?php echo $plugin_times['total_profiles'] ?? 0; ?></span>
                        </div>
                        <?php
                        // Get database info
                        require_once INSTAPULSE_PLUGIN_PATH . 'includes/class-database.php';
                        $database = InstaPulse_Database::get_instance();
                        $table_info = $database->get_table_info();
                        ?>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Database Size:</span>
                            <span class="instapulse-stat-value"><?php echo $table_info ? $table_info['size_mb'] . 'MB' : 'N/A'; ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Last Updated:</span>
                            <span class="instapulse-stat-value"><?php echo esc_html($plugin_times['timestamp']); ?></span>
                        </div>
                    </div>

                    <?php if (isset($mu_status) && !$mu_status['loaded']): ?>
                    <div class="instapulse-notice instapulse-notice-warning">
                        <p><strong>MU Plugin Not Active:</strong> For accurate measurements, the MU plugin should be automatically installed. Please check your mu-plugins directory permissions.</p>
                        <p><strong>Status:</strong>
                            <?php if (!$mu_status['file_exists']): ?>
                                File missing
                            <?php elseif (!$mu_status['writable']): ?>
                                Directory not writable
                            <?php else: ?>
                                Unknown issue
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>


                    <?php if (isset($plugin_times['phases']) && !empty($plugin_times['phases'])): ?>
                    <div class="instapulse-phases">
                        <h3>WordPress Loading Phases</h3>
                        <table class="instapulse-table instapulse-phases-table">
                            <thead>
                                <tr>
                                    <th>Phase</th>
                                    <th>Time (ms)</th>
                                    <th>Memory (bytes)</th>
                                    <th>Cumulative Time (ms)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plugin_times['phases'] as $phase => $data): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $phase))); ?></strong></td>
                                        <td><?php echo number_format($data['time_ms'], 2); ?></td>
                                        <td><?php echo size_format($data['memory_bytes']); ?></td>
                                        <td><?php echo number_format($data['absolute_time_ms'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th>Plugin Name</th>
                                <th>Load Time (ms)</th>
                                <th>Memory Usage</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort by load time and separate themes from plugins
                            $sorted_plugins = $plugin_times['plugins'];
                            uasort($sorted_plugins, function($a, $b) {
                                return $b['load_time'] <=> $a['load_time'];
                            });

                            // Calculate max load time for bar width scaling
                            $max_load_time = 0;
                            foreach ($sorted_plugins as $data) {
                                $max_load_time = max($max_load_time, $data['load_time']);
                            }
                            $max_load_time = max($max_load_time, 1); // Avoid division by zero

                            foreach ($sorted_plugins as $plugin => $data):
                                $load_time = $data['load_time'];
                                $memory_usage = $data['memory_usage'];
                                $profile_count = $data['profile_count'] ?? 1;

                                // Check if this is a theme
                                $is_theme = (strpos($data['name'], 'Theme') !== false ||
                                           wp_get_theme()->get('Name') === $data['name']);

                                $performance_class = '';
                                if ($load_time > 50) {
                                    $performance_class = 'slow';
                                } elseif ($load_time > 20) {
                                    $performance_class = 'medium';
                                } else {
                                    $performance_class = 'fast';
                                }

                                $row_class = '';

                                // Calculate bar width (minimum 5% for visibility)
                                $bar_width = max(5, ($load_time / $max_load_time) * 100);
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <?php if ($is_theme): ?>
                                            <span class="instapulse-theme-icon">🎨</span>
                                        <?php endif; ?>
                                        <strong><?php echo esc_html($data['name']); ?></strong>
                                    </td>
                                    <td>
                                        <div class="instapulse-load-time-container">
                                            <div class="instapulse-load-time-bar instapulse-bar-<?php echo $performance_class; ?>"
                                                 style="width: <?php echo $bar_width; ?>%">
                                                <?php if ($bar_width > 25): ?>
                                                    <span class="instapulse-load-time-text"><?php echo number_format($load_time, 2); ?>ms</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($bar_width <= 25): ?>
                                                <span class="instapulse-load-time-text-outside"><?php echo number_format($load_time, 2); ?>ms</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo size_format($memory_usage); ?></td>
                                    <td>
                                        <span class="instapulse-performance <?php echo $performance_class; ?>">
                                            <?php echo ucfirst($performance_class); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
            </div>
        </div>
        <?php else: ?>
        <div class="instapulse-section">
            <h2>Load Times</h2>
            <div class="instapulse-card">
                <p>No plugin load time data available yet. Load times will appear after the next page load.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="instapulse-section">
            <h2>CSS/JS Assets</h2>
            <div class="instapulse-card">
                <?php if (!empty($assets_summary) && $assets_summary['total_assets'] > 0): ?>
                    <div class="instapulse-summary">
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Total Assets:</span>
                            <span class="instapulse-stat-value"><?php echo number_format($assets_summary['total_assets']); ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Avg CSS per Request:</span>
                            <span class="instapulse-stat-value"><?php echo $assets_summary['avg_css_count']; ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Avg JS per Request:</span>
                            <span class="instapulse-stat-value"><?php echo $assets_summary['avg_js_count']; ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Avg Asset Size:</span>
                            <span class="instapulse-stat-value"><?php echo $assets_summary['avg_size'] > 0 ? size_format($assets_summary['avg_size']) : 'N/A'; ?></span>
                        </div>
                    </div>

                    <?php if (!empty($assets_summary['by_source'])): ?>
                    <h3>Assets by Source</h3>
                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Type</th>
                                <th>Count</th>
                                <th>Avg Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets_summary['by_source'] as $source_data): ?>
                                <tr>
                                    <td>
                                        <span class="instapulse-source-<?php echo esc_attr($source_data['source']); ?>">
                                            <?php
                                            $icon = $source_data['source'] === 'plugin' ? '🔌' : ($source_data['source'] === 'theme' ? '🎨' : '⚙️');
                                            echo $icon . ' ' . esc_html($source_data['source_name'] ?: ucfirst($source_data['source']));
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="instapulse-asset-type instapulse-asset-<?php echo esc_attr($source_data['type']); ?>">
                                            <?php echo strtoupper($source_data['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($source_data['count']); ?></td>
                                    <td><?php echo $source_data['avg_size'] > 0 ? size_format($source_data['avg_size']) : 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($assets_summary['top_assets'])): ?>
                    <h3>Largest Assets</h3>
                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th>Handle</th>
                                <th>Source</th>
                                <th>Type</th>
                                <th>Avg Size</th>
                                <th>Frequency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($assets_summary['top_assets'], 0, 15) as $asset): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($asset['src'])): ?>
                                            <a href="<?php echo esc_url($asset['src']); ?>" target="_blank" title="<?php echo esc_attr($asset['src']); ?>">
                                                <code><?php echo esc_html($asset['handle']); ?></code>
                                            </a>
                                        <?php else: ?>
                                            <code><?php echo esc_html($asset['handle']); ?></code>
                                            <small>(inline asset)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $icon = $asset['source'] === 'plugin' ? '🔌' : ($asset['source'] === 'theme' ? '🎨' : '⚙️');
                                        echo $icon . ' ' . esc_html($asset['source_name'] ?: ucfirst($asset['source']));
                                        ?>
                                    </td>
                                    <td>
                                        <span class="instapulse-asset-type instapulse-asset-<?php echo esc_attr($asset['type']); ?>">
                                            <?php echo strtoupper($asset['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="instapulse-asset-size <?php echo $asset['avg_size'] > 100000 ? 'large' : ($asset['avg_size'] > 50000 ? 'medium' : 'small'); ?>">
                                            <?php echo size_format($asset['avg_size']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($asset['frequency']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No asset data available yet. Assets will be tracked on the next frontend page load.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="instapulse-section">
            <h2>Recent Frontend Requests</h2>
            <div class="instapulse-card">
                <?php if (!empty($recent_requests)): ?>
                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Type</th>
                                <th>Load Time (ms)</th>
                                <th>Memory</th>
                                <th>Queries</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($recent_requests, 0, 20) as $request): ?>
                                <tr>
                                    <td>
                                        <?php
                                        // Get the full URL with domain
                                        $full_url = home_url($request['url']);
                                        ?>
                                        <a href="<?php echo esc_url($full_url); ?>" target="_blank" title="<?php echo esc_attr($full_url); ?>">
                                            <?php echo esc_html($full_url); ?>
                                        </a>
                                        <?php if (isset($request['page_type']) && $request['page_type']): ?>
                                            <br><small class="instapulse-page-type">(<?php echo esc_html($request['page_type']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="instapulse-request-type instapulse-request-type-<?php echo esc_attr($request['type'] ?? 'page'); ?>">
                                            <?php echo esc_html(ucfirst($request['type'] ?? 'Page')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($request['load_time'], 2); ?></td>
                                    <td><?php echo isset($request['memory_usage']) ? size_format($request['memory_usage']) : 'N/A'; ?></td>
                                    <td><?php echo isset($request['queries']) ? $request['queries'] : 'N/A'; ?></td>
                                    <td><?php echo esc_html(date('M j, Y H:i:s', strtotime($request['timestamp']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (count($recent_requests) > 20): ?>
                        <p class="instapulse-note">Showing latest 20 requests out of <?php echo count($recent_requests); ?> total.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No frontend requests recorded yet. Visit the frontend of your site to see request data.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($slow_queries) || !empty($slow_query_stats['basic_stats'])): ?>
        <div class="instapulse-section">
            <h2>Slow Database Queries</h2>
            <div class="instapulse-card">
                <?php if (!empty($slow_query_stats['basic_stats']) && $slow_query_stats['basic_stats']['total_slow_queries'] > 0): ?>
                    <div class="instapulse-summary">
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Total Slow Queries:</span>
                            <span class="instapulse-stat-value"><?php echo number_format($slow_query_stats['basic_stats']['total_slow_queries']); ?></span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Average Time:</span>
                            <span class="instapulse-stat-value"><?php echo number_format($slow_query_stats['basic_stats']['avg_execution_time'], 2); ?>ms</span>
                        </div>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Slowest Query:</span>
                            <span class="instapulse-stat-value"><?php echo number_format($slow_query_stats['basic_stats']['max_execution_time'], 2); ?>ms</span>
                        </div>
                        <?php
                        $options = get_option('instapulse_options', array());
                        $threshold = isset($options['slow_query_threshold']) ? (int) $options['slow_query_threshold'] : 50;
                        ?>
                        <div class="instapulse-stat">
                            <span class="instapulse-stat-label">Threshold:</span>
                            <span class="instapulse-stat-value"><?php echo $threshold; ?>ms</span>
                        </div>
                    </div>

                    <?php if (!empty($slow_queries)): ?>
                    <h3>Recent Slow Queries</h3>
                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Query (Preview)</th>
                                <th>Execution Time</th>
                                <th>Caller</th>
                                <th>Request URI</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($slow_queries, 0, 20) as $query): ?>
                                <tr>
                                    <td>
                                        <code style="font-size: 11px; word-break: break-all;">
                                            <?php echo esc_html(substr($query['query_sql'], 0, 100)); ?>
                                            <?php if (strlen($query['query_sql']) > 100): ?>...<?php endif; ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="instapulse-time <?php echo $query['execution_time'] > 200 ? 'slow' : ($query['execution_time'] > 100 ? 'medium' : 'fast'); ?>">
                                            <?php echo number_format($query['execution_time'], 2); ?>ms
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo esc_html($query['caller'] ?: 'Unknown'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo esc_html(substr($query['request_uri'] ?: $query['profile_request_uri'] ?: '', 0, 30)); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo esc_html($query['timestamp']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($slow_query_stats['frequent_queries'])): ?>
                    <h3>Most Frequent Slow Queries</h3>
                    <table class="instapulse-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Query Pattern</th>
                                <th>Frequency</th>
                                <th>Avg Time</th>
                                <th>Max Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slow_query_stats['frequent_queries'] as $frequent_query): ?>
                                <tr>
                                    <td>
                                        <code style="font-size: 11px; word-break: break-all;">
                                            <?php echo esc_html($frequent_query['query_preview']); ?>...
                                        </code>
                                    </td>
                                    <td><?php echo number_format($frequent_query['frequency']); ?></td>
                                    <td><?php echo number_format($frequent_query['avg_time'], 2); ?>ms</td>
                                    <td><?php echo number_format($frequent_query['max_time'], 2); ?>ms</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No slow queries detected yet. Queries slower than the configured threshold will appear here.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>


        <div class="instapulse-section">
            <h2>Settings</h2>
            <div class="instapulse-card">
                <form method="post" action="options.php" id="instapulse-settings-form">
                    <?php
                    settings_fields('instapulse_settings');
                    $options = get_option('instapulse_options', array());
                    $current_sample_rate = isset($options['sample_rate']) ? (int) $options['sample_rate'] : 2;
                    $current_slow_query_threshold = isset($options['slow_query_threshold']) ? (int) $options['slow_query_threshold'] : 50;
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sample_rate">Sample Rate (%)</label>
                            </th>
                            <td>
                                <input type="number" id="sample_rate" name="instapulse_options[sample_rate]"
                                       value="<?php echo esc_attr($current_sample_rate); ?>"
                                       min="1" max="100" step="1" class="small-text" />
                                <p class="description">
                                    Percentage of requests to profile (1-100%). Lower values reduce server load but provide less data.
                                    Current: <?php echo $current_sample_rate; ?>% - profiles approximately <?php echo $current_sample_rate; ?> out of every 100 requests.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="slow_query_threshold">Slow Query Threshold (ms)</label>
                            </th>
                            <td>
                                <input type="number" id="slow_query_threshold" name="instapulse_options[slow_query_threshold]"
                                       value="<?php echo esc_attr($current_slow_query_threshold); ?>"
                                       min="10" max="1000" step="1" class="small-text" />
                                <p class="description">
                                    Database queries slower than this threshold will be logged (10-1000ms).
                                    Current: <?php echo $current_slow_query_threshold; ?>ms - queries taking longer than this will be tracked.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                </form>
            </div>
        </div>

        <div class="instapulse-actions">
            <button type="button" class="button button-secondary" onclick="location.reload();">Refresh Data</button>
            <button type="button" class="button button-secondary" id="instapulse-clear-data">Clear All Data</button>
        </div>
    </div>
</div>