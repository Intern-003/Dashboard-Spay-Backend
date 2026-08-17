<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digilocker_aadhaar_fetch_logs', function (Blueprint $table) {
            $table->id();

            $table->string('request_id')->index();
            $table->boolean('success')->default(false);

            $table->string('status')->nullable();
            $table->string('document_name')->nullable();
            $table->string('issuer')->nullable();
            $table->timestamp('issue_date')->nullable();
            $table->timestamp('fetched_at')->nullable();

            $table->string('name')->nullable();
            $table->string('dob')->nullable();
            $table->string('gender',10)->nullable();
            $table->string('uid')->nullable();

            $table->text('photo_base64')->nullable();
            $table->json('raw_response')->nullable();

            $table->unsignedSmallInteger('http_status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digilocker_aadhaar_fetch_logs');
    }
};