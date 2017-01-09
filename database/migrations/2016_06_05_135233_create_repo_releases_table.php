<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRepoReleasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('repo_releases', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('repo_id')->unsigned();
            $table->string('name');
            $table->dateTime('released_at');

            $table->foreign('repo_id')->references('id')->on('repos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('repo_releases');
    }
}
