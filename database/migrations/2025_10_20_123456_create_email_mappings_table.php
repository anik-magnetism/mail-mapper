<?php

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
            $table->string('module')->default('*')->index(); // e.g., Sales
            $table->string('menu')->default('*')->index();   // e.g., Lead Generation
            $table->string('task')->default('*')->index();   // e.g., create, update, delete
            $table->json('to')->nullable();  // ["a@b.com","c@d.com"]
            $table->json('cc')->nullable();  // ["x@y.com"]
            $table->string('subject')->nullable();
            $table->longText('body')->nullable(); // HTML allowed, with placeholders
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable(); // optional additional data
            $table->unsignedBigInteger('last_updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['module', 'menu', 'task']);
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
