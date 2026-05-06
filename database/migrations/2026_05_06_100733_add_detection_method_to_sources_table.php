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
        Schema::table('sources', function (Blueprint $table) {
            $table->enum('detection_method', ['auto-detect', 'manual-selector'])->default('auto-detect')->after('description');
            $table->string('selector_title')->nullable()->change();
            $table->string('selector_body')->nullable()->change();
            $table->string('selector_image')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('detection_method');
            $table->string('selector_title')->nullable(false)->change();
            $table->string('selector_body')->nullable(false)->change();
            $table->string('selector_image')->nullable(false)->change();
        });
    }
};
