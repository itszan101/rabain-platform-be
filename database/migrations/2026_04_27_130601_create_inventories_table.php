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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();

            $table->string('bag_id')->unique();
            $table->foreignId('donor_id')->nullable()->constrained()->nullOnDelete();

            $table->string('blood_type');
            $table->string('rhesus');

            $table->date('donation_date');
            $table->date('expired_date');

            $table->foreignId('category_id')->constrained('inventory_categories');

            $table->string('status')->default('available'); // available, used, expired

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
