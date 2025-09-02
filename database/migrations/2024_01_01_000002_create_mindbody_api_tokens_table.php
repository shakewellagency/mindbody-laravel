<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindbody_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->text('access_token');
            $table->string('token_type')->default('Bearer');
            $table->integer('expires_in');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            // Composite index for efficient token lookup
            $table->index(['username', 'expires_at', 'revoked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindbody_api_tokens');
    }
};