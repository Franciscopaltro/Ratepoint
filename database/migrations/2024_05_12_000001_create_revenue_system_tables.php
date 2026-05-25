<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('boundary_coords')->nullable(); // For Geo-fencing
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name');
            $table->decimal('gps_lat', 10, 8);
            $table->decimal('gps_lng', 11, 8);
            $table->foreignId('zone_id')->constrained();
            $table->string('structure_type'); // Permanent, Temporary, etc.
            $table->string('levy_type');
            $table->decimal('fee_amount', 10, 2);
            $table->enum('status', ['paid', 'unpaid', 'pending'])->default('unpaid');
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('agent_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('cash');
            $table->string('receipt_number')->unique();
            $table->decimal('gps_lat', 10, 8);
            $table->decimal('gps_lng', 11, 8);
            $table->string('offline_sync_id')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained();
            $table->foreignId('finance_officer_id')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'verified', 'suspicious'])->default('pending');
            $table->decimal('confirmed_amount', 10, 2)->nullable();
            $table->string('bank_slip_number')->nullable();
            $table->timestamp('bank_deposit_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('suspicious_activities', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // GPS_MISMATCH, SEQUENCE_GAP, LATE_BANKING
            $table->foreignId('related_id')->nullable(); // collection_id or user_id
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high']);
            $table->enum('status', ['open', 'investigating', 'resolved'])->default('open');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('suspicious_activities');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('zones');
    }
};
