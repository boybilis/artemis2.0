@extends('admin.layouts.app')

@section('title', 'Topics and Subtopics')
@section('kicker', 'Content Management')

@section('header_actions')
    <div style="display:flex;gap:.55rem;flex-wrap:wrap">
        <button class="btn btn-ghost" type="button" onclick="openModal('importTopicModal')" {{ $importableTopics->isEmpty() ? 'disabled' : '' }}>Import topic</button>
        <button class="btn btn-primary" type="button" onclick="openAddTopicModal()">Add topic</button>
    </div>
@endsection

@section('content')
@php
    $isAdmin = Auth::user()->is_admin || trim(strtolower(Auth::user()->role)) === 'admin';
    $isInstructor = trim(strtolower(Auth::user()->role)) === 'instructor' && !Auth::user()->is_admin;
@endphp
<style>
    input[type="file"] { display: flex; align-items: center; }
    input[type="file"]::file-selector-button {
        background: var(--surface-solid); border: 1px solid var(--border); color: var(--text);
        padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; margin: 0; margin-right: 1rem;
        font-family: inherit; transition: all 0.2s; vertical-align: middle;
    }
    input[type="file"]::file-selector-button:hover { background: rgba(255,255,255,0.1); }
    body.light-mode input[type="file"]::file-selector-button { background: rgba(0,0,0,0.05); }
    body.light-mode input[type="file"]::file-selector-button:hover { background: rgba(0,0,0,0.1); }

    /* Modern Topic Cards Layout */
    .topic-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .topic-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .topic-card:hover {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .topic-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.01);
    }
    .topic-header:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    .topic-drag-handle {
        color: var(--text-muted);
        cursor: grab;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    .topic-drag-handle:hover {
        opacity: 1;
    }
    .topic-info {
        flex: 1;
    }
    .topic-info h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .topic-info p {
        margin: 0.25rem 0 0;
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    .topic-meta {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .topic-stats {
        text-align: right;
    }
    .topic-stats strong {
        display: block;
        font-size: 1.1rem;
        color: var(--accent);
        line-height: 1;
    }
    .topic-stats span {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .topic-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .subtopics-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        padding: 0;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        transition: background .2s, color .2s, border-color .2s;
    }
    .subtopics-toggle:hover { background: rgba(124,58,237,.1); color: var(--accent); border-color: var(--accent); }
    .subtopics-toggle svg { width: 18px; height: 18px; transition: transform .2s; }
    .subtopics-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
    .upload-progress{display:none;margin-top:.75rem}.upload-progress-track{height:8px;overflow:hidden;border-radius:99px;background:var(--border)}.upload-progress-bar{width:0;height:100%;border-radius:99px;background:var(--gradient);transition:width .15s}.upload-progress-text{display:flex;justify-content:space-between;margin-top:.35rem;color:var(--text-muted);font-size:.75rem}
    
    /* Subtopics Panel (Accordion Content) */
    .subtopics-panel {
        display: none;
        border-top: 1px solid var(--border);
        background: rgba(0, 0, 0, 0.1);
    }
    .subtopics-panel.open {
        display: block;
    }
    .subtopics-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 1.25rem;
        background: rgba(99, 102, 241, 0.04);
        border-bottom: 1px solid var(--border);
    }
    .subtopics-panel-header h4 { margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--accent); }
    .subtopic-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid var(--border);
        transition: background 0.15s;
    }
    .subtopic-row:last-child { border-bottom: none; }
    .subtopic-row:hover { background: rgba(255, 255, 255, 0.02); }
    .subtopic-info strong { display: block; font-size: 0.95rem; color: var(--text); margin-bottom: 0.25rem; }
    .subtopic-badges { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
    .badge-video { background: rgba(16,185,129,0.12); color: #10b981; padding: 0.2rem 0.55rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
    .badge-pdf   { background: rgba(99,102,241,0.12); color: var(--accent); padding: 0.2rem 0.55rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
    .badge-pending { background: rgba(245,158,11,0.12); color: #f59e0b; padding: 0.2rem 0.55rem; border-radius: 99px; font-size: 0.7rem; font-weight: 600; }
    .subtopic-actions { display: flex; gap: 0.35rem; }
</style>

<div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
    <a href="{{ route('admin.content.subjects', $course->id) }}" class="btn-ghost" style="padding: 0.5rem; color: var(--text-muted); text-decoration: none;"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Subjects</a>
    <h2 style="margin:0; font-family: 'Outfit', sans-serif;">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</h2>
</div>

<div class="tabs">
    <a class="tab" href="{{ route('admin.content.subjects', $course->id) }}">Subjects</a>
    <a class="tab active" href="{{ route('admin.content.topics', ['course' => $course->id, 'subject_id' => $managedSubject->id]) }}">Topics and subtopics</a>
    <a class="tab" href="{{ route('admin.content.quizzes', ['course' => $course->id, 'subject_id' => $managedSubject->id]) }}">Assessments</a>
</div>

<div class="split-grid">
    <section class="panel">
        <div class="toolbar">
            <div>
                <p class="panel-label">Topics</p>
                <h2 class="panel-title">Lesson Catalog</h2>
            </div>
        </div>

        @if(session('success'))
            <div style="margin: 0 0 1rem; padding: 0.75rem 1rem; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); border-radius: 8px; color: #10b981; font-size: 0.875rem; font-weight: 500;">
                ✓ {{ session('success') }}
            </div>
        @endif

        <div class="topic-list" id="topicsList">
            @forelse ($topics as $topic)
                <div class="topic-card" data-id="{{ $topic->id }}">
                    <!-- Topic Header (Always Visible) -->
                    <div class="topic-header" onclick="toggleSubtopicsPanel({{ $topic->id }})">
                        <div class="topic-drag-handle" onclick="event.stopPropagation()">
                            <i data-lucide="grip-vertical"></i>
                        </div>
                        <div class="sort-order-cell" style="font-weight: 600; color: var(--text-muted); width: 20px;">
                            {{ $topic->sort_order }}
                        </div>
                        <div class="topic-info">
                            <h3>
                                {{ $topic->title }}
                                @if($topic->subject)
                                    <span class="status info" style="font-size:.7rem;padding:.15rem .5rem">{{ $topic->subject->subject_code }} — {{ $topic->subject->title }}</span>
                                @endif
                                @if($topic->status === 'approved')
                                    <span class="status success" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">Approved</span>
                                @else
                                    <span class="status warning" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">Pending</span>
                                @endif
                            </h3>
                            @if($topic->description)
                                <p>{{ Str::limit($topic->description, 80) }}</p>
                            @endif
                        </div>
                        
                        <div class="topic-meta">
                            <div class="topic-stats">
                                <strong>{{ $topic->subtopics->count() }}</strong>
                                <span>Subtopic{{ $topic->subtopics->count() !== 1 ? 's' : '' }}</span>
                            </div>
                            
                            <div class="topic-actions" onclick="event.stopPropagation()">
                                @if($topic->status === 'pending' && $isAdmin)
                                    <form action="{{ route('admin.content.topics.approve', ['course' => $course->id, 'topic' => $topic->id]) }}" method="POST" style="margin:0;">
                                        @csrf
                                        <button class="btn-primary" type="submit" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; background: var(--correct); border-color: var(--correct);">Approve</button>
                                    </form>
                                @endif
                                
                                @if(!($isAdmin && $topic->status === 'pending'))
                                    <button class="btn-ghost edit-topic-btn" type="button" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;"
                                        data-id="{{ $topic->id }}"
                                        data-subject-id="{{ $topic->subject_id }}"
                                        data-title="{{ $topic->title }}"
                                        data-description="{{ $topic->description }}">
                                        Edit
                                    </button>
                                @endif
                                <form action="{{ route('admin.content.topics.destroy', ['course' => $course->id, 'topic' => $topic->id]) }}" method="POST" onsubmit="return confirmDelete(event, 'Delete this topic and all its subtopics/quizzes?');" style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-ghost" type="submit" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; color: var(--wrong);">Delete</button>
                                </form>
                                <button type="button" class="subtopics-toggle" aria-label="Toggle subtopics for {{ $topic->title }}" aria-expanded="false" aria-controls="subtopics-panel-{{ $topic->id }}" onclick="event.stopPropagation(); toggleSubtopicsPanel({{ $topic->id }}, this)">
                                    <i data-lucide="chevron-down"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Subtopics Panel (Collapsible) -->
                    <div class="subtopics-panel" id="subtopics-panel-{{ $topic->id }}">
                        <div class="subtopics-panel-header">
                            <h4>Subtopics ({{ $topic->subtopics->count() }})</h4>
                            <button type="button" class="btn-primary btn-sm" onclick="openAddSubtopicModal({{ $topic->id }}, '{{ addslashes($topic->title) }}')" style="padding: 0.3rem 0.7rem; font-size: 0.78rem;">+ Add Subtopic</button>
                        </div>

                        @forelse ($topic->subtopics as $sub)
                            <div class="subtopic-row">
                                <div class="subtopic-info">
                                    <strong>{{ $sub->sort_order }}. {{ $sub->title }}</strong>
                                    <div class="subtopic-badges" style="margin-top: 0.3rem;">
                                        @if(!in_array($sub->content_type, ['subtopic', 'zoom_link']))
                                            <span class="status info">{{ str($sub->content_type)->replace('_', ' ')->title() }} · {{ $sub->questions()->count() }} questions</span>
                                        @elseif($sub->content_type === 'zoom_link')
                                            <span class="status info">Zoom Session · {{ $sub->zoom_starts_at?->format('M d, Y h:i A') }}</span>
                                        @endif
                                        @if($sub->video_url || $sub->video_path)
                                            <span class="badge-video">Video</span>
                                        @endif
                                        @if($sub->documentation_path)
                                            <span class="badge-pdf">{{ $sub->documentation_filename }}</span>
                                        @endif
                                        @if($sub->status === 'pending')
                                            <span class="badge-pending">Pending</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="subtopic-actions">
                                    @if($sub->status === 'pending' && $isAdmin)
                                        <form action="{{ route('admin.content.subtopics.approve', ['course' => $course->id, 'subtopic' => $sub->id]) }}" method="POST" style="margin:0;">
                                            @csrf
                                            <button class="btn-ghost" type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: rgba(16,185,129,0.1); color: #10b981; border-color: rgba(16,185,129,0.3);">Approve</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn-ghost edit-subtopic-btn" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;"
                                        data-id="{{ $sub->id }}"
                                        data-content-type="{{ $sub->content_type }}"
                                        data-title="{{ $sub->title }}"
                                        data-instructions="{{ $sub->instructions }}"
                                        data-zoom-url="{{ $sub->zoom_url }}"
                                        data-zoom-description="{{ $sub->zoom_description }}"
                                        data-zoom-starts-at="{{ $sub->zoom_starts_at?->format('Y-m-d\TH:i') }}"
                                        data-zoom-ends-at="{{ $sub->zoom_ends_at?->format('Y-m-d\TH:i') }}"
                                        data-maximum-attempts="{{ $sub->maximum_attempts }}"
                                        data-video="{{ $sub->video_url }}"
                                        data-video-file="{{ $sub->video_filename }}"
                                        data-doc="{{ $sub->documentation_filename }}"
                                        data-sort="{{ $sub->sort_order }}">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.content.subtopics.destroy', ['course' => $course->id, 'subtopic' => $sub->id]) }}" method="POST" onsubmit="return confirmDelete(event, 'Delete this subtopic?');" style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-ghost" type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: var(--wrong);">Delete</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div style="padding: 1.25rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                                No subtopics yet. Click "+ Add Subtopic" to get started.
                            </div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div style="padding: 3rem; text-align: center; color: var(--text-muted); background: var(--surface); border-radius: 12px; border: 1px dashed var(--border);">
                    No topics found. Create your first topic!
                </div>
            @endforelse
        </div>
    </section>

    <aside class="panel">
        <p class="panel-label">Course structure</p>
        <h2 class="panel-title">Artemis Review Learning Flow</h2>
        <p class="panel-subtitle" style="line-height: 1.6;">Courses are organized into <strong>Subjects</strong>, <strong>Topics</strong>, and learning contents such as PDFs, videos, Pre-tests, Post-tests, and Practice Tests. Learners complete each required content to build subject progress. The course-level <strong>Mock Exam</strong> appears below the subject list when approved Mock Exam questions are available and unlocks only after every subject reaches 100%.</p>
        <div class="list-stack">
            <div class="list-item">
                <strong>Topics</strong>
                <span class="muted">Top-level course modules (e.g., "Fundamentals of Nursing"). Each contains lessons and one quiz.</span>
            </div>
            <div class="list-item">
                <strong>Subtopics</strong>
                <span class="muted">Individual lessons inside a topic. Each has its own video and optional PDF/slides.</span>
            </div>
            <div class="list-item">
                <strong>Video + PDF per Subtopic</strong>
                <span class="muted">Students can switch between the video and the document slides for each subtopic.</span>
            </div>
        </div>
    </aside>
</div>

<!-- ================= MODALS ================= -->

<div id="importTopicModal" class="admin-modal">
    <form action="{{ route('admin.content.topics.import', $course->id) }}" method="POST" class="admin-modal-content" style="max-width:620px">
        @csrf
        <input type="hidden" name="subject_id" value="{{ $managedSubject->id }}">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Import Topic</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('importTopicModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="field">
                <label>Import into</label>
                <div class="input" style="padding:.8rem 1rem;border:1.5px solid var(--border);border-radius:8px;background:rgba(124,58,237,.06);font-weight:700">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</div>
            </div>
            <div class="field" style="margin-top:1rem">
                <label for="source_topic_id">Topic from another subject</label>
                <select id="source_topic_id" name="source_topic_id" required>
                    <option value="">Select a topic to import</option>
                    @foreach($importableTopics->groupBy('subject_id') as $sourceTopics)
                        <optgroup label="{{ $sourceTopics->first()->subject?->subject_code }} — {{ $sourceTopics->first()->subject?->title }}">
                            @foreach($sourceTopics as $sourceTopic)
                                <option value="{{ $sourceTopic->id }}">{{ $sourceTopic->title }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <small class="muted">The topic, subtopics, media, assessment entries, questions, and assessment rules will be copied. The imported topic can then be edited independently.</small>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('importTopicModal')">Cancel</button>
            <button type="submit" class="btn-primary" {{ $importableTopics->isEmpty() ? 'disabled' : '' }}>Import Topic and Content</button>
        </div>
    </form>
</div>

<!-- ADD TOPIC MODAL -->
<div id="addTopicModal" class="admin-modal">
    <form action="{{ route('admin.content.topics.store', $course->id) }}" method="POST" enctype="multipart/form-data" class="admin-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add New Topic</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('addTopicModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="field">
                    <label>Subject</label>
                    <input type="hidden" id="add_subject_id" name="subject_id" value="{{ $managedSubject->id }}">
                    <div class="input" style="padding:.8rem 1rem;border:1.5px solid var(--border);border-radius:8px;background:rgba(124,58,237,.06);font-weight:700">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</div>
                    <small class="muted">This topic will be added to the subject currently being managed.</small>
                </div>
                <div class="field">
                    <label for="add_title">Topic Title</label>
                    <input type="text" id="add_title" name="title" required placeholder="e.g. Fundamentals of Nursing">
                </div>
                <div class="field">
                    <label for="add_description">Topic Description</label>
                    <textarea id="add_description" name="description" required placeholder="Brief overview shown on the dashboard..." style="width: 100%; min-height: 80px; padding: 0.8rem; border-radius: 8px; border: 1.5px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); font-family: inherit; resize: vertical;"></textarea>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('addTopicModal')">Cancel</button>
            <button type="submit" class="btn-primary">Create Topic</button>
        </div>
    </form>
</div>

<!-- EDIT TOPIC MODAL -->
<div id="editTopicModal" class="admin-modal">
    <form id="editTopicForm" method="POST" enctype="multipart/form-data" class="admin-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Edit Topic</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('editTopicModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="field">
                    <label>Subject</label>
                    <input type="hidden" id="edit_subject_id" name="subject_id" value="{{ $managedSubject->id }}">
                    <div class="input" style="padding:.8rem 1rem;border:1.5px solid var(--border);border-radius:8px;background:rgba(124,58,237,.06);font-weight:700">{{ $managedSubject->subject_code }} — {{ $managedSubject->title }}</div>
                </div>
                <div class="field">
                    <label for="edit_title">Topic Title</label>
                    <input type="text" id="edit_title" name="title" required placeholder="e.g. Fundamentals of Nursing">
                </div>
                <div class="field">
                    <label for="edit_description">Topic Description</label>
                    <textarea id="edit_description" name="description" required placeholder="Brief overview..." style="width: 100%; min-height: 80px; padding: 0.8rem; border-radius: 8px; border: 1.5px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text); font-family: inherit; resize: vertical;"></textarea>
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('editTopicModal')">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<!-- ADD SUBTOPIC MODAL -->
<div id="addSubtopicModal" class="admin-modal">
    <form id="addSubtopicForm" action="{{ route('admin.content.subtopics.store', $course->id) }}" method="POST" enctype="multipart/form-data" class="admin-modal-content">
        @csrf
        <input type="hidden" id="add_sub_topic_id" name="topic_id">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Subtopic</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('addSubtopicModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p class="muted" id="add_sub_topic_label" style="margin-bottom: 1rem; font-size: 0.85rem;"></p>
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="field">
                    <label for="add_sub_type">Content type</label>
                    <select id="add_sub_type" name="content_type" required onchange="syncSubtopicFields('add')">
                        <option value="subtopic">Subtopic</option>
                        <option value="zoom_link">Zoom Link</option>
                        <option value="pre_test">Pre-test</option>
                        <option value="post_test">Post-test</option>
                        <option value="practice_test">Practice Test</option>
                        <option value="mock_exam">Mock Exam</option>
                    </select>
                </div>
                <div id="add_sub_assessment_fields" style="display:none">
                    <div class="field">
                        <label for="add_sub_instructions">Assessment instructions</label>
                        <textarea id="add_sub_instructions" name="instructions" rows="5" placeholder="Instructions shown before the learner starts the assessment"></textarea>
                    </div>
                    <div class="field" id="add_sub_attempts_field" style="display:none;margin-top:1rem">
                        <label id="add_sub_attempts_label" for="add_sub_max_attempts">Maximum allowed tries</label>
                        <select id="add_sub_max_attempts" name="maximum_attempts">
                            <option value="">Unlimited</option>
                            @foreach([1,2,3,4,5] as $attempt)<option value="{{ $attempt }}">{{ $attempt }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div id="add_sub_lesson_fields">
                    <div class="field">
                        <label for="add_sub_title">Subtopic Title</label>
                        <input type="text" id="add_sub_title" name="title" required placeholder="e.g. Introduction to the Box Model">
                    </div>
                    <div class="field" style="margin-top:1rem">
                        <label for="add_sub_video">Video URL (YouTube or Google Drive)</label>
                        <input type="text" id="add_sub_video" name="video_url" placeholder="Paste a YouTube or Google Drive sharing link">
                        <p class="muted" style="font-size: 0.78rem; margin-top: 0.3rem;">Private Drive videos must be inside the approved Artemis folder shared with the Artemis service account.</p>
                    </div>
                    <div class="field" style="border:1px dashed var(--border);padding:1rem;border-radius:8px;margin-top:1rem">
                        <label for="add_sub_video_file">Or upload a video file</label>
                        <p class="muted" style="margin:.3rem 0 .6rem;font-size:.78rem">MP4, WebM, MOV, or M4V. Maximum 38 MB on this local server.</p>
                        <input type="file" id="add_sub_video_file" name="video_file" accept="video/mp4,video/webm,video/quicktime,.m4v">
                        <div id="add_sub_upload_progress" class="upload-progress"><div class="upload-progress-track"><div class="upload-progress-bar"></div></div><div class="upload-progress-text"><span>Uploading video…</span><strong>0%</strong></div></div>
                    </div>
                    <div class="field" style="border:1px dashed var(--border);padding:1rem;border-radius:8px;margin-top:1rem">
                        <label for="add_sub_doc">PDF / Slides (Optional)</label>
                        <p class="muted" style="margin-top:0;font-size:.8rem;margin-bottom:.5rem">Upload a PDF or presentation file for this subtopic.</p>
                        <input type="file" id="add_sub_doc" name="documentation" accept=".pdf,.ppt,.pptx,.doc,.docx,image/*" style="background:rgba(255,255,255,.02);color:var(--text);padding:.3rem;border-radius:8px;width:100%;border:1.5px solid var(--border);cursor:pointer;font-family:inherit;font-size:.85rem;height:2.8rem;box-sizing:border-box">
                    </div>
                </div>
                <div id="add_sub_zoom_fields" style="display:none">
                    <div class="field"><label for="add_sub_zoom_title">Session title</label><input id="add_sub_zoom_title" type="text" placeholder="e.g. Live NCLEX Review"></div>
                    <div class="field" style="margin-top:1rem"><label for="add_sub_zoom_url">Zoom meeting link</label><input id="add_sub_zoom_url" name="zoom_url" type="url" placeholder="https://zoom.us/j/..."></div>
                    <div class="field" style="margin-top:1rem"><label for="add_sub_zoom_description">Session description</label><textarea id="add_sub_zoom_description" name="zoom_description" rows="4" placeholder="Describe the live session and what learners should prepare."></textarea></div>
                    <div class="form-grid" style="margin-top:1rem"><div class="field"><label for="add_sub_zoom_starts_at">Starts</label><input id="add_sub_zoom_starts_at" name="zoom_starts_at" type="datetime-local"></div><div class="field"><label for="add_sub_zoom_ends_at">Ends (optional)</label><input id="add_sub_zoom_ends_at" name="zoom_ends_at" type="datetime-local"></div></div>
                </div>
                <div class="field">
                    <label for="add_sub_sort">Order (optional)</label>
                    <input type="number" id="add_sub_sort" name="sort_order" min="1" placeholder="Auto-assigned if empty" style="max-width: 150px;">
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('addSubtopicModal')">Cancel</button>
            <button type="submit" class="btn-primary">Add Subtopic</button>
        </div>
    </form>
</div>

<!-- EDIT SUBTOPIC MODAL -->
<div id="editSubtopicModal" class="admin-modal">
    <form id="editSubtopicForm" method="POST" enctype="multipart/form-data" class="admin-modal-content">
        @csrf
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Edit Subtopic</h3>
            <button type="button" class="admin-modal-close" onclick="closeModal('editSubtopicModal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="form-grid" style="grid-template-columns: 1fr;">
                <div class="field"><label for="edit_sub_type">Content type</label><select id="edit_sub_type" name="content_type" required onchange="syncSubtopicFields('edit')"><option value="subtopic">Subtopic</option><option value="zoom_link">Zoom Link</option><option value="pre_test">Pre-test</option><option value="post_test">Post-test</option><option value="practice_test">Practice Test</option><option value="mock_exam">Mock Exam</option></select></div>
                <div id="edit_sub_assessment_fields" style="display:none">
                    <div class="field"><label for="edit_sub_instructions">Assessment instructions</label><textarea id="edit_sub_instructions" name="instructions" rows="5"></textarea></div>
                    <div class="field" id="edit_sub_attempts_field" style="display:none;margin-top:1rem"><label id="edit_sub_attempts_label" for="edit_sub_max_attempts">Maximum allowed tries</label><select id="edit_sub_max_attempts" name="maximum_attempts"><option value="">Unlimited</option>@foreach([1,2,3,4,5] as $attempt)<option value="{{ $attempt }}">{{ $attempt }}</option>@endforeach</select></div>
                </div>
                <div id="edit_sub_lesson_fields">
                <div class="field">
                    <label for="edit_sub_title">Subtopic Title</label>
                    <input type="text" id="edit_sub_title" name="title" required>
                </div>
                <div id="edit_sub_zoom_fields" style="display:none">
                    <div class="field"><label for="edit_sub_zoom_title">Session title</label><input id="edit_sub_zoom_title" type="text"></div>
                    <div class="field" style="margin-top:1rem"><label for="edit_sub_zoom_url">Zoom meeting link</label><input id="edit_sub_zoom_url" name="zoom_url" type="url"></div>
                    <div class="field" style="margin-top:1rem"><label for="edit_sub_zoom_description">Session description</label><textarea id="edit_sub_zoom_description" name="zoom_description" rows="4"></textarea></div>
                    <div class="form-grid" style="margin-top:1rem"><div class="field"><label for="edit_sub_zoom_starts_at">Starts</label><input id="edit_sub_zoom_starts_at" name="zoom_starts_at" type="datetime-local"></div><div class="field"><label for="edit_sub_zoom_ends_at">Ends (optional)</label><input id="edit_sub_zoom_ends_at" name="zoom_ends_at" type="datetime-local"></div></div>
                </div>
                <div class="field">
                    <label for="edit_sub_video">Video URL (YouTube or Google Drive)</label>
                    <input type="text" id="edit_sub_video" name="video_url" placeholder="Paste a YouTube or Google Drive sharing link">
                    <p class="muted" style="font-size:.78rem;margin-top:.3rem">Private Drive videos must be inside the approved Artemis folder shared with the Artemis service account.</p>
                </div>
                <div class="field" style="border:1px dashed var(--border);padding:1rem;border-radius:8px;margin-top:1rem">
                    <label for="edit_sub_video_file">Replace with uploaded video</label>
                    <p class="muted" style="margin:.3rem 0 .6rem;font-size:.78rem">MP4, WebM, MOV, or M4V. Maximum 38 MB.</p>
                    <input type="file" id="edit_sub_video_file" name="video_file" accept="video/mp4,video/webm,video/quicktime,.m4v">
                    <div id="current_sub_video_info" style="display:none;margin-top:.65rem;padding:.55rem;border:1px solid rgba(16,185,129,.2);border-radius:7px;background:rgba(16,185,129,.08);font-size:.8rem"><span id="current_sub_video_name"></span><label style="display:flex;align-items:center;gap:.4rem;margin-top:.45rem;color:var(--wrong)"><input type="checkbox" name="remove_video_file" value="1" style="width:auto"> Remove uploaded video</label></div>
                    <div id="edit_sub_upload_progress" class="upload-progress"><div class="upload-progress-track"><div class="upload-progress-bar"></div></div><div class="upload-progress-text"><span>Uploading video…</span><strong>0%</strong></div></div>
                </div>
                <div class="field" style="border: 1px dashed var(--border); padding: 1rem; border-radius: 8px;">
                    <label for="edit_sub_doc">Replace PDF / Slides (Optional)</label>
                    <p class="muted" style="margin-top: 0; font-size: 0.8rem; margin-bottom: 0.5rem;">Upload a new file to replace the current one.</p>
                    <input type="file" id="edit_sub_doc" name="documentation" accept=".pdf,.ppt,.pptx,.doc,.docx,image/*" style="background: rgba(255,255,255,0.02); color: var(--text); padding: 0.3rem; border-radius: 8px; width: 100%; border: 1.5px solid var(--border); cursor: pointer; font-family: inherit; font-size: 0.85rem; height: 2.8rem; box-sizing: border-box;">
                    <div id="current_sub_doc_info" style="display: none; margin-top: 0.5rem; font-size: 0.85rem; padding: 0.5rem; background: rgba(16,185,129,0.1); border-radius: 6px; border: 1px solid rgba(16,185,129,0.2); align-items: center; justify-content: space-between;">
                        <span><strong style="color: var(--correct);">Current File:</strong> <span id="current_sub_doc_name"></span></span>
                        <label style="display: inline-flex; align-items: center; gap: 0.3rem; margin: 0; font-size: 0.8rem; color: var(--wrong); cursor: pointer;">
                            <input type="checkbox" name="remove_documentation" value="1" style="width: auto; margin: 0;"> Remove file
                        </label>
                    </div>
                </div>
                </div>
                <div class="field">
                    <label for="edit_sub_sort">Order</label>
                    <input type="number" id="edit_sub_sort" name="sort_order" min="1" style="max-width: 150px;">
                </div>
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-ghost" onclick="closeModal('editSubtopicModal')">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>


<script>
    // ─── Modal Helpers ───────────────────────────────────────────
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    // ─── Topic Panels Toggle ─────────────────────────────────────
    function toggleSubtopicsPanel(topicId, trigger = null) {
        const panel = document.getElementById('subtopics-panel-' + topicId);
        if (panel) {
            panel.classList.toggle('open');
            const toggle = trigger || document.querySelector(`[aria-controls="subtopics-panel-${topicId}"]`);
            if (toggle) toggle.setAttribute('aria-expanded', panel.classList.contains('open') ? 'true' : 'false');
        }
    }

    // ─── Add Topic Modal ─────────────────────────────────────────
    function openAddTopicModal() { openModal('addTopicModal'); }

    // ─── Edit Topic Bindings ─────────────────────────────────────
    document.querySelectorAll('.edit-topic-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id          = btn.dataset.id;
            const title       = btn.dataset.title;
            const description = btn.dataset.description;
            const subjectId    = btn.dataset.subjectId;

            document.getElementById('edit_subject_id').value  = subjectId || '';
            document.getElementById('edit_title').value       = title;
            document.getElementById('edit_description').value = description || '';
            document.getElementById('editTopicForm').action   = `/admin/content/courses/{{ $course->id }}/topics/${id}`;
            openModal('editTopicModal');
        });
    });

    // ─── Add Subtopic Modal ──────────────────────────────────────
    function openAddSubtopicModal(topicId, topicTitle) {
        document.getElementById('add_sub_topic_id').value    = topicId;
        document.getElementById('add_sub_topic_label').textContent = 'Topic: ' + topicTitle;
        document.getElementById('add_sub_title').value       = '';
        document.getElementById('add_sub_video').value       = '';
        document.getElementById('add_sub_sort').value        = '';
        document.getElementById('add_sub_type').value        = 'subtopic';
        document.getElementById('add_sub_instructions').value = '';
        document.getElementById('add_sub_max_attempts').value = '';
        ['zoom_title','zoom_url','zoom_description','zoom_starts_at','zoom_ends_at'].forEach(field => { const element=document.getElementById('add_sub_'+field); if(element) element.value=''; });
        syncSubtopicFields('add');
        openModal('addSubtopicModal');
    }

    // ─── Edit Subtopic Bindings ──────────────────────────────────
    document.querySelectorAll('.edit-subtopic-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id    = btn.dataset.id;
            const title = btn.dataset.title;
            const video = btn.dataset.video;
            const videoFile = btn.dataset.videoFile;
            const doc   = btn.dataset.doc;
            const sort  = btn.dataset.sort;
            const type = btn.dataset.contentType || 'subtopic';

            document.getElementById('edit_sub_title').value  = title;
            document.getElementById('edit_sub_video').value  = video || '';
            const videoInfo = document.getElementById('current_sub_video_info');
            videoInfo.style.display = videoFile ? 'block' : 'none';
            document.getElementById('current_sub_video_name').textContent = videoFile ? `Current video: ${videoFile}` : '';
            document.getElementById('edit_sub_sort').value   = sort || '';
            document.getElementById('edit_sub_type').value = type;
            document.getElementById('edit_sub_instructions').value = btn.dataset.instructions || '';
            document.getElementById('edit_sub_max_attempts').value = btn.dataset.maximumAttempts || '';
            document.getElementById('edit_sub_zoom_title').value = title || '';
            document.getElementById('edit_sub_zoom_url').value = btn.dataset.zoomUrl || '';
            document.getElementById('edit_sub_zoom_description').value = btn.dataset.zoomDescription || '';
            document.getElementById('edit_sub_zoom_starts_at').value = btn.dataset.zoomStartsAt || '';
            document.getElementById('edit_sub_zoom_ends_at').value = btn.dataset.zoomEndsAt || '';
            syncSubtopicFields('edit');
            document.getElementById('editSubtopicForm').action = `/admin/content/courses/{{ $course->id }}/subtopics/${id}`;

            const docInfo = document.getElementById('current_sub_doc_info');
            if (doc) {
                docInfo.style.display = 'flex';
                document.getElementById('current_sub_doc_name').textContent = doc;
            } else {
                docInfo.style.display = 'none';
            }
            openModal('editSubtopicModal');
        });
    });

    function syncSubtopicFields(prefix) {
        const type = document.getElementById(prefix + '_sub_type').value;
        const isLesson = type === 'subtopic';
        const isZoom = type === 'zoom_link';
        const lessonFields = document.getElementById(prefix + '_sub_lesson_fields');
        const assessmentFields = document.getElementById(prefix + '_sub_assessment_fields');
        const attemptsField = document.getElementById(prefix + '_sub_attempts_field');
        const attemptsLabel = document.getElementById(prefix + '_sub_attempts_label');
        const attemptsSelect = document.getElementById(prefix + '_sub_max_attempts');
        const title = document.getElementById(prefix + '_sub_title');
        const instructions = document.getElementById(prefix + '_sub_instructions');
        const zoomFields = document.getElementById(prefix + '_sub_zoom_fields');
        lessonFields.style.display = isLesson ? '' : 'none';
        zoomFields.style.display = isZoom ? '' : 'none';
        assessmentFields.style.display = (!isLesson && !isZoom) ? '' : 'none';
        const allowsAttemptSelection = ['post_test', 'practice_test', 'mock_exam'].includes(type);
        attemptsField.style.display = allowsAttemptSelection ? 'block' : 'none';
        attemptsSelect.disabled = !allowsAttemptSelection;
        if (attemptsLabel) attemptsLabel.textContent = type === 'post_test' ? 'Maximum allowed Post-test tries' : 'Maximum allowed tries';
        title.required = isLesson || isZoom;
        instructions.required = !isLesson && !isZoom;
        ['zoom_url','zoom_description','zoom_starts_at'].forEach(field => {
            const element = document.getElementById(prefix + '_sub_' + field);
            if (element) { element.required = isZoom; element.disabled = !isZoom; }
        });
        const zoomEnds = document.getElementById(prefix + '_sub_zoom_ends_at');
        if (zoomEnds) zoomEnds.disabled = !isZoom;
        const zoomTitle = document.getElementById(prefix + '_sub_zoom_title');
        if (zoomTitle) {
            zoomTitle.required = isZoom;
            zoomTitle.disabled = !isZoom;
            if (isZoom) title.value = zoomTitle.value;
        }
    }

    ['add', 'edit'].forEach(prefix => {
        document.getElementById(prefix + '_sub_type')?.addEventListener('change', () => syncSubtopicFields(prefix));
        document.getElementById(prefix + '_sub_zoom_title')?.addEventListener('input', event => {
            document.getElementById(prefix + '_sub_title').value = event.target.value;
        });
    });

    function bindSubtopicUploadProgress(formId, progressId) {
        const form = document.getElementById(formId);
        const progress = document.getElementById(progressId);
        form.addEventListener('submit', event => {
            const videoInput = form.querySelector('input[name="video_file"]');
            if (!videoInput?.files?.length) return;
            event.preventDefault();
            const xhr = new XMLHttpRequest();
            const bar = progress.querySelector('.upload-progress-bar');
            const percent = progress.querySelector('strong');
            const submit = form.querySelector('button[type="submit"]');
            progress.style.display = 'block'; submit.disabled = true;
            xhr.upload.addEventListener('progress', upload => {
                if (!upload.lengthComputable) return;
                const value = Math.round((upload.loaded / upload.total) * 100);
                bar.style.width = value + '%'; percent.textContent = value + '%';
            });
            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 400) window.location.reload();
                else { submit.disabled = false; alert(xhr.status === 413 ? 'The video exceeds the server upload limit.' : 'Upload failed. Please check the video format and size.'); }
            });
            xhr.addEventListener('error', () => { submit.disabled = false; alert('Upload failed because the connection was interrupted.'); });
            xhr.open('POST', form.action); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); xhr.send(new FormData(form));
        });
    }
    bindSubtopicUploadProgress('addSubtopicForm', 'add_sub_upload_progress');
    bindSubtopicUploadProgress('editSubtopicForm', 'edit_sub_upload_progress');

    // ─── Confirm Delete Helper ───────────────────────────────────
    function confirmDelete(e, msg) {
        if (!confirm(msg || 'Are you sure?')) {
            e.preventDefault();
            return false;
        }
        return true;
    }

    // ─── SortableJS for Topics ───────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '{{ csrf_token() }}';
        const topicsList = document.getElementById('topicsList');
        if (topicsList) {
            new Sortable(topicsList, {
                handle: '.topic-drag-handle',
                animation: 150,
                onEnd: function (evt) {
                    const orderedIds = Array.from(topicsList.children).map(card => card.dataset.id);
                    Array.from(topicsList.children).forEach((card, index) => {
                        const orderCell = card.querySelector('.sort-order-cell');
                        if (orderCell) orderCell.textContent = index + 1;
                    });
                    fetch('{{ route("admin.content.topics.reorder", $course->id) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ subject_id: {{ $managedSubject->id }}, ordered_ids: orderedIds })
                    });
                }
            });
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
@endsection
