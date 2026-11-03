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
        Schema::create('command_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('sos_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('command');
            $table->json('probabilities')->nullable();
            $table->json('flagged')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_classifications');
    }
};
