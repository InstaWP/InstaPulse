=== InstaPulse ===
Contributors: InstaWP
Tags: performance, monitoring, apm, profiling, optimization
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WordPress APM tool that tracks plugin load times, database queries, and frontend request performance.

== Description ==

InstaPulse is a comprehensive WordPress performance monitoring plugin that provides accurate application performance monitoring by tracking individual plugin load times, database query performance, and frontend request metrics. It uses advanced profiling techniques to measure performance without significantly impacting your site's speed.

= Key Features =

* **Plugin Performance Monitoring** - Track individual plugin load times and memory usage
* **Database Query Analysis** - Monitor slow database queries with configurable thresholds
* **CSS/JS Asset Tracking** - Monitor all stylesheets and scripts with source attribution
* **Request Performance Tracking** - Frontend request monitoring with intelligent sampling
* **Smart Sampling** - Configurable sampling rates (1-100%) to minimize overhead
* **Performance Insights** - Get actionable recommendations for optimization

= Technical Features =

* Must-Use (MU) plugin architecture for accurate timing measurements
* Stream wrapper for precise file operation tracking
* Configurable slow query threshold (10-1000ms)
* Asset source attribution (Plugin, Theme, or WordPress Core)
* GDPR compliant (no IP address logging)
* MySQL compatibility with timestamp-based filtering
* Automatic data cleanup after 30 days

== Installation ==

1. Upload the `instapulse` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Navigate to **Tools > InstaPulse** to view your performance data
4. Configure sampling rate and slow query threshold in the Settings section

== Frequently Asked Questions ==

= Will this slow down my website? =

InstaPulse is designed to have minimal impact. With the default 2% sampling rate, only 2 out of every 100 frontend requests are profiled.

= What types of requests are monitored? =

Only frontend page views are monitored. Admin pages, AJAX requests, REST API calls, cron jobs, and static assets are excluded.

= How accurate is the data? =

InstaPulse uses a Must-Use (MU) plugin architecture that loads before all other plugins, ensuring precise timing measurements.

= Can I export the performance data? =

Yes, performance data can be exported from the dashboard for external analysis.

== Screenshots ==

1. Performance Summary dashboard with key metrics
2. Plugin Load Times analysis
3. CSS/JS Asset tracking with source attribution
4. Slow database queries monitoring
5. Recent frontend requests overview

== Changelog ==

= 1.3.0 =
* Added: GitHub Auto-Updates - Integrated Plugin Update Checker for automatic updates from GitHub releases
* Improved: Stream Wrapper Timing Accuracy - Lowered detection threshold from 0.5ms to 0.1ms for more precise measurements
* Fixed: Memory Usage Accumulation - Corrected plugin-level memory tracking to prevent over-inflation
* Enhanced: File Operation Tracking - Better accuracy in capturing small file operations that were previously missed
* Optimized: Performance Measurement Logic - Simplified timing approach while maintaining accuracy
* Updated: MU Plugin Architecture - Enhanced timing precision without micro-level complexity

= 1.2.0 =
* Added: CSS/JS Asset Tracking - Monitor all stylesheets and scripts
* Added: Asset Source Attribution - Identify if assets come from plugins, themes, or core
* Added: Asset Size Analysis - Track file sizes and identify optimization opportunities
* Added: Performance Summary Dashboard - Comprehensive overview with performance scoring
* Added: Clickable Asset URLs - Direct links to asset files in dashboard
* Added: Enhanced Database Architecture - New assets table and improved request metadata
* Improved: MySQL Compatibility - Better support for older MySQL versions
* Improved: UI Design - Streamlined dashboard with better visual hierarchy
* Improved: GDPR Compliance - Removed IP address logging
* Fixed: Database query optimization for better performance

= 1.1.0 =
* Added: Slow database query monitoring
* Added: Configurable slow query threshold setting
* Added: Query frequency and pattern analysis
* Added: Enhanced dashboard with query performance data
* Improved: Sample rate display accuracy
* Improved: Database table creation and upgrade process
* Fixed: Clear data function now includes slow query data
* Fixed: Proper cleanup on plugin deletion

= 1.0.0 =
* Initial Release: Plugin performance monitoring
* Added: MU plugin architecture for accurate timing
* Added: Stream wrapper for file operation tracking
* Added: Configurable sampling rates
* Added: Memory usage tracking
* Added: Admin dashboard with performance insights
* Added: Confidence level calculations
* Added: Automatic data cleanup

== Upgrade Notice ==

= 1.3.0 =
Performance improvement update with enhanced timing accuracy, corrected memory tracking, and GitHub auto-updates. Automatic upgrade - no manual intervention required.

= 1.2.0 =
Major update with new CSS/JS asset tracking, performance dashboard, and GDPR compliance improvements. Automatic database migration included.

= 1.1.0 =
New slow query monitoring and enhanced dashboard. Database will be automatically upgraded on activation.

== Privacy Policy ==

InstaPulse is designed with privacy in mind:

* No IP addresses are logged (GDPR compliant)
* Only anonymous performance metrics are stored
* No personal data is transmitted to external servers
* All data remains on your WordPress installation
* Automatic data cleanup after 30 days

== Support ==

For technical support and documentation, please visit our [support portal](https://support.instawp.com).