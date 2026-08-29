<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 任务规则（后台可配置奖励）
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();               // daily_checkin | publish_article | publish_topic
            $table->string('name');
            $table->string('reward_currency', 32)->default('gold');
            $table->decimal('reward_amount', 18, 6)->default(0);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('daily_limit')->default(1); // 每日可完成次数（发布类可>1）
            $table->timestamps();
        });

        // 每日签到记录（user_id + checkin_date 唯一 → 天然幂等）
        Schema::create('quest_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('checkin_date');
            $table->unsignedBigInteger('quest_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_checkins');
        Schema::dropIfExists('quests');
    }
};
