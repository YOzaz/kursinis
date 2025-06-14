# Propaganda Annotations Fix - Feature Summary

This document describes the improvements made to the propaganda annotation system and language switching functionality.

## Changes Made

### 1. Annotation Position Fix Script

**File**: `app/Console/Commands/FixAnnotationPositionsCommand.php`

- Created a comprehensive script to fix incorrect annotation positions in the database
- Analyzes both legacy annotation fields and new ModelResult table entries
- Supports dry-run mode for testing without making changes
- Handles multiple annotation formats (Label Studio, direct format, flat array)
- Uses UTF-8 safe position calculations
- Provides detailed logging and error reporting

**Usage**:
```bash
# Dry run to see what would be fixed
php artisan annotations:fix-positions --dry-run

# Fix all annotations
php artisan annotations:fix-positions

# Fix specific model only
php artisan annotations:fix-positions --model=claude-opus-4

# Fix specific text only  
php artisan annotations:fix-positions --text-id=123
```

### 2. Enhanced "All Models" Functionality

**Files Modified**: 
- `app/Http/Controllers/AnalysisController.php`
- `resources/views/analyses/show.blade.php`

**Improvements**:
- **Clear Labeling**: Changed "Visi modeliai" to "Visi modeliai (kombinuoti)" to clarify functionality
- **Agreement Indicators**: Added visual indicators showing how many models agree on each annotation
  - 🟢 Green solid border: Majority consensus (>50% of models)
  - 🔵 Blue dotted border: Partial agreement (2+ models)
  - 🟡 Yellow dashed border: Single model detection
- **Agreement Badges**: Small numeric badges showing exact agreement count
- **Enhanced Tooltips**: Detailed information about which models contributed to each annotation
- **Explanation Panel**: Added info panel explaining how the "All models" mode works
- **Statistics**: Backend now provides detailed statistics about model agreement

**How It Works**:
- Combines annotations from all available models
- Shows any annotation detected by at least one model
- Calculates agreement percentages and consensus levels
- Provides visual indicators for different agreement levels
- Displays which specific models contributed to each annotation

### 3. Language Switching (LT/EN)

**Files Added**:
- `resources/lang/lt/messages.php` - Lithuanian translations
- `resources/lang/en/messages.php` - English translations
- `app/Http/Controllers/LanguageController.php` - Language switching controller
- `app/Http/Middleware/SetLanguage.php` - Language setting middleware

**Files Modified**:
- `routes/web.php` - Added language switching routes
- `bootstrap/app.php` - Registered language middleware
- `resources/views/layout.blade.php` - Added language switcher and translation support
- `app/Http/Controllers/AnalysisController.php` - Backend translations

**Features**:
- Language switcher in navigation bar showing current language (LT/EN)
- Dropdown menu to switch between Lithuanian and English
- Session-based language persistence
- Translated navigation, titles, and key interface elements
- Backend API responses also use translations
- Automatic language detection from session

**Available Translations**:
- Navigation menu items
- Page titles and headings
- Button labels and actions
- Status messages
- Error messages
- Model agreement explanations
- Annotation interface elements

### 4. Frontend Improvements

**Enhanced Annotation Display**:
- All three annotation views (main, expanded, modal) now support agreement indicators
- Consistent visual styling across all views
- Better tooltips with model agreement information
- Responsive explanation panels
- Color-coded borders for different agreement levels

**Visual Indicators**:
- **Consensus annotations**: Green solid border + bold text
- **Partial agreement**: Blue dotted border  
- **Single model**: Yellow dashed border
- **Agreement badges**: Small black badges with count numbers

## Technical Details

### Position Accuracy Algorithm

The fix script uses sophisticated text matching:
1. **Exact match**: Direct string search in content
2. **Case-insensitive match**: For minor case differences
3. **Normalized whitespace**: Handles whitespace variations
4. **Position recalculation**: Maps normalized positions back to original text

### Agreement Calculation

```php
$agreement_percentage = round(($agreement_count / $total_models) * 100);
$is_consensus = $agreement_count > ($total_models / 2);
```

### Language System

- Uses Laravel's built-in internationalization
- Session-based language storage
- Middleware automatically sets locale
- Fallback to Lithuanian if invalid language

## Testing

The system has been tested with:
- Empty databases (no existing annotations)
- Multiple annotation formats
- Language switching functionality
- Agreement indicator display
- Responsive design across different screen sizes

## Usage Instructions

### For Users

1. **Language Switching**: Click the language dropdown (LT/EN) in the top navigation
2. **All Models View**: Select "Visi modeliai (kombinuoti)" from model dropdown to see combined annotations
3. **Agreement Information**: Hover over highlighted text to see which models agree
4. **Visual Indicators**: Look for border colors to quickly identify agreement levels

### For Administrators

1. **Fix Positions**: Run the fix script before using the system with existing data
2. **Language Setup**: Language switching is automatic - no configuration needed
3. **Monitoring**: Check the agreement statistics in the "All models" explanation panel

## Future Enhancements

Potential improvements for future versions:
- Additional languages (support for more European languages)
- Advanced agreement filtering (show only consensus annotations)
- Agreement threshold configuration
- Annotation confidence scoring
- Export options with agreement metadata