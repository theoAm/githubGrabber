<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTdDiffsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('td_diffs', function (Blueprint $table) {
            $table->increments('id')->unsigned();

            $table->integer('repo_id')->unsigned();
            $table->string('author');
            $table->string('commit_sha');
            $table->string('previous_commit_sha');
            $table->string('filename');
            $table->float('sqale_index_diff');
            $table->float('sqale_debt_ratio_diff');
            $table->integer('blocker_violations_diff');
            $table->integer('critical_violations_diff');
            $table->integer('major_violations_diff');
            $table->integer('minor_violations_diff');
            $table->integer('info_violations_diff');

            $table->unique(['repo_id', 'commit_sha', 'filename']);
            $table->foreign('repo_id')->references('id')->on('repos')->onDelete('cascade');

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
        Schema::drop('td_diffs');
    }
}
