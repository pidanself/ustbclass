<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Carbon\Carbon;
use App\Helpers\Elearning;
use App\Models\Vars;
use App\Models\User;
use App\Models\Course;

class UpdateCourses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $users = User::where('elearning_id', '<>', null)
            ->where('elearning_pwd', '<>', null)
            ->where('coursed_at', null)
            ->where('coursed_at', '<', Carbon::now()->startOfWeek())
            ->all();

        if (count($users) > 0) {
            foreach ($users as $user) {
                $user->coursed_at = Carbon::now();

                try {
                    $elearning = new Elearning($user->elearning_id, $user->elearning_pwd, Vars::get('current_week') + 1);
                    $logged = $elearning->login();
                    if (!$logged) {
                        $user->save();
                        continue;
                    }

                    $courseList = $elearning->courseList();
                    $parsed = $elearning->getParsedCourses($courseList);

                    $course = Course::where('user_id', $user->id)->firstOrNew();
                    $course->user_id = $user->id;
                    $course->table = $parsed;
                    $course->save();

                    $user->save();

                    Mail::to($user)->queue(new \App\Mail\WeeklyCalender($user));

                    break;
                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }
}