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
    'default_from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
    'default_from_name' => env('MAIL_FROM_NAME', 'No Reply'),
    'enable_logging' => true,
];
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
use AnikNinja\MailMapper\Traits\MailMappingNotifier;

class LeadController extends Controller
{
    use MailMappingNotifier;

    public function store(Request $request)
    {
        // Business logic...

        $this->sendMappedNotification(
            'Sales',
            'Lead Generation',
            'Create',
            [
                'meta' => [
                    'actor_name' => auth()->user()->name,
                    'actor_email' => auth()->user()->email,
                ]
            ]
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
        'meta' => [
            'actor_name' => 'Admin'
        ]
    ]
);
```

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
resources/views/vendor/mail-mapper/mail/dynamic_notification.blade.php
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