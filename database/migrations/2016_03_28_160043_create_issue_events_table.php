<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIssueEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('issue_events', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->integer('repo_id')->unsigned();
            $table->integer('issue_id')->unsigned();
            $table->integer('issue_number')->unsigned();
            $table->integer('github_id')->unsigned();
            $table->string('actor')->nullable();
            $table->string('event_description');
            $table->string('commit_sha')->nullable();
            $table->dateTime('created_at')->nullable();

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
        Schema::drop('issue_events');
    }
}
