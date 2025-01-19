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
        Schema::create('materials', function (Blueprint $table) {
            $table->id('MaterialID');
            $table->string('Title');
            $table->text('Description')->nullable();
            $table->string('FilePath')->nullable();
            $table->string('VideoPath')->nullable();
            $table->enum('MaterialType', ['pdf', 'video', 'text']);
            $table->foreignId('CourseID')->constrained('courses', 'CourseID')->onDelete('cascade');
            $table->foreignId('ProfessorID')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};