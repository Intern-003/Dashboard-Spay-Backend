<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('credentials', function (Blueprint $table) {

            // column add
            $table->unsignedBigInteger('bank_id')->nullable()->after('id');

            // foreign key
            $table->foreign('bank_id')
                  ->references('id')
                  ->on('payin_onboarded_banks')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('credentials', function (Blueprint $table) {

            $table->dropForeign(['bank_id']);
            $table->dropColumn('bank_id');
        });
    }
};