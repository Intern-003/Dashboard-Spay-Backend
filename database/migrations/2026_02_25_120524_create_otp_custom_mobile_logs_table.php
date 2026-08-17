<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_custom_mobile_logs', function (Blueprint $table) {
            $table->id();

            // request fields
            $table->string('user')->nullable();
            $table->string('sender')->nullable();
            $table->string('templateid')->nullable();
            $table->string('mobile', 20)->index();
            $table->text('message')->nullable();

            // response main fields
            $table->boolean('status')->default(false);
            $table->string('api_message', 255)->nullable();

            $table->string('code', 10)->nullable(); // data.RESPONSE.CODE
            $table->string('info', 50)->nullable(); // data.RESPONSE.INFO
            $table->string('uid', 80)->nullable()->index();  // data.RESPONSE.UID
            $table->string('api_status', 20)->nullable(); // data.STATUS

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_custom_mobile_logs');
    }
};