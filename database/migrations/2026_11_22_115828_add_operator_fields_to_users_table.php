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
    Schema::table('users', function (Blueprint $table) {
        $table->string('username')->unique()->after('name');
        $table->string('email')->nullable()->change();
        $table->string('role')->default('operator');
        $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('work_system_id')->nullable()->constrained()->nullOnDelete();
        $table->boolean('is_active')->default(true);
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('site_id');
        $table->dropConstrainedForeignId('shift_id');
        $table->dropConstrainedForeignId('work_system_id');
        $table->dropColumn(['username', 'role', 'is_active']);
    });
}
};
