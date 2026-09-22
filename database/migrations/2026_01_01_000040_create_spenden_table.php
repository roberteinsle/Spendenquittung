<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spenden', function (Blueprint $table) {
            $table->id();
            $table->string('bescheinigungsnummer', 10)->unique(); // YYxxxx, e.g. "264711"
            $table->foreignId('spender_id')->constrained('spender')->restrictOnDelete();
            $table->date('spendendatum');
            $table->decimal('betrag', 10, 2);
            $table->string('betrag_in_worten');
            $table->foreignId('foerderungszweck_id')->constrained('foerderungszwecke')->restrictOnDelete();
            $table->string('anlass')->nullable();
            $table->string('ankreuzfeld', 30); // vermoegenstock | unmittelbar
            $table->date('ausstellungsdatum');
            $table->string('status', 20)->default('erfasst');
            $table->string('alte_lfd_nr', 50)->nullable(); // historical import field
            $table->string('pdf_pfad')->nullable();
            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('spender_id');
            $table->index('status');
            $table->index('spendendatum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spenden');
    }
};
