<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCheatingScoresTable extends Migration
{
    public function up()
    {
        Schema::create('cheating_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained('quizzes', 'QuizID')->onDelete('cascade');

            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cheating_scores');
    }
}
