# Common Logger - WordPress Plugin

## Overview

**Common Logger** is a comprehensive logging utility plugin for WordPress that provides advanced logging capabilities with dual storage modes (database and file), real-time monitoring, and an intuitive admin interface. It captures hook execution, PHP errors, exceptions, slow queries, and custom events to help developers and administrators debug and monitor their WordPress installations effectively.

## Purpose & Working Flow

### Core Purpose
The plugin serves as a centralized logging solution that:
- **Captures** all types of WordPress events and errors
- **Provides** flexible storage options (database for performance, file for simplicity)
- **Offers** advanced filtering and search capabilities
- **Prevents** self-logging to avoid recursive loops
- **Maintains** clean, searchable log interfaces

### Working Flow

#### 1. **Initialization & Setup**
```
Plugin Activation → Create Storage (Database Table/File Directory) → Set Default Options
```

#### 2. **Logging Process**
```
Event Occurs → Check Self-Logging Filter → Detect Origin Metadata → Format & Store → Optional Debug Output
```

#### 3. **Admin Interface**
```
Settings Page: Configure Storage Mode, Logging Options
Tools Page: View/Search/Filter Logs (Database Mode Only)
```

#### 4. **Storage Modes**
- **Database Mode**: Structured storage with advanced querying, pagination, filtering
- **File Mode**: Simple file-based storage, direct download capability

## Features

### 🔧 Core Features
- **Dual Storage Modes**: Database tables or flat files
- **Multiple Log Levels**: ERROR, WARNING, NOTICE, INFO, DEBUG
- **Origin Detection**: Automatically identifies plugin/file source
- **Context Preservation**: Stores structured data with each log entry
- **Self-Protection**: Prevents logging its own operations

### 📊 Monitoring Capabilities
- **Hook Tracing**: Monitor WordPress action/filter execution
- **PHP Error Capture**: Log warnings, notices, fatal errors
- **Slow Query Detection**: Identify performance bottlenecks
- **Exception Handling**: Capture uncaught exceptions

### 🎨 User Interface
- **Color-Coded Levels**: Visual indicators for log severity
- **Advanced Filtering**: By level, plugin, search terms
- **Pagination Support**: Efficient browsing of large log sets
- **Modal Details**: Expandable context information
- **Responsive Design**: Works on all screen sizes

## Installation & Setup

### Requirements
- **WordPress**: 5.0+
- **PHP**: 7.2+
- **MySQL**: 5.6+ (for database storage)

### Installation Steps
1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Activate through WordPress admin
4. Configure settings under **Settings > Common Logger**

### File Structure
```
common-logger/
├── common-logger.php           # Main plugin file
├── assets/
│   ├── css/
│   │   └── common-logger-admin.css
│   └── js/
│       └── common-logger-admin.js
├── includes/
│   ├── class-common-logger.php      # Core logger class
│   └── common-logger-functions.php  # Utility functions
├── admin/
│   └── class-common-logger-admin.php # Admin interface
└── languages/                       # Translation files
```

## Configuration Options

### Storage Mode
- **File**: Simple text file storage in uploads directory
- **Database**: Structured table storage with advanced features

### Logging Options
- **Error Only Mode**: Capture only errors/exceptions
- **Hook Tracing**: Monitor WordPress hook execution
- **PHP Errors**: Capture PHP warnings and notices
- **Slow Queries**: Detect queries exceeding threshold

## Usage Examples

### Basic Logging
```php
// Simple message
common_logger()->info('User logged in');

// With context
common_logger()->error('Payment failed', array(
    'user_id' => 123,
    'amount' => 99.99,
    'gateway' => 'stripe'
));

// Convenience methods
common_logger()->warning('Deprecated function used');
common_logger()->debug('Processing started');
```

### Programmatic Log Retrieval
```php
// Get recent logs
$logs = common_logger()->get_logs(array(
    'limit' => 50,
    'level' => 'ERROR'
));

// Search logs
$search_results = common_logger()->get_logs(array(
    'search' => 'payment',
    'plugin' => 'woocommerce'
));
```

## Admin Interface Guide

### Settings Page
Located at **Settings > Common Logger**

#### Storage Configuration
- Choose between file or database storage
- File mode shows log file path and download link
- Database mode enables advanced viewer features

#### Logging Options
- **Error Only Mode**: Toggle between comprehensive and error-only logging
- **Hook Tracing**: Enable/disable WordPress hook monitoring
- **PHP Error Capture**: Log PHP warnings and notices
- **Slow Query Detection**: Monitor database performance

### Tools Page (Database Mode Only)
Located at **Tools > Common Logger**

#### Log Viewer Features
- **Real-time Display**: Latest logs appear first
- **Level Filtering**: Filter by ERROR, WARNING, NOTICE, INFO, DEBUG
- **Plugin Filtering**: Filter by originating plugin
- **Search Functionality**: Search messages, files, and context
- **Pagination**: Navigate through large log sets

#### Visual Indicators
- **Color-coded levels**: Red (ERROR), Orange (WARNING), Blue (NOTICE), Green (INFO), Gray (DEBUG)
- **Icons**: Dashicons for quick visual recognition
- **Expandable details**: Click "View Details" for full context

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

### Data Retrieval Methods

#### `get_logs($args)`
Retrieve logs with filtering and pagination.

#### `get_logs_count($args)`
Get total count of logs matching filters.

#### `clear_logs()`
Clear all logs based on current storage mode.

## Development Guidelines

### Plugin Development Instructions

#### ⚠️ Critical Rules
- **Update .md files after every change**
- **Test thoroughly before deployment**
- **Never break site functionality**
- **Prevent self-logging at all costs**

#### 🔧 Development Workflow
1. **Make Changes**: Implement features or fixes
2. **Test Extensively**: Use custom test scripts
3. **Update Documentation**: Modify README.md with changes
4. **Verify No Breaks**: Run site functionality tests
5. **Delete Test Files**: Remove temporary test scripts

#### 🧪 Testing Protocol
```bash
# Create test script
php test-script.php

# Verify functionality
# Check admin interface
# Test both storage modes
# Confirm no self-logging

# Remove test script
rm test-script.php
```

#### 📝 Documentation Updates
- Document all new features
- Update configuration options
- Add usage examples
- Maintain API reference
- Include troubleshooting tips

### Hook Integration

The plugin integrates with WordPress hooks:

#### Initialization
```php
add_action('plugins_loaded', 'common_logger_init');
```

#### Admin Setup
```php
add_action('admin_menu', array($admin, 'register_admin_pages'));
add_action('admin_enqueue_scripts', array($admin, 'enqueue_admin_assets'));
```

#### Error Handling
```php
add_action('shutdown', 'common_logger_handle_slow_queries', 99);
```

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

## Security Considerations

- **Nonce Protection**: All admin actions use WordPress nonces
- **Capability Checks**: Admin access requires `manage_options`
- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Prevention**: Prepared statements used throughout
- **File Path Security**: Uploads directory used for file storage

## Performance Optimization

### Database Mode
- Indexed columns for fast queries
- Pagination prevents memory exhaustion
- Efficient filtering with WHERE clauses

### File Mode
- Append-only writing for speed
- Lazy loading of log entries
- File size monitoring

### Memory Management
- Limited hook tracing to prevent loops
- Context data size limits
- Garbage collection integration

## Contributing

### Code Standards
- Follow WordPress Coding Standards
- Use PHPDoc for documentation
- Implement proper error handling
- Maintain backward compatibility

### Feature Requests
- Open GitHub issues for new features
- Provide detailed use cases
- Include implementation suggestions

## Changelog

### Version 1.0.0
- Initial release
- Dual storage modes (file/database)
- Complete admin interface
- Hook tracing and error monitoring
- Advanced filtering and search
- Color-coded log levels
- Self-logging prevention

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Your Name

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Support

For support, bug reports, or feature requests:
- Create GitHub issues
- Check the troubleshooting section
- Review the API documentation
- Test in staging environment first

---

**Remember**: Always update this README.md after any changes to the plugin code or functionality.
