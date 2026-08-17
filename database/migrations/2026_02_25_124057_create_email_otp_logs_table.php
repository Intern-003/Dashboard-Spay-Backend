<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_otp_logs', function (Blueprint $table) {
            $table->id();

            // request
            $table->string('email')->index();
            $table->string('name')->nullable();

            // otp
            $table->string('otp', 10)->nullable();
            $table->enum('otp_status', ['active', 'inactive'])->default('active');

            // email payload
            $table->string('subject')->nullable();
            $table->longText('message')->nullable(); // html

            // provider response
            $table->boolean('status')->default(false);
            $table->string('api_message', 255)->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->string('provider_message', 50)->nullable();
            $table->string('message_id', 100)->nullable()->index();

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_logs');
    }
};