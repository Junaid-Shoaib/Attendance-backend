<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Device binding: one approved primary device per employee.
        // New device logins require admin approval to reduce credential sharing.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id'); // unique hardware/installation identifier
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable(); // ios / android
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->boolean('is_rooted_or_jailbroken')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
