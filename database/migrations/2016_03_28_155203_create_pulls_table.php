<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePullsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pulls', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('repo_id')->unsigned();
            $table->integer('issue_id')->unsigned();
            $table->integer('number')->unsigned();
            $table->boolean('is_merged');
            $table->dateTime('merged_at')->nullable();
            $table->string('merged_by')->nullable();
            $table->integer('commits_count')->unsigned()->nullable();
            $table->integer('additions')->unsigned()->nullable();
            $table->integer('deletions')->unsigned()->nullable();
            $table->integer('changed_files_count')->unsigned()->nullable();

            //$table->unique(['repo_id', 'number']);

            $table->foreign('repo_id')->references('id')->on('repos')->onDelete('cascade');
            $table->foreign('issue_id')->references('id')->on('issues')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('pulls');
    }
}
