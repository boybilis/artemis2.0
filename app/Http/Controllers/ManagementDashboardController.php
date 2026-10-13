<?php

namespace App\Http\Controllers;

use App\Models\{Course, Subject, TestBank, TestBankQuestion};
use Illuminate\Support\Facades\{Auth, DB};

class ManagementDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->is_admin || strtolower(trim($user->role ?? '')) !== 'encoder') {
            return app(AdminController::class)->dashboard();
        }
        $courseCounts = TestBankQuestion::selectRaw('course_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active', ['active'])->groupBy('course_id')->get()->keyBy('course_id');
        $banks = TestBank::with('course:id,title')->orderBy('title')->paginate(10, ['*'], 'banks_page');
        $subjects = Subject::with('course:id,title')->orderBy('title')->paginate(20, ['*'], 'subjects_page');
        $subjectCounts = DB::table('test_bank_question_subject as pivot')->join('test_bank_questions as questions', 'questions.id', '=', 'pivot.test_bank_question_id')
            ->selectRaw('pivot.subject_id, COUNT(DISTINCT questions.id) as total')->groupBy('pivot.subject_id')->pluck('total', 'subject_id');
        $stats = [
            ['label' => 'Courses', 'value' => Course::count()], ['label' => 'Subjects', 'value' => Subject::count()],
            ['label' => 'Test Banks', 'value' => TestBank::count()], ['label' => 'Test Bank questions', 'value' => TestBankQuestion::count()],
        ];
        return view('admin.encoder-dashboard', compact('stats', 'banks', 'subjects', 'courseCounts', 'subjectCounts'));
    }
}
