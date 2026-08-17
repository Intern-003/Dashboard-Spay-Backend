<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digilocker_pan_fetch_logs', function (Blueprint $table) {
            $table->id();

            $table->string('request_id')->index();
            $table->boolean('success')->default(false)->nullable();
            $table->string('status')->nullable();
            $table->string('document_name')->nullable();
            $table->string('issuer')->nullable();

            $table->dateTime('issue_date')->nullable();
            $table->dateTime('fetched_at')->nullable();

            $table->string('certificate_number')->nullable();
            $table->string('pan_number')->nullable()->index();
            $table->string('holder_name')->nullable();
            $table->string('holder_dob')->nullable();

            $table->longText('pdf_base64')->nullable();

            $table->json('raw_response')->nullable();
            $table->integer('http_status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digilocker_pan_fetch_logs');
    }
};