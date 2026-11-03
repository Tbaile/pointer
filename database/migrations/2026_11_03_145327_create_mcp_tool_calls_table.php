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
        Schema::create('mcp_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client')->nullable();
            $table->string('session_id')->nullable();
            $table->string('tool')->index();
            $table->string('sos_id')->nullable()->index();
            $table->json('arguments')->nullable();
            $table->boolean('success');
            $table->unsignedInteger('duration_ms');
            $table->timestamps();
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
    }
};
