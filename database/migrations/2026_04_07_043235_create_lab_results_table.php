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
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained()->cascadeOnDelete();

            // vital
            $table->integer('systolic');
            $table->integer('diastolic');
            $table->decimal('hemoglobin', 5, 2);
            $table->decimal('weight', 5, 2);
            $table->decimal('temperature', 4, 2);

            // infectious screening
            $table->enum('hiv', ['non-reactive', 'reactive']);
            $table->enum('hcv', ['non-reactive', 'reactive']);
            $table->enum('hbsag', ['non-reactive', 'reactive']);
            $table->enum('sifilis', ['non-reactive', 'reactive']);

            $table->text('notes')->nullable();

            // result
            $table->boolean('is_eligible');
            $table->boolean('is_imltd');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
