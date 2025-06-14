# Propaganda Annotations Fix Documentation

## Overview

This document describes the fixes implemented for propaganda annotation display issues and the addition of language switching functionality to the system.

## Issues Addressed

### 1. Incorrect Annotation Positions
- **Problem**: AI models returned propaganda annotations with incorrect start/stop positions
- **Solution**: Created `FixAnnotationPositionsCommand` to correct position calculations in the database
- **Command**: `php artisan fix:annotation-positions`

### 2. "All Models" Functionality Clarification
- **Problem**: The "All models" view was unclear and worked inconsistently
- **Solution**: Enhanced display with visual indicators and explanations
- **Features**:
  - Color-coded consensus indicators (green for consensus, blue for partial agreement, yellow for single model)
  - Detailed explanations of how combined model view works
  - Agreement statistics and visual borders

### 3. Language Switching System
- **Problem**: No language switching capability between Lithuanian and English
- **Solution**: Implemented comprehensive language system with user-based persistence
- **Features**:
  - Per-user language preferences stored in database
  - Complete translations for all interface elements
  - Language switcher available in all views including Mission Control
  - Automatic locale detection for authenticated users

## Technical Implementation

### Database Changes

#### User Language Preference Migration
```sql
-- Migration: 2025_06_14_164956_add_language_preference_to_users_table.php
ALTER TABLE users ADD COLUMN language VARCHAR(2) DEFAULT 'lt' AFTER is_active;
ALTER TABLE users ADD INDEX language_index (language);
```

### New Artisan Command

#### FixAnnotationPositionsCommand
- **File**: `app/Console/Commands/FixAnnotationPositionsCommand.php`
- **Purpose**: Fix incorrect UTF-8 character position calculations in annotations
- **Usage**: 
  ```bash
  php artisan fix:annotation-positions [--dry-run] [--model=MODEL] [--text-id=ID]
  ```

### Language System Components

#### User Model Extensions
- **File**: `app/Models/User.php`
- **Methods**: 
  - `getLanguage()`: Get user's preferred language
  - `setLanguage($language)`: Set user's language preference

#### Language Middleware
- **File**: `app/Http/Middleware/SetLanguage.php`
- **Function**: Automatically set locale based on user preference or session

#### Language Controller
- **File**: `app/Http/Controllers/LanguageController.php`
- **Route**: `/language/{language}`
- **Function**: Handle language switching requests

### Translation Files

#### Lithuanian (`resources/lang/lt/messages.php`)
- Complete translation of all interface elements
- Dashboard statistics and labels
- Navigation and action buttons
- Analysis interface terminology

#### English (`resources/lang/en/messages.php`)
- Full English translations
- Technical terminology
- User interface elements
- Help text and explanations

### Enhanced "All Models" View

#### Visual Indicators
- **Consensus (Green border)**: Majority of models agree on annotation
- **Partial Agreement (Blue border)**: Multiple models detected same fragment
- **Single Model (Yellow dashed border)**: Only one model detected fragment

#### Explanation System
- Detailed modal explanations of how combined model analysis works
- Agreement level descriptions
- Statistical information about model consensus

## User Interface Updates

### Dashboard Enhancements
- **File**: `resources/views/dashboard/index.blade.php`
- **Changes**: 
  - All hardcoded Lithuanian text replaced with translation keys
  - Chart labels and tooltips internationalized
  - Export functionality labels translated

### Mission Control Updates
- **File**: `resources/views/mission-control.blade.php`
- **Changes**:
  - Added language switcher to header navigation
  - Updated HTML lang attribute to be locale-aware
  - Navigation links use translation keys

### Analysis View Improvements
- **File**: `resources/views/analyses/show.blade.php`
- **Changes**:
  - Enhanced annotation display with consensus indicators
  - Visual feedback for different agreement levels
  - Improved explanation tooltips

## Usage Instructions

### For Administrators

#### Fixing Annotation Positions
```bash
# Dry run to see what would be fixed
php artisan fix:annotation-positions --dry-run

# Fix all annotations
php artisan fix:annotation-positions

# Fix specific model
php artisan fix:annotation-positions --model="claude-3-5-sonnet-20241022"

# Fix specific text
php artisan fix:annotation-positions --text-id=123
```

#### Language Management
- Users can switch languages using the language switcher in the top navigation
- Language preferences are automatically saved per user
- Administrators can see user language preferences in the user management system

### For Users

#### Language Switching
1. Look for the language switcher (LT/EN buttons) in the top navigation
2. Click desired language
3. Interface immediately switches and preference is saved
4. Language setting persists across sessions

#### Understanding "All Models" View
1. Click "All models" when viewing analysis results
2. Look for colored borders around annotations:
   - **Green solid border**: Most models agree (consensus)
   - **Blue solid border**: Multiple models agree (partial agreement)  
   - **Yellow dashed border**: Single model detection
3. Click the "?" icon for detailed explanations

## Testing

### Manual Testing Checklist
- [ ] Language switching works in all views
- [ ] User language preferences persist across sessions
- [ ] All interface elements are properly translated
- [ ] "All models" view shows proper consensus indicators
- [ ] Annotation position fix command works correctly
- [ ] Mission Control includes language switcher

### Automated Testing
Test files should be created to verify:
- Language switching functionality
- User preference persistence
- Translation key coverage
- Annotation position calculations

## Configuration

### Supported Languages
Currently supported languages are defined in:
- `LanguageController`: `$supportedLanguages = ['lt', 'en']`
- Language files in `resources/lang/`

### Default Language
- System default: Lithuanian (`lt`)
- New users default: Lithuanian (`lt`)
- Fallback for invalid selections: Lithuanian (`lt`)

## Troubleshooting

### Common Issues

#### Language Not Switching
- Check if user is authenticated (language preferences require login)
- Verify language files exist in `resources/lang/`
- Check middleware is properly registered

#### Missing Translations
- Verify translation keys exist in both `lt/messages.php` and `en/messages.php`
- Check blade templates use `__('messages.key')` syntax
- Run `php artisan config:clear` to clear cached config

#### Annotation Position Issues
- Run position fix command with `--dry-run` first
- Check database character encoding is UTF-8
- Verify text content matches expected format

## Future Enhancements

### Potential Improvements
1. Additional language support (Russian, Polish, etc.)
2. Admin interface for managing translations
3. Automatic language detection from browser settings
4. Export functionality with language-specific formatting
5. Email notifications in user's preferred language

### Technical Considerations
- Consider using Laravel's built-in localization packages
- Implement translation management system for non-technical users
- Add language-specific date/time formatting
- Consider RTL language support for future expansion