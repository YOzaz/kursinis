# User Management System

**Updated:** 2025-06-14  
**Author:** Lithuanian Propaganda Detection System  

## Overview

The system now supports multi-user authentication with role-based access control and personal API key management. This replaces the previous single-user configuration-based system.

## User Roles

### Superadmin
- Full system access
- Can create, edit, and delete other users
- Can manage API keys for all users
- Can view audit logs
- Can access all analysis results

### Admin
- Can manage regular users
- Can view system analytics
- Can access most analysis features
- Cannot modify superadmin accounts

### User
- Can perform analysis with their own API keys
- Can view their own analysis results
- Cannot access user management features

## API Key Management

### Per-User API Keys

Users can now configure their own API keys for each provider:

- **Anthropic**: Claude models (Opus 4, Sonnet 4)
- **OpenAI**: GPT models (GPT-4.1, GPT-4o)
- **Google**: Gemini models (2.5 Pro, 2.5 Flash)

### Fallback System

If a user doesn't have an API key configured for a provider:
1. System checks for user's personal API key
2. Falls back to system-wide configuration API key
3. Models without available API keys are disabled for that user

### Usage Tracking

The system tracks:
- Last usage timestamp
- Total request count
- Usage statistics per provider
- API key rotation history (via audit logs)

## Setup Commands

### Initial Setup

1. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

2. **Create Superadmin**:
   ```bash
   php artisan user:create-superadmin
   ```
   
   Or with parameters:
   ```bash
   php artisan user:create-superadmin --email=admin@example.com --name="System Admin" --password=secretpassword
   ```

### User Management

#### Create Superadmin
```bash
# Interactive mode
php artisan user:create-superadmin

# With parameters
php artisan user:create-superadmin --email=admin@example.com --name="Admin User" --password=mypassword
```

#### Upgrade Existing User
The same command can upgrade an existing user to superadmin:
```bash
php artisan user:create-superadmin --email=existing@example.com
```

## Audit Logging

The system maintains complete audit trails for:

### User Actions
- User creation, updates, deletion
- Role changes
- Login/logout activities
- Password changes

### API Key Management
- API key creation and updates
- API key deletion
- API key usage tracking
- Provider switches

### System Changes
- Analysis job creation
- Model configuration changes
- Metrics recalculation

### Audit Log Fields
- **User ID**: Who performed the action
- **Action**: What was done (created, updated, deleted, etc.)
- **Resource Type**: What was affected (User, UserApiKey, etc.)
- **Resource ID**: Specific item affected
- **Old Values**: Previous state (for updates)
- **New Values**: New state (for updates)
- **IP Address**: Request origin
- **User Agent**: Client information
- **Timestamp**: When action occurred

## Position Accuracy Update

### New Calculation Method

The system now uses the Inter-Annotator Agreement (IAA) formula for position accuracy:

```
Agreement = |A ∩ B| / min(|A|, |B|)
```

### Recalculation Command

To update existing data with the new formula:

```bash
# Recalculate all metrics
php artisan metrics:recalculate-position-accuracy --force

# Recalculate specific job
php artisan metrics:recalculate-position-accuracy --job-id=12345 --force

# Interactive confirmation
php artisan metrics:recalculate-position-accuracy
```

### Benefits of New Formula

1. **Standard Methodology**: Uses established inter-annotator agreement practices
2. **Bias Prevention**: Normalizes by smaller annotation set
3. **Accurate Measurement**: Better reflects annotation quality
4. **Research Compliance**: Aligns with academic standards

## Database Schema

### Users Table
- `id`: Primary key
- `name`: Full name
- `email`: Unique email address
- `password`: Hashed password
- `role`: superadmin, admin, user
- `is_active`: Account status
- `email_verified_at`: Email verification timestamp
- `remember_token`: Remember me functionality
- `created_at`, `updated_at`: Timestamps

### User API Keys Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `provider`: anthropic, openai, google
- `api_key`: Encrypted API key
- `is_active`: Key status
- `last_used_at`: Last usage timestamp
- `usage_stats`: JSON usage statistics
- `created_at`, `updated_at`: Timestamps

### Audit Logs Table
- `id`: Primary key
- `user_id`: Foreign key to users (nullable)
- `action`: Action performed
- `resource_type`: Type of resource affected
- `resource_id`: ID of specific resource
- `old_values`: JSON of previous values
- `new_values`: JSON of new values
- `ip_address`: Request IP
- `user_agent`: Client user agent
- `created_at`, `updated_at`: Timestamps

## Security Considerations

### API Key Storage
- API keys are stored encrypted in the database
- Only masked versions are displayed in UI
- Full keys are only accessible to the owning user

### Role-Based Access
- Middleware enforces role-based access control
- Superadmin privileges are required for user management
- Users can only access their own data

### Audit Trail
- All sensitive actions are logged
- Logs include IP addresses and user agents
- Audit logs cannot be modified by users

### Password Security
- Passwords are hashed using Laravel's default hashing
- Minimum 8 character requirement
- Password confirmation required for changes

## Migration from Config-Based System

### Automatic Fallback
The new system is backward compatible:
1. If no user is authenticated, system uses config API keys
2. If user lacks API key for provider, falls back to config
3. Existing analysis results remain unchanged

### Migration Steps
1. Run database migrations
2. Create superadmin user
3. Users configure their personal API keys
4. Optional: Remove API keys from config files for security

## Troubleshooting

### Common Issues

1. **No API Keys Configured**
   - Solution: Configure API keys via user management interface
   - Fallback: Ensure config API keys are still available

2. **Permission Denied**
   - Check user role and permissions
   - Verify superadmin has been created correctly

3. **Position Accuracy Inconsistencies**
   - Run recalculation command to update with new formula
   - Check audit logs for calculation history

### Support Commands

```bash
# Check user count and roles
php artisan tinker
> User::all(['id', 'name', 'email', 'role']);

# Verify migrations
php artisan migrate:status

# Check audit logs
php artisan tinker
> AuditLog::latest()->limit(10)->get(['action', 'resource_type', 'created_at']);
```