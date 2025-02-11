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
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['quiz', 'material'])->after('Message');
            $table->foreignId('CourseID')->nullable()->constrained('courses', 'CourseID')->onDelete('cascade')->after('RecipientID'); // ربط بالكورسات
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropForeign(['CourseID']);
            $table->dropColumn('CourseID');
        });
    }
};
