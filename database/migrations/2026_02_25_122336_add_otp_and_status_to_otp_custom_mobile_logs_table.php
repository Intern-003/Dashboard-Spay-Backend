<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('otp_custom_mobile_logs', function (Blueprint $table) {

            // ✅ Add OTP column
            $table->string('otp', 10)->nullable()->after('mobile');

            // ✅ Add ENUM status column
            $table->enum('otp_status', ['active', 'inactive'])
                  ->default('active')
                  ->after('otp');

        });
    }

    public function down(): void
    {
        Schema::table('otp_custom_mobile_logs', function (Blueprint $table) {
            $table->dropColumn(['otp', 'otp_status']);
        });
    }
};