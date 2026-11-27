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
        Schema::create('timesheet_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('work_date');
    $table->string('job_type');
    $table->time('start_time');
    $table->time('end_time');
    $table->text('note')->nullable();
    $table->string('source')->default('operator');
    $table->timestamps();

    $table->index(['user_id', 'work_date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
    }
};
