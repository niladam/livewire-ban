<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 45);
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->string('component')->nullable();
            $table->string('cf_country', 8)->nullable();
            $table->json('context')->nullable();
            $table->unsignedTinyInteger('strikes');
            $table->unsignedSmallInteger('offence');
            $table->timestamp('banned_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('unbanned_at')->nullable();
            $table->unsignedBigInteger('unbanned_by')->nullable();
            $table->timestamps();

            $table->index(['ip', 'banned_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return Config::string('livewire-ban.table', 'livewire_bans');
    }
};
