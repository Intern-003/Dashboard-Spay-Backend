<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_verification_logs', function (Blueprint $table) {
            $table->id();

            // request
            $table->string('account_number')->index();
            $table->string('ifsc', 20)->index();
            $table->string('name_to_match')->nullable();

            // main response titles
            $table->uuid('request_id')->nullable()->index();
            $table->uuid('task_id')->nullable()->index();
            $table->uuid('group_id')->nullable()->index();

            $table->boolean('success')->default(false);
            $table->string('response_code', 20)->nullable();
            $table->string('response_message')->nullable();

            $table->string('billable', 5)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->string('reason_message')->nullable();

            // result main fields
            $table->string('beneficiary_name')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('name_match_score')->nullable();
            $table->boolean('is_penny_drop')->nullable();

            $table->timestamp('request_timestamp')->nullable();
            $table->timestamp('response_timestamp')->nullable();

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_verification_logs');
    }
};