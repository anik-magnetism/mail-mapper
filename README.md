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
    'default_from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'No Reply'),
    ],
    'use_raw_fallback' => env('MAIL_MAPPER_USE_RAW_FALLBACK', true),
    'enable_logging' => env('MAIL_MAPPER_ENABLE_LOGGING', true),
];
```

or this config is usable in env file
```bash
MAIL_FROM_ADDRESS="no-reply@example.com" # Default "from" email address for outgoing emails
MAIL_FROM_NAME="No Reply"                # Default "from" name for outgoing emails
MAIL_MAPPER_USE_RAW_FALLBACK=true        # Enable/disable raw email sending fallback (true/false)
MAIL_MAPPER_ENABLE_LOGGING=true          # Enable/disable logging of email attachment info (true/false)
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

## 🧰 Troubleshooting
|Issue	                    |Solution|
|:----                      |:-------|
|Mail not sent	            |Check SMTP config|
|Job not running            |Run queue worker|
|Template not rendering	    |Publish views|
|SMTP rejects HTML	        |Enable use_raw|
#
## 🧾 Summary

Mail Mapper is a standalone Laravel package that provides a dynamic, database-driven email notification system for modular applications.
It enables `module`, `menu`, and `task-based` email routing without hardcoding recipient addresses in application code.

Built for enterprise-scale systems, Mail Mapper allows administrators to manage email recipients and content from the UI, while developers trigger notifications using a simple trait or helper.
The package supports queued delivery, customizable HTML templates, and a raw SMTP fallback to ensure reliable email sending across different mail servers.
