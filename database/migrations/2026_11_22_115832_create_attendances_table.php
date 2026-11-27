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
        Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->date('work_date');
    $table->string('status')->default('hadir');
    $table->text('note')->nullable();

    $table->dateTime('check_in_at')->nullable();
    $table->string('check_in_source')->nullable();
    $table->decimal('check_in_lat', 10, 7)->nullable();
    $table->decimal('check_in_lng', 10, 7)->nullable();
    $table->unsignedSmallInteger('check_in_accuracy_m')->nullable();
    $table->unsignedInteger('check_in_distance_m')->nullable();
    $table->boolean('check_in_outside_area')->default(false);
    $table->string('check_in_photo')->nullable();

    $table->dateTime('check_out_at')->nullable();
    $table->string('check_out_source')->nullable();
    $table->decimal('check_out_lat', 10, 7)->nullable();
    $table->decimal('check_out_lng', 10, 7)->nullable();
    $table->unsignedSmallInteger('check_out_accuracy_m')->nullable();
    $table->unsignedInteger('check_out_distance_m')->nullable();
    $table->boolean('check_out_outside_area')->default(false);
    $table->string('check_out_photo')->nullable();

    $table->timestamps();

    $table->unique(['user_id', 'work_date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
