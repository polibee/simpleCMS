<?php

use Illuminate\Support\Facades\Schedule;

// 定时发布：每分钟检查到达发布时间的文章
Schedule::command('cms:publish-scheduled')->everyMinute();
