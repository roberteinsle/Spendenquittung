<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protokolle', function (Blueprint $table) {
            $table->id();

            $table->foreignId('benutzer_id')->nullable()->constrained('users')->nullOnDelete();
            // Der Name wird mitgeschrieben: ein Prüfpfad, der beim Löschen des
            // Kontos "unbekannt" anzeigt, ist wertlos.
            $table->string('benutzer_name')->nullable();

            $table->string('aktion', 30);

            // Zeigt auf Spende oder Spender. Bleibt erhalten, auch wenn der
            // Datensatz später endgültig gelöscht wird.
            $table->nullableMorphs('betrifft');
            $table->string('bezeichnung');

            $table->text('beschreibung')->nullable();
            $table->json('aenderungen')->nullable();

            $table->timestamps();

            $table->index('aktion');
            $table->index('created_at');
            $table->index('benutzer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protokolle');
    }
};
