<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spender', function (Blueprint $table) {
            $table->id();
            $table->string('spendernummer', 8)->unique(); // "8" + 4 random digits
            $table->string('anrede', 20)->nullable();
            $table->string('firma')->nullable();
            $table->string('vorname')->nullable();
            $table->string('nachname');
            $table->string('strasse')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();
            $table->string('email')->nullable();
            $table->boolean('duzen')->default(false);
            $table->text('bemerkung')->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['nachname', 'plz']); // for donor matching
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spender');
    }
};
