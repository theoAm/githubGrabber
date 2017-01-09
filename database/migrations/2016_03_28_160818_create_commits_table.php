<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commits', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('repo_id')->unsigned();
            $table->integer('pull_id')->unsigned()->nullable();
            $table->string('sha');
            $table->string('author');
            $table->string('committer');
            $table->text('message')->nullable();
            $table->dateTime('authored_at');
            $table->dateTime('committed_at');

            //$table->unique(['repo_id', 'sha']);

            $table->foreign('repo_id')->references('id')->on('repos')->onDelete('cascade');
            $table->foreign('pull_id')->references('id')->on('pulls')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('commits');
    }
}
