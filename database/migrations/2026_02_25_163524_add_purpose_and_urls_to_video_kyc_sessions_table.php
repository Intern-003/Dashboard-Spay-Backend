<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('video_kyc_sessions', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('session_id');
            $table->string('response_url')->nullable();
            $table->string('redirect_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('video_kyc_sessions', function (Blueprint $table) {
            $table->dropColumn(['purpose','response_url','redirect_url']);
        });
    }
};