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
        Schema::create('component_events', function (Blueprint $table) {
            $table->id();
            $table->string('component')->index();
            $table->string('event')->index();
            $table->json('data')->nullable();
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['component', 'event']);
            $table->index(['created_at', 'processed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_events');
    }
};
