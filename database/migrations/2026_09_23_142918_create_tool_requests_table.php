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
        Schema::create('tool_requests', function (Blueprint $table) {
            $table->id();
            $table->string('system_type');
            $table->string('title');
            $table->text('description');
            $table->unsignedInteger('requests_count')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['system_type', 'requests_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_requests');
    }
};
