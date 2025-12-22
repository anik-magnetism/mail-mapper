<?php

/**
 * Migration: Create the email_mappings table
 *
 * This table stores dynamic email mapping templates for the Mail Mapper package.
 * - Supports module/menu/task scoping with wildcards
 * - Stores recipient lists (to/cc), subject, body (HTML), meta placeholders, and status
 * - Tracks the user who last updated the mapping
 * - Includes timestamps and soft deletes
 *
 * Columns:
 * - id: Primary key
 * - module/menu/task: Strings, indexed, support wildcards for flexible matching
 * - to/cc: JSON arrays of email addresses
 * - subject: Email subject (string)
 * - body: Email body (HTML, longText)
 * - is_active: Boolean flag
 * - meta: JSON array of placeholder keys
 * - last_updated_by: User ID (nullable)
 * - timestamps, soft deletes
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('module')->default('*')->index()->comment('Module name, e.g. Sales. Supports wildcard.');
            $table->string('menu')->default('*')->index()->comment('Menu/section, e.g. Lead Generation. Supports wildcard.');
            $table->string('task')->default('*')->index()->comment('Task/action, e.g. create, update, delete. Supports wildcard.');
            $table->json('to')->nullable()->comment('Recipient email addresses (JSON array, e.g. ["user@example.com", "admin@example.com"])');
            $table->json('cc')->nullable()->comment('CC email addresses (JSON array), e.g. ["a@b.com", "c@d.com"]');
            $table->string('subject')->nullable()->comment('Email subject');
            $table->longText('body')->nullable()->comment('Email body (HTML, supports placeholders)');
            $table->boolean('is_active')->default(true)->comment('Whether this mapping is active');
            $table->json('meta')->nullable()->comment('Meta/placeholder keys (JSON array), e.g. ["{client_name}", "{client_email}"], stored for reference. Not used directly in email sending. This helps track which placeholders are expected in the body/subject. And this column is updated when new placeholders are detected from Eloquent Model Objects or Context Array.');
            $table->unsignedBigInteger('last_updated_by')->nullable()->comment('User ID of last updater');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['module', 'menu', 'task'], 'idx_module_menu_task');
            $table->unique(['module', 'menu', 'task'], 'unique_module_menu_task');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_mappings');
    }
};
