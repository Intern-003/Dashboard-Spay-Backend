<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gst_advance_verifications', function (Blueprint $table) {
            $table->id();

            // ✅ Request details (your input)
            $table->string('gstin', 20)->nullable();           // business_gstin_number
            $table->string('financial_year', 20)->nullable();

            // ✅ Main response titles (top-level)
            $table->uuid('request_id')->nullable()->index();
            $table->uuid('task_id')->nullable()->index();
            $table->uuid('group_id')->nullable()->index();

            $table->boolean('success')->default(false);
            $table->string('response_code', 20)->nullable();
            $table->string('response_message', 255)->nullable();

            $table->string('billable', 5)->nullable();         // metadata.billable

            $table->timestamp('request_timestamp')->nullable();
            $table->timestamp('response_timestamp')->nullable();

            // ✅ Store full response JSON (everything)
            $table->json('raw_response')->nullable();

            // Optional: API http status
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_advance_verifications');
    }
};