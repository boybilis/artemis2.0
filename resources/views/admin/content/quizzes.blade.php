@extends('admin.layouts.app')

@section('title', 'Assessments')
@section('kicker', 'Assessment Management')

@section('header_actions')
    <button class="btn btn-primary" type="button" onclick="openAddQuestionModal()">Add question</button>
@endsection

@section('content')
@php
    $isAdmin = Auth::user()->is_admin || trim(strtolower(Auth::user()->role)) === 'admin';
    $isInstructor = trim(strtolower(Auth::user()->role)) === 'instructor' && !Auth::user()->is_admin;
@endphp
<style>
.matrix-builder-toolbar{display:flex;justify-content:space-between;gap:1rem;align-items:center;margin-bottom:1rem}.matrix-builder-toolbar small{display:block;color:var(--text-muted);margin-top:.25rem}.matrix-builder-toolbar>div:last-child{display:flex;gap:.5rem}.matrix-column-card{display:grid;grid-template-columns:1fr 180px auto;gap:.6rem;align-items:end;padding:.75rem;border:1px solid var(--border);border-radius:9px;margin-bottom:.6rem}.matrix-author-table{min-width:850px}.matrix-cell-options{display:grid;gap:.45rem;min-width:300px}.matrix-choice-row{display:grid;grid-template-columns:auto 1fr 85px auto;gap:.4rem;align-items:center}.matrix-preview-panel{margin-top:1rem;padding:1rem;border:1px solid var(--border);border-radius:10px}.matrix-preview-table{width:100%;min-width:650px;border-collapse:collapse}.matrix-preview-table th,.matrix-preview-table td{padding:.75rem;border:1px solid var(--border);text-align:left}.matrix-preview-table select{width:100%}.mock-settings-badge{display:block;margin-top:.45rem;border:1px solid rgba(124,58,237,.3);cursor:pointer;font:inherit}.mock-settings-badge:hover{border-color:var(--accent);filter:brightness(1.08)}@media(max-width:700px){.matrix-column-card{grid-template-columns:1fr}.matrix-builder-toolbar{align-items:flex-start;flex-direction:column}}
.assessment-pass-rule-badge,.mock-exam-settings-badge{display:flex;align-items:center;width:max-content;margin-top:.35rem;padding:.25rem .52rem;border:1px solid rgba(96,165,250,.38);border-radius:6px;background:linear-gradient(135deg,#0f2747,#174a82);color:#fff;font-size:.64rem;font-weight:700;line-height:1.15;letter-spacing:.01em;box-shadow:0 2px 7px rgba(15,39,71,.2)}.assessment-pass-rule-badge:hover,.mock-exam-settings-badge:hover{border-color:#60a5fa;background:linear-gradient(135deg,#12345d,#1d5b9e);color:#fff;filter:none;transform:translateY(-1px)}
.cloze-builder-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.cloze-builder-toolbar small{display:block;margin-top:.25rem;color:var(--text-muted)}.cloze-blank-card{margin-bottom:.75rem;padding:1rem;border:1px solid var(--border);border-radius:10px;background:var(--surface)}.cloze-blank-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.75rem}.cloze-preview-text{font-size:1.05rem;line-height:2.25}.cloze-preview-text select,.cloze-answer-select{display:inline-block;width:auto;min-width:150px;margin:0 .25rem;padding:.4rem .65rem;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text)}@media(max-width:700px){.cloze-builder-toolbar{align-items:flex-start;flex-direction:column}.cloze-blank-grid{grid-template-columns:1fr}.cloze-preview-text select{max-width:100%}}
.highlight-builder-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.highlight-segment-row{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.65rem;margin-bottom:.6rem}.highlight-segment-row>input[type=checkbox]{width:20px;height:20px;accent-color:#f59e0b}.highlight-preview-passage,.highlight-question-passage{padding:1rem;border:1px solid var(--border);border-radius:10px;background:var(--surface);font-size:1rem;line-height:2.1}.highlight-choice{display:inline;padding:.18rem .28rem;border:1px solid transparent;border-radius:4px;background:transparent;color:inherit;font:inherit;line-height:inherit;cursor:pointer}.highlight-choice:hover{background:rgba(245,158,11,.12)}.highlight-choice.selected{border-color:#f59e0b;background:#fbbf24;color:#422006}@media(max-width:700px){.highlight-builder-toolbar{align-items:flex-start;flex-direction:column}}
.bank-preview-question,.preview-question-card #bank_preview_question,.cloze-preview-text{white-space:pre-wrap;overflow-wrap:anywhere}
.question-preview-modal-content{max-width:900px}.bank-preview-question{font-size:1.2rem;line-height:1.6;margin:0 0 1.25rem}.bank-preview-options{display:grid;gap:.7rem}.bank-preview-option{display:flex;align-items:center;gap:.8rem;width:100%;padding:.9rem 1rem;border:1px solid var(--border);border-radius:10px;background:transparent;color:var(--text);text-align:left}.bank-preview-option span:first-child{display:inline-flex;width:28px;height:28px;align-items:center;justify-content:center;border-radius:50%;border:1px solid var(--border);font-weight:700}.bank-preview-matrix{width:100%;min-width:650px;border-collapse:collapse}.bank-preview-matrix th,.bank-preview-matrix td{padding:.8rem;border:1px solid var(--border);text-align:left}.bank-preview-matrix th{background:rgba(124,58,237,.08)}.bank-preview-matrix select{width:100%}.bank-preview-matrix label{display:flex;gap:.45rem;align-items:center;margin:.3rem 0}
#questionPreviewModal{z-index:10001;padding:0;align-items:stretch;overflow:hidden;background:#f3f7fb;overscroll-behavior:none}#questionPreviewModal.open{display:flex}.quiz-preview-shell{display:flex;flex-direction:column;width:100vw;max-width:none;max-height:100dvh;height:100dvh;margin:0;padding:0;overflow:hidden;border:0;border-radius:0;background:#f3f7fb}.preview-exam-header{display:grid;grid-template-columns:minmax(230px,auto) minmax(260px,1fr) auto;align-items:center;gap:2rem;flex:0 0 88px;padding:0 2.75rem;background:#073b63;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.18);z-index:2}.preview-exam-brand{display:flex;align-items:center;gap:.75rem;white-space:nowrap}.preview-exam-mark{display:inline-flex;width:38px;height:38px;align-items:center;justify-content:center;border-radius:8px;background:#fff;color:#073b63;font-weight:900}.preview-exam-brand strong{font-size:1.05rem}.preview-exam-title{padding-left:.75rem;border-left:1px solid rgba(255,255,255,.35);font-weight:800}.preview-exam-progress{min-width:0}.preview-exam-meta{display:flex;justify-content:space-between;gap:1rem;margin-bottom:.55rem;font-size:.82rem}.preview-exam-track{height:10px;overflow:hidden;border:1px solid rgba(255,255,255,.38);border-radius:99px;background:rgba(255,255,255,.28)}.preview-exam-track span{display:block;width:100%;height:100%;background:linear-gradient(90deg,#ff6b2c,#ff9a52)}.quiz-preview-close{display:block;position:static;width:46px;height:46px;border:1px solid rgba(255,255,255,.75);border-radius:50%;background:transparent;color:#fff;font-size:1.7rem}.preview-exam-body{flex:1 1 0;min-height:0;overflow-x:hidden;overflow-y:auto;padding:2.75rem 2rem;-webkit-overflow-scrolling:touch}.preview-question-card{width:min(1050px,100%);min-height:100%;margin:0 auto;padding:2.5rem 2.75rem;border:1px solid #d8e2ec;border-radius:20px;background:#fff;box-shadow:0 12px 34px rgba(7,59,99,.08);color:#062f50}.preview-response-label{display:inline-flex;margin-bottom:1.35rem;padding:.45rem .8rem;border-radius:999px;background:#e7f1fa;color:#07528a;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.preview-question-card #bank_preview_question{margin:0 0 .55rem;color:#062f50;font-size:1.45rem;line-height:1.5}.preview-answer-instruction{margin:0 0 1.4rem;color:#64748b}.preview-question-card .options-list{gap:.75rem}.preview-question-card .quiz-option{min-height:58px;border-color:#cfdae6;border-radius:10px;background:#fff;color:#163b59}.preview-question-card .quiz-option .opt-letter{border:0;border-radius:0;background:transparent;color:#064b7c}.preview-exam-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex:0 0 88px;padding:1rem 2.25rem;border-top:1px solid #d8e2ec;background:#fff;box-shadow:0 -6px 18px rgba(7,59,99,.06);z-index:2}.preview-footer-controls{display:flex;align-items:center;gap:1.2rem}.preview-previous{min-height:46px;padding:.7rem 1.15rem;border:1px solid #d8e2ec;border-radius:10px;background:#fbfcfd;color:#a1adba;font:inherit;font-weight:700}.preview-review{display:flex;align-items:center;gap:.65rem;color:#073b63;font-weight:800}.preview-review input{width:21px;height:21px;margin:0;accent-color:#ff6b2c}.preview-submit{min-width:165px;background:linear-gradient(135deg,#ff8b5b,#ffb08e)}@media(max-width:800px){.preview-exam-header{grid-template-columns:1fr auto;gap:.75rem;flex-basis:108px;padding:1rem}.preview-exam-brand strong{display:none}.preview-exam-title{font-size:.82rem}.preview-exam-progress{grid-column:1/-1;grid-row:2}.preview-exam-body{padding:1.25rem .75rem}.preview-question-card{min-height:auto;padding:1.4rem 1rem;border-radius:14px}.preview-question-card #bank_preview_question{font-size:1.1rem}.preview-exam-footer{flex-basis:72px;padding:.75rem 1rem}.preview-footer-controls{gap:.75rem}.preview-review span{font-size:.82rem}.preview-submit{min-width:132px;padding:.75rem 1rem}}@media(max-width:520px){.preview-exam-footer{align-items:stretch;flex-basis:auto;flex-direction:column;padding:.65rem .75rem}.preview-footer-controls{justify-content:space-between}.preview-submit{width:100%}}
</style>
<div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
    <a href="{{ route('admin.content.subjects', $course->id) }}" class="btn-ghost" style="padding: 0.5rem; color: var(--text-muted); text-decoration: none;"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Subjects</a>
    <h2 style="margin:0; font-family: 'Outfit', sans-serif;">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</h2>
</div>

<div class="tabs">
    <a class="tab" href="{{ route('admin.content.subjects', $course->id) }}">Subjects</a>
    <a class="tab" href="{{ route('admin.content.topics', ['course' => $course->id, 'subject_id' => $managedSubject->id]) }}">Topics and subtopics</a>
    <a class="tab active" href="{{ route('admin.content.quizzes', ['course' => $course->id, 'subject_id' => $managedSubject->id]) }}">Assessments</a>
</div>

<div class="split-grid">
    <section class="panel" data-ajax-table="assessment-sets-table">
        <div style="display:flex;align-items:end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
            <div><p class="panel-label">Assessment sets</p><h2 class="panel-title" style="margin-bottom:0">Assessments</h2></div>
            <form action="{{ route('admin.content.quizzes', $course->id) }}" method="GET" data-assessment-sets-search style="display:flex;gap:.5rem;margin:0">
                <input type="hidden" name="subject_id" value="{{ $managedSubject->id }}">
                @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                <input type="search" name="sets_search" value="{{ request('sets_search') }}" placeholder="Search assessment sets..." aria-label="Search assessment sets" style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);min-width:250px">
                <button type="submit" class="btn-primary" style="padding:.5rem 1rem">Search</button>
                @if(request('sets_search'))<a href="{{ route('admin.content.quizzes', array_filter(['course' => $course->id, 'subject_id' => $managedSubject->id, 'search' => request('search')])) }}" data-assessment-sets-clear class="btn-ghost" style="padding:.5rem .75rem;text-decoration:none">Clear</a>@endif
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Assessment Set</th><th>Questions count</th><th>Pass rule</th><th>Status</th></tr></thead>
                <tbody>
                    @php
                        $assessmentLabels = ['pre_test' => 'Pre-test', 'post_test' => 'Post-test', 'practice_test' => 'Practice Test'];
                    @endphp
                    @forelse ($assessmentSets as $set)
                        @php
                            $setLabel = $set->scope === 'topic'
                                ? $set->title . ' Topic Quiz'
                                : ($set->title ?: ($assessmentLabels[$set->content_type] ?? 'Assessment'));
                        @endphp
                        <tr>
                            <td><strong>{{ $setLabel }}</strong>@if($set->subject_code || $set->topic_title)<br><small>{{ collect([$set->subject_code, $set->topic_title])->filter()->join(' — ') }}</small>@endif</td>
                            <td><strong>{{ number_format(min($set->question_limit ?: $set->question_count, $set->question_count)) }}</strong> per attempt<br><small>{{ number_format($set->question_count) }} approved in bank</small></td>
                            <td>
                                <span>{{ $set->passing_percentage }}% passing score · {{ $set->time_limit_minutes ? $set->time_limit_minutes . ' minutes' : 'No time limit' }}</span>
                                @if($set->scope === 'mock')
                                    <button type="button" class="status info mock-settings-badge mock-exam-settings-badge" onclick="openModal('mockExamSettingsModal')">{{ $set->maximum_attempts ? $set->maximum_attempts . ' maximum tries' : 'Unlimited tries' }} · Modify</button>
                                @else
                                    <button type="button" class="status info mock-settings-badge assessment-pass-rule-badge" data-scope="{{ $set->scope }}" data-id="{{ $set->id }}" data-label="{{ $setLabel }}" data-percentage="{{ $set->passing_percentage }}" data-time-limit="{{ $set->time_limit_minutes ?: '' }}" data-question-count="{{ $set->question_limit ?: $set->question_count }}">Edit assessment rules</button>
                                @endif
                            </td>
                            <td><span class="status info">Instructor defined</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted)">No instructor-defined assessment sets yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1.25rem">
            <p class="muted" style="margin:0;font-size:.85rem">@if($assessmentSets->total()) Showing <strong style="color:var(--text)">{{ $assessmentSets->firstItem() }}</strong> to <strong style="color:var(--text)">{{ $assessmentSets->lastItem() }}</strong> of <strong style="color:var(--text)">{{ number_format($assessmentSets->total()) }}</strong> {{ request('sets_search') ? 'matching ' : '' }}assessment sets @else No {{ request('sets_search') ? 'matching ' : '' }}assessment sets found @endif</p>
            <div>{{ $assessmentSets->links('pagination::bootstrap-4') }}</div>
        </div>
    </section>
</div>

<div id="mockExamSettingsModal" class="admin-modal">
    <form method="POST" action="{{ route('admin.content.mock-exam.settings', $course->id) }}" class="admin-modal-content" style="max-width:520px">
        @csrf
        <div class="admin-modal-header"><h3 class="admin-modal-title">Mock Exam Settings</h3><button type="button" class="admin-modal-close" onclick="closeModal('mockExamSettingsModal')">&times;</button></div>
        <div class="admin-modal-body">
            <p class="muted" style="margin:0 0 1.25rem;line-height:1.6">These rules apply to the Mock Exam for every batch assigned to this master course.</p>
            <div class="field"><label>Passing percentage</label><div style="display:flex;align-items:center;gap:.65rem"><input type="number" name="mock_exam_passing_percentage" min="1" max="100" value="{{ $course->mock_exam_passing_percentage ?? 80 }}" required><strong>%</strong></div></div>
            <div class="field" style="margin-top:1rem"><label>Number of questions per attempt</label><input type="number" name="question_count" min="1" max="5000" value="{{ $course->mock_exam_question_count ?: max(1, (int) ($assessmentCounts['final'] ?? 1)) }}" required><small>Selected evenly from Easy, Average, and Difficult questions.</small></div>
            <div class="field" style="margin-top:1rem"><label>Maximum number of tries</label><select name="mock_exam_maximum_attempts"><option value="">Unlimited</option>@foreach([1,2,3,4,5] as $attempt)<option value="{{ $attempt }}" {{ (int) $course->mock_exam_maximum_attempts === $attempt ? 'selected' : '' }}>{{ $attempt }}</option>@endforeach</select></div>
            <div class="field" style="margin-top:1rem"><label>Time limit</label><select name="timing_mode" onchange="this.closest('.admin-modal-body').querySelector('.mock-time-field').style.display=this.value==='timed'?'block':'none'"><option value="unlimited" {{ $course->mock_exam_time_limit_minutes ? '' : 'selected' }}>No time limit</option><option value="timed" {{ $course->mock_exam_time_limit_minutes ? 'selected' : '' }}>Timed exam</option></select></div>
            <div class="field mock-time-field" style="margin-top:1rem;display:{{ $course->mock_exam_time_limit_minutes ? 'block' : 'none' }}"><label>Minutes to complete</label><input type="number" name="time_limit_minutes" min="1" max="1440" value="{{ $course->mock_exam_time_limit_minutes }}"></div>
        </div>
        <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeModal('mockExamSettingsModal')">Cancel</button><button type="submit" class="btn-primary">Save Mock Exam Settings</button></div>
    </form>
</div>

<div id="assessmentPassRuleModal" class="admin-modal">
    <form method="POST" action="{{ route('admin.content.assessments.pass-rule', $course->id) }}" class="admin-modal-content" style="max-width:500px">
        @csrf
        <input type="hidden" id="pass_rule_scope" name="assessment_scope">
        <input type="hidden" id="pass_rule_assessment_id" name="assessment_id">
        <div class="admin-modal-header"><h3 class="admin-modal-title">Edit Assessment Rules</h3><button type="button" class="admin-modal-close" onclick="closeModal('assessmentPassRuleModal')">&times;</button></div>
        <div class="admin-modal-body">
            <p id="pass_rule_assessment_label" style="margin:0 0 1.25rem;font-weight:700"></p>
            <div class="field"><label for="pass_rule_percentage">Passing percentage</label><div style="display:flex;align-items:center;gap:.65rem"><input id="pass_rule_percentage" type="number" name="passing_percentage" min="1" max="100" step="1" value="80" required><strong>%</strong></div></div>
            <div class="field" style="margin-top:1rem"><label for="pass_rule_question_count">Number of questions per attempt</label><input id="pass_rule_question_count" type="number" name="question_count" min="1" max="5000" value="1" required><small>Selected evenly from Easy, Average, and Difficult questions.</small></div>
            <p class="muted" style="margin:.75rem 0 0;font-size:.78rem;line-height:1.5">The default is 80%. This rule is used when the learner's scored submission is evaluated.</p>
            <div class="field" style="margin-top:1rem"><label for="pass_rule_timing_mode">Time limit</label><select id="pass_rule_timing_mode" name="timing_mode" onchange="syncAssessmentTimingField('pass_rule')"><option value="unlimited">No time limit</option><option value="timed">Timed exam</option></select></div>
            <div class="field" id="pass_rule_time_limit_field" style="display:none;margin-top:1rem"><label for="pass_rule_time_limit_minutes">Minutes to complete</label><input id="pass_rule_time_limit_minutes" type="number" name="time_limit_minutes" min="1" max="1440" step="1" placeholder="Example: 30"></div>
        </div>
        <div class="admin-modal-footer"><button type="button" class="btn-ghost" onclick="closeModal('assessmentPassRuleModal')">Cancel</button><button type="submit" class="btn-primary">Save Pass Rule</button></div>
    </form>
</div>

<section class="panel" style="margin-top:18px" data-ajax-table="question-bank-table">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div>
            <p class="panel-label">Question bank</p>
            <h2 class="panel-title" style="margin-bottom: 0;">Active Database Questions</h2>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            @if($isAdmin)
                <button type="button" class="btn-primary" onclick="openModal('approveAllQuestionsModal')" {{ $pendingQuestionsCount < 1 ? 'disabled' : '' }} style="padding:.5rem 1rem">
                    Approve All{{ $pendingQuestionsCount > 0 ? ' (' . $pendingQuestionsCount . ')' : '' }}
                </button>
            @endif
            <form action="{{ route('admin.content.quizzes', $course->id) }}" method="GET" data-question-bank-search style="display:flex;gap:.5rem;margin:0">
                <input type="hidden" name="subject_id" value="{{ $managedSubject->id }}">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search questions..." style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);min-width:250px">
                <button type="submit" class="btn-primary" style="padding:.5rem 1rem">Search</button>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Type</th>
                    <th>Response</th>
                    <th>Correct Answer(s)</th>
                    <th>Scope</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quizzes as $question)
                    <tr>
                        <td style="vertical-align: middle;">
                            <button type="button" class="btn-ghost preview-question-btn"
                                    data-question="{{ $question->question }}"
                                    data-assessment-title="{{ in_array($question->question_type, ['subtopic_assessment','pre_test','post_test']) ? ($question->subtopic?->title ?? 'Assessment') : ($question->question_type === 'final' ? 'Mock Exam' : ($question->question_type === 'midterm' ? 'Practice Test' : 'Topic Quiz')) }}"
                                    data-response-type="{{ $question->response_type }}"
                                    data-options="{{ base64_encode(json_encode($question->options)) }}"
                                    data-response-config="{{ base64_encode(json_encode($question->response_config)) }}"
                                    data-image-url="{{ $question->image_path ? asset('storage/' . $question->image_path) : '' }}"
                                    data-rationale="{{ base64_encode($question->rationale ?? '') }}"
                                    style="padding:0.35rem 0.6rem;font-size:0.75rem">Preview</button>
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="status info">{{ in_array($question->question_type, ['subtopic_assessment','pre_test','post_test']) ? ($question->subtopic?->title ?? str($question->question_type)->replace('_',' ')->title()) : ($question->question_type === 'midterm' ? 'Practice Test' : ($question->question_type === 'final' ? 'Mock Exam' : 'Topic Quiz')) }}</span>
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="status {{ in_array($question->response_type, ['sata', 'grid', 'cloze', 'highlight']) ? 'warning' : 'info' }}">
                                {{ $question->response_type === 'sata' ? 'SATA' : ($question->response_type === 'grid' ? 'Grid / Matrix' : ($question->response_type === 'cloze' ? 'Cloze / Dropdown' : ($question->response_type === 'highlight' ? 'Highlighting' : 'Single choice'))) }}
                            </span>
                        </td>
                        <td style="vertical-align: middle;">
                            @php
                                $opts = $question->options;
                                $answerIndexes = $question->correct_answers ?: [$question->answer];
                                $answerTexts = collect($answerIndexes)->map(fn ($index) => $opts[$index] ?? 'Option ' . ((int) $index + 1));
                            @endphp
                            <code>{{ $question->response_type === 'grid' ? 'Configured per matrix cell' : ($question->response_type === 'cloze' ? 'Configured per inline dropdown' : ($question->response_type === 'highlight' ? 'Configured highlightable text' : $answerTexts->join(', '))) }}</code>
                        </td>
                        <td style="vertical-align: middle;">
                            @if (in_array($question->question_type, ['subtopic_assessment','pre_test','post_test']) && $question->subtopic)
                                <span class="status info">{{ $question->topic?->title }} — {{ $question->subtopic->title }}</span>
                            @elseif ($question->question_type === 'quiz' && $question->topic)
                                <span class="status info">{{ $question->topic->title }}</span>
                            @else
                                <span class="status warning">Course-wide</span>
                            @endif
                        </td>
                        <td style="vertical-align: middle;">
                            @if($question->status === 'approved')
                                <span class="status success">Approved</span>
                            @else
                                <span class="status warning">Pending</span>
                            @endif
                        </td>
                        <td style="vertical-align: middle;">
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                @if($question->status === 'pending' && $isAdmin)
                                <form action="{{ route('admin.content.quizzes.approve', ['course' => $course->id, 'quiz' => $question->id]) }}" method="POST" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="btn-ghost" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; color: var(--correct); border-color: var(--correct);">Approve</button>
                                </form>
                                @endif
                                
                                @if(!($isAdmin && $question->status === 'pending'))
                                    <button type="button" class="btn-ghost edit-question-btn" 
                                            data-id="{{ $question->id }}"
                                            data-question="{{ $question->question }}"
                                            data-rationale="{{ base64_encode($question->rationale ?? '') }}"
                                            data-question-type="{{ $question->question_type }}"
                                            data-response-type="{{ $question->response_type }}"
                                            data-response-config="{{ base64_encode(json_encode($question->response_config)) }}"
                                            data-maximum-points="{{ $question->maximum_points ?: 1 }}"
                                            data-category="{{ $question->category ?: 'average' }}"
                                            data-scoring-method="{{ $question->scoring_method ?: 'all_or_nothing' }}"
                                            data-topic-id="{{ $question->topic_id ?: '' }}"
                                            data-subtopic-id="{{ $question->subtopic_id ?: '' }}"
                                            data-subject-id="{{ $question->topic?->subject_id ?: $question->subtopic?->topic?->subject_id ?: $managedSubject->id }}"
                                            data-options="{{ json_encode($question->options) }}"
                                            data-answers="{{ json_encode($question->correct_answers ?: [$question->answer]) }}"
                                            data-image-url="{{ $question->image_path ? asset('storage/' . $question->image_path) : '' }}"
                                            style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">
                                        Edit
                                    </button>
                                @endif
                                <form action="{{ route('admin.content.quizzes.destroy', ['course' => $course->id, 'quiz' => $question->id]) }}" method="POST" onsubmit="return confirmDelete(event, 'Are you sure you want to delete this question?');" style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; color: var(--wrong); border-color: var(--wrong);">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No assessment questions configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1.5rem">
        <p class="muted" style="margin:0;font-size:.85rem">
            @if($quizzes->total() > 0)
                Showing <strong style="color:var(--text)">{{ $quizzes->firstItem() }}</strong> to <strong style="color:var(--text)">{{ $quizzes->lastItem() }}</strong> of <strong style="color:var(--text)">{{ number_format($quizzes->total()) }}</strong> {{ request('search') ? 'matching' : '' }} questions
            @else
                No {{ request('search') ? 'matching ' : '' }}questions found
            @endif
        </p>
        <div>
            {{ $quizzes->links('pagination::bootstrap-4') }}
        </div>
    </div>
</section>

@if($isAdmin)
<div id="approveAllQuestionsModal" class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="approveAllQuestionsTitle">
    <form method="POST" action="{{ route('admin.content.quizzes.approve-all', $course->id) }}" class="admin-modal-content" style="max-width:460px">
        @csrf
        <input type="hidden" name="subject_id" value="{{ $managedSubject->id }}">
        <div class="admin-modal-header">
            <h3 id="approveAllQuestionsTitle" class="admin-modal-title">Approve All Pending Questions</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('approveAllQuestionsModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p style="margin:0;line-height:1.65;color:var(--text-muted)">Approve all {{ $pendingQuestionsCount }} pending questions in <strong style="color:var(--text)">{{ $course->title }}</strong>? Individual question approval will remain available afterward for newly added pending questions.</p>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('approveAllQuestionsModal')">Cancel</button>
            <button type="submit" class="btn-primary">Approve All Questions</button>
        </div>
    </form>
</div>
@endif

<!-- ================= ADD / EDIT QUESTION MODAL ================= -->
<div id="questionModal" class="admin-modal">
    <form id="questionForm" method="POST" action="{{ route('admin.content.quizzes.store', ['course' => $course->id]) }}" enctype="multipart/form-data" class="admin-modal-content">
        @csrf
        <input type="hidden" id="question_method" name="_method" value="POST">
        
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="modalTitle">Add New Question</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('questionModal')">&times;</button>
        </div>
        
        <div class="admin-modal-body">
            <div class="form-grid">
                <div class="field full">
                    <label for="q_type">Assessment Type</label>
                    <select id="q_type" name="question_type" required onchange="syncQuestionTypeFields()">
                        <option value="quiz">Topic Quiz</option>
                        <option value="pre_test">Pre-test</option>
                        <option value="post_test">Post-test</option>
                        <option value="subtopic_assessment">Practice Test</option>
                    </select>
                </div>
                <div class="field full" id="q_subject_field">
                    <label>Subject</label>
                    <input type="hidden" id="q_subject" name="subject_id" value="{{ $managedSubject->id }}">
                    <div class="input" style="padding:.8rem 1rem;border:1.5px solid var(--border);border-radius:8px;background:rgba(124,58,237,.06);font-weight:700">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</div>
                    <small>This question will be saved under the subject currently being managed.</small>
                </div>
                <div class="field full" id="q_assessment_item_field" style="display:none">
                    <label for="q_assessment_item">Assessment entry</label>
                    <select id="q_assessment_item" name="subtopic_id"><option value="">-- Select an assessment entry --</option>@foreach($assessmentItems as $item)<option value="{{ $item->id }}" data-content-type="{{ $item->content_type }}" data-subject-id="{{ $item->topic->subject_id }}">{{ $item->topic->title }} — {{ $item->title }}</option>@endforeach</select>
                </div>

                <div class="field full" id="q_scope_field">
                    <label for="q_scope">Topic / Module</label>
                    <select id="q_scope" name="topic_id">
                        <option value="" disabled selected>-- Select a Topic --</option>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic->id }}">{{ $topic->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field full">
                    <label for="q_response_type">Response Type</label>
                    <select id="q_response_type" name="response_type" required onchange="syncAnswerFields()">
                        <option value="single">Multiple Choice (one correct answer)</option>
                        <option value="sata">SATA (select all that apply)</option>
                        <option value="grid">Grid / Matrix (dropdown cells)</option>
                        <option value="cloze">Cloze / Inline Dropdown</option>
                        <option value="highlight">Highlighting</option>
                    </select>
                </div>

                <div class="field">
                    <label for="q_maximum_points">Maximum Points</label>
                    <input type="number" id="q_maximum_points" name="maximum_points" min="0.01" step="0.01" value="1" required>
                </div>
                <div class="field">
                    <label for="q_category">Question Category</label>
                    <select id="q_category" name="category" required>
                        <option value="easy">Easy</option>
                        <option value="average" selected>Average</option>
                        <option value="difficult">Difficult</option>
                    </select>
                </div>
                <div class="field">
                    <label for="q_scoring_method">Scoring Method</label>
                    <select id="q_scoring_method" name="scoring_method" required>
                        <option value="all_or_nothing">All or Nothing</option>
                        <option value="partial_credit">Partial Credit by Cell</option>
                    </select>
                </div>

                <div class="field full">
                    <label for="q_text">Question / Examinee Instructions</label>
                    <textarea id="q_text" name="question" required placeholder="e.g. Which action should the examinee take first?" style="width: 100%; min-height: 80px; padding: 0.8rem; border-radius: 8px; border: 1.5px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); font-family: inherit; resize: vertical;"></textarea>
                </div>

                <input type="hidden" id="q_response_config" name="response_config">
                <div class="field full" id="matrix_builder_field" style="display:none">
                    <div class="matrix-builder-toolbar">
                        <div><strong>Matrix Builder</strong><small>Configure 2–4 columns and up to 10 rows.</small></div>
                        <div><button type="button" class="btn-ghost" onclick="matrixAddColumn()">+ Column</button><button type="button" class="btn-ghost" onclick="matrixAddRow()">+ Row</button></div>
                    </div>
                    <div id="matrix_columns_editor"></div>
                    <div class="table-wrap"><table class="data-table matrix-author-table"><thead id="matrix_author_head"></thead><tbody id="matrix_author_body"></tbody></table></div>
                    <button type="button" class="btn-primary" onclick="matrixShowPreview()" style="margin-top:.8rem">Preview Examinee View</button>
                    <div id="matrix_preview" class="matrix-preview-panel" style="display:none"></div>
                </div>

                <div class="field full" id="cloze_builder_field" style="display:none">
                    <div class="cloze-builder-toolbar">
                        <div><strong>Cloze / Dropdown Builder</strong><small>Write placeholders such as <code>@{{blank_1}}</code> in the question, then configure each dropdown below.</small></div>
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem"><button type="button" class="btn-primary" onclick="clozeInsertPlaceholder()">+ Add Dropdown Placeholder</button><button type="button" class="btn-ghost" onclick="clozeSyncFromQuestion()">Sync Dropdowns</button></div>
                    </div>
                    <div id="cloze_blanks_editor"></div>
                    <button type="button" class="btn-primary" onclick="clozeShowPreview()">Preview Examinee View</button>
                    <div id="cloze_preview" class="matrix-preview-panel" style="display:none"></div>
                </div>

                <div class="field full" id="highlight_builder_field" style="display:none">
                    <div class="highlight-builder-toolbar"><div><strong>Highlighting Builder</strong><small>Enter selectable words, phrases, or sentences and mark every correct highlight.</small></div><button type="button" class="btn-ghost" onclick="highlightAddSegment()">+ Add Highlightable Text</button></div>
                    <div id="highlight_segments_editor"></div>
                    <button type="button" class="btn-primary" onclick="highlightShowPreview()">Preview Examinee View</button>
                    <div id="highlight_preview" class="matrix-preview-panel" style="display:none"></div>
                </div>

                <div class="field full">
                    <label for="q_rationale">Question Rationale</label>
                    <textarea id="q_rationale" name="rationale" maxlength="5000" placeholder="Explain why the correct answer is correct and why the alternatives are not appropriate." style="width:100%;min-height:110px"></textarea>
                    <small style="display:block;margin-top:.4rem;color:var(--text-muted)">Shown during answer review for every question type.</small>
                </div>

                <div class="field full">
                    <label for="q_image">Reference Image (optional)</label>
                    <input type="file" id="q_image" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    <input type="hidden" id="remove_question_image" name="remove_question_image" value="0">
                    <div id="q_image_preview_wrap" style="display:none; margin-top:0.75rem;">
                        <img id="q_image_preview" alt="Question image preview" style="max-width:100%; max-height:240px; object-fit:contain; border-radius:8px; border:1px solid var(--border);">
                        <button type="button" class="btn-ghost" onclick="removeQuestionImage()" style="display:block; margin-top:0.5rem;">Remove image</button>
                    </div>
                </div>

                <div class="field full" id="standard_options_field">
                    <label id="option_builder_label">Choices — select the correct answer</label>
                    <div id="q_options_builder" style="display:grid; gap:0.75rem;"></div>
                    <button type="button" class="btn-ghost" id="add_option_btn" onclick="addQuestionOption()" style="margin-top:0.75rem;">+ Add choice</button>
                    <small style="display:block; margin-top:0.5rem; color:var(--text-muted);">Minimum 2, maximum 8 choices.</small>
                </div>
            </div>
        </div>
        
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('questionModal')">Cancel</button>
            <button type="submit" id="saveQuestionBtn" class="btn-primary">Create Question</button>
        </div>
    </form>
</div>

<div id="questionPreviewModal" class="admin-modal">
    <div class="admin-modal-content question-preview-modal-content quiz-preview-shell">
        <header class="preview-exam-header">
            <div class="preview-exam-brand"><span class="preview-exam-mark">A2</span><strong>Artemis 2.0</strong><span id="bank_preview_assessment_title" class="preview-exam-title">Topic Quiz</span></div>
            <div class="preview-exam-progress">
                <div class="preview-exam-meta"><strong>Question 1 of 1</strong><span>100%</span></div>
                <div class="preview-exam-track"><span></span></div>
            </div>
            <button type="button" class="admin-modal-close quiz-preview-close" aria-label="Close preview" onclick="closeModal('questionPreviewModal')">&times;</button>
        </header>
        <main class="preview-exam-body">
          <article class="preview-question-card">
            <span id="bank_preview_response_label" class="preview-response-label">Multiple Choice</span>
            <div id="bank_preview_image_wrap" style="display:none;text-align:center;margin-bottom:1rem"><img id="bank_preview_image" alt="Question support" style="max-width:100%;max-height:360px;object-fit:contain;border-radius:10px;border:1px solid var(--border)"></div>
            <h3 id="bank_preview_question"></h3>
            <p id="bank_preview_instruction" class="preview-answer-instruction">Select the best answer.</p>
            <div id="bank_preview_response" class="options-list"></div>
            <button type="button" id="bank_preview_rationale_toggle" class="btn-ghost" style="display:none;margin-top:1rem">Show Rationale</button>
            <div id="bank_preview_rationale" class="question-rationale" style="display:none"></div>
          </article>
        </main>
        <footer class="preview-exam-footer">
            <div class="preview-footer-controls">
                <button type="button" class="preview-previous" disabled>&lsaquo; Previous</button>
                <label class="preview-review"><input type="checkbox"><span>Mark for Review</span></label>
            </div>
            <button type="button" id="bank_preview_next" class="btn-primary preview-submit" disabled>Submit Answer</button>
        </footer>
     </div>
</div>

<script>
    // Modal Helpers
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function syncAssessmentTimingField(prefix) {
        const mode = document.getElementById(`${prefix}_timing_mode`);
        const field = document.getElementById(`${prefix}_time_limit_field`);
        const input = document.getElementById(`${prefix}_time_limit_minutes`);
        const timed = mode.value === 'timed';
        field.style.display = timed ? 'block' : 'none';
        input.required = timed;
        if (!timed) input.value = '';
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('.assessment-pass-rule-badge');
        if (!button) return;
            document.getElementById('pass_rule_scope').value = button.dataset.scope;
            document.getElementById('pass_rule_assessment_id').value = button.dataset.id;
            document.getElementById('pass_rule_assessment_label').textContent = button.dataset.label;
            document.getElementById('pass_rule_percentage').value = button.dataset.percentage || '80';
            document.getElementById('pass_rule_question_count').value = button.dataset.questionCount || '1';
            const timeLimit = button.dataset.timeLimit || '';
            document.getElementById('pass_rule_timing_mode').value = timeLimit ? 'timed' : 'unlimited';
            document.getElementById('pass_rule_time_limit_minutes').value = timeLimit;
            syncAssessmentTimingField('pass_rule');
            openModal('assessmentPassRuleModal');
    });

    function resetQuestionForm() {
        document.getElementById('modalTitle').textContent = 'Add New Question';
        document.getElementById('q_type').value = 'quiz';
        document.getElementById('q_response_type').value = 'single';
        document.getElementById('q_scope').value = '';
        document.getElementById('q_subject').value = '{{ $managedSubject->id }}';
        document.getElementById('q_text').value = '';
        document.getElementById('q_rationale').value = '';
        document.getElementById('q_maximum_points').value = '1';
        document.getElementById('q_category').value = 'average';
        document.getElementById('q_scoring_method').value = 'all_or_nothing';
        document.getElementById('q_image').value = '';
        document.getElementById('remove_question_image').value = '0';
        document.getElementById('q_image_preview_wrap').style.display = 'none';
        renderQuestionOptions(['', '', '', ''], [0]);
        matrixReset();
        clozeReset();
        highlightReset();
        syncQuestionTypeFields();
        syncAnswerFields();
        
        document.getElementById('questionForm').action = "{{ route('admin.content.quizzes.store', ['course' => $course->id]) }}";
        document.getElementById('question_method').value = 'POST';
        document.getElementById('saveQuestionBtn').textContent = 'Create Question';
    }

    function openAddQuestionModal() {
        resetQuestionForm();
        openModal('questionModal');
    }

    function syncQuestionTypeFields() {
        const type = document.getElementById('q_type').value;
        const isQuiz = type === 'quiz';
        const isSubtopicAssessment = ['subtopic_assessment', 'pre_test', 'post_test'].includes(type);
        const scope = document.getElementById('q_scope');
        const subject = document.getElementById('q_subject');
        document.getElementById('q_scope_field').style.display = isQuiz ? '' : 'none';
        document.getElementById('q_subject_field').style.display = '';
        const assessmentItem = document.getElementById('q_assessment_item');
        document.getElementById('q_assessment_item_field').style.display = isSubtopicAssessment ? '' : 'none';
        scope.required = isQuiz;
        if (!isQuiz) scope.value = '';
        subject.required = true;
        subject.value = '{{ $managedSubject->id }}';
        assessmentItem.required = isSubtopicAssessment;
        if (!isSubtopicAssessment) assessmentItem.value = '';
        filterQuestionAssessmentEntries();
    }

    function filterQuestionAssessmentEntries() {
        const type = document.getElementById('q_type').value;
        const subjectId = document.getElementById('q_subject').value;
        const assessmentItem = document.getElementById('q_assessment_item');
        const expectedContentType = type === 'subtopic_assessment' ? 'practice_test' : type;
        let availableCount = 0;
        [...assessmentItem.options].forEach(option => {
            if (!option.value) return;
            option.hidden = !subjectId || option.dataset.subjectId !== subjectId || option.dataset.contentType !== expectedContentType;
            if (!option.hidden) availableCount++;
        });
        if (assessmentItem.selectedOptions[0]?.hidden) assessmentItem.value = '';
        const placeholder = assessmentItem.options[0];
        const labels = {pre_test: 'Pre-test', post_test: 'Post-test', practice_test: 'Practice Test'};
        placeholder.textContent = subjectId && availableCount === 0
            ? `-- No ${labels[expectedContentType] || 'assessment'} entry in this subject --`
            : '-- Select an assessment entry --';
    }

    function syncAnswerFields() {
        const isSata = document.getElementById('q_response_type').value === 'sata';
        const isGrid = document.getElementById('q_response_type').value === 'grid';
        const isCloze = document.getElementById('q_response_type').value === 'cloze';
        const isHighlight = document.getElementById('q_response_type').value === 'highlight';
        const isStructured = isGrid || isCloze || isHighlight;
        document.getElementById('standard_options_field').style.display = isStructured ? 'none' : '';
        document.getElementById('matrix_builder_field').style.display = isGrid ? '' : 'none';
        document.getElementById('cloze_builder_field').style.display = isCloze ? '' : 'none';
        document.getElementById('highlight_builder_field').style.display = isHighlight ? '' : 'none';
        document.querySelectorAll('#standard_options_field input').forEach(input => input.disabled = isStructured);
        if (isGrid) { matrixSyncJson(); return; }
        if (isCloze) { clozeSyncJson(); return; }
        if (isHighlight) { highlightSyncJson(); return; }
        const state = collectQuestionOptions();
        document.getElementById('option_builder_label').textContent = isSata
            ? 'Choices — select all correct answers'
            : 'Choices — select the one correct answer';
        renderQuestionOptions(state.options.length ? state.options : ['', ''], state.answers);
    }

    function collectQuestionOptions() {
        const rows = [...document.querySelectorAll('.question-option-row')];
        return {
            options: rows.map(row => row.querySelector('.question-option-text').value),
            answers: rows.filter(row => row.querySelector('.question-option-correct').checked)
                .map(row => Number(row.dataset.index)),
        };
    }

    function renderQuestionOptions(options, answers = []) {
        const builder = document.getElementById('q_options_builder');
        const isSata = document.getElementById('q_response_type').value === 'sata';
        builder.innerHTML = '';
        options.slice(0, 8).forEach((value, index) => {
            const row = document.createElement('div');
            row.className = 'question-option-row';
            row.dataset.index = index;
            row.style.cssText = 'display:grid;grid-template-columns:auto 1fr auto;gap:0.65rem;align-items:center;';

            const correct = document.createElement('input');
            correct.type = isSata ? 'checkbox' : 'radio';
            correct.name = isSata ? 'correct_answers[]' : 'answer';
            correct.value = index;
            correct.className = 'question-option-correct';
            correct.checked = answers.map(Number).includes(index);
            if (!isSata) correct.required = true;
            correct.title = isSata ? 'Mark as a correct answer' : 'Mark as the correct answer';

            const text = document.createElement('input');
            text.type = 'text';
            text.name = 'options[]';
            text.required = true;
            text.maxLength = 255;
            text.className = 'question-option-text';
            text.placeholder = `Choice ${index + 1}`;
            text.value = value || '';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-ghost';
            remove.textContent = 'Remove';
            remove.disabled = options.length <= 2;
            remove.onclick = () => removeQuestionOption(index);

            row.append(correct, text, remove);
            builder.appendChild(row);
        });
        document.getElementById('add_option_btn').disabled = options.length >= 8;
    }

    function addQuestionOption() {
        const state = collectQuestionOptions();
        if (state.options.length >= 8) return;
        state.options.push('');
        renderQuestionOptions(state.options, state.answers);
    }

    function removeQuestionOption(index) {
        const state = collectQuestionOptions();
        if (state.options.length <= 2) return;
        state.options.splice(index, 1);
        const adjustedAnswers = state.answers
            .filter(answer => answer !== index)
            .map(answer => answer > index ? answer - 1 : answer);
        renderQuestionOptions(state.options, adjustedAnswers);
    }

    function showQuestionImage(url) {
        const previewWrap = document.getElementById('q_image_preview_wrap');
        const preview = document.getElementById('q_image_preview');
        if (url) {
            preview.src = url;
            previewWrap.style.display = 'block';
        } else {
            preview.removeAttribute('src');
            previewWrap.style.display = 'none';
        }
    }

    function removeQuestionImage() {
        document.getElementById('q_image').value = '';
        document.getElementById('remove_question_image').value = '1';
        showQuestionImage('');
    }

    document.getElementById('q_image').addEventListener('change', event => {
        const file = event.target.files[0];
        document.getElementById('remove_question_image').value = '0';
        showQuestionImage(file ? URL.createObjectURL(file) : '');
    });

    document.addEventListener('click', event => {
        const btn = event.target.closest('.edit-question-btn');
        if (!btn) return;
            const id = btn.dataset.id;
            const question = btn.dataset.question;
            const rationale = btn.dataset.rationale ? new TextDecoder().decode(Uint8Array.from(atob(btn.dataset.rationale), c => c.charCodeAt(0))) : '';
            const questionType = btn.dataset.questionType;
            const responseType = btn.dataset.responseType;
            const topicId = btn.dataset.topicId;
            const subtopicId = btn.dataset.subtopicId;
            const subjectId = btn.dataset.subjectId;
            const options = JSON.parse(btn.dataset.options);
            const answers = JSON.parse(btn.dataset.answers);
            const imageUrl = btn.dataset.imageUrl;
            const matrixConfig = btn.dataset.responseConfig ? JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(btn.dataset.responseConfig), c => c.charCodeAt(0)))) : null;

            document.getElementById('modalTitle').textContent = 'Edit Question';
            document.getElementById('q_type').value = questionType;
            document.getElementById('q_response_type').value = responseType;
            document.getElementById('q_scope').value = topicId;
            document.getElementById('q_subject').value = subjectId;
            document.getElementById('q_text').value = question;
            document.getElementById('q_rationale').value = rationale;
            document.getElementById('q_maximum_points').value = btn.dataset.maximumPoints || '1';
            document.getElementById('q_category').value = btn.dataset.category || 'average';
            document.getElementById('q_scoring_method').value = btn.dataset.scoringMethod || 'all_or_nothing';
            document.getElementById('q_image').value = '';
            document.getElementById('remove_question_image').value = '0';
            showQuestionImage(imageUrl);
            renderQuestionOptions(options, answers);
            if (responseType === 'grid' && matrixConfig) matrixLoad(matrixConfig); else matrixReset();
            if (responseType === 'cloze' && matrixConfig) clozeLoad(matrixConfig); else clozeReset();
            if (responseType === 'highlight' && matrixConfig) highlightLoad(matrixConfig); else highlightReset();
            // Apply the selected response layout after restoring its data. Without
            // this, an edited grid could keep the previous multiple-choice panel visible.
            syncAnswerFields();
            syncQuestionTypeFields();
            if (questionType === 'quiz') document.getElementById('q_scope').value = topicId;
            if (['subtopic_assessment', 'pre_test', 'post_test'].includes(questionType)) {
                document.getElementById('q_subject').value = subjectId;
                filterQuestionAssessmentEntries();
                document.getElementById('q_assessment_item').value = subtopicId;
            }
            document.getElementById('option_builder_label').textContent = responseType === 'sata'
                ? 'Choices — select all correct answers'
                : 'Choices — select the one correct answer';
            
            document.getElementById('questionForm').action = `/admin/content/courses/{{ $course->id }}/quizzes/${id}`;
            document.getElementById('question_method').value = 'POST'; // POST method with Route override
            document.getElementById('saveQuestionBtn').textContent = 'Save Question Changes';
            
            openModal('questionModal');
    });

    function decodePreviewData(value, fallback) {
        if (!value) return fallback;
        try { return JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(value), char => char.charCodeAt(0)))); }
        catch (error) { return fallback; }
    }
    function decodePreviewText(value) {
        if (!value) return '';
        try { return new TextDecoder().decode(Uint8Array.from(atob(value), char => char.charCodeAt(0))); }
        catch (error) { return ''; }
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('.preview-question-btn');
        if (!button) return;
        const question = button.dataset.question || '';
        const responseType = button.dataset.responseType || 'single';
        const options = decodePreviewData(button.dataset.options, []);
        const config = decodePreviewData(button.dataset.responseConfig, null);
        const rationale = decodePreviewText(button.dataset.rationale);
        document.getElementById('bank_preview_question').textContent = responseType === 'cloze' ? 'Complete the statement below.' : question;
        document.getElementById('bank_preview_assessment_title').textContent = button.dataset.assessmentTitle || 'Topic Quiz';
        document.getElementById('bank_preview_response_label').textContent = responseType === 'grid' ? 'Grid / Matrix' : (responseType === 'cloze' ? 'Cloze / Dropdown' : (responseType === 'highlight' ? 'Highlighting' : (responseType === 'sata' ? 'Select All That Apply' : 'Multiple Choice')));
        document.getElementById('bank_preview_instruction').textContent = responseType === 'grid' ? 'Complete every response cell in the matrix.' : (responseType === 'cloze' ? 'Select the best answer for every dropdown.' : (responseType === 'highlight' ? 'Select every word or phrase that should be highlighted.' : (responseType === 'sata' ? 'Select all answers that apply.' : 'Select the best answer.')));
        const imageWrap = document.getElementById('bank_preview_image_wrap');
        const image = document.getElementById('bank_preview_image');
        if (button.dataset.imageUrl) { image.src = button.dataset.imageUrl; imageWrap.style.display = 'block'; }
        else { image.removeAttribute('src'); imageWrap.style.display = 'none'; }
        const response = document.getElementById('bank_preview_response');
        const nextButton = document.getElementById('bank_preview_next');
        nextButton.disabled = true;
        nextButton.textContent = 'Submit Answer';
        if (responseType === 'grid' && config) {
            response.innerHTML = `<div class="table-wrap"><table class="matrix-examinee-table"><thead><tr>${config.columns.map(column => `<th>${matrixEscape(column.label)}</th>`).join('')}</tr></thead><tbody>${config.rows.map(row => `<tr>${row.cells.map(cell => cell.type === 'static_text' ? `<td>${matrixEscape(cell.value)}</td>` : cell.type === 'sata' ? `<td class="matrix-sata-cell">${cell.options.map(option => `<label><input type="checkbox"> <span>${matrixEscape(option.label)}</span></label>`).join('')}</td>` : `<td><select><option value="">Select an answer</option>${cell.options.map(option => `<option>${matrixEscape(option.label)}</option>`).join('')}</select></td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
            const updateGridNext = () => { nextButton.disabled = ![...response.querySelectorAll('select')].every(select => select.value) || ![...response.querySelectorAll('.matrix-sata-cell')].every(cell => cell.querySelector('input:checked')); };
            response.querySelectorAll('select,input').forEach(control => control.addEventListener('change', updateGridNext));
        } else if (responseType === 'cloze' && config) {
            response.innerHTML = `<p class="cloze-preview-text">${clozeInlineHtml(config)}</p>`;
            const selects=[...response.querySelectorAll('select')];
            selects.forEach(select=>select.addEventListener('change',()=>{nextButton.disabled=!selects.every(item=>item.value)}));
        } else if (responseType === 'highlight' && config) {
            response.innerHTML = `<div class="highlight-question-passage">${highlightPassageHtml(config)}</div>`;
            response.querySelectorAll('.highlight-choice').forEach(choice => choice.addEventListener('click', () => {
                choice.classList.toggle('selected');
                nextButton.disabled = !response.querySelector('.highlight-choice.selected');
            }));
        } else {
            response.innerHTML = options.map((option, index) => `<button type="button" class="quiz-option"><span class="opt-letter">${String.fromCharCode(65 + index)}</span><span class="opt-text">${matrixEscape(option)}</span></button>`).join('');
            response.querySelectorAll('.quiz-option').forEach(option => option.addEventListener('click', () => {
                if (responseType === 'sata') option.classList.toggle('selected');
                else { response.querySelectorAll('.quiz-option').forEach(item => item.classList.remove('selected')); option.classList.add('selected'); }
                nextButton.disabled = !response.querySelector('.quiz-option.selected');
            }));
        }
        const rationaleToggle = document.getElementById('bank_preview_rationale_toggle');
        const rationaleBox = document.getElementById('bank_preview_rationale');
        rationaleBox.style.display = 'none'; rationaleBox.textContent = rationale;
        rationaleToggle.style.display = rationale ? '' : 'none'; rationaleToggle.textContent = 'Show Rationale';
        rationaleToggle.onclick = () => { const show = rationaleBox.style.display === 'none'; rationaleBox.style.display = show ? 'block' : 'none'; rationaleToggle.textContent = show ? 'Hide Rationale' : 'Show Rationale'; };
        nextButton.onclick = () => {
            if (nextButton.textContent === 'Close Preview') return closeModal('questionPreviewModal');
            if (rationale) { rationaleBox.style.display = 'block'; rationaleToggle.textContent = 'Hide Rationale'; }
            nextButton.textContent = 'Close Preview';
        };
        openModal('questionPreviewModal');
    });

    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-question-bank-search]');
        if (!form) return;
        event.preventDefault();
        const query = new URLSearchParams(new FormData(form));
        query.delete('page');
        const url = `${form.action}${query.toString() ? `?${query}` : ''}`;
        loadAdminTable('question-bank-table', url);
    });

    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-assessment-sets-search]');
        if (!form) return;
        event.preventDefault();
        const query = new URLSearchParams(new FormData(form));
        query.delete('sets_page');
        const url = `${form.action}${query.toString() ? `?${query}` : ''}`;
        loadAdminTable('assessment-sets-table', url);
    });

    document.addEventListener('click', event => {
        const clearLink = event.target.closest('[data-assessment-sets-clear]');
        if (!clearLink) return;
        event.preventDefault();
        loadAdminTable('assessment-sets-table', clearLink.href);
    });

    @verbatim
    let clozeBlanks = [], clozePreviewConfirmed = false;
    function clozeReset(){clozeBlanks=[];clozePreviewConfirmed=false;document.getElementById('cloze_blanks_editor').innerHTML='<p class="muted">Add placeholders such as <code>{{blank_1}}</code> to the question, then select Sync Dropdowns.</p>';document.getElementById('cloze_preview').style.display='none'}
    function clozeInsertPlaceholder(){
        const textarea=document.getElementById('q_text');const text=textarea.value;let highest=0;
        for(const match of text.matchAll(/{{\s*blank_(\d+)\s*}}/gi))highest=Math.max(highest,Number(match[1])||0);
        const token=`{{blank_${highest+1}}}`;const start=Number.isInteger(textarea.selectionStart)?textarea.selectionStart:text.length;const end=Number.isInteger(textarea.selectionEnd)?textarea.selectionEnd:start;
        const before=text.slice(0,start),after=text.slice(end);const prefix=before&&!/\s$/.test(before)?' ':'';const suffix=after&&/^[.,!?;:]/.test(after)?'':' ';
        textarea.value=before+prefix+token+suffix+after;const cursor=(before+prefix+token+suffix).length;textarea.focus();textarea.setSelectionRange(cursor,cursor);textarea.dispatchEvent(new Event('input',{bubbles:true}));clozePreviewConfirmed=false;
    }
    function clozeSyncFromQuestion(){
        const keys=[...document.getElementById('q_text').value.matchAll(/{{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*}}/g)].map(match=>match[1]);
        const unique=[...new Set(keys)];
        if(!unique.length)return alert('Add at least one placeholder such as {{blank_1}} to the question.');
        clozeBlanks=unique.map((key,index)=>clozeBlanks.find(blank=>blank.key===key)||{key,label:`Dropdown ${index+1}`,options:['',''],correct:''});
        clozePreviewConfirmed=false;clozeRender();
    }
    function clozeRender(){
        document.getElementById('cloze_blanks_editor').innerHTML=clozeBlanks.map(blank=>`<div class="cloze-blank-card"><strong>{{${matrixEscape(blank.key)}}}</strong><div class="cloze-blank-grid"><div class="field"><label>Dropdown choices (one per line)</label><textarea rows="4" oninput="clozeUpdateOptions('${matrixEscape(blank.key)}',this.value)" onchange="clozeRender()" placeholder="Choice 1&#10;Choice 2">${matrixEscape(blank.options.join('\n'))}</textarea></div><div class="field"><label>Correct answer</label><select onchange="clozeSetCorrect('${matrixEscape(blank.key)}',this.value)"><option value="">Select the correct answer</option>${blank.options.filter(Boolean).map(option=>`<option value="${matrixEscape(option)}" ${option===blank.correct?'selected':''}>${matrixEscape(option)}</option>`).join('')}</select><small style="display:block;margin-top:.45rem;color:var(--text-muted)">Choices refresh after leaving the choices field.</small></div></div></div>`).join('');
        clozeSyncJson();
    }
    function clozeUpdateOptions(key,value){const blank=clozeBlanks.find(item=>item.key===key);if(!blank)return;blank.options=value.split(/\r?\n/).map(item=>item.trim()).filter((item,index,list)=>item&&list.indexOf(item)===index).slice(0,8);if(!blank.options.includes(blank.correct))blank.correct='';clozePreviewConfirmed=false;clozeSyncJson()}
    function clozeSetCorrect(key,value){const blank=clozeBlanks.find(item=>item.key===key);if(blank)blank.correct=value;clozePreviewConfirmed=false;clozeSyncJson()}
    function clozeCollect(){
        const template=document.getElementById('q_text').value.trim();
        if(!clozeBlanks.length)throw new Error('Sync at least one dropdown placeholder.');
        const blanks=clozeBlanks.map((blank,blankIndex)=>{if(blank.options.length<2)throw new Error(`${blank.key} requires at least two choices.`);if(!blank.correct||!blank.options.includes(blank.correct))throw new Error(`Select the correct answer for ${blank.key}.`);if(!template.includes(`{{${blank.key}}}`))throw new Error(`The question is missing {{${blank.key}}}.`);return{key:blank.key,label:blank.label,options:blank.options.map((label,index)=>({value:`${blank.key}_${index+1}`,label,is_correct:label===blank.correct}))}});
        return{type:'cloze_dropdown',template,blanks,maximum_points:Number(document.getElementById('q_maximum_points').value)||1};
    }
    function clozeSyncJson(){if(document.getElementById('q_response_type').value!=='cloze')return;try{document.getElementById('q_response_config').value=JSON.stringify(clozeCollect())}catch(error){document.getElementById('q_response_config').value=''}}
    function clozeInlineHtml(config){
        const blanks=new Map((config.blanks||[]).map(blank=>[blank.key,blank]));let cursor=0,html='';const regex=/{{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*}}/g;let match;
        while((match=regex.exec(config.template||''))){html+=matrixEscape((config.template||'').slice(cursor,match.index));const blank=blanks.get(match[1]);html+=blank?`<select><option value="">Select an answer</option>${blank.options.map(option=>`<option value="${matrixEscape(option.value)}">${matrixEscape(option.label)}</option>`).join('')}</select>`:matrixEscape(match[0]);cursor=regex.lastIndex}return html+matrixEscape((config.template||'').slice(cursor));
    }
    function clozeShowPreview(){try{const config=clozeCollect();document.getElementById('q_response_config').value=JSON.stringify(config);const wrap=document.getElementById('cloze_preview');const image=document.getElementById('q_image_preview');const imageHtml=image&&image.src&&document.getElementById('q_image_preview_wrap').style.display!=='none'?`<img src="${matrixEscape(image.src)}" alt="Question support" style="display:block;max-width:100%;max-height:320px;object-fit:contain;margin:0 auto 1rem">`:'';wrap.innerHTML=`${imageHtml}<strong>Examinee Preview</strong><p class="cloze-preview-text">${clozeInlineHtml(config)}</p>`;wrap.style.display='block';clozePreviewConfirmed=true;wrap.scrollIntoView({behavior:'smooth',block:'nearest'})}catch(error){alert(error.message)}}
    function clozeLoad(config){clozeBlanks=(config.blanks||[]).map((blank,index)=>({key:blank.key,label:blank.label||`Dropdown ${index+1}`,options:(blank.options||[]).map(option=>option.label),correct:(blank.options||[]).find(option=>option.is_correct)?.label||''}));clozePreviewConfirmed=false;document.getElementById('cloze_preview').style.display='none';clozeRender()}

    @endverbatim
    let highlightSegments=[],highlightSequence=0,highlightPreviewConfirmed=false;
    function highlightReset(){highlightSegments=[{key:`segment_${++highlightSequence}`,text:'',is_correct:true},{key:`segment_${++highlightSequence}`,text:'',is_correct:false}];highlightPreviewConfirmed=false;document.getElementById('highlight_preview').style.display='none';highlightRender()}
    function highlightRender(){document.getElementById('highlight_segments_editor').innerHTML=highlightSegments.map((segment,index)=>`<div class="highlight-segment-row"><input type="checkbox" ${segment.is_correct?'checked':''} title="Correct highlight" onchange="highlightUpdate('${segment.key}','is_correct',this.checked)"><input type="text" value="${matrixEscape(segment.text)}" placeholder="Highlightable phrase ${index+1}" maxlength="500" oninput="highlightUpdate('${segment.key}','text',this.value)"><button type="button" class="btn-ghost" ${highlightSegments.length<=2?'disabled':''} onclick="highlightRemoveSegment('${segment.key}')">Remove</button></div>`).join('');highlightSyncJson()}
    function highlightAddSegment(){if(highlightSegments.length>=30)return showAdminAlert('A maximum of 30 highlightable phrases is allowed.');highlightSegments.push({key:`segment_${++highlightSequence}`,text:'',is_correct:false});highlightPreviewConfirmed=false;highlightRender()}
    function highlightRemoveSegment(key){if(highlightSegments.length<=2)return showAdminAlert('At least two highlightable phrases are required.');highlightSegments=highlightSegments.filter(segment=>segment.key!==key);highlightPreviewConfirmed=false;highlightRender()}
    function highlightUpdate(key,property,value){const segment=highlightSegments.find(item=>item.key===key);if(segment)segment[property]=value;highlightPreviewConfirmed=false;highlightSyncJson()}
    function highlightCollect(){const segments=highlightSegments.map(segment=>({...segment,text:segment.text.trim()})).filter(segment=>segment.text);if(segments.length<2)throw new Error('Add at least two non-empty highlightable phrases.');if(!segments.some(segment=>segment.is_correct))throw new Error('Mark at least one phrase as a correct highlight.');return{type:'highlight_text',segments,maximum_points:Number(document.getElementById('q_maximum_points').value)||1}}
    function highlightSyncJson(){if(document.getElementById('q_response_type').value!=='highlight')return;try{document.getElementById('q_response_config').value=JSON.stringify(highlightCollect())}catch(error){document.getElementById('q_response_config').value=''}}
    function highlightPassageHtml(config){return(config.segments||[]).map(segment=>`<button type="button" class="highlight-choice" data-key="${matrixEscape(segment.key)}">${matrixEscape(segment.text)}</button>`).join(' ')}
    function highlightShowPreview(){try{const config=highlightCollect();document.getElementById('q_response_config').value=JSON.stringify(config);const wrap=document.getElementById('highlight_preview');const image=document.getElementById('q_image_preview');const imageHtml=image&&image.src&&document.getElementById('q_image_preview_wrap').style.display!=='none'?`<img src="${matrixEscape(image.src)}" alt="Question support" style="display:block;max-width:100%;max-height:320px;object-fit:contain;margin:0 auto 1rem">`:'';wrap.innerHTML=`${imageHtml}<strong>Examinee Preview</strong><div class="highlight-preview-passage">${highlightPassageHtml(config)}</div>`;wrap.querySelectorAll('.highlight-choice').forEach(button=>button.onclick=()=>button.classList.toggle('selected'));wrap.style.display='block';highlightPreviewConfirmed=true;wrap.scrollIntoView({behavior:'smooth',block:'nearest'})}catch(error){showAdminAlert(error.message)}}
    function highlightLoad(config){highlightSegments=(config.segments||[]).map(segment=>({...segment}));highlightSequence=Math.max(highlightSequence,...highlightSegments.map(segment=>Number(String(segment.key).replace(/\D/g,''))||0));highlightPreviewConfirmed=false;document.getElementById('highlight_preview').style.display='none';highlightRender()}

    let matrixColumns = [], matrixRows = [], matrixSequence = 0;
    const matrixId = prefix => `${prefix}_${Date.now()}_${++matrixSequence}`;
    const matrixEscape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const matrixChoice = (label='', points=0, correct=false) => ({id:matrixId('option'),label,points:Number(points)||0,is_correct:correct});
    function matrixEmptyCell(type){return ['dropdown','sata'].includes(type)?{type,options:[matrixChoice('',1,true),matrixChoice('',0,false)]}:{type:'static_text',value:''}}
    function matrixReset(){
        matrixColumns=[{id:matrixId('column'),key:'column_1',label:'Patient Finding',type:'static_text'},{id:matrixId('column'),key:'column_2',label:'Interpretation',type:'dropdown'}];
        matrixRows=[]; matrixAddRow(false); matrixAddRow(false); matrixRender();
    }
    function matrixAddColumn(render=true){if(matrixColumns.length>=4)return alert('A maximum of 4 columns is allowed.');const col={id:matrixId('column'),key:`column_${matrixColumns.length+1}`,label:`Column ${matrixColumns.length+1}`,type:'dropdown'};matrixColumns.push(col);matrixRows.forEach(row=>row.cells[col.id]=matrixEmptyCell(col.type));if(render)matrixRender()}
    function matrixRemoveColumn(id){if(matrixColumns.length<=2)return alert('At least 2 columns are required.');matrixColumns=matrixColumns.filter(c=>c.id!==id);matrixRows.forEach(r=>delete r.cells[id]);matrixRender()}
    function matrixUpdateColumn(id,prop,value){
        const col=matrixColumns.find(c=>c.id===id);if(!col)return;
        col[prop]=value;
        if(prop==='label'){
            const index=matrixColumns.findIndex(c=>c.id===id);
            const header=document.querySelector(`#matrix_author_head th:nth-child(${index+1})`);
            if(header)header.textContent=value||'Untitled column';
            matrixSyncJson();
            return;
        }
        if(prop==='type')matrixRows.forEach(r=>r.cells[id]=matrixEmptyCell(value));
        matrixRender();
    }
    function matrixAddRow(render=true){if(matrixRows.length>=10)return alert('A maximum of 10 rows is allowed.');const row={id:matrixId('row'),key:`row_${matrixRows.length+1}`,cells:{}};matrixColumns.forEach(c=>row.cells[c.id]=matrixEmptyCell(c.type));matrixRows.push(row);if(render)matrixRender()}
    function matrixRemoveRow(id){if(matrixRows.length<=1)return alert('At least one row is required.');matrixRows=matrixRows.filter(r=>r.id!==id);matrixRender()}
    function matrixAddChoice(rowId,colId){matrixRows.find(r=>r.id===rowId).cells[colId].options.push(matrixChoice());matrixRender()}
    function matrixRemoveChoice(rowId,colId,choiceId){const cell=matrixRows.find(r=>r.id===rowId).cells[colId];if(cell.options.length<=2)return;cell.options=cell.options.filter(o=>o.id!==choiceId);if(!cell.options.some(o=>o.is_correct))cell.options[0].is_correct=true;matrixRender()}
    function matrixUpdateChoice(rowId,colId,choiceId,prop,value){const option=matrixRows.find(r=>r.id===rowId).cells[colId].options.find(o=>o.id===choiceId);option[prop]=prop==='points'?Number(value)||0:value;matrixSyncJson()}
    function matrixSetCorrect(rowId,colId,choiceId){const cell=matrixRows.find(r=>r.id===rowId).cells[colId];if(cell.type==='sata'){const option=cell.options.find(o=>o.id===choiceId);option.is_correct=!option.is_correct}else cell.options.forEach(o=>o.is_correct=o.id===choiceId);matrixSyncJson()}
    function matrixRender(){
        const cols=document.getElementById('matrix_columns_editor');if(!cols)return;
        cols.innerHTML=matrixColumns.map((c,i)=>`<div class="matrix-column-card"><div><label>Column ${i+1} label</label><input value="${matrixEscape(c.label)}" oninput="matrixUpdateColumn('${c.id}','label',this.value)"></div><div><label>Type</label><select onchange="matrixUpdateColumn('${c.id}','type',this.value)"><option value="static_text" ${c.type==='static_text'?'selected':''}>Static text</option><option value="dropdown" ${c.type==='dropdown'?'selected':''}>Dropdown</option><option value="sata" ${c.type==='sata'?'selected':''}>SATA choices</option></select></div><button type="button" class="btn-ghost" onclick="matrixRemoveColumn('${c.id}')">Remove</button></div>`).join('');
        document.getElementById('matrix_author_head').innerHTML=`<tr>${matrixColumns.map(c=>`<th>${matrixEscape(c.label)}</th>`).join('')}<th>Action</th></tr>`;
        document.getElementById('matrix_author_body').innerHTML=matrixRows.map(row=>`<tr>${matrixColumns.map(col=>{const cell=row.cells[col.id]||(row.cells[col.id]=matrixEmptyCell(col.type));if(col.type==='static_text')return `<td><textarea placeholder="Enter cell text" oninput="matrixRows.find(r=>r.id==='${row.id}').cells['${col.id}'].value=this.value;matrixSyncJson()">${matrixEscape(cell.value)}</textarea></td>`;return `<td><div class="matrix-cell-options">${cell.options.map(o=>`<div class="matrix-choice-row"><input type="${col.type==='sata'?'checkbox':'radio'}" name="matrix_correct_${row.id}_${col.id}" ${o.is_correct?'checked':''} onchange="matrixSetCorrect('${row.id}','${col.id}','${o.id}')"><input value="${matrixEscape(o.label)}" placeholder="Choice" oninput="matrixUpdateChoice('${row.id}','${col.id}','${o.id}','label',this.value)"><input type="number" min="0" step=".01" value="${o.points}" title="Points" oninput="matrixUpdateChoice('${row.id}','${col.id}','${o.id}','points',this.value)"><button type="button" class="btn-ghost" onclick="matrixRemoveChoice('${row.id}','${col.id}','${o.id}')">×</button></div>`).join('')}<button type="button" class="btn-ghost" onclick="matrixAddChoice('${row.id}','${col.id}')">+ Choice</button></div></td>`}).join('')}<td><button type="button" class="btn-ghost" onclick="matrixRemoveRow('${row.id}')">Remove</button></td></tr>`).join('');
        matrixSyncJson();
    }
    function matrixCollect(){
        const config={type:'dynamic_matrix_grid',title:document.getElementById('q_text').value.trim(),instructions:document.getElementById('q_text').value.trim(),maximum_points:Number(document.getElementById('q_maximum_points').value)||1,columns:matrixColumns.map((c,i)=>({key:c.key,label:c.label.trim(),type:c.type,display_order:i+1})),rows:matrixRows.map((r,ri)=>({key:r.key,display_order:ri+1,cells:matrixColumns.map(c=>{const cell=r.cells[c.id];return c.type==='static_text'?{column_key:c.key,type:'static_text',value:(cell.value||'').trim()}:{column_key:c.key,type:c.type,options:cell.options.filter(o=>o.label.trim()).map((o,oi)=>({value:o.label.trim().toLowerCase().replace(/\s+/g,'_'),label:o.label.trim(),points:Number(o.points)||0,is_correct:Boolean(o.is_correct),display_order:oi+1}))}})}))};
        if(config.columns.length<2||!config.rows.length)throw new Error('The matrix requires at least two columns and one row.');
        config.rows.forEach((row,ri)=>row.cells.forEach((cell,ci)=>{if(cell.type==='static_text'&&!cell.value)throw new Error(`Enter text in row ${ri+1}, column ${ci+1}.`);if(['dropdown','sata'].includes(cell.type)&&(cell.options.length<2||!cell.options.some(o=>o.is_correct)))throw new Error(`Complete the choices and correct answer in row ${ri+1}, column ${ci+1}.`)}));return config;
    }
    function matrixSyncJson(){try{document.getElementById('q_response_config').value=JSON.stringify(matrixCollect())}catch(e){document.getElementById('q_response_config').value=''}}
    function matrixShowPreview(){try{const config=matrixCollect();matrixSyncJson();const wrap=document.getElementById('matrix_preview');const image=document.getElementById('q_image_preview');const imageHtml=image&&image.src&&document.getElementById('q_image_preview_wrap').style.display!=='none'?`<img src="${matrixEscape(image.src)}" alt="Question support" style="display:block;max-width:100%;max-height:320px;object-fit:contain;margin:0 auto 1rem">`:'';wrap.style.display='block';wrap.innerHTML=`${imageHtml}<h3>${matrixEscape(config.title||'Question preview')}</h3><div class="table-wrap"><table class="matrix-preview-table"><thead><tr>${config.columns.map(c=>`<th>${matrixEscape(c.label)}</th>`).join('')}</tr></thead><tbody>${config.rows.map(r=>`<tr>${r.cells.map(cell=>cell.type==='static_text'?`<td>${matrixEscape(cell.value)}</td>`:cell.type==='sata'?`<td>${cell.options.map(o=>`<label style="display:flex;gap:.4rem;align-items:center"><input type="checkbox"> ${matrixEscape(o.label)}</label>`).join('')}</td>`:`<td><select><option value="">Select an answer</option>${cell.options.map(o=>`<option value="${matrixEscape(o.value)}">${matrixEscape(o.label)}</option>`).join('')}</select></td>`).join('')}</tr>`).join('')}</tbody></table></div>`}catch(error){alert(error.message)}}
    function matrixLoad(config){
        matrixColumns=(config.columns||[]).map(c=>({...c,id:matrixId('column')}));
        matrixRows=(config.rows||[]).map(r=>{
            const row={...r,id:matrixId('row'),cells:{}};
            (r.cells||[]).forEach(cell=>{
                const col=matrixColumns.find(c=>c.key===cell.column_key);
                if(col)row.cells[col.id]=['dropdown','sata'].includes(cell.type)
                    ? {...cell,options:(cell.options||[]).map(o=>({...o,id:matrixId('option')}))}
                    : {...cell};
            });
            return row;
        });
        document.getElementById('matrix_preview').style.display='none';
        matrixRender();
    }

    document.getElementById('q_maximum_points').addEventListener('input',matrixSyncJson);
    document.getElementById('q_text').addEventListener('input',()=>{if(document.getElementById('q_response_type').value==='cloze')clozePreviewConfirmed=false});
    document.getElementById('questionForm').addEventListener('submit', event => {
        const type=document.getElementById('q_response_type').value;
        if (!['grid','cloze','highlight'].includes(type)) return;
        try {
            if(type==='grid')document.getElementById('q_response_config').value=JSON.stringify(matrixCollect());
            else if(type==='cloze'){if(!clozePreviewConfirmed)throw new Error('Preview the Cloze examinee view before saving.');document.getElementById('q_response_config').value=JSON.stringify(clozeCollect())}
            else {if(!highlightPreviewConfirmed)throw new Error('Preview the Highlighting examinee view before saving.');document.getElementById('q_response_config').value=JSON.stringify(highlightCollect())}
        }
        catch (error) { event.preventDefault(); alert(error.message); }
    });
</script>
@endsection
