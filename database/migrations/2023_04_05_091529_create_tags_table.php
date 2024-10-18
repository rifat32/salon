<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string("tag")->nullable();
            $table->boolean("is_default")->default(true);
            $table->unsignedBigInteger("garage_id")->nullable();
            $table->timestamps();
        });
        $defaultTags =  [
            "Excellent",
         "Very Good",
         "Good",
         "Fair",
         "Poor",
         "Outstanding",
         "Above Average",
         "Average",
         "Below Average",
         "Terrible",
         "Highly Recommend",
         "Recommend",
         "Neutral",
         "Do Not Recommend",
         "Exceptional",
         "Satisfactory",
         "Unsatisfactory",
         "Superb",
         "Mediocre",
         "Disappointing",
         "Flawless",
         "Needs Improvement"
        ];

        foreach($defaultTags as $tag){

            DB::table("tags")
            ->insert([
                "tag" => $tag,
                "is_default" => true

            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tags');
    }
}
