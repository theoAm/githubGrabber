<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommitFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commit_files', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('repo_id')->unsigned();
            $table->integer('commit_id')->unsigned();
            $table->string('filename');
            $table->integer('additions')->unsigned()->nullable();
            $table->integer('deletions')->unsigned()->nullable();
            $table->integer('changes')->unsigned()->nullable();
            $table->string('status')->nullable();

            $table->foreign('repo_id')->references('id')->on('repos')->onDelete('cascade');
            $table->foreign('commit_id')->references('id')->on('commits')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('commit_files');
    }
}
