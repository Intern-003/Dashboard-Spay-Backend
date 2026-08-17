<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('video_kyc_sessions', function (Blueprint $table) {
      $table->id();
      $table->uuid('session_id')->unique()->index();

      $table->unsignedBigInteger('user_id')->nullable()->index(); // if you have auth
      $table->string('customer_name')->nullable();
      $table->string('customer_email')->nullable();
      $table->string('customer_mobile', 20)->nullable();

      $table->enum('status', ['created','started','uploaded','verified','rejected'])->default('created')->index();

      $table->string('video_path')->nullable();
      $table->string('video_mime', 50)->nullable();
      $table->unsignedBigInteger('video_size')->nullable();

      $table->timestamp('started_at')->nullable();
      $table->timestamp('ended_at')->nullable();

      $table->json('meta')->nullable(); // device, ip, user-agent, etc.
      $table->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('video_kyc_sessions');
  }
};