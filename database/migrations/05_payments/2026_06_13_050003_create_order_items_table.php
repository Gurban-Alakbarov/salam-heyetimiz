<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Arch §5.2 — order_items. referenced_id points to device_id / subscription_id by convention
// (no hard FK; app-enforced). Device-sale items must reference an existing unassigned device (R-DOM-13).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('item_type', ['device', 'sub_main', 'sub_additional', 'sub_renewal']);
            $table->unsignedBigInteger('referenced_id')->nullable();
            $table->string('description', 160);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount_minor');
            $table->unsignedInteger('total_amount_minor');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id', 'idx_order_items_order');
            $table->index(['item_type', 'referenced_id'], 'idx_order_items_type_ref');

            $table->foreign('order_id', 'fk_order_items_order_id')
                ->references('id')->on('orders')
                ->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
