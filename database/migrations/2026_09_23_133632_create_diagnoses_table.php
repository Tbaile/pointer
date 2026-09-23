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
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sos_id');
            $table->string('system_type');
            $table->text('user_symptoms');
            $table->text('machine_symptoms');
            $table->text('conclusion')->nullable();
            $table->text('resolution')->nullable();
            $table->string('outcome');
            $table->string('confidence');
            $table->text('confidence_reason');
            $table->string('verdict')->nullable();
            $table->text('verdict_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['system_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
