<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->dateTime('time_in')->nullable();
            $table->decimal('time_in_latitude', 10, 7)->nullable();
            $table->decimal('time_in_longitude', 10, 7)->nullable();
            $table->string('time_in_address')->nullable();
            $table->string('time_in_selfie')->nullable();
            $table->foreignId('time_in_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('time_in_ip')->nullable();
            $table->boolean('time_in_mock_location_flag')->default(false);
            $table->decimal('time_in_distance_meters', 8, 2)->nullable();

            $table->dateTime('time_out')->nullable();
            $table->decimal('time_out_latitude', 10, 7)->nullable();
            $table->decimal('time_out_longitude', 10, 7)->nullable();
            $table->string('time_out_address')->nullable();
            $table->string('time_out_selfie')->nullable();
            $table->foreignId('time_out_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('time_out_ip')->nullable();
            $table->boolean('time_out_mock_location_flag')->default(false);
            $table->decimal('time_out_distance_meters', 8, 2)->nullable();

            $table->decimal('working_hours', 5, 2)->nullable();
            $table->enum('status', [
                'present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'weekend',
            ])->default('present');
            $table->boolean('is_regularized')->default(false);
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
