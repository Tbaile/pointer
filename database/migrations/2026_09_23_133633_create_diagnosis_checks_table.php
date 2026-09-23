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
        Schema::create('diagnosis_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnosis_id')->constrained()->cascadeOnDelete();
            $table->string('tool');
            $table->json('arguments')->nullable();
            $table->text('finding');
            $table->string('result');
            $table->string('source');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnosis_checks');
    }
};
