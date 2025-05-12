<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCheatingScoreToQuizResults extends Migration
{
    public function up()
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->integer('CheatingScore')->default(0)->after('Passed');
        });
    }

    public function down()
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn('CheatingScore');
        });
    }
}
