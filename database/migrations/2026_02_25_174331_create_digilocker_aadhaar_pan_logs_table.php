<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digilocker_aadhaar_pan_logs', function (Blueprint $table) {
            $table->id();

            // request
            $table->string('purpose')->nullable();
            $table->string('response_url')->nullable();
            $table->string('redirect_url')->nullable();

            // response (main)
            $table->boolean('success')->default(false);
            $table->string('api_message', 255)->nullable();

            $table->string('request_id', 64)->nullable()->index();
            $table->uuid('webhook_security_key')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('sdk_url')->nullable();

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digilocker_aadhaar_pan_logs');
    }
};