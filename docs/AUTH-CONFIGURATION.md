# Authentication Configuration Guide

This guide explains how to configure user authentication using environment variables instead of hardcoded credentials.

## 🔐 Environment-Based Authentication

### Setting Up Users

Add the following to your `.env` file:

```bash
# Multiple users (recommended)
AUTH_USERS="admin:secure_password_123,marijus:another_password_456,darius:strong_password_789"

# Fallback admin password (used if AUTH_USERS is empty or invalid)
ADMIN_PASSWORD="fallback_admin_password"
```

### Format Specification

**AUTH_USERS Format**: `username1:password1,username2:password2,username3:password3`

**Rules**:
- Usernames and passwords are separated by a colon (`:`)
- Multiple users are separated by commas (`,`)
- Whitespace around usernames and passwords is automatically trimmed
- If a user entry is malformed (missing colon), it's ignored
- If username contains a colon, only the first colon is used as separator

### Examples

#### Basic Setup
```bash
AUTH_USERS="admin:admin123"
```

#### Multiple Users
```bash
AUTH_USERS="admin:secret123,user1:password1,user2:password2"
```

#### With Whitespace (automatically trimmed)
```bash
AUTH_USERS=" admin : secret123 , user1: password1 , user2 :password2 "
```

#### Production Example
```bash
AUTH_USERS="admin:$(openssl rand -base64 32),researcher:$(openssl rand -base64 32)"
```

## 🔄 Migration from Hardcoded Users

### Old System (Before)
```php
// In SimpleAuth.php (hardcoded)
$validUsers = [
    'admin' => env('ADMIN_PASSWORD', 'propaganda2025'),
    'marijus' => env('ADMIN_PASSWORD', 'propaganda2025'),
    'darius' => env('ADMIN_PASSWORD', 'propaganda2025'),
];
```

### New System (After)
```bash
# In .env file
AUTH_USERS="admin:new_admin_pass,marijus:marijus_pass,darius:darius_pass"
```

## 🛡️ Security Best Practices

### Strong Passwords
```bash
# Generate secure passwords
openssl rand -base64 32

# Example with generated passwords
AUTH_USERS="admin:K7H9mN2pL8qR5wT3xZ6vB4yC1uE0sA9f,user:M8J5nP3rQ7sW2zY6cF9hL1dB4vN0mK8e"
```

### Environment Security
```bash
# Make sure .env file has correct permissions
chmod 600 .env

# Never commit .env to version control
echo ".env" >> .gitignore
```

### Production Deployment
```bash
# Use environment variables in production
export AUTH_USERS="admin:${ADMIN_SECRET},user1:${USER1_SECRET}"

# Or use Docker secrets
docker run -e AUTH_USERS="admin:${ADMIN_SECRET}" your-app
```

## 🧪 Testing Configuration

### Verify Users Work
1. Set `AUTH_USERS` in your `.env` file
2. Clear config cache: `php artisan config:clear`
3. Visit `/login` and test each user
4. Check logs for authentication attempts

### Fallback Testing
1. Set `AUTH_USERS=""` (empty)
2. Set `ADMIN_PASSWORD="test123"`
3. Should allow login with `admin:test123`

### Invalid Format Testing
```bash
# This should ignore malformed entries
AUTH_USERS="validuser:validpass,invalidentry,user2:pass2"
# Should only work with 'validuser' and 'user2'
```

## 🔧 Troubleshooting

### Common Issues

#### Authentication Not Working
```bash
# Clear Laravel config cache
php artisan config:clear

# Check .env file format
cat .env | grep AUTH_USERS

# Verify no invisible characters
hexdump -C .env | grep -A5 -B5 AUTH_USERS
```

#### Users Not Loading
```bash
# Check PHP syntax
php -r "echo getenv('AUTH_USERS');"

# Test in Laravel console
php artisan tinker
>>> env('AUTH_USERS')
```

#### Permission Denied
```bash
# Fix .env permissions
chmod 644 .env
chown www-data:www-data .env  # For Apache/Nginx
```

### Debug Mode

Add this temporarily to `SimpleAuth.php` for debugging:

```php
private function getValidUsers(): array
{
    $users = [];
    $authUsers = env('AUTH_USERS', 'admin:propaganda2025');
    
    // Debug output (remove in production)
    \Log::info('AUTH_USERS from env: ' . $authUsers);
    
    // ... rest of the method
}
```

## 📝 Deployment Checklist

- [ ] Set `AUTH_USERS` in production `.env`
- [ ] Use strong, unique passwords
- [ ] Verify `.env` file permissions (600 or 644)
- [ ] Test authentication after deployment
- [ ] Clear config cache: `php artisan config:clear`
- [ ] Remove any debug logging
- [ ] Document credentials securely
- [ ] Set up password rotation schedule

## 🔄 Password Updates

### Changing Passwords
1. Update `AUTH_USERS` in `.env` file
2. Clear config cache: `php artisan config:clear`
3. Test new credentials
4. Notify affected users

### Adding New Users
```bash
# Add to existing AUTH_USERS
AUTH_USERS="existing:pass,newuser:newpass"
```

### Removing Users
```bash
# Remove from AUTH_USERS string
AUTH_USERS="keepuser1:pass1,keepuser2:pass2"  # removed unwanted users
```

This system provides flexible, secure user management without requiring code changes or database modifications.