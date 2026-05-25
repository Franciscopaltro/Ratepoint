<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('field_agent'); // super_admin, finance_officer, supervisor, field_agent
            $table->foreignId('zone_id')->nullable()->constrained();
            $table->string('phone_number')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'zone_id', 'phone_number', 'is_active']);
        });
    }
};
