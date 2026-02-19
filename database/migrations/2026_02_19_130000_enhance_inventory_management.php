<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create suppliers table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // 2. Enhance product_categories table
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->integer('sort_order')->default(0)->after('name_ar');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('sort_order');
        });

        // 3. Add new fields to products table
        Schema::table('products', function (Blueprint $table) {
            // Supplier relationship
            $table->foreignId('supplier_id')->nullable()->after('product_category_id')->constrained('suppliers')->nullOnDelete();

            // Cost and pricing
            $table->decimal('cost_price', 10, 2)->nullable()->after('price');

            // Inventory management
            $table->integer('reorder_point')->default(0)->after('max_quantity');

            // Dates for tracking
            $table->date('production_date')->nullable()->after('reorder_point');
            $table->date('expiry_date')->nullable()->after('production_date');

            // Unit of sale
            $table->enum('unit_of_sale', ['piece', 'unit', 'pack', 'box', 'bottle', 'tube', 'set'])->default('piece')->after('expiry_date');

            // Sales channel - where can it be sold
            $table->enum('sales_channel', ['online', 'in_store', 'both'])->default('both')->after('unit_of_sale');

            // Product type - who is it for
            $table->enum('product_type', ['retail', 'professional', 'both'])->default('retail')->after('sales_channel');
        });
    }

    public function down(): void
    {
        // Remove product fields
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'cost_price',
                'reorder_point',
                'production_date',
                'expiry_date',
                'unit_of_sale',
                'sales_channel',
                'product_type',
            ]);
        });

        // Remove product_categories enhancements
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'sort_order', 'status']);
        });

        // Drop suppliers table
        Schema::dropIfExists('suppliers');
    }
};
