<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foerderungszwecke', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('text');
            $table->boolean('aktiv')->default(true);
            $table->unsignedSmallInteger('sortierung')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foerderungszwecke');
    }
};
