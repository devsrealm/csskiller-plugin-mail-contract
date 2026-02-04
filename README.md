# CSS Killer Mail Contract Plugin

A secure email-based terms acceptance plugin for CSS Killer CMS. This plugin provides a complete solution for collecting user consent through email verification, with built-in token management, audit trails, and automated cleanup.

## Features

- **Secure Email Verification**: Generate cryptographically secure tokens for email confirmation
- **Terms Acceptance Flow**: Complete workflow from form submission to confirmation
- **Token Management**: Automatic expiration (24 hours), status tracking, and cleanup
- **Audit Trail**: Permanent records of confirmed acceptances for compliance
- **Responsive UI**: Modern, mobile-friendly templates using Tailwind CSS
- **Console Commands**: Automated cleanup of expired tokens
- **RESTful API**: JSON endpoints for integration with other systems

## Requirements

- PHP 8.2 or higher
- CSS Killer CMS
- Composer for dependency management

## Installation

1. Install via Composer:
```bash
composer require devsrealm/csskiller-plugin-mail-contract
```

2. Enable the plugin in your CSS Killer CMS admin panel

3. Configure your email settings in the CMS configuration

## Usage

### Basic Terms Acceptance Flow

1. **Display Terms Form**: Direct users to `/terms` to show the acceptance form
2. **Submit Email**: User enters their email and submits the form
3. **Email Confirmation**: System sends a secure confirmation link via email
4. **Confirm Acceptance**: User clicks the link to confirm their acceptance
5. **Success Page**: User sees confirmation of successful acceptance

### API Endpoints

The plugin provides the following REST endpoints under the `/terms` route:

#### GET `/terms`
Displays the terms acceptance form.

#### POST `/terms/submit`
Submits the terms acceptance request.

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Please check your email to confirm your acceptance...",
  "email": "user@example.com"
}
```

#### GET `/terms/confirm?token=<token>`
Confirms the terms acceptance using the token from the email link.

#### GET `/terms/status?email=<email>`
Retrieves the status of tokens for a given email address.

**Response:**
```json
{
  "email": "user@example.com",
  "total_tokens": 2,
  "tokens": [
    {
      "id": 1,
      "token": "abc123...",
      "data": {
        "email": "user@example.com",
        "status": "confirmed",
        "expires_at": "2024-01-01 12:00:00",
        "confirmed_at": "2024-01-01 10:30:00"
      },
      "created_at": "2024-01-01 09:00:00",
      "updated_at": "2024-01-01 10:30:00"
    }
  ]
}
```

### Console Commands

#### Cleanup Expired Tokens

Remove expired pending tokens while preserving confirmed tokens for audit purposes:

```bash
php console --cleanup:terms:tokens
```

This command:
- Removes tokens that have expired and are still pending
- Preserves all confirmed tokens as permanent audit records
- Should be run periodically (e.g., via cron job)

### Programmatic Usage

#### Using the TokenSignatureService

```php
use CSSKillerMailContract\services\TokenSignatureService;

// Generate a confirmation token
$service = new TokenSignatureService($core);
$token = $service->generateToken('user@example.com', [
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Generate a full confirmation link
$confirmation = $service->generateConfirmationLink(
    email: 'user@example.com',
    baseUrl: 'https://yoursite.com/terms/confirm',
    metadata: ['source' => 'registration']
);

// Validate a token
$result = $service->validateToken($token);
if ($result['valid']) {
    // Mark as confirmed
    $service->markAsConfirmed($token, $_SERVER['REMOTE_ADDR']);
}

// Get all tokens for an email
$tokens = $service->getTokensByEmail('user@example.com');
```

## Configuration

### Environment Variables

No additional environment variables are required beyond standard CSS Killer CMS configuration.

### Email Configuration

The plugin uses PHP's built-in `mail()` function by default. For production use, you should configure a proper email service in your CMS settings.

### Token Settings

- **Token Expiry**: 24 hours (configurable in `TokenSignatureService::TOKEN_EXPIRY_HOURS`)
- **Token Length**: 64 characters (32 bytes of random data, hex-encoded)
- **Storage**: Tokens are stored in the CMS `system_global` table with context `MAIL_SIGN`

## Security Features

- **Cryptographically Secure Tokens**: Uses `random_bytes()` for token generation
- **Token Expiration**: Automatic expiration prevents indefinite validity
- **Single-Use Tokens**: Tokens can only be confirmed once
- **IP Tracking**: Records IP addresses for audit purposes
- **Metadata Storage**: Stores user agent, timestamps, and custom metadata
- **Audit Preservation**: Confirmed tokens are never deleted for compliance

## Templates

The plugin includes the following templates (located in `src/templates/`):

- `terms-form.html` - The main terms acceptance form
- `terms-success.html` - Success confirmation page
- `terms-error.html` - Error display page

Templates use the CSS Killer CMS template system and can be customized by overriding them in your theme.

## Database Storage

Tokens are stored in the `system_global` table with:
- `context`: `MAIL_SIGN`
- `key`: The token string
- `value_json`: JSON data containing email, status, expiry, metadata, etc.

## Compliance & GDPR

- **Audit Trail**: All confirmations are permanently stored
- **Data Minimization**: Only necessary data is collected
- **Consent Records**: Permanent proof of user consent
- **Cleanup**: Automated removal of unconfirmed/expired data

## Development

### Project Structure

```
src/
├── PluginEntry.php              # Plugin registration
├── Routes.php                   # Route definitions
├── controllers/
│   └── TermsAcceptanceController.php
├── services/
│   └── TokenSignatureService.php
├── commands/
│   └── CleanupExpiredTokensCommand.php
├── middlewares/
│   └── AuthMiddleware.php
└── templates/
    ├── terms-form.html
    ├── terms-success.html
    └── terms-error.html
```

### Extending the Plugin

#### Custom Email Templates

Override the email sending in `TermsAcceptanceController::sendConfirmationEmail()` to use your preferred email service.

#### Additional Metadata

Pass additional metadata when generating tokens:

```php
$confirmation = $service->generateConfirmationLink(
    email: $email,
    baseUrl: $baseUrl,
    metadata: [
        'custom_field' => 'value',
        'user_id' => 123,
        'campaign' => 'newsletter_signup'
    ]
);
```

#### Custom Validation

Extend the controller to add custom validation logic before token generation.

## License

This plugin is licensed under the GNU Affero General Public License v3.0.

## Support

For support and contributions, please contact the maintainer or create an issue in the project repository.

## Changelog

### Version 1.0.0
- Initial release
- Basic terms acceptance flow
- Token management service
- Console cleanup command
- Responsive templates
