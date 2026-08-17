<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digilocker_transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->index();
            $table->string('status')->nullable();
            $table->string('response_code')->nullable();
            $table->string('response_message')->nullable();
            $table->boolean('billable')->nullable();
            $table->dateTime('provider_created_at')->nullable();
            $table->dateTime('provider_updated_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->integer('http_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digilocker_transaction_logs');
    }
};