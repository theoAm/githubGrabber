<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIssuesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('repo_id')->unsigned();
            $table->integer('number')->unsigned();
            $table->string('state');
            $table->string('labels')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('created_by');
            $table->string('assignee')->nullable();
            $table->boolean('is_pull_request');
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('closed_by')->nullable();

            //$table->unique(['repo_id', 'number']);

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
        Schema::drop('issues');
    }
}
