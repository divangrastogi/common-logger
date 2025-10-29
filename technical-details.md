# Common Logger Plugin - Technical Details

## Overview

The Common Logger plugin is a comprehensive WordPress logging utility designed to capture, monitor, and analyze various events, errors, and performance metrics within a WordPress installation.

This enhanced version integrates AI-powered error insight features, allowing developers to automatically detect the exact plugin, theme, file, hook, and function chain responsible for PHP/WordPress errors. It combines logging, monitoring, and debugging intelligence in one tool.

## Plugin Architecture

### Core Components

1. **Main Plugin File** (`common-logger.php`)
   - Plugin initialization and activation hooks
   - Includes core classes and functions
   - Sets up WordPress hooks for plugin lifecycle
   - Integrates AI-based error detection and advanced logging

2. **Core Logger Class** (`includes/class-common-logger.php`)
   - Singleton pattern implementation
   - Handles all logging operations
   - Manages storage modes and data retrieval
   - Captures and analyzes PHP errors and exceptions

3. **Admin Interface** (`admin/class-common-logger-admin.php`)
   - Settings page for configuration
   - Tools page for log viewing (database mode only)
   - Asset enqueuing and form handling
   - Visual insights dashboard: error counts, hooks, plugin sources

4. **Performance Monitor** (`includes/class-common-logger-monitor.php`)
   - Hook tracing functionality
   - PHP error and exception capture
   - Slow query detection
   - Function chain reconstruction for each error

5. **CLI Support** (`includes/class-common-logger-cli.php`)
   - WP-CLI commands for log management
   - Bulk operations, export, and top error reports

6. **Utility & Integration Functions** (`includes/common-logger-functions.php`)
   - Helper functions for origin detection
   - Context processing utilities
   - Optional integration with open-source tools: Whoops, Symfony VarDumper, Monolog, Xdebug

## Plugin Flow

### 1. Initialization Phase
```
WordPress Load → plugins_loaded Hook → common_logger_init()
    ↓
Load Text Domain → Initialize Admin Class → Bootstrap Monitor
    ↓
Register Activation/Deactivation Hooks → Setup Default Options
    ↓
Initialize AI Error Detection & Tool Integrations
```

### 2. Logging & Error Analysis Flow
```
Event Occurs (Hook, Error, Exception, Manual Log)
    ↓
Check Self-Logging Filter (prevent recursive logging)
    ↓
Detect Origin Metadata (plugin, theme, file, hook)
    ↓
Build Function Chain via debug_backtrace()
    ↓
Format Log Entry (timestamp, level, message, context, trace)
    ↓
Route to Storage Mode (Database or File)
    ↓
Optional Debug Output (Whoops / VarDumper)
    ↓
Fire Custom Hooks & Filters (pre_log, post_log)
```

### 3. Admin Interface Flow
```
Admin Page Load → Enqueue Assets (CSS/JS)
    ↓
Register Settings → Render Page Content
    ↓
Render Enhanced Developer Dashboard
    ↓
Handle Form Submissions → Sanitize Input
    ↓
Update Options → Redirect with Feedback
```

### 4. Data Retrieval Flow
```
Log Request (with filters: level, plugin, theme, hook, search, limit)
    ↓
Determine Storage Mode
    ↓
Query Storage (Database: SQL with WHERE clauses / File: Parse log file)
    ↓
Apply Filters (level, plugin, theme, hook, search)
    ↓
Normalize Entries → Return Formatted Results / REST API / WP-CLI
```

### 2. Logging Process Flow

```
Event Occurs (Hook, Error, Exception, Manual Log)
    ↓
Check Self-Logging Filter (prevent recursive logging)
    ↓
Detect Origin Metadata (plugin/file detection)
    ↓
Format Log Entry (timestamp, level, message, context)
    ↓
Route to Storage Mode (Database or File)
    ↓
Optional Debug Output (if WP_DEBUG enabled)
```

### 3. Admin Interface Flow

```
Admin Page Load → Enqueue Assets (CSS/JS)
    ↓
Register Settings → Render Page Content
    ↓
Handle Form Submissions → Sanitize Input
    ↓
Update Options → Redirect with Feedback
```

### 4. Data Retrieval Flow

```
Log Request (with filters: level, plugin, search, limit)
    ↓
Determine Storage Mode
    ↓
Query Storage (Database: SQL with WHERE clauses / File: Parse log file)
    ↓
Apply Filters (level, plugin, search)
    ↓
Normalize Entries → Return Formatted Results
```

## Storage Modes

### Database Mode
- **Table**: `wp_common_logger_logs`
- **Columns**:
  - `id` (Primary Key, Auto Increment)
  - `timestamp` (DATETIME)
  - `level` (VARCHAR(10))
  - `message` (TEXT)
  - `context` (LONGTEXT - JSON encoded)
  - `plugin` (VARCHAR)
  - `theme` (VARCHAR)
  - `file` (TEXT)
  - `line` (INT)
  - `hook` (VARCHAR)
  - `function_chain` (LONGTEXT JSON)
- **Indexes**: timestamp, level, plugin for efficient querying
- **Advantages**: Advanced filtering, pagination, search capabilities

### File Mode
- **Location**: `wp-content/uploads/common-logger-logs/common.log`
- **Format**: JSON lines with enhanced context
- **Structure**:
  ```json
  {
    "timestamp": "2025-10-09 12:00:00",
    "level": "ERROR",
    "message": "Undefined variable in plugin",
    "plugin": "woocommerce",
    "theme": "twentytwentyfive",
    "file": "/wp-content/plugins/woocommerce/includes/class-wc-cart.php",
    "line": 512,
    "hook": "woocommerce_before_cart",
    "function_chain": ["do_action", "update_cart_totals", "apply_filters"],
    "context": {"user_id": 123, "_origin_file": "/includes/class-wc-cart.php"}
  }
  ```
- **Advantages**: Simple, no database overhead, direct file access

## Admin Dashboard Enhancements

- **Error Table**: Filter by level, plugin, theme, hook
- **Function Chain Viewer**: Expandable for detailed debugging
- **Error Insights**: Counts, trends, and recurring issues per plugin/theme
- **Export Options**: JSON, CSV
- **Mode Switch**: Safe Mode / Developer Mode / Silent Mode
- **Notifications**: Optional thresholds for recurring errors

## AI & Developer-Friendly Features

- **REST API Endpoint**: `/wp-json/common-logger/v1/report`
- **WP-CLI Command**: `wp common-logger report --top=10`
- **Optional Integrations**: Whoops, Symfony VarDumper, Monolog, Xdebug
- **Structured Logs**: Function chain, hook, plugin, theme, file, line
- **Grouping & Insights**: Aggregate errors by plugin/theme for faster debugging
- **Filter Hooks**: `common_logger_pre_log`, `common_logger_after_log`, `common_logger_export_format`

## Hook Integration

### WordPress Hooks Used

#### Initialization
- `plugins_loaded` - Initialize plugin components
- `admin_enqueue_scripts` - Load admin assets
- `admin_init` - Register settings
- `admin_menu` - Add admin pages

#### Activation/Deactivation
- `register_activation_hook` - Setup on activation
- `register_deactivation_hook` - Cleanup on deactivation

#### Error/Exception Handling
- `shutdown` - Capture slow queries and final errors
- Custom error handlers for PHP errors and exceptions

#### Filtering
- `common_logger_should_log` - Prevent self-logging

### Custom Hooks Provided

#### Actions
- `common_logger_log_entry` - Fired after each log entry
- `common_logger_settings_updated` - After settings save

#### Filters
- `common_logger_should_log` - Control whether to log an entry
- `common_logger_log_context` - Modify log context data
- `common_logger_log_message` - Modify log message

## Configuration Options

### Storage Settings
- `common_logger_storage_mode` - 'file' or 'database'
- `common_logger_db_version` - Database schema version

### Logging Options
- `common_logger_error_only_mode` - Boolean, log only errors
- `common_logger_hook_tracing_enabled` - Boolean, enable hook monitoring
- `common_logger_hook_prefix` - String, filter hooks by prefix
- `common_logger_php_error_enabled` - Boolean, capture PHP errors
- `common_logger_slow_query_enabled` - Boolean, detect slow queries
- `common_logger_slow_query_threshold` - Float, threshold in seconds

## Data Structures

### Log Entry Format
```php
array(
    'id' => 123,                    // Database ID (database mode only)
    'timestamp' => '2024-01-01 12:00:00',
    'level' => 'INFO',              // ERROR, WARNING, NOTICE, INFO, DEBUG
    'message' => 'Log message text',
    'context' => array(             // Additional structured data
        'user_id' => 123,
        '_origin_plugin' => 'woocommerce',
        '_origin_file' => '/path/to/file.php'
    ),
    'origin_plugin' => 'woocommerce', // Extracted from context
    'origin_file' => '/path/to/file.php' // Extracted from context
)
```

### Context Data Structure
- `_origin_plugin` - Source plugin slug
- `_origin_file` - Source file path
- Custom fields - Any additional data passed to log methods

## Security Measures

### Input Sanitization
- All user inputs sanitized using WordPress functions
- Prepared statements for database queries
- Nonce verification for admin actions

### Access Control
- Admin pages require `manage_options` capability
- File operations restricted to uploads directory
- Self-logging prevention to avoid infinite loops

### Data Protection
- No sensitive data logged by default
- Context data filtered for security
- File permissions set appropriately
- Sanitization and escaping for all admin outputs
- Nonces and current_user_can() checks

## Performance Considerations

### Database Mode
- Indexed columns for fast queries
- Pagination prevents memory exhaustion
- Efficient WHERE clauses for filtering

### File Mode
- Append-only writing for speed
- Lazy loading of log entries
- File size monitoring

### Memory Management
- Limited hook tracing to prevent loops
- Context data size limits
- Garbage collection integration
- Async logging for shutdown errors
- Deduplication for repeated errors
- Optional retention policies

## API Reference

### Core Methods

#### `common_logger()`
Returns the singleton logger instance.

#### `log($message, $level, $context)`
Log a message with specified level and context.

#### `info($message, $context)`
Convenience method for INFO level logging.

#### `warning($message, $context)`
Convenience method for WARNING level logging.

#### `error($message, $context)`
Convenience method for ERROR level logging.

#### `debug($message, $context)`
Convenience method for DEBUG level logging.

### Configuration Methods

#### `get_storage_mode()`
Returns current storage mode ('file' or 'database').

#### `is_error_only_mode()`
Returns true if error-only mode is enabled.

#### `is_hook_tracing_enabled()`
Returns true if hook tracing is enabled.

#### `get_hook_prefix()`
Returns configured hook prefix.

#### `is_php_error_logging_enabled()`
Returns true if PHP error logging is enabled.

#### `is_slow_query_logging_enabled()`
Returns true if slow query logging is enabled.

#### `get_slow_query_threshold()`
Returns slow query threshold in seconds.

### Data Retrieval Methods

#### `get_logs($args)`
Retrieve logs with filtering and pagination.

**Parameters:**
- `limit` (int) - Number of entries to return
- `level` (string) - Filter by log level
- `plugin` (string) - Filter by origin plugin
- `search` (string) - Search in message and context
- `offset` (int) - Pagination offset

#### `get_logs_count($args)`
Get total count of logs matching filters.

#### `clear_logs()`
Clear all logs based on current storage mode.

## Error Handling

### PHP Error Capture
- Registers custom error handler
- Converts PHP errors to log entries
- Respects error reporting levels

### Exception Handling
- Registers custom exception handler
- Captures uncaught exceptions
- Includes stack traces in context

### Database Errors
- Graceful fallback to file mode if database unavailable
- Error logging for storage failures

## CLI Commands

### Available Commands
- `wp common-logger list` - List log entries
- `wp common-logger clear` - Clear all logs
- `wp common-logger purge` - Purge logs by criteria
- `wp common-logger export` - Export logs to file
- `wp common-logger tail` - Monitor logs in real-time
- `wp common-logger settings` - View/modify settings

## File Structure

```
common-logger/
├── common-logger.php              # Main plugin file
├── technical-details.md           # This file
├── README.md                      # User documentation
├── CHANGELOG.md                   # Version history
├── admin/
│   └── class-common-logger-admin.php
├── includes/
│   ├── class-common-logger.php    # Core logger
│   ├── class-common-logger-monitor.php
│   ├── class-common-logger-cli.php
│   └── common-logger-functions.php
├── assets/
│   ├── css/
│   │   └── common-logger-admin.css
│   └── js/
│       └── common-logger-admin.js
└── languages/                     # Translation files
```

## Dependencies

### WordPress Core
- WordPress 5.0+
- PHP 7.2+
- MySQL 5.6+ (for database mode)

### WordPress Functions Used
- `wp_upload_dir()` - File storage location
- `wp_mkdir_p()` - Directory creation
- `wp_parse_args()` - Argument parsing
- `wp_json_encode()` - JSON encoding
- `current_time()` - Timestamp generation

## Scope and Limitations

### Included Features
- Dual storage modes (database/file)
- Multiple log levels (ERROR, WARNING, NOTICE, INFO, DEBUG)
- Origin detection (plugin/file)
- Advanced filtering and search
- Admin interface with settings and tools
- CLI support for bulk operations
- Performance monitoring (hooks, slow queries)
- PHP error and exception capture

### Out of Scope
- Remote logging to external services
- Log rotation and archiving
- Real-time notifications
- Integration with external monitoring tools
- Custom log formatters
- Multi-site network logging

## Future Enhancements

### Potential Features
- AI-based suggestions for fixes
- Integration with Slack/Webhooks
- Plugin/theme version tracking
- Real-time monitoring dashboard
- Advanced analytics and reporting
- Log rotation and retention policies
- Email/Slack notifications for critical errors
- Integration with external logging services
- Custom log levels and categories
- Bulk import/export functionality

### Technical Improvements
- Asynchronous logging for performance
- Compressed storage for large logs
- Caching layer for frequent queries
- REST API endpoints for external access

## Deliverables for AI / Developer

- Full plugin code: core classes, admin, CLI, REST API, integrations
- Database table creation and migration scripts
- Enhanced error detection and function chain analysis
- Admin dashboard with visualization
- Export, REST, and WP-CLI reporting
- Optional integration with Whoops, VarDumper, Monolog, Xdebug
- WPCS-compliant, secure, and modular code

## Troubleshooting

### Common Issues

#### Self-Logging Prevention
The plugin automatically prevents logging its own operations by:
- Checking origin file paths for 'common-logger'
- Filtering context data for plugin identification
- Using the `common_logger_should_log` filter

#### Storage Mode Switching
- Switch modes in settings
- Existing logs remain in old storage
- New logs use selected storage mode

#### Performance Considerations
- Database mode: Better for frequent logging
- File mode: Better for low-volume logging
- Hook tracing: Limit with prefixes for performance

### Debug Information

#### Log File Location
```
wp-content/uploads/common-logger-logs/common.log
```

#### Database Table
```
wp_common_logger_logs
```

#### Configuration Options
All settings stored in `wp_options` with `common_logger_` prefix.

---

**Version**: 1.0.0 (Enhanced with AI Error Insights)
**Last Updated**: October 2025
**Author**: Divang Rastogi

**Note**: This enhanced version transforms Common Logger into a complete developer diagnostic platform, combining logging, monitoring, and debugging intelligence with AI-powered error detection and function chain analysis.