<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindbody_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('site_id')->nullable()->index();
            $table->json('event_data');
            $table->json('headers')->nullable();
            $table->timestamp('event_timestamp')->nullable();
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error')->nullable();
            $table->string('signature')->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['processed', 'created_at']);
            $table->index(['event_type', 'processed']);
            $table->index(['site_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindbody_webhook_events');
    }
};
