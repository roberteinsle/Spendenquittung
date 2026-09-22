<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versandprotokolle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spende_id')->constrained('spenden')->cascadeOnDelete();
            $table->string('kanal', 20); // druck, email, post
            $table->timestamp('zeitpunkt');
            $table->string('empfaenger')->nullable();
            $table->string('ergebnis', 20); // success, fehler
            $table->text('nachricht')->nullable();
            $table->foreignId('ausgefuehrt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versandprotokolle');
    }
};
