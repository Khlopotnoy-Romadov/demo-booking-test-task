<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestampTz('starts_at')->change();
            $table->timestampTz('ends_at')->change();
            $table->index(['room_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'starts_at']);
            $table->timestamp('starts_at')->change();
            $table->timestamp('ends_at')->change();
        });
    }
};
