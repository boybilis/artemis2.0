<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\TestBank;
use App\Models\TestBankQuestion;
use App\Models\TestBankQuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestBankAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_premade_information_without_replacing_questions(): void
    {
        extract($this->catalog());
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Simulation Test 2', 'quiz_type' => 'premade', 'test_type' => 'simulation',
            'item_count' => 150, 'subject_ids' => [$subject->id], 'time_limit_minutes' => 300,
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $url = route('admin.content.test-banks.quizzes.update', [$course, $bank, $quiz]);
        $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]))
            ->assertOk()->assertSee('Edit Premade Quiz')->assertSee('Save Changes');
        $this->put($url, ['title' => 'Updated Simulation', 'description' => 'Updated description', 'time_limit_minutes' => 60, 'status' => 'inactive'])->assertRedirect();
        $quiz->refresh();
        $this->assertSame('Updated Simulation', $quiz->title);
        $this->assertSame('Updated description', $quiz->description);
        $this->assertSame('inactive', $quiz->status);
        $this->assertEquals(60, $quiz->time_limit_minutes);
        $this->assertEquals(150, $quiz->item_count);
        $this->assertSame('simulation', $quiz->test_type);
        $this->put($url, ['title' => '', 'status' => 'invalid'])->assertSessionHasErrors(['title', 'status']);
        $otherBank = TestBank::create(['course_id' => $course->id, 'title' => 'Other', 'code' => 'OTHER', 'price' => 100, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id]);
        $this->put(route('admin.content.test-banks.quizzes.update', [$course, $otherBank, $quiz]), ['title' => 'Wrong bank', 'status' => 'active'])->assertNotFound();
    }

    public function test_simulation_custom_size_and_licensure_are_enforced_on_listing_and_start(): void
    {
        extract($this->catalog());
        $bank->enrollment_options = [['id'=>'dha','name'=>'DHA','country'=>'UAE','active'=>true],['id'=>'oman','name'=>'OMSB','country'=>'Oman','active'=>true]];
        $bank->simulation_unlocked_by_admin = true;
        $bank->save();
        foreach(range(1,9) as $i) {
            $q=TestBankQuestion::create(['course_id'=>$course->id,'test_bank_id'=>$bank->id,'subject_id'=>$subject->id,'question'=>'Licensure Q'.$i,'options'=>['A','B'],'correct_answer'=>0,'status'=>'active']);
            $q->subjects()->attach($subject->id);
        }
        $payload=['title'=>'Custom Licensure Simulation','test_type'=>'simulation','item_count'=>7,'time_limit_minutes'=>95,'subject_ids'=>[$subject->id],'licensure_ids'=>['dha']];
        $this->actingAs($admin)->post(route('admin.content.test-banks.quizzes.store',[$course,$bank]),$payload)->assertSessionHasNoErrors();
        $quiz=$bank->premadeQuizzes()->firstOrFail();
        $this->assertEquals(7,$quiz->questions()->count());
        $this->assertEquals(95,$quiz->time_limit_minutes);
        $this->assertSame(['dha'],$quiz->licensure_ids);
        $learner=User::factory()->create();
        $enrollment=$bank->enrollments()->create(['user_id'=>$learner->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addDays(30),'enrollment_option'=>['id'=>'oman']]);
        $this->actingAs($learner)->getJson('/api/test-banks/'.$bank->id.'/workspace')->assertJsonCount(0,'workspace.premadeTests');
        $this->getJson('/api/test-banks/'.$bank->id.'/quizzes/'.$quiz->id.'/questions')->assertForbidden();
        $enrollment->update(['enrollment_option'=>['id'=>'dha']]);
        $this->getJson('/api/test-banks/'.$bank->id.'/workspace')->assertJsonCount(1,'workspace.premadeTests');
        $this->getJson('/api/test-banks/'.$bank->id.'/quizzes/'.$quiz->id.'/questions')->assertOk()->assertJsonCount(7,'questions');
        $this->actingAs($admin)->put(route('admin.content.test-banks.quizzes.update',[$course,$bank,$quiz]),['title'=>$quiz->title,'status'=>'active','item_count'=>9,'time_limit_minutes'=>110,'audience_configured'=>1])->assertSessionHasNoErrors();
        $this->assertEquals(9,$quiz->fresh()->questions()->count());
        $this->assertSame([],$quiz->fresh()->licensure_ids);
        $this->post(route('admin.content.test-banks.quizzes.store',[$course,$bank]),array_replace($payload,['licensure_ids'=>['foreign']]))->assertSessionHasErrors('licensure_ids.0');
    }

    public function test_premade_table_pagination_and_search_return_modals_for_each_page(): void
    {
        extract($this->catalog());
        foreach(range(1,12) as $i) $bank->premadeQuizzes()->create(['title'=>'Premade '.$i,'quiz_type'=>'premade','item_count'=>10,'subject_ids'=>[$subject->id],'status'=>'active']);
        $url=route('admin.content.test-banks.manage',[$course,$bank]);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('tb-premade-table')->assertSee('12 matching quizzes')->assertSee('Page 1 of 2');
        $this->get($url.'?quiz_page=2')->assertOk()->assertSee('Page 2 of 2')->assertSee('Edit Premade Quiz');
        $this->get($url.'?quiz_search=Premade%2012')->assertOk()->assertSee('1 matching quizzes')->assertSee('Page 1 of 1');
        $json=$this->getJson($url.'?table_data=quizzes&quiz_search=not-a-match')->assertOk()->assertJsonCount(12,'rows');
        $this->assertStringContainsString('Edit Premade Quiz',$json->json('rows.0.editHtml'));
    }

    public function test_question_table_ajax_payload_and_edit_archive_responses(): void
    {
        extract($this->catalog());
        $data=['subject_ids'=>[$subject->id],'question'=>'AJAX question','options'=>['First','Second'],'correct_answer'=>0,'points'=>1];
        $this->actingAs($admin)->postJson(route('admin.content.test-banks.questions.store',[$course,$bank]),$data)->assertOk()->assertJsonPath('success',true);
        $question=TestBankQuestion::firstOrFail();
        $this->get(route('admin.content.test-banks.manage',[$course,$bank]))->assertOk()->assertSee('data-ajax-table="bank-questions"',false)->assertSee('data-question-payload',false);
        $this->putJson(route('admin.content.test-banks.questions.update',[$course,$bank,$question]),array_replace($data,['question'=>'AJAX updated']))->assertOk()->assertJsonPath('success',true);
        $this->postJson(route('admin.content.test-banks.questions.status',[$course,$bank,$question]))->assertOk()->assertJsonPath('success',true);
        $this->assertSame('inactive',$question->fresh()->status);
    }

    public function test_question_master_json_is_lightweight_and_details_are_course_scoped(): void
    {
        extract($this->catalog());
        $q=TestBankQuestion::create(['course_id'=>$course->id,'test_bank_id'=>$bank->id,'subject_id'=>$subject->id,'question'=>'Master question','options'=>['First','Second'],'correct_answer'=>0,'rationale'=>'Detailed explanation','status'=>'active']);
        $q->subjects()->attach($subject->id);
        $url=route('admin.content.test-banks.manage',[$course,$bank]);
        $response=$this->actingAs($admin)->getJson($url.'?table_data=questions&search=not-a-match')->assertOk()->assertJsonCount(1,'rows');
        $this->assertArrayNotHasKey('rationale',$response->json('rows.0'));
        $this->assertArrayNotHasKey('options',$response->json('rows.0'));
        $this->getJson($url.'?table_data=question&id='.$q->id)->assertOk()->assertJsonPath('question.rationale','Detailed explanation');
        $otherCourse=Course::create(['title'=>'Other']);
        $otherBank=TestBank::create(['course_id'=>$otherCourse->id,'title'=>'Other bank','code'=>'OTHER-JSON','price'=>100,'access_days'=>30,'status'=>'active']);
        $this->getJson(route('admin.content.test-banks.manage',[$otherCourse,$otherBank]).'?table_data=question&id='.$q->id)->assertNotFound();
    }

    private function catalog(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH-HAAD']);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'MEDSURG', 'title' => 'Medical Surgical Nursing', 'status' => 'approved']);
        $secondSubject = Subject::create(['course_id' => $course->id, 'subject_code' => 'PHARMA', 'title' => 'Pharmacology', 'status' => 'approved']);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'DOH Test Bank', 'code' => 'DOH-TB', 'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id]);
        return compact('admin', 'course', 'subject', 'secondSubject', 'bank');
    }

    public function test_delete_all_requires_admin_password_and_clears_shared_pool_and_related_records(): void
    {
        extract($this->catalog());
        $admin->update(['password'=>bcrypt('Admin-secret-123')]);
        $otherBank = TestBank::create(['course_id'=>$course->id,'title'=>'Shared bank','code'=>'SHARED','price'=>100,'access_days'=>30,'status'=>'active']);
        $question = TestBankQuestion::create(['test_bank_id'=>$bank->id,'course_id'=>$course->id,'subject_id'=>$subject->id,'question'=>'Shared question','options'=>['A','B'],'correct_answer'=>0,'status'=>'active']);
        $question->subjects()->attach($subject);
        $quiz = $otherBank->premadeQuizzes()->create(['title'=>'Custom exam','quiz_type'=>'learner','item_count'=>1,'subject_ids'=>[$subject->id],'status'=>'active']);
        $quiz->questions()->attach($question);
        $attempt = TestBankQuizAttempt::create(['user_id'=>$admin->id,'test_bank_id'=>$otherBank->id,'test_bank_quiz_id'=>$quiz->id,'score'=>1,'total'=>1,'passed'=>true,'review_data'=>[['questionId'=>$question->id,'question'=>'Shared question']]]);
        $flag = \App\Models\QuestionFlag::create(['user_id'=>$admin->id,'attempt_type'=>'test_bank','attempt_id'=>$attempt->id,'question_index'=>0,'question_snapshot'=>['questionId'=>$question->id],'reason'=>'Explain this']);
        $url = route('admin.content.test-banks.questions.delete-all',[$course,$bank]);
        $this->actingAs($admin)->delete($url,['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('test_bank_questions',['id'=>$question->id]);
        $encoder = User::factory()->create(['role'=>'encoder','is_admin'=>false]);
        $this->actingAs($encoder)->delete($url,['password'=>'Admin-secret-123'])->assertNotFound();
        $this->actingAs($admin)->delete($url,['password'=>'Admin-secret-123'])->assertRedirect();
        $this->assertDatabaseMissing('test_bank_questions',['id'=>$question->id]);
        $this->assertDatabaseMissing('test_bank_quizzes',['id'=>$quiz->id]);
        $this->assertDatabaseMissing('test_bank_quiz_attempts',['id'=>$attempt->id]);
        $this->assertDatabaseMissing('question_flags',['id'=>$flag->id]);
        $this->assertDatabaseCount('test_bank_question_subject',0);
        $this->assertDatabaseCount('test_bank_quiz_questions',0);
        $this->assertDatabaseHas('test_banks',['id'=>$bank->id]);
        $this->assertDatabaseHas('test_banks',['id'=>$otherBank->id]);
        $this->assertDatabaseHas('subjects',['id'=>$subject->id]);
    }

    public function test_admin_can_open_catalog_and_add_a_multiple_choice_question(): void
    {
        extract($this->catalog());
        Storage::fake('public');
        $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]))
            ->assertOk()->assertSee('Premade Quizzes')->assertSee('Preview')->assertSee('Archive')
            ->assertSee('quizSelectAllSubjects')->assertSee('Select All');
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.store', [$course, $bank]), [
            'subject_ids' => [$subject->id, $secondSubject->id], 'question' => 'Which action is appropriate?',
            'options' => ['Assess first', 'Call immediately', 'Document only', ''],
            'correct_answer' => 0, 'points' => 2.5, 'rationale' => 'Assessment comes first.',
            'rationale_video_url' => 'https://www.youtube.com/watch?v=example123',
            'question_image' => UploadedFile::fake()->createWithContent(
                'reference.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
            'rationale_image' => UploadedFile::fake()->createWithContent(
                'rationale.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'correct_answer' => 0, 'points' => 2.5]);
        $question = TestBankQuestion::firstOrFail();
        $this->assertEqualsCanonicalizing([$subject->id, $secondSubject->id], $question->subjects()->pluck('subjects.id')->all());

        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk();
        $subjectCounts = collect($workspace->json('workspace.subjects'))->pluck('questionCount', 'id');
        $this->assertSame(1, $subjectCounts->get($subject->id));
        $this->assertSame(1, $subjectCounts->get($secondSubject->id));
        $this->assertSame('reference.png', $question->image_filename);
        Storage::disk('public')->assertExists($question->image_path);
        Storage::disk('public')->assertExists($question->rationale_image_path);
        $this->assertSame('rationale.png', $question->rationale_image_filename);
        $this->assertSame('https://www.youtube.com/watch?v=example123', $question->rationale_video_url);

        $this->actingAs($admin)->put(route('admin.content.test-banks.questions.update', [$course, $bank, $question]), [
            'subject_ids' => [$secondSubject->id], 'question' => 'Which medication action is appropriate?',
            'options' => ['Verify the order', 'Administer immediately', '', ''], 'correct_answer' => 0,
            'points' => 3, 'rationale' => 'Verify the order first.',
            'rationale_video_url' => 'https://vimeo.com/123456789',
        ])->assertSessionHas('success');
        $question->refresh();
        $this->assertSame('Which medication action is appropriate?', $question->question);
        $this->assertEqualsCanonicalizing([$secondSubject->id], $question->subjects()->pluck('subjects.id')->all());
        $this->assertSame('https://vimeo.com/123456789', $question->rationale_video_url);
        Storage::disk('public')->assertExists($question->rationale_image_path);

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $bank, $question]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['id' => $question->id, 'status' => 'inactive']);
        Storage::disk('public')->assertExists($question->image_path);

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $this->assertFalse(collect($workspace->json('workspace.subjects'))->pluck('questionCount', 'id')->has($secondSubject->id));

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $bank, $question]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['id' => $question->id, 'status' => 'active']);
    }

    public function test_csv_import_matches_image_filenames_and_rejects_missing_or_duplicate_images(): void
    {
        extract($this->catalog());
        Storage::fake('public');
        $url = route('admin.content.test-banks.questions.import', [$course, $bank]);
        $csv = "subject_codes,question,option_a,option_b,correct_answer,reference_image,rationale_image,rationale_video_url\nMEDSURG,Image question,A,B,A,reference.png,rationale.png,https://vimeo.com/123456789\n";
        $upload = fn () => UploadedFile::fake()->createWithContent('questions.csv', $csv);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $image = fn ($name) => UploadedFile::fake()->createWithContent($name, $png);
        $this->actingAs($admin)->post($url, ['csv_file' => $upload()])->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('test_bank_questions', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->post($url, ['csv_file' => $upload(), 'images' => [$image('reference.png'), $image('reference.png')]])->assertSessionHasErrors('images');
        $this->assertDatabaseCount('test_bank_questions', 0);
        $this->post($url, [
            'csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', str_replace('https://vimeo.com/123456789', 'javascript:alert(1)', $csv)),
            'images' => [$image('reference.png'), $image('rationale.png')],
        ])->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('test_bank_questions', 0);
        $this->post($url, ['csv_file' => $upload(), 'images' => [$image('reference.png'), $image('rationale.png')]])->assertSessionHas('success');
        $question = TestBankQuestion::firstOrFail();
        $this->assertSame('reference.png', $question->image_filename);
        $this->assertSame('rationale.png', $question->rationale_image_filename);
        $this->assertSame('https://vimeo.com/123456789', $question->rationale_video_url);
        Storage::disk('public')->assertExists($question->image_path);
        Storage::disk('public')->assertExists($question->rationale_image_path);
    }

    public function test_admin_can_preview_simulation_without_unlocking_or_recording_attempts(): void
    {
        extract($this->catalog());
        $course->update(['title' => 'PROMETRIC RN']);
        $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]))->assertOk()->assertSee('Preview Simulation Test');
        $url = route('admin.content.test-banks.simulation-preview', [$course, $bank]);
        $this->get($url)->assertOk()->assertSee('PROMETRIC RN Simulation Test')->assertViewHas('prometric', true);
        $this->assertDatabaseCount('test_bank_quiz_attempts', 0);
        $this->assertDatabaseCount('test_bank_enrollments', 0);
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $this->actingAs($learner)->get($url)->assertNotFound();
    }

    public function test_admin_premade_type_routes_to_workspace_and_simulation_requires_full_pool(): void
    {
        extract($this->catalog());
        for ($i = 0; $i < 150; $i++) {
            $question = TestBankQuestion::create(['course_id' => $course->id, 'test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'question' => 'Type question '.$i, 'options' => ['A', 'B'], 'correct_answer' => 0, 'points' => 1, 'status' => 'active']);
            $question->subjects()->attach($subject->id);
        }
        $url = route('admin.content.test-banks.quizzes.store', [$course, $bank]);
        foreach (['warm_up', 'mastery', 'simulation'] as $type) {
            $this->actingAs($admin)->post($url, ['title' => 'Typed '.$type, 'test_type' => $type, 'item_count' => 10, 'subject_ids' => [$subject->id]])->assertSessionHas('success');
        }
        $simulation = $bank->premadeQuizzes()->where('test_type', 'simulation')->firstOrFail();
        $this->assertSame(10, $simulation->item_count);
        $this->assertSame(300, $simulation->time_limit_minutes);
        $learner = User::factory()->create();
        $bank->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(30)]);
        $tests = $this->actingAs($learner)->getJson('/api/test-banks/'.$bank->id.'/workspace')->assertOk()->json('workspace.premadeTests');
        $this->assertEqualsCanonicalizing(['warm_up', 'mastery', 'simulation'], array_column($tests, 'testType'));
        $this->getJson('/api/test-banks/'.$bank->id.'/quizzes/'.$simulation->id.'/questions')->assertForbidden();
        $question->update(['status' => 'inactive']);
        $this->actingAs($admin)->post($url, ['title' => 'Not enough items', 'test_type' => 'simulation', 'item_count' => 150, 'subject_ids' => [$subject->id]])->assertSessionHasErrors('subject_ids');
    }

    public function test_admin_can_import_course_subject_questions_from_csv(): void
    {
        extract($this->catalog());
        $csv = "subject_codes,question,option_a,option_b,option_c,option_d,correct_answer,points,rationale\nMEDSURG|PHARMA,What comes first?,Assessment,Intervention,Evaluation,Documentation,A,3,Assess first";
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.import', [$course, $bank]), [
            'csv_file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'points' => 3]);
        $question = TestBankQuestion::firstOrFail();
        $this->assertEqualsCanonicalizing([$subject->id, $secondSubject->id], $question->subjects()->pluck('subjects.id')->all());
    }

    public function test_legacy_double_encoded_options_are_normalized_for_editing(): void
    {
        extract($this->catalog());
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Which intervention is appropriate?', 'options' => ['Short A', 'Short B'],
            'correct_answer' => 1, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $paragraph = 'This answer contains a full paragraph with the complete clinical intervention and supporting context.';
        DB::table('test_bank_questions')->where('id', $question->id)->update([
            'options' => json_encode(json_encode(['First answer', $paragraph])),
        ]);

        $this->assertSame(['First answer', $paragraph], $question->fresh()->options);
        $response = $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]));
        $response->assertOk()->assertSee($paragraph)->assertSee('textarea id="editOption0"', false);
    }

    public function test_admin_question_table_loads_twenty_rows_and_searches_the_entire_bank(): void
    {
        extract($this->catalog());
        foreach (range(1, 21) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => $number === 1 ? 'Oldest uniquely searchable question' : "Recent question {$number}",
                'options' => ['A', 'B'], 'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
        }

        $firstPage = $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]));
        $firstPage->assertOk()->assertSee('Showing 1–20 of 21')->assertDontSee('Oldest uniquely searchable question');

        $searchUrl = route('admin.content.test-banks.manage', [$course, $bank])
            .'?search='.urlencode('Oldest uniquely searchable');
        $search = $this->actingAs($admin)->get($searchUrl);
        $search->assertOk()->assertSee('Oldest uniquely searchable question')->assertSee('1 matching questions');
    }

    public function test_quiz_builder_uses_all_available_questions_when_requested_count_is_higher(): void
    {
        extract($this->catalog());
        foreach (range(1, 3) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Question {$number}", 'options' => ['A', 'B'], 'correct_answer' => 0,
                'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id, $secondSubject->id]);
        }
        $this->actingAs($admin)->post(route('admin.content.test-banks.quizzes.store', [$course, $bank]), [
            'title' => 'Medical Surgical Drill', 'item_count' => 10, 'time_limit_minutes' => 45,
            'subject_ids' => [$subject->id],
        ])->assertSessionHas('success');
        $quiz = $bank->premadeQuizzes()->firstOrFail();
        $this->assertSame(3, $quiz->item_count);
        $this->assertSame(3, $quiz->questions()->count());
        $this->assertTrue($quiz->randomize_questions);
        $this->assertSame(45, $quiz->time_limit_minutes);
    }

    public function test_deleting_an_admin_premade_quiz_removes_all_learner_results(): void
    {
        extract($this->catalog());
        $learner = User::factory()->create();
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Quiz to Delete', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $attempt = TestBankQuizAttempt::create([
            'user_id' => $learner->id, 'test_bank_id' => $bank->id,
            'test_bank_quiz_id' => $quiz->id, 'score' => 1, 'total' => 1,
            'points_earned' => 1, 'points_possible' => 1, 'passed' => true,
            'review_data' => [['question' => 'Saved review']],
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.content.test-banks.quizzes.destroy', [$course, $bank, $quiz]))
            ->assertSessionHas('success', 'Premade quiz and all learner test results deleted.');

        $this->assertDatabaseMissing('test_bank_quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('test_bank_quiz_attempts', ['id' => $attempt->id]);
    }

    public function test_catalogs_for_the_same_course_share_the_course_question_bank(): void
    {
        extract($this->catalog());
        $secondBank = TestBank::create([
            'course_id' => $course->id, 'title' => 'DOH Practice Bank', 'code' => 'DOH-TB-2',
            'price' => 1200, 'access_days' => 60, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $otherCourse = Course::create(['title' => 'Prometrics']);
        $otherBank = TestBank::create([
            'course_id' => $otherCourse->id, 'title' => 'Prometrics Bank', 'code' => 'PROM-TB',
            'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Shared DOH course question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);

        $this->actingAs($admin)
            ->get(route('admin.content.test-banks.manage', [$course, $secondBank]))
            ->assertOk()
            ->assertSee('Shared DOH course question');
        $this->actingAs($admin)
            ->get(route('admin.content.test-banks.manage', [$otherCourse, $otherBank]))
            ->assertOk()
            ->assertDontSee('Shared DOH course question');

        $this->actingAs($admin)->post(route('admin.content.test-banks.quizzes.store', [$course, $secondBank]), [
            'title' => 'Shared Question Quiz', 'item_count' => 1, 'subject_ids' => [$subject->id],
        ])->assertSessionHas('success');
        $quiz = $secondBank->premadeQuizzes()->firstOrFail();
        $this->assertTrue($quiz->questions()->whereKey($question->id)->exists());

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $secondBank, $question]))
            ->assertSessionHas('success');
        $this->assertSame('inactive', $question->fresh()->status);
    }

    public function test_enrolled_learner_can_take_a_premade_test_and_review_the_result(): void
    {
        extract($this->catalog());
        Storage::fake('public');
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Which answer is correct?', 'options' => ['Correct answer', 'Wrong answer'],
            'correct_answer' => 0, 'points' => 2, 'rationale' => 'This explains the correct answer.',
            'rationale_video_url' => 'https://www.youtube.com/watch?v=example123',
            'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Pre Test 1', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $quiz->questions()->sync([$question->id]);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $questions = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions");
        $questions->assertOk()
            ->assertJsonPath('title', 'Pre Test 1')
            ->assertJsonPath('questions.0.question', 'Which answer is correct?')
            ->assertJsonMissing(['correct_answer' => 0]);

        $this->actingAs($learner)->deleteJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/attempt")
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('test_bank_quiz_attempts', [
            'user_id' => $learner->id, 'test_bank_quiz_id' => $quiz->id, 'passed' => false,
        ]);
        $cancelledAttempt = TestBankQuizAttempt::latest('id')->firstOrFail();
        $this->assertTrue((bool) data_get($cancelledAttempt->review_data, 'cancelled'));
        $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", ['answers' => [0]])
            ->assertUnprocessable();
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")->assertOk();

        $result = $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", [
            'answers' => [0],
        ]);
        $result->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('score', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('questions.0.correct', true)
            ->assertJsonPath('questions.0.rationale', 'This explains the correct answer.')
            ->assertJsonPath('questions.0.rationaleVideoUrl', 'https://www.youtube.com/watch?v=example123');
        $this->assertDatabaseHas('test_bank_quiz_attempts', [
            'user_id' => $learner->id, 'test_bank_id' => $bank->id,
            'test_bank_quiz_id' => $quiz->id, 'score' => 1, 'passed' => true,
        ]);
        $this->assertCount(1, TestBankQuizAttempt::firstOrFail()->review_data);

        $history = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $history->assertOk()
            ->assertJsonCount(2, 'workspace.history')
            ->assertJsonPath('workspace.history.0.title', 'Pre Test 1')
            ->assertJsonPath('workspace.history.0.passed', true)
            ->assertJsonPath('workspace.history.0.correctItems', 1)
            ->assertJsonPath('workspace.history.0.questions.0.rationale', 'This explains the correct answer.')
            ->assertJsonPath('workspace.history.1.cancelled', true)
            ->assertJsonCount(0, 'workspace.history.1.questions');
    }

    public function test_learner_without_active_test_bank_access_cannot_start_a_premade_test(): void
    {
        extract($this->catalog());
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Protected Test', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
            ->assertForbidden();
    }

    public function test_workspace_hides_subjects_without_active_questions(): void
    {
        extract($this->catalog());
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Visible subject question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk()
            ->assertJsonCount(1, 'workspace.subjects')
            ->assertJsonPath('workspace.subjects.0.id', $subject->id)
            ->assertJsonMissing(['id' => $secondSubject->id]);
    }

    public function test_test_bank_quizzes_pass_at_sixty_percent(): void
    {
        extract($this->catalog());
        $questionIds = collect(range(1, 5))->map(function ($number) use ($bank, $course, $subject, $admin) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Passing question {$number}", 'options' => ['Correct', 'Wrong'],
                'correct_answer' => 0, 'points' => $number > 3 ? 20 : 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
            return $question->id;
        });
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Passing Score Test', 'item_count' => 5, 'subject_ids' => [$subject->id],
            'randomize_questions' => false, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $quiz->questions()->sync($questionIds);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")->assertOk();
        $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", [
            'answers' => [0, 0, 0, 1, 1],
        ])->assertOk()->assertJsonPath('score', 3)->assertJsonPath('total', 5)->assertJsonPath('passed', true);
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")->assertOk();
        $this->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", [
            'answers'=>[1,1,1,0,0],
        ])->assertOk()->assertJsonPath('score',2)->assertJsonPath('total',5)->assertJsonPath('passed',false);
    }

    public function test_legacy_weighted_results_use_raw_percentages_for_history_and_subject_progress(): void
    {
        extract($this->catalog());
        $learner=User::factory()->create();
        $bank->enrollments()->create(['user_id'=>$learner->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addDays(30)]);
        $question=TestBankQuestion::create(['test_bank_id'=>$bank->id,'course_id'=>$course->id,'subject_id'=>$subject->id,'question'=>'Visible subject','options'=>['A','B'],'correct_answer'=>0,'points'=>9,'status'=>'active']);
        $question->subjects()->sync([$subject->id]);
        $quiz=$bank->premadeQuizzes()->create(['title'=>'Legacy Warm-up','quiz_type'=>'subject','test_type'=>'warm_up','description'=>'V2 one-time subject test.','owner_user_id'=>$learner->id,'item_count'=>5,'subject_ids'=>[$subject->id],'status'=>'active']);
        $attempt=TestBankQuizAttempt::create(['user_id'=>$learner->id,'test_bank_id'=>$bank->id,'test_bank_quiz_id'=>$quiz->id,'score'=>3,'total'=>5,'points_earned'=>3,'points_possible'=>43,'passed'=>false]);
        $this->assertTrue($attempt->fresh()->passed);
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace")->assertOk()
            ->assertJsonPath('workspace.history.0.score',3)->assertJsonPath('workspace.history.0.total',5)
            ->assertJsonPath('workspace.history.0.passed',true)->assertJsonPath('workspace.subjects.0.averageScore',60)
            ->assertJsonPath('workspace.simulationProgress.warmUpPassed',1)
            ->assertJsonPath('workspace.v2SubjectResults.0.score',60);
    }

    public function test_learner_can_start_a_random_subject_only_warm_up_test(): void
    {
        extract($this->catalog());
        foreach (range(1, 12) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Subject question {$number}", 'options' => ['A', 'B'],
                'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
        }
        $otherQuestion = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $secondSubject->id,
            'question' => 'Other subject question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $otherQuestion->subjects()->sync([$secondSubject->id]);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/subjects/{$subject->id}/quizzes", [
            'quiz_type' => 'warm_up',
        ]);
        $response->assertOk();
        $quiz = $bank->premadeQuizzes()->where('quiz_type', 'subject')->firstOrFail();
        $this->assertSame(10, $quiz->questions()->count());
        $this->assertSame([$subject->id], $quiz->subject_ids);
        $this->assertFalse($quiz->questions()->whereKey($otherQuestion->id)->exists());
        $endpoint = "/api/test-banks/{$bank->id}/subjects/{$subject->id}/quizzes";
        $warmId = $this->postJson($endpoint, ['quiz_type' => 'warm_up', 'reuse_set' => true])->assertOk()->json('quizId');
        $this->postJson($endpoint, ['quiz_type' => 'warm_up', 'reuse_set' => true])->assertOk()->assertJsonPath('quizId', $warmId);
        $masteryId = $this->postJson($endpoint, ['quiz_type' => 'mastery', 'reuse_set' => true])->assertOk()->json('quizId');
        $this->assertNotSame($quiz->id, $masteryId);
        $this->postJson($endpoint, ['quiz_type' => 'mastery', 'reuse_set' => true])->assertOk()->assertJsonPath('quizId', $masteryId);
        $this->assertSame(3, $bank->premadeQuizzes()->where('quiz_type', 'subject')->count());
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/questions")->assertOk();
        $this->deleteJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/attempt")->assertOk();
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/questions")->assertOk();
        $this->postJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/submit", ['answers' => array_fill(0, 10, 0)])->assertOk();
        $results=$this->getJson("/api/test-banks/{$bank->id}/workspace")->assertOk()->json('workspace.v2SubjectResults');
        $this->assertSame(1,$results[0]['attemptCount']);
        foreach ([2,3] as $number) {
            $this->postJson($endpoint,['quiz_type'=>'warm_up','reuse_set'=>true])->assertOk()->assertJsonPath('quizId',$warmId);
            $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/questions")->assertOk();
            $this->postJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/submit",['answers'=>array_fill(0,10,0)])->assertOk();
        }
        $this->postJson($endpoint, ['quiz_type' => 'warm_up', 'reuse_set' => true])->assertStatus(422);
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/questions")->assertStatus(422);
        $this->postJson("/api/test-banks/{$bank->id}/quizzes/{$warmId}/submit", ['answers' => array_fill(0, 10, 0)])->assertStatus(422);
        $this->postJson($endpoint, ['quiz_type' => 'mastery', 'reuse_set' => true])->assertOk()->assertJsonPath('quizId', $masteryId);
        foreach ([1,2,3] as $number) {
            $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$masteryId}/questions")->assertOk();
            $this->postJson("/api/test-banks/{$bank->id}/quizzes/{$masteryId}/submit",['answers'=>array_fill(0,12,0)])->assertOk();
        }
        $this->postJson($endpoint,['quiz_type'=>'mastery','reuse_set'=>true])->assertUnprocessable();
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$masteryId}/questions")->assertUnprocessable();
        $results = $this->getJson("/api/test-banks/{$bank->id}/workspace")->assertOk()->json('workspace.v2SubjectResults');
        $warmResult=collect($results)->firstWhere('phase','warm_up');
        $this->assertEquals(100, $warmResult['score']);
        $this->assertSame(3,$warmResult['attemptCount']);
        $this->assertSame(3,collect($results)->firstWhere('phase','mastery')['attemptCount']);

        $this->actingAs($learner)
            ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
            ->assertOk()->assertJsonCount(10, 'questions');
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace")
            ->assertOk()->assertJsonCount(0, 'workspace.learnerQuizzes');
    }

    public function test_subject_cards_show_completed_attempt_count_and_average_percentage(): void
    {
        extract($this->catalog());
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Progress question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Subject Progress Quiz', 'item_count' => 10, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $learner = User::factory()->create();
        $quiz->update(['quiz_type' => 'subject', 'owner_user_id' => $learner->id]);
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
        foreach ([10, 8, 6, 4, 2] as $score) {
            TestBankQuizAttempt::create([
                'user_id' => $learner->id, 'test_bank_id' => $bank->id,
                'test_bank_quiz_id' => $quiz->id, 'score' => $score, 'total' => 10,
                'points_earned' => $score, 'points_possible' => 10,
                'passed' => $score >= 6, 'review_data' => [],
            ]);
        }
        TestBankQuizAttempt::create([
            'user_id' => $learner->id, 'test_bank_id' => $bank->id,
            'test_bank_quiz_id' => $quiz->id, 'score' => 0, 'total' => 10,
            'points_earned' => 0, 'points_possible' => 0,
            'passed' => false, 'review_data' => ['cancelled'=>true],
        ]);
        $customQuiz = $bank->premadeQuizzes()->create([
            'quiz_type' => 'learner', 'owner_user_id' => $learner->id,
            'title' => 'Custom Warm-up', 'item_count' => 10,
            'subject_ids' => [$subject->id, $secondSubject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $learner->id,
        ]);
        TestBankQuizAttempt::create([
            'user_id' => $learner->id, 'test_bank_id' => $bank->id,
            'test_bank_quiz_id' => $customQuiz->id, 'score' => 10, 'total' => 10,
            'points_earned' => 10, 'points_possible' => 10,
            'passed' => true, 'review_data' => [],
        ]);

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk()
            ->assertJsonPath('workspace.subjects.0.completedTests', 5)
            ->assertJsonPath('workspace.subjects.0.averageScore', 60)
            ->assertJsonPath('workspace.subjects.0.progress', 100)
            ->assertJsonPath('workspace.simulationProgress.warmUpPassed', 2)
            ->assertJsonPath('workspace.simulationProgress.masteryPassed', 0)
            ->assertJsonPath('workspace.simulationProgress.unlocked', false);
    }

    public function test_simulation_type_uses_distinct_passes_or_admin_override_regardless_of_title(): void
    {
        extract($this->catalog());
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Simulation question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $regular = $bank->premadeQuizzes()->create([
            'title' => 'Simulation Test 1', 'test_type' => 'warm_up', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $simulation = $bank->premadeQuizzes()->create([
            'title' => 'Clinical Readiness Exam', 'test_type' => 'simulation', 'item_count' => 25, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $regular->questions()->sync([$question->id]);
        $simulationQuestionIds = collect([$question->id]);
        foreach (range(2, 25) as $number) {
            $extraQuestion = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Simulation question {$number}", 'options' => ['A', 'B'],
                'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $extraQuestion->subjects()->sync([$subject->id]);
            $simulationQuestionIds->push($extraQuestion->id);
        }
        $simulation->questions()->sync($simulationQuestionIds);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $simulationIndex = collect($workspace->json('workspace.premadeTests'))->search(fn ($test) => $test['id'] === $simulation->id);
        $regularIndex = collect($workspace->json('workspace.premadeTests'))->search(fn ($test) => $test['id'] === $regular->id);
        $workspace->assertJsonPath("workspace.premadeTests.{$simulationIndex}.locked", true)
            ->assertJsonPath("workspace.premadeTests.{$regularIndex}.locked", false);
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$regular->id}/questions")->assertOk();
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$simulation->id}/questions")->assertForbidden();

        $overrideUrl = route('admin.settings.simulation-access', $bank);
        $this->actingAs($learner)->put($overrideUrl, ['simulation_unlocked_by_admin' => 1])->assertNotFound();
        $this->actingAs($admin)->put($overrideUrl, ['simulation_unlocked_by_admin' => 1])->assertSessionHas('success');
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace")->assertJsonPath('workspace.simulationProgress.unlocked', true)->assertJsonPath('workspace.simulationProgress.adminUnlocked', true);
        $this->getJson("/api/test-banks/{$bank->id}/quizzes/{$simulation->id}/questions")->assertOk();
        $this->actingAs($admin)->put($overrideUrl, ['simulation_unlocked_by_admin' => 0])->assertSessionHas('success');
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$simulation->id}/questions")->assertForbidden();
        for ($i = 0; $i < 10; $i++) {
            TestBankQuizAttempt::create(['user_id' => $learner->id, 'test_bank_id' => $bank->id, 'test_bank_quiz_id' => $regular->id, 'score' => 1, 'total' => 1, 'points_earned' => 1, 'points_possible' => 1, 'passed' => true, 'review_data' => []]);
        }
        $this->getJson("/api/test-banks/{$bank->id}/workspace")->assertJsonPath('workspace.simulationProgress.warmUpPassed', 1)->assertJsonPath('workspace.simulationProgress.unlocked', false);
        foreach ([10 => 9, 20 => 5] as $itemCount => $attemptCount) {
            foreach (range(1, $attemptCount) as $attempt) {
            $progressQuiz = $bank->premadeQuizzes()->create([
                'quiz_type' => 'premade', 'test_type' => $itemCount === 10 ? 'warm_up' : 'mastery',
                'title' => "Progress {$itemCount}", 'item_count' => $itemCount, 'subject_ids' => [$subject->id],
                'randomize_questions' => true, 'status' => 'active', 'created_by' => $learner->id,
            ]);
                TestBankQuizAttempt::create([
                    'user_id' => $learner->id, 'test_bank_id' => $bank->id,
                    'test_bank_quiz_id' => $progressQuiz->id, 'score' => $itemCount, 'total' => $itemCount,
                    'points_earned' => $itemCount, 'points_possible' => $itemCount,
                    'passed' => true, 'review_data' => [],
                ]);
            }
        }

        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$simulation->id}/questions")
            ->assertOk()->assertJsonPath('lazyLoad', true)->assertJsonPath('totalQuestions', 25)
            ->assertJsonCount(20, 'questions');
        $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$simulation->id}/questions?offset=20")
            ->assertOk()->assertJsonPath('offset', 20)->assertJsonPath('totalQuestions', 25)
            ->assertJsonCount(5, 'questions');
    }

    public function test_learner_can_build_a_private_timed_quiz_and_all_attempts_are_numbered_in_history(): void
    {
        extract($this->catalog());
        foreach (range(1, 3) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Builder question {$number}", 'options' => ['Correct', 'Wrong'],
                'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
        }
        $learner = User::factory()->create();
        $otherLearner = User::factory()->create();
        foreach ([$learner, $otherLearner] as $enrolledLearner) {
            $bank->enrollments()->create([
                'user_id' => $enrolledLearner->id, 'status' => 'active',
                'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
            ]);
        }

        $created = $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes", [
            'subject_ids' => [$subject->id], 'item_count' => 2,
            'timed' => true, 'time_limit_minutes' => 15,
        ]);
        $created->assertOk()->assertJson(['success' => true]);
        $quiz = $bank->premadeQuizzes()->where('quiz_type', 'learner')->firstOrFail();
        $this->assertSame($learner->id, $quiz->owner_user_id);
        $this->assertSame(15, $quiz->time_limit_minutes);
        $this->assertSame(2, $quiz->questions()->count());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $questions = $this->actingAs($learner)
                ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions".($attempt > 1 ? '?shuffle_choices=1' : ''))
                ->assertOk()->assertJsonPath('timeLimitMinutes', 15)->json('questions');
            $answers = array_map(function ($question) {
                $this->assertEqualsCanonicalizing(['Correct', 'Wrong'], $question['options']);
                return array_search('Correct', $question['options'], true);
            }, $questions);
            $this->actingAs($learner)
                ->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", ['answers' => $answers])
                ->assertOk();
            $saved = TestBankQuizAttempt::where('test_bank_quiz_id', $quiz->id)->latest('id')->firstOrFail();
            $this->assertSame(2, $saved->score);
            $this->assertSame('Correct', $saved->review_data[0]['learnerAnswer']);
            $this->assertSame('Correct', $saved->review_data[0]['correctAnswer']);
            $this->assertEqualsCanonicalizing($quiz->questions()->pluck('test_bank_questions.id')->all(), array_column($questions, 'id'));
        }
        foreach ($quiz->questions as $question) {
            $this->assertSame(['Correct', 'Wrong'], $question->options);
            $this->assertSame(0, (int) $question->correct_answer);
        }

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk()
            ->assertJsonCount(1, 'workspace.learnerQuizzes')
            ->assertJsonPath('workspace.learnerQuizzes.0.attemptCount', 3)
            ->assertJsonPath('workspace.learnerQuizzes.0.subjects.0.title', 'Medical Surgical Nursing')
            ->assertJsonCount(3, 'workspace.history')
            ->assertJsonPath('workspace.history.0.attemptNumber', 3)
            ->assertJsonPath('workspace.history.1.attemptNumber', 2)
            ->assertJsonPath('workspace.history.2.attemptNumber', 1);

        $otherWorkspace = $this->actingAs($otherLearner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $otherWorkspace->assertOk()->assertJsonCount(0, 'workspace.learnerQuizzes');
        $this->actingAs($otherLearner)
            ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
            ->assertNotFound();

        $this->actingAs($learner)->withSession(['learner_login_token'=>$learner->fresh()->active_login_token])->putJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}", [
            'subject_ids' => [$subject->id], 'item_count' => 1,
            'timed' => false, 'time_limit_minutes' => null,
        ])->assertOk()->assertJsonPath('message', 'Practice test updated.');
        $quiz->refresh();
        $this->assertNull($quiz->time_limit_minutes);
        $this->assertSame(1, $quiz->questions()->count());
        $this->actingAs($otherLearner)->withSession(['learner_login_token'=>$otherLearner->fresh()->active_login_token])->putJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}", [
            'subject_ids' => [$subject->id], 'item_count' => 1,
            'timed' => false, 'time_limit_minutes' => null,
        ])->assertNotFound();

        $this->actingAs($learner)
            ->withSession(['learner_login_token'=>$learner->fresh()->active_login_token])
            ->deleteJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}")
            ->assertOk();
        $this->assertDatabaseMissing('test_bank_quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('test_bank_quiz_attempts', ['test_bank_quiz_id' => $quiz->id]);
    }

    public function test_learner_quiz_names_are_numbered_by_type_and_multiple_subjects_show_as_mixed(): void
    {
        extract($this->catalog());
        foreach ([$subject, $secondSubject] as $index => $quizSubject) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $quizSubject->id,
                'question' => "Naming question {$index}", 'options' => ['Correct', 'Wrong'],
                'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$quizSubject->id]);
        }
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        foreach ([10, 10, 20] as $itemCount) {
            $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes", [
                'subject_ids' => [$subject->id, $secondSubject->id],
                'item_count' => $itemCount, 'timed' => false, 'time_limit_minutes' => null,
            ])->assertOk();
        }

        $this->assertSame(
            ['Warm Up Quiz Set 1', 'Warm Up Quiz Set 2', 'Mastery Test #1'],
            $bank->premadeQuizzes()->where('quiz_type', 'learner')->oldest('id')->pluck('title')->all()
        );
        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace")->assertOk();
        $mastery = collect($workspace->json('workspace.learnerQuizzes'))->firstWhere('title', 'Mastery Test #1');
        $this->assertNotNull($mastery);
        $this->assertSame('Mixed', $mastery['coverageLabel']);
    }

    public function test_tracker_rank_uses_each_subscribers_best_completed_item_percentage(): void
    {
        extract($this->catalog());
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Ranking test', 'item_count' => 10, 'subject_ids' => [$subject->id],
            'status' => 'active', 'created_by' => $admin->id,
        ]);
        $learners = User::factory()->count(4)->create();
        foreach ($learners->take(3) as $learner) {
            $bank->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);
        }
        foreach ([[0, 6, false], [0, 8, false], [1, 9, false], [1, 10, true], [2, 8, false], [3, 10, false]] as [$index, $score, $cancelled]) {
            TestBankQuizAttempt::create([
                'user_id' => $learners[$index]->id, 'test_bank_id' => $bank->id,
                'test_bank_quiz_id' => $quiz->id, 'score' => $score, 'total' => 10,
                'points_earned' => $score, 'points_possible' => 10, 'passed' => true,
                'review_data' => $cancelled ? ['cancelled' => true] : [],
            ]);
        }
        $history = $this->actingAs($learners[0])->getJson("/api/test-banks/{$bank->id}/workspace")
            ->assertOk()->json('workspace.history');
        $this->assertSame([2, 2], array_column($history, 'rank'));
        foreach (['learner', 'subject'] as $quizType) {
            $custom = $bank->premadeQuizzes()->create([
                'title' => 'Custom ranking exclusion', 'item_count' => 10,
                'subject_ids' => [$subject->id], 'quiz_type' => $quizType,
                'owner_user_id' => $learners[0]->id, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            TestBankQuizAttempt::create([
                'user_id' => $learners[0]->id, 'test_bank_id' => $bank->id,
                'test_bank_quiz_id' => $custom->id, 'score' => 10, 'total' => 10,
                'points_earned' => 10, 'points_possible' => 10, 'passed' => true, 'review_data' => [],
            ]);
        }
        $history = $this->actingAs($learners[0])->getJson("/api/test-banks/{$bank->id}/workspace")
            ->assertOk()->json('workspace.history');
        $this->assertSame([null, null, 2, 2], array_column($history, 'rank'));
        $this->actingAs($learners[2])->getJson("/api/test-banks/{$bank->id}/workspace")
            ->assertOk()->assertJsonPath('workspace.history.0.rank', 2);
    }

    public function test_deleting_one_catalog_preserves_its_questions_for_another_catalog_in_the_same_course(): void
    {
        extract($this->catalog());
        $secondBank = TestBank::create([
            'course_id' => $course->id, 'title' => 'DOH Practice Bank', 'code' => 'DOH-TB-2',
            'price' => 1200, 'access_days' => 60, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Question that must survive', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);

        $this->actingAs($admin)->delete(route('admin.content.test-banks.destroy', [$course, $bank]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('test_bank_questions', [
            'id' => $question->id,
            'test_bank_id' => $secondBank->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_catalog_cannot_use_a_subject_from_another_course(): void
    {
        extract($this->catalog());
        $otherCourse = Course::create(['title' => 'Other']);
        $other = Subject::create(['course_id' => $otherCourse->id, 'subject_code' => 'OTHER', 'title' => 'Other', 'status' => 'approved']);
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.store', [$course, $bank]), [
            'subject_ids' => [$other->id], 'question' => 'Invalid?', 'options' => ['A', 'B'],
            'correct_answer' => 0,
        ])->assertNotFound();
        $this->assertDatabaseCount('test_bank_questions', 0);
    }
}
