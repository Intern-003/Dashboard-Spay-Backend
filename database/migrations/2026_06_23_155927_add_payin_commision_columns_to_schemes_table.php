<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schemes', function (Blueprint $table) {

            $table->enum('payin_commision_type_below', ['flat', 'percent'])
                ->default('flat')
                ->nullable()
                ->after('payin_commision_amount');

            $table->string('payin_commision_amount_below')
                ->nullable()
                ->after('payin_commision_type_below');

            $table->enum('payin_commision_type_above', ['flat', 'percent'])
                ->default('percent')
                ->nullable()
                ->after('payin_commision_amount_below');

            $table->string('payin_commision_amount_above')
                ->nullable()
                ->after('payin_commision_type_above');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schemes', function (Blueprint $table) {

            $table->dropColumn([
                'payin_commision_type_below',
                'payin_commision_amount_below',
                'payin_commision_type_above',
                'payin_commision_amount_above',
            ]);
        });
    }
};