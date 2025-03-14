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
        Schema::create('quiz_results', function (Blueprint $table) {
            $table->id('QuizResultID');
            $table->integer('Score');
            $table->decimal('Percentage', 5, 2);
            $table->boolean('Passed');
            $table->timestamp('SubmittedAt');
            $table->foreignId('StudentID')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('QuizID')->constrained('quizzes', 'QuizID')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_results');
    }
};