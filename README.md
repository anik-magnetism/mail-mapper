# 📬 Mail Mapper for Laravel

Mail Mapper is a standalone Laravel package that provides a **dynamic, configurable email mapping and notification system**.  
It allows administrators or developers to define **who receives which emails for specific module actions** — without changing application code.

This package is ideal for **ERP, CRM, and enterprise Laravel applications** where email recipients frequently change.

---

## ✨ Features

- Module / Menu / Task based email mapping
- Dynamic **To / CC** email configuration from database
- Queue-based email dispatching
- Blade-based HTML email template
- Raw mail fallback for strict SMTP servers
- Easy integration using **Trait** or **Helper**
- Publishable config, migrations, and views

---

## 📦 Installation

Install via Composer:

```bash
composer require anikninja/mail-mapper
```

Publish package resources:

```bash
php artisan vendor:publish --provider="AnikNinja\MailMapper\MailMapperServiceProvider"
```
Run migrations:

```bash
php artisan migrate
```

## ⚙️ Configuration

Config file location:

```arduino
config/mail-mapper.php
```

Example:
```php
return [
    'default_from'  => [
            'address'   => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
            'name'      => env('MAIL_FROM_NAME', 'No Reply'),
    ],
    'use_raw_fallback'  => env('MAIL_MAPPER_USE_RAW_FALLBACK', true),
    'enable_logging'    => env('MAIL_MAPPER_ENABLE_LOGGING', true),
    'user_model'        => config('auth.providers.users.model', \App\Models\User::class), // Change default as needed
];
```

### Authorization Configuration

The package provides configurable authorization options for the API and management UI. After publishing `config/mail-mapper.php` you can set the following under the `authorization` key (or via env variables):

- `roles` — array of role names allowed to manage mappings (supports `spatie/laravel-permission` methods like `hasRole`/`hasAnyRole`). Can be set via `MAIL_MAPPER_AUTH_ROLES=admin,super-admin`.
- `permissions` — array of permission/gate names to check via `$user->can('permission')` (e.g. `['email-mapping-configure']`).
- `allow_super_admin` — boolean fallback to allow `super-admin` role or `super-admin-only` permission (defaults to `true`).
- `default_allow` — boolean default if no roles/permissions match (defaults to `false`).

Example configuration:

```php
'authorization' => [
    'roles' => ['admin','super-admin'],
    'permissions' => ['email-mapping-configure'],
    'allow_super_admin' => true,
    'default_allow' => false,
],
```

Notes:

- The package registers a default `EmailMappingPolicy` that respects these settings. Host applications can override the policy or adjust these config values after publishing.
- This approach makes authorization flexible across different auth/permission packages and app conventions.

### 🗒️ ENV Example:
```bash
MAIL_FROM_ADDRESS="no-reply@example.com"    # Default "from" email address for outgoing emails
MAIL_FROM_NAME="No Reply"                   # Default "from" name for outgoing emails
MAIL_MAPPER_USE_RAW_FALLBACK=true           # Enable/disable raw email sending fallback (true/false)
MAIL_MAPPER_ENABLE_LOGGING=true             # Enable/disable logging of email attachment info (true/false)
MAIL_MAPPER_API_PREFIX="api"                # The route prefix for all API routes.
MAIL_MAPPER_API_VERSION="v1"                # Optional version segment for the API routes. It can be set as null
MAIL_MAPPER_API_PER_PAGE=10                 # Default pagination size for listing endpoints
MAIL_MAPPER_API_MAX_PER_PAGE=100            # Maximum allowed pagination size to prevent abuse.
MAIL_MAPPER_AUTH_ROLES=admin,super-admin    # User roles that are allowed to manage email mappings. (e.g. admin,super-admin).
MAIL_MAPPER_ALLOW_SUPER_ADMIN=true          # Allow users with 'super-admin' role or permission by default.
```

## 🧠 Core Concepts
Concept	Description:

- Module	High-level system area (Sales, SCM, Support)
- Menu	Feature name (Lead Generation, Purchase Order)
- Task	Action name (Create, Update, Delete)
- To / CC	Dynamic email recipients
- Body	Email content (HTML supported)

## 🗄️ Database Structure

Email mappings are stored in:
```nginx
email_mappings
```

|Column |Description|
|:----- |:----------|
|module |Module name|
|menu   |Menu name|
|task   |Task/action|
|to     |Comma-separated email list|
|cc     |Optional CC emails|
|body   |Email body (HTML supported)|
|meta   |Holding placeholder attributes|

## 🚀 Usage
### ✅ Using Trait (Recommended)

```php
use AnikNinja\MailMapper\Traits\NotifiesByEmailMapping;

class LeadController extends Controller
{
    use NotifiesByEmailMapping;

    public function store(Request $request)
    {
        // Business logic...

        $this->notifyByMapping(
            module: 'Sales',
            menu: 'Lead Generation',
            task: 'Create',
            modelOrContext: $lead, // Lead Model Object or associative array providing context for placeholders.
            extra: [
                'custom_note' => 'Urgent lead created'
            ],
            useRaw: false
        );
    }
}
```

## ✅ Using Helper Function
```php
send_mail_mapping(
    'Sales',
    'Lead Generation',
    'Update',
    [
        'customer_name' => 'Customer 1',
        'customer_address' => 'Customer Address 1'
    ]
);
```

### Attachments

Pass attachments via the `$extra` parameter or the model context when calling `notifyByMapping(...)`.

Supported formats:

- In-memory attachment (array with `filename`, `content`, `mime`):

```php
['attachments' => [
    [
        'filename' => 'file.pdf',
        'content' => file_get_contents($path),
        'mime' => 'application/pdf',
    ],
]]
```

- File path (server file path or stored temporary file):

```php
['attachments' => ['/full/path/to/file.pdf']]
```

- Uploaded file (from a request):

```php
['attachments' => [$request->file('upload')]]
```

Example usage:

```php
$this->notifyByMapping(
    'Sales',
    'Leads',
    'Create',
    $model,
    ['attachments' => ['/path/to/invoice.pdf']]
);
```

Notes:

- The trait will normalize attachments and prefer path-based `attach()` to avoid loading large files into memory.
- Prefer passing file paths or `UploadedFile` instances for large files.

### Parameters

| Name | Type | Description |
|------|------|-------------|
| `module` | string | The module name (e.g., `'Sales'`). Supports wildcard (`*`) to match any module. |
| `menu` | string | The feature or menu name (e.g., `'Lead Generation'`). Supports wildcard (`*`) for flexible mapping. |
| `task` | string | The action or event name (e.g., `'Create'`, `'Update'`). Supports wildcard (`*`) for generic or fallback templates. |
| `modelOrContext` | `Model` &#124; `array` | An Eloquent model or associative array providing context for placeholders. |
| `extra` | array *(optional)* | Additional data (like: url, attachments) to merge into the context. |
| `useRaw` | bool *(optional)* | If `true`, bypasses mapping and sends a raw email directly. |

### 🔹 Wildcard Matching

The **Module**, **Menu**, and **Task** parameters support the `*` wildcard to allow flexible mapping rules:

| Example | Description |
|----------|-------------|
| `module: '*'` | Matches any module (used as a global fallback). |
| `menu: '*'` | Matches any feature or menu within the specified module. |
| `task: '*'` | Matches all actions or events for a given module and menu. |
| `module: 'Sales', menu: '*', task: 'Create'` | Matches all “Create” actions under the Sales module, regardless of feature. |

Wildcard support allows you to define **general-purpose email templates** that apply to multiple actions or modules — reducing redundancy and centralizing notification management.

## ⚙️ How It Works

### 1. Context Extraction
Builds a unified context array from the provided model or array, merging any extra data.

### 2. Meta Placeholder Management
Extracts all placeholders used in templates and stores them in the mapping’s meta field if not already present.

### 3. Recipient & Template Resolution
Uses `EmailMappingService` to resolve recipients, subject, and body dynamically, applying context placeholders.

### 4. Email Dispatch
Dispatches the fully rendered email through `SendEmailNotificationJob` for queued or asynchronous delivery.

## 🗒️ Notes

- Ensure your **`EmailMapping`** model and database table are properly configured to store **mapping definitions** and **meta placeholders**.  
- Placeholders in templates (e.g., `{client_name}`) are automatically replaced with values from the provided context.  
- `{actor_name}` and `{actor_email}` are injected automatically when an authenticated user exists.  

## 📨 Email Sending Strategy

The package automatically decides how to send email:
1. Mailable (HTML template)
2. Raw mail fallback if SMTP server rejects HTML mailables

You can force raw mode:
```php
SendEmailNotificationJob::dispatch([
    'to' => ['admin@example.com'],
    'subject' => 'System Update',
    'body' => '<p>Update completed</p>',
    'use_raw' => true,
]);
```
## 🎨 Email Template

Default template:
```swift
resources/views/mail/dynamic_notification.blade.php
```

To customize:
```bash
php artisan vendor:publish --tag=views
```

## 🧪 Queue Requirement

This package uses Laravel queues.

Run worker:

```bash
php artisan queue:work
```

Recommended drivers:

- database
- redis
- supervisor (production)

## ⚙️ **API**

The package exposes a simple CRUD API for managing email mappings. Routes are published under the configured API prefix (default: `/api`) and are protected by middleware defined in `config/mail-mapper.php`.

Default endpoints (prefix may include version if configured):

- GET  `/email-mappings` — List mappings. Supports `?per_page=` pagination.
- GET  `/email-mappings/{id}` — Get a single mapping.
- POST `/email-mappings` — Create mapping (returns 201).
- PUT  `/email-mappings/{id}` — Update mapping.
- DELETE `/email-mappings/{id}` — Delete mapping.

Authentication & Authorization:

- Routes are protected by the middleware defined in `config('mail-mapper.api.middleware')` (default `['api','auth:api']`).
- The package provides a default `EmailMappingPolicy` (registered by the service provider). Host applications should register permissions (for example `email-mapping-configure`) or override the policy to customize access control.

Example: Create mapping (cURL)

```bash
curl -X POST https://your-app.test/api/email-mappings \
    -H "Authorization: Bearer <token>" \
    -H "Content-Type: application/json" \
    -d '{
        "module":"Sales",
        "menu":"Leads",
        "task":"Create",
        "to":["ops@example.com"],
        "cc":["manager@example.com"],
        "subject":"New lead",
        "body":"<p>Hello {client_name}</p>",
        "is_active": true
    }'
```

Response example (201 Created):

```json
{
    "message": "Email Mapping created successfully.",
    "data": {
        "id": 1,
        "module": "Sales",
        "menu": "Leads",
        "task": "Create",
        "to": ["ops@example.com"],
        "cc": ["manager@example.com"],
        "subject": "New lead",
        "body": "<p>Hello {client_name}</p>",
        "is_active": true,
        "meta": [],
        "last_updated_by": null,
        "created_at": "2025-10-20T12:34:56Z",
        "updated_at": "2025-10-20T12:34:56Z"
    }
}
```

Notes:

- The list endpoint supports `?per_page=`; the default and maximum values are configurable via `config('mail-mapper.api.per_page')` and `config('mail-mapper.api.max_per_page')`.

- The package includes an OpenAPI 3 specification at the project root: `openapi.yaml`.

- To import into Postman: open Postman -> Import -> File -> select `openapi.yaml`.

- Protect the endpoints using your preferred auth middleware (Sanctum, Passport, or `auth:api`) by publishing and editing `config/mail-mapper.php`.
- Consider applying rate-limiting middleware (`throttle`) in your host application for public APIs.

## 🧰 Troubleshooting
|Issue	                    |Solution|
|:----                      |:-------|
|Mail not sent	            |Check SMTP config|
|Job not running            |Run queue worker|
|Template not rendering	    |Publish views|
|SMTP rejects HTML	        |Enable use_raw|

## 🧾 Summary

Mail Mapper is a standalone Laravel package that provides a dynamic, database-driven email notification system for modular applications.
It enables `module`, `menu`, and `task-based` email routing without hardcoding recipient addresses in application code.

Built for enterprise-scale systems, Mail Mapper allows administrators to manage email recipients and content from the UI, while developers trigger notifications using a simple trait or helper.
The package supports queued delivery, customizable HTML templates, and a raw SMTP fallback to ensure reliable email sending across different mail servers.
