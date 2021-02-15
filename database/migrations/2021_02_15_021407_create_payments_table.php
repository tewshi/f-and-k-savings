<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->index()->unique();
            $table->integer('amount');
            $table->integer('fees');
            $table->enum('status', ['success', 'failed'])->default('failed');
            $table->foreignId('user_id')->nullable()->constrained()
                ->onDelete('set null')->onUpdate('cascade');
            $table->foreignId('wallet_id')->nullable()->constrained()
                ->onDelete('set null')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
