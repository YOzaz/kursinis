# User API Keys Feature

## Overview

The propaganda detection system now supports user-specific API keys for AI providers (Anthropic, OpenAI, Google). This allows users to use their own API keys instead of the system-wide defaults.

## Features

- **Personal API Keys**: Each user can configure their own API keys for different AI providers
- **Secure Storage**: API keys are encrypted using Laravel's encryption before storing in the database
- **Usage Tracking**: The system tracks when API keys are used and maintains usage statistics
- **Easy Management**: Users can add, update, or delete their API keys through the settings interface
- **Fallback Support**: If a user doesn't have a personal API key, the system falls back to the default configuration

## How It Works

### For Users

1. Navigate to Settings (`/settings`)
2. In the "My API Keys" section, enter your API keys for any providers you want to use
3. Save the API keys - they will be encrypted and stored securely
4. When you run analyses, your API keys will be used automatically

### For Developers

The implementation consists of several components:

1. **Database Structure**
   - `user_api_keys` table stores encrypted API keys
   - Each user can have one API key per provider
   - Tracks usage statistics and last used timestamp

2. **User Management**
   - SimpleAuth users are automatically mapped to User models
   - Users are created on-demand when they first access settings

3. **Service Integration**
   - `LLMService` handles API key retrieval with user context
   - AI services (Claude, OpenAI, Gemini) automatically use user keys when available
   - Fallback to system configuration if user keys are not available

4. **Security**
   - API keys are encrypted using Laravel's Crypt facade
   - Keys are never displayed in plain text after saving
   - Masked display shows only first 4 and last 4 characters

## API Key Providers

The system supports API keys for:

- **Anthropic** (Claude models)
- **OpenAI** (GPT models)
- **Google** (Gemini models)

## Code Examples

### Getting a user's API key in code:

```php
$llmService = new \App\Services\LLMService();
$apiKey = $llmService->getApiKey('anthropic', $user);
```

### Checking if a user has an API key:

```php
if ($user->hasApiKey('openai')) {
    // User has configured OpenAI API key
}
```

## Testing

Run the feature tests to ensure everything works correctly:

```bash
php artisan test tests/Feature/UserApiKeysTest.php
```

## Migration

The feature includes a migration that creates the `user_api_keys` table. This migration should already be run in your environment.

## Future Enhancements

Potential improvements for this feature:

1. API key validation before saving
2. Usage quota tracking
3. Cost estimation based on usage
4. API key expiration dates
5. Multiple API keys per provider (for rotation)