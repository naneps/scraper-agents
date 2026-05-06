<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('base_url');
            $table->text('description')->nullable();
            $table->string('selector_title');
            $table->string('selector_body');
            $table->string('selector_image')->nullable();
            $table->enum('schedule_type', ['interval', 'cron', 'once'])->default('interval');
            $table->string('schedule_value');
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamp('next_scrape_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
