<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One SupportRays integration key per Nova user. Kept in the package's own
 * table rather than on `users`, so the host's user model stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nova_supportrays_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            // The SupportRays manager the key acts as, at the time it was issued.
            $table->string('email');
            $table->unsignedBigInteger('key_id')->nullable();
            $table->text('token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nova_supportrays_keys');
    }
};
