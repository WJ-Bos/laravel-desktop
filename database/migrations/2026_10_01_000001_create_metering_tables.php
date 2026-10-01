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
        Schema::create('headends', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('site')->nullable();
            $table->string('city')->nullable();
            $table->string('protocol', 30)->default('DLMS/COSEM');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('poll_interval_min')->default(30);
            $table->string('status', 20)->default('online');
            $table->dateTime('last_poll_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headend_id')->constrained()->restrictOnDelete();
            $table->string('serial', 20)->unique();
            $table->string('name');
            $table->string('comms', 30)->default('HD-PLC/RF');
            $table->string('firmware', 20)->nullable();
            $table->smallInteger('signal_dbm')->nullable();
            $table->string('status', 20)->default('online');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained()->restrictOnDelete();
            $table->string('serial', 20)->unique();
            $table->string('name');
            $table->string('account')->nullable();
            $table->string('type', 20);
            $table->string('manufacturer', 40);
            $table->string('model', 40);
            $table->string('accuracy_class', 5)->default('1');
            $table->string('ct_ratio', 12)->nullable();
            $table->string('vt_ratio', 16)->nullable();
            $table->string('tariff', 40)->nullable();
            $table->boolean('has_generation')->default(false);
            $table->string('status', 20)->default('active');
            $table->date('installed_at')->nullable();
            $table->dateTime('last_reading_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at');
            $table->decimal('kwh_import', 12, 3);
            $table->decimal('kwh_export', 12, 3)->default(0);
            $table->decimal('kvarh_import', 12, 3)->default(0);
            $table->decimal('kvarh_export', 12, 3)->default(0);
            $table->decimal('kva_demand', 12, 3)->default(0);
            $table->decimal('power_factor', 5, 3)->nullable();
            $table->decimal('voltage_l1', 7, 2)->nullable();
            $table->decimal('voltage_l2', 7, 2)->nullable();
            $table->decimal('voltage_l3', 7, 2)->nullable();
            $table->decimal('current_l1', 9, 2)->nullable();
            $table->decimal('current_l2', 9, 2)->nullable();
            $table->decimal('current_l3', 9, 2)->nullable();
            $table->string('quality', 10)->default('actual');

            $table->unique(['meter_id', 'read_at']);
            $table->index('read_at');
        });

        Schema::create('meter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->dateTime('occurred_at');
            $table->string('type', 30);
            $table->string('severity', 10);
            $table->string('message');
            $table->boolean('acknowledged')->default(false);
            $table->timestamps();

            $table->index('occurred_at');
        });

        Schema::create('user_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->json('data');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_layouts');
        Schema::dropIfExists('meter_events');
        Schema::dropIfExists('readings');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('headends');
    }
};
