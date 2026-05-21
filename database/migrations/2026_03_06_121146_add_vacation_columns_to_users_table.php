<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('email');
            $table->tinyInteger('weekly_working_days')->default(5)->after('hire_date');
            $table->boolean('has_severe_disability')->default(false)->after('weekly_working_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hire_date', 'weekly_working_days', 'has_severe_disability']);
        });
    }
};
