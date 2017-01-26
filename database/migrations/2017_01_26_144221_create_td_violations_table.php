<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTdViolationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('td_violations', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('td_diff_id')->unsigned();
            $table->string('key');
            $table->string('name');
            $table->text('description');
            $table->string('severity');
            $table->string('defaultDebtChar');
            $table->string('added_or_resolved');

            $table->foreign('td_diff_id')->references('id')->on('td_diffs')->onDelete('cascade');

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
        Schema::drop('td_violations');
    }
}
