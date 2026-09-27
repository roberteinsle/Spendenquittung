<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Darf Einstellungen und Benutzer verwalten.
            $table->boolean('ist_admin')->default(false)->after('login_pin');
        });

        // Bestehende Installationen: Wer schon ein Konto hat, behält den vollen
        // Zugriff. Sonst käme nach dem Update niemand mehr an die Einstellungen,
        // und es gäbe keinen Weg, das aus der Oberfläche zu reparieren.
        DB::table('users')->update(['ist_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ist_admin');
        });
    }
};
