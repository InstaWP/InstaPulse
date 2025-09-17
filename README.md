# InstaPulse - WordPress Performance Monitor

InstaPulse is a lightweight WordPress plugin that provides accurate application performance monitoring by tracking individual plugin load times, database query performance, and frontend request metrics. It uses advanced profiling techniques to measure performance without significantly impacting your site's speed.

## Features

### 🚀 **Plugin Performance Monitoring**
- Track individual plugin load times and memory usage
- Monitor theme performance separately from plugins
- Confidence levels based on statistical significance
- Memory usage tracking for each component

### 🗄️ **Database Query Analysis**
- Monitor slow database queries in real-time
- Configurable slow query threshold (10-1000ms)
- Track query frequency and execution patterns
- Identify performance bottlenecks at the database level

### 📊 **Request Performance Tracking**
- Frontend request monitoring with intelligent sampling
- Configurable sampling rate (1-100%) to minimize overhead
- Request categorization and filtering
- Load time and memory usage analytics

### 🎨 **CSS/JS Asset Monitoring**
- Track all CSS and JavaScript files loaded on each request
- Source attribution (Plugin, Theme, or WordPress Core)
- File size analysis and dependency tracking
- Asset optimization recommendations
- Clickable asset URLs for easy inspection

### 🎯 **Smart Sampling**
- Lightweight profiling with configurable sample rates
- Only profiles frontend requests (excludes admin, AJAX, cron)
- Minimal performance impact through intelligent filtering
- Statistical confidence indicators for data reliability

## Installation

1. Upload the `instapulse` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the WordPress admin panel
3. Navigate to **Tools > InstaPulse** to view your performance data
4. Configure sampling rate and slow query threshold in the Settings section

## Usage

### Viewing Performance Data

1. **Performance Summary**: Overview dashboard with key metrics and performance score
2. **Plugin Load Times**: See which plugins are consuming the most resources
3. **CSS/JS Assets**: Monitor asset loading patterns and identify optimization opportunities
4. **Slow Queries**: Identify database queries that need optimization
5. **Request Performance**: Monitor overall site performance trends
6. **Settings**: Adjust sampling rate and query thresholds

### Configuration Options

- **Sample Rate**: Percentage of requests to profile (default: 2%)
- **Slow Query Threshold**: Minimum execution time to flag queries (default: 50ms)

### Data Management

- **Clear Data**: Remove all collected performance data
- **Automatic Cleanup**: Old data is automatically cleaned up after 30 days
- **Export**: Performance data can be exported for external analysis

## Technical Details

### Architecture

InstaPulse uses a Must-Use (MU) plugin approach for accurate measurements:

- **MU Plugin**: Loads before all other plugins for precise timing
- **Stream Wrapper**: Intercepts file operations to track plugin loads
- **Database Profiling**: Monitors query execution times
- **Sampling Logic**: Reduces overhead through intelligent request selection

### Database Tables

- `wp_instapulse_profiles`: Stores plugin performance and request metadata
- `wp_instapulse_queries`: Stores slow query information
- `wp_instapulse_assets`: Stores CSS/JS asset tracking data

### Performance Impact

- **Minimal Overhead**: Default 2% sampling rate
- **No Debug Logging**: Optimized for production use
- **Smart Filtering**: Excludes admin, AJAX, and static asset requests
- **Efficient Storage**: Compressed data storage with automatic cleanup

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Roadmap

- Page-specific performance tracking (individual URLs)
- Admin / Cron / CLI performance tracking
- Core Web Vitals integration (LCP, FID, CLS)
- Template rendering times
- WordPress phases, hooks and filters performance tracking

## Frequently Asked Questions

**Q: Will this slow down my website?**
A: InstaPulse is designed to have minimal impact. With the default 2% sampling rate, only 2 out of every 100 frontend requests are profiled.

**Q: What types of requests are monitored?**
A: Only frontend page views are monitored. Admin pages, AJAX requests, REST API calls, cron jobs, and static assets are excluded.

**Q: How accurate is the data?**
A: InstaPulse provides confidence levels based on the number of samples collected. More samples = higher confidence in the data accuracy.

**Q: Can I export the performance data?**
A: Yes, performance data can be exported from the dashboard for external analysis.

## Support

For technical support and documentation, please visit our [support portal](https://support.instawp.com).

## License

This plugin is licensed under the GPLv2 (or later) license.

**Enterprise Licensing**: If you wish to use this plugin for enterprise applications on production websites with additional features and support, please contact [sales@instawp.com](mailto:sales@instawp.com).

## Changelog

### Version 1.2.0
- **Added**: CSS/JS Asset Tracking - Monitor all stylesheets and scripts
- **Added**: Asset Source Attribution - Identify if assets come from plugins, themes, or core
- **Added**: Asset Size Analysis - Track file sizes and identify optimization opportunities
- **Added**: Performance Summary Dashboard - Comprehensive overview with performance scoring
- **Added**: Clickable Asset URLs - Direct links to asset files in dashboard
- **Added**: Enhanced Database Architecture - New assets table and improved request metadata
- **Improved**: MySQL Compatibility - Better support for older MySQL versions
- **Improved**: UI Design - Streamlined dashboard with better visual hierarchy
- **Fixed**: Database query optimization for better performance

### Version 1.1.0
- **Added**: Slow database query monitoring
- **Added**: Configurable slow query threshold setting
- **Added**: Query frequency and pattern analysis
- **Added**: Enhanced dashboard with query performance data
- **Improved**: Sample rate display accuracy
- **Improved**: Database table creation and upgrade process
- **Fixed**: Clear data function now includes slow query data
- **Fixed**: Proper cleanup on plugin deletion

### Version 1.0.0
- **Initial Release**: Plugin performance monitoring
- **Added**: MU plugin architecture for accurate timing
- **Added**: Stream wrapper for file operation tracking
- **Added**: Configurable sampling rates
- **Added**: Memory usage tracking
- **Added**: Admin dashboard with performance insights
- **Added**: Confidence level calculations
- **Added**: Automatic data cleanup

---

**Developed by [InstaWP](https://instawp.com)** - Making WordPress development faster and more efficient.