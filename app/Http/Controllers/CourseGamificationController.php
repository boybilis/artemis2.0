<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseGamificationController extends Controller
{
    private const ACTIVITIES = [
        'content' => ['Complete a learning item', 50, 5],
        'assessment' => ['Pass a course assessment', 100, 2],
        'course' => ['Complete the review course', 1000, 1],
        'certificate' => ['Earn a course certificate', 150, 1],
    ];

    public function index(Request $request)
    {
        $courses = Course::orderBy('title')->get(['id', 'title']);
        $course = $request->filled('course') ? $courses->firstWhere('id', $request->integer('course')) : $courses->first();
        $settings = $course ? Storage::disk('local')->json($this->path($course)) ?? [] : [];

        return view('admin.content.gamification', ['courses' => $courses, 'course' => $course, 'settings' => $settings, 'activities' => self::ACTIVITIES]);
    }

    public function update(Request $request, Course $course)
    {
        $rules = array_fill_keys(['enabled', 'points_enabled', 'badges_enabled'], 'required|boolean');
        foreach (self::ACTIVITIES as $key => $activity) {
            $rules["points.$key"] = 'required|integer|min:0|max:10000';
            $rules["badges.$key"] = 'required|integer|min:0|max:1000';
        }
        $settings = $request->validate($rules);
        abort_unless(Storage::disk('local')->put($this->path($course), json_encode($settings, JSON_THROW_ON_ERROR)), 500, 'Settings could not be saved.');

        return redirect()->route('admin.gamification.index', ['course' => $course->id])->with('success', 'Course gamification settings saved.');
    }

    private function path(Course $course): string
    {
        return 'course-gamification/'.$course->id.'.json';
    }
}
